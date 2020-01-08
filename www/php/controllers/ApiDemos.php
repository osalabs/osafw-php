<?php
/*
 Demos API Controller

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2020 Oleg Savchuk www.osalabs.com
 */

class ApiDemosController extends FwApiController {
    const access_level = -1; #unlogged can access

    public function __construct() {
        $this->model_name = 'Demos';

        Api::i()->prepare(false); #set headers, but no auth
    }

    //this is default action for /Api/Accounts
    //just return standard response
    public function IndexAction() {
        $ps = array(
            '_json'   => true,
            'success' => true,
        );

        #logger($ps);
        return $ps;
    }

}
