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
    public string|array $csv_export_fields = ""; // fields to export - string for qh or hashtable - [fieldname => field name in header], default - all export fields


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


    const string CACHE_PREFIX_DEFAULT        = 'fwmodel.one.';
    const string CACHE_PREFIX_BYCODE_DEFAULT = 'fwmodel.oneByIcode.';
    protected string $cache_prefix;
    protected string $cache_prefix_bycode;

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

        $this->cache_prefix        = self::CACHE_PREFIX_DEFAULT . get_called_class() . '*';
        $this->cache_prefix_bycode = self::CACHE_PREFIX_BYCODE_DEFAULT . get_called_class() . '*';
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

    //<editor-fold desc="basic CRUD one, list, multi, add, update, delete and related helpers">

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

    /**
     * check if item exists for a given fields and their values, commonly used in junction tables
     * @param array $fields
     * @param int $not_id
     * @return bool
     */
    public function isExistsByFields(array $fields, int $not_id): bool {
        $where = [];
        foreach ($fields as $key => $val) {
            $where[$key] = $val;
        }

        if (strlen($this->field_id)) {
            $where[$this->field_id] = $this->db->opNOT($not_id);
        }
        $val = $this->db->value($this->getTable(), $where, "1");
        return $val == "1";
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

    /**
     * update record
     * @param int $id
     * @param array $item
     * @return bool
     */
    public function update(int $id, array $item): bool {
        $item_changes = [];
        if ($this->is_log_changes) {
            $item_old     = $this->one($id);
            $item_compare = $item;
            if ($this->is_under_bulk_update) {
                // when under bulk update - as existing items updated from status=1 to 0
                // so we only need to check if other fields changed, not status
                unset($item_compare[$this->field_status]);
            }
            $item_changes = FormUtils::changesOnly($item_compare, $item_old);
        }

        if (!empty($this->field_upd_time)) {
            $item[$this->field_upd_time] = DB::NOW();
        }
        if (!empty($this->field_upd_users_id) && !isset($item[$this->field_upd_users_id]) && $this->fw->isLogged()) {
            $item[$this->field_upd_users_id] = $this->fw->userId();
        }

        $where = [$this->field_id => $id];
        $this->db->update($this->getTable(), $item, $where);

        $this->removeCache($id); // cleanup cache, so next one read will read new value

        if ($this->is_log_changes && count($item_changes) > 0) {
            if ($this->is_log_fields_changed) {
                $this->fw->logActivity(FwLogTypes::ICODE_UPDATED, $this->getTable(), $id, "", $item_changes);
            } else {
                $this->fw->logActivity(FwLogTypes::ICODE_UPDATED, $this->getTable(), $id);
            }
        }
        return true;
    }

    /**
     * non-permanent or permanent delete
     * mark record as deleted (status=127) OR actually delete from db (if is_perm or status field not defined for this model table)
     * @param int $id
     * @param bool $is_perm
     * @return bool
     */
    public function delete(int $id, bool $is_perm = false): bool {
        if ($is_perm || !strlen($this->field_status)) {
            // place here code that remove related data

            $this->db->delete($this->getTable(), $id, $this->field_id);
            $this->removeCache($id);

            if ($this->is_log_changes) {
                $this->fw->logActivity(FwLogTypes::ICODE_DELETED, $this->getTable(), $id);
            }
        } else {
            $this->update($id, [$this->field_status => self::STATUS_DELETED]);
        }
        return true;
    }

    /**
     * delete record with permanent check
     * @param int $id
     * @return void
     * @throws NoModelException
     */
    public function deleteWithPermanentCheck(int $id): void {
        if (Users::i()->isAccessLevel(Users::ACL_ADMIN)
            && !empty($this->field_status)
            && intval($this->one($id)[$this->field_status]) == self::STATUS_DELETED) {
            $this->delete($id, true);
        } else {
            $this->delete($id);
        }
    }

    /**
     * permanent direct delete of multiple records at once
     * @param array $ids
     * @return true
     * @throws DBException
     */
    public function deleteMulti(array $ids): true {
        $this->db->exec("DELETE from " . $this->qTable() .
            " where " . $this->db->qident($this->field_id) . $this->db->insqli($ids));

        foreach ($ids as $id) {
            $this->removeCache($id);
        }
        return true;
    }
    //</editor-fold>

    //<editor-fold desc="cache">
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

    //</editor-fold>

    //<editor-fold desc="Upload Utils">
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

    //</editor-fold>

    //<editor-fold desc="select options and autocomplete">

    /**
     * return db array of id, iname for select options
     * @return array
     */
    public function listSelectOptions(): array {
        $where = '';
        if (strlen($this->field_status)) {
            $where = " WHERE " . $this->db->qident($this->field_status) . "<>" . dbqi(self::STATUS_DELETED);
        }

        return $this->db->arr("SELECT " . $this->db->qident($this->field_id) . " as id, " . $this->db->qident($this->field_iname) . " as iname 
            FROM " . $this->qTable() .
            " $where ORDER BY " . $this->getOrderBy());
    }

    /**
     * similar to listSelectOptions but returns iname/iname
     * @return array
     */
    public function listSelectOptionsName(): array {
        $where = '';
        if (strlen($this->field_status)) {
            $where = " WHERE " . $this->db->qident($this->field_status) . "<>" . dbqi(self::STATUS_DELETED);
        }

        return $this->db->arr("SELECT " . $this->db->qident($this->field_iname) . " as id, " . $this->db->qident($this->field_iname) . " as iname 
            FROM " . $this->qTable() .
            " $where ORDER BY " . $this->getOrderBy());
    }

    /**
     * return db array of iname for autocomplete
     * @param string $q
     * @param int $limit
     * @return array
     * @throws DBException
     */
    public function listAutocomplete(string $q, int $limit = 5): array {
        $where = $this->db->qident($this->field_iname) . " like " . $this->db->quote('%' . $q . '%');
        if (strlen($this->field_status)) {
            $where .= " and " . $this->db->qident($this->field_status) . "<>" . dbqi(self::STATUS_DELETED);
        }

        $sql = "SELECT " . $this->db->qident($this->field_iname) . " as iname FROM " . $this->qTable() . " WHERE " . $where . " LIMIT $limit";
        return $this->db->col($sql);
    }

    //</editor-fold>


    //<editor-fold desc="support for junction models/tables">

    // convert from C# to PHP:
    //    public virtual ArrayList listByMainId(int main_id, Hashtable def = null)
    //    {
    //        if (string.IsNullOrEmpty(junction_field_main_id))
    //            throw new NotImplementedException();
    //        return db.array(table_name, DB.h(junction_field_main_id, main_id));
    //    }
    //
    //    //similar to listByMainId but by linked_id
    //    public virtual ArrayList listByLinkedId(int linked_id, Hashtable def = null)
    //    {
    //        if (string.IsNullOrEmpty(junction_field_linked_id))
    //            throw new NotImplementedException();
    //        return db.array(table_name, DB.h(junction_field_linked_id, linked_id));
    //    }

    /**
     * list records from junction table by main_id
     * @param int $main_id
     * @param array|null $def
     * @return array
     * @throws ApplicationException
     */
    public function listByMainId(int $main_id, array $def = null): array {
        if (empty($this->junction_field_main_id)) {
            throw new ApplicationException("Not implemented");
        }
        return $this->db->arr($this->getTable(), [$this->junction_field_main_id => $main_id]);
    }

    /**
     * list records from junction table by linked_id
     * @param int $linked_id
     * @param array|null $def
     * @return array
     * @throws ApplicationException
     */
    public function listByLinkedId(int $linked_id, array $def = null): array {
        if (empty($this->junction_field_linked_id)) {
            throw new ApplicationException("Not implemented");
        }
        return $this->db->arr($this->getTable(), [$this->junction_field_linked_id => $linked_id]);
    }

    /**
     * sort lookup rows so checked values will be at the top (is_checked desc)
     *  AND then by [_link]prio field (if junction table has any)
     * @param array $lookup_rows
     * @return array
     */
    public function sortByCheckedPrio(array $lookup_rows): array {
        if (!empty($this->field_prio)) {
            usort($lookup_rows, function ($a, $b) {
                return $b['_link'][$this->field_prio] - $a['_link'][$this->field_prio];
            });
        } else {
            usort($lookup_rows, function ($a, $b) {
                return $b['is_checked'] - $a['is_checked'];
            });
        }
        return $lookup_rows;
    }

    /**
     * list LINKED (from junction_model_linked model) records by main id
     * called from withing junction model like UsersCompanies that links 2 tables
     * @param int $main_id main table id
     * @param array|null $def in dynamic controller - field definition (also contains "i" and "ps", "lookup_params", ...) or you could use it to pass additional params
     * @return array
     * @throws ApplicationException
     */
    public function listLinkedByMainId(int $main_id, array $def = null): array {
        $linked_rows = $this->listByMainId($main_id, $def);

        $lookup_rows = $this->junction_model_linked->ilist();
        if ($linked_rows && count($linked_rows) > 0) {
            foreach ($lookup_rows as $k => $row) {
                // check if linked_rows contain main id
                $lookup_rows[$k]['is_checked'] = false;
                $lookup_rows[$k]['_link']      = [];
                foreach ($linked_rows as $lrow) {
                    // compare LINKED ids
                    if ($row[$this->junction_model_linked->field_id] == $lrow[$this->junction_field_linked_id]) {
                        $lookup_rows[$k]['is_checked'] = true;
                        $lookup_rows[$k]['_link']      = $lrow;
                        break;
                    }
                }
            }

            $lookup_rows = $this->sortByCheckedPrio($lookup_rows);
        }
        return $lookup_rows;
    }

    /**
     * list MAIN (from junction_model_main model) records by linked id
     * called from withing junction model like UsersCompanies that links 2 tables
     * @param int $linked_id linked table id
     * @param array|null $def in dynamic controller - field definition (also contains "i" and "ps", "lookup_params", ...) or you could use it to pass additional params
     * @return array
     * @throws ApplicationException
     */
    public function listMainByLinkedId(int $linked_id, array $def = null): array {
        $linked_rows = $this->listByLinkedId($linked_id, $def);

        $lookup_rows = $this->junction_model_main->ilist();
        if ($linked_rows && count($linked_rows) > 0) {
            foreach ($lookup_rows as $k => $row) {
                // check if linked_rows contain main id
                $lookup_rows[$k]['is_checked'] = false;
                $lookup_rows[$k]['_link']      = [];
                foreach ($linked_rows as $lrow) {
                    // compare MAIN ids
                    if ($row[$this->junction_model_main->field_id] == $lrow[$this->junction_field_main_id]) {
                        $lookup_rows[$k]['is_checked'] = true;
                        $lookup_rows[$k]['_link']      = $lrow;
                        break;
                    }
                }
            }

            $lookup_rows = $this->sortByCheckedPrio($lookup_rows);
        }
        return $lookup_rows;
    }

    /**
     * set is_checked=true flag for selected ids, sort by is_checked desc
     * @param array $rows array of hashtables for templates
     * @param array $ids array of selected ids
     * @param array|null $def def - in dynamic controller - field definition (also contains "i" and "ps", "lookup_params", ...) or you could use it to pass additional params
     * @return array
     */
    public function setMultiListChecked(array $rows, array $ids, array $def = null): array {
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
     * @param array|null $def def - in dynamic controller - field definition (also contains "i" and "ps", "lookup_params", ...) or you could use it to pass additional params
     * @return array           array of hashtables for templates
     */
    public function listWithChecked(array|string $hsel_ids, array $def = null): array {
        if (!is_array($hsel_ids)) {
            $hsel_ids = FormUtils::ids2multi($hsel_ids);
        }
        $rows = $this->setMultiListChecked($this->ilist(), $hsel_ids, $def);
        return $rows;
    }

    /**
     * return array of LINKED ids for the MAIN id in junction table
     * @param int $main_id
     * @return array
     */
    public function colLinkedIdsByMainId(int $main_id): array {
        return $this->db->col($this->getTable(), [$this->junction_field_main_id => $main_id], $this->junction_field_linked_id);
    }

    /**
     * return array of MAIN ids for the LINKED id in junction table
     * @param int $linked_id
     * @return array
     */
    public function colMainIdsByLinkedId(int $linked_id): array {
        return $this->db->col($this->getTable(), [$this->junction_field_linked_id => $linked_id], $this->junction_field_main_id);
    }

    public function getJunctionFieldStatus(): string {
        return $this->junction_field_status ?? $this->field_status;
    }

    public function setUnderUpdateByMainId(int $main_id): void {
        $junction_field_status = $this->getJunctionFieldStatus();

        if (empty($junction_field_status) || empty($this->junction_field_main_id)) {
            return; //if no status or linked field - do nothing
        }

        $this->is_under_bulk_update = true;

        $this->db->update($this->getTable(), [$junction_field_status => self::STATUS_UNDER_UPDATE], [$this->junction_field_main_id => $main_id]);
    }

    public function deleteUnderUpdateByMainId(int $main_id): void {
        $junction_field_status = $this->getJunctionFieldStatus();

        if (empty($junction_field_status) || empty($this->junction_field_main_id)) {
            return; //if no status or linked field - do nothing
        }

        $where = [
            $this->junction_field_main_id => $main_id,
            $junction_field_status        => self::STATUS_UNDER_UPDATE,
        ];
        $this->db->deleteWhere($this->getTable(), $where);
        $this->is_under_bulk_update = false;
    }

    // used when the main record must be permanently deleted
    public function deleteByMainId(int $main_id): void {
        if (empty($this->junction_field_main_id)) {
            return; //if no linked field - do nothing
        }

        $where = [$this->junction_field_main_id => $main_id];
        $this->db->deleteWhere($this->getTable(), $where);
    }

    /**
     * generic update (and add/del) for junction table
     * @param string $junction_table_name junction table name that contains id_name and link_id_name fields
     * @param int $main_id main id
     * @param string $main_id_name field name for main id
     * @param string $linked_id_name field name for linked id
     * @param array $linked_keys hashtable with keys as link id (as passed from web)
     * @return void
     */
    public function updateJunction(string $junction_table_name, int $main_id, string $main_id_name, string $linked_id_name, array $linked_keys): void {
        $fields                  = [];
        $where                   = [];
        $link_table_field_status = $this->getJunctionFieldStatus();

        // set all fields as under update
        $fields[$link_table_field_status] = self::STATUS_UNDER_UPDATE;
        $where[$main_id_name]             = $main_id;
        $this->db->update($junction_table_name, $fields, $where);

        if ($linked_keys) {
            foreach ($linked_keys as $linked_id => $val) {
                $fields                           = [];
                $fields[$main_id_name]            = $main_id;
                $fields[$linked_id_name]          = $linked_id;
                $fields[$link_table_field_status] = self::STATUS_ACTIVE;

                $where                  = [];
                $where[$main_id_name]   = $main_id;
                $where[$linked_id_name] = $linked_id;
                $this->db->updateOrInsert($junction_table_name, $fields, $where);
            }
        }

        // remove those who still not updated (so removed)
        $where                           = [];
        $where[$main_id_name]            = $main_id;
        $where[$link_table_field_status] = self::STATUS_UNDER_UPDATE;
        $this->db->deleteWhere($junction_table_name, $where);
    }

    // override to add set more additional fields
    public function updateJunctionByMainIdAdditional(array $linked_keys, string $link_id, array &$fields): void {
        if (!empty($this->field_prio) && isset($linked_keys[$this->field_prio . "_" . $link_id])) {
            $fields[$this->field_prio] = intval($linked_keys[$this->field_prio . "_" . $link_id]); // get value from prio_ID
        }
    }

    /**
     * updates junction table by MAIN id and linked keys (existing in db, but not present keys will be removed)
     * called from withing junction model like UsersCompanies that links 2 tables
     * usage example: UsersCompanies::i()->updateJunctionByMainId(id, reqh("companies"));
     * html: <input type="checkbox" name="companies[123]" value="1" checked>
     * @param int $main_id main id
     * @param array $linked_keys hashtable with keys as linked_id (as passed from web)
     * @return void
     */
    public function updateJunctionByMainId(int $main_id, array $linked_keys): void {
        $fields                  = [];
        $where                   = [];
        $link_table_field_status = $this->getJunctionFieldStatus();

        // set all rows as under update
        $this->setUnderUpdateByMainId($main_id);

        if ($linked_keys) {
            foreach ($linked_keys as $link_id => $val) {
                if (intval($link_id) == 0) {
                    continue; // skip non-id, ex prio_ID
                }

                $fields[$this->junction_field_main_id]   = $main_id;
                $fields[$this->junction_field_linked_id] = $link_id;
                $fields[$link_table_field_status]        = self::STATUS_ACTIVE;

                // additional fields here
                $this->updateJunctionByMainIdAdditional($linked_keys, $link_id, $fields);

                $where                                  = [];
                $where[$this->junction_field_main_id]   = $main_id;
                $where[$this->junction_field_linked_id] = $link_id;
                $this->db->updateOrInsert($this->getTable(), $fields, $where);
            }
        }

        // remove those who still not updated (so removed)
        $this->deleteUnderUpdateByMainId($main_id);
    }

    //override to add set more additional fields
    public function updateJunctionByLinkedIdAdditional(array $linked_keys, string $main_id, array &$fields): void {
        if (!empty($this->field_prio) && isset($linked_keys[$this->field_prio . "_" . $main_id])) {
            $fields[$this->field_prio] = intval($linked_keys[$this->field_prio . "_" . $main_id]); // get value from prio_ID
        }
    }

    /**
     * updates junction table by LINKED id and linked keys (existing in db, but not present keys will be removed)
     * called from withing junction model like UsersCompanies that links 2 tables
     * usage example: UsersCompanies::i()->updateJunctionByLinkedId(id, reqh("users"));
     * html: <input type="checkbox" name="users[123]" value="1" checked>
     * @param int $linked_id linked id
     * @param array $main_keys hashtable with keys as main_id (as passed from web)
     * @return void
     */
    public function updateJunctionByLinkedId(int $linked_id, array $main_keys): void {
        $fields                  = [];
        $where                   = [];
        $link_table_field_status = $this->getJunctionFieldStatus();

        // set all fields as under update
        $fields[$link_table_field_status]       = self::STATUS_UNDER_UPDATE;
        $where[$this->junction_field_linked_id] = $linked_id;
        $this->db->update($this->getTable(), $fields, $where);

        if ($main_keys) {
            foreach ($main_keys as $main_id => $val) {
                if (intval($main_id) == 0) {
                    continue; // skip non-id, ex prio_ID
                }

                $fields[$this->junction_field_linked_id] = $linked_id;
                $fields[$this->junction_field_main_id]   = $main_id;
                $fields[$link_table_field_status]        = self::STATUS_ACTIVE;

                // additional fields here
                $this->updateJunctionByLinkedIdAdditional($main_keys, $main_id, $fields);

                $where                                  = [];
                $where[$this->junction_field_linked_id] = $linked_id;
                $where[$this->junction_field_main_id]   = $main_id;
                $this->db->updateOrInsert($this->getTable(), $fields, $where);
            }
        }

        // remove those who still not updated (so removed)
        $where                                  = [];
        $where[$this->junction_field_linked_id] = $linked_id;
        $where[$link_table_field_status]        = self::STATUS_UNDER_UPDATE;
        $this->db->deleteWhere($this->getTable(), $where);
    }

    //</editor-fold>

    //<editor-fold desc="dynamic subtable component">
    // override in your specific models when necessary
    public function prepareSubtable(array &$list_rows, int $related_id, array $def = null): void {
        $model_name = $def != null ? (string)$def["model"] : get_class($this);
        foreach ($list_rows as $k => $row) {
            $list_rows[$k]["model"] = $model_name;
            //if row_id starts with "new-" - set flag is_new
            $list_rows[$k]["is_new"] = str_starts_with($row["id"], "new-");
        }
    }

    public function prepareSubtableAddNew(array &$list_rows, int $related_id, array $def = null): void {
        //generate unique id based on time (milliseconds) for sequential adding
        $t           = microtime(true);
        $id          = "new-" . intval($t * 1000);
        $item        = [
            "id" => $id,
        ];
        $list_rows[] = $item;
    }

    //</editor-fold>

    //<editor-fold desc="support for sortable records">
    public function updatePrioRange(int $inc_value, int $from_prio, int $to_prio): int {
        $field_prioq = $this->db->qident($this->field_prio);
        $p           = [
            "inc_value" => $inc_value,
            "from_prio" => $from_prio,
            "to_prio"   => $to_prio,
        ];
        return $this->db->exec("UPDATE " . $this->db->qident($this->table_name) .
            " SET " . $field_prioq . "=" . $field_prioq . "+(@inc_value)" .
            " WHERE " . $field_prioq . " BETWEEN @from_prio AND @to_prio", $p);
    }

    public function updatePrio(int $id, int $prio): int {
        return $this->db->update($this->table_name, [$this->field_prio => $prio], $id, $this->field_id);
    }

    /**
     *     // reorder prio column
     * public bool reorderPrio(string sortdir, int id, int under_id, int above_id)
     * {
     * if (sortdir != "asc" && sortdir != "desc")
     * throw new ApplicationException("Wrong sort directrion");
     *
     * if (string.IsNullOrEmpty(field_prio))
     * return false;
     *
     * int id_prio = Utils.f2int(one(id)[field_prio]);
     *
     * // detect reorder
     * if (under_id > 0)
     * {
     * // under id present
     * int under_prio = Utils.f2int(one(under_id)[field_prio]);
     * if (sortdir == "asc")
     * {
     * if (id_prio < under_prio)
     * {
     * // if my prio less than under_prio - make all records between old prio and under_prio as -1
     * updatePrioRange(-1, id_prio, under_prio);
     * // and set new id prio as under_prio
     * updatePrio(id, under_prio);
     * }
     * else
     * {
     * // if my prio more than under_prio - make all records between old prio and under_prio as +1
     * updatePrioRange(+1, (under_prio + 1), id_prio);
     * // and set new id prio as under_prio+1
     * updatePrio(id, under_prio + 1);
     * }
     * }
     * else
     * // desc
     * if (id_prio < under_prio)
     * {
     * // if my prio less than under_prio - make all records between old prio and under_prio-1 as -1
     * updatePrioRange(-1, id_prio, under_prio - 1);
     * // and set new id prio as under_prio-1
     * updatePrio(id, under_prio - 1);
     * }
     * else
     * {
     * // if my prio more than under_prio - make all records between under_prio and old prio as +1
     * updatePrioRange(+1, under_prio, id_prio);
     * // and set new id prio as under_prio
     * updatePrio(id, under_prio);
     * }
     * }
     * else if (above_id > 0)
     * {
     * // above id present
     * int above_prio = Utils.f2int(one(above_id)[field_prio]);
     * if (sortdir == "asc")
     * {
     * if (id_prio < above_prio)
     * {
     * // if my prio less than under_prio - make all records between old prio and above_prio-1 as -1
     * updatePrioRange(-1, id_prio, above_prio - 1);
     * // and set new id prio as under_prio
     * updatePrio(id, above_prio - 1);
     * }
     * else
     * {
     * // if my prio more than under_prio - make all records between above_prio and old prio as +1
     * updatePrioRange(+1, above_prio, id_prio);
     * // and set new id prio as under_prio+1
     * updatePrio(id, above_prio);
     * }
     * }
     * else
     * // desc
     * if (id_prio < above_prio)
     * {
     * // if my prio less than under_prio - make all records between old prio and above_prio as -1
     * updatePrioRange(-1, id_prio, above_prio);
     * // and set new id prio as above_prio
     * updatePrio(id, above_prio);
     * }
     * else
     * {
     * // if my prio more than under_prio - make all records between above_prio+1 and old prio as +1
     * updatePrioRange(+1, above_prio + 1, id_prio);
     * // and set new id prio as under_prio+1
     * updatePrio(id, above_prio + 1);
     * }
     * }
     * else
     * // bad reorder call - ignore
     * return false;
     *
     * return true;
     * }
     */

    // reorder prio column
    public function reorderPrio(string $sortdir, int $id, int $under_id, int $above_id): bool {
        if ($sortdir != "asc" && $sortdir != "desc") {
            throw new ApplicationException("Wrong sort directrion");
        }

        if (empty($this->field_prio)) {
            return false;
        }

        $id_prio = intval($this->one($id)[$this->field_prio]);

        // detect reorder
        if ($under_id > 0) {
            // under id present
            $under_prio = intval($this->one($under_id)[$this->field_prio]);
            if ($sortdir == "asc") {
                if ($id_prio < $under_prio) {
                    // if my prio less than under_prio - make all records between old prio and under_prio as -1
                    $this->updatePrioRange(-1, $id_prio, $under_prio);
                    // and set new id prio as under_prio
                    $this->updatePrio($id, $under_prio);
                } else {
                    // if my prio more than under_prio - make all records between old prio and under_prio as +1
                    $this->updatePrioRange(+1, ($under_prio + 1), $id_prio);
                    // and set new id prio as under_prio+1
                    $this->updatePrio($id, $under_prio + 1);
                }
            } else {
                // desc
                if ($id_prio < $under_prio) {
                    // if my prio less than under_prio - make all records between old prio and under_prio-1 as -1
                    $this->updatePrioRange(-1, $id_prio, $under_prio - 1);
                    // and set new id prio as under_prio-1
                    $this->updatePrio($id, $under_prio - 1);
                } else {
                    // if my prio more than under_prio - make all records between under_prio and old prio as +1
                    $this->updatePrioRange(+1, $under_prio, $id_prio);
                    // and set new id prio as under_prio
                    $this->updatePrio($id, $under_prio);
                }
            } //end of desc
        } elseif ($above_id > 0) {
            // above id present
            $above_prio = intval($this->one($above_id)[$this->field_prio]);
            if ($sortdir == "asc") {
                if ($id_prio < $above_prio) {
                    // if my prio less than above_prio - make all records between old prio and above_prio-1 as -1
                    $this->updatePrioRange(-1, $id_prio, $above_prio - 1);
                    // and set new id prio as above_prio-1
                    $this->updatePrio($id, $above_prio - 1);
                } else {
                    // if my prio more than above_prio - make all records between old prio and above_prio as +1
                    $this->updatePrioRange(+1, $above_prio, $id_prio);
                    // and set new id prio as above_prio
                    $this->updatePrio($id, $above_prio);
                }
            } else {
                // desc
                if ($id_prio < $above_prio) {
                    // if my prio less than above_prio - make all records between old prio and above_prio as -1
                    $this->updatePrioRange(-1, $id_prio, $above_prio);
                    // and set new id prio as above_prio
                    $this->updatePrio($id, $above_prio);
                } else {
                    // if my prio more than above_prio - make all records between above_prio+1 and old prio as +1
                    $this->updatePrioRange(+1, $above_prio + 1, $id_prio);
                    // and set new id prio as above_prio+1
                    $this->updatePrio($id, $above_prio + 1);
                }
            }
        } else {
            // bad reorder call - ignore
            return false;
        } //end of if

        return true;
    }

    //</editor-fold>

    /**
     * fetch all active records and export to CSV with csv_export_fields and Utils::exportCSV
     * @return void
     */
    public function exportCSV(): void {
        $where = [];
        if (!empty($this->field_status)) {
            $where[$this->field_status] = self::STATUS_ACTIVE;
        }

        $rows = $this->db->arr($this->table_name, $where);
        Utils::exportCSV($rows, $this->csv_export_fields);
    }

} //end of class
