<?php
/*
 Admin Site Settings Controller class

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2024 Oleg Savchuk www.osalabs.com
*/

class AdminSettingsController extends FwAdminController {
    public FwModel|Settings $model;
    public string $model_name = 'Settings';

    public string $base_url = '/Admin/Settings';
    public string $required_fields = 'ivalue';
    public string $save_fields = 'ivalue';


    public string $search_fields = 'icode iname ivalue';
    public string $list_sortdef = 'iname asc';   //default sorting - req param name, asc|desc direction
    public array $list_sortmap = array(//sorting map: req param name => sql field name(s) asc|desc direction
                                       'id'       => 'id',
                                       'iname'    => 'iname',
                                       'upd_time' => 'upd_time',
    );

    public function __construct() {
        parent::__construct();
    }

    public function IndexAction(): ?array {
        #get filters from the search form
        $f = $this->initFilter();

        $this->setListSorting();
        $this->setListSearch();

        //other filters add to $this->list_where here
        //if search - no category
        if ($f['s'] == '' && isset($f['icat'])) {
            $this->list_where .= ' and icat=' . $this->db->quote($f['icat']);
        }

        $this->getListRows();
        //add/modify rows from db
        /*
        foreach ($this->list_rows as $k => $row) {
            $this->list_rows[$k]['field'] = 'value';
        }
        */
        $ps = array(
            'list_rows' => $this->list_rows,
            'count'     => $this->list_count,
            'pager'     => $this->list_pager,
            'f'         => $this->list_filter,
        );

        return $ps;
    }

    public function SaveAction($form_id): ?array {
        $id   = intval($form_id);
        $item = reqh('item');

        try {
            $this->Validate($id, $item);
            #load old record if necessary
            #$item_old = $this->model->one($id);

            $itemdb = FormUtils::filter($item, $this->save_fields);
            #TODO - checkboxes support
            #FormUtils::filterCheckboxes($itemdb, $item, 'is_checkbox');

            $id = $this->model->update($id, $itemdb);

            #TODO cleanup any caches that depends on settings
            #$this->fw->cache->remove("XXX");

            fw::redirect($this->base_url . '/' . $id . '/edit');

        } catch (ApplicationException $ex) {
            $this->setFormError($ex);
            $this->routeRedirect("ShowForm");
        }
        return null;
    }

    public function Validate($id, $item): void {
        $result = $this->validateRequired($item, $this->required_fields);

        if ($id == 0) {
            throw new ApplicationException("Wrong Settings ID");
        }

        $this->validateCheckResult();
    }

}//end of class
