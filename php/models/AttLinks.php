<?php
/*
AttLinks model class
*/

class AttLinks extends FwModel {
    public const string FIELD_ENTITY = "fwentities_id";

    public function __construct() {
        parent::__construct();

        $this->table_name         = 'att_links';
        $this->field_upd_time     = "";
        $this->field_upd_users_id = "";

        $this->junction_model_main      = Att::i();
        $this->junction_field_main_id   = "att_id";
        $this->junction_model_linked    = FwEntities::i();// Update to the model that you want to link to
        $this->junction_field_linked_id = "item_id";
    }

    public function oneByUK(int $att_id, int $fwentities_id, int $item_id): array {
        $where = array(
            $this->junction_field_main_id   => $att_id,
            $this->junction_field_linked_id => $item_id,
            self::FIELD_ENTITY              => $fwentities_id,
        );
        return $this->db->row($this->table_name, $where);
    }

    public function deleteByAtt(int $att_id): void {
        $where = array(
            $this->junction_field_main_id => $att_id,
        );
        $this->db->delete($this->table_name, $where);
    }

    public function setUnderUpdate(int $fwentities_id, int $item_id): void {
        $this->is_under_bulk_update = true;
        $fields                     = array(
            $this->field_status => self::STATUS_UNDER_UPDATE,
        );
        $where                      = array(
            $this->junction_field_linked_id => $item_id,
            self::FIELD_ENTITY              => $fwentities_id,
        );
        $this->db->update($this->table_name, $fields, $where);
    }

    public function deleteUnderUpdate(int $fwentities_id, int $item_id): void {
        $where = array(
            $this->junction_field_linked_id => $item_id,
            $this->field_status             => self::STATUS_UNDER_UPDATE,
        );
        $this->db->delete($this->table_name, $where);
        $this->is_under_bulk_update = false;
    }

    public function updateJunctionByKeys(string $entity_icode, int $item_id, array $att_keys): void {
        if ($att_keys == null) {
            return;
        }

        $fields = [];
        $where  = [];

        $fwentities_id = FwEntities::i()->idByIcodeOrAdd($entity_icode);

        // set all rows as under update
        $this->setUnderUpdate($fwentities_id, $item_id);

        foreach ($att_keys as $key => $value) {
            $att_id = intval($key);
            if ($att_id == 0) {
                continue; // skip non-id, ex prio_ID
            }

            $item = $this->oneByUK($att_id, $fwentities_id, $item_id);
            if (count($item) > 0) {
                // existing link
                $fields                      = array();
                $fields[$this->field_status] = self::STATUS_ACTIVE;
                $where                       = array(
                    'att_id'        => $att_id,
                    'item_id'       => $item_id,
                    'fwentities_id' => $fwentities_id,
                );
                $this->db->update($this->table_name, $fields, $where);
            } else {
                // new link
                $fields                                  = array();
                $fields[$this->junction_field_main_id]   = $att_id;
                $fields[$this->junction_field_linked_id] = $item_id;
                $fields[self::FIELD_ENTITY]              = $fwentities_id;
                $fields[$this->field_status]             = self::STATUS_ACTIVE;
                $fields[$this->field_add_users_id]       = $this->fw->userId();
                $this->db->insert($this->table_name, $fields);
            }
        }

        // remove those who still not updated (so removed)
        $this->deleteUnderUpdate($fwentities_id, $item_id);
    }
}
