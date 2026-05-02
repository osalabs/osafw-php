<?php
/*
 Site Contact Form controller

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2024 Oleg Savchuk www.osalabs.com
*/

class ContactController extends FwController {
    const string route_default_action = '';

    public string $required_fields = 'FirstName LastName Email';

    public function __construct() {
        parent::__construct();

        $this->base_url = '/Contact';

        #override layout
        $this->fw->page_layout = $this->fw->config->PAGE_LAYOUT_PUBLIC;
    }

    public function IndexAction(): ?array {
        $ps = array();

        $page               = Spages::i()->oneByFullUrl($this->base_url);
        $ps["page"]         = $page;
        $ps['hide_sidebar'] = true;
        return $ps;
    }

    public function SaveAction($form_id): ?array {
        $this->route_onerror = FW::ACTION_INDEX;

        $id   = 0;
        $item = reqh('item');

        $success = true; #if refresh - force route redirect to form
        $is_new  = ($id == 0);

        $this->Validate($id, $item);

        $itemdb   = $this->getSaveFields($id, $item);
        $msg_body = '';
        foreach ($itemdb as $key => $value) {
            $msg_body .= $key . ' = ' . $value . "\n";
        }

        $this->fw->sendEmail($this->fw->GLOBAL['SUPPORT_EMAIL'], 'Contact Form', $msg_body);

        return $this->afterSave($success, $id, $is_new, FW::ACTION_INDEX, $this->base_url . '/(Sent)');
    }

    public function Validate($id, $item): void {
        $result = $this->validateRequired($id, $item, $this->required_fields);
        $this->validateCheckResult();
    }
}
