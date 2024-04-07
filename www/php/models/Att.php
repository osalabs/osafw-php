<?php
/*
 Att model class

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2024 Oleg Savchuk www.osalabs.com
*/

class Att extends FwModel {
    public $MIME_MAP = 'doc|application/msword docx|application/msword xls|application/vnd.ms-excel xlsx|application/vnd.ms-excel ppt|application/vnd.ms-powerpoint pptx|application/vnd.ms-powerpoint pdf|application/pdf html|text/html zip|application/x-zip-compressed jpg|image/jpeg jpeg|image/jpeg gif|image/gif png|image/png wmv|video/x-ms-wmv avi|video/x-msvideo';
    public $att_table_link = 'att_table_link';

    public function __construct() {
        parent::__construct();

        $this->table_name = 'att';
    }

    public function delete($id, $is_perm = NULL): bool {
        if ($is_perm) {
            //first, remove files
            $item = $this->one($id);
            $this->removeUpload($id, $item['ext']);
        }

        return parent::delete($id, $is_perm);
    }

    #return list of records for the att category where status=0 order by add_time desc
    public function ilistByCategory($att_categories_id) {
        $where = array(
            'status' => 0,
        );
        if ($att_categories_id > 0) {
            $where['att_categories_id'] = $att_categories_id;
        }

        return $this->db->arr($this->table_name, $where, 'add_time desc');
    }

    public function ilistByOneTable($rel_table_name, $rel_item_id) {
        $where = array(
            'status'     => 0,
            'table_name' => $rel_table_name,
            'item_id'    => $rel_item_id,
        );

        return $this->db->arr($this->table_name, $where, 'add_time desc');
    }

    /**
     * upload one posted file in $field_name field to item $id
     * @param int $id item id
     * @param array $file one assoc array from getPostedFiles()
     * @return none
     */
    public function upload($id, $file, $is_add = false) {
        //put file to /upload/module dir with default thumbnails
        $filepath = $this->uploadFile($id, $file);

        //get file info (ext, name, size, is_image)
        //update db
        $ext  = UploadUtils::uploadExt($filepath);
        $item = array(
            'is_image' => UploadUtils::isImgExt($ext),
            'fname'    => $file['name'],
            'fsize'    => filesize($filepath),
            'ext'      => $ext,
            'status'   => 0, //switch from uploading status(1) to uploaded(0)
        );
        if ($is_add) {
            $item['iname'] = $file['name'];
        } //if adding new image set user name to same as file
        $this->update($id, $item);
    }

    /**
     * upload multiple files, add att records and link with ONE table/item_id
     * @param array $files UploadUtils::getPostedFiles('file1')
     * @param string $rel_table_name related table
     * @param string $rel_item_id related itemid
     * @return array of inserted ids
     */
    public function addAndUploadOneTable($files, $rel_table_name, $rel_item_id) {
        $result = array();
        foreach ($files as $key => $file) {
            $fields = array(
                'iname'      => 'new file upload',
                'status'     => 1, //under upload
                'table_name' => $rel_table_name,
                'item_id'    => $rel_item_id,
            );
            $id     = $this->add($fields);

            $this->upload($id, $file, true);

            $result[] = $id;
        }
        return $result;
    }

    public function getAttLinks($table_name, $id) {
        if (!$id || !$table_name) {
            return array();
        }
        return $this->db->arr('select att.* from att, att_table_link atl where att.id = atl.att_id and atl.table_name=' . $this->db->quote($table_name) . ' and atl.item_id=' . dbqi($id));
    }

    //add/update att_table_links
    public function updateAttLinks($table_name, $id, $form_att) {
        if (!is_array($form_att)) {
            return;
        }

        #1. set status=1 (under update)
        $fields              = array();
        $fields['status']    = 1;
        $where               = array();
        $where['table_name'] = $table_name;
        $where['item_id']    = $id;
        $this->db->update($this->att_table_link, $fields, $where);

        #2. add new items or update old to status =0
        foreach ($form_att as $att_id => $value) {
            $att_id += 0;
            if (!$att_id) {
                continue;
            }

            $where               = array();
            $where['table_name'] = $table_name;
            $where['item_id']    = $id;
            $where['att_id']     = $att_id;
            $row                 = $this->db->row($this->att_table_link, $where);

            if ($row) {
                #existing link
                $fields           = array();
                $fields['status'] = 0;
                $where            = array();
                $where['id']      = $row['id'];
                $this->db->update($this->att_table_link, $fields, $where);
            } else {
                #new link
                $fields                 = array();
                $fields['att_id']       = $att_id;
                $fields['table_name']   = $table_name;
                $fields['item_id']      = $id;
                $fields['add_users_id'] = $this->fw->userId();
                $this->db->insert($this->att_table_link, $fields);
            }
        }

        #3. remove not updated atts (i.e. user removed them)
        $this->db->delete($this->att_table_link, 1, 'status', " and item_id=" . dbqi($id) . " and table_name=" . $this->db->quote($table_name));
    }

    //return correct url
    public function getUrl($id, $size = '') {
        if (!$id) {
            return '';
        }

        #if /Att need to be on offline folder
        $result = $this->fw->GLOBAL['ROOT_URL'] . '/Att/' . $id;
        if ($size > '') {
            $result .= '?size=' . $size;
        }
        return $result;
    }

    //return correct url - direct, i.e. not via /Att
    // $id, $size=''
    //
    // OR overloaded
    // if you already have item, must contain: item("id"), item("ext")
    // $item, $size=''
    public function getUrlDirect($id_or_item, $size = '') {
        if (is_array($id_or_item)) {
            return $this->getUploadUrl($id_or_item['id'], $id_or_item['ext'], $size);

        } else {
            if (!$id_or_item) {
                return '';
            }
            $item = $this->one($id_or_item);
            if (!count($item)) {
                return '';
            }
            return $this->getUrlDirect($item, $size);
        }
    }

    #transimt file by id/size to user's browser, optional disposition - attachment(default)/inline
    #also check access rights - throws ApplicationException if file not accessible by cur user
    #if no file found OR file status<>0 - throws ApplicationException
    #$is_private - if true - send private cache headers, instead of public
    #Optimized: returns 304 if file not modified according to If-Modified-Since http header
    public function transmitFile($id, $size = '', $disposition = 'attachment', $is_private = false) {
        $item = $this->one($id);
        #validation
        if (!count($item)) {
            throw new ApplicationException('No file specified');
        }
        if ($item['status'] <> 0) {
            throw new ApplicationException('Access Denied');
        }

        $size = UploadUtils::checkSize($size);

        $filepath = $this->getUploadPath($id, $item['ext'], $size);
        $filetime = filemtime($filepath);

        $cache_time = 2592000; #30 days
        header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + $cache_time));
        header("Pragma: cache");
        if ($is_private) {
            header("Cache-Control: max-age=$cache_time, private");
        } else {
            header("Cache-Control: max-age=$cache_time, public");
        }
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s T', $filetime));

        if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $filetime) {
            header('HTTP/1.0 304 Not Modified');
            return;
        }

        $filename = str_replace('"', "'", $item['iname']); #quote filename
        header('Content-type: ' . UploadUtils::getMimeForExt($item['ext']));
        header("Content-Length: " . filesize($filepath));
        header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');

        logger('TRACE', "transmit file [$filepath] $id, $size, $disposition, " . UploadUtils::getMimeForExt($item['ext']));
        $fp = fopen($filepath, 'rb');
        fpassthru($fp);
    }

    /**
     * return all att files linked via att_table_link
     * @param string $table_name table name
     * @param integer $id id in table
     * @param integer $is_image -1 (all - files and images), 0 (files only), 1 (images only)
     * @return array              array of hashtables
     */
    public function getAllLinked($table_name, $id, $is_image = -1) {
        $where = '';
        if ($is_image > -1) {
            $where .= " and a.is_image=" . dbqi($is_image);
        }
        return $this->db->arr("SELECT a.* " .
            "  FROM " . $this->att_table_link . " atl, att a " .
            " WHERE atl.table_name=" . dbq($table_name) .
            "   and atl.item_id=" . dbqi($id) .
            "   and a.id=atl.att_id" .
            $where .
            " ORDER BY a.id ");
    }

}
