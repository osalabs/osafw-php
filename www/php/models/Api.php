<?php
/*
 Api model class

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2020 Oleg Savchuk www.osalabs.com

 */

class Api extends FwModel {
    /** @return Api */
    public static function i() {
        return parent::i();
    }

    //auth by session key passed
    public function auth() {
        $result = false;

        #check if user logged
        if ($_SESSION['is_logged']) {
            $result = true;
        }

        #$result=true; #DEBUG
        if (!$result) {
            throw new ApplicationException("API auth error", 401);
        }

        return $result;
    }

    public function setHeaders() {
        #$http_origin = $_SERVER['HTTP_ORIGIN'];
        $http_origin = $this->fw->config->ROOT_DOMAIN;
        header("Access-Control-Allow-Origin: $http_origin");
        header("Access-Control-Allow-Credentials: true");
    }

    public function setHeadersOptions() {
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
        #header("Access-Control-Allow-Headers: Access-Control-Allow-Headers, Origin,Accept, X-Requested-With, Content-Type, Access-Control-Request-Method, Access-Control-Request-Headers");
        #header("Access-Control-Max-Age: 86400");
    }

    public function prepare($is_auth = true) {
        $this->setHeaders();

        if ($is_auth) {
            $this->auth();
        }
    }

}
