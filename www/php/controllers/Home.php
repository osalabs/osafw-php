<?php

class HomeController extends FwController {
    const route_default_action = 'show';

    public function __construct() {
        parent::__construct();
    }

    #CACHED as home_page
    public function IndexAction() {
        /*cached version
        $ps = FwCache::get_value('home_page');

        if (is_null($ps)){
            #cache miss
            $ps = array();
            #create home page with heavy queries

            FwCache::set_value('home_page', $ps);
        }
        */

        $ps = array();
        return $ps;
    }

    public function ShowAction($id='') {
        $ps = array();

        $this->fw->parser('/home/'.Dispatcher::_route_fix_chars(strtolower($id)), $ps);
        return false;
    }

}

?>