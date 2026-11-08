<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

#[Signature('legacy:load-dump {path? : SQL dump path (default: database/import/dbs12809208-full.sql)} {--database=climbing_legacy : Target database name} {--force : Drop and recreate the database before import}')]
#[Description('Create the Sail legacy database and load a climbing-team SQL dump into it')]
class LegacyLoadDumpCommand extends Command
{
    public function handle(): int
    {
        $path = $this->argument('path') ?: database_path('import/dbs12809208-full.sql');
        $database = (string) $this->option('database');
        $force = (bool) $this->option('force');

        if (! is_file($path)) {
            $this->error("Dump not found: {$path}");

            return self::FAILURE;
        }

        $host = (string) config('database.connections.legacy.host', env('LEGACY_DB_HOST', env('DB_HOST', '127.0.0.1')));
        $port = (string) config('database.connections.legacy.port', env('LEGACY_DB_PORT', env('DB_PORT', '3306')));
        $username = (string) env('LEGACY_DB_USERNAME', env('DB_USERNAME', 'sail'));
        $password = (string) env('LEGACY_DB_PASSWORD', env('DB_PASSWORD', ''));
        // Sail sets MYSQL_ROOT_PASSWORD from DB_PASSWORD.
        $rootPassword = (string) env('DB_PASSWORD', $password);

        $this->info("Preparing database [{$database}] on {$host}:{$port}…");

        if ($force) {
            $drop = $this->mariadb($host, $port, 'root', $rootPassword, [
                "DROP DATABASE IF EXISTS `{$database}`;",
            ]);

            if ($drop !== 0) {
                return self::FAILURE;
            }
        }

        $prepare = $this->mariadb($host, $port, 'root', $rootPassword, [
            "CREATE DATABASE IF NOT EXISTS `{$database}`;",
            "GRANT ALL PRIVILEGES ON `{$database}`.* TO '{$username}'@'%';",
            'FLUSH PRIVILEGES;',
        ]);

        if ($prepare !== 0) {
            return self::FAILURE;
        }

        $this->info('Importing '.File::basename($path).' ('.number_format(File::size($path) / 1048576, 1).' MB)…');

        $import = Process::fromShellCommandline(
            sprintf(
                'mariadb --host=%s --port=%s --user=%s --password=%s %s < %s',
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($username),
                escapeshellarg($password),
                escapeshellarg($database),
                escapeshellarg($path),
            ),
        );
        $import->setTimeout(600);
        $import->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        if (! $import->isSuccessful()) {
            $this->error('Import failed.');
            $this->line($import->getErrorOutput());

            return self::FAILURE;
        }

        $this->info("Legacy dump loaded into [{$database}]. Point LEGACY_DB_DATABASE={$database} and run legacy:import / legacy:verify.");

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $statements
     */
    private function mariadb(string $host, string $port, string $username, string $password, array $statements): int
    {
        $process = new Process([
            'mariadb',
            '--host='.$host,
            '--port='.$port,
            '--user='.$username,
            '--password='.$password,
            '--execute='.implode(' ', $statements),
        ]);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error($process->getErrorOutput() ?: $process->getOutput());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
