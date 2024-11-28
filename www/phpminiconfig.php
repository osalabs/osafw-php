<?php
 @session_start();
 require_once "php/configs/config.php" ;

 if ($_SESSION['user_id'] && $_SESSION['access_level']==100){
    $DBDEF=array(
     'user'=>$CONFIG['DB']['USER'],#required
     'pwd'=>$CONFIG['DB']['PWD'], #required
     'db'=>$CONFIG['DB']['DBNAME'],  #optional, default DB
     'host'=>$CONFIG['DB']['HOST'],#optional
     'port'=>$CONFIG['DB']['PORT'],#optional
     'chset'=>"utf8",#optional, default charset
    );
    loadcfg();
    if (!isset($_REQUEST['q'])) {
       $_REQUEST['XSS']=$_SESSION['XSS'];
       $_REQUEST['q']=b64e('SHOW TABLE STATUS');
    }
 }else{
    $ACCESS_PWD='321'; #set Access password here to enable access to database as non-logged admin
    if (!$ACCESS_PWD){
        rw("Set \$ACCESS_PWD or login to site as an Administrator");
        exit;
    }
 }

?>