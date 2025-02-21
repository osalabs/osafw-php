<?php
/*
 Code generation for Developers

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2025 Oleg Savchuk www.osalabs.com
*/

class FwDev {

    private readonly FW $fw;
    private readonly DB $db;

    /**
     * table name to model name
     *  demo_dicts => DemoDicts
     *  TODO actually go thru models and find model with table_name
     * @param string $table_name
     * @return string
     */
    public static function tablenameToModel(string $table_name): string {
        $result = '';
        $pieces = explode('_', $table_name);
        foreach ($pieces as $value) {
            $result .= ucfirst($value);
        }
        return $result;
    }

    public static function init(FW $fw, DB $db): self {
        return new self($fw, $db);
    }

    public function __construct(FW $fw, DB $db) {
        $this->fw = $fw;
        $this->db = $db;
    }

    /**
     * create or update controller's config
     * @param array $entity must contain "model_name"
     * @param array $config
     * @return array
     * @throws NoModelException
     */
    public function updateControllerConfig(array $entity, array $config): array {
        $model_name        = $entity["model_name"];
        $entity_controller = $entity["controller"] ?? [];
        $controller_title  = $entity_controller['title'] ?? Utils::name2human($model_name);

        $model  = fw::model($model_name);
        $fields = $model->schema();
        #logger("DB fields", $fields);
        $hfields    = array();
        $sys_fields = Utils::qh("add_time add_users_id upd_time upd_users_id");

        $saveFields         = array();
        $saveFieldsNullable = array(); #TODO
        $hFieldsMap         = array();
        $showFields         = array();
        $showFormFields     = array();
        foreach ($fields as &$fld) {
            #logger("check field=", $fld["name"]);
            $human_name = Utils::name2human($fld["name"]);

            $hfields[$fld["name"]]    = $fld;
            $hFieldsMap[$fld["name"]] = $human_name;

            $sf          = array();
            $sff         = array();
            $is_skip     = false;
            $sf["field"] = $fld["name"];
            $sf["label"] = $human_name;
            $sf["type"]  = "plaintext";

            $sff["field"] = $fld["name"];
            $sff["label"] = $human_name;

            if ($fld["is_nullable"] = "0" && !$fld["default"]) {
                $sff["required"] = true;
            } #if not nullable and no default - required

            if ($fld["maxlen"] > 0) {
                $sff["maxlength"] = intval($fld["maxlen"]);
            }
            if ($fld["fw_type"] == "varchar") {
                if ($fld["maxlen"] == -1 || $fld["type"] == 'text') { #large text
                    $sf["type"]           = "markdown";
                    $sff["type"]          = "textarea";
                    $sff["rows"]          = 5;
                    $sff["class_control"] = "markdown autoresize"; #or fw-html-editor or fw-html-editor-short
                } else {
                    $sff["type"] = "input";
                }

            } elseif ($fld["fw_type"] == "int") {
                if (str_ends_with($fld["name"], "_id") && $fld["name"] != "dict_link_auto_id") { #TODO remove dict_link_auto_id
                    #TODO better detect if field has foreign key
                    #if link to other table - make type=select
                    $mname = self::tablenameToModel(substr($fld["name"], 0, strlen($fld["name"]) - 3));
                    if ($mname == "Parent") {
                        $mname = $model_name;
                    }

                    $sf["lookup_model"] = $mname;
                    #$sf["lookup_field"] = "iname"

                    $sff["type"]           = "select";
                    $sff["lookup_model"]   = $mname;
                    $sff["is_option0"]     = true;
                    $sff["class_contents"] = "col-md-3";

                } elseif ($fld["type"] == "tinyint" && str_starts_with($fld["name"], "is")) {
                    #make it as yes/no radio if tinyint and starts with "is"
                    $sff["type"]      = "yesno";
                    $sff["is_inline"] = true;
                } else {
                    $sff["type"]           = "number";
                    $sff["min"]            = 0;
                    $sff["max"]            = 999999;
                    $sff["class_contents"] = "col-md-3";
                }
            } elseif ($fld["fw_type"] == "float") {
                $sff["type"] = "number";
                //$sff["step"]           = 0.1;
                $sff["class_contents"] = "col-md-3";

            } elseif ($fld["fw_type"] == "datetime") {
                $sf["type"]            = "date";
                $sff["type"]           = "date_popup";
                $sff["class_contents"] = "col-md-3";
                #TODO distinguish between date and date with time
            } else {
                #everything else - just input
                $sff["type"] = "input";
            }

            if ($fld["is_identity"] == "1") {
                $sff["type"] = "group_id";
                unset($sff["required"]);
            }

            #special fields
            switch ($fld["name"]) {
                case "iname":
                    $sff["validate"] = "exists"; #unique field
                    break;

                case "att_id": #Single attachment field - TODO better detect on foreign key to "att" table
                    $sf["type"]           = "att";
                    $sf["label"]          = "Attachment";
                    $sf["class_contents"] = "col-md-2";
                    unset($sff["lookup_model"]);

                    $sff["type"]           = "att_edit";
                    $sff["label"]          = "Attachment";
                    $sff["class_contents"] = "col-md-3";
                    $sff["att_category"]   = "general";
                    unset($sff["class_contents"]);
                    unset($sff["lookup_model"]);
                    unset($sff["is_option0"]);
                    break;

                case "status":
                    $sf["label"]      = "Status";
                    $sf["lookup_tpl"] = "/common/sel/status.sel";

                    $sff["label"]          = "Status";
                    $sff["type"]           = "select";
                    $sff["lookup_tpl"]     = "/common/sel/status.sel";
                    $sff["class_contents"] = "col-md-3";
                    break;

                case "add_time":
                    $sf["label"] = "Added on";
                    $sf["type"]  = "added";

                    $sff["label"] = "Added on";
                    $sff["type"]  = "added";
                    break;

                case "upd_time":
                    $sf["label"] = "Updated on";
                    $sf["type"]  = "updated";

                    $sff["label"] = "Updated on";
                    $sff["type"]  = "updated";
                    break;

                case "add_users_id":
                case "upd_users_id":
                    $is_skip = true;
                    break;

                default:
                    #nothing else
                    break;
            }

            if (!$is_skip) {
                $showFields[]     = $sf;
                $showFormFields[] = $sff;
            }

            if ($fld["is_identity"] == "1" || in_array($fld["name"], $sys_fields)) {
                continue;
            }
            $saveFields[] = $fld["name"];
        }
        unset($fld);
        #logger('$hfields:', $hfields);

        // view_list_defaults - if exists: id, iname, add_time, status, then first several fields (so total up to 5), which not already added to $view_list_defaults
        $hview_list_defaults = array();
        foreach (Utils::qw("id iname add_time status") as $fld) {
            if (array_key_exists($fld, $hfields)) {
                $hview_list_defaults[$fld] = true;
            }
        }
        $i = 5 - count($hview_list_defaults);
        foreach ($hfields as $fld_name => $fld) {
            if ($i <= 0) {
                break;
            }
            if (!array_key_exists($fld_name, $hview_list_defaults)) {
                $hview_list_defaults[$fld_name] = true;
                $i--;
            }
        }

        // list_sortdef - iname asc or id desc or just first field if other not exists
        if (array_key_exists('id', $hfields)) {
            $list_sortdef = "id desc";
        } elseif (array_key_exists('iname', $hfields)) {
            $list_sortdef = "iname asc";
        } else {
            $list_sortdef = array_key_first($hfields) . " asc";
        }

        // search_fields - id, iname if exists, or empty string
        $search_fields = "";
        if (array_key_exists('id', $hfields)) {
            $search_fields = "!id";
        }
        if (array_key_exists('iname', $hfields)) {
            $search_fields .= ($search_fields ? " " : "") . "iname";
        }

        # finalize config
        $config["model"]                  = $model_name;
        $config["is_dynamic_index"]       = true;
        $config["save_fields"]            = $saveFields; #save all non-system
        $config["save_fields_checkboxes"] = "";
        $config["save_fields_nullable"]   = $saveFieldsNullable;
        $config["search_fields"]          = $search_fields;
        $config["list_sortdef"]           = $list_sortdef;
        unset($config["list_sortmap"]); #N/A in dynamic controller
        unset($config["required_fields"]); #not necessary in dynamic controller as controlled by showform_fields required attribute
        $config["related_field_name"] = ""; #TODO?
        $config["list_view"]          = $model->table_name;
        $config["view_list_defaults"] = Utils::qwRevert(array_keys($hview_list_defaults));
        $config["view_list_map"]      = $hFieldsMap; #fields to names
        $config["view_list_custom"]   = "status";
        $config["show_fields"]        = $showFields;
        $config["showform_fields"]    = $showFormFields;

        #enable dynamic index edit
        $config["is_dynamic_index_edit"] = $entity_controller['is_dynamic_index_edit'] ?? true;

        #titles
        $config["list_title"]    = $controller_title;
        $config["view_title"]    = 'View ' . $controller_title . ' Record';
        $config["edit_title"]    = 'Edit ' . $controller_title . ' Record';
        $config["add_new_title"] = 'Add New ' . $controller_title . ' Record';

        #remove all commented items - name start with "#"
        foreach (array_keys($config) as $key) {
            if (str_starts_with($key, '#')) {
                unset($config[$key]);
            }
        }
        #logger("updated controller config :", $config);

        return $config;
    }
}
