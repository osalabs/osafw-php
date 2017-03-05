<?php
/*
Part of PHP osa framework  www.osalabs.com/osafw/php
(c) 2009-2015 Oleg Savchuk www.osalabs.com
*/

require_once dirname(__FILE__)."/../config.php";
require_once dirname(__FILE__)."/dispatcher.php";
require_once dirname(__FILE__)."/sitetpl.php";
require_once dirname(__FILE__)."/db.php";
require_once dirname(__FILE__)."/Utils.php";
require_once dirname(__FILE__)."/FormUtils.php";
require_once dirname(__FILE__)."/UploadUtils.php";
require_once dirname(__FILE__)."/DateUtils.php";
require_once dirname(__FILE__)."/FwCache.php";
require_once dirname(__FILE__)."/FwController.php";
require_once dirname(__FILE__)."/FwAdminController.php";
require_once dirname(__FILE__)."/FwModel.php";
require_once dirname(__FILE__)."/../FwHooks.php";

//just to be able to distinguish between system exceptions and applicaiton-level exceptions
class ApplicationException extends Exception {}
class ValidationException extends ApplicationException {}
class ExitException extends Exception {}
class BadAccessException extends Exception {}

//******** autoload controller/model classes
function __autoload($class_name){
    $dir = dirname(__FILE__).'/..';
    if ( preg_match('/Controller$/', $class_name) ){
        $dir.='/controllers/';
        $class_name=preg_replace("/Controller$/", "", $class_name);
    }else{
        $dir.='/models/';
        $class_name=preg_replace("/Model$/", "", $class_name);
    }

    #try class name directly
    $file = $dir.$class_name.'.php';
    if (!file_exists($file)){
        #if not exists - try normalized
        $class_name=ucfirst(strtolower($class_name));   #normalize name, i.e. users => Users
        $file = $dir.$class_name.'.php';
    }

    if (!file_exists($file)) throw new NoClassException("Class not found: $class_name");
    try {
        #logger("before include [$file]");
        include_once $file;
    } catch (Exception $ex){
        logger("********** Error Loading class $class_name");
        throw new Exception("Error Loading class $class_name", 1);
    }
}

class fw {
    public static $instance;

    public $dispatcher;

    public $route = array(); #current request route data
    public $ROUTES = array();
    public $G = array();    #"global" vars
    public $models_cache=array(); #cached model instances

    public $page_layout;

    # run web application
    # should be called in index.php
    public static function run($ROUTES=array()){
        global $CONFIG;
        $start_time = microtime(true);
        session_start();  #start session on each request

        #initial fixes
        if (get_magic_quotes_gpc()){
            $_POST = array_map(array('Utils','kill_magic_quotes'), $_POST);
            $_GET = array_map(array('Utils','kill_magic_quotes'), $_GET);
            $_COOKIE = array_map(array('Utils','kill_magic_quotes'), $_COOKIE);
            $_REQUEST= array_map(array('Utils','kill_magic_quotes'), $_REQUEST);
        }

        #setup user's language to use by template engine
        if (isset($_SESSION['lang'])){
           $CONFIG['LANG']=$_SESSION['lang'];
        }else{
           $CONFIG['LANG']=$CONFIG['LANG_DEF'];  #use default language
        }

        # and now run dispatcher
        $fw = fw::i();

        FwHooks::global_init();

        //fw vairables
        $fw->page_layout = $CONFIG['PAGE_TPL'];

        #save flash to current var and update session
        if (isset($_SESSION['_flash'])){
            $fw->G['_flash']=$_SESSION['_flash'];
        }
        $_SESSION['_flash']=array();

        $fw->dispatcher = new Dispatcher($ROUTES);
        $fw->route = $fw->dispatcher->get_route();

        $fw->run_route();

        $total_time = microtime(true) - $start_time;
        logger('INFO', '*** REQUEST END in '.number_format($total_time,10).'s, '.number_format(1/$total_time, 3).'/s');
    }

    # return singleton instance
    public static function i(){
        if (!fw::$instance){
            fw::$instance = new fw();
        }
        return fw::$instance;
    }

    public static function model($model_class){
        $fw = fw::i();
        if ( array_key_exists(strtolower($model_class), $fw->models_cache) ){
            return $fw->models_cache[$model_class];
        }
        #logger($model_class);
        $object = new $model_class($fw);
        $fw->models_cache[$model_class] = $object;
        return $object;
    }

    public function __construct() {
        global $CONFIG;
        $this->G = $CONFIG;
    }

    public function run_route(){
        try {
            $this->auth($this->route);
            #logger("BEFORE CALL render_route", $this->route);
            $this->render_route($this->route);

        } catch (AuthException $ex) {
            $this->handle_page_error(401, $ex->getMessage(), $ex);

        } catch (NoClassMethodException $ex) {
            #if can't call method - so class/method doesn't exists - show using route_default_action
            logger('WARN', "No method found for route", $this->route, ", checking route_default_action");

            $default_action = $this->dispatcher->get_route_default_action($this->route['controller']);
            if ($default_action=='index'){
                $this->route['action']='Index';

            }elseif($default_action=='show'){
                #assume action is id and use ShowAction
                $this->route['id']=$this->route['action'];
                $this->route['params'][0]=$this->route['id'];
                $this->route['action']='Show';
            }else{
                #if no default action set - this is special case action - this mean action should be got form REST's 'id'
                if ($this->route['id']>''){
                    $this->route['action']= $this->route['id'];
                }
            }

            try {
                $this->render_route($this->route);

            } catch (NoClassMethodException $ex2) {
                #if no method - just call parser() - show template from /cur_controller/cur_action dir
                logger('WARN', "Default parser");
                $this->parser();
            }

        } catch (NoClassException $ex) {
            #if can't call class - class doesn't exists - show 404 error
            $this->handle_page_error(404, $ex->getMessage(), $ex);

        } catch (ExitException $ex) {
            #not a problem - just graceful exit
            #logger("Exit Exception (normal behaviour, usually due to redirect)");

        } catch (BadAccessException $ex) {
            $this->handle_page_error(401, $ex->getMessage(), $ex);

        } catch (ApplicationException $ex){
            $this->handle_page_error(500, $ex->getMessage(), $ex);

        } catch (Exception $ex) {
            $this->handle_page_error(500, $ex->getMessage(), $ex);
        }
    }

    public function render_route($route){
        #remember in G for rendering
        $this->G['controller']=$route['controller'];
        $this->G['controller.action']=$route['controller'].'.'.$route['action'];
        $this->G['controller.action.id']=$route['controller'].'.'.$route['action'].'.'.$route['id'];

        $ps = $this->dispatcher->run_controller($route['controller'],$route['action'], $route['params']);

        #if action doesn't returned array - assume action rendered page by itself
        if ( is_array($ps) ){
            $this->parser($ps);
        }
    }

    #return 1 if client expects json response (based on passed route or _SERVER[HTTP_ACCEPT]) header
    public function is_json_expected($route=NULL){
        if (!is_array($route)) $route = $this->route;

        if ($route['format']=='json' || preg_match('!application/json!', $_SERVER['HTTP_ACCEPT'])){
            return 1;
        }else{
            return 0;
        }
    }
    public function get_response_expected_format($route=NULL){
        if (!is_array($route)) $route = $this->route;
        $result = '';

        if ($route['format']=='json' || preg_match('!application/json!', $_SERVER['HTTP_ACCEPT'])) {
            $result = 'json';
        }elseif ($route['format']=='pjax' || $_SERVER['HTTP_X_REQUESTED_WITH']>'' ) {
            $result = 'pjax';
        }else{
            $result = $route['format'];
        }

        return $result;
    }

    # TODO $args
    public function route_redirect($action, $controller=NULL, $params=NULL) {
        $this->route['action']=$action;
        if ( !is_null($controller) ) $this->route['controller']=$controller;
        if ( !is_null($params) ) $this->route['params']=$params;

        $this->run_route();
    }

    # simple auth check based on /controller/action - and rules filled in in Config class
    # also check for XSS in $_SESSION
    # IN: route hash
    # OUT: return TRUE throw AuthException if not authorized to view the page
    private function auth($route) {
        global $CONFIG;
        $ACCESS_LEVELS = array_change_key_case($CONFIG['ACCESS_LEVELS'], CASE_LOWER);

        #XSS check for all requests that modify data
        if ( ($_REQUEST["XSS"] || $this->route['method'] == "POST" || $this->route['method'] == "PUT" || $this->route['method'] == "DELETE")
            && $_SESSION["XSS"] > "" && $_SESSION["XSS"] <> $_REQUEST["XSS"]
            && !in_array($this->route['controller'], $CONFIG['NO_XSS']) //no XSS check for selected controllers
        ) {
           throw new AuthException("XSS Error");
        }

        #access level check
        $path = strtolower('/'.$this->route['controller'].'/'.$this->route['action']);
        $path2 = strtolower('/'.$this->route['controller']);

        $current_level = -1;
        if (isset($_SESSION['access_level'])) $current_level=$_SESSION['access_level'];

        $rule_level = -1; #no restictions
        if ( array_key_exists($path, $ACCESS_LEVELS) ){
            $rule_level = $ACCESS_LEVELS[$path];
        }elseif ( array_key_exists($path2, $ACCESS_LEVELS) ){
            $rule_level = $ACCESS_LEVELS[$path2];
        }

        if ( $current_level < $rule_level ) {
            throw new AuthException("Access Denied");
        }

        return true;
    }

    public function handle_page_error($error_code, $error_message='', $exeption=NULL){
        $custom_error_route = $this->dispatcher->ROUTES[$error_code];
        if ($custom_error_route>''){
            $route=@$this->dispatcher->str2route($custom_error_route);
        }

        logger("ERROR", "Dispatcher - handle_page_error : $error_code $error_message", $route);

        $is_error_processed = false;
        if ($route){
            //custom error handling route
            try {
                $this->render_route($route);
                $is_error_processed = true;

            } catch (NoClassException $ex) {
                //still error not processed
                logger("ERROR", 'Error occured during processing custom error handler: '. $ex->getMessage());
            }
        }

        if (!$is_error_processed){
            $uri = $_SERVER['REQUEST_URI'];
            $d = $this->dispatcher;
            $err_code_desc = $d::$HTTP_CODE[$error_code];

            header("HTTP/1.0 $error_code $err_code_desc", true, $error_code);

            $err_msg = $error_message ? $error_message : ($err_code_desc ? $err_code_desc : "PAGE NOT YET IMPLEMENTED [$uri] ");

            $ps=array(
                'success'   =>  0,
                'err_code'  =>  $error_code,
                'err_msg'   =>  $err_msg,
                'err_time'  => time(),
            );
            if ($this->G['IS_DEBUG'] && $_SESSION['access_level']==100){
                $ps['is_dump'] = true;
                if (!is_null($exeption)){
                    $ps['DUMP_STACK'] = $exeption->getTraceAsString();
                }
                $ps['DUMP_FORM'] = print_r($_REQUEST,true);
                $ps['DUMP_SESSION'] = print_r($_SESSION,true);
            }
            $this->parser('/error', $ps);
        }

    }

    # RETURN output to browser according to expected format: full html, pjax, json
    # overloaded:
    # parser()      - show page from template  /cur_controller/cur_action = parser('/cur_controller/cur_action/', $PAGE_TPL, array())
    # parser($ps)   - show page from template  /cur_controller/cur_action = parser('/cur_controller/cur_action/', $PAGE_TPL, $ps)
    # parser('/controller/action', $ps)   - show page from template  /controller/action = parser('/controller/action/', $PAGE_TPL, $ps)
    # parser('/controller/action', $layout, $ps)   - show page from template  /controller/action = parser('/controller/action/', $layout, $ps)
    # full params:
    # $basedir, $layout, $ps, $out_filename=''|'v'|'filename'
    #
    # output format based on requested format: json, pjax or (default) full page html
    # JSON: for automatic json response support - set ps("_json_enabled") = true - TODO make it better?
    # to return only specific content for json - set it to ps("_json_data")
    # to override page template - set ps("_page_tpl")="another_page_layout.html"
    #
    # TODO: (not for json) to perform route_redirect - set ps("_route_redirect"), ps("_route_redirect_controller"), ps("_route_redirect_args")
    # TODO: (not for json) to perform redirect - set ps("_redirect")="url"
    public function parser() {
        global $CONFIG;

        $args=func_get_args();
        $basedir='';
        $controller = $this->route['controller'];
        if ( $this->route['prefix'] ){
            $basedir.= '/'.$this->route['prefix'];
            $controller=preg_replace('/^'.preg_quote($this->route['prefix']).'/i', '', $controller);
        }
        $basedir.='/'.$controller.'/'.$this->route['action'];
        $basedir=strtolower($basedir);
        $layout=$this->page_layout;
        $ps=array();
        $out_filename='';

        if ( !count($args) ){
            $ps = array();
        }elseif ( count($args)==1 && is_array($args[0]) ){
            $ps = &$args[0];
        }elseif ( count($args)==2 && is_string($args[0]) && is_array($args[1]) ){
            $basedir = &$args[0];
            $ps = &$args[1];
        }elseif ( count($args)>=3 && is_string($args[0]) && is_string($args[1]) && is_array($args[2]) ){
            $basedir = &$args[0];
            $layout = &$args[1];
            $ps = &$args[2];
            if (count($args)==4) $out_filename = &$args[3];
        }else{
            throw Exception("parser - wrong call");
        }

        $out_format = $this->get_response_expected_format();
        if ($out_format == 'json'){
            if ($ps['_json_enabled']){
                # if _json_data exists - return only this element
                if (array_key_exists('_json_data', $ps)){
                    parse_json($ps['_json_data']);
                }else{
                    parse_json($ps);
                }
            }else{
                parse_json(array(
                    'success'   => false,
                    'message'   => 'JSON response is not enabled for the Controller.Action (set ps[\"_json_enabled\"]=true to enable).',
                ));
            }

        }elseif ($out_format == 'html' || $out_format == 'pjax' || !$out_format){
            #html output based on ParsePage templates
            if ($out_format == 'pjax') {
                $layout = $CONFIG['PAGE_TPL_PJAX'];
            }

            if ( !array_key_exists('ERR', $ps) ) {
                $ps["ERR"] = $this->G['ERR']; #add errors if any
            }

            logger("basedir=[$basedir], layout=[$layout] to [$out_filename]");
            return parse_page($basedir, $layout, $ps, $out_filename);

        }else{
            #any other formats - call controller's Export($out_format)
            logger("export $out_format using ".$this->route['controller']."Controller.Export()");
            $this->dispatcher->call_class_method($this->route['controller'].'Controller','Export', array($ps, $out_format));
        }
    }

    //flash - read/store flash data (available on the next request and only on it)
    public function flash($name, $value=NULL) {
        if ( is_null($value) ) {
            #read mode
            return $this->G['_flash'][$name];
        }else{
            #write for next request
            $_SESSION['_flash'][$name]=$value;
        }
    }


########################## Email functions

    /*
     send email in UTF-8
     $ToEmail can be array
    */
    public function send_email($ToEmail, $Subj, $Message, $isBCC="", $FromEmail="", $is_html=""){
      global $CONFIG;
      $result=true;
      $MAIL= $CONFIG['MAIL'];

      if (!$FromEmail) $FromEmail=$CONFIG['FROM_EMAIL'];

      if ($this->G['IS_DEBUG']) logger("ToEmail=[$ToEmail], Subj=[$Subj]\nis_html=[$is_html]\n".substr($Message,0,255));

      if ($MAIL['IS_SMTP']){
        #send using PHPMailer class
        require_once(dirname(__FILE__).'/mail/class.mailer.php');
        $mail = new PHPMailer(true);

        $mail->IsSMTP();

        try {
          $mail->SMTPAuth   = true;
          $mail->Host       = $MAIL['SMTP_SERVER'];
          $mail->Username   = $MAIL['USER'];
          $mail->Password   = $MAIL['PWD'];

          if (is_array($ToEmail)){
            foreach ($ToEmail as $k=>$v){
              $mail->AddAddress($v);
            }
          }else{
            $mail->AddAddress($ToEmail);
          }

          $mail->SetFrom($FromEmail);
        #      $mail->AddReplyTo($FromEmail);
          $mail->Subject = $Subj;

          if ($is_html){
             $mail->AltBody = 'To view the message, please use an HTML compatible email viewer!'; // optional - MsgHTML will create an alternate automatically
             $mail->MsgHTML($Message);
          }else{
             $mail->Body    = $Message;
          }
        #      rw("before Send");
          $res=$mail->Send();
        #      rw($res);
          $result=true;

        } catch (phpmailerException $e) {
          logger($e->errorMessage());
        } catch (Exception $e) {
          logger($e->getMessage());
        }

      }else{
        #send using usual php mailer
        $more="Content-Type: text/plain; charset=\"utf-8\" ; format=\"flowed\"\n";
        if ($FromEmail) $more.="From: $FromEmail\n";
        if ($isBCC) $more.="Bcc: $isBCC\n";

        if ( preg_match("/\W/", $Subj) ) $Subj="=?utf-8?B?".base64_encode($Subj)."?=";

        if (is_array($ToEmail)){
          foreach ($ToEmail as $k=>$v){
             mail($v, $Subj, $Message, $more);
          }
        }else{
          mail($ToEmail, $Subj, $Message, $more);
        }

      }

      return $result;

    }


    // Administrator message alerter
    public function send_email_admin($msg_body){
        global $CONFIG;
        logger("ADMIN ALERT:", $msg_body);
        $this->send_email($CONFIG['ADMIN_EMAIL'],'SITE SYSTEM ALERT!', $msg_body);
    }

    ################## SEND EMAIL FROM TEMPLATE in /emails path
    /*
     FIRST LINE IN TEMPLATE FILE - Message Subject
     to_email, ps hash

     sample:
         $ps=array(
             'user' => $hU,
         );
         send_email_tpl( $hU['email'], 'email_invite.txt', $ps);

         OR
         send_email_tpl( $hU['email'], 'email_invite.html', $ps);
    */
    public function send_email_tpl($to_email, $tpl, $ps, $isBCC='', $FromEmail=''){
        $msg_body=parse_page('/emails', $tpl, $ps, 'v');
        list($msg_subj, $msg_body)=$this->_email2subj_body($msg_body);

        $is_html=1;
        if ( preg_match("/\.txt$/i", $tpl) ) $is_html=0;

        @$this->send_email($to_email, $msg_subj, $msg_body, $isBCC, $FromEmail, $is_html);
    }

    // ****************** get first line from email - will be subject, return list(subj, body) #TODO - remove
    private function _email2subj_body($message){
        return preg_split("/\n/", $message, 2);
    }

##########################  STATIC methods

    public static function redirect($url, $noexit=''){
        $url = fw::url2abs($url);

        logger("REDIRECT to [$url]");
        $ps=array(
            'url'=>$url
        );
        parse_page("/common", "redirect_js.html", $ps);
        if (!$noexit) throw new ExitException;
    }
    //TODO:
    //function redirect($location){
    //header('location: http://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']).'/'.$location);
    //}

    #make url absolute
    # /some_site_url?aaaa => http://root_domain/ROOT_URL/some_site_url?aaaa
    public static function url2abs($url){
        global $CONFIG;

        if (substr($url,0,1)=='/') $url=$CONFIG['ROOT_DOMAIN'].$CONFIG['ROOT_URL'].$url;

        return $url;
    }

}

//Helper/debug functions - TODO move to fw or Utils class?

//get some value from $_REQUEST
//TODO? make support of infinite []
function _req($name){
  if ( preg_match('/^(.+?)(?:\[(.+?)\])/', $name, $m) ){
    return $_REQUEST[ $m[1] ][ $m[2] ];
  }else{
    return $_REQUEST[$name];
  }
}

//get integer value from $_REQUEST
//return 0
function reqi($name){
    return _req($name)+0;
}
//get string value from $_REQUEST
//return 0
function reqs($name){
    return _req($name).'';
}
//shortcut to $_REQUEST[$name]
//return value from request
function req($name){
    return $_REQUEST[$name];
}
//return hash/array from request (if no such param or not array - returns empty array)
function reqh($name){
    $h=$_REQUEST[$name];
    if (!is_array($h)) $h=array();
    return $h;
}


########################## for debug
# IN: [logtype (TRACE, INFO, DEBUG, WARN, ERROR, PANIC), ] and variable number of params
# OUT: none, just write to $site_error_log
# limit output to 2048 chars per call
function logger(){
 global $CONFIG;

 if (!$CONFIG['IS_DEBUG']) return;  #don't log if debug is off (for production)

 $args=func_get_args();
 $logtype = 'DEBUG';
 if (count($args)>0 && is_string($args[0]) && preg_match("/^(TRACE|INFO|DEBUG|WARN|ERROR|PANIC)$/", $args[0], $m) ){
     $logtype = $m[1];
     array_shift($args);
 }

 $arr=debug_backtrace();
 $func = $arr[1];
 $line = $arr[0]['line'];

 //remove unnecessary site_root_offline path
 $func['file']=str_replace( strtolower($CONFIG['SITE_ROOT_OFFLINE']), "", strtolower($func['file']) );

 $strlog=strftime("%Y-%m-%d %H:%M:%S")." $logtype ".$func['file']."::".$func['function']."(".$line.") ";
 foreach ($args as $str){
     if (is_scalar($str)){
        $strlog.=$str;
     }else{
        $strlog.=print_r($str,true);
     }
     $strlog.="\n";
 }

 //cut too long logging
 if (strlen($strlog)>2048) $strlog=substr($strlog,0,2048).'...'.substr($strlog,-128);
 if ( !preg_match("/\n$/", $strlog) ) $strlog.="\n";

 error_log($strlog, $CONFIG['LOGGER_MESSAGE_TYPE'], $CONFIG['site_error_log']);

 if ($logtype == 'TRACE'){
    $e = new Exception();
    error_log($e->getTraceAsString()."\n", $CONFIG['LOGGER_MESSAGE_TYPE'], $CONFIG['site_error_log']);
 }

}

########################### for debugging with output right in the browser or console
function rw($var){
 $is_html=$_SERVER['HTTP_HOST'] ? 1:0;
 if ( !is_scalar($var) ){
    $var=print_r($var,true);
    if ($is_html) $var=preg_replace("/\n/", "<br>\n",$var);
 }
 echo $var.(($is_html)?"<br>":"")."\n";
 flush();
}
########################### same
function rwe($var){
 rw($var);
 die;
}