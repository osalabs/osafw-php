<?php
/*
 Distributed Locks (MySQL-based) class

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2020 Oleg Savchuk www.osalabs.com

Usage example:

if (!fw::model('Locks')->lock(static::lock_icode)) return false;
.... do something....
fw::model('Locks')->unlock(static::lock_icode);

Also create this table:
CREATE TABLE locks (
    icode               varchar(64) NOT NULL,
    item_id             int unsigned NOT NULL DEFAULT 0,
    expires             int unsigned,

    host                varchar(255) NOT NULL default '',
    script              varchar(255) NOT NULL default '',
    pid                 varchar(255) NOT NULL default '',

    upd_time            timestamp default CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    upd_users_id        int unsigned default 0,

    PRIMARY KEY (icode)
) DEFAULT CHARSET=utf8mb4;

 */

class Locks extends FwModel {
    /** @return Locks */
    public static function i() {
        return parent::i();
    }

    public function __construct() {
        parent::__construct();

        $this->table_name   = 'locks';
        $this->field_id     = 'icode';
        $this->field_status = '';
        $this->field_add_users_id = '';
    }

    #cleanup - auto-expire locks
    public function cleanup($value = '') {
        $this->db->exec("delete from $this->table_name where DATE_ADD(upd_time, INTERVAL expires SECOND)<NOW()");
    }

    /**
     * full params lock
     * @param  string  $icode   lock code
     * @param  integer $item_id optional, particular id
     * @param  integer $expires optional, default auto expiration time in seconds - 1 hour = 3600 secs
     * @return boolean          true if lock obtained
     */
    public function lockFull($icode, $item_id = 0, $expires = 3600) {
        $result = false;

        #check
        $item = array(
            'icode'   => $icode,
            'item_id' => $item_id,
            'expires' => $expires,
            'host'    => gethostname(),
            'script'  => $_SERVER['PHP_SELF'],
            'pid'     => getmypid(),
        );
        try {
            $this->db->insert($this->table_name, $item);
            $result = true;
        } catch (Exception $e) {
            #if exception then insert failed - current lock exists
            #just launch cleanup before exit
            $this->cleanup();
        }

        return $result;
    }

    #simple lock by icode
    public function lock($icode, $expires = 3600) {
        return $this->lockFull($icode, '', 0, $expires);
    }

    #unlock
    public function unlock($icode, $item_id = 0) {
        $this->db->exec("delete from $this->table_name where icode=" . $this->db->quote($icode) . " and item_id=" . $this->db->quote($item_id));
    }

    #extend lock - set updated time, so expiration time extended
    #call if you need to keep lock while working
    public function extend($icode, $item_id = 0) {
        $this->db->exec("update $this->table_name set upd_time=NOW() where icode=" . $this->db->quote($icode) . " and item_id=" . $this->db->quote($item_id));
        #TODO probably would be good to check if any rows actually updated and if not - return false to indicate that lock already expired
    }

}
