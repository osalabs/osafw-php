<?php
/*
Part of PHP osa framework  www.osalabs.com/osafw/php
(c) 2009-2015 Oleg Savchuk www.osalabs.com
*/

require_once dirname(__FILE__)."/ImageUtils.php";

class UploadUtils {
    public static $DEFAULT_ID2DIR_LEVEL=1;
    //extensions upload functions check for (ondisk)
    public static $UPLOAD_EXT=array('.jpg','.gif','.png');
    public static $IMG_EXT=array('.jpg','.gif','.png'); //image extensions
    public static $IMG_RESIZE_DEF=array( //default options for upload resize
                  ''   =>  1,
                  's'  =>  1,
                  'm'  =>  1,
                  'l'  =>  1,
                );
    public static $MIME_MAP = "doc|application/msword docx|application/msword xls|application/vnd.ms-excel xlsx|application/vnd.ms-excel ppt|application/vnd.ms-powerpoint pptx|application/vnd.ms-powerpoint pdf|application/pdf html|text/html zip|application/x-zip-compressed jpg|image/jpeg jpeg|image/jpeg gif|image/gif png|image/png wmv|video/x-ms-wmv avi|video/x-msvideo";

    public static function getMimeForExt($ext=''){
        $map = Utils::qh(self::$MIME_MAP);
        $ext = preg_replace("/^\./", "", $ext); #remove dot if any
        if (array_key_exists($ext, $map)){
            $result=$map[$ext];
        }else{
            $result="application/octetstream";
        }
        return $result;
    }
    public static function getUploadBaseDir(){
        return fw::i()->config->PUBLIC_UPLOAD_DIR;
    }
    public static function getUploadBaseUrl(){
        return fw::i()->config->PUBLIC_UPLOAD_URL;
    }

    /**
     * return array of posted files from $_FILES, each array element contains: name, type, tmp_name, error, size
     * @param  string $field_name posted form field name
     * @return array of assoc arrays (even if one file posted)
     */
    public static function getPostedFiles($field_name) {
        $files=array();
        $fdata=$_FILES[$field_name];

        if ( is_array($fdata['name']) ){
          for($i=0;$i<count($fdata['name']);++$i){
              $files[]=array(
                  'name'      =>  $fdata['name'][$i],
                  'type'      =>  $fdata['type'][$i],
                  'tmp_name'  =>  $fdata['tmp_name'][$i],
                  'error'     =>  $fdata['error'][$i],
                  'size'      =>  $fdata['size'][$i]
              );
          }
        } else {
          if (isset($fdata)){
              $files[]=$fdata;
          }
        }

        return $files;
    }

    //return file extension with dot, lowercased, taking care of jpeg
    public static function uploadExt($filename) {
      $pp=pathinfo($filename);
      return '.'.self::jpeg2jpg(strtolower($pp['extension']));
    }

    public static function jpeg2jpg($str) {
      if ($str=='jpeg') $str='jpg';
      return $str;
    }

    /**
     * check if extension is image extension
     * @param  string  $ext extension with dot
     * @return boolean      true if
     */
    public static function isImgExt($ext){
        return in_array(strtolower($ext), self::$IMG_EXT);
    }

    //check for:
    //      uploaded file not empty
    //      tmp file still exists
    //      extension is allowed (checked ONLY if $allowed_ext non-empty)
    //      size (TODO?)
    // sample:
    // UploadUtils::isUploadValid($_FILES['file'], array(.jpg', '.png'))
    public static function isUploadValid($file, $allowed_ext=array()) {
      $result=false;
      if ($file && $file['name']>'') {
          if ( strlen($file['tmp_name']) && file_exists($file['tmp_name']) ) {

              if (count($allowed_ext)){
                  $ext=self::uploadExt($file['name']);
                  if ( array_key_exists($ext, array_flip($allowed_ext)) ){
                      $result=true;
                  }
              }else{
                  $result=true;
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
     * @param  int      $item_id     item id
     * @param  string   $module_basedir basedir for module $id related to, id2dir path will be added to this
     * @param  array    $file        one assoc array from getPostedFiles()
     * @param  array    $opt         options:
     *                                   ext - force to save images in this format (example: .jpg instead of .jpeg)
     * @return string                ''(empty) if error (or no file or no $item_id), 'full path to uploaded file' if success
     */
    public static function uploadFile($item_id, $module_basedir, $file, $opt=array()) {
        $result='';
        if (!$item_id || !is_array($file) || !$module_basedir ) return '';
        logger('TRACE', "uploadFile: $item_id, $module_basedir", $file, $opt);

        self::cleanupUpload($item_id, $module_basedir);

        $ext=self::uploadExt($file['name']);
        if ($opt['ext']) $ext=$opt['ext']; #if required to save images in particular format - do this

        $file_path_orig=self::getUploadPath($item_id, $module_basedir, $ext);

        if ( move_uploaded_file($file['tmp_name'], $file_path_orig) ) {
            //uploaded successfully
            $result=$file_path_orig;

            //check other options
        }else{
            logger('WARN','Upload error during move_uploaded_file');
        }

        return $result;
    }

    /**
     * resize uploaded image and/or create thumbnails in same dir
     *
     * @param  int      $item_id    item id
     * @param  array    $opt        assocarray for resizing original and/or creating previews
     *                                ''  => array(w,h) or just 1 (defaults for original used)
     *                                's' => array(w,h) or just 1 (defaults for s used)
     *                                'm' => array(w,h) or just 1 (defaults for l used)
     *                                'l' => array(w,h) or just 1 (defaults for l used)
     * @return none
     */
    public static function uploadResize($filepath, $opt=array()){
        $pp=pathinfo($filepath);
        $dir = $pp['dirname'];
        $ext = '.'.$pp['extension'];
        $item_id = $pp['filename']; //file name without ext
        if (!self::isImgExt($ext)) return;

        foreach ($opt as $size => $wh) {
            if ( !is_array($wh) ) $wh=ImageUtils::$MAX_RESIZE_WH[$size]; #use defaults
            if ( !is_array($wh) ) continue; #if no resize width/heigh for the $size - skip resize for this $size

            $_size = ($size>''?'_'.$size:'');
            $filepath_to=$dir.'/'.$item_id.$_size.$ext;
            #logger("resize: $filepath => $filepath_to, $wh[0], $wh[1]");
            ImageUtils::resize($filepath, $wh[0], $wh[1], $filepath_to);
        }
    }

    public static function mkdirTree($dir) {
        if ( !file_exists( $dir ) ) mkdir( $dir, 0777, true );
    }

    /**
     * id to directory (to help filesystem keep fast operations)
     * default level 1: 1,001 => /1
     * level 2: 1,000,001 => /1/0
     * level 3: 1,000,000,001 => /1/0/0
     *
     * @param  int    $id    item id
     * @param  int    $level optional, default in $DEFAULT_ID2DIR_LEVEL, dir deep level
     * @return string        path without trailing /
     */
    public static function id2dir($id, $level=NULL){
        if (is_null($level)) $level = self::$DEFAULT_ID2DIR_LEVEL;

        $id1=floor($id/1000);
        $result='';
        for($i=1;$i<=$level;$i++){
           if ($i==$level){
              $result=$id1.(strlen($result)?'/':'').$result;
              break;
           }
           $id2=floor($id1/1000);
           $result=($id1-$id2*1000).(strlen($result)?'/':'').$result;
           $id1=$id2;
        }

        return '/'.$result;
    }

    /**
     * return upload dir (autocreated if not exists) for the id and base dir, usually CONFIG("PUBLIC_UPLOAD_DIR")/module_name
     * Sample: UploadUtils::getUploadDir($avatar_id, UploadUtils::getUploadBaseDir().'/avatars')
     *
     * @param  int     $id          item id
     * @param  string  $module_basedir basedir for module $id related to, id2dir path will be added to this
     * @return string               path to directory (no trailing /)
     */
    public static function getUploadDir($id, $module_basedir){
        $dir = $module_basedir.self::id2dir($id);
        self::mkdirTree($dir);

        return $dir;
    }

    /**
     * return full path to uploaded file
     * @param  int     $id          item id
     * @param  string  $module_basedir basedir for module $id related to, id2dir path will be added to this
     * @param  string  $ext         file extension with dot
     * @param  string  $size        optional, s,m,l or ''(default) for original upload
     * @return string               absolute path to file
     */
    public static function getUploadPath($id, $module_basedir, $ext, $size=''){
        if ($size>'') $size='_'.$size;
        $path = self::getUploadDir($id, $module_basedir).'/'.$id.$size.$ext;

        return $path;
    }

    /**
     * return url for the item
     * @param  string  $module_name name of the module $id related to, used in upload path
     * @param  int     $id          item id
     * @param  string  $module_baseurl base url for module $id related to, id2dir path will be added to this
     * @param  string  $ext         optional, file extension with dot, if not passed - all self::$UPLOAD_EXT checked for file existence (if $nocheck not set to true)
     * @param  string  $size        optional, s,m,l or ''(default) for original upload
     * @return string               direct url to file
     */
    public static function getUploadUrl($id, $module_baserul, $ext, $size=''){
        if ($size>'') $size='_'.$size;
        $url = $module_baserul.self::id2dir($id).'/'.$id.$size.$ext;

        return $url;
    }

    /**
     * remove all files with extensions in $UPLOAD_EXT for $item_id in the destination $dir
     * sample usage: UploadUtils::cleanupUpload($id, UploadUtils::getUploadDir('avatars',$id))
     *
     * @param  int      $id  item id
     * @param  string   $dir destination dir
     * @param  string   $ext optional, explicit extension to cleanup
     * @return none
     */
    public static function cleanupUpload($id, $module_basedir, $ext=''){
        $diskpath=self::getUploadDir($id, $module_basedir)."/".$id;

        if ($ext>''){
            $acheck = array($ext);
        }else{
            $acheck = self::$UPLOAD_EXT;
        }

        foreach ($acheck as $ext){
            @unlink($diskpath.$ext);
            if (self::isImgExt($ext)){
                @unlink($diskpath.'_s'.$ext);
                @unlink($diskpath.'_m'.$ext);
                @unlink($diskpath.'_l'.$ext);
            }
        }
    }

    /**
     * check if size have allowed value and return it. Return '' (empty string) in other cases
     * @param  string $size size for the uploaded file
     * @return string       size for the uploaded file
     */
    public static function checkSize($size){
        if ($size<>'s' && $size<>'m' && $size<>'l') $size='';
        return $size;
    }
}