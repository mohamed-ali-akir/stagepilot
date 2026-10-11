<?php

/*
 * One-off migration of the production data from SQLite to MySQL / MariaDB (run on 2026-10-11).
 *
 * Plan (no data loss):
 *   1. copy database/database.sqlite to a backup folder;
 *   2. php artisan down (the scheduler and the site stop writing);
 *   3. create the tables in MySQL: DB_CONNECTION=mariadb ... php artisan migrate --force;
 *   4. this script: copies every row, table by table, then compares the number of rows;
 *   5. switch DB_CONNECTION in .env, php artisan optimize, php artisan up.
 *
 * Usage (MySQL settings given as environment variables, so .env still points to SQLite):
 *   DB_CONNECTION=mariadb DB_HOST=... DB_DATABASE=... DB_USERNAME=... php deploy/sqlite_to_mysql.php
 */

use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

config(['database.connections.source' => [
    'driver' => 'sqlite',
    'database' => __DIR__.'/../database/database.sqlite',
    'foreign_key_constraints' => false,
]]);
$source = DB::connection('source');
$target = DB::connection(); // the MySQL / MariaDB connection from the environment

// temporary data that is not worth moving (sessions are logged out, the cache rebuilds itself)
$skip = ['migrations', 'sessions', 'cache', 'cache_locks', 'sqlite_sequence'];
$tables = collect($source->select("select name from sqlite_master where type = 'table' order by name"))
    ->pluck('name')
    ->reject(fn ($t) => in_array($t, $skip, true))
    ->values();

$target->statement('SET FOREIGN_KEY_CHECKS=0'); // rows are inserted table by table, in any order
$errors = 0;
foreach ($tables as $table) {
    if (! $target->getSchemaBuilder()->hasTable($table)) {
        echo "! table absente dans MySQL : {$table}\n";
        $errors++;

        continue;
    }
    $target->table($table)->delete();
    $rows = $source->table($table)->get()->map(fn ($row) => (array) $row);
    foreach ($rows->chunk(200) as $chunk) {
        $target->table($table)->insert($chunk->all());
    }
    $copied = $target->table($table)->count();
    $status = $copied === $rows->count() ? 'ok' : 'DIFFÉRENCE';
    if ($status !== 'ok') {
        $errors++;
    }
    printf("%-24s %5d -> %5d  %s\n", $table, $rows->count(), $copied, $status);
}
$target->statement('SET FOREIGN_KEY_CHECKS=1');

echo $errors === 0 ? "Copie terminée sans différence.\n" : "{$errors} problème(s) : ne pas basculer.\n";
exit($errors === 0 ? 0 : 1);
