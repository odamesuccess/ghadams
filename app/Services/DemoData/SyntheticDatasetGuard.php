<?php

namespace App\Services\DemoData;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Proves the target database is a local, isolated development database
 * before the synthetic dataset may write to it.
 */
class SyntheticDatasetGuard
{
    private const LOOPBACK_HOSTS = ['127.0.0.1', 'localhost', '::1'];

    /**
     * @return array{allowed: bool, reasons: array<int, string>, facts: array<string, mixed>}
     */
    public function inspect(): array
    {
        $reasons = [];
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $config = $connection->getConfig();
        $environment = (string) app()->environment();

        $facts = [
            'app_env' => $environment,
            'app_url' => (string) config('app.url'),
            'driver' => $driver,
            'host' => $config['host'] ?? null,
            'port' => $config['port'] ?? null,
            'database' => $connection->getDatabaseName(),
        ];

        if (app()->isProduction()) {
            $reasons[] = 'APP_ENV is production.';
        }

        if (! in_array($environment, (array) config('demo_dataset.allowed_environments', []), true)) {
            $reasons[] = "APP_ENV '{$environment}' is not an allowed demo environment.";
        }

        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $localAppHost = $appHost === ''
            || in_array($appHost, self::LOOPBACK_HOSTS, true)
            || str_ends_with($appHost, '.test')
            || str_ends_with($appHost, '.localhost');

        if (! $localAppHost) {
            $reasons[] = "APP_URL host '{$appHost}' is not a local development host.";
        }

        if ($driver === 'sqlite') {
            $facts['server_hostname'] = 'n/a (sqlite file or memory)';
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            $host = strtolower((string) ($config['host'] ?? ''));

            if (! in_array($host, self::LOOPBACK_HOSTS, true)) {
                $reasons[] = "DB_HOST '{$host}' is not a loopback address.";
            }

            // A loopback port can still be an SSH tunnel to a remote server.
            // The server itself reports which machine it runs on.
            try {
                $row = DB::selectOne('select @@hostname as h, @@datadir as d, @@version as v');
                $facts['server_hostname'] = $row->h ?? null;
                $facts['server_datadir'] = $row->d ?? null;
                $facts['server_version'] = $row->v ?? null;
                $facts['client_hostname'] = gethostname();

                if (strcasecmp((string) ($row->h ?? ''), (string) gethostname()) !== 0) {
                    $reasons[] = 'MySQL reports hostname "'.($row->h ?? '?').'" but this machine is "'.gethostname().'" (possible tunnel to another server).';
                }
            } catch (Throwable $e) {
                $reasons[] = 'Could not query the database server identity: '.$e->getMessage();
            }
        } else {
            $reasons[] = "Driver '{$driver}' is not supported by the demo dataset guard.";
        }

        return [
            'allowed' => $reasons === [],
            'reasons' => $reasons,
            'facts' => $facts,
        ];
    }
}
