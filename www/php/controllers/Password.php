<?php

class PasswordController extends FwController {
    const string route_default_action = '';

    public FwModel|Users $model;
    public string $model_name = 'Users';

    public string $base_url = '/Password';

    protected const int PWD_RESET_EXPIRATION = 60; // minutes

    public function __construct() {
        parent::__construct();

        #override layout
        $this->fw->page_layout = $this->fw->config->PAGE_LAYOUT_PUBLIC;
    }

    public function IndexAction(): ?array {
        if ($this->isGet()) {
            #defaults
            $item = array();
        } else {
            $item = reqh('item');
        }

        $ps = array(
            'i'            => $item,
            'hide_sidebar' => true,
        );
        return $ps;
    }

    public function SaveAction(): ?array {
        $this->route_onerror = FW::ACTION_INDEX;

        $item          = reqh('item');
        $item['login'] = trim($item['login']);

        $this->Validate(0, $item);
        $user = $this->model->oneByEmail($item['login']);

        $this->model->sendPwdReset($user['id']);

        fw::redirect($this->base_url . '/(Sent)');

        return null;
    }

    public function Validate(int $id, array $item): void {
        $result = $this->validateRequired($item, "login");

        if ($result) {
            $user = $this->model->oneByEmail($item['login']);
            if (!count($user) || $user['status'] != FwModel::STATUS_ACTIVE) {
                $this->setError('login', 'WRONG');
            }
        }

        $this->validateCheckResult();
    }

}//end of class
