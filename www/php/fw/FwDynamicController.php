<?php
/*
 Base Fw Controller class for standard module with list/form screens

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2024 Oleg Savchuk www.osalabs.com
*/

class FwDynamicController extends FwController {
    const int access_level = 100; #by default Admin Controllers allowed only for Admins
    protected $model_related;

    public function __construct() {
        parent::__construct();

        //uncomment in the interited controller
        //$this->base_url='/Admin/DemosDynamic'; #base url must be defined for loadControllerConfig
        //$this->loadControllerConfig();
        //$this->model_related = DemoDicts::i();
    }

    public function IndexAction(): ?array {
        #get filters from the search form
        $f = $this->initFilter();

        $this->setListSorting();
        $this->setListSearch();
        $this->setListSearchStatus();
        // set here non-standard search
        #if (isset($f["field"])) {
        #    $this->list_where .= " and field=" . dbq($f["field"]);
        #}

        $this->getListRows();
        //add/modify rows from db if necessary
        /*
        foreach ($this->list_rows as $k => $row) {
            $this->list_rows[$k]['field'] = 'value';
        }
        */
        $ps = array(
            'list_rows'  => $this->list_rows,
            'count'      => $this->list_count,
            'pager'      => $this->list_pager,
            'f'          => $this->list_filter,
            'related_id' => $this->related_id,
            'return_url' => $this->return_url,
        );

        #optional userlists support
        $ps["select_userlists"] = UserLists::i()->listSelectByEntity($this->list_view);
        $ps["mylists"]          = UserLists::i()->listForItem($this->list_view, 0);
        $ps["list_view"]        = $this->list_view;

        if ($this->is_dynamic_index) {
            #customizable headers
            $this->setViewList($ps, reqh("search"));
        }

        return $ps;
    }

    public function ShowAction($form_id): ?array {
        $id   = intval($form_id);
        $item = $this->model->one($id);
        if (!$item) {
            throw new ApplicationException("Not Found", 404);
        }

        $ps = array(
            'id'                => $id,
            'i'                 => $item,
            #added/updated should be filled before dynamic fields
            'add_users_id_name' => Users::i()->iname($item['add_users_id']),
            'upd_users_id_name' => Users::i()->iname($item['upd_users_id']),
            'return_url'        => $this->return_url,
            'related_id'        => $this->related_id,
        );

        #dynamic fields
        if ($this->is_dynamic_show) {
            $ps["fields"] = $this->prepareShowFields($item, $ps);
        }

        #optional userlists support
        $ps["list_view"] = $this->list_view ? $this->model->table_name : $this->list_view;
        $ps["mylists"]   = UserLists::i()->listForItem($ps["list_view"], $id);

        return $ps;
    }

    public function ShowFormAction($form_id): ?array {
        $id = intval($form_id);

        if ($this->isGet()) {
            if ($id > 0) {
                $item = $this->model->one($id);
            } else {
                #defaults
                $item = $this->form_new_defaults;
            }
        } else {
            $itemdb = $id ? $this->model->one($id) : array();
            $item   = array_merge($itemdb, reqh('item'));
        }

        $ps = array(
            'id'         => $id,
            'i'          => $item,
            'return_url' => $this->return_url,
            'related_id' => $this->related_id,
        );
        if ($this->model->field_add_users_id) {
            $ps['add_users_id_name'] = Users::i()->iname($item['add_users_id'] ?? 0);
        }
        if ($this->model->field_upd_users_id) {
            $ps['upd_users_id_name'] = Users::i()->iname($item['upd_users_id'] ?? 0);
        }

        if ($this->is_dynamic_showform) {
            $ps["fields"] = $this->prepareShowFormFields($item, $ps);
        }

        if ($this->fw->GLOBAL['ERR']) {
            logger($this->fw->GLOBAL['ERR']);
        }

        return $ps;
    }

    public function modelAddOrUpdate(int $id, array $fields): int {
        if ($this->is_dynamic_showform) {
            $this->processSaveShowFormFields($id, $fields);
        }

        $id = parent::modelAddOrUpdate($id, $fields);

        if ($this->is_dynamic_showform) {
            $this->processSaveShowFormFieldsAfter($id, $fields);
        }

        return $id;
    }

    public function SaveAction($form_id): ?array {
        $id   = intval($form_id);
        $item = reqh('item');

        $success  = true;
        $is_new   = ($id == 0);
        $location = '';

        try {
            $this->Validate($id, $item);

            $itemdb = $this->getSaveFields($id, $item);

            $id = $this->modelAddOrUpdate($id, $itemdb);

        } catch (ApplicationException $ex) {
            $success = false;
            $this->setFormError($ex);
        }

        return $this->afterSave($success, $id, $is_new);
    }

    public function Validate($id, $item): void {
        $result = $this->validateRequiredDynamic($item);

        if ($result && $this->is_dynamic_showform) {
            $this->validateSimpleDynamic($id, $item);
        }

        /*
                if ($result){
                    if ($this->model->isExists( $item['iname'], $id ) ){
                        $this->setError('iname', 'EXISTS');
                    }
                }
        */
        $this->validateCheckResult();
    }

    protected function validateRequiredDynamic($item): bool {
        $result = true;

        if (!$this->required_fields && $this->is_dynamic_showform) {
            #if required_fields not defined - fill from showform_fields
            $fields = $this->config["showform_fields"];
            $req    = array();
            foreach ($fields as $def) {
                if ($def['required']) {
                    $req[] = $def['field'];
                }
            }
            $result = $this->validateRequired($item, $req);

        } else {
            $result = $this->validateRequired($item, $this->required_fields);
        }

        return $result;
    }

    #simple validation via showform_fields
    public function validateSimpleDynamic($id, $item): bool {
        $result = true;

        $fields = $this->config["showform_fields"];
        foreach ($fields as $def) {
            $field = $def['field'];
            if (!$field) {
                continue;
            }

            $val = Utils::qh($def["validate"]);
            if (array_key_exists('exists', $val) && $this->model->isExists($item[$field], $id)) {
                $this->setError($field, 'EXISTS');
                $result = false;
            }
            if (array_key_exists('isemail', $val) && !FormUtils::isEmail($item[$field])) {
                $this->setError($field, 'WRONG');
                $result = false;
            }
            if (array_key_exists('isphone', $val) && !FormUtils::isPhone($item[$field])) {
                $this->setError($field, 'WRONG');
                $result = false;
            }
            if (array_key_exists('isdate', $val) && !FormUtils::isDate($item[$field])) {
                $this->setError($field, 'WRONG');
                $result = false;
            }
            if (array_key_exists('isfloat', $val) && !FormUtils::isFloat($item[$field])) {
                $this->setError($field, 'WRONG');
                $result = false;
            }

            #if (!$result) break; #uncomment to break on first error
        }

        return $result;
    }

    public function ShowDeleteAction($id): ?array {
        $id += 0;
        $ps = array(
            'i'          => $this->model->one($id),
            'return_url' => $this->return_url,
            'related_id' => $this->related_id,
            'base_url'   => $this->base_url, #override default template url, remove if you created custom /showdelete templates
        );

        $this->fw->parser('/common/form/showdelete', $ps);
        //return $ps; #use this instead of parser if you created custom /showdelete templates
        return null;
    }

    public function DeleteAction($id): ?array {
        $id += 0;
        $this->model->delete($id);

        $this->fw->flash("onedelete", 1);
        return $this->afterSave(true);
    }

    public function SaveMultiAction(): ?array {
        $acb = req('cb');
        if (!is_array($acb)) {
            $acb = array();
        }
        $is_delete            = reqs('delete') > '';
        $user_lists_id        = reqi("addtolist");
        $remove_user_lists_id = reqi("removefromlist");

        if ($user_lists_id) {
            $user_lists = UserLists::i()->one($user_lists_id);
            if (!$user_lists || $user_lists["add_users_id"] <> $this->fw->userId()) {
                throw new ApplicationException("Wrong Request");
            }
        }

        $ctr = 0;
        foreach ($acb as $id => $value) {
            if ($is_delete) {
                $this->model->delete($id);
                $ctr += 1;
            } elseif ($user_lists_id) {
                UserLists::i()->addItemList($user_lists_id, $id);
                $ctr += 1;
            } elseif ($remove_user_lists_id) {
                UserLists::i()->delItemList($remove_user_lists_id, $id);
                $ctr += 1;
            }
        }

        if ($is_delete) {
            $this->fw->flash("multidelete", $ctr);
        }
        if ($user_lists_id) {
            $this->fw->flash("success", "$ctr records added to the list");
        }

        return $this->afterSaveJson(true, ['ctr' => $ctr]);
    }

    ###################### support for autocomlete related items
    public function AutocompleteAction(): ?array {
        if (!$this->model_related) {
            throw new ApplicationException('No model_related defined');
        }
        $items = $this->model_related->getAutocompleteList(reqs("q"));

        return array('_json' => $items);
    }

    ###################### support for customizable list screen
    public function UserViewsAction($form_id): ?array {

        $ps = array(
            'rows' => $this->getViewListArr($this->getViewListUserFields(), true)
        );

        $this->fw->parser("/common/list/userviews", $ps);
        return null;
    }

    public function SaveUserViewsAction(): ?array {
        $item    = reqh('item');
        $success = true;

        try {
            if (reqi("is_reset")) {
                UserViews::i()->updateByIcode($this->base_url, $this->view_list_defaults);
            } else {
                #save fields
                #order by value
                $ordered = reqh("fld");
                asort($ordered);

                #and then get ordered keys
                $anames = array();
                foreach ($ordered as $key => $value) {
                    $anames[] = $key;
                }

                UserViews::i()->updateByIcode($this->base_url, implode(' ', $anames));
            }

        } catch (Exception $ex) {
            $success = false;
            $this->setFormError($ex);
        }

        return fw::redirect($this->return_url);
    }

    ###################### HELPERS for dynamic fields

    /**
     * prepare data for fields repeat in ShowAction based on config.json show_fields parameter
     * @param array $item one item
     * @param array $ps for parsepage
     * @return array       array of hashtables to build fields in templates
     */
    public function prepareShowFields($item, $ps): array {
        $id = intval($item['id']);

        $fields = $this->config["show_fields"];
        if (!$fields) {
            throw new ApplicationException("Controller config.json doesn't contain 'show_fields'");
        }
        foreach ($fields as &$def) {
            $def['i'] = $item;
            $dtype    = $def["type"];
            $field    = $def["field"];

            if ($dtype == "row" || $dtype == "row_end" || $dtype == "col" || $dtype == "col_end") {
                #structural tags
                $def["is_structure"] = true;

            } elseif ($dtype == "multi") {
                #complex field
                $def["multi_datarow"] = fw::model($def["lookup_model"])->listWithChecked($item[$field], $def);

            } elseif ($dtype == "att") {
                $def["att"] = Att::i()->one($item[$field]);

            } elseif ($dtype == "att_links") {
                $def["att_links"] = Att::i()->getAllLinked($this->model->table_name, $id);

            } else {
                #single values
                #lookups
                if (array_key_exists('lookup_table', $def)) {
                    #lookup by table
                    $lookup_key = $def["lookup_key"];
                    if (!$lookup_key) {
                        $lookup_key = "id";
                    }

                    $lookup_field = $def["lookup_field"];
                    if (!$lookup_field) {
                        $lookup_field = "iname";
                    }

                    $def["lookup_row"] = $this->db->row($def["lookup_table"], array($lookup_key => $item[$field]));
                    $def["value"]      = $def["lookup_row"][$lookup_field];

                } elseif (array_key_exists('lookup_model', $def)) {
                    #lookup by model

                    $def["lookup_row"] = fw::model($def["lookup_model"])->one($item[$field]);

                    $lookup_field = $def["lookup_field"];
                    if (!$lookup_field) {
                        $lookup_field = "iname";
                    }

                    $def["value"] = $def["lookup_row"][$lookup_field];

                } elseif (array_key_exists('lookup_tpl', $def)) {
                    $def["value"] = get_selvalue($def["lookup_tpl"], $item[$field]);
                } else {
                    $def["value"] = $item[$field];
                }
            }

            #convertors
            if (array_key_exists('conv', $def)) {
                if ($def["conv"] == "time_from_seconds") {
                    $def["value"] = DateUtils::int2timestr($def["value"]);
                }
            }
        }
        unset($def);

        return $fields;
    }

    /**
     * prepare data for fields repeat in ShowFormAction based on config.json showform_fields parameter
     * @param array $item one item
     * @param array $ps for parsepage
     * @return array       array of hashtables to build fields in templates
     */
    public function prepareShowFormFields(array $item, array $ps): array {
        $id = intval($item['id'] ?? 0);

        $fields = $this->config["showform_fields"];
        if (!$fields) {
            throw new ApplicationException("Controller config.json doesn't contain 'showform_fields'");
        }
        foreach ($fields as &$def) {
            $def['i']    = $item;  #ref to item
            $def['ps']   = $ps;   #ref to whole ps
            $dtype       = $def["type"]; #type is required
            $field       = $def["field"] ?? '';
            $field_value = $item[$field] ?? '';

            if ($dtype == "row" || $dtype == "row_end" || $dtype == "col" || $dtype == "col_end") {
                #structural tags
                $def["is_structure"] = true;

            } elseif ($dtype == "multicb") {
                #complex field
                logger($def);
                $def["multi_datarow"] = fw::model($def["lookup_model"] ?? '')->listWithChecked($field_value, $def);
                foreach ($def["multi_datarow"] as &$row) {
                    $row["field"] = $def["field"];
                }
                unset($row);

            } elseif ($dtype == "att_edit") {
                $def["att"]   = Att::i()->one($field_value);
                $def["value"] = $field_value;

            } elseif ($dtype == "att_links_edit") {
                $def["att_links"] = Att::i()->getAllLinked($this->model->table_name, $id);

            } else {
                #single values
                #lookups
                if (array_key_exists('lookup_table', $def)) {
                    #lookup by table
                    $lookup_key = $def["lookup_key"];
                    if (!$lookup_key) {
                        $lookup_key = "id";
                    }

                    $lookup_field = $def["lookup_field"];
                    if (!$lookup_field) {
                        $lookup_field = "iname";
                    }

                    $def["lookup_row"] = $this->db->row($def["lookup_table"], array($lookup_key => $field_value));
                    $def["value"]      = $def["lookup_row"][$lookup_field];

                } elseif (array_key_exists('lookup_model', $def)) {
                    if (array_key_exists('lookup_field', $def)) {
                        #lookup value
                        $def["lookup_row"] = fw::model($def["lookup_model"])->one($field_value);
                        $def["value"]      = $def["lookup_row"][$def["lookup_field"]] ?? '';
                    } else {
                        #lookup select
                        $def["select_options"] = fw::model($def["lookup_model"])->listSelectOptions($def['lookup_params'] ?? null);
                        $def["value"]          = $field_value;
                    }

                } elseif (array_key_exists('lookup_tpl', $def)) {
                    $def['select_options'] = FormUtils::selectTplOptions($def['lookup_tpl'], $field_value);
                    $def["value"]          = $field_value;
                    foreach ($def['select_options'] as &$row) { #contains id, iname
                        $row["is_inline"] = $def["is_inline"] ?? false;
                        $row["field"]     = $def["field"];
                        $row["value"]     = $item["field"] ?? '';
                    }
                    unset($row);
                } else {
                    $def["value"] = $field_value;
                }
            }

            #convertors
            if (array_key_exists('conv', $def)) {
                if ($def["conv"] == "time_from_seconds") {
                    $def["value"] = DateUtils::int2timestr($def["value"]);
                }
            }
        }
        unset($def);

        return $fields;
    }

    #auto-process fields BEFORE record saved to db
    public function processSaveShowFormFields($id, &$fields): void {
        $item = reqh("item");

        $showform_fields = $this->_fieldsToHash($this->config["showform_fields"]);
        #special auto-processing for fields of particular types
        foreach (array_keys($fields) as $field) {
            if (array_key_exists($field, $showform_fields)) {
                $def = $showform_fields[$field];
                if ($def['type'] == 'multicb') {
                    #multiple checkboxes
                    $fields[$field] = FormUtils::multi2ids(reqh($field . "_multi"));
                } elseif ($def['type'] == 'autocomplete') {
                    $fields[$field] = fw::model($def["lookup_model"])->findOrAddByIname($fields[$field]);
                } elseif ($def['type'] == 'date_combo') {
                    $fields[$field] = FormUtils::dateForCombo($item, $field);
                } elseif ($def['type'] == 'date_popup') {
                    $fields[$field] = DateUtils::Str2SQL($fields[$field]); #convert date to sql format
                } elseif ($def['type'] == 'time') {
                    $fields[$field] = DateUtils::timestr2int($fields[$field]); #ftime - convert from HH:MM to int (0-24h in seconds)
                } elseif ($def['type'] == 'number') {
                    $fields[$field] = floatval($fields[$field]); #number - convert to number (if field empty or non-number - it will become 0)
                }
            }
        }

        #$fields["fint"]=$fields["fint"]+0 'TODO? field accepts only int
    }

    #auto-process fields AFTER record saved to db
    protected function processSaveShowFormFieldsAfter($id, $fields): void {
        #for now we just look if we have att_links_edit field and update att links
        foreach ($this->config['showform_fields'] as $def) {
            if ($def['type'] == 'att_links_edit') {
                Att::i()->updateAttLinks($this->model->table_name, $id, reqh("att")); #TODO make att configurable
            }
        }
    }

    #convert config's fields list into hashtable as field => {}
    #if there are more than one field - just first field added to the hash
    protected function _fieldsToHash($fields): array {
        $result = array();
        foreach ($fields as $fldinfo) {
            if (array_key_exists('field', $fldinfo) && !array_key_exists($fldinfo['field'], $result)) {
                $result[$fldinfo['field']] = $fldinfo;
            }
        }
        return $result;
    }

}//end of class
