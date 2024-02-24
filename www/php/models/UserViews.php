<?php
/*
 UserViews model class

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2024 Oleg Savchuk www.osalabs.com
*/

class UserViews extends FwModel {

    public function __construct() {
        parent::__construct();

        $this->table_name = 'user_views';
    }

    #return screen record for logged user
    public function oneByScreen($screen) {
        $where = array(
            'add_users_id' => Utils::me(),
            'screen'       => $screen,
        );
        return $this->db->row($this->table_name, $where);
    }

    /**
     * update screen fields for logged user
     * @param string $screen screen name
     * @param string $fields fields selected by user for this screen
     * @return integer         user_views.id
     */
    public function updateByScreen($screen, $fields) {
        $result = 0;
        $item   = $this->oneByScreen($screen);
        if ($item) {
            #exists
            $result = $item['id'];
            $this->update($item['id'], array('fields' => $fields));
        } else {
            #new
            $result = $this->add(array(
                'screen'       => $screen,
                'fields'       => $fields,
                'add_users_id' => Utils::me(),
            ));
        }

        return $result;
    }

}
