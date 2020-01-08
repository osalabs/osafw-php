<?php
/*
 Configuration variables for the site

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2019 Oleg Savchuk www.osalabs.com

*/

######### set all variables to defaults with detection of base dirs
$site_root         = preg_replace("![\\\/]\w+$!i", "", dirname(__FILE__));
$site_root_offline = preg_replace("![\\\/]\w+$!i", "", $site_root);

#!note, these $_SERVER vars will be empty if script run from command line
$proto = 'http';
if ( (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443 || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') == "https") ) {
    $proto = 'https';
}

$root_domain0 = $_SERVER['HTTP_HOST'] ?? '';
$root_domain  = $root_domain0 ? $proto . "://" . $root_domain0 : '';
$root_url     = "";

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

    #db connection settings  - REQUIRED
    'DB'    => array(
        'DBNAME'    => '',      #database name
        'USER'      => '',      #db user name
        'PWD'       => '',      #db user password
        'HOST'      => 'localhost',
        'PORT'      => '',
        'SQL_SERVER'=> '', # if empty - MySQL
        'IS_LOG'    => true, #enable logging via fw
        ),

    'site_error_log'        => $site_root_offline . '/logs/osafw.log',
    'LOG_MESSAGE_TYPE'      => 3, #3 - default to $site_error_log
    'LOG_LEVEL'             => 'INFO', #ALL|TRACE|DEBUG|INFO|WARN|ERROR|FATAL|OFF. Use WARN|ERROR|FATAL|OFF for production, ALL|TRACE|DEBUG for dev
    'LOG_LIMIT'             => 4096, #trucate log messages at this length
    'IS_LOG_REMOTE'         => false, #if true - requires, for example, Sentry
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
    'PAGE_LAYOUT'              => '/layout_fluid.html',       #default layout for all pages
    'PAGE_LAYOUT_PUBLIC'       => '/layout.html',       #default layout for pub pages
    'PAGE_LAYOUT_STD'          => '/layout.html',
    'PAGE_LAYOUT_FLUID'        => '/layout_fluid.html',
    'PAGE_LAYOUT_PJAX'         => '/layout_pjax.html',
    'PAGE_LAYOUT_MIN'          => '/layout_min.html',
    'PAGE_LAYOUT_PRINT'        => '/layout_print.html',

    'MAX_PAGE_ITEMS'        => 25,

    #prefixes for Dispatcher
    'ROUTE_PREFIXES'        => array(
        '/Admin',
        '/Api',
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
        #'/Main'         => 0,
        '/AdminAtt/Select' => 0,
        ),

    #multilanguage support settings
    'LANG_DEF'              => 'en',        #default language - en, ru, ua, ...
    'LANG'                  => 'en',        #to be updated according to user session
    'IS_LANG_UPD'           => false,       #false - don't update lang files, true - update lang files with new strings

    'SITE_VERSION'          => '0.20.0108', #also used to re-load css/js to avoid browser cacheing
    'CRYPT_KEY'             => '', #define in site/dev specific config
    'CRYPT_V'               => '', #define in site/dev specific config
    'PDF_CONVERTER'         => '"C:\Program Files (x86)\wkhtmltopdf\bin\wkhtmltopdf.exe"', #(optional) path to html to pdf converter for reports, if empty - try to use Dompdf

    'loaded_config'         => '', # which config is loaded now
    ########### place site specific configuration variables here:
    'SITE_VAR'              => false,
);

#load config which may override any variables: some.domain.name[:port] -> config.some.domain.name[_port].php
$conf_server_name = str_replace(':', '_', strtolower($root_domain0));
if (!$conf_server_name || !@include_once ('config.' . $conf_server_name . '.php')) {
    #if no config exists for the domain - use site config
    $conf_server_name='site';
    include_once('config.'.$conf_server_name.'.php');
}
#set loaded config name
$FW_CONFIG['loaded_config'] = $conf_server_name;
$CONFIG = array_merge($FW_CONFIG, $SITE_CONFIG);

/*
echo "conf_server_name=$conf_server_name<br>";
echo "site config:<br>\n";
echo "<pre>";
echo print_r($CONFIG, true)."<br>\n";
echo "</pre>";
#exit;
*/

