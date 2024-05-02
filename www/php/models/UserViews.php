<?php
/*
 UserViews model class

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2024 Oleg Savchuk www.osalabs.com
*/

class UserViews extends FwModel {

    public function __construct() {
        parent::__construct();

        $this->table_name     = 'user_views';
        $this->is_log_changes = false; #no need to log changes here
    }

    #return default screen record for logged user
    public function oneByIcode(string $icode): array {
        $where = array(
            $this->field_add_users_id => $this->fw->userId(),
            $this->field_icode        => $icode,
            $this->field_iname        => '',
        );
        return $this->db->row($this->table_name, $where);
    }

    /**
     * return screen record for logged user by id
     * @param string $icode
     * @param int $id
     * @return array
     * @throws DBException
     */
    public function oneByIcodeId(string $icode, int $id): array {
        $params = array(
            'icode'        => $icode,
            'id'           => $id,
            'add_users_id' => $this->fw->userId(),
        );

        return $this->db->rowp("select * from " . $this->db->qid($this->table_name) .
            " where icode=@icode
                     and id=@id
                     and (is_system=1 OR add_users_id=@add_users_id)", $params);
    }

    /**
     * by icode/iname/loggeduser
     * @param string $icode
     * @param string $iname
     * @return array
     * @throws DBException
     */
    public function oneByUK(string $icode, string $iname): array {
        return $this->db->row($this->table_name, array(
            $this->field_icode        => $icode,
            $this->field_iname        => $iname,
            $this->field_add_users_id => $this->fw->userId(),
        ));
    }

    /**
     * add or update view for logged user
     * @param string $icode
     * @param string $fields
     * @param string $iname
     * @param string $density
     * @return int
     */
    public function addSimple(string $icode, string $fields, string $iname, string $density = ""): int {
        return $this->add(array(
            $this->field_icode        => $icode,
            $this->field_iname        => $iname,
            'fields'                  => $fields,
            'density'                 => $density,
            $this->field_add_users_id => $this->fw->userId(),
        ));
    }

    /**
     * add or update view for logged user
     * @param string $icode
     * @param string $fields
     * @param string $iname
     * @return int
     * @throws DBException
     */
    public function addOrUpdateByUK(string $icode, string $fields, string $iname): int {
        $id   = 0;
        $item = $this->oneByUK($icode, $iname);
        if ($item) {
            $id = intval($item['id']);
            $this->update($id, array('fields' => $fields));
        } else {
            $id = $this->addSimple($icode, $fields, $iname);
        }

        return $id;
    }

    /**
     * update default screen fields for logged user
     * @param string $icode screen url
     * @param array $itemdb
     * @return integer         user_views.id
     * @throws DBException
     */
    public function updateByIcode(string $icode, array $itemdb): int {
        $result = 0;
        $item   = $this->oneByIcode($icode);
        if ($item) {
            #exists
            $result = $item['id'];
            $this->update($item['id'], $itemdb);
        } else {
            // new - add key fields
            $result = $this->add(array_merge($itemdb, [
                $this->field_icode        => $icode,
                $this->field_add_users_id => $this->fw->userId(),
            ]));
        }

        return $result;
    }

    /*
     *     /// <summary>
    /// update default screen fields for logged user
    /// </summary>
    /// <param name="icode">screen url</param>
    /// <param name="fields">comma-separated fields</param>
    /// <param name="iname">view title (for save new view)</param>
    /// <returns>user_views.id</returns>
    public int updateByIcodeFields(string icode, string fields)
    {
        return updateByIcode(icode, DB.h("fields", fields));
    }

    /// <summary>
    /// list for select by icode(basically controller's base_url) and only for logged user OR active system views
    /// iname>'' - because empty name is for default view, it's not visible in the list (use "Reset to Defaults" instead)
    /// </summary>
    /// <param name="icode"></param>
    /// <returns></returns>
    public ArrayList listSelectByIcode(string icode)
    {
        return db.arrayp("select id, iname from " + db.qid(table_name) +
                        @" where status=0
                                 and iname>''
                                 and icode=@icode
                                 and (is_system=1 OR add_users_id=@users_id)
                            order by is_system desc, iname", DB.h("@icode", icode, "@users_id", fw.userId));
    }

    /// <summary>
    /// list all icodes available for the user
    /// </summary>
    /// <returns></returns>
    public ArrayList listSelectIcodes()
    {
        return db.arrayp("select distinct icode as id, icode as iname from " + db.qid(table_name) +
                        @" where status=0
                                 and iname>''
                                 and (is_system=1 OR add_users_id=@users_id)
                            order by icode", DB.h("@users_id", fw.userId));
    }

    /// <summary>
    /// replace current default view for icode using view in id
    /// </summary>
    /// <param name="icode"></param>
    /// <param name="id"></param>
    public void setViewForIcode(string icode, int id)
    {
        var item = oneByIcodeId(icode, id);
        if (item.Count == 0) return;

        updateByIcodeFields(icode, item["fields"]);
    }
*/

    /**
     * list all icodes available for the user
     * @param string $icode screen url
     * @param string $fields comma-separated fields
     * @return int
     * @throws DBException
     */
    public function updateByIcodeFields(string $icode, string $fields): int {
        return $this->updateByIcode($icode, ['fields' => $fields]);
    }

    /**
     * list for select by icode(basically controller's base_url) and only for logged user OR active system views
     * iname>'' - because empty name is for default view, it's not visible in the list (use "Reset to Defaults" instead)
     * @param string $icode
     * @return array
     * @throws DBException
     */
    public function listSelectByIcode(string $icode): array {
        return $this->db->arrp("select id, iname from " . $this->db->qid($this->table_name) .
            " where status=0 
                and icode=@icode
                and (is_system=1 OR add_users_id=@users_id)
           order by is_system desc, iname", ['@icode' => $icode, '@users_id' => $this->fw->userId()]);
    }

    /**
     * list all icodes available for the user
     * @return array
     * @throws DBException
     */
    public function listSelectIcodes(): array {
        return $this->db->arrp("select distinct icode as id, icode as iname from " . $this->db->qid($this->table_name) .
            " where status=0 
                and iname>''
                and (is_system=1 OR add_users_id=@users_id)
           order by icode", ['@users_id' => $this->fw->userId()]);
    }

    /**
     * replace current default view for icode using view in id
     * @param string $icode
     * @param int $id
     * @return void
     * @throws DBException
     */
    public function setViewForIcode(string $icode, int $id): void {
        $item = $this->oneByIcodeId($icode, $id);
        if (!$item) {
            return;
        }

        $this->updateByIcodeFields($icode, $item['fields']);
    }

}
