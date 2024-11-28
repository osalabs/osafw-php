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
     * @param string $uri
     * @return void
     */
    public static function initRequest(FW $fw, string $uri): void {

        if (!$fw->isOffline()) {

            session_start(); #session starts only here

            #permanent login support
            if (!$fw->userId()) {
                Users::i()->checkPermanentLogin();
            }

            #'also force set XSS code
            if (!isset($_SESSION['XSS'])) {
                $_SESSION['XSS'] = Utils::getRandStr(16);
            }
        }
    }

    //called before each fw->renderRoute called
    public static function beforeRenderRoute(stdClass $route) {
    }

    //called after each fw->renderRoute so you can add something to $ps output
    public static function afterRenderRoute(stdClass $route, ?array $ps) {
    }

    //called at the end of request processing
    public static function endRequest(FW $fw) {
    }

    /**
     * Default logger override, see fw::logger()
     *
     * @return bool if true returned - fw default logger won't trigger
     */
    public static function logger($args): bool {
        return false;
    }

    /**
     * Default exception handler override, see fw::handleException()
     * @param Exception $e
     * @return array|null if array returned - fw default exception handler won't trigger and array will be passed to user
     */
    public static function handleException(Exception $e): ?array {
        return null;
    }
}
