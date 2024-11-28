<?php
/*
 Configuration variables for the site

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2024 Oleg Savchuk www.osalabs.com

*/

#for command line scripts - override host (must contain "verified.email") from arg OR use path for staging/develop
$_dirname = dirname(__FILE__);
if (PHP_SAPI === 'cli') {
    if (isset($argv[1]) && filter_var($argv[1], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
        $_SERVER['HTTP_HOST'] = $argv[1];
    } elseif (str_contains($_dirname, "staging")) {
        $_SERVER['HTTP_HOST'] = "staging.example.com"; #TODO set your staging domain here, so cli scripts run withing directory will use this domain
    } elseif (str_contains($_dirname, "develop")) {
        $_SERVER['HTTP_HOST'] = "develop.example.com"; #TODO set your develop domain here, so cli scripts run withing directory will use this domain
    }
}
######### set all variables to defaults with detection of base dirs
$site_root         = dirname(dirname(dirname(__FILE__)));# level up as we are under /configs dir
$site_root_offline = dirname($site_root);

#!note, these will be empty if script run from command line
# X-forwarded are for the load balancer setup.
if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 0) == 443 || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') == "https")) {
    $proto = 'https';
} else {
    $proto = 'http';
};

$root_domain0 = preg_replace("/[^a-zA-Z0-9\-:.]/", "", ($_SERVER['HTTP_HOST'] ?? '')); #sanitize as it is also used as config filename
$root_domain  = $root_domain0 ? $proto . "://" . $root_domain0 : '';
$root_url     = "";

#global site config
$FW_CONFIG = array(
    'SITE_ROOT'         => $site_root,          #site root usually is parent of this file dir (inc)
    'SITE_ROOT_OFFLINE' => $site_root_offline,  #offline dir usually is parent to site_root dir (www)
    'PROTO'             => $proto,
    'ROOT_DOMAIN0'      => $root_domain0,       #domain without proto, without port, example: domain.com
    'ROOT_DOMAIN'       => $root_domain,        #full domain url with http or https, example: http://domain.com
    'ROOT_URL'          => $root_url,           #use only if site installed in subdirectory of domain like domain.com/sub_site, example: /sub_site

    'FEEDBACK_EMAIL' => '',                     #TODO
    'SUPPORT_EMAIL'  => 'support@website.com',
    'FROM_EMAIL'     => 'noreply@website.com',   #default from email for system emails
    'TEST_EMAIL'     => '',                      #if set - all emails will be sent to this email, if IS_TEST=true

    'MAIL' => array(
        'IS_SMTP'     => false, #if true, use SMTP settings below
        'SMTPSecure'  => '', #empty or ssl or tls
        'SMTP_SERVER' => '', #IP or domain name of SMTP server ";" separated if multiple
        'SMTP_PORT'   => '', #optional, SMTP port
        'USER'        => '', #SMTP auth user
        'PWD'         => '', #SMTP auth pwd
    ),

    #db connection settings  - REQUIRED
    'DB'   => array(
        'DBNAME'     => '',      #database name
        'USER'       => '',      #db user name
        'PWD'        => '',      #db user password
        'HOST'       => 'localhost',
        'PORT'       => '',
        'SQL_SERVER' => '', # if empty - MySQL
        'IS_LOG'     => true, #enable logging via fw
    ),

    'site_error_log'   => $site_root_offline . '/logs/osafw.log',
    'LOG_MESSAGE_TYPE' => 3, #3 - default to $site_error_log
    'LOG_LEVEL'        => 'INFO', #ALL|TRACE|DEBUG|INFO|WARN|ERROR|FATAL|OFF. Use WARN|ERROR|FATAL|OFF for production, ALL|TRACE|DEBUG for dev
    'IS_DEV'           => false, #NEVER set to true on live environments
    'IS_TEST'          => false, #if true - test mode, emails sent to current user or test_email

    'IS_SIGNUP'            => false,  #set to true to enable Sign Up module
    'UNLOGGED_DEFAULT_URL' => '/',
    'LOGGED_DEFAULT_URL'   => '/Main',

    'SITE_TEMPLATES'        => $site_root . '/template',
    'PUBLIC_UPLOAD_DIR'     => $site_root . '/upload',
    'PUBLIC_UPLOAD_URL'     => $root_url . '/upload',
    'ASSETS_URL'            => $root_url . '/assets',

    #page layout templates - relative to SITE_TEMPLATES dir
    'PAGE_LAYOUT'           => '/layout.html',        #default layout for all pages
    'PAGE_LAYOUT_PUBLIC'    => '/layout_public.html', #default layout for pub pages
    'PAGE_LAYOUT_PJAX'      => '/layout_pjax.html',
    'PAGE_LAYOUT_PJAX_NOJS' => '/layout_pjax_nojs.html',
    'PAGE_LAYOUT_MIN'       => '/layout_min.html',
    'PAGE_LAYOUT_PRINT'     => '/layout_print.html',
    "is_list_btn_left"      => false, #true - show list buttons on the left, false - on the right
    "ui_theme"              => 0, #0 default theme
    "ui_mode"               => 0, #0 default mode(auto)

    #prefixes for Dispatcher
    'ROUTE_PREFIXES'        => array(
        '/Admin',
        '/My',
        '/Dev',
        '/v1', #API version 1
    ),
    #prefixes without XSS check (without slash)
    'NO_XSS_PREFIXES'       => array(
        'v1' => true, # no need to check for API
    ),
    #Controllers without need for XSS check
    'NO_XSS'                => array(
        'Login' => true,
    ),
    #Allowed Access levels for Controllers
    #if set here - overrides Controller::access_level
    #0 - any visitor
    #1 - user must be logged in
    #100 - admin user
    'ACCESS_LEVELS'         => array(
        '/Main'            => 1,
        '/AdminAtt/Select' => 1,
    ),

    #multilanguage support settings
    'LANG_DEF'              => 'en',        #default language - en, ru, ua, ...
    'LANG'                  => 'en',        #to be updated according to user session
    'IS_LANG_UPD'           => false,       #false - don't update lang files, true - update lang files with new strings

    'IS_MFA_ENFORCES' => false, #true - enforce MFA for all users, false - use user settings
    'CRYPT_KEY'       => '', #define in site/dev specific config
    'CRYPT_V'         => '', #define in site/dev specific config

    'PDF_CONVERTER' => '"C:\Program Files\wkhtmltopdf\bin\wkhtmltopdf.exe"', #(optional) path to html to pdf converter for reports, if empty - try to use Dompdf

    ########### place site specific configuration variables here:
    'SITE_VERSION'  => '0.24.1121', #also used to re-load css/js to avoid browser caching
    'SITE_NAME'     => 'Site Name',
    'SITE_VAR'      => false,
);

ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);

#load $SITE_CONFIG config which may override any variables: some.domain.name[:port] -> config.some.domain.name[_port].php
$conf_server_name = str_replace(':', '_', strtolower($root_domain0));
if (include_once('config.' . $conf_server_name . '.php')) {
    #set loaded config name
    $overrides['loaded_config'] = $conf_server_name;
}
$CONFIG = array_replace_recursive($FW_CONFIG, $SITE_CONFIG);

if (!empty($CONFIG['site_error_log'])) {
    ini_set("log_errors", 1);
    ini_set("error_log", $CONFIG['site_error_log']);
} else {
    if (!empty($CONFIG['php_error_log'])) {
        ini_set("log_errors", 1);
        ini_set("error_log", $CONFIG['php_error_log']);
        ini_set("log_errors_max_len", 0);
    } else {
        ini_set("log_errors", 0);
    }
}

/*
echo "conf_server_name=$conf_server_name<br>";
echo "site config:<br>\n";
echo "<pre>";
echo print_r($CONFIG, true)."<br>\n";
echo "</pre>";
#exit;
*/
