<?php

class AttController extends FwController {
    const route_default_action = 'show';
    public $model_name = 'Att';

    public function IndexAction() {
        $ps = array();

        return $ps;
    }

    public function DownloadAction($id=''){
        $id+=0;
        If (!$id) throw new ApplicationException("404 File Not Found");
        $size = reqs('size');

        $this->model->transmit_file($id, $size);
    }

    public function ShowAction($id=''){
        global $CONFIG;
        $id+=0;
        If (!$id) throw new ApplicationException("404 File Not Found");
        $size = reqs('size');
        $is_preview = reqi('preview');

        if ($is_preview){
            $item = $this->model->one($id);
            if ($item['is_image']){
                $this->model->transmit_file($id, $size, 'inline');
            }else{
                #if it's not an image and requested preview - return std image
                header('location: '.$CONFIG['ROOT_URL'].'/img/att_file.png');

                // $filepath = $CONFIG['site_root'].'/img/att_file.png'; # TODO move to web.config or to model?
                // header('Content-type: '.UploadUtils::get_mime4ext($item['ext']));
                // $fp = fopen($filepath, 'rb');
                // fpassthru($fp);
            }
        }else{
            $this->model->transmit_file($id, $size, 'inline');
        }
    }
}

?>