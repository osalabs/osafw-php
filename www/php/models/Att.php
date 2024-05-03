<?php
/*
 Att model class

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2024 Oleg Savchuk www.osalabs.com
*/

class Att extends FwModel {
    public const string IMGURL_0    = "/img/0.gif";
    public const string IMGURL_FILE = "/img/att_file.png";

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
            $fields = array();
            if ($is_new) {
                $fields["iname"] = $file['name'];
            }

            $fields["fname"]  = $file['name'];
            $fields["fsize"]  = filesize($filepath);
            $fields["ext"]    = $ext;
            $fields["status"] = self::STATUS_ACTIVE; // finished upload - change status to active
            // turn on image flag if it's an image
            if (UploadUtils::isImgExt($ext)) {
                // if it's an image - turn on flag and resize for thumbs
                $fields["is_image"] = 1;

                ImageUtils::resize($filepath, self::MAX_THUMB_W_S, self::MAX_THUMB_H_S, $this->getUploadImgPath($id, "s", $ext));
                ImageUtils::resize($filepath, self::MAX_THUMB_W_M, self::MAX_THUMB_H_M, $this->getUploadImgPath($id, "m", $ext));
                ImageUtils::resize($filepath, self::MAX_THUMB_W_L, self::MAX_THUMB_H_L, $this->getUploadImgPath($id, "l", $ext));
            }

            $this->update($id, $fields);
            $fields["filepath"] = $filepath;
            $result             = $fields;

            $this->moveToS3($id);
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
        $result = array();
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

        $result = '';
        if ($item['is_s3']) {
            //TODO $result = S3::i()->getSignedUrl($this->getS3KeyByID($id), $size);
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
            if ($item['is_s3']) {
                //TODO return S3::i()->getSignedUrl($this->getS3KeyByID($item['id']), $size);
                return '';
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
            if ($item["is_s3"]) {
                //TODO S3::i()->deleteObject($this->table_name . "/" . $item["id"]);
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
            foreach (Utils::qw("s m l") as $size) {
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

        $where                    = "";
        $params                   = array();
        $params["@fwentities_id"] = $fwentities_id;
        $params["@item_id"]       = $item_id;

        if ($is_image > -1) {
            $where               .= " and a.is_image=@is_image";
            $params["@is_image"] = $is_image;
        }
        if ($category_icode > '') {
            $att_category = AttCategories::i()->oneByIcode($category_icode);
            if (count($att_category) > 0) {
                $where                        .= " and a.att_categories_id=@att_categories_id";
                $params["@att_categories_id"] = $att_category["id"];
            }
        }

        return $this->db->arrp("select a.* " .
            " from " . $this->db->qid(AttLinks::i()->table_name) . " al, " . $this->db->qid($this->table_name) . " a " .
            " where al.fwentities_id=@fwentities_id " .
            "   and al.item_id=@item_id " .
            "   and a.id=al.att_id " .
            $where .
            " order by a.id", $params);
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

        throw new ApplicationException("Not implemented");
        //TODO
        //        $url = S3::i()->getSignedUrl($this->getS3KeyByID($id, $size));
        //        $this->fw->redirect($url);
    }

    /**
     * move file from local file storage to S3
     * @param int $id
     * @return bool
     * @throws DBException
     */
    public function moveToS3(int $id): bool {
        return false; // TODO IMPLEMENT

        if (!S3::IS_ENABLED) {
            return false;
        }

        $result = true;
        $item   = $this->one($id);
        if (intval($item["is_s3"]) == 1) {
            return true; // already in S3
        }

        $model_s3 = S3::i();
        // model_s3.createFolder(Me.table_name)
        // upload all sizes if exists
        // id=47 -> /47/47 /47/47_s /47/47_m /47/47_l
        foreach (Utils::qw(" s m l") as $size1) {
            $size     = trim($size1);
            $filepath = $this->getUploadImgPath($id, $size, $item["ext"]);
            if (!file_exists($filepath)) {
                continue;
            }

            $result = $model_s3->uploadLocalFile($this->getS3KeyByID($id, $size), $filepath, "inline");
            if (!$result) {
                break;
            }
        }

        if ($result) {
            // mark as uploaded
            $this->update($id, array("is_s3" => "1"));
            // remove local files
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
        return ""; //TODO IMPLEMENT

        $item = $this->one($id);
        if ($item["is_s3"] != "1") {
            logger("att file not in S3");
            return "";
        }

        if (Utils::isEmpty($filepath)) {
            $filepath = Utils::getTmpFilename() . $item["ext"];
        }

        return S3::i()->download($this->getS3KeyByID($id, $size), $filepath);
    }

    /*
     *     /// <summary>
    /// upload all posted files (fw.request.Form.Files) to S3 for the table
    /// </summary>
    /// <param name="entity_icode"></param>
    /// <param name="item_id"></param>
    /// <param name="att_categories_id"></param>
    /// <param name="fieldnames">qw string of ONLY field names to upload</param>
    /// <returns>number of successuflly uploaded files</returns>
    /// <remarks>also set FLASH error if some files not uploaded</remarks>
    public int uploadPostedFilesS3(string entity_icode, int item_id, string att_categories_id = null, string fieldnames = "")
    {
        var result = 0;
        var fwentities_id = fw.model<FwEntities>().idByIcodeOrAdd(entity_icode);
        var honlynames = Utils.qh(fieldnames);

        // create list of eligible file uploads, check for the ContentLength as any 'input type = "file"' creates a System.Web.HttpPostedFile object even if the file was not attached to the input
        ArrayList afiles = new();
        if (honlynames.Count > 0)
        {
            // if we only need some fields - skip if not requested field
            for (var i = 0; i <= fw.request.Form.Files.Count - 1; i++)
            {
                if (!honlynames.ContainsKey(fw.request.Form.Files[i].FileName))
                    continue;
                if (fw.request.Form.Files[i].Length > 0)
                    afiles.Add(fw.request.Form.Files[i]);
            }
        }
        else
            // just add all files
            for (var i = 0; i <= fw.request.Form.Files.Count - 1; i++)
            {
                if (fw.request.Form.Files[i].Length > 0)
                    afiles.Add(fw.request.Form.Files[i]);
            }

        // do nothing if empty file list
        if (afiles.Count == 0)
            return 0;

        // upload files to the S3
        var model_s3 = fw.model<S3>();

        // create /att folder
        model_s3.createFolder(this.table_name);

        // upload files to S3
        foreach (IFormFile file in afiles)
        {
            // first - save to db so we can get att_id
            Hashtable attitem = new();
            attitem["att_categories_id"] = att_categories_id;
            attitem["fwentities_id"] = fwentities_id;
            attitem["item_id"] = Utils.f2str(item_id);
            attitem["is_s3"] = "1";
            attitem["status"] = "1";
            attitem["fname"] = file.FileName;
            attitem["fsize"] = Utils.f2str(file.Length);
            attitem["ext"] = UploadUtils.getUploadFileExt(file.FileName);
            var att_id = fw.model<Att>().add(attitem);

            try
            {
                model_s3.uploadPostedFile(getS3KeyByID(att_id.ToString()), file, "inline");

                // TODO check response for 200 and if not - error/delete?
                // once uploaded - mark in db as uploaded
                fw.model<Att>().update(att_id, new Hashtable() { { "status", "0" } });

                result += 1;
            }
            catch (Exception ex)
            {
                logger(ex.Message);
                logger(ex);
                fw.flash("error", "Some files were not uploaded due to error. Please re-try.");
                // TODO if error - don't set status to 0 but remove att record?
                fw.model<Att>().delete(att_id, true);
            }
        }

        return result;
    }
     * */

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
        $model_s3 = S3::i();

        // create /att folder
        $model_s3->createFolder($this->table_name);

        // upload files to S3
        foreach ($afiles as $file) {
            // first - save to db so we can get att_id
            $attitem                      = array();
            $attitem['att_categories_id'] = $att_categories_id;
            $attitem['fwentities_id']     = $fwentities_id;
            $attitem['item_id']           = $item_id;
            $attitem['is_s3']             = 1;
            $attitem['status']            = 1;
            $attitem['fname']             = $file['name'];
            $attitem['fsize']             = $file['size'];
            $attitem['ext']               = UploadUtils::uploadExt($file['name']);
            $att_id                       = $this->add($attitem);

            try {
                $model_s3->uploadPostedFile($this->getS3KeyByID($att_id), $file, "inline");

                // TODO check response for 200 and if not - error/delete?
                // once uploaded - mark in db as uploaded
                $this->update($att_id, ['status' => 0]);

                $result += 1;
            } catch (Exception $ex) {
                logger($ex->getMessage());
                logger($ex);
                $this->fw->flash("error", "Some files were not uploaded due to error. Please re-try.");
                // TODO if error - don't set status to 0 but remove att record?
                $this->delete($att_id, true);
            }
        }

        return $result;
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

}
