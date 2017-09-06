<?php

class MyPasswordController extends FwController {
    const access_level = 0; #logged only
    const route_default_action = '';
    public $base_url = '/My/Password';
    public $model_name = 'Users';

    public function IndexAction() {
        $this->routeRedirect("ShowForm");
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

            $vars = FormUtils::filter($item, 'email pwd');
            $this->model->update($id, $vars);

            $this->fw->model('Events')->logEvent('chpwd', $id);
            $this->fw->flash("record_updated", true);
            fw::redirect($this->base_url);

        }catch( ApplicationException $ex ){
            $this->setFormError($ex->getMessage());
            $this->routeRedirect("ShowForm");
        }
    }

    public function Validate($id, $item) {
        $result= $this->validateRequired($item, "email old_pwd pwd pwd2");

        if ($result){
            $itemdb=$this->model->one($id);
            if ( $item['old_pwd']!=$itemdb['pwd'] ){
                $this->setError('old_pwd', 'WRONG');
            }

            if ($this->model->isExists( $item['email'], $id ) ){
                $this->setError('email', 'EXISTS');
            }

            if (!FormUtils::isEmail( $item['email'] ) ){
                $this->setError('email', 'WRONG');
            }

            if ($item['pwd']!=$item['pwd2'] ){
                $this->setError('pwd2', 'NOTEQUAL');
            }

        }

        $this->validateCheckResult();
    }

}//end of class

?>