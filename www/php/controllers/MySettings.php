<?php

class MySettingsController extends FwController {
    const int    access_level         = 0; #logged only
    const string route_default_action = '';

    public Users $model;
    public string $model_name = 'Users';

    public string $base_url = '/My/Settings';

    public function __construct() {
        parent::__construct();
        $this->model = $this->model0; // use then $this->model in code for proper type hinting
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

            $vars = FormUtils::filter($item, 'email fname lname address1 address2 city state zip phone');
            $this->model->update($id, $vars);

            $this->fw->flash("record_updated", true);
            fw::redirect($this->base_url);

        } catch (ApplicationException $ex) {
            $this->setFormError($ex);
            $this->routeRedirect("ShowForm");
        }
    }

    public function Validate($id, $item) {
        $result = $this->validateRequired($item, "email");

        if ($result) {
            if ($this->model->isExists($item['email'], $id)) {
                $this->setError('email', 'EXISTS');
            }

            if (!FormUtils::isEmail($item['email'])) {
                $this->setError('email', 'WRONG');
            }
        }

        $this->validateCheckResult();
    }

}//end of class
