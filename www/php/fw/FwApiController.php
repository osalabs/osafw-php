<?php
/*
Base Fw Api Controller class for building APIs

Part of PHP osa framework  www.osalabs.com/osafw/php
(c) 2009-2029 Oleg Savchuk www.osalabs.com
 */

class FwApiController extends FwController {
    #public $base_url = '/Api/SomeController'; #SET IN CHILD CLASS

    public function __construct() {
        parent::__construct();

        Api::i()->prepare(true); #auth+set headers
        #$this->model_name='SET IN CHILD CLASS';
    }

    public function setApiError($ex, &$ps) {
        logger("FATAL", $ex);
        $ps['success'] = false;
        $ps['err_msg'] = $ex->getMessage();
    }

    //sample API method /Api/SomeController/(Test)/$form_id
    // public function TestAction($form_id = '') {
    //     $id = $form_id + 0;
    //     $ps = array(
    //         '_json'   => true,
    //         'success' => true,
    //     );

    //     try {
    //         //do something
    //     } catch (Exception $ex) {
    //         $ps['success'] = false;
    //         $ps['err_msg'] = $ex->getMessage();
    //     }

    //     return $ps;
    // }
}
