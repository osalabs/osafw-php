<?php
/*
Login Controller

Part of PHP osa framework  www.osalabs.com/osafw/php
(c) 2009-2025 Oleg Savchuk www.osalabs.com
*/

class LoginController extends FwController {
    const string route_default_action = '';

    public FwModel|Users $model;
    public string $model_name = 'Users';

    protected Google\Client $client;

    public function __construct() {
        parent::__construct();

        $this->base_url = '/Login';

        #override layout
        $this->fw->page_layout = $this->fw->config->PAGE_LAYOUT_PUBLIC;
    }

    public function IndexAction(): ?array {
        if ($this->fw->isLogged()) {
            fw::redirect($this->fw->config->LOGGED_DEFAULT_URL);
        }

        $item = reqh('item');
        if (!$item) {
            #defaults
            $item = array();
        }
        $ps = array(
            'i'            => $item,
            'hide_sidebar' => true,
        );

        return $ps;
    }

    public function SaveAction(): void {
        $item = reqh('item');

        try {
            $login = trim($item['login']);
            $pwd   = $item['pwdh'];
            if (($item["chpwd"] ?? '') == "1") {
                $pwd = $item['pwd'];
            }
            $pwd      = substr(trim($pwd), 0, 64);
            $gourl    = reqs('gourl');
            $remember = $item['remember'] ?? '';

            #for dev only - login as first admin
            $is_dev_login = false;
            if ($this->fw->config->IS_DEV === TRUE && $login == '' && $pwd == '~') {
                $dev          = $this->db->rowp("select email, pwd from users where status=0 and access_level=100 order by id limit 1");
                $login        = $dev['email'];
                $is_dev_login = true;
            }
            #for normal logins - have a delay up to 2s to slow down any brute force attempts
            if (!$is_dev_login) {
                $delay = intval((mt_rand() / mt_getrandmax() * 2 + 0.5) * 1000);
                usleep($delay * 1000);
            }

            if (!strlen($login) || !strlen($pwd)) {
                $this->setError("REGISTER");
                throw new ApplicationException("");
            }

            $user = Users::i()->oneByEmail($login);
            if (!$is_dev_login) {
                if (!$user || $user['status'] != 0 || !$this->model->checkPwd($pwd, $user['pwd'])) {
                    $this->fw->logActivity(FwLogTypes::ICODE_USERS_LOGIN_FAIL, FwEntities::ICODE_USERS, 0, $login);
                    throw new ApplicationException("User Authentication Error");
                }
            }

            $this->model->doLogin($user['id'], !empty($remember));

            if ($gourl && !preg_match("/^http/i", $gourl)) { #if url set and not external url (hack!) given
                fw::redirect($gourl);
            } else {
                fw::redirect($this->fw->config->LOGGED_DEFAULT_URL);
            }

        } catch (ApplicationException $ex) {
            $this->fw->GLOBAL['err_ctr'] = reqi('err_ctr') + 1;
            $this->setFormError($ex);
            $this->routeRedirect(FW::ACTION_INDEX);
        }
    }

    public function LogoffAction(): void {
        $this->fw->logActivity(FwLogTypes::ICODE_USERS_LOGOFF, FwEntities::ICODE_USERS, $this->fw->userId());

        @session_start();
        //delete session
        $_SESSION = array();
        session_destroy();

        $this->model->removePermCookie();

        fw::redirect($this->fw->config->UNLOGGED_DEFAULT_URL);
    }

    public function GoogleAction(): void {
        if (!$this->fw->config->IS_SIGNUP) {
            throw new ApplicationException("Sign Up denied by site config [IS_SIGNUP]");
        }

        $code     = reqs('code');
        $state    = reqs('state');
        $username = reqs("username");

        $this->client = new Google\Client();
        $this->client->setClientId($this->fw->config->GOOGLE['CLIENT_ID']);
        $this->client->setClientSecret($this->fw->config->GOOGLE['CLIENT_SECRET']);
        $this->client->setRedirectUri($this->fw->config->ROOT_DOMAIN . $this->base_url . '/(Google)');
        $this->client->addScope(Google\Service\Oauth2::USERINFO_EMAIL);
        $this->client->addScope(Google\Service\Oauth2::USERINFO_PROFILE);

        if (empty($code)) {
            $this->redirectToLogin();
        }

        @session_start();
        if (empty($_SESSION['oauth2state']) || ($state !== $_SESSION['oauth2state'])) {
            unset($_SESSION['oauth2state']);
            throw new ApplicationException("Invalid state");
            # or redirect back to google login
        }

        try {
            $token = $this->client->fetchAccessTokenWithAuthCode($code);
            if (array_key_exists('error', $token)) {
                throw new ApplicationException("Error fetching access token");
            }
            #token looks like:
            //array(
            //    'access_token'  => 'xxx',
            //    'expires_in'    => 3599,
            //    'refresh_token' => 'xxx',
            //    'scope'         => 'https://www.googleapis.com/auth/userinfo.email https://mail.google.com/ openid https://www.googleapis.com/auth/userinfo.profile',
            //    'token_type'    => 'Bearer',
            //    'id_token'      => 'xxx',
            //    'created'       => 1717174381,
            //);

            #$token = json_encode($token);
            #$this->client->setAccessToken($token);

            #get user info
            $oauth2 = new Google\Service\Oauth2($this->client);
            $user   = $oauth2->userinfo_v2_me;
            if (!$user) {
                throw new ApplicationException("Google account has no email");
            }

            #check if user already registered
            $email = trim($user['email']);
            if (!$email) {
                throw new ApplicationException("Google account has no email");
            }

            #get received scopes
            $scopes = $token['scope'];

            #check if user already registered
            $dbuser = Users::i()->oneByEmail($email);
            if ($dbuser) {
                $users_id = $dbuser['id'];
                #update scopes
                Users::i()->update($users_id, [
                    'oauth_scopes' => $scopes,
                ]);
            } else {
                #register
                $users_id = Users::i()->add([
                    'email'        => $email,
                    'fname'        => $user['name'],
                    'access_level' => Users::ACL_USER,
                    'status'       => Users::STATUS_ACTIVE,
                    'pwd'          => '', #empty password for google users
                    'oauth_scopes' => $scopes,
                ]);
            }

            #login
            Users::i()->doLogin($users_id);
            fw::redirect($this->fw->config->LOGGED_DEFAULT_URL);

        } catch (InvalidArgumentException $e) {
            $err = $e->getMessage();
            logger($err);
            if ($err != 'NO_CODE') {
                logger('WARN', '[Handled] Google auth: ' . $err, $e);
                $this->client->setPrompt("consent");
            }
            if ($username) {
                $this->client->setLoginHint($username); // If username (email) is known pass it to Google oAuth - Google will narrow down consent screen to the selected email
            }
            // InvalidArgumentException here happens if Google cannot auth due to bad/invalid token, so we just redirect user to auth again
            $this->redirectToLogin();

        } catch (Exception $ex) {
            logger('ERROR', 'Google auth: ' . $ex->getMessage(), $ex);
            $this->redirectToLogin();
        }
    }

    private function redirectToLogin(string $warning = ''): void {
        if (!empty($warning)) {
            logger('WARN', $warning);
        }

        # to prevent CSRF attacks
        # https://auth0.com/docs/protocols/state-parameters
        $state_token = Utils::getRandStr(16);
        $this->client->setState($state_token);
        @session_start();
        $_SESSION['oauth2state'] = $state_token;
        session_write_close();

        $authUrl = $this->client->createAuthUrl();

        logger("Google OAuth2: redirecting to [$authUrl]");
        fw::redirect($authUrl);
    }

}//end of class
