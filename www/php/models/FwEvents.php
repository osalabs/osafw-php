<?php
/*
Events/Event logging model class
*/

class FwEvents extends FwModel {
    public $log_table_name = 'fwevents_log';

    public function __construct() {
        parent::__construct();

        $this->table_name = 'fwevents';
    }

    //add new record - override FwModel because we don't need to log this
    public function add($item) {
        if (!isset($item['add_users_id'])) $item['add_users_id']=Utils::me();
        $id=$this->db->insert($this->table_name, $item);

        return $id;
    }

    public function oneByIcode($icode){
        return $this->db->row($this->table_name, array('icode'=>$icode));
    }

    public function log($ev_icode, $item_id=0, $item_id2=0, $iname='', $records_affected=0, $fields=null){
        if (!$this->fw->config->IS_LOG_FWEVENTS) return;

        $ev = $this->oneByIcode($ev_icode);
        if (!$ev){
            logger('INFO', 'No event defined for icode=['.$ev_icode.'], auto-creating');
            $ev=array(
                'icode' => $ev_icode,
                'iname' => $ev_icode,
                'idesc' => 'auto-created',
            );
            $ev['id']=$this->add($ev);
        }

        $tolog = array(
            'events_id' => $ev['id'],
            'item_id'   => $item_id,
            'item_id2'  => $item_id2,
            'iname'     => $iname,
            'records_affected' => $records_affected,
            'add_users_id' => Utils::me(),
        );
        if (!is_null($fields)) $tolog['fields'] = json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
        return $this->db->insert($this->log_table_name, $tolog);
    }

    //just to log fields for particular record
    public function logFields($ev_icode, $item_id, $fields){
        return $this->log($ev_icode, $item_id, 0, '', 1, $fields);
    }

}

?>