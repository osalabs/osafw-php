<?php
/*
 Base Fw Controller class for standard module with list/form screens

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2024 Oleg Savchuk www.osalabs.com
*/

class FwVueController extends FwDynamicController {
    const int access_level = Users::ACL_SITE_ADMIN;

    // list of keys from fw.G to pass to Vue
    protected string $global_keys = "ROOT_URL is_list_btn_left";

    public function __construct() {
        parent::__construct();
        $this->fw->page_layout = $this->fw->config->PAGE_LAYOUT_VUE; // layout for Vue pages
    }

    /**
     * set list fields for db select, based on user-selected headers in list_headers
     * so we fetch from db only fields that are visible in the list + id field
     * @return void
     */
    protected function setListFields(): void {
        $quoted_fields   = [];
        $is_id_in_fields = false;
        foreach ($this->list_headers as $header) {
            $field_name      = $header["field_name"];
            $quoted_fields[] = $this->db->qid($field_name);
            if ($field_name == $this->model->field_id) {
                $is_id_in_fields = true;
            }
        }
        //always include id field
        if (!$is_id_in_fields && !empty($this->model->field_id)) {
            $quoted_fields[] = $this->db->qid($this->model->field_id);
        }
        //join quoted_fields array into comma-separated string
        $this->list_fields = implode(",", $quoted_fields);
    }

    /**
     * filter list rows for json output using model's filterForJson
     */
    protected function filterListForJson(): void {
        //extract autocomplete fields
        $ac_fields = [];
        $fields    = $this->config["showform_fields"];
        foreach ($fields as $def) {
            //$field_name = strval($def["field"] ?? "");
            //$model_name = strval($def["lookup_model"] ?? "");
            $dtype = strval($def["type"] ?? '');
            if ($dtype == "autocomplete" || $dtype == "plaintext_autocomplete") {
                $ac_fields[] = $def;
            }
        }

        foreach ($this->list_rows as &$row) {
            $row = $this->model->filterForJson($row);

            //added/updated username - it's readonly so we can replace _id fields with names
            if (!empty($this->model->field_add_users_id) && isset($row[$this->model->field_add_users_id])) {
                $row["add_users_id"] = Users::i()->iname($row[$this->model->field_add_users_id]);
            }
            if (!empty($this->model->field_upd_users_id) && isset($row[$this->model->field_upd_users_id])) {
                $row["upd_users_id"] = Users::i()->iname($row[$this->model->field_upd_users_id]);
            }

            //autocomplete fields - add _iname fields
            foreach ($ac_fields as $def) {
                $field_name = strval($def["field"] ?? "");
                if (!isset($row[$field_name])) {
                    continue;
                }

                $model_name = strval($def["lookup_model"] ?? "");
                $dtype      = strval($def["type"]);
                if ($dtype == "autocomplete" || $dtype == "plaintext_autocomplete") {
                    $ac_model                    = $this->fw->model($model_name);
                    $ac_item                     = $ac_model->one($row[$field_name]);
                    $row[$field_name . "_iname"] = $ac_item["iname"];
                }
            }
        }
        unset($row);
    }

    /**
     * set data for initial scope for Vue controller
     * @param array $ps
     * @return void
     * @throws DBException
     * @throws NoModelException
     */
    protected function setScopeInitial(array &$ps): void {
        $ps["XSS"]          = $_SESSION["XSS"];
        $ps["access_level"] = $this->fw->userAccessLevel();
        $ps["me_id"]        = $this->fw->userId();
        //some specific from global fw.GLOBAL;
        $global = [];
        foreach (Utils::qw($this->global_keys) as $key) {
            $global[$key] = $this->fw->GLOBAL[$key];
        }
        $ps["global"] = $global;

        $this->setViewList(false); // initialize list_headers and related

        // userviews customization support
        $ps["all_list_columns"] = $this->getViewListArr($this->getViewListUserFields(), true); // list all fields
        $ps["select_userviews"] = UserViews::i()->listSelectByIcode(UserViews::icodeByUrl($this->base_url, $this->is_list_edit));

        $ps["field_id"] = $this->model->field_id;

        //return view form definitions
        $ps["show_fields"] = $this->config["show_fields"];
        //return editable fields definitions
        $ps["showform_fields"] = $this->config["showform_fields"];

        $ps["list_user_view"] = $this->list_user_view;
        $ps["list_headers"]   = $this->list_headers;

        // other static params
        $ps["related_id"]   = $this->related_id;
        $ps["base_url"]     = $this->base_url;
        $ps["is_userlists"] = $this->is_userlists;
        $ps["is_readonly"]  = $this->is_readonly;
        $ps["is_list_edit"] = $this->is_list_edit;
    }

    /**
     * set data for list_rows scope for Vue controller
     * @param array $ps
     * @return void
     * @throws DBException
     * @throws NoModelException
     */
    protected function setScopeListRows(array &$ps): void {
        $this->setListSorting();
        $this->setListSearch();
        $this->setListSearchStatus();

        if (empty($this->list_headers)) {
            $this->setViewList(false); // initialize list_headers and related (can already be initialized in setScopeInitial)
        }

        //only select from db visible fields + id, save as comma-separated string into list_fields
        $this->setListFields();

        $this->getListRows();
        $this->filterListForJson();

        // if export - no need further processing - just return asap
        if (!empty($this->export_format)) {
            return;
        }

        $ps["list_rows"] = $this->list_rows;
        $ps["count"]     = $this->list_count;
        $ps["pager"]     = $this->list_pager;
    }

    protected function setScopeLookups(array &$ps): void {
        // userlists support if necessary
        if ($this->is_userlists) {
            $this->setUserLists($ps);
        }

        if (empty($this->list_headers)) {
            $this->setViewList(false); // initialize list_headers and related (can already be initialized in setScopeInitial)
        }

        $showform_fields = $this->config["showform_fields"];
        //$hfields         = $this->_fieldsToHash($showform_fields);

        // extract lookups from config and add to ps
        $lookups = [];
        foreach ($showform_fields as $def) {
            if (is_null($def)) {
                continue;
            }

            $dtype        = strval($def["type"] ?? '');
            $lookup_model = strval($def["lookup_model"] ?? '');
            if (!empty($lookup_model) && $dtype != "autocomplete") {
                //all lookup_models, except autocomplete (for those it could be too large)
                $lookups[$lookup_model] = $this->fw->model($lookup_model)->listSelectOptions($def);
            }

            $lookup_tpl = strval($def["lookup_tpl"] ?? '');
            if (!empty($lookup_tpl)) {
                $lookups[$lookup_tpl] = FormUtils::selectTplOptions($lookup_tpl);
            }
        }

        $ps["lookups"] = $lookups;
    }

    /**
     * basically return layout/js to the browser, then Vue will load data via API
     * @return array related template will be parsed, null - no templates parsed (if action did all the output)
     * @throws DBException
     * @throws NoModelException
     */
    public function IndexAction(): array {
        $scope  = reqs("scope");
        $scopes = !empty($scope) ? Utils::commastr2hash($scope, "1") : [];
        if (!empty($this->export_format)) {
            $scopes["list_rows"] = "1";
        }

        // get filters from the search form
        $this->initFilter();

        // set standard output - load html with Vue app
        $ps = [];

        if ($this->fw->isJsonExpected()) {
            // if json expected - return data only as json
            $ps["_json"] = true;

            if (empty($scopes) || isset($scopes["init"])) {
                $this->setScopeInitial($ps);
            }

            // prepare data for list_rows scope
            if (empty($scopes) || isset($scopes["list_rows"])) {
                $this->setScopeListRows($ps);

                if (!empty($this->export_format)) {
                    return []; // return empty hashtable just in case action override to avoid check for null
                }
            }

            // prepare data for lookups scope
            if (empty($scopes) || isset($scopes["lookups"])) {
                $this->setScopeLookups($ps);
            }
        } else {
            // else - this is initial non-json page load - return layout/js to the browser, then Vue will load data via API
            // if url is /ID or /ID/edit or /new - add screen, id to ps so Vue app will switch to related screen
            $route = $this->fw->dispatcher->getRoute($_SERVER['REQUEST_URI']);
            if ($route->action == FW::ACTION_SHOW_FORM) {
                $ps["screen"] = "edit";
                $ps["id"]     = $route->id;
            } elseif ($route->action == FW::ACTION_SHOW_FORM_NEW) {
                $ps["screen"] = "edit";
            } elseif ($route->action == FW::ACTION_SHOW) {
                $ps["screen"] = "view";
                $ps["id"]     = $route->id;
            }

            $ps = $this->setPS($ps);
        }

        $ps["f"] = $this->list_filter;

        return $ps;
    }

    public function ShowAction($form_id): ?array {
        $id = $form_id; //might be int or string
        if (!$this->fw->isJsonExpected()) {
            //direct access to show page - redirect to index
            $this->fw->routeRedirect(FW::ACTION_INDEX);
            return null;
        }

        $mode = reqs("mode"); // view or edit

        $ps   = [];
        $item = $this->model->one($id);
        if (empty($item)) {
            throw new NotFoundException();
        }

        // additionally, if we have autocomplete fields - preload their values
        $multi_rows  = [];
        $subtables   = [];
        $attachments = []; //att_id => att item
        $att_links   = []; //linked att ids

        $fields = $mode == "edit" ? $this->config["showform_fields"] : $this->config["show_fields"];
        foreach ($fields as $def) {
            $field_name = strval($def["field"] ?? "");
            $model_name = strval($def["lookup_model"] ?? "");
            $dtype      = strval($def["type"] ?? '');
            if ($dtype == "autocomplete" || $dtype == "plaintext_autocomplete") {
                $ac_model = $this->fw->model($model_name);
                if ($ac_model != null) {
                    $ac_item                      = $ac_model->one($item[$field_name]);
                    $item[$field_name . "_iname"] = $ac_item["iname"] ?? '';
                }
            } elseif ($dtype == "multi" || $dtype == "multicb" || $dtype == "multicb_prio") {
                //multiple values either from lookup model or junction model
                $multi_model = null;
                $rows        = [];
                if (!empty($def["lookup_model"])) {
                    //use comma-separated values in field from lookup_model
                    $multi_model = $this->fw->model($model_name);
                    $rows        = $multi_model->listWithChecked(strval($item[$field_name]), $def);
                } else {
                    //use junction model
                    $multi_model = $this->fw->model(strval($def["model"]));
                    if ($def["is_by_linked"] ?? false) {
                        // list main items by linked id from junction model (i.e. list of Users(with checked) for Company from UsersCompanies model)
                        $rows = $multi_model->listMainByLinkedId($id, $def); //junction model
                    } else {
                        // list linked items by main id from junction model (i.e. list of Companies(with checked) for User from UsersCompanies model)
                        $rows = $multi_model->listLinkedByMainId($id, $def); //junction model
                    }
                }
                $multi_rows[$field_name] = $multi_model->filterListOptionsForJson($rows);
            } elseif ($dtype == "subtable" || $dtype == "subtable_edit") {
                $sub_model = $this->fw->model(strval($def["model"]));
                $list_rows = $sub_model->listByMainId($id, $def); //list related rows from db
                $sub_model->prepareSubtable($list_rows, $id, $def);

                $subtables[$field_name] = $list_rows;
            } elseif ($dtype == "att" || $dtype == "att_edit") {
                $att_id = intval($item[$field_name]);
                if ($att_id > 0) {
                    $att_item = Att::i()->one($att_id);
                    if (!empty($att_item)) {
                        $attachments[$att_id] = Att::i()->filterForJson($att_item);
                    }
                }
            } elseif ($dtype == "att_links" || $dtype == "att_links_edit") {
                $att_items = Att::i()->listLinked($this->model->table_name, $id);
                foreach ($att_items as $att_item) {
                    $att_item                     = Att::i()->filterForJson($att_item);
                    $attachments[$att_item["id"]] = $att_item;
                    $att_links[]                  = $att_item["id"];
                }
            }
        }

        if (!empty($multi_rows)) {
            $ps["multi_rows"] = $multi_rows;
        }
        if (!empty($subtables)) {
            $ps["subtables"] = $subtables;
        }
        if (!empty($attachments)) {
            $ps["attachments"] = $attachments;
        }
        if (!empty($att_links)) {
            $ps["att_links"] = $att_links;
        }

        // fill added/updated too
        $this->setAddUpdUser($ps, $item);

        $item = $this->model->filterForJson($item);

        $ps["id"]    = $id;
        $ps["i"]     = $item;
        $ps["_json"] = true;
        return $ps;
    }

    public function SaveAction($form_id): array {
        $id = intval($form_id);
        if ($this->save_fields == null) {
            throw new Exception("No fields to save defined, define in Controller.save_fields");
        }
        if (reqb("refresh")) {
            throw new Exception("Wrong use refresh=1 on Vue Controller");
        }

        Users::i()->checkReadOnly();

        $item    = reqh("item");
        $success = true;
        $is_new  = ($id == 0);

        $this->Validate($id, $item);
        // load old record if necessary
        // $item_old = $this->model->one($id);

        $itemdb = FormUtils::filter($item, $this->save_fields);
        FormUtils::filterCheckboxes($itemdb, $item, $this->save_fields_checkboxes, $this->isPatch());
        FormUtils::filterNullable($itemdb, $this->save_fields_nullable);

        $id = $this->modelAddOrUpdate($id, $itemdb);

        return $this->afterSave($success, $id, $is_new);
    }

    public function NextAction($form_id): array {
        $ps          = parent::NextAction($form_id);
        $ps["_json"] = true;
        return $ps;
    }

    public function ShowFormAction($form_id): ?array {
        $id = intval($form_id);
        if (!$this->fw->isJsonExpected()) {
            //direct access to show page - redirect to index
            $this->fw->routeRedirect(FW::ACTION_INDEX);
            return null;
        }

        throw new ApplicationException("Not Implemented"); // N/A for Vue controllers
    }

    public function ShowDeleteAction($form_id): ?array {
        throw new ApplicationException("Not Implemented"); // N/A for Vue controllers
    }

}
