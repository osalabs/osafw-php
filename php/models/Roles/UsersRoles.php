<?php
/*
 UsersRoles model class

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2024 Oleg Savchuk www.osalabs.com
*/

class UsersRoles extends FwModel {

    public function __construct() {
        parent::__construct();

        $this->table_name = 'users_roles';

        $junction_model_main      = Users::i();
        $junction_field_main_id   = "users_id";
        $junction_model_linked    = Roles::i();
        $junction_field_linked_id = "roles_id";
    }

}
