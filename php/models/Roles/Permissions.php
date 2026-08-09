<?php
/*
 Permissions model class

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2024 Oleg Savchuk www.osalabs.com
*/

class Permissions extends FwModel {

    public const string PERMISSION_LIST             = "list";
    public const string PERMISSION_VIEW             = "view";
    public const string PERMISSION_ADD              = "add";
    public const string PERMISSION_EDIT             = "edit";
    public const string PERMISSION_DELETE           = "del";
    public const string PERMISSION_DELETE_PERMANENT = "del_perm";

    protected const array MAP_ACTIONS_PERMISSIONS = [
        FW::ACTION_INDEX           => self::PERMISSION_LIST,
        FW::ACTION_SHOW            => self::PERMISSION_VIEW,
        FW::ACTION_SHOW_FORM . '/' . FW::ACTION_MORE_NEW, self::PERMISSION_ADD, //to distinguish between add and edit
        FW::ACTION_SHOW_FORM . '/' . FW::ACTION_MORE_EDIT, self::PERMISSION_EDIT, // necessary for show edit form
        FW::ACTION_SHOW_FORM       => self::PERMISSION_EDIT,
        FW::ACTION_SAVE            => self::PERMISSION_EDIT,
        FW::ACTION_SAVE_MULTI      => self::PERMISSION_EDIT,
        FW::ACTION_SHOW_DELETE     => self::PERMISSION_DELETE,
        FW::ACTION_SHOW_DELETE . '/' . FW::ACTION_MORE_EDIT, self::PERMISSION_DELETE, // necessary for show delete form
        FW::ACTION_DELETE          => self::PERMISSION_DELETE,
        //FW::ACTION_DELETE => self::PERMISSION_DELETE_PERMANENT //TODO distinguish permanent delete
        FW::ACTION_DELETE_RESTORE  => self::PERMISSION_DELETE, //if can delete permanently - can restore too
        FW::ACTION_NEXT            => self::PERMISSION_VIEW, // next/prev links for view
        FW::ACTION_AUTOCOMPLETE    => self::PERMISSION_LIST, // autocomplete - same as list
        FW::ACTION_USER_VIEWS      => self::PERMISSION_VIEW, // if can view - can work with views too
        FW::ACTION_SAVE_USER_VIEWS => self::PERMISSION_VIEW, // if can view - can save views too
        FW::ACTION_SAVE_SORT       => self::PERMISSION_EDIT //can edit - can sort too
    ];

    public function __construct() {
        parent::__construct();

        $this->table_name = 'permissions';
        $this->field_prio = 'prio';
    }

    /**
     * map fw actions to permissions
     * @param string $action
     * @param string $action_more
     * @return string
     */
    public function mapActionToPermission(string $action, string $action_more = ""): string {
        if (!empty($action_more)) {
            //if action_more is set - use it to find more granular permission
            //new, edit, delete
            $action = $action . "/" . $action_more;
        }

        //find standard mapping
        $permission = (string)self::MAP_ACTIONS_PERMISSIONS[$action];
        if (!empty($permission)) {
            return $permission;
        }

        //if no standard permission found - return action as permission (custom permission)
        return $action;
    }
}
