<?php
/*
Base Fw Api Controller class for building APIs

Part of PHP osa framework  www.osalabs.com/osafw/php
(c) 2009-2024 Oleg Savchuk www.osalabs.com
*/

class FwApiController extends FwController {
    #set in child class:
    #public MODELCLASS $model;
    #public string $model_name = 'MODELCLASS';
    #public $base_url = '/Api/SomeController';

    protected string $http_origin = '';

    public function __construct() {
        parent::__construct();

        #$this->http_origin = $_SERVER['HTTP_ORIGIN']; #use this if API consumed by external sites
        $this->http_origin = $this->fw->config->ROOT_DOMAIN; #use this if API consumed by the same site only

        if ($this->fw->route->method != 'OPTIONS') {
            $this->prepare(true); #auth+set headers
        }

    }

    /**
     * Prepare API call - set headers and auth
     * @param bool $is_auth - if true - also check auth
     * @throws AuthException
     */
    protected function prepare(bool $is_auth = true): void {
        $this->setHeaders();

        if ($is_auth) {
            $this->auth();
        }
    }

    /**
     * Set standard headers for API
     * @return void
     */
    protected function setHeaders(): void {
        header("Access-Control-Allow-Origin: {$this->http_origin}");
        header("Access-Control-Allow-Credentials: true");
    }

    /**
     * Set standard headers for API OPTIONS requests
     * @return void
     */
    protected function setHeadersOptions(): void {
        header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization");
        #header("Access-Control-Allow-Headers: Access-Control-Allow-Headers, Origin,Accept, X-Requested-With, Content-Type, Access-Control-Request-Method, Access-Control-Request-Headers");
        #header("Access-Control-Max-Age: 86400");
    }

    /**
     * authenticate by Session or API key or JWT
     * TODO
     * @return bool
     * @throws AuthException
     */
    protected function auth(): bool {
        $result = false;

        #check if user logged
        if ($this->fw->isLogged()) {
            $result = true;
        }

        #$result=true; #DEBUG
        if (!$result) {
            throw new AuthException("API auth error", 401);
        }

        return $result;
    }

    public function setApiError($ex, &$ps): void {
        logger("FATAL", $ex);
        $ps['success'] = false;
        $ps['err_msg'] = $ex->getMessage();
        header("HTTP/1.1 " . $ex->getCode() . " " . $ex->getMessage()); #also set HTTP status code
    }

    #used for prefight OPTIONS requests
    public function OptionsAction(): void {
        $this->setHeaders();
        $this->setHeadersOptions();
        echo "";
    }

    //sample API method /Api/SomeController/(Test)/$form_id
    // public function TestAction($form_id = '') {
    //     $id = intval($form_id);
    //     $ps = array(
    //         'success' => true,
    //     );

    //     try {
    //         //do something
    //     } catch (Exception $ex) {
    //         $ps['success'] = false;
    //         $ps['err_msg'] = $ex->getMessage();
    //     }

    //     return ['_json' => $ps];
    // }
}
