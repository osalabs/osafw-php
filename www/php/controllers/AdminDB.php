<?php

class AdminDBController extends FwController {
    const int access_level = Users::ACL_SITE_ADMIN; #logged admin only

    public function __construct() {
        parent::__construct();

        //optionally init controller
        fw::redirect('/phpminiadmin.php');
    }

}//end of class
