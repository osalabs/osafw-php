<?php
/*
Base Fw Controller class

Part of PHP osa framework  www.osalabs.com/osafw/php
(c) 2009-2024 Oleg Savchuk www.osalabs.com
 */

abstract class FwController {
    //overridable
    const int    access_level         = Users::ACL_VISITOR; // access level for the controller. $CONFIG['ACCESS_LEVELS'] overrides this. 0 (public access), 1(min logged level), 100(max admin level)
    const string route_default_action = ''; //empty, "index", "show" - default action for controller, dispatcher will use it if no requested action found
    //if no default action set - this is special case action - this mean action should be got form REST's 'id'

    public string $route_onerror = ""; //route redirect action name in case ApplicationException occurs in current route, if empty - 500 error page returned

    public string $base_url;                    //base url for the controller
    public string $base_url_suffix = '';        //additional base url suffix

    public array $form_new_defaults = array();  //defaults for the fields in new form
    public string $required_fields = '';        //optional, default required fields, space-separated
    public string $save_fields = '';            //fields to save from the form to db, space-separated
    public string $save_fields_checkboxes = ''; //checkboxes fields to save from the form to db, qw string: "field|def_value field2|def_value2" or "field field2" (def_value=1 in this case)
    public string $save_fields_nullable = '';   //nullable fields that should be set to null in db if form submit as ''
    public string $model_name = ''; //default model name for the controller

    //not overridable
    protected FW $fw; //current app/framework object
    protected DB $db;
    protected FwModel $model; //default model for the controller
    protected array $config = [];        // controller config, loaded from template dir/config.json
    protected array $access_actions_to_permissions = []; // optional, controller-level custom actions to permissions mapping for role-based access checks, e.g. "UIMain" => Permissions.PERMISSION_VIEW . Can also be used to override default actions to permissions


    protected string $list_view; // table or view name to selecte from for the list screen
    protected string $list_orderby; // orderby for the list screen
    protected array $list_filter; // filter values for the list screen
    protected array $list_filter_search; // filter for the search columns from reqh("search")
    protected array $list_where_params = []; // any sql params for the list_where
    protected string $list_where = ' 1=1 '; // where to use in list sql, default all (see setListSearch() )
    protected int $list_count; // count of list rows returned from db
    protected array $list_rows; // list rows returned from db (array of hashes)
    protected array $list_pager; // pager for the list from FormUtils::getPager
    protected string $list_sortdef = 'id asc'; //default sorting - req param name, asc|desc direction
    protected array $list_sortmap = [ //sorting map: req param name => sql field name(s) asc|desc direction
                                      'id'       => 'id',
                                      'iname'    => 'iname',
                                      'add_time' => 'add_time',
    ];
    protected string $search_fields = 'iname idesc'; //space-separated, fields to search via $list_filter['s'], ! - means exact match, not "like"
    //format: 'field1 field2,!field3 field4' => field1 LIKE '%$s%' or (field2 LIKE '%$s%' and field3='$s') or field4 LIKE '%$s%'

    public string $export_format = '';            // empty or "csv" or "xls" (set from query string "export") - export format for IndexAction
    protected string $export_filename = 'export'; // default filename for export, without extension


    #support of dynamic controller and customizable view list
    protected bool $is_dynamic_index = false;    // true if controller has dynamic IndexAction, then define below:
    protected string $view_list_defaults = '';       // qw list of default columns
    protected array $view_list_map = [];  // list of all available columns fieldname|visiblename
    protected string $view_list_custom = '';       // array or qw list of custom-formatted fields for the list_table

    protected bool $is_dynamic_show = false;    // true if controller has dynamic ShowAction, requires "show_fields" to be defined in config.json
    protected bool $is_dynamic_showform = false;    // true if controller has dynamic ShowFormAction, requires "showform_fields" to be defined in config.json

    protected bool $is_userlists = false;         // true if controller should support UserLists
    protected bool $is_activity_logs = false;     // true if controller should support ActivityLogs
    protected bool $is_readonly = false;          // true if user is readonly, no actions modifying data allowed

    protected string $route_return = '';        // FW.ACTION_SHOW or _INDEX to return (usually after SaveAction, default ACTION_SHOW_FORM)
    protected string $return_url = '';            // url to return after SaveAction successfully completed, passed via request
    protected string $related_id;               // related id, passed via request. Controller should limit view to items related to this id
    protected string $related_field_name;       // if set (in Controller) and $related_id passed - list will be filtered on this field

    public function __construct() {
        $this->fw = fw::i();
        $this->db = $this->fw->db;

        $this->return_url    = reqs('return_url');
        $this->related_id    = reqs('related_id');
        $this->export_format = reqs('export');

        if ($this->model_name) {
            $this->model = fw::model($this->model_name);
        }

        $this->is_readonly = Users::i()->isReadOnly();
    }

    // load controller config from json in template dir (based on base_url)
    public function loadControllerConfig(string $config_filename = 'config.json'): void {
        $conf_file0 = strtolower($this->base_url) . '/' . $config_filename;
        $conf_file  = $this->fw->config->SITE_TEMPLATES . $conf_file0;
        if (!file_exists($conf_file)) {
            throw new ApplicationException("Controller Config file not found in templates: $conf_file");
        }

        $this->config = json_decode(file_get_contents($conf_file), true);
        if (!$this->config) {
            throw new ApplicationException("Controller Config is invalid, check json in templates: $conf_file0");
        }
        #logger("loaded config:", $this->config);

        #fill up controller params
        $model_name = $this->config["model"] ?? '';
        if ($model_name) {
            $this->model_name = $model_name;
            $this->model      = fw::model($model_name);
        }

        $this->required_fields = $this->config["required_fields"] ?? '';
        $this->is_userlists    = $this->config["is_userlists"] ?? false;

        #save_fields could be defined as qw string or array - check and convert
        if (is_array($this->config["save_fields"])) {
            $this->save_fields = Utils::qwRevert($this->config["save_fields"]); #not optimal, but simplest for now
        } else {
            $this->save_fields = $this->config["save_fields"] ?? '';
        }

        $this->form_new_defaults = $this->config["form_new_defaults"] ?? [];

        #save_fields_checkboxes could be defined as qw string - check and convert
        if (is_array($this->config["save_fields_checkboxes"])) {
            $this->save_fields_checkboxes = Utils::qhRevert($this->config["save_fields_checkboxes"]); #not optimal, but simplest for now
        } else {
            $this->save_fields_checkboxes = $this->config["save_fields_checkboxes"] ?? '';
        }

        #save_fields_nullable could be defined as qw string - check and convert
        if (is_array($this->config["save_fields_nullable"])) {
            $this->save_fields_nullable = Utils::qwRevert($this->config["save_fields_nullable"]); #not optimal, but simplest for now
        } else {
            $this->save_fields_nullable = $this->config["save_fields_nullable"] ?? '';
        }

        $this->search_fields = $this->config["search_fields"] ?? '';
        $this->list_sortdef  = $this->config["list_sortdef"] ?? '';

        #list_sortmap could be defined as array or qw string - check and convert
        if (is_array($this->config["list_sortmap"] ?? false)) {
            $this->list_sortmap = $this->config["list_sortmap"]; #not optimal, but simplest for now
        } else {
            $this->list_sortmap = Utils::qh($this->config["list_sortmap"] ?? '');
        }

        $this->related_field_name = $this->config["related_field_name"] ?? '';

        $this->list_view = $this->config["list_view"] ?? '';

        $this->is_dynamic_index = $this->config["is_dynamic_index"] ?? false;
        if ($this->is_dynamic_index) {
            #Whoah! list view is dynamic
            $this->view_list_defaults = $this->config["view_list_defaults"] ?? '';

            #save_fields_nullable could be defined as qw string - check and convert
            if (is_array($this->config["view_list_map"])) {
                $this->view_list_map = $this->config["view_list_map"]; #not optimal, but simplest for now
            } else {
                $this->view_list_map = Utils::qh($this->config["view_list_map"] ?? '');
            }

            $this->view_list_custom = $this->config["view_list_custom"] ?? '';

            if (!$this->list_sortmap) {
                $this->list_sortmap = $this->getViewListSortmap(); #just add all fields from view_list_map
            }
            if (!$this->search_fields) {
                #just search in all visible fields if no specific fields defined
                $this->search_fields = $this->getViewListUserFields();
            }
        }

        $this->is_dynamic_show     = $this->config["is_dynamic_show"] ?? false;
        $this->is_dynamic_showform = $this->config["is_dynamic_showform"] ?? false;

        $this->route_return = $this->config["route_return"] ?? '';
    }

    ############### helpers - shortcuts from fw

    /**
     * return true if current request is GET request
     * @return boolean
     */
    public function isGet(): bool {
        return $this->fw->route->method == 'GET';
    }

    public function checkXSS(bool $is_die = true): bool {
        return $this->fw->checkXSS($is_die);
    }

    public function routeRedirect(string $action, ?string $controller = null, ?array $params = null): void {
        $this->fw->routeRedirect($action, $controller, $params);
    }

    /**
     * initialize filter values from request and session
     *   - automatically set to defaults - pagenum=0 and pagesize=MAX_PAGE_ITEMS
     *   - if request param 'dofilter' passed - session filters cleaned
     * @param string $session_key
     * @return array and also set $this->list_filter
     */
    public function initFilter(string $session_key = ''): array {
        $f = reqh('f');

        if (!$session_key) {
            $session_key = '_filter_' . $this->fw->GLOBAL['controller.action'];
        }

        $sfilter = $_SESSION[$session_key] ?? [];
        if (!is_array($sfilter)) {
            $sfilter = array();
        }

        $is_dofilter = reqs('dofilter');
        if (!$is_dofilter) {
            $f = array_merge($sfilter, $f);
        } else {
            $userfilters_id = reqi('userfilters_id');
            if ($userfilters_id > 0) {
                $uf = UserFilters::i()->one($userfilters_id);
                $f1 = json_decode($uf['idesc'], true);
                if ($f1) {
                    $f = $f1;
                }
                if (intval($uf['is_system']) == 0) {
                    $f['userfilters_id'] = $userfilters_id; // set filter id (for edit/delete) only if not system
                    $f['userfilter']     = $uf;
                }
            } else {
                $userfilters_id = intval($f['userfilters_id']);
                if ($userfilters_id > 0) {
                    $uf              = UserFilters::i()->one($userfilters_id);
                    $f['userfilter'] = $uf;
                }
            }
        }

        // paging
        $f['pagenum']  = intval($f['pagenum'] ?? 0);
        $f['pagesize'] = intval($f['pagesize'] ?? FormUtils::MAX_PAGE_ITEMS);

        $_SESSION[$session_key] = $f;

        $this->list_filter = $f;

        $session_key_search       = '_filtersearch_' . $this->fw->GLOBAL['controller.action'];
        $this->list_filter_search = reqh('search');
        if (empty($this->list_filter_search) && !$is_dofilter) {
            //read from session
            $this->list_filter_search = $_SESSION[$session_key_search] ?? [];
            if (!is_array($this->list_filter_search)) {
                $this->list_filter_search = array();
            }
        } else {
            //remember in session
            $_SESSION[$session_key_search] = $this->list_filter_search;
        }

        return $f;
    }

    /**
     * clears list_filter and related session key
     * @param string $session_key
     * @return void
     */
    public function clearFilter(string $session_key = ''): void {
        $f = array();
        if (!$session_key) {
            $session_key = '_filter_' . $this->fw->GLOBAL['controller.action'];
        }
        $_SESSION[$session_key] = $f;
        $this->list_filter      = $f;
    }

    public function setListSorting(): void {
        if (empty($this->list_sortdef)) {
            throw new Exception('No default sort order defined, define in list_sortdef');
        }
        if (empty($this->list_sortmap)) {
            throw new Exception('No sort order mapping defined, define in list_sortmap');
        }

        list($sortdef_field, $sortdef_dir) = Utils::split2(" ", $this->list_sortdef);

        $sortby  = $this->list_filter['sortby'] ?? '';
        $sortdir = $this->list_filter['sortdir'] ?? '';

        if (empty($sortby)) {
            $sortby = $sortdef_field;
        }
        if ($sortdir != 'desc' && $sortdir != 'asc') {
            $sortdir = $sortdef_dir;
        }

        $orderby = trim($this->list_sortmap[$sortby]);
        if (!$orderby) {
            throw new Exception('No orderby defined for [' . $sortby . ']');
        }

        $this->list_filter['sortby']  = $sortby;
        $this->list_filter['sortdir'] = $sortdir;

        $this->list_orderby = FormUtils::sqlOrderBy($sortby, $sortdir, $this->list_sortmap);
    }

    /**
     * set list_where based on search[] filter based on $this->search_fields
     * Sample: $this->search_fields="field1 field2,!field3 field4" => field1 LIKE '%$s%' or (field2 LIKE '%$s%' and field3='$s') or field4 LIKE '%$s%'
     * @return void
     */
    public function setListSearch(): void {
        $s = trim($this->list_filter['s'] ?? '');
        if (strlen($s) && $this->search_fields) {
            $is_subquery     = false;
            $list_table_name = $this->list_view;
            if (empty($list_table_name)) {
                $list_table_name = $this->model->table_name;
            } else {
                // list_table_name could contain subquery as "(...) t" - detect it (contains whitespace)
                $is_subquery = preg_match("/\s/", $list_table_name);
            }

            $like_s = "%" . $s . "%";

            $afields = Utils::qw($this->search_fields); // OR fields delimited by space
            foreach ($afields as $i => $fieldsand) {
                $afieldsand = explode(',', $fieldsand); // AND fields delimited by comma

                foreach ($afieldsand as $j => $fand) {
                    $param_name = "list_search_" . $i . "_" . $j;
                    if (str_starts_with($fand, "!")) {
                        // exact match
                        $fand = preg_replace("/^!/", "", $fand);
                        if ($is_subquery) {
                            // for subqueries - just use string quoting, but convert to number (so only numeric search supported in this case)
                            $this->list_where_params[$param_name] = intval($s);
                        } else {
                            $ft = $this->db->schemaFieldType($list_table_name, $fand);
                            if ($ft == "int") {
                                $this->list_where_params[$param_name] = intval($s);
                            } elseif ($ft == "float") {
                                $this->list_where_params[$param_name] = floatval($s);
                            } elseif ($ft == "decimal") {
                                $this->list_where_params[$param_name] = floatval($s);
                            } else {
                                $this->list_where_params[$param_name] = $s;
                            }
                        }
                        $afieldsand[$j] = $this->db->qid($fand) . " = :" . $param_name;
                    } else {
                        // like match
                        $afieldsand[$j]                       = $this->db->qid($fand) . " LIKE :" . $param_name;
                        $this->list_where_params[$param_name] = $like_s;
                    }
                }
                $afields[$i] = implode(' and ', $afieldsand);
            }
            $this->list_where .= " and (" . implode(' or ', $afields) . ")";
        }

        $this->setListSearchUserList();

        if ($this->related_id > '' && $this->related_field_name) {
            $this->list_where .= " and " . $this->db->qid($this->related_field_name) . "=:related_field_name";

            $this->list_where_params['related_field_name'] = $this->related_id;
        }

        $this->setListSearchAdvanced();
    }

    public function setListSearchUserList(): void {
        if (isset($this->list_filter["userlist"])) {
            $this->list_where                         .= " and id IN (SELECT ti.item_id FROM " . $this->db->qid(UserLists::i()->table_items) . " ti WHERE ti.user_lists_id=:user_lists_id and ti.add_users_id=:userId) ";
            $this->list_where_params["user_lists_id"] = $this->list_filter["userlist"];
            $this->list_where_params["userId"]        = $this->fw->userId();
        }
    }

    /**
     * set list_where based on search[] filter
     *   - exact: "=term" or just "=" - mean empty
     *   - Not equals "!=term" or just "!=" - means not empty
     *   - Not contains: "!term"
     *   - more/less: <=, <, >=, >"
     *   - and support search by date if search value looks like date in format MM/DD/YYYY
     * @return void
     */
    public function setListSearchAdvanced(): void {
        $hsearch = $this->list_filter_search;
        foreach ($hsearch as $fieldname => $value) {
            if (empty($value) || ($this->is_dynamic_index && !array_key_exists($fieldname, $this->view_list_map))) {
                continue;
            }

            $qfieldname = $this->db->qid($fieldname);
            #for SQL Server:
            #$fieldname_sql      = "COALESCE(CAST($qfieldname as NVARCHAR(255)), '')"; //255 need as SQL Server by default makes only 30
            #$fieldname_sql_num  = "TRY_CONVERT(DECIMAL(18,1),CAST($qfieldname as NVARCHAR))"; // SQL Server 2012+ only
            #$fieldname_sql_date = "TRY_CONVERT(DATE, $qfieldname)"; //for date search

            #for MySQL:
            $fieldname_sql      = "COALESCE(CAST($qfieldname as CHAR), '')";
            $fieldname_sql_num  = "CAST($qfieldname as DECIMAL(18,1))";
            $fieldname_sql_date = "STR_TO_DATE($qfieldname, '%m/%d/%Y')"; //for date search

            $op  = substr($value, 0, 1);
            $op2 = strlen($value) >= 2 ? substr($value, 0, 2) : null;

            $v = substr($value, 1);
            if ($op2 == "!=" || $op2 == "<=" || $op2 == ">=") {
                $v = substr($value, 2);
            }

            $qv = $this->db->q($v); // quoted value
            if (DateUtils::isDateStr($v)) {
                //if input looks like a date - compare as date
                $fieldname_sql = $fieldname_sql_date;
                $qv            = $this->db->q(DateUtils::Str2SQL($v));
            } else {
                if ($op2 == "<=" || $op == "<" || $op2 == ">=" || $op == ">") {
                    //numerical comparison
                    $fieldname_sql = $fieldname_sql_num;
                    $qv            = floatval($v);
                }
            }

            $op_value = match ($op2) {
                "!=" => " <> $qv",
                "<=" => " <= $qv",
                ">=" => " >= $qv",
                default => match ($op) {
                    "=" => " = $qv",
                    "<" => " < $qv",
                    ">" => " > $qv",
                    "!" => " NOT LIKE " . $this->db->q("%$v%"),
                    default => " LIKE " . $this->db->q("%$value%"),
                },
            };

            $this->list_where .= " AND $fieldname_sql $op_value";
        }
    }

    /**
     * set list_where filter based on status filter:
     *  - if status not set - filter our deleted (i.e. show all)
     *  - if status set - filter by status, but if status=127 (deleted) only allow to see deleted by admins
     * @return void
     * @throws NoModelException
     */
    public function setListSearchStatus(): void {
        if (!empty($this->model->field_status)) {
            if (!empty($this->list_filter["status"])) {
                $status = intval($this->list_filter["status"]);
                // if want to see trashed and not admin - just show active
                if ($status == FwModel::STATUS_DELETED && !Users::i()->isAccessLevel(Users::ACL_SITE_ADMIN)) {
                    $status = 0;
                }
                $this->list_where                  .= " and " . $this->db->qid($this->model->field_status) . "=@status";
                $this->list_where_params["status"] = $status;
            } else {
                $this->list_where                  .= " and " . $this->db->qid($this->model->field_status) . "<>@status";
                $this->list_where_params["status"] = FwModel::STATUS_DELETED; // by default - show all non-deleted
            }
        }
    }

    public function getListCount(string $list_view = ''): void {
        $list_view_name   = !empty($list_view) ? $list_view : $this->list_view;
        $this->list_count = intval($this->db->valuep("select count(*) from " . $list_view_name . " where " . $this->list_where, $this->list_where_params));
    }


    /**
     * Perform 2 queries to get list of rows.
     * Set variables:
     * $this->list_count - count of rows obtained from db
     * $this->list_rows list of rows
     * $this->list_pager pager from FormUtils::getPager
     * @return void
     * @throws DBException
     */
    public function getListRows(): void {
        $is_export = false;
        $pagenum   = intval($this->list_filter["pagenum"]);
        $pagesize  = intval($this->list_filter["pagesize"]);
        // if export requested - start with first page and have a high limit (still better to have a limit just for the case)
        if ($this->export_format > '') {
            $is_export = true;
            $pagenum   = 0;
            $pagesize  = 100000;
        }

        if (empty($this->list_view)) {
            $this->list_view = $this->model->table_name;
        }
        $list_view_name = (str_starts_with($this->list_view, "(") ? $this->list_view : $this->db->qid($this->list_view)); // don't quote if list_view is a subquery (starting with parentheses)

        $this->getListCount($list_view_name);
        if ($this->list_count > 0) {
            $offset = $pagenum * $pagesize;
            $limit  = $pagesize;

            $this->list_rows = $this->db->selectRaw("*", $list_view_name, $this->list_where, $this->list_where_params, $this->list_orderby, $offset, $limit);

            if ($this->model->is_normalize_names) {
                foreach ($this->list_rows as &$row) {
                    $this->model->normalizeNames($row);
                }
                unset($row);
            }

            // for 2005<= SQL Server versions <2012
            // offset+1 because _RowNumber starts from 1
            // Dim sql As String = "SELECT * FROM (" &
            // "   SELECT *, ROW_NUMBER() OVER (ORDER BY " & Me.list_orderby & ") AS _RowNumber" &
            // "   FROM " & list_view &
            // "   WHERE " & Me.list_where &
            // ") tmp WHERE _RowNumber BETWEEN " & (offset + 1) & " AND " & (offset + 1 + limit - 1)

            if (!$is_export) {
                $this->list_pager = FormUtils::getPager($this->list_count, $pagenum, $pagesize);
            }
        } else {
            $this->list_rows  = array();
            $this->list_pager = array();
        }

        if ($this->related_id > '') {
            Utils::arrayInject($this->list_rows, ['related_id' => $this->related_id]);
        }
    }

    /**
     * prepare and return itemdb for save to db
     * called from SaveAction()
     * using save_fields and save_fields_checkboxes
     * override in child class if more modifications is necessary
     *
     * @param int $id item id, could be 0 for new item
     * @param array $item fields from the form
     */
    public function getSaveFields(int $id, array $item): array {
        #load old record if necessary
        #$item_old = $this->model->one($id);

        $itemdb = FormUtils::filter($item, $this->save_fields);
        FormUtils::filterCheckboxes($itemdb, $item, $this->save_fields_checkboxes);
        FormUtils::filterNullable($itemdb, $this->save_fields_nullable);

        return $itemdb;
    }

    /**
     * validate required fields are non-empty and set global ERR[field] and ERR[REQ] values in case of errors
     * also set global ERR[REQUIRED]=true in case of validation error
     * @param array $item fields/values
     * @return bool        true if all required field names non-empty
     */
    /**
     * Validate required fields are non-empty and set global fw.ERR[field] values in case of errors
     * @param array $item to validate
     * @param array|string $afields field names required to be non-empty (trim used)
     * @param array|null $form_errors optional - form errors to fill
     * @return bool true if all required field names non-empty
     *              also set global fw.FormErrors[REQUIRED]=true in case of validation error if no form_errors defined
     */
    public function validateRequired(array $item, array|string $afields, array &$form_errors = null): bool {
        $result = true;

        if (!is_array($afields)) {
            $afields = Utils::qw($afields);
        }

        $is_global_errors = false;
        if (is_null($form_errors)) {
            $form_errors      = &$this->fw->FormErrors;
            $is_global_errors = true;
        }

        if ($item && $afields) {
            foreach ($afields as $fld) {
                if ($fld > '' && (!array_key_exists($fld, $item) || !strlen(trim($item[$fld])))) {
                    $result            = false;
                    $form_errors[$fld] = true;
                }
            }
        }

        if (!$result && $is_global_errors) {
            $form_errors["REQUIRED"] = true; // set global error
        }

        return $result;
    }

    /**
     * Check validation result of validateRequired
     * @param bool $result - to use from external validation check
     * @return void
     * @throws ValidationException if global ERR non-empty, Also set global ERR[INVALID] if ERR non-empty, but ERR[REQUIRED] not true
     */
    public function validateCheckResult(bool $result = true): void {
        if (isset($this->fw->FormErrors['REQUIRED']) && $this->fw->FormErrors['REQUIRED']) {
            $result = false;
        }

        if (is_array($this->fw->FormErrors) && !empty($this->fw->FormErrors) && (!isset($this->fw->FormErrors['REQUIRED']) || !$this->fw->FormErrors['REQUIRED'])) {
            $this->fw->FormErrors['INVALID'] = true;
            $result                          = false;
        }

        if (!$result) {
            throw new ValidationException('');
        }
    }

    public function setFormError(Exception $ex): void {
        //if Validation exception - don't set general error message - specific validation message set in templates
        if (!($ex instanceof ValidationException)) {
            $this->fw->GLOBAL['err_msg'] = $ex->getMessage();
        }
    }

    //add fields name to form error hash
    public function setError(string $field_name, mixed $error_type = true): void {
        $this->fw->FormErrors[$field_name] = $error_type;
    }

    /**
     * called when unhandled error happens in controller's Action
     * @param Exception $ex
     * @param array $args
     * @return array|null
     * @throws Exception
     */
    public function actionError(Exception $ex, array $args): ?array {
        if ($this->fw->isJsonExpected()) {
            throw $ex; //exception will be handled in fw.dispatch() and fw.errMsg() called
        } else {
            //if not json - redirect to route route_onerror if it's defined
            $this->setFormError($ex);

            if (empty($this->route_onerror)) {
                throw $ex; //re-throw exception
            } else {
                $this->fw->routeRedirect($this->route_onerror, null, $args);
            }
        }
        return null;
    }

    /**
     * add or update records in db ($this->model)
     *   Also set fw.flash
     * @param int $id id of the record
     * @param array $fields hash of field/values
     * @return int              new autoincrement id (if added) or old id (if update). Also set fw->flash
     * @throws DBException
     */
    public function modelAddOrUpdate(int $id, array $fields): int {
        if ($id > 0) {
            $this->model->update($id, $fields);
            $this->fw->flash("record_updated", 1);
        } else {
            $id = $this->model->add($fields);
            $this->fw->flash("record_added", 1);
        }
        return $id;
    }


    /**
     * return URL for location after successful Save action
     * if return_url set (and no add new form requested) - go to return_url
     * id:
     *  - if empty - base_url
     *  - if >0 - base_url + index/view/new/edit depending on return_to var/param
     * also appends:
     *  - base_url_suffix
     *  - related_id
     *  - copy_id
     * @param string $id
     * @return string
     */
    public function afterSaveLocation(string $id = ''): string {
        $url        = '';
        $url_q      = ($this->related_id > '' ? '&related_id=' . $this->related_id : '');
        $is_add_new = false;

        if ($id > '') {
            $request_route_return = reqh('route_return');
            $to                   = ($request_route_return > '' ? $request_route_return : $this->route_return);
            if ($to == 'show') {
                // or return to view screen
                $url = $this->base_url . '/' . $id;
            } elseif ($to == 'index') {
                // or return to list screen
                $url = $this->base_url;
            } elseif ($to == 'show_form_new') {
                // or return to add new form
                $url        = $this->base_url . '/new';
                $url_q      .= '&copy_id=' . $id;
                $is_add_new = true;
            } else {
                // default is return to edit screen
                $url = $this->base_url . '/' . $id . '/edit';
            }
        } else {
            $url = $this->base_url;
        }

        //preserve return url if present
        if ($this->return_url > '') {
            $url_q .= '&return_url=' . Utils::urlescape($this->return_url);
        }

        //add base_url_suffix if any
        if ($this->base_url_suffix > '') {
            $url_q .= '&' . $this->base_url_suffix;
        }

        //add query
        $is_url_q = false;
        if ($url_q > '') {
            $is_url_q = true;
            $url_q    = preg_replace('/^&/', '', $url_q); // make url clean
            $url_q    = '?' . $url_q;
        }

        if ($is_add_new || !$this->return_url) {
            //if has add new or no specific return_url - just
            $result = $url . $url_q;
        } else {
            //if has return url - go to it
            if ($this->fw->isJsonExpected()) {
                // if json - it's usually autosave - don't redirect back to return url yet
                $result = $url . $url_q . ($is_url_q ? '&' : '?') . 'return_url=' . Utils::urlescape($this->return_url);
            } else {
                $result = $this->return_url;
            }
        }

        return $result;
    }

    /**
     * Called from SaveAction/DeleteAction/DeleteMulti or similar.
     * Return json or route redirect back to ShowForm
     * or redirect to proper location
     * @param bool $success operation successful or not
     * @param string $id item id
     * @param bool $is_new true if it's newly added item
     * @param string $action route redirect to this method if error
     * @param string $location redirect to this location if success
     * @param array|null $more_json added to json response
     * @return array|null ps array of json response or null (will be redirected to new location or ShowForm)
     */
    public function afterSave(bool $success, string $id = '', bool $is_new = false, string $action = 'ShowForm', string $location = '', array $more_json = null): ?array {
        if (!$location) {
            $location = $this->afterSaveLocation($id);
        }

        if ($this->fw->isJsonExpected()) {
            $ps   = array();
            $json = array(
                'success'  => $success,
                'id'       => $id,
                'is_new'   => $is_new,
                'location' => $location,
                'err_msg'  => $this->fw->GLOBAL['err_msg'],
            );
            // add ERR field errors to response if any
            if (!empty($this->fw->FormErrors)) {
                $json['ERR'] = $this->fw->FormErrors;
            }

            if ($more_json) {
                $json = array_merge($json, $more_json);
            }

            $ps['_json'] = $json;
            return $ps;
        } else {
            // If save Then success - Return redirect
            // If save Then failed - Return back To add/edit form
            if ($success) {
                fw::redirect($location);
            } else {
                $this->routeRedirect($action, null, [$id]);
            }
        }

        return null;
    }

    public function afterSaveJson(bool $success, array $more_json = null): array {
        return $this->afterSave($success, "", false, "no_action", "", $more_json);
    }

    /**
     * called before each controller action (init() already called), check access to current fw.route
     * @return void
     * @throws AuthException if no access
     * @throws ApplicationException|DBException|NoModelException
     */
    public function checkAccess(): void {
        // if user is logged and not SiteAdmin(can access everything)
        // and user's access level is enough for the controller - check access by roles (if enabled)
        $current_user_level = $this->fw->userAccessLevel();
        if ($current_user_level > Users::ACL_VISITOR && $current_user_level < Users::ACL_SITE_ADMIN) {
            if (!Users::i()->isAccessByRolesResourceAction($this->fw->userId(), $this->fw->route->controller, $this->fw->route->action, $this->fw->route->action_more, $this->access_actions_to_permissions)) {
                throw new AuthException("Bad access - Not authorized (3)");
            }
        }
    }

    public function setPS(array &$ps): array {
        if (empty($ps)) {
            $ps = array();
        }

        $ps["list_rows"]    = $this->list_rows;
        $ps["count"]        = $this->list_count;
        $ps["pager"]        = $this->list_pager;
        $ps["f"]            = $this->list_filter;
        $ps["related_id"]   = $this->related_id;
        $ps["base_url"]     = $this->base_url;
        $ps["is_userlists"] = $this->is_userlists;
        $ps["is_readonly"]  = $this->is_readonly;

        //implement "Showing FROM to TO of TOTAL records"
        if (count($this->list_rows) > 0) {
            $pagenum          = intval($this->list_filter["pagenum"]);
            $pagesize         = intval($this->list_filter["pagesize"]);
            $ps["count_from"] = $pagenum * $pagesize + 1;
            $ps["count_to"]   = $pagenum * $pagesize + count($this->list_rows);
        }

        if ($this->return_url > '') {
            $ps["return_url"] = $this->return_url; // if not passed - don't override return_url.html
        }

        return $ps;
    }

    public function setUserLists(array &$ps, int $id = 0): bool {
        // userlists support
        if ($id == 0) {
            // select only for list screens
            $ps["select_userlists"] = UserLists::i()->listSelectByEntity($this->base_url);
        }
        $ps["my_userlists"] = UserLists::i()->listForItem($this->base_url, $id);
        return true;
    }

    public function exportList(): void {
        if (empty($this->list_rows)) {
            $this->list_rows = array();
        }

        $fields = $this->getViewListUserFields();
        // header names
        $headers = array();
        foreach (Utils::qw($fields) as $fld) {
            $headers[$fld] = $this->view_list_map[$fld];
        }

        if ($this->export_format == "xls") {
            Utils::responseXLS($this->fw, $this->list_rows, $headers, $this->export_filename . ".xls");
        } else {
            Utils::responseCSV($this->list_rows, $headers, $this->export_filename . ".csv");
        }
    }

    public function setAddUpdUser(array &$ps, array $item): void {
        if ($this->model->field_add_users_id > '') {
            $ps["add_users_id_name"] = Users::i()->iname($item[$this->model->field_add_users_id]);
        }
        if ($this->model->field_upd_users_id > '') {
            $ps["upd_users_id_name"] = Users::i()->iname($item[$this->model->field_upd_users_id]);
        }
    }

    //<editor-fold desc="Dynamic Controller Support">

    /**
     * as arraylist of hashtables {field_name=>, field_name_visible=> [, is_checked=>true]} in right order
     * @param array|string $fields qw-string, if fields defined - show fields only
     * @param bool $is_all if is_all true - then show all fields (not only from fields param)
     * @return array           array of hashtables
     */
    public function getViewListArr(array|string $fields = '', bool $is_all = false): array {
        $result = array();

        if (!is_array($fields)) {
            $fields = Utils::qw($fields);
        }

        #if fields defined - first show these fields, then the rest
        $fields_added = array();
        if ($fields) {
            foreach ($fields as $fieldname) {
                $result[]                 = array(
                    'field_name'         => $fieldname,
                    'field_name_visible' => $this->view_list_map[$fieldname],
                    'is_checked'         => true,
                    'is_sortable'        => !empty($this->list_sortmap[$fieldname]),
                );
                $fields_added[$fieldname] = true;
            }
        }

        if ($is_all) {
            #rest/all fields
            #sorted by values (visible field name)
            $arr = $this->view_list_map;
            asort($arr);
            foreach ($arr as $key => $value) {
                if (array_key_exists($key, $fields_added)) {
                    continue;
                }
                $result[] = array(
                    'field_name'         => $key,
                    'field_name_visible' => $value,
                    'is_sortable'        => !empty($this->list_sortmap[$key]),
                );
            }
        }

        return $result;
    }

    public function getViewListSortmap(): array {
        $result = array();
        foreach ($this->view_list_map as $fieldname => $value) {
            $result[$fieldname] = $fieldname;
        }
        return $result;
    }

    public function getViewListUserFields() {
        $item = UserViews::i()->oneByIcode($this->base_url); #base_url is screen identifier
        return empty($item['fields']) ? $this->view_list_defaults : $item['fields'];
    }

    /**
     * Called from setViewList to get conversions for fields.
     * Currently, supports only "date" conversion - i.e. date only fields will be formatted as date only (without time)
     * Override to add more custom conversions
     * @param array $afields
     * @return array
     */
    public function getViewListConversions(array $afields): array {
        $result = array();
        //use table_name or list_view if it's not subquery
        $list_view_name = $this->model->table_name;
        if ($this->list_view > '' && $this->list_view[0] != "(") {
            $list_view_name = $this->list_view;
        }

        $table_schema = $this->db->loadTableSchema($list_view_name);
        foreach ($afields as $fieldname) {
            $fieldname_lc = strtolower($fieldname);
            if (!array_key_exists($fieldname_lc, $table_schema)) {
                continue;
            }
            $field_schema = $table_schema[$fieldname_lc];

            //if field is exactly DATE - show only date part without time
            if ($field_schema["fw_subtype"] == "date") {
                $result[$fieldname] = "date";
            }
            // ADD OTHER CONVERSIONS HERE if necessary
        }

        return $result;
    }

    /**
     * Apply conversions to data for a single view list field
     * Override to add custom conversions.
     * @param string $fieldname field name to apply conversion to
     * @param array $row data row from db
     * @param array $hconversions standard conversion rules from getViewListConversions
     * @return string
     */
    public function applyViewListConversions(string $fieldname, array $row, array $hconversions): string {
        $data = $row[$fieldname] ?? '';
        if (array_key_exists($fieldname, $hconversions)) {
            $data = DateUtils::Str2DateOnly($data);
        }
        return $data;
    }

    /**
     * Add to $ps:
     * headers
     * headers_search
     * depends on $ps["list_rows"]
     * use is_cols=false when return $ps as json
     * usage:
     * $this->setViewList($ps, $this->list_filter_search)
     * @param array $ps
     * @param array $hsearch
     * @param bool $is_cols
     * @return void
     * @throws NoModelException
     */
    public function setViewList(array &$ps, array $hsearch, bool $is_cols = true): void {
        $user_view       = UserViews::i()->oneByIcode($this->base_url);
        $ps["user_view"] = $user_view;

        $fields = $this->getViewListUserFields();

        $headers = $this->getViewListArr($fields);
        // add search from user's submit
        foreach ($headers as $header) {
            $header["search_value"] = $hsearch[$header["field_name"]] ?? '';
        }

        $ps["headers"]        = $headers;
        $ps["headers_search"] = $headers;

        $hcustom = Utils::qh($this->view_list_custom);

        if ($is_cols) {
            // dynamic cols
            $afields = Utils::qw($fields);

            $hconversions = $this->getViewListConversions($afields);

            foreach ($ps["list_rows"] as &$row) {
                $cols = array();
                foreach ($afields as $fieldname) {
                    $data = $this->applyViewListConversions($fieldname, $row, $hconversions);

                    $cols[] = array(
                        'row'        => $row,
                        'field_name' => $fieldname,
                        'data'       => $data,
                        'is_custom'  => array_key_exists($fieldname, $hcustom),
                    );
                }
                $row['cols'] = $cols;
            }
            unset($row);
        }
    }

    //</editor-fold>

    ######################### Default Actions

    public function IndexAction(): ?array {
        rw("in Base Fw Controller IndexAction");
        #fw->parser();
        return null;
    }
} //end of class
