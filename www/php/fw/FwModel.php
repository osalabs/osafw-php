<?php
/*
Base Fw Model class

Part of PHP osa framework  www.osalabs.com/osafw/php
(c) 2009-2024 Oleg Savchuk www.osalabs.com
 */

abstract class FwModel {
    #standard statuses
    const STATUS_ACTIVE   = 0;
    const STATUS_INACTIVE = 10;
    const STATUS_DELETED  = 127;

    public $fw; //current app/framework object
    public $table_name; //must be defined in inherited classes

    public $CACHE_PREFIX = 'fwmodel.one.'; #TODO - ability to cleanup all, but this model-only cache items
    public $CACHE_PREFIX_BYICODE = 'fwmodel.oneByIcode.';

    public $field_id = 'id'; #default primary key name
    public $field_icode = 'icode';
    public $field_iname = 'iname';
    # default field names. If you override it and make empty - automatic processing disabled
    public $field_status = 'status';
    public $field_add_users_id = 'add_users_id'; #add_users_id
    public $field_upd_users_id = 'upd_users_id'; #upd_users_id
    public $field_upd_time = ''; #upd_time, usually no need as it's updated automatically by DB via "ON UPDATE CURRENT_TIMESTAMP"

    public $is_fwevents = true; #set to false to prevent fwevents logging

    protected ?DB $db;

    /**
     * preferred alternative of fw::model(Model)->method() is Model::i()->method()
     * @return FwModel
     * @throws NoModelException
     */
    public static function i(): static {
        return fw::model(get_called_class());
    }

    public function __construct($param_fw = null) {
        if (is_null($param_fw)) {
            $this->fw = fw::i();
        } else {
            $this->fw = $param_fw;
        }
        $this->db = $this->fw->db;
    }

    /**
     * return db instance
     * @return DB db instance for the model
     */
    public function getDB() {
        return $this->db;
    }

    /**
     * by default just return model's table name. Override if more sophisticated table name builder requires
     * @return string  main table name for a model
     */
    public function getTable() {
        return $this->table_name;
    }

    //cached, pass $is_force=true to force read from db
    // Note: use removeCache($id) if you need force re-read!
    public function one($id): array {
        if (empty($id)) {
            return []; #return empty array for empty id
        }

        $cache_key = $this->CACHE_PREFIX . $this->getTable() . '*' . $id;
        $row       = FwCache::getValue($cache_key);
        if (is_null($row)) {
            $row = $this->db->row($this->getTable(), [$this->field_id => $id]);
            $this->saveCache($id, $row);
        }
        #else logger('CACHE HIT!');
        return $row;
    }

    #return one specific field for the row, uncached
    public function oneField($id, $field) {
        return $this->db->value($this->getTable(), [$this->field_id => $id], $field);
    }

    public function oneByIname($iname): array {
        $where = array(
            $this->field_iname => $iname,
        );
        if ($this->field_status > '') {
            $where[$this->field_status] = 0;
        }
        return $this->db->row($this->getTable(), $where);
    }

    public function oneByIcode(string $icode): array {
        $cache_key = $this->CACHE_PREFIX_BYICODE . $this->getTable() . '*' . $icode;
        $row       = FwCache::getValue($cache_key);
        if (is_null($row)) {
            $row = $this->db->row($this->getTable(), [$this->field_icode => $icode]);
            FwCache::setValue($cache_key, $row);
        }
        return $row;
    }

    public function listFields() {
        $rows = $this->db->arr("EXPLAIN " . dbq_ident($this->getTable()));
        foreach ($rows as $key => $row) {
            #add standard id/name fields
            $rows[$key]['id']    = $row['Field'];
            $rows[$key]['iname'] = $row['Field'];
        }

        return $rows;
    }

    # list multiple records by multiple ids
    public function listMulti(array $ids): array {
        return $this->db->arr("SELECT * from " . dbq_ident($this->getTable()) . " where " . $this->db->quote_ident($this->field_id) . $this->db->insql($ids));
    }

    public function iname($id) {
        $row = $this->one($id);
        return $row[$this->field_iname];
    }

    public function getFullName($id) {
        $result = '';

        if (!empty($id)) {
            $item   = $this->one($id);
            $result = $item[$this->field_iname];
        }

        return $result;
    }

    //add new record
    public function add($item) {
        if (!empty($this->field_add_users_id) && !isset($item[$this->field_add_users_id])) {
            $item[$this->field_add_users_id] = $this->fw->userId();
        }

        $id = $this->db->insert($this->getTable(), $item);

        $this->removeCache($id);

        if ($this->is_fwevents) {
            FwEvents::i()->logFields($this->getTable() . '_add', $id, $item);
        }

        return $id;
    }

    //update record
    public function update($id, $item) {
        if (!empty($this->field_upd_users_id) && !isset($item[$this->field_upd_users_id])) {
            $item[$this->field_upd_users_id] = $this->fw->userId();
        }

        if (!empty($this->field_upd_time) && !isset($item[$this->field_upd_time])) {
            $item[$this->field_upd_time] = DB::NOW;
        }

        $this->db->update($this->getTable(), $item, $id, $this->field_id);

        $this->removeCache($id);

        if ($this->is_fwevents) {
            FwEvents::i()->logFields($this->getTable() . '_upd', $id, $item);
        }
        return $id;
    }

    #quickly add new record just with iname
    #if such iname exists - just id returned
    #RETURN id - for new or existing record
    public function findOrAddByIname($iname, &$is_added = false) {
        $result = 0;
        $iname  = trim($iname);
        if (!strlen($iname)) {
            return 0;
        }

        $item = $this->oneByIname($iname);
        if ($item) {
            #exists
            $result = $item[$this->field_id];
        } else {
            $item     = array(
                $this->field_iname => $iname,
            );
            $result   = $this->add($item);
            $is_added = true;
        }
        return $result;
    }

    //non-permanent or permanent delete
    public function delete($id, $is_perm = null) {
        if ($is_perm || !strlen($this->field_status)) {
            $this->db->delete($this->getTable(), $id, $this->field_id);
            if ($this->is_fwevents) {
                FwEvents::i()->log($this->getTable() . '_del', $id);
            }
        } else {
            $vars = array(
                $this->field_status => 127,
            );
            $this->update($id, $vars);
        }

        $this->removeCache($id);
        return true;
    }

    //permanent delete of multiple records at once
    public function deleteMulti($ids) {
        $this->db->exec("DELETE from " . $this->db->quote_ident($this->getTable()) .
            " where " . $this->db->quote_ident($this->field_id) . $this->db->insqli($ids));

        foreach ($ids as $id) {
            $this->removeCache($id);
        }
        return true;
    }

    public function saveCache($id, $row) {
        $cache_key = $this->CACHE_PREFIX . $this->getTable() . '*' . $id;
        FwCache::setValue($cache_key, $row);
    }

    //remove from cache - can be called from outside model if model table updated
    public function removeCache($id) {
        $cache_key = $this->CACHE_PREFIX . $this->getTable() . '*' . $id;
        FwCache::remove($cache_key);

        if ($this->field_icode > '') {
            #we need to cleanup all cache keys which might be realted, so cleanup all cached icodes
            FwCache::removeWithPrefix($this->CACHE_PREFIX_BYICODE . $this->getTable() . '*');
        }
    }

    //remove all cached rows for the table
    public function removeCacheAll() {
        FwCache::removeWithPrefix($this->CACHE_PREFIX . $this->getTable() . '*');

        if ($this->field_icode > '') {
            FwCache::removeWithPrefix($this->CACHE_PREFIX_BYICODE . $this->getTable() . '*');
        }
    }

    //check if item exists for a given iname
    public function isExistsByField($uniq_key, $field, $not_id = null) {
        return $this->db->is_record_exists($this->getTable(), $uniq_key, $field, $not_id);
    }

    public function isExists($uniq_key, $not_id = null) {
        return $this->isExistsByField($uniq_key, $this->field_iname, $not_id);
    }

    #return standard list of id,iname where status=0 order by iname
    public function ilist() {
        $where = array();
        if (strlen($this->field_status)) {
            $where[$this->field_status] = 0;
        }

        $orderby = $this->field_iname > '' ? $this->db->quote_ident($this->field_iname) : null;

        return $this->db->arr($this->getTable(), $where, $orderby);
    }

    public function listSelectOptions() {
        $where = '1=1';
        if (strlen($this->field_status)) {
            $where .= " and " . $this->db->quote_ident($this->field_status) . "=0";
        }

        return $this->db->arr("SELECT " . $this->db->quote_ident($this->field_id) . " as id, " . $this->db->quote_ident($this->field_iname) . " as iname FROM " . $this->db->quote_ident($this->getTable()) . " WHERE $where ORDER BY " . $this->db->quote_ident($this->field_iname));
    }

    public function getSelectOptions($sel_id) {
        return FormUtils::selectOptions($this->listSelectOptions(), $sel_id);
    }

    public function getCount() {
        $where = '';
        if (strlen($this->field_status)) {
            $where .= " WHERE " . $this->db->quote_ident($this->field_status) . "<>127";
        }

        return $this->db->value("SELECT count(*) FROM " . $this->db->quote_ident($this->getTable()) . $where);
    }

    /**
     * return array of hashtables for multilist values with is_checked set for selected values
     * @param string|array $hsel_ids array of comma-separated string
     * @param object $params optional, to use - override in your model
     * @return array           array of hashtables for templates
     */
    public function getMultiList($hsel_ids, $params = null) {
        if (!is_array($hsel_ids)) {
            $hsel_ids = FormUtils::ids2multi($hsel_ids);
        }

        $rows = $this->ilist();
        if (count($hsel_ids)) {
            foreach ($rows as $k => $row) {
                $rows[$k]['is_checked'] = array_key_exists($row[$this->field_id], $hsel_ids) !== false;
            }
        }

        return $rows;
    }

    public function getAutocompleteList($q, $limit = 5) {
        $where = $this->db->quote_ident($this->field_iname) . " like " . $this->db->quote('%' . $q . '%');
        if (strlen($this->field_status)) {
            $where .= " and " . $this->db->quote_ident($this->field_status) . "<>127 ";
        }

        $sql = "SELECT " . $this->db->quote_ident($this->field_iname) . " as iname FROM " . $this->db->quote_ident($this->getTable()) . " WHERE " . $where . " LIMIT $limit";
        return $this->db->col($sql);
    }


    //****************** Item Upload Utils

    //simple upload of the file related to item
    public function uploadFile($id, $file) {
        $filepath = UploadUtils::uploadFile($id, $this->getUploadBaseDir(), $file);
        logger('DEBUG', "file uploaded to [$filepath]");

        UploadUtils::uploadResize($filepath, UploadUtils::$IMG_RESIZE_DEF);
        return $filepath;
    }

    public function getUploadBaseDir() {
        return UploadUtils::getUploadBaseDir() . '/' . $this->table_name;
    }

    public function getUploadBaseUrl() {
        return UploadUtils::getUploadBaseUrl() . '/' . $this->table_name;
    }


    public function getUploadDir($id) {
        return UploadUtils::getUploadDir($id, $this->getUploadBaseDir());
    }

    public function getUploadPath($id, $ext, $size = '') {
        return UploadUtils::getUploadPath($id, $this->getUploadBaseDir(), $ext, $size);
    }

    public function getUploadUrl($id, $ext, $size = '') {
        return UploadUtils::getUploadUrl($id, $this->getUploadBaseDir(), $this->getUploadBaseUrl(), $ext, $size);
    }

    public function removeUpload($id, $ext) {
        UploadUtils::cleanupUpload($id, $this->getUploadBaseDir(), $ext);
    }

} //end of class
