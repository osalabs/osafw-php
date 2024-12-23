<?php
/*
 Static Pages Admin  controller

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2024 Oleg Savchuk www.osalabs.com
*/

class AdminSpagesController extends FwAdminController {
    const int access_level = Users::ACL_MANAGER;

    public FwModel|Spages $model;
    public string $model_name = 'Spages';

    public string $base_url = '/Admin/Spages';
    public string $required_fields = 'iname';
    public string $save_fields = 'iname idesc idesc_left idesc_right head_att_id template prio meta_keywords meta_description custom_head custom_css custom_js';
    public string $save_fields_checkboxes = '';

    /*REMOVE OR OVERRIDE*/
    public string $search_fields = 'url iname idesc';
    public string $list_sortdef = 'iname asc';   //default sorting - req param name, asc|desc direction
    public array $list_sortmap = array(//sorting map: req param name => sql field name(s) asc|desc direction
                                       'id'       => 'id',
                                       'iname'    => 'iname',
                                       'pub_time' => 'pub_time',
                                       'upd_time' => 'upd_time',
                                       'status'   => 'status',
    );

    //override list rows
    public function getListRows(): void {
        if ($this->list_filter['sortby'] == 'iname' && ($this->list_filter['s'] ?? '') == '') {
            $this->list_count = $this->db->valuep("select count(*) from " . $this->model->table_name . " where " . $this->list_where, $this->list_where_params);
            if ($this->list_count > 0) {
                #build pages tree
                $pages_tree      = $this->model->tree($this->list_where, $this->list_where_params, "parent_id, prio desc, iname");
                $this->list_rows = $this->model->getPagesTreeList($pages_tree, 0);

                #apply LIMIT
                if ($this->list_count > $this->list_filter["pagesize"]) {
                    $subset       = array();
                    $start_offset = $this->list_filter["pagenum"] * $this->list_filter["pagesize"];

                    for ($i = $start_offset; $i < min($start_offset + $this->list_filter["pagesize"], count($this->list_rows)); $i++) {
                        $subset[] = $this->list_rows[$i];
                    }

                    $this->list_rows = $subset;
                }

                $this->list_pager = FormUtils::getPager($this->list_count, $this->list_filter["pagenum"], $this->list_filter["pagesize"]);
            } else {
                $this->list_rows  = array();
                $this->list_pager = array();
            }

        } else {
            #if order not by iname or search performed - display plain page list using  Me.get_list_rows()
            parent::getListRows();
        }

        foreach ($this->list_rows as $k => $row) {
            $this->list_rows[$k]['full_url'] = $this->model->getFullUrl($row["id"]);
        }

    }

    //override if necessary: IndexAction, ShowAction, ShowFormAction, Validate, DeleteAction, Export, SaveMultiAction
    //or override just: setListSearch, set_list_rows, getSaveFields

    public function ShowFormAction($form_id): ?array {
        #set new form defaults here if any
        if (reqs("parent_id") > "") {
            $this->form_new_defaults              = array();
            $this->form_new_defaults["parent_id"] = reqi("parent_id");
        }

        $ps = parent::ShowFormAction($form_id);

        $item      = $ps["i"];
        $id        = $item["id"] ?? 0;
        $parent_id = $item["parent_id"] ?? 0;

        $where                          = " status<>127 ";
        $pages_tree                     = $this->model->tree($where, [], "parent_id, prio desc, iname");
        $ps["select_options_parent_id"] = $this->model->getPagesTreeSelectHtml($parent_id, $pages_tree);

        if ($id) {
            $ps["parent_url"] = $this->model->getFullUrl($parent_id);
            $ps["full_url"]   = $this->model->getFullUrl($item["id"]);

            $ps["parent"] = $this->model->one($parent_id);

            if (!empty($item["head_att_id"])) {
                $ps["head_att_id_url_s"] = Att::i()->getUrlDirect($item["head_att_id"], "s");
            }

            $ps["subpages"] = $this->model->listChildren($id);
        }

        return $ps;
    }


    public function SaveAction($form_id): ?array {
        $id   = intval($form_id);
        $item = reqh('item');

        $success  = true;
        $is_new   = ($id == 0);
        $location = '';

        try {
            $item_old = $this->model->one($id);
            $is_home  = $item_old["is_home"] ?? 0;
            #for non-home page enable some fields
            if (!$id || $is_home != 1) {
                $this->required_fields .= " url";
                $this->save_fields     .= " parent_id url status pub_time";
            }

            $this->Validate($id, $item);

            $itemdb = $this->getSaveFields($id, $item);

            #if no publish time defined - publish it now
            if ($itemdb["pub_time"]) {
                $itemdb["pub_time"] = DateUtils::Str2SQL($itemdb["pub_time"]);
            } else {
                $itemdb["pub_time"] = DB::NOW();
            }
            if (!$itemdb["head_att_id"]) {
                $itemdb["head_att_id"] = null;
            }
            logger($itemdb);

            $id = $this->modelAddOrUpdate($id, $itemdb);

            if ($is_home == 1) {
                $this->fw->cache->remove("home_page");
            } #reset home page cache if Home page changed

        } catch (ApplicationException $ex) {
            $success = false;
            $this->setFormError($ex);
        }

        return $this->afterSave($success, $id, $is_new);
    }

}//end of class
