<?php
/*
Part of PHP osa framework  www.osalabs.com/osafw/php
(c) 2009-2024 Oleg Savchuk www.osalabs.com
 */

require_once dirname(__FILE__) . "/../config.php";
require_once dirname(__FILE__) . "/FwExceptions.php";
require_once dirname(__FILE__) . "/../FwHooks.php";
require_once dirname(__FILE__) . "/dispatcher.php";
require_once dirname(__FILE__) . "/parsepage.php";
require_once dirname(__FILE__) . "/db.php";
#TODO require_once dirname(__FILE__) . '/../vendor/autoload.php';
require_once dirname(__FILE__) . "/../SiteUtils.php";

class fw {
    public static $instance;
    public static $start_time;

    public $dispatcher;
    public $db;

    public $request_url; #current request url (relative to application url)
    public stdClass $route; #current request route data, stdClass
    public $ROUTES = array();
    public $GLOBAL = array(); #"global" vars, initialized with $CONFIG
    public $config; #copy of the config as object, usage: $this->fw->config->ROOT_URL; $this->fw->config->DB['DBNAME'];
    public $models_cache = array(); #cached model instances

    public $page_layout;
    public $is_session = true; #if use session, can be set to false in initRequest to abort session use

    public static $LOG_LEVELS = array(
        'OFF'    => 0, #no logging occurs
        'FATAL'  => 10, #severe error, current request (or even whole application) aborted (notify admin)
        'ERROR'  => 20, #error happened, but current request might still continue (notify admin)
        'WARN'   => 30, #potentially harmful situations for further investigation, request processing continues
        'INFO'   => 40, #default for production (easier maintenance/support), progress of the application at coarse-grained level (fw request processing: request start/end, sql, route/external redirects, sql, fileaccess, third-party API)
        'NOTICE' => 45, #normal, but significant noticeable condition (for Sentry logged as breadcrumbs)
        'DEBUG'  => 50, #default for development (default for logger("msg") call), fine-grained level
        'TRACE'  => 60, #very detailed  (in-module details like fw core, despatcher, parse page, ...)
        'ALL'    => 70, #just log everything
    );

    # run web application request
    # should be called in index.php
    public static function run(array $ROUTES = []): void {
        $uri = strtok($_SERVER["REQUEST_URI"] ?? '', '?');

        logger('*** REQUEST START [' . $uri . ']');

        self::$start_time = microtime(true);
        $fw               = fw::i();

        session_start(); #start session on each request

        #setup user's language to use by template engine
        if (isset($_SESSION['lang'])) {
            $fw->config->LANG = $_SESSION['lang'];
        }

        # and now run dispatcher
        FwHooks::initRequest($fw, $uri);

        #save flash to current var and update session
        if (isset($_SESSION['_flash'])) {
            $fw->GLOBAL['_flash'] = $_SESSION['_flash'];
        }
        $_SESSION['_flash'] = array();

        $fw->dispatcher  = new Dispatcher($ROUTES, $fw->config->ROOT_URL, $fw->config->ROUTE_PREFIXES);
        $fw->route       = $fw->dispatcher->getRoute();
        $fw->request_url = $fw->dispatcher->request_url;

        if ($fw->is_session) {
            session_write_close();
        } else {
            session_abort();
        }

        $fw->runRoute();

        self::endRequest($fw);
    }

    # initialization code for offline scripts
    public static function initOffline($caller = "") {
        self::$start_time = microtime(true);

        $fw = fw::i(); #get fw instance
        if (!$fw->isOffline()) {
            logger("FATAL", "Unauthorized attempt to run offline script");
            exit; #exit - prevent run from web browser url
        }

        set_time_limit(0);
        ignore_user_abort(true);

        FwHooks::initRequest($fw, $caller); #additional initializations
    }

    public static function endRequest($fw = null) {
        #logger("NOTICE", "fw endRequest");
        if (is_null($fw)) {
            $fw = fw::i(); #get fw instance
        }
        FwHooks::endRequest($fw);

        if ($fw->isOffline()) {
            $msg = '*** CMD END in ';
        } else {
            $msg = '*** REQUEST END in ';
        }

        $total_time  = microtime(true) - self::$start_time;
        $memory_used = memory_get_peak_usage(true);
        logger("DEBUG", $msg . number_format($total_time, 4) . 's, ' . number_format(1 / $total_time, 3) . '/s, SQL:' . DB::$SQL_QUERY_CTR . ', MEM:' . Utils::bytes2str($memory_used));
        if ($fw->isOffline()) {
            # cmd script
            if ($memory_used > 80 * 1000 * 1000) {
                # to debug high memory usage (80MB is ~twice more than avg php script)
                logger("WARN", "Offline Script used a lot of memory", [
                    'time'   => $total_time,
                    'sql'    => DB::$SQL_QUERY_CTR,
                    'memory' => $memory_used
                ]);
            }
        } else {
            # web
            if ($total_time >= 30) {
                logger("WARN", "Server too slow", array(
                    'time' => $total_time,
                    'sql'  => DB::$SQL_QUERY_CTR,
                ));
            }
        }
    }

    # return singleton instance

    /**
     * return singleton instance
     * @return self
     */
    public static function i(): self {
        if (!fw::$instance) {
            fw::$instance = new fw();
        }
        return fw::$instance;
    }

    /**
     * return model object
     * usage: $model = fw::model('Users');
     * Note: uses model singleton, i.e. equivalent to Users::i() call
     * @param string $model_class model class name
     * @return FwModel             instance of FwModel object
     * @throws NoModelException
     */
    public static function model(string $model_class): FwModel {
        $fw        = fw::i();
        $cache_key = strtolower($model_class);
        if (array_key_exists($cache_key, $fw->models_cache)) {
            return $fw->models_cache[$cache_key];
        }
        #logger("MODEL CACHE MISS:" . $model_class);
        try {
            $object = new $model_class($fw);
        } catch (NoClassException $ex) {
            throw new NoModelException("Model class not found: $model_class");
        }
        $fw->models_cache[$cache_key] = $object;
        return $object;
    }

    public function __construct() {
        global $CONFIG;
        spl_autoload_register(array($this, 'autoload'));

        $this->GLOBAL        = $CONFIG;
        $this->GLOBAL['ERR'] = []; #store form errors
        $CONFIG['LANG']      = $CONFIG['LANG_DEF']; #use default language

        # fw vairables
        $this->config      = (object)$CONFIG; #set fw config
        $this->page_layout = $this->config->PAGE_LAYOUT;

        # prepare db object (will connect on demand)
        $this->db = new DB($this->config->DB);
    }

    //autoload controller/model classes
    public function autoload($class_name) {
        $dirs          = array();
        $bdir          = dirname(__FILE__) . '/';
        $is_controller = false;
        if (preg_match('/Controller$/', $class_name)) {
            $is_controller = true;
            $dirs[]        = $bdir . '../controllers/';
            if ($class_name !== 'FwController' && $class_name !== 'FwAdminController' && $class_name !== 'FwDynamicController' && $class_name !== 'FwApiController') {
                $class_name = preg_replace("/Controller$/", "", $class_name);
            }
        } else {
            $dirs[] = $bdir . '../models/';
            if ($class_name !== 'FwModel') {
                $class_name = preg_replace("/Model$/", "", $class_name);
            }
        }
        $dirs[] = $bdir; #also look at /www/php/fw
        $dirs[] = $bdir . '../'; #also look at /www/php

        $file_found = '';
        #logger($class_name, $dirs);
        foreach ($dirs as $dir) {
            #try class name directly
            $file = $dir . $class_name . '.php';
            if (file_exists($file)) {
                $file_found = $file;
                break;
            }

            #if not exists - try normalized
            $file = $dir . ucfirst(strtolower($class_name)) . '.php'; #normalize name, i.e. users => Users
            if (file_exists($file)) {
                $file_found = $file;
                break;
            }
        }

        if ($file_found) {
            include_once $file_found;
        } else {
            if ($is_controller) {
                throw new NoControllerException("Controller class not found: $class_name");
            } else {
                //no exception so class_exists() can work without a crash
                // throw new NoClassException("Class not found: $class_name");
            }
        }
    }

    public function runRoute() {
        try {
            $this->auth($this->route);
            $this->renderRoute($this->route);
        } catch (AuthException $ex) {
            $this->handlePageError(401, $ex->getMessage(), $ex);
        } catch (NoControllerException $ex) {
            #requested controller not found - use Home->NotFoundAction
            logger("No controller found [" . $this->route->controller . "], using default HomeController->NotFoundAction()");
            $this->route->prefix     = '';
            $this->route->controller = 'Home';
            $this->route->action     = 'NotFound';
            $this->route->id         = '';

            try {
                $this->renderRoute($this->route);
            } catch (NoClassMethodException $ex2) {
                logger('WARN', "No HomeController->NotFoundAction() found");
                $this->handlePageError(404, $ex->getMessage(), $ex);
                return;
            }
        } catch (NoClassException $ex) {
            #if can't call class - this is server error
            $this->handlePageError(500, $ex->getMessage(), $ex);
            return;
        } catch (NoClassMethodException $ex) {
            #if can't call method - so class/method doesn't exists - show using route_default_action
            logger('WARN', "No method found for route", $this->route, ", checking route_default_action");

            $default_action = $this->dispatcher->getRouteDefaultAction($this->route->controller);
            if ($default_action == 'index') {
                $this->route->action = 'Index';
            } elseif ($default_action == 'show') {
                #assume action is id and use ShowAction
                $this->route->id        = $this->route->action;
                $this->route->params[0] = $this->route->id;
                $this->route->action    = 'Show';
            } else {
                #if no default action set - this is special case action - this mean action should be got form REST's 'id'
                if ($this->route->id > '') {
                    $this->route->action = $this->route->id;
                }
            }

            try {
                $this->renderRoute($this->route);
            } catch (NoClassMethodException $ex2) {
                #if no method - just call parser() - show template from /cur_controller/cur_action dir
                logger('WARN', "Default parser");
                $this->parser();
            }
        } catch (ExitException $ex) {
            #not a problem - just graceful exit
            logger('TRACE', "Exit Exception (normal behaviour, usually due to redirect)");
        } catch (BadAccessException $ex) {
            $this->handlePageError(401, $ex->getMessage(), $ex);
        } catch (ApplicationException $ex) {
            $this->handlePageError(($ex->getCode() ? $ex->getCode() : 500), $ex->getMessage(), $ex);
        } catch (Exception $ex) {
            $this->handlePageError(500, $ex->getMessage(), $ex);
        }
    }

    public function renderRoute(stdClass $route) {
        FwHooks::beforeRenderRoute($route);

        #remember in G for rendering
        $this->GLOBAL['route']                = $route;
        $this->GLOBAL['controller']           = $route->controller;
        $this->GLOBAL['controller.action']    = $route->controller . '.' . $route->action;
        $this->GLOBAL['controller.action.id'] = $route->controller . '.' . $route->action . '.' . $route->id;

        $ps = $this->dispatcher->runController($route->controller, $route->action, $route->params);

        FwHooks::afterRenderRoute($route, $ps);

        #if action doesn't returned array - assume action rendered page by itself
        if (is_array($ps)) {
            $this->parser($ps);
        }
    }

    /**
     * return true if current request is GET request
     * @return boolean
     */
    public function isGetRequest() {
        return $this->route->method == 'GET';
    }

    /**
     * return true if script runs not under web server (i.e. cron script)
     * @return boolean
     */
    public function isOffline() {
        return (PHP_SAPI === 'cli');
    }

    #return 1 if client expects json response (based on passed route or _SERVER[HTTP_ACCEPT]) header
    public function isJsonExpected($route = null) {
        if (!is_object($route)) {
            $route = $this->route;
        }

        if ($route->format == 'json' || preg_match('!application/json!', $_SERVER['HTTP_ACCEPT'])) {
            return 1;
        } else {
            return 0;
        }
    }

    public function getResponseExpectedFormat($route = null) {
        if (!is_object($route)) {
            $route = $this->route;
        }

        $result = '';

        if ($route->format == 'json' || preg_match('!application/json!', $_SERVER['HTTP_ACCEPT'])) {
            $result = 'json';
        } elseif ($route->format == 'pjax') {
            $result = 'pjax';
        } else {
            $result = $route->format;
        }

        return $result;
    }

    # TODO $args
    public function routeRedirect($action, $controller = null, $params = null) {
        $this->route->action = $action;
        if (!is_null($controller)) {
            $this->route->controller = $controller;
        }

        if (!is_null($params)) {
            $this->route->params = $params;
        }

        $this->runRoute();
    }

    #throw AuthException if request XSS is not passed or not equal to session's value
    public function checkXSS($is_die = true) {
        if ($_SESSION["XSS"] != reqs("XSS")) {
            #logger("WARN", "XSS CHECK FAIL"); #too excessive logging
            if ($is_die) {
                throw new AuthException("XSS Error");
            }
            return false;
        }
        return true;
    }

    # simple auth check based on /controller/action - and rules filled in in Config class
    # also check for XSS in $_SESSION
    # IN: route hash
    # OUT: return TRUE throw AuthException if not authorized to view the page
    private function auth($route) {
        $ACCESS_LEVELS = array_change_key_case($this->config->ACCESS_LEVELS, CASE_LOWER);
        #XSS check for all requests that modify data
        $request_xss = reqs("XSS");
        $session_xss = $_SESSION["XSS"] ?? '';
        if (($request_xss || $this->route->method == "POST" || $this->route->method == "PUT" || $this->route->method == "DELETE")
            && $session_xss > "" && $session_xss != $request_xss
            && !in_array($this->route->controller, $this->config->NO_XSS) //no XSS check for selected controllers
        ) {
            throw new AuthException("XSS Error");
        }

        #access level check
        $path  = strtolower('/' . $this->route->controller . '/' . $this->route->action);
        $path2 = strtolower('/' . $this->route->controller);

        $current_level = -1;
        if (isset($_SESSION['access_level'])) {
            $current_level = $_SESSION['access_level'];
        }

        $rule_level = null;
        if (array_key_exists($path, $ACCESS_LEVELS)) {
            $rule_level = $ACCESS_LEVELS[$path];
        } elseif (array_key_exists($path2, $ACCESS_LEVELS)) {
            $rule_level = $ACCESS_LEVELS[$path2];
        }

        if (is_null($rule_level)) {
            #rule not found in config - try Controller.access_level
            $rule_level = $this->dispatcher->getRouteAccessLevel($this->route->controller);
        }

        if (is_null($rule_level)) {
            #if no access level set in config and in controller - no restictions
            $rule_level = -1;
        }

        if ($current_level < $rule_level) {
            throw new AuthException("Access Denied");
        }

        return true;
    }

    /**
     * @throws Exception
     */
    public function handlePageError(int $error_code, string $error_message = '', ?Exception $ex = null): void {
        #Sentry support
        global $_raven;
        if (isset($_raven)) {
            $_raven->captureException($ex, array("message" => $error_message));
        }

        if ($ex) {
            $ps = FwHooks::handleException($ex);
            if ($ps) {
                // only process exceptions there and only return here if we actually returned something
                if (!$ps["success"]) {
                    if ($ps["err_code"] >= 100 && $ps["err_code"] <= 599) {
                        #if error http code exists
                        header("HTTP/1.0 " . $ps["err_code"] . " " . $ps["err_msg"], true, $ps["err_code"]);
                    }
                    $this->parser('/error', $ps);
                }
                return;
            }
        }

        $route              = null;
        $custom_error_route = $this->dispatcher->ROUTES[$error_code] ?? '';
        if ($custom_error_route > '') {
            $route = @$this->dispatcher->str2route($custom_error_route);
        }

        if ($ex) {
            logger("FATAL", "Dispatcher - handlePageError exception: " . $ex->getMessage(), $ex);
        } else {
            logger('ERROR', "Dispatcher - handlePageError : $error_code $error_message");
        }

        if ($error_code >= 500) {
            logger($ex->getTraceAsString());
        }

        $is_error_processed = false;
        if (!is_null($route)) {
            //custom error handling route
            try {
                $this->renderRoute($route);
                $is_error_processed = true;
            } catch (NoClassException $ex) {
                //still error not processed
                logger('ERROR', 'Additional error occured during processing custom error handler: ' . $ex->getMessage());
            }
        }

        if (!$is_error_processed) {
            $uri           = $_SERVER['REQUEST_URI'];
            $err_code_desc = Dispatcher::$HTTP_CODE[$error_code];

            header("HTTP/1.0 $error_code $err_code_desc", true, $error_code);

            $err_msg = $error_message ? $error_message : ($err_code_desc ? $err_code_desc : "PAGE NOT YET IMPLEMENTED [$uri]");

            $ps = array(
                '_json'    => true, #also allow return urls by json
                'success'  => false,
                'err_code' => $error_code,
                'err_msg'  => $err_msg,
                'err_time' => time(),
            );

            //if it was Auth error - add current XSS to response, so client can try to re-send request
            if ($error_code == 401) {
                $ps['xss'] = $_SESSION["XSS"];
            }

            if ($this->GLOBAL['LOG_LEVEL'] == 'DEBUG' && $_SESSION['access_level'] == Users::ACL_SITE_ADMIN) {
                #for site admins - show additional details
                $ps['is_dump'] = true;
                if (!is_null($ex)) {
                    //remove unnecessary site root path
                    $ps['DUMP_STACK'] = str_replace($this->config->SITE_ROOT, "", $ex->getTraceAsString());
                }
                $ps['DUMP_FORM']    = print_r($_REQUEST, true);
                $ps['DUMP_SESSION'] = print_r($_SESSION, true);
            }

            $this->parser('/error', $ps);
        }
    }

    # RETURN output to browser according to expected format: full html, pjax, json
    # overloaded:
    # parser()      - show page from template  /cur_controller/cur_action = parser('/cur_controller/cur_action/', $PAGE_LAYOUT, array())
    # parser($ps)   - show page from template  /cur_controller/cur_action = parser('/cur_controller/cur_action/', $PAGE_LAYOUT, $ps)
    # parser('/controller/action', $ps)   - show page from template  /controller/action = parser('/controller/action/', $PAGE_LAYOUT, $ps)
    # parser('/controller/action', $layout, $ps)   - show page from template  /controller/action = parser('/controller/action/', $layout, $ps)
    # full params:
    # $basedir, $layout, $ps, $out_filename=''|'v'|'filename'
    #
    # output format based on requested format: json, pjax or (default) full page html
    # JSON: for automatic json response support - set ps("_json") = true
    # to return only specific content for json - set ps("_json")=array(...)
    # to override page template - set ps("_layout")="/another_page_layout.html" (relative to SITE_TEMPLATES dir)
    #
    # TODO: (not for json) to perform routeRedirect - set ps("_route_redirect"), ps("_route_redirect_controller"), ps("_route_redirect_args")
    # TODO: (not for json) to perform redirect - set ps("_redirect")="url"
    public function parser() {
        $args       = func_get_args();
        $basedir    = '';
        $controller = $this->route->controller;
        if ($this->route->prefix) {
            $basedir    .= '/' . $this->route->prefix;
            $controller = preg_replace('/^' . preg_quote($this->route->prefix, '/') . '/i', '', $controller);
        }
        $basedir      .= '/' . $controller . '/' . $this->route->action;
        $basedir      = strtolower($basedir);
        $layout       = $this->page_layout;
        $ps           = array();
        $out_filename = '';

        if (!count($args)) {
            $ps = array();
        } elseif (count($args) == 1 && is_array($args[0])) {
            $ps = &$args[0];
        } elseif (count($args) == 2 && is_string($args[0]) && is_array($args[1])) {
            $basedir = &$args[0];
            $ps      = &$args[1];
        } elseif (count($args) >= 3 && is_string($args[0]) && is_string($args[1]) && is_array($args[2])) {
            $basedir = &$args[0];
            $layout  = &$args[1];
            $ps      = &$args[2];
            if (count($args) == 4) {
                $out_filename = &$args[3];
            }
        } else {
            throw new Exception("parser - wrong call");
        }

        $out_format = $this->getResponseExpectedFormat();
        if ($out_format == 'json') {
            if (isset($ps['_json'])) {
                if ($ps['_json'] === true) {
                    # just enable whole array as json
                    parse_json($ps);
                } else {
                    # or return data only from this element
                    parse_json($ps['_json']);
                }
            } else {
                #logger("WARN", "JSON response is not enabled for the Controller.Action (set ps[_json]=true to enable).", array($_SERVER, $_REQUEST, $_SESSION));

                parse_json(array(
                    'success' => false,
                    'message' => 'JSON response is not enabled for the Controller.Action (set ps[\"_json\"]=true to enable).',
                ));

            }
        } elseif ($out_format == 'html' || $out_format == 'pjax' || !$out_format) {
            #html output based on ParsePage templates
            if ($out_format == 'pjax') {
                $layout = $this->config->PAGE_LAYOUT_PJAX;
            }

            if (array_key_exists('_layout', $ps)) {
                #override layout from parse strings
                $layout = $ps['_layout'];
            }

            if (!array_key_exists('ERR', $ps)) {
                $ps["ERR"] = $this->GLOBAL['ERR']; #add errors if any
            }

            $ps['current_time'] = time(); #TODO move to GLOBAL[current_time]?

            logger('TRACE', "basedir=[$basedir], layout=[$layout] to [$out_filename]");
            return parse_page($basedir, $layout, $ps, $out_filename);
        } else {
            #any other formats - call controller's Export($out_format)
            logger('TRACE', "export $out_format using " . $this->route->controller . "Controller.Export()");
            $this->dispatcher->callClassMethod($this->route->controller . 'Controller', 'Export', array($ps, $out_format));
        }
    }

    //flash - read/store flash data (available on the next request and only on it)
    public function flash($name, $value = null) {
        if (is_null($value)) {
            #read mode
            return $this->GLOBAL['_flash'][$name];
        } else {
            #write for next request
            #@session_start();
            $_SESSION['_flash'][$name] = $value;
            #@session_write_close();
        }
    }

    ########################## Email functions

    /**
     * Send email in UTF-8 via PHPMailer (default) or mail() (but mail() can't do SMTP or html or file attachments)
     * @param string|array $ToEmail one string or array of email addresses
     * @param string $Subj subject
     * @param string $Message message body
     * @param array $options misc options:
     *                          bcc => string for bcc emails
     *                          cc => string or array for cc emails
     *                          from => override from address (default $CONFIG['FROM_EMAIL'])
     *                          reply => reply to email
     *                          files => array(filepath, filepath, ...) or array(filename => filepath)
     * @return bool   true if message sent successfully
     */
    public function sendEmail($ToEmail, $Subj, $Message, $options = array()) {
        $MAIL   = $this->config->MAIL;
        $result = true;

        if (!is_array($ToEmail)) {
            $ToEmail = array($ToEmail);
        }

        $from = $options['from'];
        if (!$from) {
            $from = $this->config->FROM_EMAIL;
        }

        $files = $options['files'];
        if (!$files) {
            $files = array();
        }

        logger('INFO', "Sending email. From=[$from], To=[" . implode(",", $ToEmail) . "], Subj=[$Subj]");
        logger('TRACE', $Message);

        #detect if message is in html format - it should start with <!DOCTYPE or <html tag
        $is_html = false;
        if (preg_match('/^\s*<(!DOCTYPE|html)[^>]*>/', $Message)) {
            $is_html = true;
        }

        #try to send using PHPMailer class
        $file_phpmailer = dirname(__FILE__) . '/mail/class.phpmailer.php';
        if (file_exists($file_phpmailer)) {
            require_once $file_phpmailer;
            $mail = new PHPMailer;

            if ($MAIL['IS_SMTP']) {
                require_once dirname(__FILE__) . '/mail/class.smtp.php';
                $mail->isSMTP();
            }

            try {
                if ($this->config->LOG_LEVEL == 'TRACE') {
                    $mail->SMTPDebug = 3; // Enable verbose debug output
                }
                $mail->SMTPAuth   = true;
                $mail->SMTPSecure = $MAIL['SMTPSecure'];
                $mail->Host       = $MAIL['SMTP_SERVER'];
                if ($MAIL['SMTP_PORT']) {
                    $mail->Port = $MAIL['SMTP_PORT'];
                }

                $mail->Username = $MAIL['USER'];
                $mail->Password = $MAIL['PWD'];

                foreach ($ToEmail as $k => $v) {
                    $mail->addAddress($v);
                }

                #if from is in form: 'NAME <EMAIL>' - parse it
                if (preg_match("/^(.+)\s+<(.+)>$/", $from, $m)) {
                    $mail->setFrom($m[2], $m[1]);
                } else {
                    #from is usual email address
                    $mail->setFrom($from);
                }

                if ($options['reply']) {
                    $mail->addReplyTo($options['reply']);
                }

                if ($options['cc']) {
                    $mail->addCC($options['cc']);
                }

                if ($options['bcc']) {
                    $mail->addBCC($options['bcc']);
                }

                $mail->Subject = $Subj;
                $mail->Body    = $Message;
                if ($is_html) {
                    $mail->isHTML(true);
                    $mail->AltBody = 'To view the message, please use an HTML compatible email viewer!'; // optional - MsgHTML will create an alternate automatically
                }

                foreach ($files as $key => $filepath) {
                    $mail->addAttachment($filepath, (intval($key) === $key ? '' : $key)); #if key is not a number - they key is filename
                }

                $result = true;
                if (!$mail->send()) {
                    $result = false;
                    logger('WARN', 'Error sending email via PHPMailer: ' . $mail->ErrorInfo);
                }
            } catch (Exception $e) {
                logger('WARN', $e->getMessage());
                $result = false;
            }
        } else {
            if ($MAIL['IS_SMTP']) {
                logger('ERROR', 'mail() cannot send via SMTP');
            }

            if ($is_html) {
                logger('WARN', 'mail() cannot send html emails');
            }

            if ($files) {
                logger('WARN', 'mail() cannot send emails with file attachments');
            }

            #send using usual php mailer
            $more = "Content-Type: text/plain; charset=\"utf-8\" ; format=\"flowed\"\n";
            if ($from) {
                $more .= "From: $from\n";
            }

            if ($options['reply']) {
                $more .= "Reply-to: " . $options['reply'] . "\n";
            }

            if ($options['cc']) {
                $more .= "Cc: " . explode(',', $options['cc']) . "\n";
            }

            if ($options['bcc']) {
                $more .= "Bcc: " . $options['bcc'] . "\n";
            }

            if (preg_match("/\W/", $Subj)) {
                $Subj = "=?utf-8?B?" . base64_encode($Subj) . "?=";
            }

            foreach ($ToEmail as $k => $v) {
                $res = mail($v, $Subj, $Message, $more);
                if ($res === false) {
                    logger('WARN', 'Error sending email via mail(): ' . error_get_last()['message']);
                    $result = false;
                }
            }
        }

        return $result;
    }

    /**
     * Send email from text or html template in /template/emails
     * FIRST LINE IN TEMPLATE FILE - Subject
     * SECOND AND FURTHER LINES - Message body
     * @param  [type] $to_email [description]
     * @param  [type] $tpl      [description]
     * @param  [type] $ps       [description]
     * @param  [type] $options  [description]
     * @return [type]           [description]
     * Usage:
     *   $ps=array(
     *       'user' => $hU,
     *   );
     *   sendEmailTpl( $hU['email'], 'email_invite.txt', $ps);
     */
    public function sendEmailTpl($to_email, $tpl, $ps, $options = array()) {
        $msg_body = parse_page('/emails', $tpl, $ps, 'v');
        list($msg_subj, $msg_body) = preg_split("/\n/", $msg_body, 2);

        return $this->sendEmail($to_email, $msg_subj, $msg_body, $options);
    }

    ##########################  STATIC methods

    // redirect to relative or absolute url using header
    public static function redirect($url, $noexit = false) {
        global $CONFIG;
        $url = fw::url2abs($url);

        logger("REDIRECT to [$url]");
        header("Location: $url");
        if (!$noexit) {
            exit;
        }
    }

    #make url absolute
    # if url start with "/" - it's redirect to url relative to current host
    # /some_site_url?aaaa => http://root_domain/ROOT_URL/some_site_url?aaaa
    public static function url2abs($url) {
        global $CONFIG;
        if (substr($url, 0, 1) == '/') {
            $url = $CONFIG['ROOT_DOMAIN'] . $CONFIG['ROOT_URL'] . $url;
        }

        return $url;
    }
}

//Helper/debug functions - TODO move to fw or Utils class?

//get some value from $_REQUEST
//TODO? make support of infinite []
function _req($name) {
    if (preg_match('/^(.+?)\[(.+?)]/', $name, $m)) {
        return $_REQUEST[$m[1]][$m[2]] ?? '';
    } else {
        return $_REQUEST[$name] ?? '';
    }
}

//get integer value from $_REQUEST
//return 0
function reqi($name) {
    return intval(_req($name));
}

//get string value from $_REQUEST
//return 0
function reqs($name) {
    return _req($name) . '';
}

//shortcut to $_REQUEST[$name]
//return value from request
function req($name) {
    return @$_REQUEST[$name];
}

//return hash/array from request (if no such param or not array - returns empty array)
function reqh($name) {
    $h = $_REQUEST[$name];
    if (!is_array($h)) {
        $h = array();
    }

    return $h;
}

//get bool value from $_REQUEST
// true only if "true" or 1 passed
//return bool
function reqb($name) {
    $value = reqs($name);
    if ($value == "true" || $value == 1) {
        return true;
    } else {
        return false;
    }
}

//get decoded json array from $_REQUEST
//return array
function reqjson($name) {
    $result = json_decode(reqs($name), true) ?? [];
    if (!is_array($result)) {
        $result = [];
    }
    return $result;
}

########################## for debug
# IN: [logtype (ALL|TRACE|STACK|DEBUG|INFO|WARN|ERROR|FATAL), default DEBUG] and variable number of params
# OUT: none, just write to $site_error_log
# If not ALL - limit output to 2048 chars per call
# example: logger('DEBUG', 'hello there', $var);
function logger() {
    $args = func_get_args();
    if (FwHooks::logger($args)) {
        #if logger overridden - don't use standard logger
        return;
    }

    global $CONFIG;
    $log_level = fw::$LOG_LEVELS[$CONFIG['LOG_LEVEL']];

    if (!$log_level) {
        #don't log if logger is off (for production)
        return;
    }

    $logtype = 'DEBUG'; #default log type
    if (count($args) > 0 && is_string($args[0]) && preg_match("/^(ALL|TRACE|STACK|DEBUG|INFO|WARN|ERROR|FATAL)$/", $args[0], $m)) {
        $logtype = $m[1];
        array_shift($args);
    }

    if (fw::$LOG_LEVELS[$logtype] > $log_level) {
        return; #skip logging if requested level more than config's log level
    }

    $arr      = debug_backtrace(); #0-logger(),1-func called logger,...
    $func     = (isset($arr[1]) ? $arr[1] : '');
    $function = isset($func['function']) ? $func['function'] : '';
    $line     = $arr[1]['line'];

    //remove unnecessary site_root_offline path
    $func['file'] = str_replace(strtolower($CONFIG['SITE_ROOT']), "", strtolower($func['file']));

    $date   = new DateTime();
    $strlog = $date->format("Y-m-d H:i:s.v") . ' ' . getmypid() . ' ' . $logtype . ' ' . $func['file'] . '::' . $function . '(' . $line . ') ';
    foreach ($args as $str) {
        if (is_scalar($str)) {
            $strlog .= $str;
        } else {
            $strlog .= print_r($str, true);
        }
        $strlog .= "\n";
    }

    if ($logtype != 'ALL') {
        //cut too long logging
        if (strlen($strlog) > 2048) {
            $strlog = substr($strlog, 0, 2048) . '...' . substr($strlog, -128);
        }

        if (!preg_match("/\n$/", $strlog)) {
            $strlog .= "\n";
        }
    }

    @error_log($strlog, $CONFIG['LOG_MESSAGE_TYPE'], $CONFIG['site_error_log']); #using @ to prevent warnings if log not writable

    #Sentry support - if enabled also log to Sentry breadcrumbs
    if (isset($_raven)) {
        $_raven->breadcrumbs->record(array(
            'message' => $strlog,
            'level'   => strtolower($logtype),
        ));
    }

    if ($logtype == 'STACK') {
        $e = new Exception();
        @error_log($e->getTraceAsString() . "\n", $CONFIG['LOG_MESSAGE_TYPE'], $CONFIG['site_error_log']);
    }
}

########################### for debugging with output right into the browser or console
function rw($var) {
    $is_html = $_SERVER['HTTP_HOST'] ? 1 : 0;
    if (!is_scalar($var)) {
        $var = print_r($var, true);
    }
    if ($is_html) {
        $var = "<pre>" . preg_replace("/\n/", "<br>\n", $var) . "</pre>";
    }
    echo $var . "\n";
    flush();
}

########################### same
function rwe($var) {
    rw($var);
    die;
}
