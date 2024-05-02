<?php
/*
AttCategories model class

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2024 Oleg Savchuk www.osalabs.com
*/

class AttCategories extends FwModel {

    public function __construct() {
        parent::__construct();

        $this->table_name = 'att_categories';
    }

    public function listSelectOptionsLikeIcode(string $icode_prefix): array {
        return $this->db->arr($this->table_name, array(
            'status' => self::STATUS_ACTIVE,
            'icode'  => $this->db->opLIKE($icode_prefix . '[_]%')
        ), 'id, iname');
    }
}
