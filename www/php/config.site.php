<?php
# config file for specific server
# if you set settings here, they will override default settings from config.site.php

date_default_timezone_set('UTC'); #required in PHP 5.3

$SITE_CONFIG = array(
    #REQUIRED if you use offline scripts
    #'ROOT_DOMAIN0'          => 'DOMAIN.com',                               #domain without proto, without port, example: domain.com
    #'ROOT_DOMAIN'           => $FW_CONFIG['PROTO'].'://DOMAIN.com',        #full domain url with http or https, example: http://domain.com
    #'ROOT_URL'              => '',                                         #use only if site installed in subdirectory of domain like domain.com/sub_site, example: /sub_site

    #'SITE_ROOT_OFFLINE'     => '/var/www/website',
    #'SITE_ROOT'             => '/var/www/website/public_html',

    #'SUPPORT_EMAIL'         => 'support@website.com',
    #'FROM_EMAIL'            => 'noreply@website.com',

    #db connection settings  - REQUIRED
    'DB'        => array(
        'DBNAME'     => '',      #database name
        'USER'       => '',      #db user name
        'PWD'        => '',      #db user password
        'HOST'       => 'localhost',
        'PORT'       => '',
        'SQL_SERVER' => '', # if empty - MySQL
        'IS_LOG'     => true,
    ),
    'LOG_LEVEL' => 'INFO', #use WARN|ERROR|FATAL|OFF for production, use INFO temporary to see SQL queries in production
    'CRYPT_KEY' => '', #TODO define your key for crypting
    'CRYPT_V'   => '', #TODO define your "salt" for crypting
);

ini_set('display_errors', 0);
error_reporting(E_ERROR); #report only critical errors
ini_set("log_errors", 1);
ini_set("error_log", $FW_CONFIG['site_error_log']);

?>
