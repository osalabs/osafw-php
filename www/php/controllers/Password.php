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
            'hide_sidebar'  => true,
        );

        return $ps;
    }

    public function SaveAction() {
        $item = req('item');
        $item['login']=trim($item['login']);

        try{
            $this->Validate($id, $item);
            $user = $this->model->oneByEmail($item['login']);

            $this->fw->sendEmailTpl( $user['login'], 'email_pwd.txt', $user);

            fw::redirect($this->base_url.'/(Sent)');

        }catch( ApplicationException $ex ){
            $this->setFormError($ex->getMessage());
            $this->routeRedirect("Index");
        }
    }

    public function Validate($id, $item) {
        $result= $this->validateRequired($item, "login");

        if ($result){
            $user = $this->model->oneByEmail($item['login']);
            if (!count($user)) $this->setError('login', 'WRONG');
        }

        $this->validateCheckResult();
    }

}//end of class

?>