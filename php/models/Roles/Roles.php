<?php
/*
 Roles model class

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2024 Oleg Savchuk www.osalabs.com
*/

class Roles extends FwModel {

    public const string INAME_VISITOR = "visitor";

    public function __construct() {
        parent::__construct();

        $this->table_name = 'roles';
        $this->field_prio = 'prio';
    }

    public function idVisitor(): int {
        return intval($this->oneByIname(self::INAME_VISITOR)[$this->field_id]);
    }

}
