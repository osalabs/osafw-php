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

        #override layout
        $this->fw->page_layout = $this->fw->config->PAGE_LAYOUT_PUBLIC;
    }

    public function IndexAction() {
        $ps = array();

        #TODO - get item from Spages and for /Sent too
        return $ps;
    }

}
