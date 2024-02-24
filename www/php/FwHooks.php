<?php

/*
 Different hooks called by fw during request run

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2024 Oleg Savchuk www.osalabs.com

*/

class FwHooks {
    /**
     * general global initializations before route dispatched
     * @param FW $fw
     * @return void
     */
    public static function initRequest(FW $fw): void {

        if (!$fw->isOffline()) {
            $me_id = Utils::me();

            #permanent login support
            if (!$me_id) {
                Users::i()->checkPermanentLogin();
                $me_id = Utils::me();
            }

            #'also force set XSS code
            if (!isset($_SESSION['XSS']))
                $_SESSION['XSS'] = Utils::getRandStr(16);
        }
    }

    /**
     * Default logger override, see fw::logger()
     *
     * @return bool, if true returned - fw default logger won't trigger
     */
    public static function logger($args): bool {
        return false;
    }

}
