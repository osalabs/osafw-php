<?php

class ContactController extends FwController {
    const route_default_action = '';

    public function __construct() {
        parent::__construct();
    }

    public function IndexAction() {
        $ps = array();

        #TODO - get item from Spages and for /Sent too
        return $ps;
    }

}

?>