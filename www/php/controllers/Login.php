<?php
/*
Login Controller

Part of PHP osa framework  www.osalabs.com/osafw/php
(c) 2009-2024 Oleg Savchuk www.osalabs.com
*/

class LoginController extends FwController {
    const string route_default_action = '';
    public string $model_name = 'Users';

    public function __construct() {
        parent::__construct();

        #override layout
        $this->fw->page_layout = $this->fw->config->PAGE_LAYOUT_PUBLIC;
    }

    public function IndexAction(): ?array {
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

    public function SaveAction() {
        $item = reqh('item');

        try {
            $login = trim($item['login']);
            $pwd   = $item['pwdh'];
            if (($item["chpwd"] ?? '') == "1") {
                $pwd = $item['pwd'];
            }
            $pwd   = substr(trim($pwd), 0, 64);
            $gourl = reqs('gourl');

            #for dev only - login as first admin
            $is_dev_login = false;
            if ($this->fw->config->IS_DEV === TRUE && $login == '' && $pwd == '~') {
                $dev          = $this->db->rowp("select email, pwd from users where status=0 and access_level=100 order by id limit 1");
                $login        = $dev['email'];
                $is_dev_login = true;
            }

            if (!strlen($login) || !strlen($pwd)) {
                $this->setError("REGISTER", True);
                throw new ApplicationException("");
            }

            $user = Users::i()->oneByEmail($login);
            if (!$is_dev_login) {
                if (!$user || $user['status'] != 0 || !$this->model->checkPwd($pwd, $user['pwd'])) {
                    throw new ApplicationException(lng("User Authentication Error"));
                }
            }

            $this->model->doLogin($user['id']);

            if ($gourl && !preg_match("/^http/i", $gourl)) { #if url set and not external url (hack!) given
                fw::redirect($gourl);
            } else {
                fw::redirect($this->fw->config->LOGGED_DEFAULT_URL);
            }

        } catch (ApplicationException $ex) {
            $this->fw->GLOBAL['err_ctr'] = reqi('err_ctr') + 1;
            $this->setFormError($ex);
            $this->routeRedirect("Index");
        }
    }

    public function LogoffAction() {
        $this->fw->logActivity(FwLogTypes::ICODE_USERS_LOGOFF, FwEntities::ICODE_USERS, $this->fw->userId());

        @session_start();
        //delete session
        $_SESSION = array();
        session_destroy();

        $this->model->removePermCookie();

        fw::redirect($this->fw->config->UNLOGGED_DEFAULT_URL);
    }

}//end of class
