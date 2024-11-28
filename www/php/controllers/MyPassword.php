<?php

class MyPasswordController extends FwController {
    const int    access_level         = 0; #logged only
    const string route_default_action = '';

    public FwModel|Users $model;
    public string $model_name = 'Users';

    public string $base_url = '/My/Password';

    public function __construct() {
        parent::__construct();
    }

    public function IndexAction(): ?array {
        $this->routeRedirect("ShowForm");
        return null;
    }

    public function ShowFormAction() {
        $id = $this->fw->userId();

        if ($this->isGet()) {
            if ($id > 0) {
                $item = $this->model->one($id);
            } else {
                #defaults
                $item = array();
            }
        } else {
            $itemdb = $this->model->one($id);
            $item   = array_merge($itemdb, reqh('item'));
        }

        $ps = array(
            'id' => $id,
            'i'  => $item,
        );

        return $ps;
    }

    public function SaveAction() {
        $id   = $this->fw->userId();
        $item = reqh('item');

        try {
            $this->Validate($id, $item);

            $vars        = FormUtils::filter($item, 'email pwd');
            $vars['pwd'] = trim($vars['pwd']);
            $this->model->update($id, $vars);

            $this->fw->logActivity(FwLogTypes::ICODE_USERS_CHPWD, FwEntities::ICODE_USERS, $id);
            $this->fw->flash("record_updated", true);
            fw::redirect($this->base_url);

        } catch (ApplicationException $ex) {
            $this->setFormError($ex);
            $this->routeRedirect("ShowForm");
        }
    }

    public function Validate($id, $item) {
        $result = $this->validateRequired($item, "email old_pwd pwd pwd2");

        if ($result) {
            $itemdb = $this->model->one($id);
            if (!$this->model->checkPwd($item['old_pwd'], $itemdb['pwd'])) {
                $this->setError('old_pwd', 'WRONG');
            }

            if ($this->model->isExists($item['email'], $id)) {
                $this->setError('email', 'EXISTS');
            }

            if (!FormUtils::isEmail($item['email'])) {
                $this->setError('email', 'WRONG');
            }

            if ($item['pwd'] != trim($item['pwd2'])) {
                $this->setError('pwd2', 'NOTEQUAL');
            }

        }

        $this->validateCheckResult();
    }

}//end of class

?>
