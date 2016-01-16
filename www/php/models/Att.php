<?php
/*
 Att model class

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2015 Oleg Savchuk www.osalabs.com
*/

class Att extends FwModel {
    public $MIME_MAP = 'doc|application/msword docx|application/msword xls|application/vnd.ms-excel xlsx|application/vnd.ms-excel ppt|application/vnd.ms-powerpoint pptx|application/vnd.ms-powerpoint pdf|application/pdf html|text/html zip|application/x-zip-compressed jpg|image/jpeg jpeg|image/jpeg gif|image/gif png|image/png wmv|video/x-ms-wmv avi|video/x-msvideo';
    public $att_table_link = 'att_table_link';

    public function __construct() {
        parent::__construct();

        $this->table_name = 'att';
    }

    public function delete($id, $is_perm=NULL){
        if ($is_perm){
            //first, remove files
            $item = $this->one($id);
            $this->remove_upload($id, $item['ext']);
        }

        parent::delete($id, $is_perm);
    }

    #return list of records for the att category where status=0 order by add_time desc
    public function ilist_by_category($att_categories_id) {
        $where = array(
            'status'    => 0,
        );
        if ($att_categories_id>0){
            $where['att_categories_id'] = $att_categories_id;
        }

        return db_array($this->table_name, $where, 'add_time desc');
    }

    /**
     * upload one posted file in $field_name field to item $id
     * @param  int      $id         item id
     * @param  array    $file       one assoc array from get_posted_files()
     * @return none
     */
    public function upload($id, $file, $is_add = false){
        //put file to /upload/module dir with default thumbnails
        $filepath = $this->upload_file($id, $file);

        //get file info (ext, name, size, is_image)
        //update db
        $ext = UploadUtils::upload_ext($filepath);
        $item=array(
            'is_image'  => UploadUtils::is_img_ext($ext),
            'fname'     => $file['name'],
            'fsize'     => filesize($filepath),
            'ext'       => $ext,
        );
        if ($is_add) $item['iname']=$file['name']; //if adding new image set user name to same as file
        $this->update($id, $item);
    }

    //add/update att_table_links
    public function update_att_links($table_name, $id, $form_att){
        if (!is_array($form_att)) return;

        $me_id = Utils::me();

        #1. set status=1 (under update)
        $fields = array();
        $fields['status'] = 1;
        $where = array();
        $where['table_name'] = $table_name;
        $where['item_id'] = $id;
        db_update($this->att_table_link, $fields, $where);

        #2. add new items or update old to status =0
        foreach ($form_att as $att_id => $value) {
            $att_id+=0;
            if (!$att_id) continue;

            $where = array();
            $where['table_name'] = $table_name;
            $where['item_id'] = $id;
            $where['att_id'] = $att_id;
            $row = db_row($att_table_link, $where);

            if (count($row)){
                #existing link
                $fields = array();
                $fields['status'] = 0;
                $where = array();
                $where['id'] = $row['id'];
                db_update($att_table_link, $fields, $where);
            }else{
                #new link
                $fields = array();
                $fields['att_id'] = $att_id;
                $fields['table_name'] = $table_name;
                $fields['item_id'] = $id;
                $fields['add_user_id'] = $me_id;
                db_insert($att_table_link, $fields);
            }
        }

        #3. remove not updated atts (i.e. user removed them)
        $where = array();
        $where['table_name'] = $table_name;
        $where['item_id'] = $id;
        $where['status'] = 1;
        db_del($att_table_link, $where);
    }

    //return correct url
    public function get_url($id, $size=''){
        if (!$id) return '';

        #if /Att need to be on offline folder
        $result = $this->fw->G['ROOT_URL'].'/Att/'.$id;
        if ($size>''){
            $result .= '?size='.$size;
        }
        return $result;
    }

    //return correct url - direct, i.e. not via /Att
    // $id, $size=''
    //
    // OR overloaded
    // if you already have item, must contain: item("id"), item("ext")
    // $item, $size=''
    public function get_url_direct($id_or_item, $size=''){
        if (is_array($id_or_item)){
            return $this->get_upload_url($id_or_item['id'], $id_or_item['ext'], $size);

        }else{
            if (!$id_or_item) return '';
            $item = $this->one($id_or_item);
            if (!count($item)) return '';
            return $this->get_url_direct($item, $size);
        }
    }

    #transimt file by id/size to user's browser, optional disposition - attachment(default)/inline
    #also check access rights - throws ApplicationException if file not accessible by cur user
    #if no file found OR file status<>0 - throws ApplicationException
    public function transmit_file($id, $size='', $disposition='attachment'){
        $item = $this->one($id);
        #validation
        if (!count($item)) throw new ApplicationException('No file specified');
        if ($item['status']<>0) throw new ApplicationException('Access Denied');

        $size = UploadUtils::check_size($size);

        $filepath = $this->get_upload_path($id, $item['ext'], $size);
        $filename = str_replace('"', "'", $item['iname']); #quote filename
        header('Content-type: '.UploadUtils::get_mime4ext($item['ext']));
        header("Content-Length: " . filesize($filepath));
        header('Content-Disposition: '.$disposition.'; filename="'.$filename.'"');

        #logger('transmit file '.$filepath." $id, $size, $disposition, ".UploadUtils::get_mime4ext($item['ext']));
        $fp = fopen($filepath, 'rb');
        fpassthru($fp);
    }

}

?>