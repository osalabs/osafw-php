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

    public function oneByEmail($email) {
        return $this->db->row("select * from ".$this->table_name." where email=".$this->db->quote($email));
    }

    public function getFullName($id) {
        $result='';
        $item = $this->one($id);
        if ($item['id']){
            $result=$item['fname'].' '.$item['lname'];
        }
        return $result;
    }

    #return standard list of id,iname where status=0 order by iname
    public function ilist($min_acl=null) {
        $where='';
        if (!is_null($min_acl)) $where=' and access_level>='.dbqi($min_acl);
        $sql  = "select *, (fname+' '+lname) as iname from $this->table_name where status=0 $where order by fname,lname";
        return $this->db->arr($sql);
    }

    public function getMultiList($hsel_ids, $min_acl=null){
        $rows = $this->ilist($min_acl);
        if (is_array($hsel_ids) && count($hsel_ids)){
            foreach ($rows as $k => $row) {
                $rows[$k]['is_checked'] = array_key_exists($row['id'], $hsel_ids)!==FALSE;
            }
        }

        return $rows;
    }

    public function isExists($email, $not_id=NULL) {
        return $this->isExistsByField($email, 'email', $not_id);
    }

    #encrypt/decrypt pwd based on config keys
    public static function encryptPwd($value){
        return Utils::crypt('encrypt', $value, fw::i()->config->CRYPT_V, fw::i()->config->CRYPT_KEY);
    }
    public static function decryptPwd($value){
        return Utils::crypt('decrypt', $value, fw::i()->config->CRYPT_V, fw::i()->config->CRYPT_KEY);
    }

    public function doLogin($id) {
        $is_just_registered=$_SESSION['is_just_registered']+0;

        @session_destroy();
        session_start();
        $_SESSION['is_logged']=true;
        $_SESSION['XSS']=Utils::getRandStr(16);  #setup XSS code

        #fill up session data
        $this->reloadSession($id);
        $_SESSION['just_logged']=1;
        $_SESSION['is_just_registered']=$is_just_registered;
        session_write_close();

        $this->fw->model('FwEvents')->log('login', $id);

        //set permanent login if requested
        //if ($_REQUEST['is_remember']) createPermCookie($id);
        $this->createPermCookie($id);  #in this project no need is_remember

        $this->updateAfterLogin($id);
    }

    public function reloadSession($id=0){
        if (!$id) $id=Utils::me();
        $hU=$this->one($id);

        $_SESSION['user_id']=$id;
        $_SESSION['login']=$hU['email'];
        $fname = trim($hU['fname']);
        $lname = trim($hU['lname']);
        $_SESSION['user_name']=$fname.($fname?' ':'').$lname; #will be empty if no user name set
        $_SESSION['access_level']=$hU['access_level'];
    }

    private function updateAfterLogin($id) {
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
        $this->db->insert('users_log', $vars);
        */

        $host=gethostbyaddr($ip);

        $vars=array(
            'login_time'    => '~!now()',
            'login_ip'      => $ip,
            'login_host'    => $host,
        );
        $this->db->update($this->table_name, $vars, $id);
    }

    public function createPermCookie($id){
        $root_domain0=$this->fw->config->ROOT_DOMAIN0;

        $cookie_id=substr(Utils::getRandStr(16).time(),0,32);

        $vars=array(
            'cookie_id' => $cookie_id,
            'users_id'  => $id
        );
        $this->db->insert('user_cookie', $vars, array('replace' => 1));

        setcookie(self::$PERM_COOKIE_NAME, $cookie_id, time()+60*60*24*self::$PERM_COOKIE_DAYS, "/", (preg_match('/\./',$root_domain0))?'.'.$root_domain0:'');
        #rwe("[$root_domain0] ".self::$PERM_COOKIE_NAME.", $cookie_id, ".(time()+60*60*24*self::$PERM_COOKIE_DAYS));

        return $cookie_id;
    }

    # check for permanent login cookie and if it's present - doLogin
    public function checkPermanentLogin(){
        $root_domain0=$this->fw->config->ROOT_DOMAIN0;

        $result = false;

        $cookie_id=@$_COOKIE[ self::$PERM_COOKIE_NAME ];
        #rw("cookies:");
        #print_r($_COOKIE);
        #exit;

        if ($cookie_id) {
            $u_id=$this->db->value("select users_id
                  from user_cookie
                 where cookie_id=".$this->db->quote($cookie_id)."
                   and add_time>=FROM_DAYS(TO_DAYS(now())-".self::$PERM_COOKIE_DAYS.")
            ");

            if ($u_id>0){
                $result=true;
                #logger("PERMANENT LOGIN");
                $this->doLogin($u_id);
            }else{
                #cookie is not found in DB - clean it (so it will not put load on DB during next pages)
                setcookie(self::$PERM_COOKIE_NAME, FALSE, -1, "/", (preg_match('/\./',$root_domain0))?'.'.$root_domain0:'' );
            }
        }
        return $result;
    }

    public function removePermCookie(){
        $cookie_id=$_COOKIE[ self::$PERM_COOKIE_NAME ];

        setcookie(self::$PERM_COOKIE_NAME, FALSE, -1, "/");

        #cleanup in DB (user's cookie and ALL old cookies)
        $this->db->query("delete from user_cookie
            where cookie_id=".$this->db->quote($cookie_id)."
               or add_time<FROM_DAYS(TO_DAYS(now())-".self::$PERM_COOKIE_DAYS.")
        ");

    }

    public function isAccess($access_str) {
        $req_level=self::$ACL[$access_str]+0;

        return $_SESSION['access_level']>=$req_level ? true : false;
    }

    /**
     * return sql for limiting access according to current user ACL
     * @param  string $alias optional, add_users_id field alias with dot. Example: 'c.'. If not provided - no alias used
     * @param  string $field optional, add_users_id field name
     * @return string        sql query string like " and add_users_id=".Utils::me()
     */
    public function sql_acl($alias='', $field=''){
        $result='';
        if (self::isAccess('MODER')){
            //if we are admin user - allow access to all records
        }else{
            //if we are normal user - allows access only records we created
            if (!$field) $field = 'add_users_id';

            $result=' and '.$alias.$field.'='.dbqi(Utils::me()).' ';
        }
        return $result;
    }

}

?>
