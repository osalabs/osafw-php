<?php

class HomeController extends FwController {
    const string route_default_action = FW::ACTION_SHOW;

    public function __construct() {
        parent::__construct();

        #override layout
        $this->fw->page_layout = $this->fw->config->PAGE_LAYOUT_PUBLIC;
    }

    #CACHED as home_page
    public function IndexAction(): ?array {
        #fw::redirect('/Login'); #uncomment to always show login instead of Home

        /*cached version
        $ps = $this->fw->cache->get('home_page');

        if (is_null($ps)){
            #cache miss
            $ps = array();
            #create home page with heavy queries, for example from Spages
            ps['page'] = $this->oneByFullUrl('');

            $this->fw->cache->set('home_page', $ps);
        }
        */

        $ps = array(
            'hide_sidebar' => true,
        );
        return $ps;
    }

    #show home subpage page from hardcoded template
    public function ShowAction($id = '') {
        $ps = array(
            'hide_sidebar' => true,
        );

        $this->fw->parser('/home/' . Utils::routeFixChars(strtolower($id)), $ps);
    }

    #called if fw dispatcher can't find controller
    public function NotFoundAction() {
        Spages::i()->showPageByFullUrl($this->fw->request_url);
    }

}
