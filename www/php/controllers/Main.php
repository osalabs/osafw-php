<?php

class MainController extends FwController {
    const access_level = 0; #logged only
    const route_default_action = 'index';

    public function IndexAction() {
        $ps = array();

        return $ps;
    }

}

?>