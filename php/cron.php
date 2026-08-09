<?php
/*
 Sample crontab script
 Launch every: X minutes
 Description: do something regularly offline

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2018 Oleg Savchuk www.osalabs.com
*/

require_once dirname(__FILE__) . "/fw/fw.php";

$fw = fw::i(); #get fw instance
if (!$fw->isOffline()) {
    exit; #prevent run from web browser url
}

#Notifications::i()->sendCron(); #sample model->method call

?>
