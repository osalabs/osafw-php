<?php
/*
Base Fw Api Controller class for building APIs

Part of PHP osa framework  www.osalabs.com/osafw/php
(c) 2009-2024 Oleg Savchuk www.osalabs.com
*/

class FwApiController extends FwController {
    #public $base_url = '/Api/SomeController'; #SET IN CHILD CLASS

    public function __construct() {
        parent::__construct();

        if ($this->fw->route->method != 'OPTIONS') {
            $this->prepare(true); #auth+set headers
        }

        #$this->model_name='SET IN CHILD CLASS';
    }

    protected function prepare($is_auth = true) {
        $this->setHeaders();

        if ($is_auth) {
            $this->auth();
        }
    }

    protected function setHeaders() {
        #$http_origin = $_SERVER['HTTP_ORIGIN'];
        $http_origin = $this->fw->config->ROOT_DOMAIN;
        header("Access-Control-Allow-Origin: $http_origin");
        header("Access-Control-Allow-Credentials: true");
    }

    protected function setHeadersOptions() {
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
        #header("Access-Control-Allow-Headers: Access-Control-Allow-Headers, Origin,Accept, X-Requested-With, Content-Type, Access-Control-Request-Method, Access-Control-Request-Headers");
        #header("Access-Control-Max-Age: 86400");
    }

    //auth by session key passed
    protected function auth() {
        $result = false;

        #check if user logged
        if ($_SESSION['is_logged']) {
            $result = true;
        }

        #$result=true; #DEBUG
        if (!$result) {
            throw new AuthException("API auth error", 401);
        }

        return $result;
    }

    public function setApiError($ex, &$ps) {
        logger("FATAL", $ex);
        $ps['success'] = false;
        $ps['err_msg'] = $ex->getMessage();
    }

    public function OptionsAction() {
        $this->setHeaders(); #set std headers
        $this->setHeadersOptions();
        echo "";
    }

    //sample API method /Api/SomeController/(Test)/$form_id
    // public function TestAction($form_id = '') {
    //     $id = $form_id + 0;
    //     $ps = array(
    //         '_json'   => true,
    //         'success' => true,
    //     );

    //     try {
    //         //do something
    //     } catch (Exception $ex) {
    //         $ps['success'] = false;
    //         $ps['err_msg'] = $ex->getMessage();
    //     }

    //     return $ps;
    // }
}
