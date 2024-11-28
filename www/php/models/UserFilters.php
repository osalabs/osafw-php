<?php
/*
 // UserFilters model class

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2024 Oleg Savchuk www.osalabs.com
*/

class UserFilters extends FwModel {

    public function __construct() {
        parent::__construct();

        $this->table_name     = 'user_filters';
        $this->is_log_changes = false; #no need to log changes here
    }

    public function listSelectByIcode(string $icode): array {
        return $this->db->arrp("select id, iname from " . $this->db->qid($this->table_name) .
            " where status=0 and icode=@icode
                     and (is_system=1 OR add_users_id=@users_id)
                   order by is_system desc, iname", ['@icode' => $icode, '@users_id' => $this->fw->userId()]);
    }

}
