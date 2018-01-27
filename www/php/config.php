<?php
/*
 Configuration variables for the site

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2015 Oleg Savchuk www.osalabs.com

*/

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
                'SMTPSecure'    => '', #empty or ssl or tls
                'SMTP_SERVER'   => '', #IP or domain name of SMTP server ";" separated if multiple
                'SMTP_PORT'     => '', #optional, SMTP port
                'USER'          => '', #SMTP auth user
                'PWD'           => '', #SMTP auth pwd
                ),

    #db connection settings
    'DB'    => array(
                'DBNAME'    => '',      #database name
                'USER'      => '',      #db user name
                'PWD'       => '',      #db user password
                'HOST'      => 'localhost',
                'PORT'      => '',
                'SQL_SERVER'=> '', # if empty - MySQL
                'IS_LOG'    => true, #enable logging via fw
                ),

    'site_error_log'        => $site_root_offline.'/logs/osafw.log',
    'LOGGER_MESSAGE_TYPE'   => 3, #3 - default to $site_error_log
    'LOG_LEVEL'             => 'INFO', #ALL|TRACE|DEBUG|INFO|WARN|ERROR|FATAL|OFF. Use WARN|ERROR|FATAL|OFF for production, ALL|TRACE|DEBUG for dev
    'IS_DEV'                => false, #NEVER set to true on live environments
    'IS_LOG_FWEVENTS'       => true, #by default log all changes via FwEvents

    'IS_SIGNUP'             => true,  #set to false to disable Sign Up module
    'LOGGED_DEFAULT_URL'    => '/Main',
    'UNLOGGED_DEFAULT_URL'  => '/',

    'SITE_TEMPLATES'        => $site_root.'/template',
    'PUBLIC_UPLOAD_DIR'     => $site_root.'/upload',
    'PUBLIC_UPLOAD_URL'     => $root_url.'/upload',
    'ASSETS_URL'            => $root_url.'/assets',

    #page layout templates - relative to SITE_TEMPLATES dir
    'PAGE_LAYOUT'              => '/layout.html',       #default layout for all pages
    'PAGE_LAYOUT_PJAX'         => '/layout_pjax.html',
    'PAGE_LAYOUT_ADMIN'        => '/layout_tpl.html',

    'MAX_PAGE_ITEMS'        => 25,

    #prefixes for Dispatcher
    'ROUTE_PREFIXES'        => array(
                                '/Admin',
                                '/My',
                                '/Dev',
                                ),
    #Controllers without need for XSS check
    'NO_XSS'                => array(
                                'Login',
                                ),
    #Allowed Access levels for Controllers
    #if set here - overrides Controller::access_level
    #0 - user must be logged in
    #100 - admin user
    'ACCESS_LEVELS'         => array(
    #                            '/Main'         => 0,
                                '/AdminAtt/Select' => 0,
                                ),

    #multilanguage support settings
    'LANG_DEF'              => 'en',        #default language - en, ru, ua, ...
    'IS_LANG_UPD'           => false,       #false - don't update lang files, true - update lang files with new strings

    'SITE_VERSION'          => '0.18.0127', #also used to re-load css/js to avoid browser cacheing

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
