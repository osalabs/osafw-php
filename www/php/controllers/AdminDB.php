<?php

class AdminDBController extends FwController {
    const access_level = 100; #logged admin only

    public function __construct() {
        parent::__construct();

        //optionally init controller
        fw::redirect('/phpminiadmin_site.php');
    }

}//end of class

?>