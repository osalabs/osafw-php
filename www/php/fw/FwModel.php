<?php
/*
Base Fw Model class

Part of PHP osa framework  www.osalabs.com/osafw/php
(c) 2009-2024 Oleg Savchuk www.osalabs.com
 */

abstract class FwModel {
    #standard statuses
    const int STATUS_ACTIVE       = 0;
    const int STATUS_UNDER_UPDATE = 1;
    const int STATUS_INACTIVE     = 10;
    const int STATUS_DELETED      = 127;

    protected FW $fw; //current app/framework object
    protected ?DB $db;

    public string $table_name; //must be defined in inherited classes
    public string $csv_export_fields = ""; // all or Utils.qw format
    public string $csv_export_headers = ""; // comma-separated format


    public string $field_id = 'id'; #default primary key name
    public string $field_icode = 'icode';
    public string $field_iname = 'iname';
    # default field names. If you override it and make empty - automatic processing disabled
    public string $field_status = 'status';
    public string $field_add_users_id = 'add_users_id';
    public string $field_upd_users_id = 'upd_users_id';
    public string $field_upd_time = ''; #upd_time, usually no need as it's updated automatically by DB via "ON UPDATE CURRENT_TIMESTAMP"
    public string $field_prio = '';
    public bool $is_normalize_names = false; // if true - Utils.name2fw() will be called for all fetched rows to normalize names (no spaces or special chars)

    public bool $is_log_changes = true; // if true - event_log record added on add/update/delete
    public bool $is_log_fields_changed = true; // if true - event_log.fields filled with changes
    public bool $is_under_bulk_update = false; // true when perform bulk updates like modelAddOrUpdateSubtableDynamic (disables log changes for status)

    // for junction models like UsersCompanies that link 2 tables via junction table, ex users_companies
    public FwModel $junction_model_main;   // main model (first entity), initialize in init(), ex fw.model<Users>()
    public string $junction_field_main_id; // id field name for main, ex users_id
    public FwModel $junction_model_linked;   // linked model (second entity), initialize in init()
    public string $junction_field_linked_id; // id field name for linked, ex companies_id
    public string $junction_field_status; // custom junction status field name, using this.field_status if not set

    protected string $cache_prefix = 'fwmodel.one.'; #TODO - ability to cleanup all, but this model-only cache items
    protected string $cache_prefix_bycode = 'fwmodel.oneByIcode.';

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

        $this->cache_prefix        = $this->cache_prefix . get_called_class() . '*';
        $this->cache_prefix_bycode = $this->cache_prefix_bycode . get_called_class() . '*';
    }

    /**
     * return db instance
     * @return DB|null db instance for the model
     */
    public function getDB(): ?DB {
        return $this->db;
    }

    /**
     * by default just return model's table name. Override if more sophisticated table name builder requires
     * @return string  main table name for a model
     */
    public function getTable(): string {
        return $this->table_name;
    }

    /**
     * return quoted table name for the model
     * @return string
     */
    public function qTable(): string {
        return $this->db->qident($this->getTable());
    }

    /**
     * standard stub for check access for particular record
     * @param string|int $id
     * @param string $action specific action code to check like view or edit
     * @return bool
     * @throws ApplicationException
     */
    public function isAccess(string|int $id = 0, string $action = ''): bool {
        throw new ApplicationException("Not implemented");
    }

    /**
     * shortcut for isAccess with throwing AuthException if no access
     * @param string|int $id
     * @param string $action
     * @return void
     * @throws ApplicationException
     * @throws AuthException
     */
    public function checkAccess(string|int $id = 0, string $action = ""): void {
        if (!$this->isAccess($id, $action)) {
            throw new AuthException();
        }
    }

    //cached, pass $is_force=true to force read from db
    // Note: use removeCache($id) if you need force re-read!
    public function one(string|int $id): array {
        if (empty($id)) {
            return []; #return empty array for empty id
        }

        $cache_key = $this->cache_prefix . $id;
        $row       = FwCache::getValue($cache_key);
        if (is_null($row)) {
            $row = $this->db->row($this->getTable(), [$this->field_id => $id]);
            $this->normalizeNames($row);
            $this->saveCache($id, $row);
        }
        #else logger('CACHE HIT!');
        return $row;
    }

    #return one specific field for the row, uncached
    public function oneField(string|int $id, $field): ?string {
        return $this->db->value($this->getTable(), [$this->field_id => $id], $field);
    }

    public function oneByIname(string $iname): array {
        $where = array(
            $this->field_iname => $iname,
        );
        if ($this->field_status > '') {
            $where[$this->field_status] = 0;
        }
        $row = $this->db->row($this->getTable(), $where);
        $this->normalizeNames($row);
        return $row;
    }

    public function oneByIcode(string $icode): array {
        $cache_key = $this->cache_prefix_bycode . $icode;
        $row       = FwCache::getValue($cache_key);
        if (is_null($row)) {
            $row = $this->db->row($this->getTable(), [$this->field_icode => $icode]);
            $this->normalizeNames($row);
            FwCache::setValue($cache_key, $row);

            #also save to cache by id
            if ($row) {
                $this->saveCache($row[$this->field_id], $row);
            }
        }
        return $row;
    }

    public function listFields(): array {
        $rows = $this->db->arr("EXPLAIN " . $this->qTable());
        foreach ($rows as $key => $row) {
            #add standard id/name fields
            $rows[$key]['id']    = $row['Field'];
            $rows[$key]['iname'] = $row['Field'];
        }

        return $rows;
    }

    # list multiple records by multiple ids
    public function listMulti(array $ids): array {
        return $this->db->arr("SELECT * from " . $this->qTable() . " where " . $this->db->qident($this->field_id) . $this->db->insql($ids));
    }

    // add renamed fields For template engine - spaces and special chars replaced With "_" and other normalizations
    public function normalizeNames(array &$row): void {
        if (!$this->is_normalize_names || !$row) {
            return;
        }

        foreach ($row as $key => $val) {
            $row[Utils::name2fw($key)] = $val;
        }

        //add id field if not exists
        if ($this->field_id && !isset($row['id'])) {
            $row['id'] = $row[$this->field_id];
        }
    }

    public function iname(string|int $id): string {
        $row = $this->one($id);
        return $row[$this->field_iname];
    }

    /**
     * find record by iname, if not exists - add, return id (existing or newly added)
     * @param string $iname
     * @return int
     */
    public function idByInameOrAdd(string $iname): int {
        $row = $this->oneByIname($iname);
        $id  = intval($row[$this->field_id]);
        if ($id == 0) {
            $id = $this->add([$this->field_iname => $iname]);
        }
        return $id;
    }

    /**
     * add new record just with iname
     * if such iname exists - existing id returned
     * @param string $iname
     * @param bool $is_added return true if new record added
     * @return int
     */
    public function findOrAddByIname(string $iname, bool &$is_added = false): int {
        $iname = trim($iname);
        if (!strlen($iname)) {
            return 0;
        }

        $item = $this->oneByIname($iname);
        if ($item) {
            #exists
            $result = intval($item[$this->field_id]);
        } else {
            $item     = array(
                $this->field_iname => $iname,
            );
            $result   = $this->add($item);
            $is_added = true;
        }
        return $result;
    }

    /**
     * return ORDER BY string for the model
     * default order is iname asc
     * or if prio column exists - prio asc, iname asc
     * @return string
     */
    public function getOrderBy(): string {
        $result = $this->field_iname;
        if (strlen($this->field_prio)) {
            $result = $this->db->qident($this->field_prio) . ", " . $this->db->qident($this->field_iname);
        }
        return $result;
    }

    /**
     * return standard list of id,iname for all non-deleted OR wtih specified statuses order by by getOrderBy
     * @param array|null $statuses
     * @return array
     */
    public function ilist(array $statuses = null): array {
        $where = '';
        if (strlen($this->field_status)) {
            if ($statuses && count($statuses) > 0) {
                $where .= " and " . $this->db->qident($this->field_status) . $this->db->insqli($statuses);
            } else {
                $where .= " and " . $this->db->qident($this->field_status) . ' != ' . dbqi(self::STATUS_DELETED);
            }
        }

        $orderby = $this->field_iname > '' ? $this->db->qident($this->field_iname) : null;

        return $this->db->arr("SELECT * from " . $this->qTable() . " WHERE 1=1 $where ORDER BY $orderby");
    }

    /**
     * return total count of all non-deleted rows
     * @return int
     */
    public function getCount(): int {
        $where = '';
        if (strlen($this->field_status)) {
            $where .= " WHERE " . $this->db->qident($this->field_status) . "<>127";
        }

        return intval($this->db->value("SELECT count(*) FROM " . $this->qTable() . $where));
    }

    //check if item exists for a given iname
    public function isExistsByField($uniq_key, $field, $not_id = null): bool {
        return $this->db->is_record_exists($this->getTable(), $uniq_key, $field, $not_id);
    }

    public function isExists($uniq_key, $not_id = null): bool {
        return $this->isExistsByField($uniq_key, $this->field_iname, $not_id);
    }


    //add new record
    public function add($item): int {
        if (!empty($this->field_add_users_id) && !isset($item[$this->field_add_users_id]) && $this->fw->isLogged()) {
            $item[$this->field_add_users_id] = $this->fw->userId();
        }

        $id = $this->db->insert($this->getTable(), $item);

        $this->removeCache($id);

        if ($this->is_log_changes) {
            if ($this->is_log_fields_changed) {
                $this->fw->logActivity(FwLogTypes::ICODE_ADDED, $this->getTable(), $id, "", $item);
            } else {
                $this->fw->logActivity(FwLogTypes::ICODE_ADDED, $this->getTable(), $id);
            }
        }

        if (!empty($this->field_prio) && !isset($item[$this->field_prio])) {
            //if priority field defined - update it with newly added id to allow proper re/ordering
            $this->db->update($this->getTable(), [$this->field_prio => $id], $id, $this->field_id);
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

        if ($this->is_log_changes) {
            FwEvents::i()->logFields($this->getTable() . '_upd', $id, $item);
        }
        return $id;
    }

    //non-permanent or permanent delete
    public function delete($id, $is_perm = null): bool {
        if ($is_perm || !strlen($this->field_status)) {
            $this->db->delete($this->getTable(), $id, $this->field_id);
            if ($this->is_log_changes) {
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
    public function deleteMulti($ids): true {
        $this->db->exec("DELETE from " . $this->qTable() .
            " where " . $this->db->qident($this->field_id) . $this->db->insqli($ids));

        foreach ($ids as $id) {
            $this->removeCache($id);
        }
        return true;
    }

    public function saveCache($id, $row): void {
        $cache_key = $this->cache_prefix . $id;
        FwCache::setValue($cache_key, $row);
    }

    //remove from cache - can be called from outside model if model table updated
    public function removeCache($id): void {
        $cache_key = $this->cache_prefix . $id;
        FwCache::remove($cache_key);

        if ($this->field_icode > '') {
            #we need to cleanup all cache keys which might be realted, so cleanup all cached icodes
            FwCache::removeWithPrefix($this->cache_prefix_bycode);
        }
    }

    //remove all cached rows for the table
    public function removeCacheAll(): void {
        FwCache::removeWithPrefix($this->cache_prefix);

        if ($this->field_icode > '') {
            FwCache::removeWithPrefix($this->cache_prefix_bycode);
        }
    }

    public function listSelectOptions(): array {
        $where = '1=1';
        if (strlen($this->field_status)) {
            $where .= " and " . $this->db->qident($this->field_status) . "=0";
        }

        return $this->db->arr("SELECT " . $this->db->qident($this->field_id) . " as id, " . $this->db->qident($this->field_iname) . " as iname FROM " . $this->qTable() .
            " WHERE $where ORDER BY " . $this->db->qident($this->field_iname));
    }

    public function getSelectOptions($sel_id): string {
        return FormUtils::selectOptions($this->listSelectOptions(), $sel_id);
    }


    public function setMultiListChecked(array $rows, array $ids, object $def = null): array {
        $result          = $rows;
        $is_checked_only = $def['lookup_checked_only'] ?? false;

        if (count($ids) > 0) {
            foreach ($rows as $k => $row) {
                $rows[$k]['is_checked'] = in_array($row[$this->field_id], $ids);
            }

            // now sort so checked values will be at the top
            $result = [];
            if ($is_checked_only) {
                foreach ($rows as $row) {
                    if ($row['is_checked']) {
                        $result[] = $row;
                    }
                }
            } else {
                usort($rows, function ($a, $b) {
                    return $b['is_checked'] - $a['is_checked'];
                });
                $result = $rows;
            }
        } elseif ($is_checked_only) {
            // return no items if no checked
            $result = [];
        }

        return $result;
    }


    /**
     * list rows and add is_checked=true flag for selected ids, sort by is_checked desc
     * @param array|string $hsel_ids array or comma-separated string - selected ids from the list()
     * @param object|null $def def - in dynamic controller - field definition (also contains "i" and "ps", "lookup_params", ...) or you could use it to pass additional params
     * @return array           array of hashtables for templates
     */
    public function listWithChecked(array|string $hsel_ids, object $def = null): array {
        if (!is_array($hsel_ids)) {
            $hsel_ids = FormUtils::ids2multi($hsel_ids);
        }
        $rows = $this->setMultiListChecked($this->ilist(), $hsel_ids, $def);
        return $rows;
    }

    public function getAutocompleteList($q, $limit = 5): array {
        $where = $this->db->qident($this->field_iname) . " like " . $this->db->quote('%' . $q . '%');
        if (strlen($this->field_status)) {
            $where .= " and " . $this->db->qident($this->field_status) . "<>127 ";
        }

        $sql = "SELECT " . $this->db->qident($this->field_iname) . " as iname FROM " . $this->qTable() . " WHERE " . $where . " LIMIT $limit";
        return $this->db->col($sql);
    }


    //****************** Item Upload Utils

    //simple upload of the file related to item
    public function uploadFile($id, $file): string {
        $filepath = UploadUtils::uploadFile($id, $this->getUploadBaseDir(), $file);
        logger('DEBUG', "file uploaded to [$filepath]");

        UploadUtils::uploadResize($filepath, UploadUtils::$IMG_RESIZE_DEF);
        return $filepath;
    }

    public function getUploadBaseDir(): string {
        return UploadUtils::getUploadBaseDir() . '/' . $this->table_name;
    }

    public function getUploadBaseUrl(): string {
        return UploadUtils::getUploadBaseUrl() . '/' . $this->table_name;
    }


    public function getUploadDir($id): string {
        return UploadUtils::getUploadDir($id, $this->getUploadBaseDir());
    }

    public function getUploadPath($id, $ext, $size = ''): string {
        return UploadUtils::getUploadPath($id, $this->getUploadBaseDir(), $ext, $size);
    }

    public function getUploadUrl($id, $ext, $size = ''): string {
        return UploadUtils::getUploadUrl($id, $this->getUploadBaseDir(), $this->getUploadBaseUrl(), $ext, $size);
    }

    public function removeUpload($id, $ext): void {
        UploadUtils::cleanupUpload($id, $this->getUploadBaseDir(), $ext);
    }

} //end of class
