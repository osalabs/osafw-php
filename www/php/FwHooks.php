<?php
/*
 Different hooks called by fw during request run

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2015 Oleg Savchuk www.osalabs.com

*/
class FwHooks {
    //general global initializations before route dispatched
    public static function initRequest($fw) {
        if (!$fw->isOffline()) {
            $me_id=Utils::me();

            #permanent login support
            if (!$me_id){
               fw::model('Users')->checkPermanentLogin();
               $me_id=Utils::me();
            }

            #also force set XSS code
            if (!isset($_SESSION['XSS'])) $_SESSION['XSS']=Utils::getRandStr(16);
            if ($me_id) Users::i()->loadMenuItems();
        }
    }

    //called before each fw->renderRoute called
    public static function beforeRenderRoute($route) {
        // logger("**** BEFORE RENDER ROUTE");
    }

    //called after each fw->renderRoute so you can add something to $ps output
    public static function afterRenderRoute($route, &$ps) {
        // logger("**** AFTER RENDER ROUTE");
    }

    /**
     * hook for handleRouteError to additionally handle exceptions happened during request
     * @param $ex Exception
     * @return array $ps or false
     */
    public static function handleRouteException($ex) {
        // $code    = $ex->getCode();
        // return $ps;
        return false;
    }

    /**
     * Default logger override, see fw::logger()
     *
     * @return true, if true returned - fw default logger won't trigger
     */
    public static function logger($args) {
    }
}

?>