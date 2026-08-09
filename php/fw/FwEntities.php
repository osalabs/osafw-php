<?php
/*
 Application Entities model class
 handles list of entities (tables) in the application
 so entities can be referenced by id in other tables
 Use idByIcodeOrAdd("icode") to get id by icode, if not exists - automatically adds new record

Part of PHP osa framework  www.osalabs.com/osafw/php
(c) 2009-2024 Oleg Savchuk www.osalabs.com
 */

class FwEntities extends FwModel {
    public const string ICODE_USERS = "users";

    public function __construct() {
        parent::__construct();

        $this->table_name = "fwentities";
    }

    //find record by icode, if not exists - add, return id (existing or newly added)
    public function idByIcodeOrAdd(string $icode): int {
        $row = $this->oneByIcode($icode);
        $id  = intval($row[$this->field_id] ?? 0);
        if (!$id) {
            $id = $this->add([
                'icode' => $icode,
                'iname' => Utils::name2human($icode)
            ]);
        }
        return $id;
    }
}
