<?php
/*
 Base Fw Model class

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2019 Oleg Savchuk www.osalabs.com
*/

abstract class FwModel {
    public const STATUS_ACTIVE = 0;
    public const STATUS_DELETED = 127;

    public $fw;  //current app/framework object
    public $table_name; //must be defined in inherited classes

    public $CACHE_PREFIX = 'fwmodel.one.'; #TODO - ability to cleanup all, but this model-only cache items

    public $field_id='id';                  #default primary key name
    public $field_iname='iname';
    # default field names. If you override it and make empty - automatic processing disabled
    public $field_status='status';
    public $field_add_users_id='add_users_id';
    public $field_upd_users_id='upd_users_id';
    public $field_upd_time='upd_time';

    public $csv_export_fields = ""; #all or Utils.qh format fieldname|HumanName|fieldName

    protected $db;

    #alternative of fw::model(Model)->method() is Model::i()->method()
    #7 chars shorter :]
    #this comment below is a helper hint for IDE:
    /** @return FwModel */
    static function i(){
        return fw::model(get_called_class());
    }

    public function __construct($param_fw=null) {
        if ( is_null($param_fw) ){
            $this->fw = fw::i();
        }else{
            $this->fw = $param_fw;
        }
        $this->db = $this->fw->db;
    }

    //cached, pass $is_force=true to force read from db
    public function one($id, $is_force=false) {
        $cache_key = $this->CACHE_PREFIX.$this->table_name.'*'.$id;
        if (!$is_force) {
            $row = FwCache::getValue($cache_key);
        }
        if ($is_force || is_null($row)){
            $row = $this->db->row("SELECT * FROM ".$this->table_name." WHERE ".$this->db->quote_ident($this->field_id)."=".$this->db->quote($id));
            FwCache::setValue($cache_key, $row);
        }else{
            #logger('CACHE HIT!');
        }
        return $row;
    }

    public function oneByIname($iname) {
        return $this->db->row("SELECT * FROM ".$this->table_name." WHERE ".$this->db->quote_ident($this->field_iname)."=".$this->db->quote($iname));
    }

    public function listFields(){
        $result=array();

        $rows = $this->db->arr("explain ".dbq_ident($this->table_name));
        foreach ($rows as $key => $row) {
            #add standard id/name fields
            $rows[$key]['id']=$row['Field'];
            $rows[$key]['iname']=$row['Field'];
        }

        return $result;
    }

    public function iname($id) {
        $row = $this->one($id);
        return $row[$this->field_iname];
    }

    public function getFullName($id) {
        $id+=0;
        $result = '';

        if ($id){
            $item = $this->one($id);
            $result = $item[$this->field_iname];
        }

        return $result;
    }

    //add new record
    public function add($item) {
        if (!empty($this->field_add_users_id) && !isset($item[$this->field_add_users_id])) $item[$this->field_add_users_id]=Utils::me();
        $id=$this->db->insert($this->table_name, $item);

        $this->removeCache($id);

        $this->fw->model('FwEvents')->logFields($this->table_name.'_add', $id, $item);
        return $id;
    }

    //update record
    public function update($id, $item) {
        if (!empty($this->field_upd_users_id) && !isset($item[$this->field_upd_users_id])) $item[$this->field_upd_users_id]=Utils::me();
        if (!empty($this->field_upd_time) && !isset($item[$this->field_upd_time])) $item[$this->field_upd_time]='~!now()';
        $this->db->update($this->table_name, $item, $id);

        $this->removeCache($id);

        $this->fw->model('FwEvents')->logFields($this->table_name.'_upd', $id, $item);
        return $id;
    }

    #quickly add new record just with iname
    #if such iname exists - just id returned
    #RETURN id - for new or existing record
    public function findOrAddByIname($iname, &$is_added=false){
        $result=0;
        $iname=trim($iname);
        if (!strlen($iname)) return 0;

        $item = $this->oneByIname($iname);
        if ($item){
            #exists
            $result = $item[$this->field_id];
        }else{
            $item=array(
                $this->field_iname => $iname,
            );
            $result = $this->add($item);
            $is_added=true;
        }
        return $result;
    }

    //non-permanent or permanent delete
    public function delete($id, $is_perm=NULL) {
        if ($is_perm || !strlen($this->field_status)){
            $this->db->delete($this->table_name, $id);
            $this->fw->model('FwEvents')->log($this->table_name.'_del', $id);
        }else{
            $vars=array(
                $this->field_status => 127,
            );
            $this->update($id, $vars);
        }

        $this->removeCache($id);
        return true;
    }

    //remove from cache - can be called from outside model if model table updated
    public function removeCache($id){
        $cache_key = $this->CACHE_PREFIX.$this->table_name.'*'.$id;
        FwCache::remove($cache_key);
    }

    //check if item exists for a given iname
    public function isExistsByField($uniq_key, $field, $not_id=NULL) {
        return $this->db->is_record_exists($this->table_name, $uniq_key, $field, $not_id);
    }

    public function isExists($uniq_key, $not_id=NULL) {
        return $this->isExistsByField($uniq_key, $this->field_iname, $not_id);
    }

    #return standard list of id,iname where status=0 order by iname
    public function ilist() {
        $where = array();
        if (strlen($this->field_status)) $where[$this->field_status]=0;
        return $this->db->arr($this->table_name, $where, $this->db->quote_ident($this->field_iname));
    }

    public function listSelectOptions(){
        $where = '';
        if (strlen($this->field_status)) $where.=" ".$this->db->quote_ident($this->field_status)."=0";
        return $this->db->arr("SELECT ".$this->db->quote_ident($this->field_id)." as id, ".$this->db->quote_ident($this->field_iname)." as iname FROM ".$this->db->quote_ident($this->table_name)." WHERE $where ORDER BY ".$this->db->quote_ident($this->field_iname));
    }
    public function getSelectOptions($sel_id) {
        return FormUtils::selectOptions($this->listSelectOptions(), $sel_id);
    }

    public function getCount(){
        $where = '';
        if (strlen($this->field_status)) $where.=" ".$this->db->quote_ident($this->field_status)."<>127";
        return $this->db->value("SELECT count(*) FROM ".$this->db->quote_ident($this->table_name)." WHERE $where");
    }


    /**
     * return array of hashtables for multilist values with is_checked set for selected values
     * @param  string|array $hsel_ids array of comma-separated string
     * @param  object $params   optional, to use - override in your model
     * @return array           array of hashtables for templates
     */
    public function getMultiList($hsel_ids, $params=null){
        if (!is_array($hsel_ids)) $hsel_ids=FormUtils::ids2multi($hsel_ids);

        $rows = $this->ilist();
        if (count($hsel_ids)){
            foreach ($rows as $k => $row) {
                $rows[$k]['is_checked'] = array_key_exists($row[$this->field_id], $hsel_ids)!==FALSE;
            }
        }

        return $rows;
    }

    public function getAutocompleteList($q, $limit=5) {
        $where = $this->db->quote_ident($this->field_iname)." like ".$this->db->quote('%'.$q.'%');
        if (strlen($this->field_status)) $where .= " and ".$this->db->quote_ident($this->field_status)."<>127 ";

        $sql = "SELECT ".$this->db->quote_ident($this->field_iname)." as iname FROM ".$this->db->quote_ident($this->table_name)." WHERE ".$where." LIMIT $limit";
        return $this->db->col($sql);
    }

    public function getCSVExport() {
        $where = array();
        if ($this->field_status > "")  $where[$this->field_status] = self::STATUS_ACTIVE;

        $rows = $this->db->arr($this->table_name, $where);
        Utils::responseCSV($rows, $this->csv_export_fields);
    }

    //****************** Item Upload Utils

    //simple upload of the file related to item
    public function uploadFile($id, $file){
        $filepath = UploadUtils::uploadFile($id, $this->getUploadBaseDir(), $file);
        logger('DEBUG', "file uploaded to [$filepath]");

        UploadUtils::uploadResize($filepath, UploadUtils::$IMG_RESIZE_DEF);
        return $filepath;
    }

    public function getUploadBaseDir(){
        return UploadUtils::getUploadBaseDir().'/'.$this->table_name;
    }
    public function getUploadBaseUrl(){
        return UploadUtils::getUploadBaseUrl().'/'.$this->table_name;
    }


    public function getUploadDir($id){
        return UploadUtils::getUploadDir($id, $this->getUploadBaseDir());
    }

    public function getUploadPath($id, $ext, $size=''){
        return UploadUtils::getUploadPath($id, $this->getUploadBaseDir(), $ext, $size);
    }

    public function getUploadUrl($id, $ext, $size=''){
        return UploadUtils::getUploadUrl($id, $this->getUploadBaseDir(), $this->getUploadBaseUrl(), $ext, $size);
    }

    public function removeUpload($id, $ext){
        UploadUtils::cleanupUpload($id, $this->getUploadBaseDir(), $ext);
    }

}//end of class

?>