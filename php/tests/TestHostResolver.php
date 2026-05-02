<?php
declare(strict_types=1);

final class TestHostResolver {
    private const array ENV_KEYS = [
        'OSAFW_TEST_HOST',
        'OSAFW_CLI_HOST',
    ];

    private const array AUTO_DETECT_PATTERNS = [
        '/^localhost\.php$/i',
        '/^osafw-php\.lo\.php$/i',
        '/^lo[-.].*\.php$/i',
    ];

    public static function resolve(?array $argv = null): string {
        $argv = $argv ?? ($_SERVER['argv'] ?? $GLOBALS['argv'] ?? []);

        $cliHost = self::extractCliHostArgument($argv);
        if (!is_null($cliHost)) {
            return $cliHost;
        }

        foreach (self::ENV_KEYS as $envKey) {
            $host = getenv($envKey);
            if ($host === false) {
                continue;
            }

            $host = trim((string)$host);
            if ($host !== '') {
                return self::validateHost($host, "environment variable {$envKey}");
            }
        }

        $envValues = self::readEnvFile(self::envFilePath());
        foreach (self::ENV_KEYS as $envKey) {
            if (!array_key_exists($envKey, $envValues)) {
                continue;
            }

            $host = trim((string)$envValues[$envKey]);
            if ($host !== '') {
                return self::validateHost($host, self::envFilePath() . " ({$envKey})");
            }
        }

        $host = self::autoDetectHost();
        if (!is_null($host)) {
            return $host;
        }

        throw new RuntimeException(self::missingHostMessage());
    }

    public static function applyToEnvironment(string $host, bool $setHttpHost = true): void {
        putenv('OSAFW_TEST_HOST=' . $host);
        putenv('OSAFW_CLI_HOST=' . $host);
        $_ENV['OSAFW_TEST_HOST'] = $host;
        $_ENV['OSAFW_CLI_HOST']  = $host;

        if ($setHttpHost) {
            $_SERVER['HTTP_HOST'] = $host;
        }
    }

    /**
     * @param array<int,string> $argv
     * @return array<int,string>
     */
    public static function stripCliHostArgument(array $argv): array {
        if (self::extractCliHostArgument($argv) === null) {
            return $argv;
        }

        array_splice($argv, 1, 1);
        return $argv;
    }

    /**
     * @param array<int,string> $argv
     */
    public static function extractCliHostArgument(array $argv): ?string {
        $candidate = $argv[1] ?? null;
        if (!is_string($candidate)) {
            return null;
        }

        $candidate = trim($candidate);
        if ($candidate === '' || !self::isValidHost($candidate)) {
            return null;
        }

        return $candidate;
    }

    private static function envFilePath(): string {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env.test.local';
    }

    private static function configsDir(): string {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'configs';
    }

    private static function autoDetectHost(): ?string {
        $configPaths = glob(self::configsDir() . DIRECTORY_SEPARATOR . '*.php') ?: [];
        if (!$configPaths) {
            return null;
        }

        $configFiles = [];
        foreach ($configPaths as $configPath) {
            $configFile = basename($configPath);
            if (strtolower($configFile) === 'config.php') {
                continue;
            }
            $configFiles[] = $configFile;
        }

        sort($configFiles, SORT_NATURAL | SORT_FLAG_CASE);

        foreach (self::AUTO_DETECT_PATTERNS as $pattern) {
            foreach ($configFiles as $configFile) {
                if (preg_match($pattern, $configFile)) {
                    return substr($configFile, 0, -4);
                }
            }
        }

        return null;
    }

    /**
     * @return array<string,string>
     */
    private static function readEnvFile(string $path): array {
        if (!is_file($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return [];
        }

        $values = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $key = trim($key);
            if ($key === '') {
                continue;
            }

            $values[$key] = self::trimEnvValue(trim($value));
        }

        return $values;
    }

    private static function trimEnvValue(string $value): string {
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last  = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                return substr($value, 1, -1);
            }
        }

        return $value;
    }

    private static function validateHost(string $host, string $source): string {
        if (self::isValidHost($host)) {
            return $host;
        }

        throw new RuntimeException("Invalid test host [{$host}] configured in {$source}.");
    }

    private static function isValidHost(string $host): bool {
        return (bool)filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);
    }

    private static function missingHostMessage(): string {
        $envFile = self::envFilePath();

        return implode(PHP_EOL, [
            'Unable to resolve a local PHPUnit host config.',
            'Resolution order:',
            '1. CLI hostname argument.',
            '2. OSAFW_TEST_HOST or OSAFW_CLI_HOST environment variable.',
            "3. {$envFile}.",
            '4. Auto-detect from php/configs/ using localhost.php, osafw-php.lo.php, or lo-* local configs.',
            '',
            'Create /.env.test.local with:',
            'OSAFW_TEST_HOST=localhost',
            '',
            'Or export OSAFW_TEST_HOST before running PHPUnit.',
        ]);
    }
}
