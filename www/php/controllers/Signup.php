<?php

class SignupController extends FwAdminController {
    const access_level = null;
    const route_default_action = '';
    public $base_url='/Signup';
    public $required_fields = 'email pwd pwd2';
    public $save_fields = 'email fname lname pwd';
    public $model_name = 'Users';

    public function __construct() {
        parent::__construct();

        #override layout
        $this->fw->page_layout = $this->fw->config->PAGE_LAYOUT_PUBLIC;
        if (!$this->fw->config->IS_SIGNUP) throw new ApplicationException("Sign Up access denied by site config [IS_SIGNUP]");
    }

    public function IndexAction() {
        $this->fw->routeRedirect("ShowForm");
    }

    public function ShowFormAction($form_id) {
        $id = $form_id+0;

        if ($this->fw->isGetRequest()){
            if ($id>0){
                $item = $this->model->one($id);
            }else{
                #defaults
                $item=array(
                );
            }
        }else{
            $itemdb = $id ? $this->model->one($id) : array();
            $item = array_merge($itemdb, reqh('item'));
        }

        $ps = array(
            'id'    => $id,
            'i'     => $item,
        );

        return $ps;
    }

    public function SaveAction($form_id) {
        $id = $form_id+0;
        $item = reqh('item');

        try{
            $this->Validate($id, $item);
            #load old record if necessary
            #$item_old = $this->model->one($id);

            $itemdb = FormUtils::filter($item, $this->save_fields);
            $itemdb['pwd']=$this->model->encryptPwd($itemdb['pwd']);

            $id = $this->modelAddOrUpdate($id, $itemdb);

            #signup confirmaiton email
            $user = $this->model->one($id);
            $ps=array(
                'user' => $user,
            );
            $this->fw->sendEmailTpl( $user['email'], 'signup.txt', $ps);

            $this->model->doLogin( $id );
            fw::redirect($this->fw->config->LOGGED_DEFAULT_URL);

        }catch( ApplicationException $ex ){
            $this->setFormError($ex->getMessage());
            $this->routeRedirect("ShowForm");
        }
    }

    public function Validate($id, $item) {
        $result= $this->validateRequired($item, $this->required_fields);

        //result here used only to disable further validation if required fields validation failed
        if ($result){
            if ($this->model->isExists( $item['email'], $id ) ){
                $this->setError('email', 'EXISTS');
            }

            if (!FormUtils::isEmail( $item['email'] ) ){
                $this->setError('email', 'WRONG');
            }

            if ( $item['pwd']>"" && $item['pwd']!=$item['pwd2'] ){
                $this->setError('pwd', 'WRONG');
            }
        }

        $this->validateCheckResult();
    }

}//end of class

?>