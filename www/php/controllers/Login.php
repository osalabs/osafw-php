<?php

class LoginController extends FwController {
    const route_default_action = '';
    public $model_name = 'Users';

    public function __construct() {
        parent::__construct();

        #override layout
        $this->fw->page_layout = $this->fw->config->PAGE_LAYOUT_PUBLIC;
    }

    public function IndexAction() {
        $item = reqh('item');
        if (!$item){
            #defaults
            $item=array(
            );
        }
        $ps = array(
            'i'  => $item,
            'hide_sidebar'  => true,
        );

        return $ps;
    }

    public function SaveAction() {
        #special case login
        if ( req('save_type')=='facebook' ){
            $this->SaveFacebook();
            return;
        }

        try{
            $login  = trim($_REQUEST['item']['login']);
            $pwd    = $_REQUEST['item']['pwdh'];
            if ($_REQUEST["item"]["chpwd"] == "1") $pwd = $_REQUEST['item']['pwd'];
            $pwd    = trim($pwd);
            $gourl  = reqs('gourl');

            #for dev only - login as first admin
            $is_dev_login=false;
            if ($this->fw->config->IS_DEV===TRUE && $login=='' && $pwd=='~'){
                $dev = $this->db->row("select email, pwd from users where status=0 and access_level=100 order by id limit 1");
                $login = $dev['email'];
                $is_dev_login=true;
            }

            if (!strlen($login) || !strlen($pwd) ) {
                $this->setError("REGISTER", True);
                throw new ApplicationException("");
            }

            $user = $this->db->row("select * from users where email=".$this->db->quote($login));
            if (!$is_dev_login){
                if (!$user || $user['status']!=0 || !$this->model->checkPwd($pwd, $user['pwd']) ) throw new ApplicationException(lng("User Authentication Error"));
            }

            $this->model->doLogin( $user['id'] );

            if ($gourl && !preg_match("/^http/i", $gourl)){ #if url set and not external url (hack!) given
                fw::redirect($gourl);
            }else{
                fw::redirect($this->fw->config->LOGGED_DEFAULT_URL);
            }

        }catch( ApplicationException $ex){
            $this->fw->GLOBAL['err_ctr']=reqi('err_ctr')+1;
            $this->setFormError($ex->getMessage());
            $this->routeRedirect("Index");
        }
    }

    public function SaveFacebook() {

        $item=FormUtils::filter($_REQUEST, 'access_token id email first_name last_name name username gender link locale timezone verified');
        #TODO better validate
        if (!$item['access_token'] || !$item['id']) throw new ApplicationException("Wrong facebook data", 1);

        /*
        $fb = new Facebook(array(
            'appId'  => $GLOBALS['FACEBOOK_APP_ID'],
            'secret' => $GLOBALS['FACEBOOK_APP_SECRET'],
        ));
        $fb_user_id = $facebook->getUser();
        $user_profile = $facebook->api('/me');
        */

        #check if such user exists
        $users_id=0;

        #first - check by email
        $user=$this->model->oneByEmail($item['email']);
        if ($user['id']){
            $users_id=$user['id'];
        }

        if (!$users_id){
            #now check by facebook email
            $user=$this->db->row("select * from users where fb_email=".$this->db->quote($item['email']) );
            if ($user['id']) $users_id=$user['id'];
        }

        if (!$users_id){
            #now check by facebook id
            $user=$this->db->row("select * from users where fb_id=".$this->db->quote($item['id']) );
            if ($user['id']) $users_id=$user['id'];
        }

        if ($users_id){
            #update user's missing data from facebook
            $vars=array(
                'fb_access_token'   => $item['access_token'],
            );

            if ($user['sex']!= ($item['gender']=='male' ? 1 : 0) ) $vars['sex']=$item['gender']=='male' ? 1 : 0;
            if (!$user['fname']) $vars['fname']=$item['first_name'];
            if (!$user['lname']) $vars['lname']=$item['last_name'];
            if ($user['fb_email']!=$item['email'] && $item['email']) $vars['fb_email']=$item['email'];

            if (!$user['fb_id'])          $vars['fb_id']          =$item['id'];
            if (!$user['fb_link'])        $vars['fb_link']        =$item['link'];
            if (!$user['fb_locale'])      $vars['fb_locale']      =$item['locale'];
            if (!$user['fb_name'])        $vars['fb_name']        =$item['name'];
            if (!$user['fb_timezone'])    $vars['fb_timezone']    =$item['timezone'];
            if (!$user['fb_username'])    $vars['fb_username']    =$item['username'];
            if (!$user['fb_verified'])    $vars['fb_verified']    =$item['verified']=='true' ? 1 : 0;
            if (!$user['fb_picture_url']) $vars['fb_picture_url'] ='http://graph.facebook.com/'.$item['username'].'/picture';

            $this->db->update('users', $vars, $users_id);

        }else{
            #register user first if new
            $users_id=$this->model->add(array(
                'email'     => $item['email'],
                #'phone'  => $item['phone'],
                'nick'      => $item['name'],
                'sex'       => $item['gender']=='male' ? 1 : 0,
                'fname'     => $item['first_name'],
                'lname'     => $item['last_name'],

                'fb_id'     => $item['id'],
                'fb_link'   => $item['link'],
                'fb_locale'   => $item['locale'],
                'fb_name'     => $item['name'],
                'fb_timezone' => $item['timezone'],
                'fb_username' => $item['username'],
                'fb_verified' => $item['verified']=='true' ? 1 : 0,
                'fb_picture_url' => 'http://graph.facebook.com/'.$item['username'].'/picture',
                'fb_access_token'   => $item['access_token'],
            ));
        }

        #automatically login the user
        $_SESSION['is_just_registered']=1;
        $this->model->doLogin($users_id);

        $ps=array(
            'status'    => 0,
            'err_msg'   => '',
        );
        parse_json($ps);
    }

    public function LogoffAction() {
        $this->fw->model('FwEvents')->log('logoff', Utils::me());

        //delete session
        $_SESSION = array();
        session_destroy();

        $this->model->removePermCookie();

        fw::redirect($this->fw->config->UNLOGGED_DEFAULT_URL);
    }

}//end of class

?>