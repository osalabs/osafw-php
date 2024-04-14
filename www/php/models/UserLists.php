<?php
/*
 UserLists model class

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2024 Oleg Savchuk www.osalabs.com
*/

class UserLists extends FwModel {
    #TODO add here your entities or just use list_view in controllers
    #const ENTITY_DEMOS = "demos";

    public $table_items = 'user_lists_items';

    public function __construct() {
        parent::__construct();

        $this->table_name = 'user_lists';
    }

    #list for select by entity and for only logged user
    public function listSelectByEntity($entity): array {
        $where = [
            'status'       => 0,
            'entity'       => $entity,
            'add_users_id' => $this->fw->userId()
        ];
        return $this->db->arr($this->getTable(), $where, 'iname', '', ['id', 'iname']);
    }

    public function listForItem($entity, $item_id): array {
        return $this->db->arrp("SELECT t.id, t.iname, " . dbqi($item_id) . " as item_id, ti.id as is_checked FROM $this->table_name t
                        LEFT OUTER JOIN $this->table_items ti ON (ti.user_lists_id=t.id and ti.item_id=@item_id )
                        WHERE t.status=0 and t.entity=@entity
                        and t.add_users_id=@users_id
                        ORDER BY t.iname",
            [
                'item_id'  => $item_id,
                'entity'   => $entity,
                'users_id' => $this->fw->userId()

            ]);
    }

    public function delete($id, $is_perm = false): bool {
        if ($is_perm) {
            #delete list items first
            $this->db->delete($this->table_items, $id);
        }

        return parent::delete($id, $is_perm);
    }

    public function oneItemsByUK($user_lists_id, $item_id) {
        return $this->db->row($this->table_items, array("user_lists_id" => $user_lists_id, "item_id" => $item_id));
    }

    public function deleteItems($id) {
        $this->db->delete($this->table_items, $id);
        FwEvents::i()->log($this->table_items . '_del', $id);
    }

    #add new record and return new record id
    public function addItems($user_lists_id, $item_id) {
        $item = array(
            "user_lists_id" => $user_lists_id,
            "item_id"       => $item_id,
            "add_users_id"  => $this->fw->userId()
        );
        $id   = $this->db->insert($this->table_items, $item);
        FwEvents::i()->log($this->table_items . '_add', $id);
        return $id;
    }

    #add or remove item from the list
    public function toggleItemList($user_lists_id, $item_id) {
        $result = false;
        $litem  = $this->oneItemsByUK($user_lists_id, $item_id);
        if ($litem) {
            #remove
            $this->deleteItems($litem["id"]);
        } else {
            #add new
            $this->addItems($user_lists_id, $item_id);
            $result = true;
        }

        return $result;
    }

    #add item to the list, if item not yet in the list
    public function addItemList($user_lists_id, $item_id) {
        $result = false;
        $litem  = $this->oneItemsByUK($user_lists_id, $item_id);
        if ($litem) {
            #do nothing
        } else {
            #add new
            $this->addItems($user_lists_id, $item_id);
            $result = true;
        }

        return $result;
    }

    public function delItemList($user_lists_id, $item_id) {
        $result = false;
        $litem  = $this->oneItemsByUK($user_lists_id, $item_id);
        if ($litem) {
            $this->deleteItems($litem["id"]);
            $result = true;
        }

        return $result;
    }

}
