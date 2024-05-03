<?php
/*
Part of PHP osa framework  www.osalabs.com/osafw/php
(c) 2009-2024 Oleg Savchuk www.osalabs.com
*/

class UploadUtils {
    public static int $DEFAULT_ID2DIR_LEVEL = 1;
    //extensions upload functions check for (ondisk)
    public static array $UPLOAD_EXT = array('.jpg', '.gif', '.png');
    public static array $IMG_EXT = array('.jpg', '.gif', '.png'); //image extensions
    public static array $IMG_RESIZE_DEF = array( //default options for upload resize
                                                 ''  => 1,
                                                 's' => 1,
                                                 'm' => 1,
                                                 'l' => 1,
    );
    public static string $MIME_MAP = "doc|application/msword docx|application/msword xls|application/vnd.ms-excel xlsx|application/vnd.ms-excel ppt|application/vnd.ms-powerpoint pptx|application/vnd.ms-powerpoint pdf|application/pdf html|text/html zip|application/x-zip-compressed jpg|image/jpeg jpeg|image/jpeg gif|image/gif png|image/png wmv|video/x-ms-wmv avi|video/x-msvideo";

    public static function getMimeForExt($ext = '') {
        $map = Utils::qh(self::$MIME_MAP);
        $ext = preg_replace("/^\./", "", $ext); #remove dot if any
        if (array_key_exists($ext, $map)) {
            $result = $map[$ext];
        } else {
            $result = "application/octetstream";
        }
        return $result;
    }

    public static function getUploadBaseDir() {
        return fw::i()->config->PUBLIC_UPLOAD_DIR;
    }

    public static function getUploadBaseUrl() {
        return fw::i()->config->PUBLIC_UPLOAD_URL;
    }

    /**
     * return array of posted files from $_FILES, each array element contains: name, type, tmp_name, error, size
     * @param string $field_name posted form field name
     * @return array of assoc arrays (even if one file posted)
     */
    public static function getPostedFiles(string $field_name): array {
        $files = array();
        $fdata = $_FILES[$field_name] ?? null;
        if (!isset($fdata)) {
            return $files;
        }

        if (is_array($fdata['name'])) {
            for ($i = 0; $i < count($fdata['name']); ++$i) {
                $files[] = array(
                    'name'     => $fdata['name'][$i],
                    'type'     => $fdata['type'][$i],
                    'tmp_name' => $fdata['tmp_name'][$i],
                    'error'    => $fdata['error'][$i],
                    'size'     => $fdata['size'][$i]
                );
            }
        } else {
            $files[] = $fdata;
        }

        return $files;
    }

    //return file extension with dot, lowercased, taking care of jpeg
    public static function uploadExt(string $filename): string {
        $pp = pathinfo($filename);
        return '.' . self::jpeg2jpg(strtolower($pp['extension']));
    }

    public static function jpeg2jpg(string $str) {
        if ($str == 'jpeg') {
            $str = 'jpg';
        }
        return $str;
    }

    /**
     * check if extension is image extension
     * @param string $ext extension with dot
     * @return boolean      true if
     */
    public static function isImgExt(string $ext): bool {
        return in_array(strtolower($ext), self::$IMG_EXT);
    }

    //check for:
    //      uploaded file not empty
    //      tmp file still exists
    //      extension is allowed (checked ONLY if $allowed_ext non-empty)
    //      size (TODO?)
    // sample:
    // UploadUtils::isUploadValid($_FILES['file'], array(.jpg', '.png'))
    public static function isUploadValid($file, $allowed_ext = array()): bool {
        $result = false;
        if ($file && $file['name'] > '') {
            if (strlen($file['tmp_name']) && file_exists($file['tmp_name'])) {

                if (count($allowed_ext)) {
                    $ext = self::uploadExt($file['name']);
                    if (array_key_exists($ext, array_flip($allowed_ext))) {
                        $result = true;
                    }
                } else {
                    $result = true;
                }

            }
        }

        return $result;
    }

    /*
    upload file to /upload_dir/0/0/0/0/$id.$ext
    options:

    return:
    -1 - upload failed
    0 - no file - empty item_id or $file or $upload_path or $field_name (i.e. user didn't selected a file for upload)
    1 - upload successfull
    */
    /**
     * upload file to some directory ($module_basedir/0/0/0/0/$id.$ext) for related $id and options
     * @param int $item_id item id
     * @param string $module_basedir basedir for module $id related to, id2dir path will be added to this
     * @param array $file one assoc array from $_FILES
     * @param array $opt options:
     *                                   ext - force to save images in this format (example: .jpg instead of .jpeg)
     * @return string                ''(empty) if error (or no file or no $item_id), 'full path to uploaded file' if success
     */
    public static function uploadFile(int $item_id, string $module_basedir, array $file, array $opt = array()): string {
        $result = '';
        if (!$item_id || !$file || !$module_basedir) {
            return '';
        }
        logger('TRACE', "uploadFile: $item_id, $module_basedir", $file, $opt);

        self::cleanupUpload($item_id, $module_basedir);

        $ext = self::uploadExt($file['name']);
        if ($opt['ext']) {
            $ext = $opt['ext'];
        } #if required to save images in particular format - do this

        $file_path_orig = self::getUploadPath($item_id, $module_basedir, $ext);

        if (move_uploaded_file($file['tmp_name'], $file_path_orig)) {
            //uploaded successfully
            $result = $file_path_orig;

            //check other options
        } else {
            logger('WARN', 'Upload error during move_uploaded_file');
        }

        return $result;
    }

    public static function mkdirTree($dir): void {
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    /**
     * id to directory (to help filesystem keep fast operations)
     * default level 1: 1,001 => /1
     * level 2: 1,000,001 => /1/0
     * level 3: 1,000,000,001 => /1/0/0
     *
     * @param int $id item id
     * @param int|null $level optional, default in $DEFAULT_ID2DIR_LEVEL, dir deep level
     * @return string        path without trailing /
     */
    public static function id2dir(int $id, int $level = NULL): string {
        if (is_null($level)) {
            $level = self::$DEFAULT_ID2DIR_LEVEL;
        }

        $id1    = floor($id / 1000);
        $result = '';
        for ($i = 1; $i <= $level; $i++) {
            if ($i == $level) {
                $result = $id1 . (strlen($result) ? '/' : '') . $result;
                break;
            }
            $id2    = floor($id1 / 1000);
            $result = ($id1 - $id2 * 1000) . (strlen($result) ? '/' : '') . $result;
            $id1    = $id2;
        }

        return '/' . $result;
    }

    /**
     * return upload dir (autocreated if not exists) for the id and base dir, usually CONFIG("PUBLIC_UPLOAD_DIR")/module_name
     * Sample: UploadUtils::getUploadDir($avatar_id, UploadUtils::getUploadBaseDir().'/avatars')
     *
     * @param int $id item id
     * @param string $module_basedir basedir for module $id related to, id2dir path will be added to this
     * @return string               path to directory (no trailing /)
     */
    public static function getUploadDir(int $id, string $module_basedir): string {
        $dir = $module_basedir . self::id2dir($id);
        self::mkdirTree($dir);

        return $dir;
    }

    /**
     * return full path to uploaded file
     * @param int $id item id
     * @param string $module_basedir basedir for module $id related to, id2dir path will be added to this
     * @param string $ext file extension with dot
     * @param string $size optional, s,m,l or ''(default) for original upload
     * @return string               absolute path to file
     */
    public static function getUploadPath(int $id, string $module_basedir, string $ext, string $size = ''): string {
        if ($size > '') {
            $size = '_' . $size;
        }
        $path = self::getUploadDir($id, $module_basedir) . '/' . $id . $size . $ext;

        return $path;
    }

    /**
     * return url for the item
     * @param int $id item id
     * @param string $module_basedir
     * @param string $module_baserul
     * @param string $ext optional, file extension with dot, if not passed - all self::$UPLOAD_EXT checked for file existence (if $nocheck not set to true)
     * @param string $size optional, s,m,l or ''(default) for original upload
     * @return string               direct url to file
     */
    public static function getUploadUrl(int $id, string $module_basedir, string $module_baserul, string $ext, string $size = ''): string {
        if ($size > '') {
            $size = '_' . $size;
        }
        $url = $module_baserul . self::id2dir($id) . '/' . $id . $size . $ext;

        return $url;
    }

    /**
     * remove all files with extensions in $UPLOAD_EXT for $item_id in the destination $dir
     * sample usage: UploadUtils::cleanupUpload($id, UploadUtils::getUploadDir('avatars',$id))
     *
     * @param int $id item id
     * @param string $module_basedir destination directory
     * @param string $ext optional, explicit extension to cleanup
     * @return void
     */
    public static function cleanupUpload(int $id, string $module_basedir, string $ext = ''): void {
        $diskpath = self::getUploadDir($id, $module_basedir) . "/" . $id;

        if ($ext > '') {
            $acheck = array($ext);
        } else {
            $acheck = self::$UPLOAD_EXT;
        }

        foreach ($acheck as $ext) {
            @unlink($diskpath . $ext);
            if (self::isImgExt($ext)) {
                @unlink($diskpath . '_s' . $ext);
                @unlink($diskpath . '_m' . $ext);
                @unlink($diskpath . '_l' . $ext);
            }
        }
    }

    /**
     * check if size have allowed value and return it. Return '' (empty string) in other cases
     * @param string $size size for the uploaded file
     * @return string       size for the uploaded file
     */
    public static function checkSize(string $size): string {
        if ($size <> 's' && $size <> 'm' && $size <> 'l') {
            $size = '';
        }
        return $size;
    }

    public static function removeUploadImgByPath(string $path): bool {
        $dir  = dirname($path);
        $path = $dir . "/" . pathinfo($path, PATHINFO_FILENAME); // cut extension if any

        if (!file_exists($dir)) {
            return false;
        }

        @unlink($path . "_l.png");
        @unlink($path . "_l.gif");
        @unlink($path . "_l.jpg");

        @unlink($path . "_m.png");
        @unlink($path . "_m.gif");
        @unlink($path . "_m.jpg");

        @unlink($path . "_s.png");
        @unlink($path . "_s.gif");
        @unlink($path . "_s.jpg");

        @unlink($path . ".png");
        @unlink($path . ".gif");
        @unlink($path . ".jpg");
        return true;
    }

}
