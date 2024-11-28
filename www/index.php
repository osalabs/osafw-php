<?php

 require_once dirname(__FILE__)."/php/fw/fw.php";

 $ROUTES = array(
    '/Logoff' => '/Login/(Logoff)', //special case for Logoff
#    '' => 'index::view', //default route
#    'aaa' => 'index', //default route
#    '/test/test/a' => 'user::show_list', //direct processing
#    '/auser' => '/user', //redirect
#    'auser' => 'user',   #resource replace
 );
 
 fw::run($ROUTES);

 exit;
