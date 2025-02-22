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
        if ($fw->config->LOG_SENTRY_DSN) {
            Utils::initSentry($fw->config->LOG_SENTRY_DSN, '', '');
        }

        if (!$fw->isOffline()) {
            #check for dual mode
            if (!is_null($fw->config->IS_API)) {
                #ensure that we only access api or non-api routes
                if ($fw->config->IS_API) {
                    #api mode - uri should start with /vXXX
                    if (!preg_match('/^\/v\d+/', $uri)) {
                        logger("attempt to access non-api uri: $uri");
                        header("HTTP/1.1 404 Not Found");
                        echo "API endpoint not found";
                        exit; #hard stop
                    }
                    $_SERVER['HTTP_ACCEPT'] = 'application/json'; #force json response for API
                } else {
                    #non-api mode - uri should not start with /vXXX
                    if (preg_match('/^\/v\d+/', $uri)) {
                        logger("attempt to access api uri: $uri");
                        header("HTTP/1.1 404 Not Found");
                        exit; #hard stop
                    }
                }
            }

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
