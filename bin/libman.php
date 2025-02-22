<?php
/*
LibMan install asset libraries
 */

require_once dirname(__FILE__) . "/../www/php/fw/fw.php";

fw::initOffline();

$jsonPath = fw::i()->config->SITE_ROOT . '/php/libman.json';
$rootPath = fw::i()->config->SITE_ROOT;

$libman = new PhpLibMan($jsonPath, $rootPath);
$libman->install();

fw::endRequest();