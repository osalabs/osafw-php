<?php
/*
 Base Fw Controller class for standard module with list/form screens

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2024 Oleg Savchuk www.osalabs.com
*/

class FwDynamicController extends FwController {
    const int access_level = 100; #by default Admin Controllers allowed only for Admins

    // public string $base_url = '/Admin/Controller'; # define in inherited controller for loadControllerConfig

    protected FwModel $model_related;

    public function __construct() {
        parent::__construct();

        $this->loadControllerConfig();
        //uncomment in the interited controller
        //$this->model_related = DemoDicts::i();
    }

    public function IndexAction(): ?array {
        #get filters from the search form
        $this->initFilter();

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

        // if export - no need to parse templates and prep for them - just return empty hashtable asap
        if (strlen($this->export_format)) {
            return [];// return empty hashtable just in case action overriden to avoid check for null
        }

        $ps = $this->setPS();

        // userlists support if necessary
        if ($this->is_userlists) {
            $this->setUserLists($ps);
        }

        $ps["select_userfilters"] = UserFilters::i()->listSelectByIcode($this->fw->GLOBAL["controller.action"]);

        if ($this->is_dynamic_index) {
            #customizable headers
            $this->setViewList($ps, reqh("search"));
        }

        return $ps;
    }

    public function NextAction($form_id): ?array {
        $id = intval($form_id);
        if ($id == 0) {
            return fw::redirect($this->base_url);
        }

        $is_prev = reqi("prev") == 1;
        $is_edit = reqi("edit") == 1;

        $this->initFilter("_filter_" . $this->fw->GLOBAL["controller.action"] . ".Index"); //read list filter for the IndexAction

        if (count($this->list_sortmap) == 0) {
            $this->list_sortmap = $this->getViewListSortmap();
        }
        $this->setListSorting();

        $this->setListSearch();
        $this->setListSearchStatus();

        // get all ids
        $sql = "SELECT id FROM " . $this->list_view . " WHERE " . $this->list_where . " ORDER BY " . $this->list_orderby;
        $ids = $this->db->colp($sql, $this->list_where_params);
        if (count($ids) == 0) {
            return fw::redirect($this->base_url);
        }

        $go_id = 0;
        if ($is_prev) {
            $index_prev = -1;
            for ($index = count($ids) - 1; $index >= 0; $index--) {
                if ($ids[$index] == $id) {
                    $index_prev = $index - 1;
                    break;
                }
            }
            if ($index_prev > -1 && $index_prev <= count($ids) - 1) {
                $go_id = intval($ids[$index_prev]);
            } elseif (count($ids) > 0) {
                $go_id = intval($ids[count($ids) - 1]);
            } else {
                return fw::redirect($this->base_url);
            }
        } else {
            $index_next = -1;
            for ($index = 0; $index <= count($ids) - 1; $index++) {
                if ($ids[$index] == $id) {
                    $index_next = $index + 1;
                    break;
                }
            }
            if ($index_next > -1 && $index_next <= count($ids) - 1) {
                $go_id = intval($ids[$index_next]);
            } elseif (count($ids) > 0) {
                $go_id = intval($ids[0]);
            } else {
                return fw::redirect($this->base_url);
            }
        }

        $url = $this->base_url . "/" . $go_id;
        if ($is_edit) {
            $url .= "/edit";
        }
        if ($this->related_id || $this->return_url) {
            $url .= "/?";
        }
        if ($this->related_id) {
            $url .= "related_id=" . urlencode($this->related_id);
        }
        if ($this->return_url) {
            $url .= "&return_url=" . urlencode($this->return_url);
        }

        return fw::redirect($url);
    }

    public function ShowAction($form_id): ?array {
        $id   = intval($form_id);
        $item = $this->model0->one($id);
        if (!$item) {
            throw new ApplicationException("Not Found", 404);
        }

        $ps = array(
            'id'               => $id,
            'i'                => $item,
            'return_url'       => $this->return_url,
            'related_id'       => $this->related_id,
            'base_url'         => $this->base_url,
            'is_userlists'     => $this->is_userlists,
            'is_activity_logs' => $this->is_activity_logs,
            'is_readonly'      => $this->is_readonly,
        );

        $this->setAddUpdUser($ps, $item);

        #dynamic fields
        if ($this->is_dynamic_show) {
            $ps["fields"] = $this->prepareShowFields($item, $ps);
        }

        if ($this->is_userlists) {
            $this->setUserLists($ps, $id);
        }

        if ($this->is_activity_logs) {
            $this->initFilter();

            $this->list_filter["tab_activity"] = reqs("tab_activity") ?? FwActivityLogs::TAB_COMMENTS;
            $ps["list_filter"]                 = $this->list_filter;
            $ps["activity_entity"]             = $this->model0->table_name;
            $ps["activity_rows"]               = FwActivityLogs::i()->listByEntityForUI($this->model0->table_name, $id, $this->list_filter["tab_activity"]);
        }

        return $ps;
    }

    public function ShowFormAction($form_id): ?array {
        $id   = intval($form_id);
        $item = reqh('item'); // set defaults from request params

        if ($this->isGet()) {
            if ($id > 0) {
                $item = $this->model0->one($id);
            } else {
                #defaults
                $item = array_merge($item, $this->form_new_defaults);
            }
        } else {
            $itemdb = $id ? $this->model0->one($id) : array();
            $item   = array_merge($itemdb, $item);
        }

        $ps = array(
            'id'          => $id,
            'i'           => $item,
            'return_url'  => $this->return_url,
            'related_id'  => $this->related_id,
            'is_readonly' => $this->is_readonly,
        );
        $this->setAddUpdUser($ps, $item);

        if ($this->is_dynamic_showform) {
            $ps["fields"] = $this->prepareShowFormFields($item, $ps);
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
        $this->route_onerror = FW::ACTION_SHOW_FORM;

        Users::i()->checkReadOnly();
        if (reqi("refresh") == 1) {
            $this->fw->routeRedirect(FW::ACTION_SHOW_FORM, null, [$form_id]);
            return null;
        }

        $id   = intval($form_id);
        $item = reqh('item');

        $success = true;
        $is_new  = ($id == 0);

        $this->Validate($id, $item);
        // load old record if necessary
        // $item_old = $this->model0->one($id);

        $itemdb = $this->getSaveFields($id, $item);

        $id = $this->modelAddOrUpdate($id, $itemdb);

        return $this->afterSave($success, $id, $is_new);
    }

    /**
     * Performs submitted form validation for required field and simple validations: exits, isemail, isphone, isdate, isfloat.
     * If more complex validation required - just override this and call just necessary validation
     * @param int $id
     * @param array $item
     * @return void
     * @throws ValidationException
     */
    public function Validate(int $id, array $item): void {
        $result = $this->validateRequiredDynamic($item);

        if ($result && $this->is_dynamic_showform) {
            $this->validateSimpleDynamic($id, $item);
        }

        //        if ($result) {
        //            if ($this->model0->isExists($item['iname'], $id)) {
        //                $this->setError('iname', 'EXISTS');
        //            }
        //        }
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
    public function validateSimpleDynamic(int $id, array $item): bool {
        $result = true;

        $subtable_del = reqh("subtable_del");

        $fields = $this->config["showform_fields"];
        foreach ($fields as $def) {
            $field = $def['field'] ?? '';
            if (!$field) {
                continue;
            }

            $type = $def['type'] ?? '';

            if ($type == "subtable_edit") {
                //validate subtable rows
                $model_name = $def['model'];
                $sub_model  = fw::model($model_name);

                $save_fields            = $def['required_fields'] ?? '';
                $save_fields_checkboxes = $def['save_fields_checkboxes'];

                //check if we delete specific row
                $del_id = $subtable_del[$model_name] ?? '';

                // row ids submitted as: item-<~model>[<~id>]
                // input name format: item-<~model>#<~id>[field_name]
                $hids = reqh("item-" . $model_name);
                // sort hids.Keys, so numerical keys - first and keys staring with "new-" will be last
                $sorted_keys = array_keys($hids);
                usort($sorted_keys, function ($a, $b) {
                    if (str_starts_with($a, "new-") && str_starts_with($b, "new-")) {
                        return 0;
                    }
                    if (str_starts_with($a, "new-")) {
                        return 1;
                    }
                    if (str_starts_with($b, "new-")) {
                        return -1;
                    }
                    return $a <=> $b;
                });

                foreach ($sorted_keys as $row_id) {
                    if ($row_id == $del_id) {
                        continue; //skip deleted row
                    }

                    $row_item = reqh("item-" . $model_name . "#" . $row_id);
                    $itemdb   = FormUtils::filter($row_item, $save_fields);
                    FormUtils::filterCheckboxes($itemdb, $row_item, $save_fields_checkboxes);

                    if (str_starts_with($row_id, "new-")) {
                        $itemdb[$sub_model->junction_field_main_id] = $id;
                    }

                    //VAILIDATE itemdb
                    $is_valid = $this->validateSubtableRowDynamic($row_id, $itemdb, $def);
                }
            } else {
                // other types - use "validate" field
                $val = Utils::qh($def["validate"]);
                if (count($val) > 0) {
                    $field_value = $item[$field] ?? '';

                    if (array_key_exists('exists', $val) && $this->model0->isExists($field_value, $id)) {
                        $this->setError($field, 'EXISTS');
                        $result = false;
                    }
                    if (array_key_exists('isemail', $val) && !FormUtils::isEmail($field_value)) {
                        $this->setError($field, 'WRONG');
                        $result = false;
                    }
                    if (array_key_exists('isphone', $val) && !FormUtils::isPhone($field_value)) {
                        $this->setError($field, 'WRONG');
                        $result = false;
                    }
                    if (array_key_exists('isdate', $val) && !FormUtils::isDate($field_value)) {
                        $this->setError($field, 'WRONG');
                        $result = false;
                    }
                    if (array_key_exists('isfloat', $val) && !FormUtils::isFloat($field_value)) {
                        $this->setError($field, 'WRONG');
                        $result = false;
                    }
                }
            }

            #if (!$result) break; #uncomment to break on first error
        }

        return $result;
    }

    /**
     * validate single subtable row using def[required_fields] and fill fw.FormErrors with row errors if any
     * Override in controller and add custom validation if needed
     * @param string $row_id row_id can start with "new-" (for new rows) or be numerical id (existing rows)
     * @param array $item submitted row data from the form
     * @param array $def subable definition from config.json
     * @return bool
     */
    public function validateSubtableRowDynamic(string $row_id, array $item, array $def): bool {
        $result          = true;
        $required_fields = Utils::qw($def["required_fields"] ?? "");
        if (count($required_fields) == 0) {
            return $result; //nothing to validate
        }

        $row_errors = array();
        $result     = $this->validateRequired($item, $required_fields, $row_errors);
        if (!$result) {
            //fill global fw.FormErrors with row errors
            $model_name = $def["model"];
            foreach ($row_errors as $field_name => $value) {
                // row input names format: item-<~model>#<~id>[field_name]
                $this->fw->FormErrors["item-" . $model_name . "#" . $row_id . "[$field_name]"] = true;
            }
            $this->fw->FormErrors["REQUIRED"] = true; // also set global error
        }

        return $result;
    }


    public function ShowDeleteAction($form_id): ?array {
        Users::i()->checkReadOnly();

        $id = intval($form_id);
        $ps = array(
            'i'          => $this->model0->one($id),
            'return_url' => $this->return_url,
            'related_id' => $this->related_id,
            'base_url'   => $this->base_url, #override default template url, remove if you created custom /showdelete templates
        );

        $this->fw->parser('/common/form/showdelete', $ps);
        //return $ps; #use this instead of parser if you created custom /showdelete templates
        return null;
    }

    public function DeleteAction($form_id): ?array {
        Users::i()->checkReadOnly();

        $id = intval($form_id);

        try {
            $this->model0->deleteWithPermanentCheck($id);
            $this->fw->flash("onedelete", 1);
        } catch (Exception $ex) {
            $msg = $ex->getMessage();
            if (preg_match('/table\s+"[^.]+\.([^"]+)",\s+column\s+\'([^\']+)\'/', $msg, $match)) {
                $tableName = Utils::capitalize($match[1], "all");
                $msg       = "This record cannot be deleted because it is linked to another $tableName record. You will need to unlink these records before either can be deleted";
            }

            $this->fw->flash("error", $msg);
            return fw::redirect("{$this->base_url}/{$id}/delete");
        }

        $this->model0->delete($id);

        $this->fw->flash("onedelete", 1);
        return $this->afterSave(true);
    }

    public function RestoreDeletedAction($form_id): ?array {
        Users::i()->checkReadOnly();

        $id = intval($form_id);
        $this->model0->update($id, [$this->model0->field_status => FwModel::STATUS_ACTIVE]);

        $this->fw->flash("record_updated", 1);
        return $this->afterSave(true, $id);
    }

    public function SaveMultiAction(): ?array {
        $this->route_onerror = FW::ACTION_INDEX;

        $acb       = reqh('cb');
        $is_delete = reqs('delete') > '';
        if ($is_delete) {
            Users::i()->checkReadOnly();
        }
        $user_lists_id        = reqi("addtolist");
        $remove_user_lists_id = reqi("removefromlist");

        if ($user_lists_id) {
            $user_lists = UserLists::i()->one($user_lists_id);
            if (!$user_lists || $user_lists["add_users_id"] != $this->fw->userId()) {
                throw new ApplicationException("Wrong Request");
            }
        }

        $ctr = 0;
        foreach ($acb as $id => $value) {
            $id = intval($id);
            if ($is_delete) {
                $this->model0->deleteWithPermanentCheck($id);
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

    /**
     * Autocomplete action for related items
     * @return array|null
     * @throws ApplicationException
     */
    public function AutocompleteAction(): ?array {
        $items = $this->model_related->listAutocomplete(reqs("q"));

        return ['_json' => $items];
    }

    ###################### support for customizable list screen
    public function UserViewsAction($form_id): ?array {
        $ps = array(
            'rows'             => $this->getViewListArr($this->getViewListUserFields(), true),
            'select_userviews' => UserViews::i()->listSelectByIcode($this->base_url),
        );

        $this->fw->parser("/common/list/userviews", $ps);
        return null;
    }

    /*
     * public virtual Hashtable SaveUserViewsAction()
    {
        var fld = reqh("fld");
        var load_id = reqi("load_id");
        var is_reset = reqi("is_reset");
        var density = reqs("density");

        if (load_id > 0)
            // set fields from specific view
            fw.model<UserViews>().setViewForIcode(base_url, load_id);
        else if (is_reset == 1)
            // reset fields to defaults
            fw.model<UserViews>().updateByIcodeFields(base_url, view_list_defaults);
        else if (density.Length > 0)
        {
            // save density
            // validate density can be only table-sm, table-dense, table-normal, otherwise - set empty
            if (!"table-sm table-dense table-normal".Contains(density))
                density = "";
            fw.model<UserViews>().updateByIcode(base_url, DB.h("density", density));
        }
        else
        {
            var item = reqh("item");
            var iname = Utils.f2str(item["iname"]);

            // save fields
            // order by value
            var ordered = fld.Cast<DictionaryEntry>().OrderBy(entry => Utils.f2int(entry.Value)).ToList();
            // and then get ordered keys
            List<string> anames = new();
            foreach (var el in ordered)
                anames.Add((string)el.Key);
            var fields = string.Join(" ", anames);

            if (!string.IsNullOrEmpty(iname))
            {
                // create new view by name or update if this name exists
                fw.model<UserViews>().addOrUpdateByUK(base_url, fields, iname);
            }
            // update default view with fields
            fw.model<UserViews>().updateByIcodeFields(base_url, fields);
        }

        return afterSave(true, null, false, "no_action", return_url);
    }

     * */
    public function SaveUserViewsAction(): ?array {
        $fld      = reqh("fld");
        $load_id  = reqi("load_id");
        $is_reset = reqi("is_reset");
        $density  = reqs("density");

        if ($load_id > 0) {
            // set fields from specific view
            UserViews::i()->setViewForIcode($this->base_url, $load_id);
        } elseif ($is_reset == 1) {
            // reset fields to defaults
            UserViews::i()->updateByIcodeFields($this->base_url, $this->view_list_defaults);
        } elseif ($density) {
            // save density
            // validate density can be only table-sm, table-dense, table-normal, otherwise - set empty
            if (!in_array($density, ["table-sm", "table-dense", "table-normal"])) {
                $density = "";
            }
            UserViews::i()->updateByIcode($this->base_url, ['density' => $density]);
        } else {
            $item  = reqh("item");
            $iname = $item["iname"] ?? '';

            // save fields
            // order by value
            $ordered = array();
            foreach ($fld as $key => $value) {
                $ordered[$key] = intval($value);
            }
            asort($ordered);
            $anames = array_keys($ordered);
            $fields = implode(" ", $anames);

            if ($iname) {
                // create new view by name or update if this name exists
                UserViews::i()->addOrUpdateByUK($this->base_url, $fields, $iname);
            }
            // update default view with fields
            UserViews::i()->updateByIcodeFields($this->base_url, $fields);
        }

        return $this->afterSave(true, null, false, "no_action", $this->return_url);
    }


    /**
     * support for sortable rows
     * @return array[]|null
     * @throws ApplicationException
     */
    public function SaveSortAction(): ?array {
        $sortdir  = reqs("sortdir");
        $id       = reqi("id");
        $under_id = reqi("under");
        $above_id = reqi("above");

        $success = $this->model0->reorderPrio($sortdir, $id, $under_id, $above_id);

        return ['_json' => ['success' => $success]];
    }


    ###################### HELPERS for dynamic fields

    /**
     * prepare data for fields repeat in ShowAction based on config.json show_fields parameter
     * @param array $item
     * @param array $ps
     * @return array
     * @throws ApplicationException
     * @throws DBException
     * @throws NoModelException
     */
    public function prepareShowFields(array $item, array $ps): array {
        $id = intval($item['id'] ?? 0);

        $fields = $this->config["show_fields"];
        if (!$fields) {
            throw new ApplicationException("Controller config.json doesn't contain 'show_fields'");
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

            } elseif ($dtype == "multi") {
                #complex field
                if (array_key_exists('lookup_model', $def)) {
                    $def["multi_datarow"] = fw::model($def["lookup_model"])->listWithChecked($field_value, $def);
                } else {
                    if ($def["is_by_linked"]) {
                        #list main items by linked id from junction model (i.e. list of Users(with checked) for Company from UsersCompanies model)
                        $def["multi_datarow"] = fw::model($def["model"])->listMainByLinkedId($id, $def); #junction model
                    } else {
                        #list linked items by main id from junction model (i.e. list of Companies(with checked) for User from UsersCompanies model)
                        $def["multi_datarow"] = fw::model($def["model"])->listLinkedByMainId($id, $def); #junction model
                    }
                }

            } elseif ($dtype == "multi_prio") {
                #complex field with prio
                $def["multi_datarow"] = fw::model($def["model"])->listLinkedByMainId($id, $def); #junction model

            } elseif ($dtype == "att") {
                $def["att"] = Att::i()->one(intval($field_value));

            } elseif ($dtype == "att_links") {
                $def["att_links"] = Att::i()->listLinked($this->model0->table_name, $id);

            } elseif ($dtype == "subtable") {
                #subtable functionality
                $model_name = $def["model"];
                $sub_model  = fw::model($model_name);
                $list_rows  = $sub_model->listByMainId($id, $def); #list related rows from db
                $sub_model->prepareSubtable($list_rows, $id, $def);

                $def["list_rows"] = $list_rows;

            } else {
                #single values
                #lookups
                if (array_key_exists('lookup_table', $def)) {
                    $lookup_key   = $def["lookup_key"] ?? "id";
                    $lookup_field = $def["lookup_field"] ?? "iname";

                    $def["lookup_row"] = $this->db->row($def["lookup_table"], array($lookup_key => $field_value));
                    $def["value"]      = $def["lookup_row"][$lookup_field];

                } elseif (array_key_exists('lookup_model', $def)) {
                    $lookup_model      = fw::model($def["lookup_model"]);
                    $def["lookup_id"]  = intval($item[$def["lookup_id"]]);
                    $def["lookup_row"] = $lookup_model->one($def["lookup_id"]);

                    $lookup_field = $def["lookup_field"] ?? $lookup_model->field_iname;
                    $def["value"] = $def["lookup_row"][$lookup_field] ?? '';
                    if (!array_key_exists('admin_url', $def)) {
                        $def["admin_url"] = "/Admin/" . $def["lookup_model"]; #default admin url from model name
                    }
                } elseif (array_key_exists('lookup_tpl', $def)) {
                    $def["value"] = FormUtils::selectTplName($def["lookup_tpl"], $item[$field]);
                } else {
                    $def["value"] = $field_value;
                }

                #convertors
                if (array_key_exists('conv', $def)) {
                    if ($def["conv"] == "time_from_seconds") {
                        $def["value"] = DateUtils::int2timestr($def["value"]);
                    }
                }
            }
        }
        unset($def);

        return $fields;
    }

    public function prepareShowFormFields(array $item, array $ps): array {
        $id = intval($item['id'] ?? 0);

        $subtable_add = reqh("subtable_add");
        $subtable_del = reqh("subtable_del");

        $fields = $this->config["showform_fields"];
        if (!$fields) {
            throw new ApplicationException("Controller config.json doesn't contain 'showform_fields'");
        }
        foreach ($fields as &$def) {
            // logger(def)
            $def['i']    = $item; // ref to item
            $def['ps']   = $ps; // ref to whole ps
            $dtype       = $def["type"]; // type is required
            $field       = $def["field"] ?? '';
            $field_value = $item[$field] ?? '';

            if ($id == 0 && ($dtype == "added" || $dtype == "updated")) {
                // special case - hide if new item screen
                $def["class"] = "d-none";
            }

            if ($dtype == "row" || $dtype == "row_end" || $dtype == "col" || $dtype == "col_end") {
                // structural tags
                $def["is_structure"] = true;

            } elseif ($dtype == "multicb") {
                if (array_key_exists('lookup_model', $def)) {
                    $def["multi_datarow"] = fw::model($def["lookup_model"])->listWithChecked($field_value, $def);
                } else {
                    if ($def["is_by_linked"]) {
                        // list main items by linked id from junction model (i.e. list of Users(with checked) for Company from UsersCompanies model)
                        $def["multi_datarow"] = fw::model($def["model"])->listMainByLinkedId($id, $def); //junction model
                    } else {
                        // list linked items by main id from junction model (i.e. list of Companies(with checked) for User from UsersCompanies model)
                        $def["multi_datarow"] = fw::model($def["model"])->listLinkedByMainId($id, $def); //junction model
                    }
                }

                foreach ($def["multi_datarow"] as &$row) {
                    $row["field"] = $def["field"];
                }
                unset($row);

            } elseif ($dtype == "multicb_prio") {
                $def["multi_datarow"] = fw::model($def["model"])->listLinkedByMainId($id, $def); // junction model

                foreach ($def["multi_datarow"] as &$row) {
                    $row["field"] = $def["field"];
                }
                unset($row);

            } elseif ($dtype == "att_edit") {
                $def["att"]   = Att::i()->one(intval($field_value));
                $def["value"] = $field_value;

            } elseif ($dtype == "att_links_edit") {
                $def["att_links"] = Att::i()->listLinked($this->model0->table_name, $id);

            } elseif ($dtype == "subtable_edit") {
                // subtable functionality
                $model_name = $def["model"];
                $sub_model  = fw::model($model_name);
                $list_rows  = array();

                if ($this->isGet()) {
                    if ($id > 0) {
                        $list_rows = $sub_model->listByMainId($id, $def); //list related rows from db
                    } else {
                        $sub_model->prepareSubtableAddNew($list_rows, $id, $def); //add at least one row
                    }
                } else {
                    //check if we deleted specific row
                    $del_id = $subtable_del[$model_name] ?? '';

                    //copy list related rows from the form
                    // row ids submitted as: item-<~model>[<~id>]
                    // input name format: item-<~model>#<~id>[field_name]
                    $hids = reqh("item-" . $model_name);
                    // sort hids.Keys, so numerical keys - first and keys staring with "new-" will be last
                    $sorted_keys = array_keys($hids);
                    usort($sorted_keys, function ($a, $b) {
                        if (str_starts_with($a, "new-") && str_starts_with($b, "new-")) {
                            return 0;
                        }
                        if (str_starts_with($a, "new-")) {
                            return 1;
                        }
                        if (str_starts_with($b, "new-")) {
                            return -1;
                        }
                        return $a <=> $b;
                    });

                    foreach ($sorted_keys as $row_id) {
                        if ($row_id == $del_id) {
                            continue; //skip deleted row
                        }

                        $row_item       = reqh("item-" . $model_name . "#" . $row_id);
                        $row_item["id"] = $row_id;

                        $list_rows[] = $row_item;
                    }
                }

                //delete row clicked
                //if (subtable_del.ContainsKey(model_name))
                //{
                //    var del_id = (string)subtable_del[model_name];
                //    // delete with LINQ from the form list (actual delete from db will be on save)
                //    list_rows = new ArrayList((from Hashtable d in list_rows
                //                               where (string)d["id"] != del_id
                //                               select d).ToList());
                //}

                //add new clicked
                if (array_key_exists($model_name, $subtable_add)) {
                    $sub_model->prepareSubtableAddNew($list_rows, $id, $def);
                }

                //prepare rows for display (add selects, etc..)
                $sub_model->prepareSubtable($list_rows, $id, $def);

                $def["list_rows"] = $list_rows;

            } else {
                // single values
                // lookups
                if (array_key_exists('lookup_table', $def)) {
                    $lookup_key   = $def["lookup_key"] ?? "id";
                    $lookup_field = $def["lookup_field"] ?? "iname";

                    $lookup_row        = $this->db->row($def["lookup_table"], [$lookup_key => $field_value]);
                    $def["lookup_row"] = $lookup_row;
                    $def["value"]      = $lookup_row[$lookup_field];

                } elseif (array_key_exists('lookup_model', $def)) {
                    if ($dtype == "select" || $dtype == "radio") {
                        // lookup select
                        $def["select_options"] = fw::model($def["lookup_model"])->listSelectOptions($def);
                        $def["value"]          = $field_value;
                    } else {
                        // single value from lookup
                        $lookup_model      = fw::model($def["lookup_model"]);
                        $def["lookup_id"]  = intval($item[$def["lookup_id"]]);
                        $lookup_row        = $lookup_model->one($def["lookup_id"]);
                        $def["lookup_row"] = $lookup_row;

                        $lookup_field = $def["lookup_field"] ?? $lookup_model->field_iname;
                        $def["value"] = $lookup_row[$lookup_field] ?? '';
                        if (!array_key_exists('admin_url', $def)) {
                            $def["admin_url"] = "/Admin/" . $def["lookup_model"]; // default admin url from model name
                        }
                    }

                } elseif (array_key_exists('lookup_tpl', $def)) {
                    $def["select_options"] = FormUtils::selectTplOptions($def["lookup_tpl"]);
                    $def["value"]          = $field_value;
                    foreach ($def["select_options"] as &$row) {
                        $row["is_inline"] = $def["is_inline"];
                        $row["field"]     = $def["field"];
                        $row["value"]     = $field_value;
                    }
                    unset($row);
                } else {
                    $def["value"] = $field_value;
                }

                // convertors
                if (array_key_exists('conv', $def)) {
                    if ($def["conv"] == "time_from_seconds") {
                        $def["value"] = DateUtils::int2timestr($def["value"]);
                    }
                }
            }
        }
        unset($def);

        return $fields;
    }

    #auto-process fields BEFORE record saved to db
    protected function processSaveShowFormFields($id, &$fields): void {
        $item = reqh("item");

        $showform_fields = $this->_fieldsToHash($this->config["showform_fields"]);
        $fnullable       = Utils::qh($this->save_fields_nullable);
        #special auto-processing for fields of particular types
        foreach ($fields as $field => $value) {
            if (array_key_exists($field, $showform_fields)) {
                $def  = $showform_fields[$field];
                $type = $def["type"];
                if ($type == "autocomplete") {
                    $fields[$field] = fw::model($def["lookup_model"])->findOrAddByIname($value, $out);
                } elseif ($type == "date_combo") {
                    $fields[$field] = FormUtils::dateForCombo($item, $field);
                } elseif ($def['type'] == 'date_popup') {
                    $fields[$field] = DateUtils::Str2SQL($value); #convert date to sql format
                } elseif ($type == "time") {
                    $fields[$field] = DateUtils::timestr2int($value); // ftime - convert from HH:MM to int (0-24h in seconds)
                } elseif ($type == "number") {
                    if (array_key_exists($field, $fnullable) && empty($value)) {
                        // if field nullable and empty - pass NULL
                        $fields[$field] = null;
                    } else {
                        $fields[$field] = floatval($value); // number - convert to number (if field empty or non-number - it will become 0)
                    }
                }
            }
        }

    }

    /**
     * auto-process fields AFTER record saved to db
     * @param int $id
     * @param array $fields
     * @return void
     * @throws DBException
     * @throws NoModelException
     */
    protected function processSaveShowFormFieldsAfter(int $id, array $fields): void {
        $subtable_del = reqh("subtable_del");

        $fields_update = array();

        // for now we just look if we have att_links_edit field and update att links
        $showform_fields = $this->_fieldsToHash($this->config["showform_fields"]);
        foreach ($showform_fields as $def) {
            $type = $def["type"];
            if ($type == "att_links_edit") {
                AttLinks::i()->updateJunction($this->model0->table_name, $id, reqh("att")); // TODO make att configurable
            } elseif ($type == "multicb") {
                if (empty($def["model"])) {
                    $fields_update[$def["field"]] = FormUtils::multi2ids(reqh($def["field"] . "_multi")); // multiple checkboxes -> single comma-delimited field
                } else {
                    if ($def["is_by_linked"]) {
                        //by linked id
                        fw::model($def["model"])->updateJunctionByLinkedId($id, reqh($def["field"] . "_multi")); // junction model
                    } else {
                        //by main id
                        fw::model($def["model"])->updateJunctionByMainId($id, reqh($def["field"] . "_multi")); // junction model
                    }
                }
            } elseif ($type == "multicb_prio") {
                fw::model($def["model"])->updateJunctionByMainId($id, reqh($def["field"] . "_multi")); // junction model
            } elseif ($type == "subtable_edit") {
                //save subtable
                $model_name = $def["model"];
                $sub_model  = fw::model($model_name);

                $save_fields            = $def["save_fields"];
                $save_fields_checkboxes = $def["save_fields_checkboxes"];

                //check if we delete specific row
                $del_id = $subtable_del[$model_name] ?? '';

                //mark all related records as under update (status=1)
                $sub_model->setUnderUpdateByMainId($id);

                //update and add new rows

                // row ids submitted as: item-<~model>[<~id>]
                // input name format: item-<~model>#<~id>[field_name]
                $hids = reqh("item-" . $model_name);
                // sort hids.Keys, so numerical keys - first and keys staring with "new-" will be last
                $sorted_keys = array_keys($hids);
                usort($sorted_keys, function ($a, $b) {
                    if (str_starts_with($a, "new-") && str_starts_with($b, "new-")) {
                        return 0;
                    }
                    if (str_starts_with($a, "new-")) {
                        return 1;
                    }
                    if (str_starts_with($b, "new-")) {
                        return -1;
                    }
                    return $a <=> $b;
                });

                $junction_field_status = $sub_model->getJunctionFieldStatus();
                foreach ($sorted_keys as $row_id) {
                    if ($row_id == $del_id) {
                        continue; //skip deleted row
                    }

                    $row_item = reqh("item-" . $model_name . "#" . $row_id);
                    $itemdb   = FormUtils::filter($row_item, $save_fields);
                    FormUtils::filterCheckboxes($itemdb, $row_item, $save_fields_checkboxes);

                    $itemdb[$junction_field_status] = FwModel::STATUS_ACTIVE; // mark new and updated existing rows as active

                    $this->modelAddOrUpdateSubtableDynamic($id, $row_id, $itemdb, $def, $sub_model);
                }

                //remove any not updated rows (i.e. those deleted by user)
                $sub_model->deleteUnderUpdateByMainId($id);
            }
        }

        if (count($fields_update) > 0) {
            $this->model0->update($id, $fields_update);
        }
    }

    /**
     * modelAddOrUpdate for subtable with dynamic model
     * @param int $main_id main entity id
     * @param string $row_id row_id can start with "new-" (for new rows) or be numerical id (existing rows)
     * @param array $fields fields to save to db
     * @param array $def subable definition from config.json
     * @param FwModel|null $sub_model optional subtable model, if not passed def[model] will be used
     * @return int
     * @throws DBException
     * @throws NoModelException
     */
    public function modelAddOrUpdateSubtableDynamic(int $main_id, string $row_id, array $fields, array $def, ?FwModel $sub_model = null): int {
        if ($sub_model === null) {
            $model_name = $def["model"];
            $sub_model  = fw::model($model_name);
        }

        if (str_starts_with($row_id, "new-")) {
            $fields[$sub_model->junction_field_main_id] = $main_id;
            $id                                         = $sub_model->add($fields);
        } else {
            $id = intval($row_id);
            $sub_model->update($id, $fields);
        }

        return $id;
    }

    /**
     * return first field definition by field name
     * @param string $field_name
     * @param array $fields
     * @return array|null
     */
    protected function defByFieldname(string $field_name, array $fields): ?array {
        foreach ($fields as $def) {
            if ($def["field"] == $field_name) {
                return $def;
            }
        }
        return null;
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
