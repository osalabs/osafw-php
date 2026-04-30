<?php
/*
Lookup Manager Controller

Part of PHP osa framework  www.osalabs.com/osafw/php
(c) 2009-2024 Oleg Savchuk www.osalabs.com
*/

class AdminLookupManagerController extends FwAdminController {
    public string $base_url = '/Admin/LookupManager';
    public string $required_fields = 'xxx';
    public string $save_fields = 'xxx';
    public string $save_fields_checkboxes = '';
    public string $model_name = '';  #TODO LookupManagerTables
    /*REMOVE OR OVERRIDE*/
    public string $search_fields = 'iname idesc';
    public string $list_sortdef = 'iname asc';   //default sorting - req param name, asc|desc direction
    public array $list_sortmap = array(//sorting map: req param name => sql field name(s) asc|desc direction
                                       'id'       => 'id',
                                       'iname'    => 'iname',
                                       'add_time' => 'add_time',
                                       'status'   => 'status',
    );

    protected LookupManagerTables $model_tables;
    protected string $dict; // current lookup dictionary
    protected array $defs;

    public function __construct() {
        parent::__construct();

        //optionally init controller
        $this->model_tables = LookupManagerTables::i();

        $this->dict = reqs("d");
        $this->defs = $this->model_tables->oneByIcode($this->dict);
        if (!$this->defs) {
            $this->dict = "";
        } else {
            //don't allow access to tables with access_level higher than current user
            $acl = intval($this->defs["access_level"]);
            if (!Users::i()->isAccessLevel($acl)) {
                $this->dict = "";
            }
        }
    }

    public function checkDict(): void {
        if (empty($this->dict)) {
            $this->fw->redirect($this->base_url . "/(Dictionaries)");
        }
        if (!empty($this->defs["url"])) {
            $this->fw->redirect($this->defs["url"]);
        }
    }

    //override due to custom search filter on status
    public function setListSearch(): void {
        parent::setListSearch();

        if (isset($this->list_filter['status'])) {
            $this->list_where .= ' and status=' . dbqi($this->list_filter['status']);
        }
    }

    //override if necessary: IndexAction, ShowAction, ShowFormAction, Validate, DeleteAction, Export, SaveMultiAction
    //or override just: setListSearch, set_list_rows, getSaveFields

    public function DictionariesAction(): array {
        $ps = [];

        $columns  = 4;
        $tables   = $this->model_tables->ilist();
        $max_rows = ceil(count($tables) / $columns);
        $cols     = [];

        // add rows
        $curcol = 0;
        foreach ($tables as $table) {
            //do not show tables with access_level higher than current user
            $acl = intval($table["access_level"]);
            if (!Users::i()->isAccessLevel($acl)) {
                continue;
            }

            if (count($cols) <= $curcol) {
                $cols[] = [
                    "col_sm"    => floor(12 / $columns),
                    "list_rows" => [],
                ];
            }
            $al   = &$cols[$curcol]["list_rows"];
            $al[] = $table;
            if (count($al) >= $max_rows) {
                $curcol += 1;
            }
        }


        $ps = [
            "list_cols" => $cols,
        ];
        return $ps;
    }

    public function IndexAction(): ?array {
        $this->checkDict();

        throw new ApplicationException("Not Implemented", 501);
    }

    public function ShowAction($form_id): ?array {
        $this->checkDict();

        throw new ApplicationException("Not Implemented", 501);
    }

    public function ShowFormAction($form_id): ?array {
        $this->checkDict();

        throw new ApplicationException("Not Implemented", 501);
    }

    public function SaveAction($form_id): ?array {
        $this->checkDict();

        throw new ApplicationException("Not Implemented", 501);
    }

    public function Validate($id, $item): void {
        $this->checkDict();

        throw new ApplicationException("Not Implemented", 501);
    }

    public function DeleteAction($form_id): array|null {
        $this->checkDict();

        throw new ApplicationException("Not Implemented", 501);
    }

}//end of class
