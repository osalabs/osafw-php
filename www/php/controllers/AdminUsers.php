<?php

class AdminUsersController extends FwAdminController {
    const route_default_action = '';
    public $base_url = '/Admin/Users';
    public $required_fields = 'email access_level';
    public $save_fields = 'email pwd access_level fname lname title address1 address2 city state zip phone status att_id';
    public $model_name = 'Users';

    public $list_sortdef = 'iname asc';    //default sorting - req param name, asc|desc direction
    public $list_sortmap = array(       //sorting map: req param name => sql field name(s) asc|desc direction
                        'id'            => 'id',
                        'iname'         => 'fname,lname',
                        'email'         => 'email',
                        'access_level'  => 'access_level',
                        'status'        => 'status',
                        'add_time'      => 'add_time',
                        );
    public $search_fields = 'email fname lname';  //fields to search via $s$list_filter['s'], ! - means exact match, not "like"
                                            //format: 'field1 field2,!field3 field4' => field1 LIKE '%$s%' or (field2 LIKE '%$s%' and field3='$s') or field4 LIKE '%$s%'

    public function setListSearch() {
        $this->list_where=' 1=1 ';

        parent::setListSearch();

        if ($this->list_filter['status']>''){
            $this->list_where .= ' and status='.$this->db->quote($this->list_filter['status']);
        }else{
            $this->list_where .= ' and status<>127';
        }
    }

    public function ShowFormAction($form_id){
        $ps = parent::ShowFormAction($form_id);
        $ps['att']= $ps['i']['att_id'] ? fw::model('Att')->one($ps['i']['att_id']+0) : '';
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
            if (!strlen($itemdb['pwd'])) unset($itemdb['pwd']);

            $id = $this->modelAddOrUpdate($id, $itemdb);

            if ($id==Utils::me()) $this->model->reloadSession();

            fw::redirect($this->base_url.'/'.$id.'/edit');

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
        }

        $this->validateCheckResult();
    }

    public function Export($ps, $format) {
        if ($format!='csv') throw new ApplicationException("Unsupported format");

        $fields = array(
            'fname'   => 'First Name',
            'lname'   => 'Last Name',
            'email'   => 'Email',
            'add_time' => 'Added',
        );
        Utils::responseCSV($ps['list_rows'], $fields, "members.csv");
    }

    //send email notification with password
    public function SendPwdAction($form_id){
        $id=$form_id+0;

        $user = $this->model->one($id);
        $this->fw->sendEmailTpl( $user['email'], 'email_pwd.txt', $user);

        return array(
            '_json' => array(
                    'success'   => true,
                ),
        );
    }

}//end of class

?>