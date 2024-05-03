<?php
/*
 Admin Users controller

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2024 Oleg Savchuk www.osalabs.com
*/

class AdminUsersController extends FwDynamicController {
    const int access_level = Users::ACL_ADMIN;

    public Users $model;
    public string $base_url = '/Admin/Users';

    public function __construct() {
        parent::__construct();
        $this->model = $this->model0; // use then $this->model in code for proper type hinting
    }

    public function setListSearch(): void {
        parent::setListSearch();

        if (!empty($this->list_filter["access_level"])) {
            $this->list_where                        .= " and access_level=@access_level";
            $this->list_where_params["access_level"] = $this->list_filter["access_level"];
        }
    }

    public function ShowFormAction($form_id): ?array {
        $ps = parent::ShowFormAction($form_id);
        $id = $ps['id'];

        $att_id    = $ps['i']['att_id'] ?? 0;
        $ps['att'] = Att::i()->one($att_id);

        $ps['is_roles']   = $this->model->isRoles();
        $ps['roles_link'] = $this->model->listLinkedRoles($id);
        return $ps;
    }

    public function SaveAction($form_id): ?array {
        $this->route_onerror = FW::ACTION_SHOW_FORM;

        if (empty($this->save_fields)) {
            throw new Exception("No fields to save defined, define in Controller.save_fields");
        }

        if (reqi('refresh') == 1) {
            $this->fw->routeRedirect(FW::ACTION_SHOW_FORM, null, [$form_id]);
        }

        $id      = intval($form_id);
        $item    = reqh('item');
        $is_new  = ($id == 0);
        $success = true;

        $item["email"] = $item["ehack"]; // just because Chrome autofills fields too agressively

        $this->Validate($id, $item);
        #load old record if necessary
        #$item_old = $this->model->one($id);

        $itemdb = $this->getSaveFields($id, $item);

        $itemdb['pwd'] = trim($itemdb['pwd']);
        if (!strlen($itemdb['pwd'])) {
            unset($itemdb['pwd']);
        }

        $id = $this->modelAddOrUpdate($id, $itemdb);

        $this->model->updateLinkedRoles($id, reqh('roles_link'));

        if ($id == $this->fw->userId()) {
            $this->model->reloadSession();
        }

        return $this->afterSave($success, $id, $is_new);
    }

    public function Validate($id, $item): void {
        $result = $this->validateRequired($item, $this->required_fields);

        //result here used only to disable further validation if required fields validation failed
        if ($result) {
            if ($this->model->isExists($item['email'], $id)) {
                $this->setError('ehack', 'EXISTS');
            }

            if (!FormUtils::isEmail($item['email'])) {
                $this->setError('ehack', 'WRONG');
            }
        }

        $this->validateCheckResult();
    }

    /**
     * cleanup session for current user and re-login as user from id
     * check access - only users with higher level may login as lower level
     * @param $id
     * @return void
     * @throws AuthException
     * @throws UserException
     */
    public function SimulateAction($id): void {
        $user = $this->model->one($id);
        if (empty($user)) {
            throw new UserException("Wrong User ID");
        }
        if ($user['access_level'] >= $this->fw->userAccessLevel()) {
            throw new AuthException("Access Denied. Cannot simulate user with higher access level");
        }

        $this->fw->logActivity(FwLogTypes::ICODE_USERS_SIMULATE, FwEntities::ICODE_USERS, $id);

        $this->model->doLogin($id);

        $this->fw->redirect((string)$this->fw->config->LOGGED_DEFAULT_URL);
    }

    //send email notification with password
    public function SendPwdAction($form_id): array {
        $id = intval($form_id);

        $success = $this->model->sendPwdReset($id);
        return array(
            '_json' => array(
                'success' => $success,
                'err_msg' => $this->fw->last_error_send_email
            ),
        );
    }

    public function ResetMFAAction($form_id): void {
        $this->fw->checkXSS();
        $id = intval($form_id);

        $this->model->update($id, ['mfa_secret' => null]);

        $this->fw->flash("success", "Multi-Factor Authentication Secret Reset");
        $this->fw->redirect($this->base_url . "/ShowForm/{$id}/edit");
    }

}//end of class
