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

    public function __construct($param_fw=null) {
        if ( is_null($param_fw) ){
            $this->fw = fw::i();
        }else{
            $this->fw = $fw;
        }
    }

    //cached, pass $is_force=true to force read from db
    public function one($id, $is_force=false) {
        $cache_key = $this->CACHE_PREFIX.$this->table_name.'*'.$id;
        if (!$is_force) {
            $row = FwCache::get_value($cache_key);
        }
        if ($is_force || is_null($row)){
            $row = db_row("select * from ".$this->table_name." where id=".dbq($id));
            FwCache::set_value($cache_key, $row);
        }else{
            #logger('CACHE HIT!');
        }
        return $row;
    }

    public function one_by_iname($iname) {
        return db_row("select * from ".$this->table_name." where iname=".dbq($iname));
    }

    public function iname($id) {
        $row = $this->one($id);
        return $row['iname'];
    }

    public function full_name($id) {
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
        if (!isset($item['add_user_id'])) $item['add_user_id']=Utils::me();
        $id=db_insert($this->table_name, $item);

        $this->cache_remove($id);

        $this->fw->model('Events')->log_fields($this->table_name.'_add', $id, $item);
        return $id;
    }

    //update record
    public function update($id, $item) {
        if (!isset($item['upd_user_id'])) $item['upd_user_id']=Utils::me();
        $item['upd_time']='~!now()';
        db_update($this->table_name, $item, $id);

        $this->cache_remove($id);

        $this->fw->model('Events')->log_fields($this->table_name.'_upd', $id, $item);
        return $id;
    }

    #quickly add new record just with iname
    #if such iname exists - just id returned
    #RETURN id - for new or existing record
    public function add_or_update_quick($iname){
        $result=0;
        $iname=trim($iname);
        if (!strlen($iname)) return 0;

        $item = $this->one_by_iname($iname);
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
            db_delete($this->table_name, $id);
            $this->fw->model('Events')->log_event($this->table_name.'_del', $id);
        }else{
            $vars=array(
                'status'    => 127,
            );
            $this->update($id, $vars);
        }

        $this->cache_remove($id);
        return true;
    }

    //remove from cache - can be called from outside model if model table updated
    public function cache_remove($id){
        $cache_key = $this->CACHE_PREFIX.$this->table_name.'*'.$id;
        FwCache::remove($cache_key);
    }

    //check if item exists for a given iname
    public function is_exists_byfield($uniq_key, $field, $not_id=NULL) {
        return db_is_record_exists($this->table_name, $uniq_key, $field, $not_id);
    }

    public function is_exists($uniq_key, $not_id=NULL) {
        $this->is_exists_byfield($uniq_key, 'iname', $not_id);
    }

    #return standard list of id,iname where status=0 order by iname
    public function ilist() {
        $sql  = 'select id, iname from '.$this->table_name.' where status=0 order by iname';
        return db_array($sql);
    }

    public function get_select_options($sel_id) {
        return FormUtils::select_options_db($this->ilist(), $sel_id);
    }

    public function get_multi_list($hsel_ids){
        $rows = $this->ilist();
        if (is_array($hsel_ids) && count($hsel_ids)){
            foreach ($rows as $k => $row) {
                $rows[$k]['is_checked'] = array_key_exists($row['id'], $hsel_ids)!==FALSE;
            }
        }

        return $rows;
    }

    public function get_autocomplete_items($q, $limit=5) {
        $sql = 'select iname from '.$this->table_name.'
                 where status=0
                  and iname like '.dbq('%'.$q.'%').'
                 limit '.$limit;
        return db_col($sql);
    }


    //****************** Item Upload Utils

    //simple upload of the file related to item
    public function upload_file($id, $file){
        $filepath = UploadUtils::upload_file($id, $this->get_upload_basedir(), $file);
        logger('DEBUG', "file uploaded to [$filepath]");

        UploadUtils::upload_resize($filepath, UploadUtils::$IMG_RESIZE_DEF);
        return $filepath;
    }

    public function get_upload_basedir(){
        return UploadUtils::get_upload_basedir().'/'.$this->table_name;
    }
    public function get_upload_baseurl(){
        return UploadUtils::get_upload_baseurl().'/'.$this->table_name;
    }


    public function get_upload_dir($id){
        return UploadUtils::get_upload_dir($id, $this->get_upload_basedir());
    }

    public function get_upload_path($id, $ext, $size=''){
        return UploadUtils::get_upload_path($id, $this->get_upload_basedir(), $ext, $size);
    }

    public function get_upload_url($id, $ext, $size=''){
        return UploadUtils::get_upload_url($id, $this->get_upload_basedir(), $this->get_upload_baseurl(), $ext, $size);
    }

    public function remove_upload($id, $ext){
        UploadUtils::cleanup_upload($id, $this->get_upload_basedir(), $ext);
    }

}//end of class

?>