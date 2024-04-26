<?php
/*
 Att - file inline/downloads controller

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2024 Oleg Savchuk www.osalabs.com
*/

class AttController extends FwController {
    const string route_default_action = 'show';
    public string $model_name = 'Att';

    public function IndexAction(): ?array {
        $ps = array();

        return $ps;
    }

    public function DownloadAction($id = '') {
        $id += 0;
        if (!$id) {
            throw new ApplicationException("404 File Not Found");
        }
        $size = reqs('size');

        $this->model->transmitFile($id, $size);
    }

    public function ShowAction($id = '') {
        $id += 0;
        if (!$id) {
            throw new ApplicationException("404 File Not Found");
        }
        $size       = reqs('size');
        $is_preview = reqi('preview');

        if ($is_preview) {
            $item = $this->model->one($id);
            if ($item['is_image']) {
                $this->model->transmitFile($id, $size, 'inline');
            } else {
                #if it's not an image and requested preview - return std image
                header('location: ' . $this->fw->config->ASSETS_URL . '/img/att_file.png');

                // $filepath = $this->fw->config->site_root.'/img/att_file.png'; # TODO move to web.config or to model?
                // header('Content-type: '.UploadUtils::getMimeForExt($item['ext']));
                // $fp = fopen($filepath, 'rb');
                // fpassthru($fp);
            }
        } else {
            $this->model->transmitFile($id, $size, 'inline');
        }
    }
}
