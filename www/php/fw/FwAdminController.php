<?php
/*
Base Fw Controller class for standard module with list/form screens

Part of PHP osa framework  www.osalabs.com/osafw/php
(c) 2009-2025 Oleg Savchuk www.osalabs.com
 */

class FwAdminController extends FwController {
    const int    access_level         = Users::ACL_SITE_ADMIN; #by default Admin Controllers allowed only for Admins
    const string route_default_action = '';

    // set/override in child class:
    #public $model_name = 'DemoDicts'; #set in child class!
    public string $base_url = '/Admin/FwAdmin';
    public string $required_fields = 'iname';
    public string $save_fields = 'iname status';
    public string $save_fields_checkboxes = '';
    public string $save_fields_nullable = '';

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
        $this->list_view = $this->model->table_name ?? '';
    }

    public function IndexAction(): ?array {
        #get filters from the search form
        $this->initFilter();

        $this->setListSorting();
        $this->setListSearch();
        $this->setListSearchStatus();
        //other filters add to $this->list_where here

        $this->getListRows();
        //add/modify rows from db
        //        foreach ($this->list_rows as $k => $row) {
        //            $this->list_rows[$k]['field'] = 'value';
        //        }

        $ps = $this->setPS();

        // userlists support if necessary
        if ($this->is_userlists) {
            $this->setUserLists($ps);
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

        // userlists support if necessary
        if ($this->is_userlists) {
            $this->setUserLists($ps);
        }

        return $ps;
    }

    public function ShowFormAction($form_id): ?array {
        $id   = intval($form_id);
        $item = reqh('item');

        if ($this->isGet()) {
            if ($id > 0) {
                // edit screen
                $item = $this->model->one($id);
            } else {
                // add new screen
                $item_new = [];
                $item_new = array_merge($item_new, $this->form_new_defaults); // use hardcoded defaults if any
                $item_new = array_merge($item_new, $item); // override with passed defaults
                $item     = $item_new;
            }
        } else {
            $itemdb = $this->model->one($id);
            $item   = array_merge($itemdb, $item);
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

        return $ps;
    }

    public function SaveAction($form_id): ?array {
        $this->route_onerror = FW::ACTION_SHOW_FORM;
        if (empty($this->save_fields)) {
            throw new Exception("No fields to save defined, define in Controller.save_fields");
        }

        Users::i()->checkReadOnly();
        $is_refresh = reqb("refresh");
        if ($is_refresh && empty($form_id)) { //for new record - just refresh the form, for existing - also try to save
            $this->fw->routeRedirect(FW::ACTION_SHOW_FORM, null, [$form_id]);
            return null;
        }

        $id   = intval($form_id);
        $item = reqh('item');

        $success = !$is_refresh; #if refresh - force route redirect to form
        $is_new  = ($id == 0);

        $this->Validate($id, $item);

        $itemdb = $this->getSaveFields($id, $item);

        $id = $this->modelAddOrUpdate($id, $itemdb);

        return $this->afterSave($success, $id, $is_new);
    }

    public function Validate($id, $item): void {
        $result = $this->validateRequired($id, $item, $this->required_fields);

        //        if ($result){
        //            if ($this->model->isExists( $item['iname'], $id ) ){
        //                $this->setError('iname', 'EXISTS');
        //            }
        //        }
        $this->validateCheckResult();
    }

    public function ShowDeleteAction($form_id): ?array {
        Users::i()->checkReadOnly();

        $id = intval($form_id);
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

    public function DeleteAction($form_id): ?array {
        $id = intval($form_id);
        $this->model->deleteWithPermanentCheck($id);

        $this->fw->flash("onedelete", 1);
        return $this->afterSave(true);
    }

    public function SaveMultiAction(): ?array {
        $acb = reqh('cb');

        $is_delete = reqs('delete') > '';
        if ($is_delete) {
            Users::i()->checkReadOnly();
        }

        $user_lists_id        = reqi("addtolist");
        $remove_user_lists_id = reqi("removefromlist");

        if ($user_lists_id) {
            $user_lists = UserLists::i()->one($user_lists_id);
            if (!$user_lists || $user_lists["add_users_id"] <> $this->fw->userId()) {
                throw new UserException("Wrong Request");
            }
        }

        $ctr = $this->saveMultiRows(array_keys($acb), $is_delete, $user_lists_id, $remove_user_lists_id);
        $this->saveMultiResult($ctr, $is_delete, $user_lists_id, $remove_user_lists_id);

        return $this->afterSaveJson(true, ['ctr' => $ctr]);
    }
} //end of class
