<?php
/*
 FwUpdates model class

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2025 Oleg Savchuk www.osalabs.com
*/

class FwUpdates extends FwModel {
    public const int STATUS_FAILED  = 20;
    public const int STATUS_APPLIED = 30;

    public function __construct() {
        parent::__construct();

        $this->table_name = 'fwupdates';
    }

    /**
     * Load new updates from the updates directory and add them to the database.
     * @return void
     */
    public function loadUpdates(): void {
        $updates_root = $this->fw->config->SITE_ROOT_OFFLINE . '/db/updates';
        logger("checking $updates_root");
        if (!is_dir($updates_root)) {
            return;
        }

        $files = scandir($updates_root); # sorted order is alphabetical in ascending order
        foreach ($files as $file) {
            logger("checking $file");
            if ($file === '.' || $file === '..' || !str_ends_with($file, '.sql')) {
                continue;
            }

            if ($this->isExists($file)) {
                continue; # already exists
            }

            $filepath = $updates_root . '/' . $file;
            $content  = file_get_contents($filepath);
            $this->add([
                'iname' => $file,
                'idesc' => $content
            ]);
        }
    }

    public function listPending(): array {
        return $this->db->arr($this->table_name, ['status' => self::STATUS_ACTIVE], 'id');
    }

    public function applyPending(bool $is_echo = false): void {
        $rows = $this->listPending();
        foreach ($rows as $row) {
            $this->applyOne($row['id'], $is_echo);
        }

        @session_start();
        $_SESSION['FW_UPDATES_CTR'] = 0;
        @session_write_close();
    }

    public function applyOne(int $id, bool $is_echo = false): void {
        $row = $this->one($id);
        if ($is_echo) {
            rw("<b>{$row['iname']} applying</b>");
        }

        $uitem = [
            'status'       => self::STATUS_APPLIED,
            'applied_time' => DB::NOW()
        ];

        try {
            //            if ($is_echo) {
            //                rw($row['idesc']);
            //            }

            $this->db->exec("BEGIN");
            $this->db->execMultipleSQL($row['idesc']);
            $this->db->exec("COMMIT");

            $this->update($id, $uitem);

        } catch (Exception $e) {
            #rollback
            $this->db->exec("ROLLBACK");

            $uitem['status']     = self::STATUS_FAILED;
            $uitem['last_error'] = $e->getMessage();
            $this->update($id, $uitem);

            if ($is_echo) {
                rw("<b style='color:red'>{$row['iname']} failed</b>");
            }
            #re-throw
            throw $e;
        }
    }

    public function getCountPending(): int {
        return $this->getCount([self::STATUS_ACTIVE]);
    }

    public function checkApplyIfDev(): void {
        if (!$this->fw->config->IS_DEV) {
            return; #only auto-apply in dev
        }
        $this->loadUpdates();

        #if any pending updates - redirect to automatically apply
        if ($this->getCountPending()) {
            fw::redirect('/Dev/Configure/(ApplyUpdates)');
        }
    }
}
