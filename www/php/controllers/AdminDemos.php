<?php
/*
Demo Admin Controller

Part of PHP osa framework  www.osalabs.com/osafw/php
(c) 2009-2024 Oleg Savchuk www.osalabs.com
*/

class AdminDemosController extends FwAdminController {
    const int access_level = Users::ACL_MANAGER;

    public FwModel|Demos $model;
    public string $model_name = 'Demos';

    public string $base_url = '/Admin/Demos';
    public string $required_fields = 'iname email';
    public string $save_fields = 'parent_id demo_dicts_id iname idesc email fint ffloat fcombo fradio fyesno fdate_pop fdatetime dict_link_multi att_id status';
    public string $save_fields_checkboxes = 'is_checkbox';
    public string $save_fields_nullable = 'demo_dicts_id att_id fdate_pop fdatetime';

    public $model_related;

    /*REMOVE OR OVERRIDE*/
    public string $related_field_name = 'demo_dicts_id';
    public string $search_fields = 'iname idesc';
    public string $list_sortdef = 'iname asc';   //default sorting - req param name, asc|desc direction
    public array $list_sortmap = array(//sorting map: req param name => sql field name(s) asc|desc direction
                                       'id'            => 'id',
                                       'iname'         => 'iname',
                                       'add_time'      => 'add_time',
                                       'demo_dicts_id' => 'demo_dicts_id',
                                       'email'         => 'email',
                                       'status'        => 'status',
    );
    public array $form_new_defaults = array(
        'fint'   => 0,
        'ffloat' => 0,
    );

    public function __construct() {
        parent::__construct();

        $this->model_related = DemoDicts::i();
    }

    //override due to custom search filter on status
    public function setListSearch(): void {
        parent::setListSearch();

        if ($this->list_filter['status'] > '') {
            $this->list_where .= ' and status=' . dbqi($this->list_filter['status']);
        }
    }

    // override get list rows as list need to be modified
    public function getListRows(): void {
        parent::getListRows();

        #add/modify rows from db
        foreach ($this->list_rows as $k => $row) {
            $this->list_rows[$k]['demo_dicts'] = $this->model_related->one($row['demo_dicts_id']);
        }
    }

    //View item screen
    public function ShowAction($form_id): ?array {
        $ps   = parent::ShowAction($form_id);
        $item = $ps['i'];
        $id   = intval($item['id']);

        $item["ftime_str"] = DateUtils::int2timestr($item["ftime"]);

        $ps = array_merge($ps, array(
            'i'                  => $item,
            'parent'             => $this->model->one($item['parent_id']),
            'demo_dicts'         => $this->model_related->one($item['demo_dicts_id']),
            'dict_link_auto'     => $this->model_related->one($item['dict_link_auto_id']),
            'multi_datarow'      => $this->model_related->listWithChecked($item['dict_link_multi']),
            'multi_datarow_link' => DemosDemoDicts::i()->listLinkedByMainId($id),
            'att'                => Att::i()->one($item['att_id']),
            'att_links'          => Att::i()->listLinked($this->model->table_name, $id),
        ));

        if ($this->is_activity_logs) {
            $this->initFilter();
            $ps["list_filter"]["tab_activity"] = $ps["list_filter"]["tab_activity"] ?? FwActivityLogs::TAB_COMMENTS;
            $ps["activity_entity"]             = $this->model->table_name;
            $ps["activity_rows"]               = FwActivityLogs::i()->listByEntityForUI($this->model->table_name, $id, $ps["list_filter"]["tab_activity"]);
        }

        return $ps;
    }

    /*
     *     public override Hashtable ShowFormAction(int id = 0)
        {
            // Me.form_new_defaults = New Hashtable 'set new form defaults here if any
            // Me.form_new_defaults = reqh("item") 'OR optionally set defaults from request params
            // item["field"]="default value"
            Hashtable ps = base.ShowFormAction(id);

            // read dropdowns lists from db
            var item = (Hashtable)ps["i"];
            ps["select_options_parent_id"] = model.listSelectOptionsParent();
            ps["select_options_demo_dicts_id"] = model_related.listSelectOptions();
            ps["dict_link_auto_id_iname"] = model_related.iname(item["dict_link_auto_id"]);
            ps["multi_datarow"] = model_related.listWithChecked((string)item["dict_link_multi"]);
            ps["multi_datarow_link"] = fw.model<DemosDemoDicts>().listLinkedByMainId(id);
            FormUtils.comboForDate((string)item["fdate_combo"], ps, "fdate_combo");

            ps["att"] = fw.model<Att>().one(Utils.f2int(item["att_id"])).toHashtable();
            ps["att_links"] = fw.model<Att>().listLinked(model.table_name, id);

            return ps;
        }

     * */
    public function ShowFormAction($form_id): ?array {
        $ps   = parent::ShowFormAction($form_id);
        $id   = intval($ps['id']);
        $item = $ps['i'];

        $ps = array_merge($ps, array(
            'select_options_parent_id'     => $this->model->listSelectOptionsParent(),
            'select_options_demo_dicts_id' => $this->model_related->listSelectOptions(),
            'dict_link_auto_id_iname'      => $this->model_related->iname($item['dict_link_auto_id'] ?? 0),
            'multi_datarow'                => $this->model_related->listWithChecked($item['dict_link_multi'] ?? ''),
            'multi_datarow_link'           => DemosDemoDicts::i()->listLinkedByMainId($id),
            'att'                          => Att::i()->one($item['att_id']),
            'att_links'                    => Att::i()->listLinked($this->model->table_name, $id),
        ));
        FormUtils::comboForDate($item['fdate_combo'] ?? '', $ps, 'fdate_combo');

        return $ps;
    }

    // override to modify some fields before save
    public function getSaveFields($id, $item): array {
        #load old record if necessary
        #$item_old = $this->model->one($id);

        $itemdb['dict_link_auto_id'] = $this->model_related->findOrAddByIname($item['dict_link_auto_id_iname']);
        $itemdb['dict_link_multi']   = FormUtils::multi2ids(reqh('dict_link_multi'));
        $itemdb["fdate_combo"]       = FormUtils::dateForCombo($item, "fdate_combo");
        $itemdb['fdate_pop']         = DateUtils::Str2SQL($item['fdate_pop']);
        $itemdb['ftime']             = DateUtils::timestr2int($item['ftime_str']); #ftime - convert from HH:MM to int (0-24h in seconds)

        $itemdb = parent::getSaveFields($id, $item);

        return $itemdb;
    }

    // override because we need to update att links
    public function modelAddOrUpdate(int $id, array $fields): int {
        $id = parent::modelAddOrUpdate($id, $fields);

        DemosDemoDicts::i()->updateJunctionByMainId($id, reqh('demo_dicts_link'));
        #TODO AttLinks::i()->updateJunction($this->model->table_name, ;id, reqh("att"));
        #Att::i()->updateAttLinks($this->model->table_name, $id, reqh('att'));

        return $id;
    }

    public function Validate($id, $item): void {
        $result = $this->validateRequired($item, $this->required_fields);

        //check $result here used only to disable further validation if required fields validation failed
        if ($result) {
            if ($this->model->isExistsByField($item['email'], 'email', $id)) {
                $this->setError('email', 'EXISTS');
            }

            if (!FormUtils::isEmail($item['email'])) {
                $this->setError('email', 'WRONG');
            }
        }

        $this->validateCheckResult();
    }

    public function AutocompleteAction(): array {
        $query = reqs('q');

        return array(
            '_json' => $this->model_related->listAutocomplete($query),
        );
    }

}//end of class
