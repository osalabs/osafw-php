<?php
/*
 WebFormMailer controller
 Send all form fields to site support_email
 Then redirect to form("redirect") url

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2015 Oleg Savchuk www.osalabs.com
*/


class WebFormMailerController extends FwController {
    const route_default_action = '';

    public function __construct() {
        parent::__construct();
    }

    public function SaveAction() {
        $mail_to = $this->fw->G['SUPPORT_EMAIL'];
        $mail_subject = reqs('subject');
        $redirect_to = reqs('redirect');

        $sys_fields = Utils::qh('form_format redirect subject submit RAWURL XSS');
        $msg_body='';
        foreach ($_POST as $key => $value) {
            if (array_key_exists($key, $sys_fields)) continue;
            $msg_body.=$key.' = '.$value."\n";
        }

        $this->fw->send_email($mail_to, $mail_subject, $msg_body);

        //need to add root_domain, so no one can use our redirector for bad purposes
        fw::redirect($this->fw->G['ROOT_DOMAIN'].$redirect_to);
    }

}

?>