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
    protected ?object $jwt_payload = null; #decoded JWT payload

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
     * @return void
     * @throws AuthException
     */
    protected function auth(): void {
        $result = false;

        #alternative 1 - check if user logged via PHP session
        $result = $this->authSession();

        #alternative 2 - simple API KEY - basic authentication
        if (!$result && !empty($this->fw->config->API_KEY)) {
            $result = $this->authApiKey();
        }

        #alternative 3 - JWT - using Firebase JWT library
        if (!$result && !empty($this->fw->config->JWT_SECRET)) {
            $result = $this->authJWT();
        }

        #$result=true; #DEBUG
        if (!$result) {
            throw new AuthException("API auth error", 401);
        }
    }

    /**
     * Authenticate via session
     * @return bool
     */
    protected function authSession(): bool {
        if (!$this->fw->isLogged()) {
            return false;
        }
        return true;
    }

    /**
     * Authenticate via API key
     * @return bool
     */
    protected function authApiKey(): bool {
        $result      = false;
        $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $api_key     = $this->fw->config->API_KEY;
        #get and decode API key from header
        $api_key_header = base64_decode(str_replace('Basic ', '', $auth_header));
        if ($api_key_header == $api_key) {
            $result = true;
        }
        return $result;
    }

    /**
     * Authenticate via JWT token
     * @return bool
     * @throws AuthException
     */
    protected function authJWT(): bool {
        $result = false;

        #check if Firebase JWT library is loaded
        if (!class_exists('\Firebase\JWT\JWT')) {
            logger("Firebase JWT library not loaded");
            return false;
        }

        $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s(\S+)/', $auth_header, $matches)) {
            $encoded_token = $matches[1];

            \Firebase\JWT\JWT::$leeway = 30; # allow 30 seconds difference
            try {
                $payload = \Firebase\JWT\JWT::decode($encoded_token, $this->fw->config->JWT_SECRET, ['HS256']);
                #validate payload
                //                if (isset($payload->exp) && DateUtils::isExpired($payload->exp, 0)) {
                //                    throw new AuthException("JWT token expired", 401); # exp - expiration time
                //                }
                //                if (isset($payload->nbf) && !DateUtils::isExpired($payload->nbf, 0)) {
                //                    throw new AuthException("JWT token not yet valid", 401); # nbf - not before time
                //                }

                if (isset($payload->iss) && $payload->iss != $this->fw->config->JWT_ISSUER) {
                    throw new AuthException("JWT token issuer invalid", 401); # iss - issuer
                }
                if (isset($payload->aud) && $payload->aud != $this->fw->config->JWT_AUDIENCE) {
                    throw new AuthException("JWT token audience invalid", 401); # aud - audience
                }
                $this->jwt_payload = $payload;

                $result = true;
            } catch (\Firebase\JWT\BeforeValidException $e) {
                throw new AuthException("JWT token not yet valid", 401);
            } catch (\Firebase\JWT\ExpiredException $e) {
                throw new AuthException("JWT token expired", 401);
            } catch (\Firebase\JWT\SignatureInvalidException $e) {
                throw new AuthException("JWT signature invalid", 401);
            } catch (\Exception $e) {
                // InvalidArgumentException
                // DomainException
                // UnexpectedValueException
                throw new AuthException("JWT processing error", 401);
            }
        }
        return $result;
    }

    /**
     * Generate JWT token for user data
     * @param array $user
     * @return string
     */
    public function generateJWT(array $user): string {
        $payload = array(
            "sub"  => "API",
            "user" => $user, #TODO check if accepts array or only scalar values
            "iat"  => time(),
            "nbf"  => time(),
            "exp"  => time() + ($this->fw->config->JWT_EXPIRATION ?? 3600), # default 1h
            #other options:
            # "uid" => $user['id'],
            # "uname" => $user['uname'],
            # "email" => $user['email'],
            # "roles" => $user['roles'],
            # "ip" => $_SERVER['REMOTE_ADDR'],
            # "ua" => $_SERVER['HTTP_USER_AGENT'],
            # "jti" => uniqid(),
        );

        if (isset($this->fw->config->JWT_ISSUER)) {
            $payload['iss'] = $this->fw->config->JWT_ISSUER;
        }
        if (isset($this->fw->config->JWT_AUDIENCE)) {
            $payload['aud'] = $this->fw->config->JWT_AUDIENCE;
        }

        $token = \Firebase\JWT\JWT::encode($payload, $this->fw->config->JWT_SECRET, 'HS256');
        return $token;
    }

    /**
     * OPTIONS request handler
     * @return void
     */
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
