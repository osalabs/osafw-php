<?php

class Users extends FwModel {
    public static $ACL=array(
                        'ADMIN' => 100,
                        'MODER' => 80,
                        'USER'  => 0,
                    );
    public static $PERM_COOKIE_NAME='perm';
    public static $PERM_COOKIE_DAYS=356;
    public static $order_by = 'fname, lname';
    public function __construct() {
        parent::__construct();

        $this->table_name = 'users';
    }

    public function one_by_email($email) {
        return db_row("select * from ".$this->table_name." where email=".dbq($email));
    }

    public function full_name($id) {
        $result='';
        $item = $this->one($id);
        if ($item['id']){
            $result=$item['fname'].' '.$item['lname'];
        }
        return $result;
    }

    public function add_or_update($login, $pwd, $item){
        $result=0;
        $itemold=$this->one_by_email($login);
        $item['pwd']=$pwd;
        if ($itemold){
            $this->update($itemold['id'], $item);
            $result=$itemold['id'];
        }else{
            $result=parent::add($item);
        }
        return $result;
    }

    public function add($item) {
        $vars=array(
            'pwd'   => Utils::get_rand_str(8), #generate password
        );
        $vars=array_merge($vars, $item);
        $id=parent::add($vars);

        #send email notification with password
        $ps=array(
            'user' => $this->one($id),
        );
        $this->fw->send_email_tpl( $item['email'], 'email_registered.txt', $ps);

        return $id;
    }

    public function is_exists($email, $not_id=NULL) {
        return parent::is_exists_byfield($email, 'email', $not_id);
    }
    public function is_email_exists($email) {
        return db_value("select 1 from ".$this->table_name." where email=".dbq($email)) ? true : false;
    }

    public function do_login($id) {
        $is_just_registered=$_SESSION['is_just_registered']+0;

        @session_destroy();
        session_start();

        #fill up session data
        $this->set_def_session($id);
        $_SESSION['just_logged']=1;
        $_SESSION['is_just_registered']=$is_just_registered;
        session_write_close();

        $this->fw->model('Events')->log_event('login', $id);

        //set permanent login if requested
        //if ($_REQUEST['is_remember']) create_perm_cookie($id);
        $this->create_perm_cookie($id);  #in this project no need is_remember

        $this->update_after_login($id);
    }

    private function set_def_session($id){
        $hU=$this->one($id);
        foreach($hU as $key => $value){
            $_SESSION['user'][$key]=$value;
        }

        $_SESSION['login']=$_SESSION['user']['email'];
        $fname = trim($_SESSION['user']['fname']);
        $lname = trim($_SESSION['user']['lname']);
        $_SESSION['user_name']=$fname.($fname?' ':'').$lname; #will be empty if no user name set
        $_SESSION['access_level']=$_SESSION['user']['access_level'];
        $_SESSION['is_logged']=1;
        $_SESSION['XSS']=Utils::get_rand_str(16);  #setup XSS code
    }

    public function session_reload(){
        $this->set_def_session($_SESSION['user']['id']+0);
    }

    private function update_after_login($id) {
        $hU=$this->one($id);
        #TODO add_notify_log($GLOBAL['NOTIFY_LOG_LOGIN'], $id, 0, $hU);

        #update login vars
        $ip=getenv("REMOTE_ADDR");

        /*TODO
        $vars=array(
           'users_id'     => $id,
           'login_ip' => $ip,
           'add_time' => '~!now()',
        );
        db_insert('users_log', $vars);
        */

        $host=gethostbyaddr($ip);

        $vars=array(
            'login_time'    => '~!now()',
            'login_ip'      => $ip,
            'login_host'    => $host,
        );
        db_update($this->table_name, $vars, $id);
    }

    public function create_perm_cookie($id){
        global $CONFIG;
        $root_domain0=$CONFIG['ROOT_DOMAIN0'];

        $cookie_id=substr(Utils::get_rand_str(16).time(),0,32);

        $vars=array(
            'cookie_id' => $cookie_id,
            'users_id'  => $id
        );
        db_insert('user_cookie', $vars, array('replace' => 1));

        setcookie(self::$PERM_COOKIE_NAME, $cookie_id, time()+60*60*24*self::$PERM_COOKIE_DAYS, "/", (preg_match('/\./',$root_domain0))?'.'.$root_domain0:'');
        #rwe("[$root_domain0] ".self::$PERM_COOKIE_NAME.", $cookie_id, ".(time()+60*60*24*self::$PERM_COOKIE_DAYS));

        return $cookie_id;
    }

    # check for permanent login cookie and if it's present - do_login
    public function check_permanent_login(){
        global $CONFIG;
        $root_domain0=$CONFIG['ROOT_DOMAIN0'];

        $result = false;

        $cookie_id=$_COOKIE[ self::$PERM_COOKIE_NAME ];
        #rw("cookies:");
        #print_r($_COOKIE);
        #exit;

        if ($cookie_id) {
            $u_id=db_value("select users_id
                  from user_cookie
                 where cookie_id=".dbq($cookie_id)."
                   and add_time>=FROM_DAYS(TO_DAYS(now())-".self::$PERM_COOKIE_DAYS.")
            ");

            if ($u_id>0){
                $result=true;
                #logger("PERMANENT LOGIN");
                $this->do_login($u_id);
            }else{
                #cookie is not found in DB - clean it (so it will not put load on DB during next pages)
                setcookie(self::$PERM_COOKIE_NAME, FALSE, -1, "/", (preg_match('/\./',$root_domain0))?'.'.$root_domain0:'' );
            }
        }
        return $result;
    }

    public function remove_perm_cookie(){
        $cookie_id=$_COOKIE[ self::$PERM_COOKIE_NAME ];

        setcookie(self::$PERM_COOKIE_NAME, FALSE, -1, "/");

        #cleanup in DB (user's cookie and ALL old cookies)
        db_query("delete from user_cookie
            where cookie_id=".dbq($cookie_id)."
               or add_time<FROM_DAYS(TO_DAYS(now())-".self::$PERM_COOKIE_DAYS.")
        ");

    }

    public function is_access($access_str) {
        $req_level=self::$ACL[$access_str]+0;

        return $_SESSION['user']['access_level']>=$req_level ? true : false;
    }

    /**
     * return sql for limiting access according to current user ACL
     * @param  string $alias optional, add_user_id field alias with dot. Example: 'c.'. If not provided - no alias used
     * @param  string $field optional, add_user_id field name
     * @return string        sql query string like " and add_user_id=".Utils::me()
     */
    public function sql_acl($alias='', $field=''){
        $result='';
        if (self::is_access('MODER')){
            //if we are admin user - allow access to all records
        }else{
            //if we are normal user - allows access only records we created
            if (!$field) $field = 'add_user_id';

            $result=' and '.$alias.$field.'='.dbqi(Utils::me()).' ';
        }
        return $result;
    }

}

?>