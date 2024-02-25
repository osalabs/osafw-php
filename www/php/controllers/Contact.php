<?php
/*
 Site Contact Form controller

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2024 Oleg Savchuk www.osalabs.com
*/

class ContactController extends FwController {
    const route_default_action = '';

    public function __construct() {
        parent::__construct();

        $this->base_url = '/Contact';

        #override layout
        $this->fw->page_layout = $this->fw->config->PAGE_LAYOUT_PUBLIC;
    }

    public function IndexAction() {
        $ps = array();

        $page               = Spages::i()->oneByFullUrl($this->base_url);
        $ps["page"]         = $page;
        $ps['hide_sidebar'] = true;
        return $ps;
    }

}
