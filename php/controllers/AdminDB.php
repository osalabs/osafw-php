<?php

class AdminDBController extends FwController {
    const int access_level = Users::ACL_SITE_ADMIN; #logged admin only
    const string route_default_action = FW::ACTION_INDEX;

    public function IndexAction(): never {
        $_SERVER['PHP_SELF'] = $this->fw->config->ROOT_URL . '/Admin/DB';

        require $this->fw->config->PHP_ROOT . '/tools/phpminiadmin.php';
        exit;
    }

}//end of class
