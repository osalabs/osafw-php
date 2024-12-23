<?php

/*
Dispatcher/router class - used for call funcs/methods by URL
by default RESTful approach assumed

Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2024 Oleg Savchuk www.osalabs.com

SAMPLE USAGE:

$ROUTES = array(
''      => 'index', //default route
'401'   => 'baseview::page401', //default 401 page (Unauthorized)
'404'   => 'baseview::page404', //default 404 page (Not Found)
'/aaa/bbb/ccc'  => 'class',         #class to work
'/aaa/bbb/ccc'  => 'class::method', #particular method to work with uri
'/aaa/bbb/ccc'  => '::method',      #global function to work with uri
'/admin/user'  => '/auser',         #"internal redirect"
'MODULE'  => 'CLASS',               #resource replacing - process MODULE with class CLASS
'^/regexp/(\d+)'=> 'class',         #match uri by regexp TODO???
);
# MODULE - chars allowed just a-z and 0-9, case insensitive


Dispatcher::go($ROUTES);


RESTful interface:
GET   /controller                 Index
POST  /controller                 Save     (save new record - Create)
PUT   /controller                 SaveMulti (update multiple records)
GET   /controller/new             ShowForm (show new form - ShowNew)
GET   /controller/{id}[.format]   Show     (show in format - not for editing)
GET   /controller/{id}/edit       ShowForm (show edit form - ShowEdit)
GET   /controller/{id}/delete     ShowDelete
POST/PUT  /controller/{id}        Save     (save changes to exisitng record - Update    Note:$_POST should contain data
DELETE  /controller/{id}          Delete   (alternative: POST with _method=DELETE)

/controller/(Action)              Action    call for arbitrary action from the controller
/controller/Action                Action    call for arbitrary action from the controller, action name should be less than 32 chars

id - record id (numeric or UUID - at least 32 chars no dashes)

 */

class Dispatcher {
    public const array METHOD_ALLOWED = array(
        'GET'    => true,
        'POST'   => true,
        'PUT'    => true,
        'DELETE' => true,
    );

    # public static $table_name = '';
    public const array REST2METHOD_MAP = array(
        'view'        => FW::ACTION_SHOW,
        'create'      => FW::ACTION_SAVE,
        'update'      => FW::ACTION_SAVE,
        'updatemulti' => FW::ACTION_SAVE_MULTI,
        'delete'      => FW::ACTION_DELETE,
        'showdelete'  => FW::ACTION_SHOW_DELETE,
        'list'        => FW::ACTION_INDEX,
        'new'         => FW::ACTION_SHOW_FORM,
        'edit'        => FW::ACTION_SHOW_FORM,
        'options'     => FW::ACTION_OPTIONS,
    );

    public const array HTTP_CODE = array(
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        500 => 'Internal server error',
    );

    public array $ROUTES = array();
    public string $ROOT_URL;
    public array $ROUTE_PREFIXES; #array('/Admin', '/My', ...)
    public string $request_url; #last url processed by uriToRoute

    public function __construct(array $ROUTES = array(), string $ROOT_URL = '', array $ROUTE_PREFIXES = array()) {
        $this->ROUTES         = $ROUTES;
        $this->ROOT_URL       = $ROOT_URL;
        $this->ROUTE_PREFIXES = $ROUTE_PREFIXES;
    }

    # get route for method/uri with defaults
    public function getRoute(): stdClass {
        $method = $_SERVER['REQUEST_METHOD']; #ex: POST, GET
        $uri    = $_SERVER['REQUEST_URI']; #ex: /add/post/12390/alksjdla?qoeewlkj

        $route = $this->uriToRoute($method, $uri, $this->ROUTES);
        #logger("ROUTE found:", $route);
        logger('TRACE', "ROUTE: " . $route->method . ' ' . $route->controller . '.' . $route->action . ' id=' . $route->id . ' ' . $route->action_more);

        return $route;
    }

    /**
     * run controller action
     *  Also calls controller's checkAccess() before action
     *  Also calls controller's actionError() in case of ApplicationException
     * @param string $controller_name - controller class name "Controller" suffix is added automatically (ex: "Users" -> "UsersController")
     * @param string $action_name - action name without suffix (ex: "Index" -> "IndexAction")
     * @param array $aparams - additional params to pass to action
     * @return array|null - array of params to pass to parser or null if no parser needed
     * @throws AuthException
     * @throws DBException
     * @throws NoClassMethodException
     * @throws NoControllerException
     * @throws NoModelException
     */
    public function runController(string $controller_name, string $action_name, array $aparams = array()): ?array {
        if (!$controller_name) {
            throw new NoControllerException();
        }
        if (!$action_name) {
            throw new NoClassMethodException();
        }
        $class_name = $controller_name . 'Controller';
        if (!class_exists($class_name)) {
            throw new NoControllerException();
        }
        $method_name = $action_name . FW::ACTION_SUFFIX;

        /** @var FwController $controller */
        $controller = new $class_name;
        $ps         = [];

        try {
            $controller->checkAccess();
            if (!method_exists($controller, $method_name)) {
                throw new NoClassMethodException();
            }
            //call controller's method in $method_name and expand $aparams array into method params
            $ps = $controller->$method_name(...$aparams);

            //special case for export - IndexAction+export_format is set - call exportList without parser
            if ($method_name == FW::ACTION_INDEX . FW::ACTION_SUFFIX && $controller->export_format > '') {
                $controller->exportList($aparams);
                $ps = null; // disable parser
            }

        } catch (ApplicationException $ex) {
            $ps = $controller->actionError($ex, $aparams);
        }

        return $ps;
    }

    public function getRouteDefaultAction(string $controller) {
        $class_name = $controller . 'Controller';
        if (!class_exists($class_name)) {
            throw new NoControllerException();
        }

        return $class_name::route_default_action;
    }

    public function getRouteAccessLevel(string $controller) {
        $class_name = $controller . 'Controller';
        if (!class_exists($class_name)) {
            throw new NoControllerException();
        }

        return $class_name::access_level;
    }

    #IN: controller::action
    #OUT: array(controller, action)
    public function splitRoute(string $route): array {
        list($controller, $action) = explode('::', $route);
        if (!$controller) {
            #TODO - global
        } elseif (!$action) {
            $action = FW::ACTION_INDEX;
        }

        return array($controller, $action);
    }

    # string to route
    # ususally string is from $ROUTES
    public function str2route(string $str): stdClass {
        list($controller, $action) = $this->splitRoute($str);

        #TODO handle controller prefix?

        $result              = new stdClass;
        $result->method      = 'GET';
        $result->prefix      = '';
        $result->controller  = $controller;
        $result->action      = $action;
        $result->id          = '';
        $result->action_more = '';
        $result->format      = 'html';
        $result->params      = array();

        return $result;
    }

    public function detectOperation(string $method, string $id, string $action_more): string {
        //$oper= $uri1=='new'?'new':( isset($uri2)?$uri2:'' );
        $result = '';

        if ($method == 'GET') {
            if ($action_more == 'new') {
                $result = 'new';
            } elseif ($id > '' && $action_more == 'edit') {
                $result = 'edit';
            } elseif ($id > '' && $action_more == 'delete') {
                $result = 'showdelete';
            } elseif ($id > '') {
                $result = 'view';
            } else {
                $result = 'list';
            }
        } elseif ($method == 'POST') {
            if ($id > '') {
                $result = 'update';
            } else {
                $result = 'create';
            }
        } elseif ($method == 'PUT') {
            if ($id > '') {
                $result = 'update';
            } else {
                $result = 'updatemulti';
            }

        } elseif ($method == 'DELETE' && $id > '') {
            $result = 'delete';
        } elseif ($method == 'OPTIONS') {
            $result = 'options';
        }

        if (!$result) {
            #just respond with 405 and exit immediately
            header("HTTP/1.0 405 Method Not Allowed", true, 405);
            header("Allow: GET, POST, PUT, DELETE, OPTIONS"); #instruct client what methods are allowed
            exit;
            #throw new Exception('Unsupported REST params combination'); #405 Method Not Allowed
        }

        return $result;
    }

    # for a given method, uri and routes return controller/action
    # usually:
    #   $method=$_SERVER['REQUEST_METHOD'];    #ex: POST, GET
    #   $uri=$_SERVER['REQUEST_URI'];         #ex: /add/post/12390/alksjdla?qoeewlkj
    # IN: method, uri, routes
    # OUT: hash:
    #       method
    #       prefix
    #       controller
    #       action
    #       id
    #       action_more
    #       format
    #       params
    public function uriToRoute(string $method, string $uri, array $ROUTES): stdClass {
        $root_url = $this->ROOT_URL;

        if ($root_url) {
            $uri = preg_replace("/^" . preg_quote($root_url, '/') . "/", '', $uri); #remove root_url if any
        }

        $uri               = preg_replace("/\?.*/", '', $uri); #remove query if any , ex. /add/post/12390/alksjdla
        $uri               = preg_replace("!/$!", '', $uri); #remove last /
        $uri               = str_replace(["%28", "%29"], ["(", ")"], $uri); # mobile chrome posts urls like /xxx/(Save) to /xxx/%28Save%29 for some reason, need to un-escape
        $this->request_url = $uri;

        #check if method override exists
        $tmp_method_check = $_POST['_method'] ?? '';
        if ($tmp_method_check > '' && array_key_exists($tmp_method_check, self::METHOD_ALLOWED)) {
            $method = $tmp_method_check;
        }

        #prefixes (such as /Admin /Member - setup in Config.php::$ROUTE_PREFIXES) - or do via $ROUTES?
        $controller_prefix = '';
        foreach ($this->ROUTE_PREFIXES as $prefix) {
            $qprefix = preg_quote($prefix, '/');
            if (preg_match('/^' . $qprefix . '/i', $uri)) {
                $controller_prefix = Utils::routeFixChars($prefix, true);
                $uri               = preg_replace('/^' . $qprefix . '/i', '', $uri);
                break;
            }
        }

        $cur_controller  = 'Home';
        $cur_action      = FW::ACTION_INDEX;
        $cur_id          = '';
        $cur_action_more = '';
        $cur_format      = 'html';
        $cur_aparams     = array(); #stores additional resourse/id  i.e. /user/999/notes/888/attachments/777

        #process ROUTES to find matching routes
        $is_route_found = false;
        if (count($ROUTES)) {
            while (!$is_route_found) {
                if (array_key_exists($uri, $ROUTES)) {
                    if ($ROUTES[$uri][0] == '/') {
                        #if started from / - this is redirect url
                        $uri = $ROUTES[$uri];
                    } else {
                        #otherwise - it's a direct class-method to call
                        list($cur_controller, $cur_action) = $this->splitRoute($ROUTES[$uri]);
                        $is_route_found = true;
                        break;
                    }
                } else {
                    break;
                }
            }

            if (!$is_route_found) { #if no route found - try to process regexp routes
                #TODO
            }
        }

        if (!$is_route_found) {
            #if no special ROUTES found - try to detect default RESTful URLs
            $RX_CONTROLLER = '[^/]+';
            $RX_ID         = '\d+|[\w_]{32,}'; // Match numeric IDs or UUID-like IDs (at least 32 chars, no dashes). I.e. custom action names should be less than 32 chars

            #get RESTful URI
            $is_match = preg_match("!^/($RX_CONTROLLER)(?:/(new|\.\w+)|/($RX_ID)(?:\.(\w+))?(?:/(edit|delete))?)?/?$!i", $uri, $m);

            #logger('TRACE', "$method $uri => REST is_match=$is_match");
            #logger($m);

            if ($is_match) {
                $cur_controller = Utils::routeFixChars($m[1], true);
                if (!strlen($cur_controller)) {
                    throw new Exception("Wrong request", 1);
                }

                #TODO - capitalize controller name or not? or site-wide option?

                $cur_id          = $m[3] ?? '';
                $cur_format      = $m[4] ?? '';
                $cur_action_more = $m[5] ?? '';

                $tmp_check_suffix = $m[2] ?? ''; #could contain "new" or ".format"
                if ($tmp_check_suffix > '') {
                    if ($tmp_check_suffix == FW::ACTION_MORE_NEW) {
                        $cur_action_more = FW::ACTION_MORE_NEW;
                    } else {
                        $cur_format = substr($tmp_check_suffix, 1);
                    }
                }

                $rest_oper  = $this->detectOperation($method, $cur_id, $cur_action_more);
                $cur_action = self::REST2METHOD_MAP[$rest_oper];

                #check if there is mapping module => class present and replace dest class
                if (array_key_exists($cur_controller, $ROUTES)) {
                    $cur_controller = $ROUTES[$cur_controller];
                }
            } else {
                #otherwise detect controller/action/id.format/more_action
                if ($uri) {
                    $arr = explode("/", $uri);
                    #0 element is empty string, not needed
                    $cur_controller  = $arr[1] ?? '';
                    $cur_action      = $arr[2] ?? '';
                    $cur_id          = $arr[3] ?? '';
                    $cur_action_more = $arr[4] ?? '';
                    $cur_controller  = Utils::routeFixChars($cur_controller, true);

                    #special case for OPTIONS request -> always call Options method
                    if ($method == 'OPTIONS') {
                        $cur_action = FW::ACTION_OPTIONS;
                    }
                }

                #call default method $ROUTES['']
                #@list($cur_controller,$cur_action)=$this->splitRoute($ROUTES['']);
                #logger('TRACE', "DEFAULT call $cur_controller->$cur_action()\n");
            }
        }

        $cur_controller = $controller_prefix . $cur_controller;
        $cur_action     = Utils::routeFixChars($cur_action, true);
        if (!strlen($cur_action)) {
            $cur_action = FW::ACTION_INDEX;
        }

        array_unshift($cur_aparams, $cur_id); #first param always is id

        $result              = new stdClass;
        $result->method      = $method;
        $result->prefix      = $controller_prefix;
        $result->controller  = $cur_controller;
        $result->action      = $cur_action;
        $result->id          = $cur_id;
        $result->action_more = $cur_action_more;
        $result->format      = $cur_format;
        $result->params      = $cur_aparams;

        logger('TRACE', 'ROUTER RESULT=', $result);
        return $result;
    }

}
