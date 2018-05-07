<?php

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

?>