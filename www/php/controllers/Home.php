<?php

class HomeController extends FwController {
    const route_default_action = 'show';

    public function __construct() {
        parent::__construct();
    }

    #CACHED as home_page
    public function IndexAction() {
        #fw::redirect('/Login'); #uncomment to always show login instead of Home

        /*cached version
        $ps = FwCache::getValue('home_page');

        if (is_null($ps)){
            #cache miss
            $ps = array();
            #create home page with heavy queries

            FwCache::setValue('home_page', $ps);
        }
        */

        $ps = array(
            'hide_sidebar'  => true,
        );
        return $ps;
    }

    public function ShowAction($id='') {
        $ps = array(
            'hide_sidebar'  => true,
        );

        $this->fw->parser('/home/'.Dispatcher::RouteFixChars(strtolower($id)), $ps);
        return false;
    }
}
?>