<?php
/*
 Base Fw Model class

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2015 Oleg Savchuk www.osalabs.com
*/

abstract class FwModel {
    public $fw;  //current app/framework object
    public $table_name; //must be defined in inherited classes

    public $CACHE_PREFIX = 'fwmodel.one.'; #TODO - ability to cleanup all, but this model-only cache items

    protected $db;

    #alternative of fw::model(Model)->method() is Model::i()->method()
    #7 chars shorter :]
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
            $row = $this->db->row("select * from ".$this->table_name." where id=".$this->db->quote($id));
            FwCache::setValue($cache_key, $row);
        }else{
            #logger('CACHE HIT!');
        }
        return $row;
    }

    public function oneByIname($iname) {
        return $this->db->row("select * from ".$this->table_name." where iname=".$this->db->quote($iname));
    }

    public function iname($id) {
        $row = $this->one($id);
        return $row['iname'];
    }

    public function getFullName($id) {
        $id+=0;
        $result = '';

        if ($id){
            $item = $this->one($id);
            $result = $item['iname'];
        }

        return $result;
    }

    //add new record
    public function add($item) {
        if (!isset($item['add_users_id'])) $item['add_users_id']=Utils::me();
        $id=$this->db->insert($this->table_name, $item);

        $this->removeCache($id);

        $this->fw->model('FwEvents')->logFields($this->table_name.'_add', $id, $item);
        return $id;
    }

    //update record
    public function update($id, $item) {
        if (!isset($item['upd_users_id'])) $item['upd_users_id']=Utils::me();
        $item['upd_time']='~!now()';
        $this->db->update($this->table_name, $item, $id);

        $this->removeCache($id);

        $this->fw->model('FwEvents')->logFields($this->table_name.'_upd', $id, $item);
        return $id;
    }

    #quickly add new record just with iname
    #if such iname exists - just id returned
    #RETURN id - for new or existing record
    public function addOrUpdateByIname($iname){
        $result=0;
        $iname=trim($iname);
        if (!strlen($iname)) return 0;

        $item = $this->oneByIname($iname);
        if ($item['id']){
            #exists
            $result = $item['id'];
        }else{
            $item=array(
                'iname' => $iname,
            );
            $result = $this->add($item);
        }
        return $result;
    }

    //non-permanent or permanent delete
    public function delete($id, $is_perm=NULL) {
        if ($is_perm){
            $this->db->delete($this->table_name, $id);
            $this->fw->model('FwEvents')->log($this->table_name.'_del', $id);
        }else{
            $vars=array(
                'status'    => 127,
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
        return $this->isExistsByField($uniq_key, 'iname', $not_id);
    }

    #return standard list of id,iname where status=0 order by iname
    public function ilist() {
        $sql  = 'select id, iname from '.$this->table_name.' where status=0 order by iname';
        return $this->db->arr($sql);
    }

    public function getSelectOptions($sel_id) {
        return FormUtils::selectOptions($this->ilist(), $sel_id);
    }

    public function getMultiList($hsel_ids){
        $rows = $this->ilist();
        if (is_array($hsel_ids) && count($hsel_ids)){
            foreach ($rows as $k => $row) {
                $rows[$k]['is_checked'] = array_key_exists($row['id'], $hsel_ids)!==FALSE;
            }
        }

        return $rows;
    }

    public function getAutocompleteList($q, $limit=5) {
        $sql = 'select iname from '.$this->table_name.'
                 where status=0
                  and iname like '.$this->db->quote('%'.$q.'%').'
                 limit '.$limit;
        return $this->db->col($sql);
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