<?php
/*
Base Fw Controller class for standard module with list/form screens

Part of PHP osa framework  www.osalabs.com/osafw/php
(c) 2009-2024 Oleg Savchuk www.osalabs.com
 */

class FwAdminController extends FwController {
    const access_level         = Users::ACL_SITE_ADMIN; #by default Admin Controllers allowed only for Admins
    const route_default_action = '';
    public $base_url = '/Admin/FwAdmin';
    public $required_fields = 'iname';
    public $save_fields = 'iname status';
    public $save_fields_checkboxes = '';
    public $save_fields_nullable = '';
    #public $model_name = 'DemoDicts'; #set in child class!
    /*REMOVE OR OVERRIDE
    public $search_fields = 'iname idesc';
    public $list_sortdef = 'iname asc';   //default sorting - req param name, asc|desc direction
    public $list_sortmap = array(                   //sorting map: req param name => sql field name(s) asc|desc direction
    'id'            => 'id',
    'iname'         => 'iname',
    'add_time'      => 'add_time',
    );
     */

    public function __construct() {
        parent::__construct();

        //optionally init controller
        $this->list_view = $this->model->table_name;
    }

    public function IndexAction() {
        #get filters from the search form
        $f = $this->initFilter();

        $this->setListSorting();
        $this->setListSearch();
        $this->setListSearchStatus();
        //other filters add to $this->list_where here

        $this->getListRows();
        //add/modify rows from db
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
        );

        #optional userlists support
        $ps["select_userlists"] = UserLists::i()->listSelectByEntity($this->list_view);
        $ps["mylists"]          = UserLists::i()->listForItem($this->list_view, 0);
        $ps["list_view"]        = $this->list_view;

        return $ps;
    }

    public function ShowAction($form_id) {
        $id   = intval($form_id);
        $item = $this->model->one($id);
        if (!$item) {
            throw new ApplicationException("Not Found", 404);
        }

        $ps = array(
            'id'                => $id,
            'i'                 => $item,
            'add_users_id_name' => Users::i()->iname($item['add_users_id'] ?? 0),
            'upd_users_id_name' => Users::i()->iname($item['upd_users_id'] ?? 0),
            'return_url'        => $this->return_url,
            'related_id'        => $this->related_id,
        );

        #userlists support
        $ps["list_view"] = $this->list_view ? $this->list_view : $this->model->table_name;
        $ps["mylists"]   = UserLists::i()->listForItem($ps["list_view"], $id);

        return $ps;
    }

    public function ShowFormAction($form_id) {
        $id = intval($form_id);

        if ($this->fw->isGetRequest()) {
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
            'id'                => $id,
            'i'                 => $item,
            'add_users_id_name' => Users::i()->iname($item['add_users_id'] ?? 0),
            'upd_users_id_name' => Users::i()->iname($item['upd_users_id'] ?? 0),
            'return_url'        => $this->return_url,
            'related_id'        => $this->related_id,
        );

        return $ps;
    }

    public function SaveAction($form_id) {
        $this->fw->checkXSS();

        $id   = intval($form_id);
        $item = reqh('item');

        $success  = true;
        $is_new   = ($id == 0);
        $location = '';

        try {
            $this->Validate($id, $item);

            $itemdb = $this->getSaveFields($id, $item);

            $id = $this->modelAddOrUpdate($id, $itemdb);

            $location = $this->getReturnLocation($id);
        } catch (ApplicationException $ex) {
            $success = false;
            $this->setFormError($ex->getMessage());
        }

        return $this->afterSave($success, $location, $id, $is_new);
    }

    public function Validate($id, $item) {
        $result = $this->validateRequired($item, $this->required_fields);

        /*
        if ($result){
        if ($this->model->isExists( $item['iname'], $id ) ){
        $this->setError('iname', 'EXISTS');
        }
        }
         */
        $this->validateCheckResult();
    }

    public function ShowDeleteAction($id) {
        $id += 0;
        $ps = array(
            'i'          => $this->model->one($id),
            'return_url' => $this->return_url,
            'related_id' => $this->related_id,
            'base_url'   => $this->fw->config->ROOT_URL . $this->base_url, #override default template url, remove if you created custom /showdelete templates
        );

        $this->fw->parser('/common/form/showdelete', $ps);
        //return $ps; #use this instead of parser if you created custom /showdelete templates
    }

    public function DeleteAction($id) {
        $this->fw->checkXSS();

        $id += 0;
        $this->model->delete($id);

        $this->fw->flash("onedelete", 1);
        fw::redirect($this->getReturnLocation());
    }

    public function SaveMultiAction() {
        $this->fw->checkXSS();

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

        fw::redirect($this->getReturnLocation());
    }
} //end of class
