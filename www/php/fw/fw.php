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
require_once dirname(__FILE__) . '/../vendor/autoload.php';
#also directly preload common classes used in every request:
require_once dirname(__FILE__) . "/Utils.php";
require_once dirname(__FILE__) . "/FwModel.php";
require_once dirname(__FILE__) . "/../SiteUtils.php";
if (PHP_SAPI !== 'cli') {
    require_once dirname(__FILE__) . "/FwController.php";
}

class fw {
    //controller standard actions
    public const string ACTION_SUFFIX        = "Action";
    public const string ACTION_INDEX         = "Index";
    public const string ACTION_SHOW          = "Show";
    public const string ACTION_SHOW_FORM     = "ShowForm";
    public const string ACTION_SHOW_FORM_NEW = "New"; // not actual action, just a const
    public const string ACTION_SAVE          = "Save";
    public const string ACTION_SAVE_MULTI    = "SaveMulti";
    public const string ACTION_SHOW_DELETE   = "ShowDelete";
    public const string ACTION_DELETE        = "Delete";
    //additional actions used across controllers
    public const string ACTION_DELETE_RESTORE  = "RestoreDeleted";
    public const string ACTION_NEXT            = "Next"; // prev/next on view/edit forms
    public const string ACTION_AUTOCOMPLETE    = "Autocomplete"; // autocomplete json
    public const string ACTION_USER_VIEWS      = "UserViews"; // custom user views modal
    public const string ACTION_SAVE_USER_VIEWS = "SaveUserViews"; // custom user views sacve changes
    public const string ACTION_SAVE_SORT       = "SaveSort"; // sort rows on list screen

    //helpers for route.action_more
    public const string ACTION_MORE_NEW    = "new";
    public const string ACTION_MORE_EDIT   = "edit";
    public const string ACTION_MORE_DELETE = "delete";


    public static ?self $instance = null;
    public static float $start_time;

    public Dispatcher $dispatcher;
    public DB $db;

    public string $request_url; #current request url (relative to application url)
    public stdClass $route; #current request route data, stdClass

    public array $GLOBAL = []; #"global" vars, initialized with $CONFIG - used in template engine, also stores "_flash"
    public array $FormErrors = []; # for storing form id's with error messages, put to ps("ERR") for parser
    public object $config; #copy of the config as object, usage: $this->fw->config->ROOT_URL; $this->fw->config->DB['DBNAME'];
    public array $models_cache = []; #cached model instances

    public string $page_layout;
    public bool $is_session = true; #if use session, can be set to false in initRequest to abort session use
    public bool $is_log_events = true; // can be set temporarly to false to prevent event logging (for batch process for ex)

    public static array $LOG_LEVELS = array(
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
    public static function initOffline($caller = ""): void {
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

    public static function endRequest($fw = null): void {
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
     * usage: $model = Users::i();
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
        } catch (NoClassException) {
            throw new NoModelException("Model class not found: $model_class");
        }
        $fw->models_cache[$cache_key] = $object;
        return $object;
    }

    public function __construct() {
        global $CONFIG;
        spl_autoload_register(array($this, 'autoload'));

        $this->GLOBAL                 = $CONFIG;
        $this->FormErrors             = []; #store form errors
        $this->GLOBAL['current_time'] = time(); #current time for the request
        $CONFIG['LANG']               = $CONFIG['LANG_DEF']; #use default language

        # fw vairables
        $this->config      = (object)$CONFIG; #set fw config
        $this->page_layout = $this->config->PAGE_LAYOUT;

        # prepare db object (will connect on demand)
        $this->db = new DB($this->config->DB);
    }

    //autoload controller/model classes
    public function autoload($class_name): void {
        $dirs          = array();
        $bdir          = dirname(__FILE__) . '/';
        $is_controller = false;
        if (str_ends_with($class_name, 'Controller')) {
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

    public function runRoute(): void {
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
            } catch (NoClassMethodException) {
                logger('WARN', "No HomeController->NotFoundAction() found");
                $this->handlePageError(404, $ex->getMessage(), $ex);
                return;
            }
        } catch (NoClassException $ex) {
            #if can't call class - this is server error
            $this->handlePageError(500, $ex->getMessage(), $ex);
            return;
        } catch (NoClassMethodException) {
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
            } catch (NoClassMethodException) {
                #if no method - just call parser() - show template from /cur_controller/cur_action dir
                logger('WARN', "Default parser");
                $this->parser();
            }
        } catch (ExitException) {
            #not a problem - just graceful exit
            logger('TRACE', "Exit Exception (normal behaviour, usually due to redirect)");
        } catch (ApplicationException $ex) {
            $this->handlePageError(($ex->getCode() ?: 500), $ex->getMessage(), $ex);
        } catch (Exception $ex) {
            $this->handlePageError(500, $ex->getMessage(), $ex);
        }
    }

    public function renderRoute(stdClass $route): void {
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
     * return true if script runs not under web server (i.e. cron script)
     * @return boolean
     */
    public function isOffline(): bool {
        return (PHP_SAPI === 'cli');
    }

    #return 1 if client expects json response (based on passed route or _SERVER[HTTP_ACCEPT]) header
    public function isJsonExpected($route = null): int {
        if (!is_object($route)) {
            $route = $this->route;
        }

        if ($route->format == 'json' || str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
            return 1;
        } else {
            return 0;
        }
    }

    public function getResponseExpectedFormat($route = null) {
        if (!is_object($route)) {
            $route = $this->route;
        }

        if ($route->format == 'json' || str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) {
            $result = 'json';
        } elseif ($route->format == 'pjax' || isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            $result = 'pjax';
        } else {
            $result = $route->format;
        }

        return $result;
    }

    # TODO $args
    public function routeRedirect(string $action, ?string $controller = null, ?array $params = null): void {
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
    public function checkXSS(bool $is_die = true): bool {
        if ($_SESSION["XSS"] != reqs("XSS")) {
            #logger("WARN", "XSS CHECK FAIL"); #too excessive logging
            if ($is_die) {
                throw new AuthException("XSS Error. Reload the page or try to re-login");
            }
            return false;
        }
        return true;
    }

    # simple auth check based on /controller/action - and rules filled in in Config class
    # also check for XSS in $_SESSION
    # IN: route hash
    # OUT: return TRUE throw AuthException if not authorized to view the page
    private function auth(stdClass $route): void {
        $ACCESS_LEVELS = array_change_key_case($this->config->ACCESS_LEVELS);
        #XSS check for all requests that modify data
        $request_xss = reqs("XSS");
        $session_xss = $_SESSION["XSS"] ?? '';
        if (($request_xss || $route->method == "POST" || $route->method == "PUT" || $route->method == "DELETE")
            && $session_xss > "" && $session_xss != $request_xss
            && !in_array($route->controller, $this->config->NO_XSS) //no XSS check for selected controllers
        ) {
            throw new AuthException("XSS Error");
        }

        #access level check
        $path  = strtolower('/' . $route->controller . '/' . $route->action);
        $path2 = strtolower('/' . $route->controller);

        $current_level = self::userAccessLevel();

        $rule_level = null;
        if (array_key_exists($path, $ACCESS_LEVELS)) {
            $rule_level = $ACCESS_LEVELS[$path];
        } elseif (array_key_exists($path2, $ACCESS_LEVELS)) {
            $rule_level = $ACCESS_LEVELS[$path2];
        }

        if (is_null($rule_level)) {
            #rule not found in config - try Controller.access_level
            $rule_level = $this->dispatcher->getRouteAccessLevel($route->controller);
        }

        if (is_null($rule_level)) {
            #if no access level set in config and in controller - no restictions
            $rule_level = Users::ACL_VISITOR;
        }

        if ($current_level < $rule_level) {
            throw new AuthException("Access Denied");
        }

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

            $err_msg = $error_message ?: ($err_code_desc ?: "PAGE NOT YET IMPLEMENTED [$uri]");

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

            if ($this->GLOBAL['LOG_LEVEL'] == 'DEBUG' && self::userAccessLevel() == Users::ACL_SITE_ADMIN) {
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
    # (not for json) to perform route_redirect - set ps("_route_redirect") = ["method" => , "controller" => , "args" => ]
    # TODO: (not for json) to perform redirect - set ps("_redirect")="url"
    public function parser(): void {
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
            #no args - use default
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

        if ($this->FormErrors && !isset($ps['ERR'])) {
            $ps['ERR'] = $this->FormErrors; // add form errors if any
            logger("DEBUG", "Form errors:", $ps["ERR"]);
        }

        $out_format = $this->getResponseExpectedFormat();
        if ($out_format == 'json') {
            if (isset($ps['_json'])) {
                if ($ps['_json'] === true) {
                    # just enable whole array as json
                    unset($ps['_json']); // remove internal flag
                    $this->parserJson($ps);
                } else {
                    # or return data only from this element
                    $this->parserJson($ps['_json']);
                }
            } else {
                $msg = "JSON response is not enabled for the Controller.Action (set ps[_json]=true to enable).";
                logger("DEBUG", $msg);

                $this->parserJson([
                    'success' => false,
                    'message' => $msg,
                ]);
            }

            return; // no further processing for json
        }

        if ($ps["_route_redirect"]) {
            $rr = $ps["_route_redirect"];
            $this->routeRedirect($rr["method"], $rr["controller"], $rr["args"]);
            return; // no further processing
        }

        if ($ps["_redirect"]) {
            self::redirect($ps["_redirect"]);
            return; // no further processing
        }

        $layout = $out_format == 'pjax' ? $this->config->PAGE_LAYOUT_PJAX : $this->page_layout;
        if (array_key_exists('_layout', $ps)) {
            #override layout from parse strings
            $layout = $ps['_layout'];
        }

        logger('TRACE', "basedir=[$basedir], layout=[$layout] to [$out_filename]");
        parse_page($basedir, $layout, $ps, $out_filename);
    }

    public function parserJson(array $ps): void {
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode($ps);
    }

    //flash - read/store flash data (available on the next request and only on it)
    public function flash($name, $value = null) {
        if (is_null($value)) {
            #read mode
            return $this->GLOBAL['_flash'][$name];
        } else {
            if (!$this->isJsonExpected()) {
                #write for the next request
                #@session_start();
                $_SESSION['_flash'][$name] = $value;
                #@session_write_close();
            }
            return $this; //for chaining
        }
    }

    /**
     * shortcut for currently logged users.id
     * @return int
     */
    public static function userId(): int {
        return intval($_SESSION['user_id'] ?? 0);
    }

    public static function userAccessLevel(): int {
        return intval($_SESSION['access_level'] ?? 0);
    }

    public static function isLogged(): bool {
        return (self::userId() > 0);
    }

    ########################## Email functions

    /**
     * Send email in UTF-8 via PHPMailer (default) or mail() (but mail() can't do SMTP or html or file attachments)
     * @param array|string $ToEmail one string or array of email addresses
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
    public function sendEmail(array|string $ToEmail, string $Subj, string $Message, array $options = array()): bool {
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

        if ($MAIL['IS_SMTP']) {
            #send using PHPMailer class
            $mailer = new PHPMailer\PHPMailer\PHPMailer;
            $mailer->isSMTP();

            try {
                if ($this->config->LOG_LEVEL == 'TRACE') {
                    $mailer->SMTPDebug = 3; // Enable verbose debug output
                }
                $mailer->SMTPAuth   = true;
                $mailer->SMTPSecure = $MAIL['SMTPSecure'];
                $mailer->Host       = $MAIL['SMTP_SERVER'];
                if ($MAIL['SMTP_PORT']) {
                    $mailer->Port = $MAIL['SMTP_PORT'];
                }

                $mailer->Username = $MAIL['USER'];
                $mailer->Password = $MAIL['PWD'];

                foreach ($ToEmail as $v) {
                    $mailer->addAddress($v);
                }

                #if from is in form: 'NAME <EMAIL>' - parse it
                if (preg_match("/^(.+)\s+<(.+)>$/", $from, $m)) {
                    $mailer->setFrom($m[2], $m[1]);
                } else {
                    #from is usual email address
                    $mailer->setFrom($from);
                }

                if (isset($options['reply'])) {
                    $mailer->addReplyTo($options['reply']);
                }

                if (isset($options['cc'])) {
                    if (is_array($options['cc'])) {
                        foreach ($options['cc'] as $v) {
                            $mailer->addCC($v);
                        }
                    } else {
                        #if cc is string (not array) - assume it's comma separated list
                        $mailer->addCC($options['cc']);
                    }
                }

                if (isset($options['bcc'])) {
                    $mailer->addBCC($options['bcc']);
                }

                $mailer->Subject = $Subj;
                $mailer->Body    = $Message;
                if ($is_html) {
                    $mailer->isHTML();
                    $mailer->AltBody = 'To view the message, please use an HTML compatible email viewer!'; // optional - MsgHTML will create an alternate automatically
                }

                foreach ($files as $key => $filepath) {
                    $mailer->addAttachment($filepath, (intval($key) === $key ? '' : $key)); #if key is not a number - they key is filename
                }

                if (!$mailer->send()) {
                    $result = false;
                    logger('WARN', 'Error sending email via PHPMailer: ' . $mailer->ErrorInfo);
                }
            } catch (Exception $e) {
                logger('WARN', $e->getMessage());
                $result = false;
            }
        } else {
            #send via standard mail()

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

            if (isset($options['reply'])) {
                $more .= "Reply-to: " . $options['reply'] . "\n";
            }

            if (isset($options['cc'])) {
                if (is_array($options['cc'])) {
                    $more .= "Cc: " . implode(',', $options['cc']) . "\n";
                } else {
                    #if cc is string (not array) - assume it's comma separated list
                    $more .= "Cc: " . $options['cc'] . "\n";
                }
            }

            if (isset($options['bcc'])) {
                $more .= "Bcc: " . $options['bcc'] . "\n";
            }

            if (preg_match("/\W/", $Subj)) {
                $Subj = "=?utf-8?B?" . base64_encode($Subj) . "?=";
            }

            foreach ($ToEmail as $v) {
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
     * @param string|array $to_email
     * @param string $tpl
     * @param array $ps
     * @param array $options
     * @return bool [type]           [description]
     * Usage:
     *   $ps=array(
     *       'user' => $hU,
     *   );
     *   sendEmailTpl( $hU['email'], 'email_invite.txt', $ps);
     */
    public function sendEmailTpl(string|array $to_email, string $tpl, array $ps, array $options = array()): bool {
        $msg_body = parse_page('/emails', $tpl, $ps, 'v');
        list($msg_subj, $msg_body) = preg_split("/\n/", $msg_body, 2);

        return $this->sendEmail($to_email, $msg_subj, $msg_body, $options);
    }

    public function logActivity(string $log_types_icode, string $entity_icode, int $item_id = 0, string $iname = "", array $changed_fields = null): void {
        if (!$this->is_log_events) {
            return;
        }

        $payload = null;
        if ($changed_fields) {
            $payload = array(
                'fields' => $changed_fields
            );
        }

        FwActivityLogs::i()->addSimple($log_types_icode, $entity_icode, $item_id, $iname, $payload);
    }

    ##########################  STATIC methods

    // redirect to relative or absolute url using header
    public static function redirect(string $url, bool $noexit = false): null {
        $url = fw::url2abs($url);

        logger("REDIRECT to [$url]");
        header("Location: $url");
        if (!$noexit) {
            exit;
        }
        return null;
    }

    #make url absolute
    # if url start with "/" - it's redirect to url relative to current host
    # /some_site_url?aaaa => http://root_domain/ROOT_URL/some_site_url?aaaa
    public static function url2abs(string $url): string {
        global $CONFIG;
        if (str_starts_with($url, '/')) {
            $url = $CONFIG['ROOT_DOMAIN'] . $CONFIG['ROOT_URL'] . $url;
        }

        return $url;
    }
}

//Helper/debug functions - TODO move to fw or Utils class?

//get some value from $_REQUEST
//TODO? make support of infinite []
function _req(string $name) {
    if (preg_match('/^(.+?)\[(.+?)]/', $name, $m)) {
        return $_REQUEST[$m[1]][$m[2]] ?? '';
    } else {
        return $_REQUEST[$name] ?? '';
    }
}

//get integer value from $_REQUEST
//return 0
function reqi(string $name): int {
    return intval(_req($name));
}

//get string value from $_REQUEST
//return 0
function reqs(string $name): string {
    return _req($name) . '';
}

//shortcut to $_REQUEST[$name]
//return value from request
function req(string $name) {
    return @$_REQUEST[$name];
}

//return hash/array from request (if no such param or not array - returns empty array)
function reqh(string $name) {
    $h = $_REQUEST[$name] ?? [];
    if (!is_array($h)) {
        $h = array();
    }

    return $h;
}

// return date (timestamp seconds) from request
function reqd(string $name): int {
    $date = reqs($name);
    if ($date) {
        $date = strtotime($date);
    }

    return intval($date);
}

//get bool value from $_REQUEST
// true only if "true" or 1 passed
//return bool
function reqb($name): bool {
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
# IN: [logtype (ALL|TRACE|DEBUG|NOTICE|INFO|WARN|ERROR|FATAL), default DEBUG] and variable number of params
# OUT: none, just write to $site_error_log
# If not ALL - limit output to 2048 chars per call
# example: logger('DEBUG', 'hello there', $var);
function logger(): void {
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
    if (count($args) > 0 && is_string($args[0]) && preg_match("/^(ALL|TRACE|DEBUG|NOTICE|INFO|WARN|ERROR|FATAL)$/", $args[0], $m)) {
        $logtype = $m[1];
        array_shift($args);
    }

    if (fw::$LOG_LEVELS[$logtype] > $log_level) {
        return; #skip logging if requested level more than config's log level
    }

    $arr      = debug_backtrace(); #0-logger(),1-func called logger,...
    $func     = ($arr[1] ?? '');
    $function = $func['function'] ?? '';
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
}

########################### for debugging with output right into the browser or console
function rw($var): void {
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
function rwe($var): void {
    rw($var);
    die;
}
