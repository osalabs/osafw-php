<?php
/*
 Activity Logs model class
 can be used for:
 - log user activity
 - log comments per entity
 - log changes per entity
 - log related events per entity
 - log custom user events per entity

 Can be used as a base class for custom log models

Part of PHP osa framework  www.osalabs.com/osafw/php
(c) 2009-2024 Oleg Savchuk www.osalabs.com
 */

class FwActivityLogs extends FwModel {
    public const string TAB_ALL      = "all";
    public const string TAB_COMMENTS = "comments";
    public const string TAB_HISTORY  = "history";

    public function __construct() {
        parent::__construct();

        $this->table_name     = "activity_logs";
        $this->is_log_changes = false; // disable logging of changes in this table as this is log table itself
    }

    /**
     * add new log record by icodes
     * @param string $log_types_icode - required, must be predefined constant from FwLogTypes
     * @param string $entity_icode - required, fwentity, basically table name - if not exists - autocreated
     * @param int $item_id - related item id, if 0 - NULL will be stored in db
     * @param string $idesc - optional title/description
     * @param array|null $payload - optional payload (will be serialized as json)
     * @return int
     * @throws ApplicationException
     * @throws NoModelException
     */
    public function addSimple(string $log_types_icode, string $entity_icode, int $item_id = 0, string $idesc = "", array $payload = null): int {
        $lt = FwLogTypes::i()->oneByIcode($log_types_icode);
        if (empty($lt)) {
            throw new ApplicationException("Log type not found for icode=[" . $log_types_icode . "]");
        }
        $et_id  = FwEntities::i()->idByIcodeOrAdd($entity_icode);
        $fields = [
            "log_types_id"  => $lt["id"],
            "fwentities_id" => $et_id,
            "idesc"         => $idesc,
            "users_id"      => $this->fw->userId > 0 ? $this->fw->userId : null
        ];
        if ($item_id != 0) {
            $fields["item_id"] = $item_id;
        }
        if ($payload) {
            $fields["payload"] = Utils::jsonEncode($payload);
        }
        return $this->add($fields);
    }

    /**
     * return activity for given entity
     * @param string $entity_icode - entity table name
     * @param int $id - entity item id
     * @param array|null $log_types_icodes - optional list of log types(by icode) to filter on
     * @return array
     * @throws NoModelException
     */
    public function listByEntity(string $entity_icode, int $id, array $log_types_icodes = null): array {
        $fwentities_id = FwEntities::i()->idByIcodeOrAdd($entity_icode);
        $where         = [
            "fwentities_id" => $fwentities_id,
            "item_id"       => $id
        ];
        if ($log_types_icodes && count($log_types_icodes) > 0) {
            $log_types_ids = [];
            foreach ($log_types_icodes as $icode) {
                $log_type        = FwLogTypes::i()->oneByIcode($icode);
                $log_types_ids[] = $log_type["id"];
            }
            $where["log_types_id"] = $this->db->opIN($log_types_ids);
        }

        return $this->db->arr($this->table_name, $where, "idate desc, id desc");
    }

    /**
     * Return activity for given entity for UI
     *
     * @param string $entity_icode
     * @param int $id
     * @param string $tab "all", "comments", or "history"
     * @return array
     * @throws NoModelException
     */
    public function listByEntityForUI(string $entity_icode, int $id, string $tab = ""): array {
        // Convert tab to log_types_icodes
        $log_types_icodes = [];
        switch ($tab) {
            case self::TAB_COMMENTS:
                $log_types_icodes[] = FwLogTypes::ICODE_COMMENT;
                break;
            case self::TAB_HISTORY:
                $log_types_icodes[] = FwLogTypes::ICODE_ADDED;
                $log_types_icodes[] = FwLogTypes::ICODE_UPDATED;
                $log_types_icodes[] = FwLogTypes::ICODE_DELETED;
                break;
        }

        // Prepare list of activity records for UI
        $last_fields       = null;
        $last_add_time     = new DateTime("0000-00-00 00:00:00");
        $last_users_id     = -1;
        $last_log_types_id = -1;

        $result = [];
        $rows   = $this->listByEntity($entity_icode, $id, $log_types_icodes);
        foreach ($rows as $row) {
            $add_time     = new DateTime($row["add_time"]);
            $users_id     = intval($row["users_id"]);
            $log_types_id = intval($row["log_types_id"]);
            $log_type     = FwLogTypes::i()->one($log_types_id);

            // For system types - fill fields from payload
            $is_merged = false;
            if (intval($log_type["itype"]) == FwLogTypes::ITYPE_SYSTEM) {
                if ($last_fields !== null
                    && $last_log_types_id == $log_types_id
                    && $last_users_id == $users_id
                    && $last_add_time->diff($add_time)->i < 10
                ) {
                    // Same user and short time between updates - merge with last_fields
                    $is_merged = true;
                } else {
                    // New row
                    $last_fields = [];
                }

                $payload = json_decode($row["payload"], true);
                $fields  = $payload["fields"] ?? null;
                if ($fields !== null) {
                    foreach ($fields as $key => $value) {
                        // If key is password, pass, pwd - hide value
                        if (str_contains($key, "pass") || str_contains($key, "pwd")) {
                            $value = "********";
                        }

                        // Deduplicate - if key already exists - skip, because we are merging older row into newer
                        if (!array_key_exists($key, $last_fields)) {
                            $last_fields[$key] = $value;
                        }
                    }
                }
            } else {
                $last_fields = null; // Reset fields for user types
            }

            $last_users_id     = $users_id;
            $last_add_time     = $add_time;
            $last_log_types_id = $log_types_id;

            if ($is_merged) {
                continue; // Skip this row as it's merged with previous
            }

            $new_row             = [];
            $new_row["idesc"]    = $row["idesc"];
            $new_row["idate"]    = $row["idate"];
            $new_row["add_time"] = $row["add_time"];
            $new_row["upd_time"] = $row["upd_time"];
            $new_row["tab"]      = $tab;
            $new_row["log_type"] = $log_type;

            $user            = Users::i()->one($users_id);
            $new_row["user"] = $user;
            if (intval($user["att_id"]) > 0) {
                $new_row["avatar_link"] = Att::i()->getUrl(intval($user["att_id"]), "s");
            }
            if (!empty($row["upd_users_id"])) {
                $new_row["upd_user"] = Users::i()->one($row["upd_users_id"]);
            }
            if ($last_fields !== null) {
                $new_row["fields"] = $last_fields;
            }

            $result[] = $new_row;
        }

        return $result;
    }

    public function getCountByLogIType(int $log_itype, array $statuses = null, int $since_days = null): int {
        $sql = "SELECT count(*) 
                    from {$this->qTable()} al 
                        INNER JOIN {FwLogTypes::i()->qTable()} lt on (lt.id=al.log_types_id)
                    where lt.itype=" . dbqi($log_itype) . "
                     and al.status " . $this->db->insqli($statuses);
        if ($since_days !== null) {
            $sql .= " and al.add_time > DATEADD(day, " . $this->db->q($since_days) . ", GETDATE())";
        }

        return intval($this->db->valuep($sql));
    }


}
