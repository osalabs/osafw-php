<?php
/*
 UserLists model class

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2019 Oleg Savchuk www.osalabs.com
*/

class UserLists extends FwModel {

    public $table_items = 'user_lists_items';

    public function __construct() {
        parent::__construct();

        $this->table_name = 'user_lists';
    }

    #list for select by entity and for only logged user
    public function listSelectByEntity($entity){
        # code...
    }

    public function listForItem($entity, $item_id){
        # code...
    }

    public function delete($id, $is_perm = false){
        # code...
    }

    public function oneItemsByUK($user_lists_id, $item_id){
        # code...
    }

    public function deleteItems($id){
        # code...
    }

    public function addItems($user_lists_id, $item_id){
        # code...
    }

    public function toggleItemList($user_lists_id, $item_id){
        # code...
    }

    public function addItemList($user_lists_id, $item_id){
        # code...
    }

    public function delItemList($user_lists_id, $item_id){
        # code...
    }

    #TODO finish class
}

?>