<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

#[Signature('stagepilot:backup {--connection= : connexion à sauvegarder (par défaut celle du site)}')]
#[Description('Sauvegarde la base de données (fichier .sql.gz daté) et garde les 14 dernières')]
class BackupDatabase extends Command
{
    public function handle(): int
    {
        $connection = DB::connection($this->option('connection') ?: null);
        $folder = config('stagepilot.backup.path');
        File::ensureDirectoryExists($folder);
        $base = $folder.'/stagepilot-'.now()->format('Y-m-d-Hi');

        if ($connection->getDriverName() === 'sqlite') {
            if ($connection->getDatabaseName() === ':memory:') {
                $this->error('Base en mémoire : rien à sauvegarder.');

                return self::FAILURE;
            }
            // VACUUM INTO writes a consistent copy, even while the site is using the database
            $connection->statement('VACUUM INTO ?', [$base.'.sqlite']);
            $file = $this->compress($base.'.sqlite', (string) file_get_contents($base.'.sqlite'));
            File::delete($base.'.sqlite');
        } else {
            $config = $connection->getConfig();
            // the password goes through the environment, never on the command line (visible in the process list)
            $dump = Process::env(['MYSQL_PWD' => (string) ($config['password'] ?? '')])
                ->timeout(300)
                ->run([
                    config('stagepilot.backup.dump_binary'), '--single-transaction', '--quick', '--no-tablespaces',
                    '--host='.$config['host'], '--port='.$config['port'], '--user='.$config['username'], $config['database'],
                ]);
            if ($dump->failed()) {
                $this->error('La sauvegarde a échoué : '.trim($dump->errorOutput()));

                return self::FAILURE;
            }
            $file = $this->compress($base.'.sql', $dump->output());
        }

        $removed = $this->keepLatest($folder, (int) config('stagepilot.backup.keep'));
        $this->info('Sauvegarde : '.basename($file).' ('.round(filesize($file) / 1024).' Ko)'.($removed ? ", {$removed} ancienne(s) supprimée(s)" : ''));

        return self::SUCCESS;
    }

    private function compress(string $path, string $content): string
    {
        File::put($path.'.gz', (string) gzencode($content, 9));

        return $path.'.gz';
    }

    /** Deletes the oldest backups beyond $keep. Returns how many were deleted. */
    private function keepLatest(string $folder, int $keep): int
    {
        $old = collect(File::glob($folder.'/stagepilot-*.gz'))->sort()->reverse()->slice($keep);
        File::delete($old->all());

        return $old->count();
    }
}
