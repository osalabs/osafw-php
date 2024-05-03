<?php
/*
 Admin Reports Controller class

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2024 Oleg Savchuk www.osalabs.com
*/

class AdminReportsController extends FwAdminController {
    const int access_level = Users::ACL_MANAGER;
    public Reports $model;
    public string $model_name = 'Reports';

    public string $base_url = '/Admin/Reports';
    public $is_admin = false;

    public function __construct() {
        parent::__construct();

        $this->model = $this->model0;

        //optionally init controller
        $this->is_admin = Users::i()->isAccessLevel(Users::ACL_MANAGER);
    }

    public function IndexAction(): ?array {
        $ps = array();
        return $ps;
    }

    public function ShowAction($repcode): ?array {
        $ps = array();

        $repcode      = $this->model->cleanupRepcode($repcode);
        $ps["is_run"] = reqs("dofilter") > "" || reqs("is_run") > "";

        #report filters (options)
        $f = $this->initFilter("AdminReports." . $repcode);

        #get format directly form request as we don't need to remember format
        $f["format"] = reqh("f")["format"];
        if (!$f["format"]) {
            $f["format"] = "html";
        }

        $report = $this->model->createInstance($repcode, $f);

        $ps["f"] = $report->getReportFilters();
        if ($ps["is_run"]) {
            $ps["rep"] = $report->getReportData();
        }

        #show or output report according format
        $report->render($ps);
        return null;
    }

    #save changes from editable reports
    public function SaveAction($form_id): ?array {
        $repcode = $form_id;
        $repcode = $this->model->cleanupRepcode(reqs("repcode"));

        $report = $this->model->createInstance($repcode, reqh("f"));

        try {
            if ($report->saveChanges()) {
                fw::redirect($this->base_url . "/" . $repcode . "?is_run=1");
            } else {
                $_REQUEST['is_run'] = 1;
                $this->fw->routeRedirect("Show");
            }
        } catch (ApplicationException $ex) {
            $this->setFormError($ex);
            $this->routeRedirect("ShowForm");
        }
        return null;
    }

}//end of class
