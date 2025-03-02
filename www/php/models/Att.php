<?php
/*
 Att model class

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2025 Oleg Savchuk www.osalabs.com
*/

class Att extends FwModel {
    public const int STORAGE = self::STORAGE_FILE; #default storage for the project

    public const int STORAGE_TABLE = 0; #att.raw
    public const int STORAGE_FILE  = 10; #0/0/0/att_id.dat
    public const int STORAGE_S3    = 20; #$S3Bucket/$S3Root/att/att_id

    public const string IMGURL_0    = "/img/0.gif";
    public const string IMGURL_FILE = "/img/att_file.png";

    const string SIZE_ORIGINAL = "";
    const string SIZE_SMALL    = "s";
    const string SIZE_MEDIUM   = "m";
    const string SIZE_LARGE    = "l";

    const array ALL_SIZES   = [self::SIZE_ORIGINAL, self::SIZE_SMALL, self::SIZE_MEDIUM, self::SIZE_LARGE];
    const array THUMB_SIZES = [self::SIZE_SMALL, self::SIZE_MEDIUM, self::SIZE_LARGE];

    const int MAX_THUMB_W_S = 180;
    const int MAX_THUMB_H_S = 180;
    const int MAX_THUMB_W_M = 512;
    const int MAX_THUMB_H_M = 512;
    const int MAX_THUMB_W_L = 1200;
    const int MAX_THUMB_H_L = 1200;

    public const string MIME_MAP = "doc|application/msword docx|application/msword xls|application/vnd.ms-excel xlsx|application/vnd.ms-excel ppt|application/vnd.ms-powerpoint pptx|application/vnd.ms-powerpoint csv|text/csv pdf|application/pdf html|text/html zip|application/x-zip-compressed jpg|image/jpeg jpeg|image/jpeg gif|image/gif png|image/png wmv|video/x-ms-wmv avi|video/x-msvideo mp4|video/mp4";

    public function __construct() {
        parent::__construct();

        $this->table_name = 'att';
    }

    /**
     * upload one posted file in $field to item $id
     * @param int $id item id
     * @param array $file one assoc array from $_FILES
     * @param bool $is_new - if true - this is new record, not update
     * @return array|null - array with file fields for att table or null if failed
     * @throws ApplicationException
     * @throws DBException
     */
    public function uploadOne(int $id, array $file, bool $is_new = false): ?array {
        $result = null;
        if ($filepath = $this->uploadFile($id, $file)) {
            logger("uploaded to [" . $filepath . "]");
            $ext = UploadUtils::uploadExt($filepath);

            // update db with file information
            $fields = [
                'fname'   => $file['name'],
                'fsize'   => filesize($filepath),
                'ext'     => $ext,
                'storage' => self::STORAGE_FILE, // initial upload to file, then move to storage
                'status'  => self::STATUS_ACTIVE, // finished upload - change status to active
            ];
            if ($is_new) {
                $fields["iname"] = $file['name'];
            }

            if (UploadUtils::isImgExt($ext)) {
                // if it's an image - set flag and resize for thumbs
                $fields["is_image"] = 1;

                ImageUtils::resize($filepath, self::MAX_THUMB_W_S, self::MAX_THUMB_H_S, $this->getUploadImgPath($id, self::SIZE_SMALL, $ext));
                ImageUtils::resize($filepath, self::MAX_THUMB_W_M, self::MAX_THUMB_H_M, $this->getUploadImgPath($id, self::SIZE_MEDIUM, $ext));
                ImageUtils::resize($filepath, self::MAX_THUMB_W_L, self::MAX_THUMB_H_L, $this->getUploadImgPath($id, self::SIZE_LARGE, $ext));
            }

            $this->update($id, $fields);

            $result             = $fields;
            $result["filepath"] = $filepath; #add full local upload path to result

            $this->moveToStorage($id);
        }
        return $result;
    }

    /**
     * mulitple fields upload from $_FILES
     * @param array $item files to add to att table, can contain: table_name, item_id, att_categories_id
     * @return array list of added files information id, fname, fsize, ext, filepath
     * @throws ApplicationException
     * @throws DBException
     */
    public function uploadMulti(array $item): array {
        $result = [];
        foreach ($_FILES as $field_name => $file) {
            if ($file['size'] > 0) {
                // add att db record
                $itemdb           = $item;
                $itemdb['status'] = self::STATUS_UNDER_UPDATE; // under upload
                $id               = $this->add($itemdb);

                $resone = $this->uploadOne($id, $file, true);
                if ($resone) {
                    $resone['id'] = $id;
                    $result[]     = $resone;
                }
            }
        }
        return $result;
    }

    /**
     * Update tmp uploads to be linked to specific entity id
     * @param string $entity_icode
     * @param int $item_id
     * @return bool
     * @throws DBException
     * @throws NoModelException
     */
    public function updateTmpUploads(string $entity_icode, int $item_id): bool {
        $fwentities_id = FwEntities::i()->idByIcodeOrAdd($entity_icode);

        $where = array(
            "fwentities_id" => $fwentities_id,
            "iname"         => $this->db->opLIKE("TMP#%"),
            "status"        => self::STATUS_DELETED,
            "item_id"       => $this->db->opISNULL(),
        );
        $this->db->update($this->table_name, array(
            "status"  => self::STATUS_ACTIVE,
            "item_id" => $item_id,
        ), $where);
        return true;
    }

    /**
     * permanently removes any temporary uploads older than 48h
     * @return int number of uploads deleted
     * @throws DBException
     */
    public function cleanupTmpUploads(): int {
        $where = array(
            "add_time" => $this->db->opLT("DATEADD(hour, -48, getdate())"),
            "status"   => $this->db->opIN([self::STATUS_UNDER_UPDATE, self::STATUS_DELETED]),
            "iname"    => $this->db->opLIKE("TMP#%"),
        );
        $rows  = $this->db->arrp($this->table_name, $where);
        foreach ($rows as $row) {
            $this->delete($row["id"], true);
        }
        return count($rows);
    }

    //return correct url
    public function getUrl(int $id, string $size = ''): string {
        $item = $this->one($id);
        if (!$item) {
            return '';
        }

        if ($item['storage'] == self::STORAGE_S3) {
            $result = S3::i()->getSignedUrl($this->getS3KeyByID($id), $size);
        } else {
            #if /Att need to be on offline folder
            $result = $this->fw->GLOBAL['ROOT_URL'] . '/Att/' . $id;
            if ($size > '') {
                $result .= '?size=' . $size;
            }
        }

        return $result;
    }

    /**
     * return correct url - direct, i.e. not via /Att
     * @param array|int $id_or_item item id or item array (must contain: id, ext)
     * @param string $size
     * @return string
     */
    public function getUrlDirect(array|int $id_or_item, string $size = ''): string {
        if (is_array($id_or_item)) {
            $item = $id_or_item;
            if ($item['storage'] == self::STORAGE_S3) {
                return S3::i()->getSignedUrl($this->getS3KeyByID($item['id']), $size);
            } elseif ($item['storage'] == self::STORAGE_TABLE) {
                return $this->getUrl($item['id'], $size); // don't have direct url for table storage
            } else {
                return $this->getUploadUrl($item['id'], $item['ext'], $size);
            }
        } else {
            $id   = intval($id_or_item);
            $item = $this->one($id);
            if (!$item) {
                return '';
            }

            return $this->getUrlDirect($item, $size);
        }
    }

    /**
     * return mime type for extension
     * @param string $ext extension - doc, jpg, ... (dot is optional)
     * @return string mime type or application/octetstream if not found
     */
    public function getMimeForExt(string $ext): string {
        $map = Utils::qh(self::MIME_MAP);
        $ext = preg_replace("/^\./", "", $ext); // remove dot if any

        $result = "application/octetstream";
        if (isset($map[$ext])) {
            $result = $map[$ext];
        }

        return $result;
    }

    public function delete(int $id, bool $is_perm = false): bool {
        // also delete from related tables:
        // users.att_id -> null?
        // spages.head_att_id -> null?
        if ($is_perm) {
            // delete from att_links only if perm
            AttLinks::i()->deleteByAtt($id);

            // remove files first
            $item = $this->one($id);
            if ($item["storage"] == self::STORAGE_S3) {
                //delete whole folder
                S3::i()->deleteObject($this->table_name . "/" . $item["id"]);

            } elseif ($item["storage"] == self::STORAGE_TABLE) {
                $this->update($id, [
                    'raw'   => null,
                    'raw_s' => null,
                    'raw_m' => null,
                    'raw_l' => null,
                ]);

            } else {
                // local storage
                $this->deleteLocalFiles($id);
            }
        }

        return parent::delete($id, $is_perm);
    }

    public function deleteLocalFiles(int $id): void {
        $item = $this->one($id);

        $filepath = $this->getUploadImgPath($id, "", $item["ext"]);
        if ($filepath > '') {
            unlink($filepath);
        }
        // for images - also delete s/m thumbnails
        if ($item["is_image"]) {
            foreach (self::THUMB_SIZES as $size) {
                $filepath = $this->getUploadImgPath($id, $size, $item["ext"]);
                if ($filepath > '') {
                    unlink($filepath);
                }
            }
        }
    }

    /**
     * check access rights for current user for the file by id
     * @param int|string $id
     * @param string $action
     * @return void
     * @throws AuthException
     */
    public function checkAccess(int|string $id = 0, string $action = ""): void {
        $item = $this->one($id);

        if ($item["status"] != self::STATUS_ACTIVE) {
            throw new AuthException("Access Denied. You don't have enough rights to get this file");
        }
    }

    /**
     * transmit file by id/size to user's browser, optional disposition - attachment(default)/inline
     * also check access rights - throws ApplicationException if file not accessible by cur user
     * if no file found OR file status<>0 - throws ApplicationException
     * Optimized: returns 304 if file not modified according to If-Modified-Since http header
     * @param int $id
     * @param string $size - s, m, l, xl
     * @param string $disposition - attachment(default)/inline
     * @param bool $is_private - if true - send private cache headers, instead of public
     * @return void
     * @throws ApplicationException
     */
    public function transmitFile(int $id, string $size = '', string $disposition = 'attachment', bool $is_private = false): void {
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
     * list all files linked to item_id via att_links, optionally filtered by is_image and category_icode
     * @param string $entity_icode
     * @param int $item_id
     * @param int $is_image
     * @param string $category_icode
     * @return array
     * @throws DBException
     * @throws NoModelException
     */
    public function listLinked(string $entity_icode, int $item_id, int $is_image = -1, string $category_icode = ""): array {
        $fwentities_id = FwEntities::i()->idByIcodeOrAdd($entity_icode);

        $where                   = "";
        $params                  = array();
        $params["fwentities_id"] = $fwentities_id;
        $params["item_id"]       = $item_id;

        if ($is_image > -1) {
            $where              .= " and a.is_image=@is_image";
            $params["is_image"] = $is_image;
        }
        if ($category_icode > '') {
            $att_category = AttCategories::i()->oneByIcode($category_icode);
            if (count($att_category) > 0) {
                $where                       .= " and a.att_categories_id=@att_categories_id";
                $params["att_categories_id"] = $att_category["id"];
            }
        }

        $links_table = AttLinks::i()->qTable();

        return $this->db->arrp("select a.* 
                from {$this->qTable()} a 
                    INNER JOIN $links_table al ON (al.att_id=a.id)
             where al.fwentities_id=@fwentities_id 
               and al.item_id=@item_id 
                $where 
             order by a.id", $params);
    }

    /**
     * return first linked file (or image) to item_id via att_links
     * @param string $entity_icode
     * @param int $item_id
     * @param int $is_image
     * @return array
     * @throws DBException
     * @throws NoModelException
     */
    public function oneFirstLinked(string $entity_icode, int $item_id, int $is_image = -1): array {
        $fwentities_id = FwEntities::i()->idByIcodeOrAdd($entity_icode);

        $where                    = "";
        $params                   = array();
        $params["@fwentities_id"] = $fwentities_id;
        $params["@item_id"]       = $item_id;

        if ($is_image > -1) {
            $where               .= " and a.is_image=@is_image";
            $params["@is_image"] = $is_image;
        }

        return $this->db->rowp("SELECT a.* from " . $this->db->qid(AttLinks::i()->table_name) . " al, " . $this->db->qid($this->table_name) . " a" .
            " WHERE al.fwentities_id=@fwentities_id " .
            "   and al.item_id=@item_id " .
            "   and a.id=al.att_id " .
            $where .
            " order by a.id", $params);
    }

    /**
     * return all att images linked via att_links to item_id
     * @param string $entity_icode
     * @param int $item_id
     * @return array
     * @throws DBException
     * @throws NoModelException
     */
    public function listLinkedImages(string $entity_icode, int $item_id): array {
        return $this->listLinked($entity_icode, $item_id, 1);
    }

    /**
     * return all att files linked via att.fwentities_id and att.item_id
     * @param string $entity_icode
     * @param int $item_id
     * @param int $is_image -1 - all, 0 - not image, 1 - image
     * @return array
     * @throws DBException
     * @throws NoModelException
     */
    public function listByEntity(string $entity_icode, int $item_id, int $is_image = -1): array {
        $fwentities_id = FwEntities::i()->idByIcodeOrAdd($entity_icode);

        $where = array(
            'status'        => self::STATUS_ACTIVE,
            'fwentities_id' => $fwentities_id,
            'item_id'       => $item_id,
        );
        if ($is_image > -1) {
            $where['is_image'] = $is_image;
        }

        return $this->db->arr($this->table_name, $where, 'id');
    }

    /**
     * return list of records for the att category where status=0 order by add_time desc
     * @param int $att_categories_id
     * @return array
     * @throws DBException
     */
    public function listByCategory(int $att_categories_id): array {
        $where = array(
            'status' => 0,
        );
        if ($att_categories_id > 0) {
            $where['att_categories_id'] = $att_categories_id;
        }

        return $this->db->arr($this->table_name, $where, 'add_time desc');
    }

    /**
     * return one att record with additional check by entity
     * @param int $id
     * @param string $entity_icode
     * @return array
     * @throws NoModelException
     */
    public function oneWithEntityCheck(int $id, string $entity_icode): array {
        $fwentities_id = FwEntities::i()->idByIcodeOrAdd($entity_icode);

        $row = $this->one($id);
        if (intval($row["fwentities_id"]) != $fwentities_id) {
            $row = array();
        }
        return $row;
    }

    /**
     * return one att record by entity and item_id
     * @param int $entity_icode
     * @param int $item_id
     * @return array
     * @throws DBException
     * @throws NoModelException
     */
    public function oneByEntity(int $entity_icode, int $item_id): array {
        $fwentities_id = FwEntities::i()->idByIcodeOrAdd($entity_icode);

        return $this->db->row($this->table_name, array(
            'fwentities_id' => $fwentities_id,
            'item_id'       => $item_id,
        ));
    }

    // *********************** S3 support - only works with S3 model and Amazon SDK installed ***********************
    public function getS3KeyByID(int $id, string $size = ""): string {
        $sizestr = "";
        if ($size > '') {
            $sizestr = "_" . $size;
        }

        return $this->table_name . "/" . $id . "/" . $id . $sizestr;
    }

    public function redirectS3(int $id, string $size = ""): void {
        if ($this->fw->userId() == 0) {
            throw new AuthException(); // denied for non-logged
        }

        $url = S3::i()->getSignedUrl($this->getS3KeyByID($id, $size));
        fw::redirect($url);
    }

    /**
     * move file from local file storage to DB or S3 if default storage is not local file
     * @param int $id
     * @return bool
     * @throws DBException
     */
    public function moveToStorage(int $id): bool {
        if (self::STORAGE == self::STORAGE_FILE) {
            return true; # no need to move, all uploaded files initially already in file storage
        }

        if (self::STORAGE == self::STORAGE_TABLE) {
            return $this->moveToDB($id);
        }

        if (self::STORAGE == self::STORAGE_S3) {
            return $this->moveToS3($id);
        }

        return false;
    }

    /**
     * move file from local file storage to DB
     * @param int $id
     * @return bool
     * @throws DBException
     */
    public function moveToDB(int $id): bool {
        $item = $this->one($id);
        if ($item["storage"] == self::STORAGE_TABLE) {
            return true; # no need to move, all uploaded files initially already in file storage
        }

        $fields = [
            'storage' => self::STORAGE_TABLE
        ];
        foreach (self::ALL_SIZES as $size) {
            $path = $this->getUploadPath($id, $item["ext"], $size);
            if (file_exists($path)) {
                $fields["raw_" . $size] = file_get_contents($path);
            }
        }

        $this->update($id, $fields);
        $this->deleteLocalFiles($id);

        return true;
    }

    /**
     * move file from local file storage to S3
     * @param int $id
     * @return bool
     * @throws DBException
     * @throws NoModelException
     */
    public function moveToS3(int $id): bool {
        $result = true;
        $item   = $this->one($id);

        $S3 = S3::i();
        // upload all sizes if exists
        // id=47 -> /47/47 /47/47_s /47/47_m /47/47_l
        foreach (self::ALL_SIZES as $size) {
            $filepath = $this->getUploadImgPath($id, $size, $item["ext"]);
            if (!file_exists($filepath)) {
                continue;
            }

            $result = $S3->uploadLocalFile($this->getS3KeyByID($id, $size), $filepath, "inline");
            if (!$result) {
                break;
            }
        }

        if ($result) {
            $this->update($id, [
                'storage' => self::STORAGE_S3,
            ]);
            $this->deleteLocalFiles($id);
        }

        return $result;
    }

    /**
     * download file from S3 to filepath, if filepath is empty - download to tmp file and return full path
     * @param int $id att.id
     * @param string $size optional size for images
     * @param string $filepath filepath, if filepath is empty - download to tmp file and return full path
     * @return string downloaded file filepath or empty string if not success
     * @throws \Random\RandomException
     */
    public function downloadFromS3(int $id, string $size = "", string $filepath = ""): string {
        $item = $this->one($id);
        if ($item["storage"] != self::STORAGE_S3) {
            logger("att file not in S3");
            return "";
        }

        if (empty($filepath)) {
            $filepath = Utils::getTmpFilename() . $item["ext"];
        }

        return S3::i()->download($this->getS3KeyByID($id, $size), $filepath);
    }

    /**
     * upload all posted files ($_FILES) to S3 for the entity/table
     * @param string $entity_icode entity icode
     * @param int $item_id item id
     * @param string $att_categories_id optional category id
     * @param string $fieldnames qw string of ONLY field names to upload
     * @return int number of successuflly uploaded files
     *             also set FLASH error if some files not uploaded
     */
    public function uploadPostedFilesS3(string $entity_icode, int $item_id, string $att_categories_id = '', string $fieldnames = ''): int {
        $result        = 0;
        $fwentities_id = FwEntities::i()->idByIcodeOrAdd($entity_icode);
        $honlynames    = Utils::qh($fieldnames);

        // create list of eligible file uploads, check for the ContentLength as any 'input type = "file"' creates a System.Web.HttpPostedFile object even if the file was not attached to the input
        $afiles = array();
        if (count($honlynames) > 0) {
            // if we only need some fields - skip if not requested field
            foreach ($_FILES as $file) {
                if (!isset($honlynames[$file['name']])) {
                    continue;
                }
                if ($file['size'] > 0) {
                    $afiles[] = $file;
                }
            }
        } else {
            // just add all files
            foreach ($_FILES as $file) {
                if ($file['size'] > 0) {
                    $afiles[] = $file;
                }
            }
        }

        // do nothing if empty file list
        if (count($afiles) == 0) {
            return 0;
        }

        // upload files to the S3
        $S3 = S3::i();

        // create /att folder
        $S3->createFolder($this->table_name);

        // upload files to S3
        foreach ($afiles as $file) {
            // first - save to db so we can get att_id
            $attitem                      = array();
            $attitem['att_categories_id'] = $att_categories_id;
            $attitem['fwentities_id']     = $fwentities_id;
            $attitem['item_id']           = $item_id;
            $attitem['storage']           = self::STORAGE_S3;
            $attitem['status']            = self::STATUS_UNDER_UPDATE;
            $attitem['fname']             = $file['name'];
            $attitem['fsize']             = $file['size'];
            $attitem['ext']               = UploadUtils::uploadExt($file['name']);
            $att_id                       = $this->add($attitem);

            try {
                $S3->uploadPostedFile($this->getS3KeyByID($att_id), $file, "inline");

                // TODO check response for 200 and if not - error/delete?
                // once uploaded - mark in db as uploaded
                $this->update($att_id, ['status' => self::STATUS_ACTIVE]);

                $result += 1;
            } catch (Exception $ex) {
                logger($ex->getMessage());
                logger($ex);
                $this->fw->flash("error", "Some files were not uploaded due to error. Please re-try.");
                // if error - don't set status to 0 but remove att record
                $this->delete($att_id, true);
            }
        }

        return $result;
    }

}
