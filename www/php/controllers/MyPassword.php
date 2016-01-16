<?php

class MyPasswordController extends FwController {
    const route_default_action = '';
    public $base_url = '/My/Password';
    public $model_name = 'Users';

    public function IndexAction() {
        $this->route_redirect("ShowForm");
    }

    public function ShowFormAction() {
        $id = Utils::me();

        if ($this->fw->route['method']=='GET' ){
            if ($id>0){
                $item = $this->model->one($id);
            }else{
                #defaults
                $item=array(
                );
            }
        }else{
            $itemdb = $this->model->one($id);
            $item = array_merge($itemdb, req('item'));
        }

        $ps = array(
            'id'    => $id,
            'i'     => $item,
        );

        return $ps;
    }

    public function SaveAction() {
        $id = Utils::me();
        $item = req('item');

        try{
            $this->Validate($id, $item);

            $vars = FormUtils::form2dbhash($item, 'email pwd');
            $this->model->update($id, $vars);

            $this->fw->flash("record_updated", true);
            fw::redirect($this->base_url);

        }catch( ApplicationException $ex ){
            $this->set_form_error($ex->getMessage());
            $this->route_redirect("ShowForm");
        }
    }

    public function Validate($id, $item) {
        $result= $this->validate_required($item, "email old_pwd pwd pwd2");

        if ($result){
            $itemdb=$this->model->one($id);
            if ( $item['old_pwd']!=$itemdb['pwd'] ){
                $this->ferr('old_pwd', 'WRONG');
            }

            if ($this->model->is_exists( $item['email'], $id ) ){
                $this->ferr('email', 'EXISTS');
            }

            if (!FormUtils::is_email( $item['email'] ) ){
                $this->ferr('email', 'WRONG');
            }

            if ($item['pwd']!=$item['pwd2'] ){
                $this->ferr('pwd2', 'NOTEQUAL');
            }

        }

        $this->validate_check_result();
    }

}//end of class

?>