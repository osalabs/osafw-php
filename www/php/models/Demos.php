<?php
/*
Demos model class
*/

class Demos extends FwModel {

    public function __construct() {
        parent::__construct();

        $this->table_name = 'demos';
    }

    public function is_exists($email, $not_id=NULL) {
        return parent::is_exists($email, 'email', $not_id);
    }

}

?>