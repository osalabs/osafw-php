<?php
/*
 Admin Users controller

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2024 Oleg Savchuk www.osalabs.com
*/

class AdminUsersController extends FwAdminController {
    const string route_default_action = '';
    public string $base_url = '/Admin/Users';
    public string $required_fields = 'email access_level';
    public string $save_fields = 'email pwd access_level fname lname title address1 address2 city state zip phone status att_id';
    public string $save_fields_nullable = 'att_id';
    public string $model_name = 'Users';

    public string $list_sortdef = 'iname asc';    //default sorting - req param name, asc|desc direction
    public array $list_sortmap = array(//sorting map: req param name => sql field name(s) asc|desc direction
                                       'id'           => 'id',
                                       'iname'        => 'fname,lname',
                                       'email'        => 'email',
                                       'access_level' => 'access_level',
                                       'status'       => 'status',
                                       'add_time'     => 'add_time',
    );
    public string $search_fields = 'email fname lname';  //fields to search via $s$list_filter['s'], ! - means exact match, not "like"

    //format: 'field1 field2,!field3 field4' => field1 LIKE '%$s%' or (field2 LIKE '%$s%' and field3='$s') or field4 LIKE '%$s%'

    public function ShowFormAction($form_id): ?array {
        $ps = parent::ShowFormAction($form_id);
        if (!$this->isGet()) {
            $ps['i']["email"] = $ps['i']["ehack"];
        }

        $att_id    = $ps['i']['att_id'] ?? 0;
        $ps['att'] = Att::i()->one($att_id);
        return $ps;
    }

    public function SaveAction($form_id): ?array {
        $this->fw->checkXSS();

        $id   = intval($form_id);
        $item = reqh('item');

        $item["email"] = $item["ehack"]; // just because Chrome autofills fields too agressively

        try {
            $this->Validate($id, $item);
            #load old record if necessary
            #$item_old = $this->model->one($id);

            $itemdb        = $this->getSaveFields($id, $item);
            $itemdb['pwd'] = trim($itemdb['pwd']);
            if (!strlen($itemdb['pwd'])) {
                unset($itemdb['pwd']);
            }

            $id = $this->modelAddOrUpdate($id, $itemdb);

            if ($id == $this->fw->userId()) {
                $this->model->reloadSession();
            }

            fw::redirect($this->base_url . '/' . $id . '/edit');

        } catch (ApplicationException $ex) {
            $this->setFormError($ex);
            $this->routeRedirect("ShowForm");
        }
    }

    public function Validate($id, $item): void {
        $result = $this->validateRequired($item, $this->required_fields);

        //result here used only to disable further validation if required fields validation failed
        if ($result) {
            if ($this->model->isExists($item['ehack'], $id)) {
                $this->setError('ehack', 'EXISTS');
            }

            if (!FormUtils::isEmail($item['ehack'])) {
                $this->setError('ehack', 'WRONG');
            }
        }

        $this->validateCheckResult();
    }

    public function Export($ps, $format) {
        if ($format != 'csv') {
            throw new ApplicationException("Unsupported format");
        }

        $fields = array(
            'fname'    => 'First Name',
            'lname'    => 'Last Name',
            'email'    => 'Email',
            'add_time' => 'Added',
        );
        Utils::responseCSV($ps['list_rows'], $fields, "members.csv");
    }

    //send email notification with password
    public function SendPwdAction($form_id) {
        $this->fw->checkXSS();

        $id = intval($form_id);

        $user = $this->model->one($id);
        $this->fw->sendEmailTpl($user['email'], 'email_pwd.txt', $user);

        return array(
            '_json' => array(
                'success' => true,
            ),
        );
    }

}//end of class
