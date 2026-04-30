<?php
/*
Distributed locks class

Usage example:

if (!Locks::i()->lock(self::LOCK_CODE)) return false;
.... do something....
Locks::i()->unlock(self::LOCK_CODE);

#for particular item and for 60 seconds
if (!Locks::i()->lock(self::LOCK_CODE, $id, 60)) return false;
.... do something....
Locks::i()->unlock(self::LOCK_CODE, $id);

 */

class Locks extends FwModel {

    public function __construct() {
        parent::__construct();

        $this->table_name = 'locks';

        #$this->db = SiteUtils::connectDBHostQuick($this->fw); #this model works with Quick DB
    }

    protected function lockTimeField(): string {
        $schema = $this->schema();
        $fields = array_column($schema, 'name');
        if (in_array('upd_time', $fields, true)) {
            return 'upd_time';
        }
        if (in_array('updated', $fields, true)) {
            return 'updated';
        }
        return 'add_time';
    }

    protected function lockTimeSql(): string {
        $schema = $this->schema();
        $fields = array_column($schema, 'name');
        $qadd = $this->db->qid('add_time');
        $hasUpdTime = in_array('upd_time', $fields, true);
        $hasUpdated = in_array('updated', $fields, true);

        if ($hasUpdTime && $hasUpdated) {
            return 'IFNULL(' . $this->db->qid('upd_time') . ', IFNULL(' . $this->db->qid('updated') . ', ' . $qadd . '))';
        }
        if ($hasUpdTime) {
            return 'IFNULL(' . $this->db->qid('upd_time') . ', ' . $qadd . ')';
        }
        if ($hasUpdated) {
            return 'IFNULL(' . $this->db->qid('updated') . ', ' . $qadd . ')';
        }
        return $qadd;
    }

    #cleanup - auto-expire locks
    public function cleanup(): void {
        $this->db->exec("delete from " . $this->qTable() . " where DATE_ADD(" . $this->lockTimeSql() . ", INTERVAL " . $this->db->qid('expires') . " SECOND)<NOW()");
    }

    // check if lock exists
    public function exists(string $icode, int $item_id = 0, ?string $environment = null): bool {
        if (empty($environment)) {
            $environment = SiteUtils::getEnvironment();
        }

        $row = $this->db->row($this->table_name, [
            'icode'       => $icode,
            'environment' => $environment,
            'item_id'     => $item_id
        ]);
        if ($row) {
            $expires         = $row['expires'] ?? 3600;
            $upd_time        = $row['upd_time'] ?? $row['updated'] ?? $row['add_time'];
            $expiration_time = strtotime($upd_time) + $expires;
            return $expiration_time > time();
        }

        return false;
    }

    /**
     * full params lock
     * @param string $icode lock code
     * @param integer $item_id optional, particular id
     * @param integer $expires optional, default auto expiration time in seconds - 1 hour = 3600 secs
     * @param string|null $environment optional, particular environment, if not set - SiteUtils::getEnvironment() used
     * @return boolean          true if lock obtained
     */
    public function lock(string $icode, int $item_id = 0, int $expires = 3600, ?string $environment = null): bool {
        $result = false;

        if (empty($environment)) {
            $environment = SiteUtils::getEnvironment();
        }

        #check
        $item = array(
            'icode'       => $icode,
            'environment' => $environment,
            'item_id'     => $item_id,
            'expires'     => $expires,
            'host'        => gethostname(),
            'script'      => $_SERVER['PHP_SELF'],
            'pid'         => getmypid(),
        );
        try {
            $this->db->insert($this->table_name, $item);
            $result = true;
        } catch (Exception $e) {
            #if exception then insert failed - current lock exists or db error
            #just launch cleanup before exit
            $this->cleanup();
        }

        return $result;
    }

    /**
     * unlock the lock
     * @param string $icode
     * @param int $item_id
     * @param string|null $environment
     * @return bool false if failed to unlock (like db error)
     */
    public function unlock(string $icode, int $item_id = 0, ?string $environment = null): bool {
        $result = true;

        if (empty($environment)) {
            $environment = SiteUtils::getEnvironment();
        }

        try {
            $this->db->delete($this->table_name, [
                'icode'       => $icode,
                'environment' => $environment,
                'item_id'     => $item_id
            ]);
        } catch (DBException $ex) {
            $result = false;
            logger("NOTICE", "Locks unlock failed", $ex->getCode(), $ex->getMessage());
            #ignore DB errors, no need to fail whole thing if we can't unlock (for example server rebooting)
        }
        return $result;
    }

    /**
     * extend lock - set updated time, so expiration time extended
     * call if you need to keep lock while working
     * @param string $icode
     * @param int $item_id
     * @param string|null $environment
     * @return bool
     */
    public function extend(string $icode, int $item_id = 0, ?string $environment = null): bool {
        $result = true;

        if (empty($environment)) {
            $environment = SiteUtils::getEnvironment();
        }

        try {
            $time_field = $this->lockTimeField();
            if ($time_field === 'add_time') {
                return false;
            }
            $rows_updated_ctr = $this->db->update($this->table_name, [
                $time_field => DB::NOW()
            ], [
                'icode'       => $icode,
                'environment' => $environment,
                'item_id'     => $item_id
            ]);
            #check if any rows actually updated and if not - return false to indicate that lock already expired
            if ($rows_updated_ctr == 0) {
                $result = false;
            }

        } catch (DBException $ex) {
            $result = false;
            logger("NOTICE", "Locks extend failed", $ex->getCode(), $ex->getMessage());
            #ignore DB errors, no need to fail whole thing if we can't update (for example server rebooting) as if lock server inaccessible, other script that want to lock won't get lock anyway
        }
        return $result;
    }

}
