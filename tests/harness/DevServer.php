<?php
declare(strict_types=1);

namespace CampusFlow\Tests\Harness;

class DevServer {
    private static mixed $process = null;
    private static ?string $serverUrl = 'http://localhost:8000';

    public static function isRunning(string $url = 'http://localhost:8000'): bool {
        $ch = curl_init($url . '/login.php');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $res !== false && $code > 0;
    }

    public static function ensureRunning(string $projectRoot, string $phpPath = 'C:\\xampp\\php\\php.exe'): string {
        if (self::isRunning()) {
            return self::$serverUrl;
        }

        Env::load($projectRoot);

        // Check if PHP executable exists
        if (!file_exists($phpPath)) {
            // Try resolving php from path
            $phpPath = 'php';
        }

        $cmd = "\"{$phpPath}\" -d extension=pdo_pgsql -S localhost:8000 router.php";

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];

        // Pass current environment with env variables loaded
        $env = array_merge($_ENV, [
            'DATABASE_URL' => Env::get('DATABASE_URL', ''),
            'ADMIN_PASSWORD' => Env::get('ADMIN_PASSWORD', ''),
            'SUPABASE_URL' => Env::get('SUPABASE_URL', ''),
            'SUPABASE_SERVICE_ROLE_KEY' => Env::get('SUPABASE_SERVICE_ROLE_KEY', ''),
            'SUPABASE_STORAGE_BUCKET' => Env::get('SUPABASE_STORAGE_BUCKET', 'campusflow-resources')
        ]);

        self::$process = proc_open($cmd, $descriptors, $pipes, $projectRoot, $env);

        // Wait up to 5 seconds for server to respond
        $start = microtime(true);
        while (microtime(true) - $start < 5.0) {
            usleep(150000);
            if (self::isRunning()) {
                break;
            }
        }

        register_shutdown_function([self::class, 'stop']);
        return self::$serverUrl;
    }

    public static function stop(): void {
        if (is_resource(self::$process)) {
            $status = proc_get_status(self::$process);
            if ($status['running']) {
                // Windows taskkill /F /PID
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                    exec("taskkill /F /T /PID " . $status['pid'] . " 2>&1");
                } else {
                    proc_terminate(self::$process);
                }
            }
            proc_close(self::$process);
            self::$process = null;
        }
    }
}
