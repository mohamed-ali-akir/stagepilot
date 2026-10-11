<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->backups = sys_get_temp_dir().'/stagepilot-backups-'.uniqid();
    config(['stagepilot.backup.path' => $this->backups, 'stagepilot.backup.keep' => 14]);
});

afterEach(function () {
    File::deleteDirectory($this->backups);
});

it('shows the legal page to everyone, linked from the home page', function () {
    $this->get('/')->assertSee(route('legal'));

    $this->get(route('legal'))
        ->assertOk()
        ->assertSee('ALWAYSDATA')
        ->assertSee(config('stagepilot.contact_email'))
        ->assertSee('CNIL');
});

it('deletes the CV file and the API tokens with the account', function () {
    Storage::fake('local');
    $user = student();
    Storage::disk('local')->put('cvs/cv-'.$user->id.'.pdf', '%PDF');
    $user->profile->update(['cv_path' => 'cvs/cv-'.$user->id.'.pdf']);
    $user->createToken('test');

    $user->delete();

    Storage::disk('local')->assertMissing('cvs/cv-'.$user->id.'.pdf');
    expect(DB::table('personal_access_tokens')->count())->toBe(0);
});

it('dumps MariaDB without putting the password on the command line', function () {
    Process::fake(['*' => Process::result('-- MariaDB dump')]);
    config(['database.connections.backup_test' => [
        'driver' => 'mariadb', 'host' => 'db.example.test', 'port' => 3306,
        'database' => 'stagepilot', 'username' => 'akir', 'password' => 'secret-pw',
    ]]);

    $this->artisan('stagepilot:backup', ['--connection' => 'backup_test'])->assertSuccessful();

    Process::assertRan(fn ($process) => in_array('--single-transaction', $process->command)
        && in_array('stagepilot', $process->command)
        && ! str_contains(implode(' ', $process->command), 'secret-pw')
        && $process->environment['MYSQL_PWD'] === 'secret-pw');
    $files = File::glob($this->backups.'/stagepilot-*.sql.gz');
    expect($files)->toHaveCount(1)
        ->and(gzdecode(File::get($files[0])))->toStartWith('-- MariaDB dump');
});

it('fails loudly when the dump fails', function () {
    Process::fake(['*' => Process::result(errorOutput: 'Access denied', exitCode: 2)]);
    config(['database.connections.backup_test' => [
        'driver' => 'mariadb', 'host' => 'h', 'port' => 3306, 'database' => 'd', 'username' => 'u', 'password' => 'p',
    ]]);

    $this->artisan('stagepilot:backup', ['--connection' => 'backup_test'])
        ->expectsOutputToContain('Access denied')
        ->assertFailed();
    expect(File::glob($this->backups.'/*'))->toBeEmpty();
});

it('copies a SQLite database file', function () {
    $database = sys_get_temp_dir().'/stagepilot-backup-test-'.uniqid().'.sqlite';
    touch($database);
    config(['database.connections.backup_sqlite' => ['driver' => 'sqlite', 'database' => $database, 'prefix' => '']]);
    DB::connection('backup_sqlite')->statement('create table notes (body text)');
    DB::connection('backup_sqlite')->insert('insert into notes values (?)', ['bonjour']);

    $this->artisan('stagepilot:backup', ['--connection' => 'backup_sqlite'])->assertSuccessful();

    DB::purge('backup_sqlite');
    File::delete($database);
    $files = File::glob($this->backups.'/stagepilot-*.sqlite.gz');
    expect($files)->toHaveCount(1)
        ->and(gzdecode(File::get($files[0])))->toStartWith('SQLite format 3')->toContain('bonjour');
});

it('refuses an in-memory database', function () {
    $this->artisan('stagepilot:backup')->assertFailed();
});

it('keeps only the 14 most recent backups', function () {
    Process::fake(['*' => Process::result('-- dump')]);
    config(['database.connections.backup_test' => [
        'driver' => 'mariadb', 'host' => 'h', 'port' => 3306, 'database' => 'd', 'username' => 'u', 'password' => 'p',
    ]]);
    File::ensureDirectoryExists($this->backups);
    foreach (range(1, 15) as $day) {
        File::put($this->backups.sprintf('/stagepilot-2026-09-%02d-0230.sql.gz', $day), 'old');
    }

    $this->artisan('stagepilot:backup', ['--connection' => 'backup_test'])->assertSuccessful();

    $files = collect(File::glob($this->backups.'/stagepilot-*.gz'))->map(fn ($f) => basename($f));
    expect($files)->toHaveCount(14)
        ->not->toContain('stagepilot-2026-09-01-0230.sql.gz', 'stagepilot-2026-09-02-0230.sql.gz')
        ->toContain('stagepilot-'.now()->format('Y-m-d-Hi').'.sql.gz');
});

it('is scheduled every night', function () {
    $events = collect(app(Schedule::class)->events());

    expect($events->first(fn ($e) => str_contains($e->command, 'stagepilot:backup'))?->expression)->toBe('30 2 * * *');
});
