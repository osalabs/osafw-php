<?php

class PasswordController extends FwController {
    const route_default_action = '';
    public $base_url = '/Password';
    public $model_name = 'Users';

    public function IndexAction() {
        if ($this->fw->route['method']=='GET' ){
            #defaults
            $item=array();
        }else{
            $item = req('item');
        }

        $ps = array(
            'i'     => $item,
        );

        return $ps;
    }

    public function SaveAction() {
        $item = req('item');
        $item['login']=trim($item['login']);

        try{
            $this->Validate($id, $item);
            $user = $this->model->one_by_email($item['login']);

            $this->fw->send_email_tpl( $user['login'], 'email_pwd.txt', $user);

            fw::redirect($this->base_url.'/(Sent)');

        }catch( ApplicationException $ex ){
            $this->set_form_error($ex->getMessage());
            $this->route_redirect("Index");
        }
    }

    public function Validate($id, $item) {
        $result= $this->validate_required($item, "login");

        if ($result){
            $user = $this->model->one_by_email($item['login']);
            if (!count($user)) $this->ferr('login', 'WRONG');
        }

        $this->validate_check_result();
    }

}//end of class

?>