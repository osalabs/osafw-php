<?php
/*
 Att - file inline/downloads controller

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2024 Oleg Savchuk www.osalabs.com
*/

class AttController extends FwController {
    const string route_default_action = 'show';

    public Att $model;
    public string $model_name = 'Att';

    public function __construct() {
        parent::__construct();
        $this->model = $this->model0; // use then $this->model in code for proper type hinting
    }

    public function IndexAction(): ?array {
        return $this->redirect($this->fw->config->ASSETS_URL . Att::IMGURL_0);
    }

    public function DownloadAction($form_id): void {
        $id = intval($form_id);
        if (!$id) {
            throw new ApplicationException("404 File Not Found");
        }
        $size = reqs('size');

        $item = $this->model->one($id);
        if (!$item) {
            throw new ApplicationException("404 File Not Found");
        }
        if ($item['is_s3']) {
            $this->model->redirectS3($id, $size);
        } else {
            $this->model->transmitFile($id, $size);
        }
    }

    public function ShowAction($form_id): void {
        $id = intval($form_id);
        if (!$id) {
            throw new ApplicationException("404 File Not Found");
        }
        $size       = reqs('size');
        $is_preview = reqb('preview');

        $item = $this->model->one($id);
        if (!$item) {
            throw new ApplicationException("404 File Not Found");
        }
        if ($item['is_s3']) {
            $this->model->redirectS3($id, $size);
            return;
        }

        if ($is_preview) {
            if ($item['is_image']) {
                $this->model->transmitFile($id, $size, 'inline');
            } else {
                #if it's not an image and requested preview - return std image
                header('location: ' . $this->fw->config->ASSETS_URL . Att::IMGURL_FILE);
            }
        } else {
            $this->model->transmitFile($id, $size, 'inline');
        }
    }
}
