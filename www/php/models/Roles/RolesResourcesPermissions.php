<?php
/*
 RolesResourcesPermissions model class

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2024 Oleg Savchuk www.osalabs.com
*/

class RolesResourcesPermissions extends FwModel {
    const string KEY_DELIM = "#";

    public FwModel $junction_model_permissions;
    public string $junction_field_permissions_id;

    public function __construct() {
        parent::__construct();

        $this->table_name = 'roles_resources_permissions';
        $this->field_prio = 'prio';

        $junction_model_main      = Roles::i();
        $junction_field_main_id   = "roles_id";
        $junction_model_linked    = Resources::i();
        $junction_field_linked_id = "resources_id";

        $junction_model_permissions    = Permissions::i();
        $junction_field_permissions_id = "permissions_id";
    }

    public function matrixKey($resources_id, $permissions_id): string {
        return $resources_id . self::KEY_DELIM . $permissions_id;
    }

    // extract resources_id and permissions_id from key
    public function extractKey(string $key): array {
        list($resources_id, $permissions_id) = explode(self::KEY_DELIM, $key);
        return [(int)$resources_id, (int)$permissions_id];
    }

    /**
     * check if at least one record exists for resource/permission and multiple roles - i.e. user has a role with resource's permission
     * @param int $resources_id
     * @param int $permissions_id
     * @param array $roles_ids
     * @return bool
     * @throws DBException
     */
    public function isExistsByResourcePermissionRoles(int $resources_id, int $permissions_id, array $roles_ids): bool {
        $where = [
            "resources_id"   => $resources_id,
            "permissions_id" => $permissions_id,
            "roles_id"       => $this->db->opIN($roles_ids)
        ];
        $value = $this->db->value($this->table_name, $where, "1");

        return 1 == intval($value);
    }

    /**
     * list of records for given role and resource
     * @param int $roles_id
     * @param int $resources_id
     * @return array
     * @throws DBException
     */
    public function listByRoleResource(int $roles_id, int $resources_id): array {
        return $this->db->arr($this->table_name, ["roles_id" => $roles_id, "resources_id" => $resources_id]);
    }

    /**
     * return hashtable of [resources_id#permissions_id => row] for given role and resource
     * @param int $roles_id
     * @param int $resources_id
     * @return array
     * @throws DBException
     */
    public function matrixRowByRoleResource(int $roles_id, int $resources_id): array {
        $result = [];
        $rows   = $this->listByRoleResource($roles_id, $resources_id);
        foreach ($rows as $row) {
            $result[$this->matrixKey($row["resources_id"], $row["permissions_id"])] = $row;
        }
        return $result;
    }

    public function resourcesMatrixByRole(int $roles_id, array $permissions): array {
        $resources = Resources::i()->ilist();

        // for each resource - get permissions for this role
        foreach ($resources as &$resource) {
            $permissions_cols = [];
            $resource["permissions_cols"] = $permissions_cols;

            // load permissions for this resource
            $hpermissions = $this->matrixRowByRoleResource($roles_id, (int)$resource["id"]);

            foreach ($permissions as $permission) {
                $permission_col = [
                    "key"        => $this->matrixKey((int)$resource["id"], (int)$permission["id"]),
                    "is_checked" => isset($hpermissions[$this->matrixKey((int)$resource["id"], (int)$permission["id")])
                ];
                $permissions_cols[] = $permission_col;
            }
        }
        unset($resource);

        return $resources;
    }

    public function updateMatrixByRole(int $roles_id, array $hresources_permissions): void {
        #$permissions = Permissions::i()->ilist();

        $fields = [];
        $where  = [];

        // set all fields as under update
        $fields[$this->field_status] = self::STATUS_UNDER_UPDATE;
        $where[$this->junction_field_main_id] = $roles_id;
        $this->db->update($this->table_name, $fields, $where);

        foreach ($hresources_permissions as $key => $val) {
            list($resources_id, $permissions_id) = $this->extractKey($key);

            $fields = [
                $this->junction_field_main_id       => $roles_id,
                $this->junction_field_linked_id     => $resources_id,
                $this->junction_field_permissions_id => $permissions_id,
                $this->field_status                 => self::STATUS_ACTIVE,
                $this->field_upd_users_id           => $this->fw->userId(),
                $this->field_upd_time               => $this->db->now()
            ];
            $where = [
                $this->junction_field_main_id       => $roles_id,
                $this->junction_field_linked_id     => $resources_id,
                $this->junction_field_permissions_id => $permissions_id
            ];
            $this->db->upsert($this->table_name, $fields, $where);
        }

        // remove those who still not updated (so removed)
        $where = [
            $this->junction_field_main_id => $roles_id,
            $this->field_status           => self::STATUS_UNDER_UPDATE
        ];
        $this->db->delete($this->table_name, $where);
    }

}
