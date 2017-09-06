<?php
/*
 Different hooks called by fw during request run

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2015 Oleg Savchuk www.osalabs.com

*/
class FwHooks {
    //general global initializations before route dispatched
    public static function initRequest() {
        $me_id=Utils::me();

        #permanent login support
        if (!$me_id){
           fw::model('Users')->checkPermanentLogin();
           $me_id=Utils::me();
        }

        #if (!isset($_SESSION['categories'])) $_SESSION['categories']=fw::model('Categories')->ilist();
        #'also force set XSS code
        if (!isset($_SESSION['XSS'])) $_SESSION['XSS']=Utils::getRandStr(16);
    }

}

?>