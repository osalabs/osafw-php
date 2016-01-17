<?php
/*
 Configuration variables for the site

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2015 Oleg Savchuk www.osalabs.com

*/

$IS_TEST_SERVER=0;  #0 - production server
$conf_server_name='site';

########### detect test/development servers and load appropriate config
#!!! add server configs here:
$DEV_SERVERS=array(
  #format - [0]filepath to check for existence (must be unique and not in SVN), [1]-config name to load
  array('/var',         'site'),  #prod
  array('c:/docs_proj', 'develop'),
  array('d:/myfile.my', 'my'),
);

#detect
foreach($DEV_SERVERS as $k => $arr){
 if ( @file_exists($arr[0]) ){
    if ($arr[1]!='site') $IS_TEST_SERVER=1;
    $conf_server_name=$arr[1];
    break;
 }
}

######### set all variables to defaults with detection of base dirs
   $site_root         = preg_replace("![\\\/]\w+$!i", "", dirname(__FILE__));
   $site_root_offline = preg_replace("![\\\/]\w+$!i", "", $site_root);

   #!note, these will be empty if script run from command line
   $proto = preg_match("/https/i", $_SERVER["SERVER_PROTOCOL"]) ? 'https' : 'http';

   $root_domain0=preg_replace('/:\d+$/','',$_SERVER['HTTP_HOST']);
   $root_domain=$proto."://".$_SERVER['HTTP_HOST'];
   $root_url="";

#global site config
$FW_CONFIG = array(
    'SITE_ROOT'             => $site_root,          #site root usually is parent of this file dir (inc)
    'SITE_ROOT_OFFLINE'     => $site_root_offline,  #offline dir usually is parent to site_root dir (www)
    'PROTO'                 => $proto,
    'ROOT_DOMAIN0'          => $root_domain0,       #domain without proto, without port, example: domain.com
    'ROOT_DOMAIN'           => $root_domain,        #full domain url with http or https, example: http://domain.com
    'ROOT_URL'              => $root_url,           #use only if site installed in subdirectory of domain like domain.com/sub_site, example: /sub_site

    'ADMIN_EMAIL'           => 'admin@website.com',
    'SUPPORT_EMAIL'         => 'support@website.com',
    'FROM_EMAIL'            => 'noreply@website.com',

    'MAIL'  => array(
                'IS_SMTP'       => false, #if true, use SMTP settings below
                'SMTP_SERVER'   => '', #IP or domain name of SMTP server
                'USER'          => '', #SMTP auth user
                'PWD'           => '', #SMTP auth pwd
                ),

    #db connection settings
    'DB'    => array(
                'DBNAME'    => 'XXXXXXXXXXX',
                'USER'      => '',
                'PWD'       => '',
                'HOST'      => 'localhost',
                'PORT'      => '',
                'SQL_SERVER'=> '', # if empty - MySQL
                ),

    'site_error_log'        => $site_root_offline.'/logs/error.log',
    'LOGGER_MESSAGE_TYPE'   => 3, #3 - default to $site_error_log
    'IS_DEBUG'              => false,

    'LOGGED_DEFAULT_URL'    => '/Main',
    'UNLOGGED_DEFAULT_URL'  => '/',

    'SITE_TEMPLATES'        => $site_root_offline.'/template',
    'PUBLIC_UPLOAD_DIR'     => $site_root.'/upload',
    'PUBLIC_UPLOAD_URL'     => $root_url.'/upload',


    #page layout templates - relative to SITE_TEMPLATES dir
    'PAGE_TPL'              => '/page_tpl.html',
    'PAGE_TPL_PJAX'         => '/page_tpl_pjax.html',
    'PAGE_TPL_ADMIN'        => '/page_tpl.html',

    'MAX_PAGE_ITEMS'        => 25,

    #prefixes for Dispatcher
    'ROUTE_PREFIXES'        => array(
                                '/Admin',
                                '/My',
                                ),
    #Allowed Access levels for Controllers
    #0 - user must be logged in
    #100 - admin user
    'ACCESS_LEVELS'         => array(
                                '/Main'         => 0,
                                '/MySettings'   => 0,
                                '/MyPassword'   => 0,
                                '/AdminCategories' => 80,
                                '/AdminSPages'  => 80,
                                '/AdminUsers'   => 100,
                                '/AdminAtt'     => 100,
                                '/AdminSettings'   => 100,
                                ),

    #multilanguage support settings
    'LANG_DEF'              => 'en',        #default language - en, ru, ua, ...
    'IS_LANG_UPD'           => false,       #false - don't update lang files, true - update lang files with new strings

    'SITE_VERSION'          => '0.14.1125',

    ########### place site specific configuration variables here:
    'SITE_VAR'              => false,
);

#load config which may override any variables
include_once('config.'.$conf_server_name.'.php');
$CONFIG = array_merge($FW_CONFIG, $SITE_CONFIG);

/*
echo "conf_server_name=$conf_server_name<br>";
echo "site config:<br>\n";
echo "<pre>";
echo print_r($CONFIG, true)."<br>\n";
echo "</pre>";
#exit;
*/

?>
