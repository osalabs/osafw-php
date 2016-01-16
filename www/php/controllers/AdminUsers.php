<?php

class AdminUsersController extends FwAdminController {
    const route_default_action = '';
    public $base_url = '/Admin/Users';
    public $required_fields = 'email access_level';
    public $save_fields = 'email fname lname phone pwd access_level status';
    public $model_name = 'Users';

    public $list_sortdef = 'iname asc';    //default sorting - req param name, asc|desc direction
    public $list_sortmap = array(       //sorting map: req param name => sql field name(s) asc|desc direction
                        'id'            => 'id',
                        'iname'         => 'fname,lname',
                        'email'         => 'email',
                        'access_level'  => 'access_level',
                        'add_time'      => 'add_time',
                        );
    public $search_fields = 'email fname lname';  //fields to search via $s$list_filter['s'], ! - means exact match, not "like"
                                            //format: 'field1 field2,!field3 field4' => field1 LIKE '%$s%' or (field2 LIKE '%$s%' and field3='$s') or field4 LIKE '%$s%'

    public function SaveAction($form_id) {
        $id = $form_id+0;
        $item = req('item');

        try{
            $this->Validate($id, $item);
            #load old record if necessary
            #$item_old = $this->model->one($id);

            $itemdb = FormUtils::form2dbhash($item, $this->save_fields);
            if (!strlen($itemdb['pwd'])) unset($itemdb['pwd']);

            $id = $this->model_add_or_update($id, $itemdb);

            if ($id==Utils::me()) $this->model->session_reload();

            fw::redirect($this->base_url.'/'.$id.'/edit');

        }catch( ApplicationException $ex ){
            $this->set_form_error($ex->getMessage());
            $this->route_redirect("ShowForm");
        }
    }

    public function Validate($id, $item) {
        $result= $this->validate_required($item, $this->required_fields);

        //result here used only to disable further validation if required fields validation failed
        if ($result){
            if ($this->model->is_exists( $item['email'], $id ) ){
                $this->ferr('email', 'EXISTS');
            }

            if (!FormUtils::is_email( $item['email'] ) ){
                $this->ferr('email', 'WRONG');
            }
        }

        $this->validate_check_result();
    }

    public function ExportAction() {
        $ps = $this->IndexAction();

        $fields = array(
            'fname'   => 'First Name',
            'lname'   => 'Last Name',
            'email'   => 'Email',
            'add_time' => 'Added',
        );
        Utils::response_csv($ps['list_rows'], $fields, "members.csv");
    }

}//end of class

?>