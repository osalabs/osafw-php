<?php
declare(strict_types=1);

require_once __DIR__ . '/TestHostResolver.php';

$testsDir = __DIR__;
$phpDir   = dirname($testsDir);
$rootDir  = dirname($phpDir, 2);

$phpunitArgs = [
    __FILE__,
    '--configuration',
    $rootDir . DIRECTORY_SEPARATOR . 'phpunit.xml',
];

try {
    $cliArgs  = $_SERVER['argv'] ?? $GLOBALS['argv'] ?? [];
    $testHost = TestHostResolver::resolve($cliArgs);
} catch (RuntimeException $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}

TestHostResolver::applyToEnvironment($testHost, false);

$cliArgs = TestHostResolver::stripCliHostArgument($cliArgs);
if (count($cliArgs) > 1) {
    $phpunitArgs = array_merge($phpunitArgs, array_slice($cliArgs, 1));
}

$_SERVER['argv'] = $phpunitArgs;
$GLOBALS['argv'] = $phpunitArgs;
chdir($rootDir);

$autoload = $phpDir . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, 'Composer dependencies are not installed. Run composer install from php with dev dependencies enabled.' . PHP_EOL);
    exit(1);
}

require $autoload;

if (!class_exists(PHPUnit\TextUI\Application::class)) {
    fwrite(STDERR, 'PHPUnit is not installed. Run composer install from php with dev dependencies enabled.' . PHP_EOL);
    exit(1);
}

exit((new PHPUnit\TextUI\Application())->run($phpunitArgs));
