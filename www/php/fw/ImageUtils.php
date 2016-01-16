<?php
/*
Part of PHP osa framework  www.osalabs.com/osafw/php
(c) 2009-2015 Oleg Savchuk www.osalabs.com

2013-06-20 added image_resize_fixed
*/

class ImageUtils {
  public static $IMG_RESIZE_JPG_QUALITY=90;
  public static $IMG_EXT=array('jpeg'=>'jpg','jpg'=>'jpg','gif'=>'gif','png'=>'png');
  public static $MAX_RESIZE_WH=array(
#                  ''    =>  array(1024,1024), #resize original - no resize if commented
                  's'  =>  array(150,150),
                  'm'  =>  array(300,300),
                  'l'  =>  array(600,600),
                );

  //accept image file, max width, max height and out_file (default output to same file)
  //return list(new_w, new_h)
  public static function image_resize($in_file, $maxw=0, $maxh=0, $out_file=''){

    if (!$out_file) $out_file=$in_file;
    if (!$maxw) $maxw=self::$MAX_RESIZE_WH[''][0];
    if (!$maxh) $maxh=self::$MAX_RESIZE_WH[''][1];

    # logger("resizing: [$in_file] $maxw/$maxh [$out_file]");

    $img_format='';
    $img=self::open_image($in_file, $img_format);

    if ($img==-1) return array(-1,-1); #no resize done because GD is not attached

    if (file_exists($in_file)){

      $old_w=imagesx($img);
      $old_h=imagesy($img);
    #    logger(".old: . $old_w,$old_h");

      $ratio=$old_w/$old_h;
      $w_scale=1;
      $h_scale=1;

      if ($maxw && $maxh){
         $w_scale=$old_w/$maxw;
         $h_scale=$old_h/$maxh;
      }

    #    logger(".scale: . $w_scale, $h_scale");
      if (($w_scale>1 || $h_scale>1)){
         if ($w_scale>$h_scale){
            $new_w=$maxw;
            $new_h=floor($new_w/$ratio);
         }
         else{
            $new_h=$maxh;
            $new_w=floor($new_h*$ratio);
         }

         if ($new_w!=$old_w || $new_h!=$old_h){
            $s_img=imagecreatetruecolor($new_w,$new_h);
            if ($img_format=='gif' || $img_format=='png'){  //for gif and png - keep transparency
               $trans_ind = imagecolortransparent($img);
               if ($trans_ind>0){ // If we have a specific transparent color
                  $trans_color = imagecolorsforindex($img, $trans_ind); // Get the original image's transparent color's RGB values
                  $trans_ind   = imagecolorallocate($s_img, $trans_color['red'], $trans_color['green'], $trans_color['blue']); // Allocate the same color in the new image resource

                  imagefill($s_img, 0, 0, $trans_ind); // Completely fill the background of the new image with allocated color.
                  imagecolortransparent($s_img, $trans_ind);  // Set the background color for new image to transparent

               }elseif ($img_format=='png') {
                  imagealphablending($s_img, false);  // Turn off transparency blending (temporarily)

                  $trans_color = imagecolorallocatealpha($s_img, 0, 0, 0, 127); // Create a new transparent color for image
                  imagefill($s_img, 0, 0, $trans_color);  // Completely fill the background of the new image with allocated color.
               }
            }

            $a=imagecopyresampled($s_img,$img,0,0,0,0,$new_w,$new_h,$old_w,$old_h);

            self::save_image($s_img, $out_file, self::image_type($out_file, $img_format) );

            imagedestroy($s_img);
         }

      }else if ($out_file!=$in_file){
         //juse make copy
         self::save_image($img, $out_file, $img_format);
      }

    #    logger("$new_w,$new_h ");
      return array($new_w,$new_h);

    }else{
      logger("ERROR: FILE NOT EXISTS $in_file");
      return array(0,0);
    }

  }

  //********* opens gif, png, jpg image with check, return img format via ref $img_format
  //throws exception if
  //  no GD installed
  public static function open_image($in_file, &$img_format){

    if ( ($img_format=='jpg' || preg_match("/\.jpe?g$/i", $in_file)) && function_exists('imagecreatefromjpeg') ){
      $img_format='jpg';
      $img  = imagecreatefromjpeg($in_file);
    }
    elseif ( ($img_format=='gif' || preg_match("/\.gif$/i", $in_file)) && function_exists('imagecreatefromgif') ){
      $img_format='gif';
      $img  = imagecreatefromgif($in_file);
    }
    elseif ( ($img_format=='png' || preg_match("/\.png$/i", $in_file)) && function_exists('imagecreatefrompng') ){
      $img_format='png';
      $img  = imagecreatefrompng($in_file);
    }
    else{
      throw new Exception("no GD installed, required for open_image");
    }

    return $img;
  }

  //******************** save img from memory to file
  public static function save_image($img, $out_file, $img_format=''){
    if (!$img_format) $img_format=self::image_type($out_file);

    # logger("save_image as [$out_file] [$img_format]");

    if ($img_format=='jpg'){
      imageinterlace($img, 1);          #make progressive jpeg
      imagejpeg($img, $out_file, self::$IMG_RESIZE_JPG_QUALITY);  #80% quality should be enough?
    }
    elseif ($img_format=='gif'){  # if GIF image - get only 1st image or not?
      imagegif($img, $out_file);
    }
    elseif ($img_format=='png'){
      imagealphablending($img, false);
      imagesavealpha($img, true);
      imagepng($img, $out_file);
    }else{
      throw new ApplicationException("unknown format [$img_format] for $out_file");
    }
  }

  public static function image_type($path, $default_ext=''){
    $pp=pathinfo($path);
    $ext=self::$IMG_EXT[ strtolower($pp['extension']) ];

    if ( !strlen($ext) ) $ext=$default_ext;

    return $ext;
  }

  //********** rotate image in file
  // $dir =-1 - counterclockwise
  // $dir =1 - clockwise
  // return:
  //   1 - success
  //   0 - problem
  // throws exception if:
  //  no GD installed
  public static function image_rotate($in_file, $dir, $out_file=''){
    if (!$dir || !file_exists($in_file)) return 0;

    #logger("rotating: $in_file, $dir, $out_file");

    if (!$out_file) $out_file=$in_file;
    if ($dir==-1) $angle=90;
    if ($dir==1) $angle=-90;

    $img_format='';
    $img=self::open_image($in_file, $img_format);

    if ($img==-1) throw new Exception("no GD installed, required for image_rotate"); #not done because GD is not attached

    if (!function_exists('imagerotate')) {
      logger("WARN: standard 'imagerotate' not exists, emulating...");
      ini_set("memory_limit", "128M");
      $img==self::imagerotate_my($img, $angle, 0xFFFFFF);
    }else{
      $img=imagerotate($img, $angle, 0xFFFFFF);
    }

    #logger("rotating OK?");

    self::save_image($img, $out_file, self::image_type($out_file, $img_format) );

    imagedestroy($img);

    return 1;
  }


  /*
      Imagerotate replacement. ignore_transparent is work for png images
      Also, have some standard functions for 90, 180 and 270 degrees.
      Rotation is clockwise
  */

  public static function imagerotate_my($srcImg, $angle, $bgcolor, $ignore_transparent = 0) {
      function rotateX($x, $y, $theta){
          return $x * cos($theta) - $y * sin($theta);
      }
      function rotateY($x, $y, $theta){
          return $x * sin($theta) + $y * cos($theta);
      }

      $srcw = imagesx($srcImg);
      $srch = imagesy($srcImg);

      //Normalize angle
      $angle %= 360;
      //Set rotate to clockwise
  //    $angle = -$angle;

      if($angle == 0) {
          if ($ignore_transparent == 0) {
              imagesavealpha($srcImg, true);
          }
          return $srcImg;
      }

      // Convert the angle to radians
      $theta = deg2rad ($angle);

      //Standart case of rotate
      if ( (abs($angle) == 90) || (abs($angle) == 270) ) {
          $width = $srch;
          $height = $srcw;
          if ( ($angle == 90) || ($angle == -270) ) {
              $minX = 0;
              $maxX = $width;
              $minY = -$height+1;
              $maxY = 1;
          } else if ( ($angle == -90) || ($angle == 270) ) {
              $minX = -$width+1;
              $maxX = 1;
              $minY = 0;
              $maxY = $height;
          }
      } else if (abs($angle) === 180) {
          $width = $srcw;
          $height = $srch;
          $minX = -$width+1;
          $maxX = 1;
          $minY = -$height+1;
          $maxY = 1;
      } else {
          // Calculate the width of the destination image.
          $temp = array (rotateX(0, 0, 0-$theta),
          rotateX($srcw, 0, 0-$theta),
          rotateX(0, $srch, 0-$theta),
          rotateX($srcw, $srch, 0-$theta)
          );
          $minX = floor(min($temp));
          $maxX = ceil(max($temp));
          $width = $maxX - $minX;

          // Calculate the height of the destination image.
          $temp = array (rotateY(0, 0, 0-$theta),
          rotateY($srcw, 0, 0-$theta),
          rotateY(0, $srch, 0-$theta),
          rotateY($srcw, $srch, 0-$theta)
          );
          $minY = floor(min($temp));
          $maxY = ceil(max($temp));
          $height = $maxY - $minY;
      }

      $destimg = imagecreatetruecolor($width, $height);
      if ($ignore_transparent == 0) {
          imagefill($destimg, 0, 0, imagecolorallocatealpha($destimg, 255,255, 255, 127));
          imagesavealpha($destimg, true);
      }

      // sets all pixels in the new image
      for($x=$minX; $x<$maxX; $x++) {
          for($y=$minY; $y<$maxY; $y++) {
              // fetch corresponding pixel from the source image
              $srcX = round(rotateX($x, $y, $theta));
              $srcY = round(rotateY($x, $y, $theta));
              if($srcX >= 0 && $srcX < $srcw && $srcY >= 0 && $srcY < $srch) {
                  $color = imagecolorat($srcImg, $srcX, $srcY );
              } else {
                  $color = $bgcolor;
              }
              imagesetpixel($destimg, $x-$minX, $y-$minY, $color);
          }
      }
      return $destimg;
  }


  /*
  resize any image to fixed width/heigth with cropping if necessary!
  return: 1 - success, 0 - failed
  */
  public static function image_resize_fixed($in_file, $w=0, $h=0, $out_file=''){
      if (!$out_file) $out_file=$in_file;

      if (!file_exists($in_file)){
          logger("ERROR: FILE NOT EXISTS $in_file");
          return 0;
      }

      $img_format='';
      $img=self::open_image($in_file, $img_format);

      if ($img==-1) return array(-1,-1); #no resize done because GD is not attached

      $old_w=imagesx($img);
      $old_h=imagesy($img);
  #    logger(".old: . $old_w,$old_h");

      $new_ratio=$w/$h;
      $old_ratio=$old_w/$old_h;

      //determine sizes and crop coords
      $src_x=0;$src_y=0;
      $src_h=0;$src_w=0;

      if ($old_ratio > $new_ratio){
          //to vert/square image
          $src_h=$old_h;
          $src_w=floor($w*$old_h/$h);

          $src_x=floor(0+($old_w-$src_w)/2);
          $src_y=0;
      }else{
          //to horiz image
          $src_w=$old_w;
          $src_h=floor($h*$old_w/$w);

          $src_x=0;
          $src_y=floor(0+($old_h-$src_h)/4); // div by 4 because we want to crop closer to top of the image
      }

      $new_w=$w;
      $new_h=$h;
      $s_img=imagecreatetruecolor($new_w,$new_h);
      if ($img_format=='gif' || $img_format=='png'){  //for gif and png - keep transparency
          $trans_ind = imagecolortransparent($img);
          if ($trans_ind>0){ // If we have a specific transparent color
              $trans_color = imagecolorsforindex($img, $trans_ind); // Get the original image's transparent color's RGB values
              $trans_ind   = imagecolorallocate($s_img, $trans_color['red'], $trans_color['green'], $trans_color['blue']); // Allocate the same color in the new image resource

              imagefill($s_img, 0, 0, $trans_ind); // Completely fill the background of the new image with allocated color.
              imagecolortransparent($s_img, $trans_ind);  // Set the background color for new image to transparent

          }elseif ($img_format=='png') {
              imagealphablending($s_img, false);  // Turn off transparency blending (temporarily)

              $trans_color = imagecolorallocatealpha($s_img, 0, 0, 0, 127); // Create a new transparent color for image
              imagefill($s_img, 0, 0, $trans_color);  // Completely fill the background of the new image with allocated color.
          }
      }

      //crop and resize
      //bool imagecopyresampled ( resource $dst_image , resource $src_image , int $dst_x , int $dst_y , int $src_x , int $src_y , int $dst_w , int $dst_h , int $src_w , int $src_h )
      //rw("src xy=$src_x,$src_y wh=$src_w,$src_h, to $w,$h");
      $a=imagecopyresampled($s_img,$img,0,0, $src_x,$src_y, $w,$h, $src_w,$src_h);

      //save result
      self::save_image($s_img, $out_file, self::image_type($out_file, $img_format) );

      imagedestroy($s_img);
      return 1;
  }

}

?>