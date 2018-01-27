<?php
# config file for specific server
# if you set settings here, they will override default settings from config.site.php

date_default_timezone_set('UTC'); #required in PHP 5.3

$SITE_CONFIG=array(
    #REQUIRED if you use offline scripts
    #'ROOT_DOMAIN0'          => 'DOMAIN.com',                               #domain without proto, without port, example: domain.com
    #'ROOT_DOMAIN'           => $FW_CONFIG['PROTO'].'://DOMAIN.com',        #full domain url with http or https, example: http://domain.com
    #'ROOT_URL'              => '',                                         #use only if site installed in subdirectory of domain like domain.com/sub_site, example: /sub_site

    #'SITE_ROOT_OFFLINE'     => '/var/www/website',
    #'SITE_ROOT'             => '/var/www/website/public_html',

    #'ADMIN_EMAIL'           => 'admin@website.com',
    #'SUPPORT_EMAIL'         => 'support@website.com',
    #'FROM_EMAIL'            => 'noreply@website.com',

    #db connection settings  - REQUIRED
    'DB'    => array(
                'DBNAME'    => 'demo2',
                'USER'      => 'dev',
                'PWD'       => '',
                'HOST'      => 'localhost',
                'PORT'      => '',
                'SQL_SERVER'=> '', # if empty - MySQL
                'IS_LOG'    => true,
                ),
    'IS_DEV'            => true, #to enable developer features
    'LOG_LEVEL'         => 'DEBUG', #use ALL|TRACE|DEBUG for development
    'IS_SIGNUP'         => true,
);

ini_set('display_errors',1);
error_reporting(E_ALL & ~E_NOTICE);
#error_reporting(E_ALL);
ini_set("log_errors", 1);
ini_set("error_log", $SITE_CONFIG['site_error_log']);

?>
