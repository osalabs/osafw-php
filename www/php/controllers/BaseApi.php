<?php
/*
Application-Base Api Controller class for building APIs

Part of PHP osa framework  www.osalabs.com/osafw/php
(c) 2009-2025 Oleg Savchuk www.osalabs.com
*/

/*
API general design:
- headers:
  Authorization: Bearer API_KEY
  Content-Type: application/json (when request body has a json)
- response:
  HTTP code (2xx, 3xx, 4xx, 5xx)
  json object (if needed)
  in case of error - json contains:
    {
      "error": [
        "code" : 400,
        "message" : "detailed error message",
        "category: "optional category",
        "details" : [ optional details ]
      ]
    }

- Generic structure of entity endpoints and actions:
  - /entity
    - GET - list records in entity.
      - request - can have query params for filtering
      - response - HTTP=200, object:
        {
            PLURAL_ENTITY_NAME(i.e. users, orders,...): [{key => value,...},...], // array of objects
            metadata: {
                // any additional info
            }
        }
    - POST - add new record to the entity.
      - request - body is a json object {key => value} to add.
      - response - HTTP=201, object:
        {
            SINGULAR_ENTITY_NAME(i.e. user, orders,...): {id: ID, key => value,...}, // entity object where ID is an id of added record
            metadata: {
                // any additional info
            }
        }

  - /entity/ID
    - GET - get record by ID
      - response - HTTP=200, object:
        {
            SINGULAR_ENTITY_NAME(i.e. user, list,...): {id: ID, key => value,...}, // entity object where ID is an id of added record
            metadata: {
                // any additional info
            }
        }
    - POST/PUT  - modify record by ID
      - request - body is a json object {key => value} with fields to update.
      - response - HTTP=200, object:
        {
            SINGULAR_ENTITY_NAME(i.e. user, list,...): {id: ID, key => value,...}, // entity object where ID is an id of added record
            metadata: {
                // any additional info
            }
        }
    - DELETE (or POST with empty request body) - delete record by ID
      - response - HTTP=204 (No Content)
*/

class BaseApiController extends FwApiController {
    #set in child class:
    #public FwModel|MODELCLASS $model;
    #public string $model_name = 'MODELCLASS';
    #public $base_url = '/v1/SomeController';

    const string route_default_action = fw::ACTION_ERROR; #if no route found - return error

    protected string $api_key = ''; #authorized API key
    protected array $api_key_item = []; #authorized API key item from db

    protected int $users_id = 0; #authorized user id, 0 if not authorized

    #list of routes that do not require authorization
    # format: 'controller.action' in lowercase
    const array NON_AUTH_ROUTES = [
        'v1users.confirm',
        'v1users.remail',
    ];

    public function __construct($is_auth = true) {
        $this->http_origin = $_SERVER['HTTP_ORIGIN'] ?? '*'; #use this if API consumed by external sites

        parent::__construct($is_auth);
    }

    /**
     * authenticate example
     * @return void
     * @throws AuthException|NoModelException
     */
    //    protected function auth(): void {
    //        $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    //        if (!$auth_header) {
    //            $auth_header = $_SERVER['HTTP_BEARER'] ?? '';
    //        }
    //        $api_key_header = str_replace('Bearer ', '', $auth_header);
    //
    //        if (!strlen($api_key_header)) {
    //            $api_key_header = reqs("key"); // support for key in query params for GET requests
    //        }
    //        try {
    //            $dbkey              = AuthKeys::i()->authorizeKey($api_key_header); // throws exception if key is not valid (not found or inactive)
    //            $this->api_key_item = $dbkey;
    //            $this->api_key      = $api_key_header;
    //
    //            $this->users_id = intval($dbkey['users_id'] ?? 0);
    //            if ($this->users_id) {
    //                #got session key
    //                $user = Users::i()->one($this->users_id);
    //                if (!$user) {
    //                    throw new AuthException("User not found", fw::HTTP_FORBIDDEN);
    //                }
    //            }
    //
    //            #set account for the model (if model has setAccount method)
    //            #if (isset($this->model) && method_exists($this->model, 'setAccount')) {
    //            #    $this->model->setAccount($this->accounts_id);
    //            #}
    //        } catch (AuthException $e) {
    //            logger("WARN", "API auth error: {$e->getMessage()}");
    //            throw $e;
    //        }
    //    }

    #override init list filters in API there are no session
    function initFilter(string $session_key = ''): array {
        $this->list_filter = [
            'sortby'  => reqs('sortField'),
            'sortdir' => reqs('sortOrder'),
            'limit'   => max(0, min(reqi('limit', SiteUtils::DEFAULT_PAGE_LIMIT), SiteUtils::MAX_PAGE_LIMIT)),  #0...SiteUtils::MAX_PAGE_LIMIT
            'offset'  => max(0, reqi('offset')), #0...no max

            #standard framework filters
            #'pagenum'  => reqi('pagenum'),
            #'pagesize' => reqi('pagesize', FormUtils::MAX_PAGE_ITEMS),
        ];

        #calculate page num
        $this->list_filter['pagenum'] = $this->list_filter['offset'] / $this->list_filter['limit'] + 1;

        return $this->list_filter;
    }

    // just to disable default IndexAction in FwController
    public function IndexAction(): ?array {
        throw new UserException("Bad request", fw::HTTP_BAD_REQUEST);
    }

    //sample API method /v1/SomeController/(Test)/$form_id
    // public function TestAction($form_id = '') {
    //     $id = intval($form_id);
    //     $ps = [];

    //     try {
    //         //do something
    //     } catch (Exception $ex) {
    //         $ps['error'] = [ 'message' => $ex->getMessage() ];
    //     }

    //     return ['_json' => $ps];
    // }
}
