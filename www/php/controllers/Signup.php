<?php

class SignupController extends FwAdminController {
    const route_default_action = '';
    public $base_url='/Signup';
    public $required_fields = 'email pwd pwd2';
    public $save_fields = 'email fname lname pwd';
    public $model_name = 'Users';

    public function __construct() {
        global $CONFIG;
        parent::__construct();

        if (!$CONFIG['IS_SIGNUP']) throw new ApplicationException("Sign Up access denied by site config [IS_SIGNUP]");
    }

    public function IndexAction() {
        $this->fw->route_redirect("ShowForm");
    }

    public function ShowFormAction($form_id) {
        $id = $form_id+0;

        if ($this->fw->route['method']=='GET' ){
            if ($id>0){
                $item = $this->model->one($id);
            }else{
                #defaults
                $item=array(
                );
            }
        }else{
            $itemdb = $id ? $this->model->one($id) : array();
            $item = array_merge($itemdb, req('item'));
        }

        $ps = array(
            'id'    => $id,
            'i'     => $item,
        );

        return $ps;
    }

    public function SaveAction($form_id) {
        global $CONFIG;

        $id = $form_id+0;
        $item = req('item');

        try{
            $this->Validate($id, $item);
            #load old record if necessary
            #$item_old = $this->model->one($id);

            $itemdb = FormUtils::form2dbhash($item, $this->save_fields);

            $id = $this->model_add_or_update($id, $itemdb);

            #signup confirmaiton email
            $user = $this->model->one($id);
            $ps=array(
                'user' => $user,
            );
            $this->fw->send_email_tpl( $user['email'], 'signup.txt', $ps);

            $this->model->do_login( $id );
            fw::redirect($CONFIG['LOGGED_DEFAULT_URL']);

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

            if ( $item['pwd']>"" && $item['pwd']!=$item['pwd2'] ){
                $this->ferr('pwd', 'WRONG');
            }
        }

        $this->validate_check_result();
    }

}//end of class

?>