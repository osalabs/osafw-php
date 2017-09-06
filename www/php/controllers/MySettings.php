<?php

class MySettingsController extends FwController {
    const access_level = 0; #logged only
    const route_default_action = '';
    public $base_url = '/My/Settings';
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

            $vars = FormUtils::filter($item, 'email fname lname address1 address2 city state zip phone');
            $this->model->update($id, $vars);

            $this->fw->flash("record_updated", true);
            fw::redirect($this->base_url);

        }catch( ApplicationException $ex ){
            $this->setFormError($ex->getMessage());
            $this->routeRedirect("ShowForm");
        }
    }

    public function Validate($id, $item) {
        $result= $this->validateRequired($item, "email");

        if ($result){
            if ($this->model->isExists( $item['email'], $id ) ){
                $this->setError('email', 'EXISTS');
            }

            if (!FormUtils::isEmail( $item['email'] ) ){
                $this->setError('email', 'WRONG');
            }
        }

        $this->validateCheckResult();
    }

}//end of class

?>