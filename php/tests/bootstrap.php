<?php
declare(strict_types=1);

require_once __DIR__ . '/TestHostResolver.php';

try {
    $testHost = TestHostResolver::resolve();
} catch (RuntimeException $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}

TestHostResolver::applyToEnvironment($testHost);

require_once __DIR__ . '/../fw/fw.php';
require_once __DIR__ . '/../fw/FwController.php';
require_once __DIR__ . '/../fw/FwAdminController.php';
require_once __DIR__ . '/../fw/FwDynamicController.php';
require_once __DIR__ . '/../fw/FwApiController.php';
require_once __DIR__ . '/../fw/FwVueController.php';

fw::initOffline(__FILE__);

$fw      = fw::i();
$logFile = dirname(__DIR__, 2) . '/logs/test.log';
$fw->config->LOG_DESTINATION = $logFile;
$fw->config->LOG_LEVEL       = 'DEBUG';

global $CONFIG;
$CONFIG['LOG_DESTINATION'] = $logFile;
$CONFIG['LOG_LEVEL']       = 'DEBUG';
ini_set('error_log', $logFile);

logger('NOTICE', 'PHPUnit bootstrap initialized');
