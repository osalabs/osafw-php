<?php
/*Manage controller for Developers

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2019 Oleg Savchuk www.osalabs.com
 */

class DevManageController extends FwController {
    const access_level = 100;
    public $base_url = '/Dev/Manage';

    public function IndexAction() {
        $ps = array(
        );

        #table list
        $tables = $this->db->tables();
        sort($tables);

        $ps["select_tables"] = array();
        foreach ($tables as $table) {
            $ps["select_tables"][]=array(
                'id'    => $table,
                'iname' => $table,
            );
        }

        #models list - all clasess inherited from FwModel
        $ps["select_models"] = array();
        foreach ($this->_models() as $model_name) {
            $ps["select_models"][]=array(
                'id'    => $model_name,
                'iname' => $model_name,
            );
        }

        $ps["select_controllers"] = array();
        foreach ($this->_controllers() as $controller_name) {
            $ps["select_controllers"][]=array(
                'id'    => $controller_name,
                'iname' => $controller_name,
            );
        }

        return $ps;
    }

    public function ResetCacheAction(){
        $this->fw->flash("error", "Not applicable in PHP framework. Yet.");
        fw::redirect($this->base_url);
    }

    public function DumpLogAction(){
        $seek = reqi("seek");
        $logpath = $this->fw->config->site_error_log;
        rw("Dump of last ".$seek." bytes of the site log");

        $flog = fopen($logpath, "r");
        fseek($flog, -$seek);
        rw("<pre>");
        fpassthru($flog);
        rw("</pre>");

        rw("end of dump");
        fclose($flog);
    }

    public function CreateModelAction(){
        $item = reqh("item");
        $table_name = trim($item["table_name"]);
        $model_name = trim($item["model_name"]);
        if (!$table_name || !$model_name || in_array($model_name, $this->_models())) throw new ApplicationException("No table name or no model name or model exists");

        #copy DemoDicts.vb to model_name.vb
        $path = $this->fw->config->SITE_ROOT."/php/models";

        #replace: DemoDicts => ModelName, demo_dicts => table_name
        $replacements=array(
            "DemoDicts" => $model_name,
            "demo_dicts" => $table_name,
        );
        if (!$this->_replaceInFile($path."/DemoDicts.php", $replacements, $path.'/'.$model_name.'.php')) throw new ApplicationException("Can't open DemoDicts.php");

        $this->fw->flash("success", $model_name.".php model created");
        fw::redirect($this->base_url);
    }

    public function CreateControllerAction(){
        $item = reqh("item");
        $model_name = Trim($item["model_name"]);
        $controller_url = Trim($item["controller_url"]);
        $controller_name = str_replace("/", "",$controller_url);
        $controller_title = Trim($item["controller_title"]);

        if (!$model_name || !$controller_url || !$controller_title) throw new ApplicationException("No model or no controller name or no title");
        if (in_array($controller_name,$this->_controllers())) throw new ApplicationException("Such controller already exists");

        #copy DemoDicts.php to $model_name.php
        $path = $this->fw->config->SITE_ROOT."/php/controllers";

        #replace: DemoDicts => ModelName, demo_dicts => table_name
        $replacements=array(
            "AdminDemosDynamic" => $controller_name,
            "/Admin/DemosDynamic" => $controller_url,
            "DemoDicts" => $model_name,
            "Demos" => $model_name,
        );
        if (!$this->_replaceInFile($path."/AdminDemosDynamic.php", $replacements, $path."/".$controller_name.".php")) throw new ApplicationException("Can't open AdminDemosDynamic.php");

        #copy templates from /admin/demosdynamic to /controller/url
        $tpl_from = $this->fw->config->SITE_TEMPLATES."/admin/demosdynamic";
        $tpl_to = $this->fw->config->SITE_TEMPLATES.strtolower($controller_url);
        $this->_copyDirectory($tpl_from, $tpl_to);

        #replace in templates: DemoDynamic to Title
        #replace in url.html /Admin/DemosDynamic to $controller_url
        $replacements=array(
            "/Admin/DemosDynamic"   =>   $controller_url,
            "DemoDynamic"           =>   $controller_title,
        );
        $this->_replaceInFiles($tpl_to, $replacements);

        #update config.json:
        # save_fields - all fields from model table (except id and sytem add_time/user fields)
        # save_fields_checkboxes - empty (TODO based on bit field?)
        # list_view - $model->table_name
        # view_list_defaults - iname add_time status
        # view_list_map
        # view_list_custom - just status
        # show_fields - all
        # show_form_fields - all, analyse if:
        #   field NOT NULL and no default - required
        #   field has foreign key - add that table as dropdown
        $config_file = $tpl_to."/config.json";
        $config = Utils::jsonDecode(file_get_contents($config_file));
        if (!$config) $config=array();
        logger("LOADED:", $config);

        $model = fw::model($model_name);
        $fields = $this->db->table_schema($model->table_name);
        logger("DB $fields", $fields);
        $hfields = array();
        $sys_fields = Utils::qh("add_time add_users_id upd_time upd_users_id");

        $saveFields = array();
        $hFieldsMap = array();
        $showFields = array();
        $showFormFields = array();
        $saveFieldsNullable = array();
        $fld = array();
        foreach ($fields as &$fld) {
            #logger("check field=", $fld["name"]);
            $hfields[$fld["name"]] = $fld;
            $hFieldsMap[$fld["name"]] = $fld["name"];

            $sf = array();
            $sff = array();
            $is_skip = false;
            $sf["field"] = $fld["name"];
            $sf["label"] = $fld["name"];
            $sf["type"] = "plaintext";

            $sff["field"] = $fld["name"];
            $sff["label"] = $fld["name"];

            if ($fld["is_nullable"] = "1") $saveFieldsNullable[]=$fld["name"];
            if ($fld["is_nullable"] = "0" && !$fld["default"]) $sff["required"] = true; #if not nullable and no default - required

            if ($fld["maxlen"]>0) $sff["maxlength"] = intval($fld["maxlen"]);
            if ($fld["internal_type"] == "varchar") {
                if ($fld["maxlen"]==-1 || $fld["type"]=='text') { #large text
                    $sf["type"] = "markdown";
                    $sff["type"] = "textarea";
                    $sff["rows"] = 5;
                    $sff["class_control"] = "markdown autoresize"; #or fw-html-editor or fw-html-editor-short
                } else {
                    $sff["type"] = "input";
                }

            } elseif ($fld["internal_type"] == "int") {
                if (substr($fld["name"], -3) == "_id" && $fld["name"] != "dict_link_auto_id") { #TODO remove dict_link_auto_id
                    #TODO better detect if field has foreign key
                    #if link to other table - make type=select
                    $mname = $this->_tablename2model(substr($fld["name"], 0, strlen($fld["name"]) - 3));
                    if ($mname == "Parent") $mname = $model_name;

                    $sf["lookup_model"] = $mname;
                    #$sf["lookup_field"] = "iname"

                    $sff["type"] = "select";
                    $sff["lookup_model"] = $mname;
                    $sff["is_option0"] = true;
                    $sff["class_contents"] = "col-md-3";

                } elseif ($fld["type"] == "tinyint") {
                    #make it as yes/no radio
                    $sff["type"] = "yesno";
                    $sff["is_inline"] = true;
                } else {
                    $sff["type"] = "number";
                    $sff["min"] = 0;
                    $sff["max"] = 999999;
                    $sff["class_contents"] = "col-md-3";
                }
            } elseif ($fld["internal_type"] == "float") {
                $sff["type"] = "number";
                $sff["step"] = 0.1;
                $sff["class_contents"] = "col-md-3";

            } elseif ($fld["internal_type"] == "datetime") {
                $sf["type"] = "date";
                $sff["type"] = "date_popup";
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
                    $sf["type"] = "att";
                    $sf["label"] = "Attachment";
                    $sf["class_contents"] = "col-md-2";
                    unset($sff["lookup_model"]);

                    $sff["type"] = "att_edit";
                    $sff["label"] = "Attachment";
                    $sff["class_contents"] = "col-md-3";
                    $sff["att_category"] = "general";
                    unset($sff["class_contents"]);
                    unset($sff["lookup_model"]);
                    unset($sff["is_option0"]);
                    break;

                case "status":
                    $sf["label"] = "Status";
                    $sf["lookup_tpl"] = "/common/sel/status.sel";

                    $sff["label"] = "Status";
                    $sff["type"] = "select";
                    $sff["lookup_tpl"] = "/common/sel/status.sel";
                    $sff["class_contents"] = "col-md-3";
                    break;

                case "add_time":
                    $sf["label"] = "Added on";
                    $sf["type"] = "added";

                    $sff["label"] = "Added on";
                    $sff["type"] = "added";
                    break;

                case "upd_time":
                    $sf["label"] = "Updated on";
                    $sf["type"] = "updated";

                    $sff["label"] = "Updated on";
                    $sff["type"] = "updated";
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
                $showFields[]=$sf;
                $showFormFields[]=$sff;
            }

            if ($fld["is_identity"] == "1" || in_array($fld["name"],$sys_fields)) continue;
            $saveFields[]=$fld["name"];
        }
        unset($fld);
        logger('$hfields:', $hfields);

        $config["model"] = $model_name;
        $config["save_fields"] = $saveFields; #save all non-system
        $config["save_fields_checkboxes"] = "";
        $config["save_fields_nullable"] = $saveFieldsNullable;
        $config["search_fields"] = "id".(array_key_exists('iname', $hfields) ? ' iname' : ''); #id iname
        $config["list_sortdef"] = (array_key_exists('iname', $hfields) ? 'iname asc' : 'id desc'); #either sort by iname or id
        unset($config["list_sortmap"]); #N/A in dynamic controller
        unset($config["required_fields"]); #not necessary in dynamic controller as controlled by showform_fields required attribute
        $config["related_field_name"] = ""; #TODO?
        $config["list_view"] = $model->table_name;
        $config["view_list_defaults"] = "id".(array_key_exists('iname', $hfields) ? ' iname' : '').(array_key_exists('add_time', $hfields) ? ' add_time' : '').(array_key_exists('status', $hfields) ? ' status' : '');
        $config["view_list_map"] = $hFieldsMap; #fields to names
        $config["view_list_custom"] = "status";
        $config["show_fields"] = $showFields;
        $config["showform_fields"] = $showFormFields;

        #remove all commented items - name start with "#"
        foreach (array_keys($config) as $key) {
            if (substr($key, 0, 1)=='#') unset($config[$key]);
        }
        logger("BEFORE SAVE:", $config);

        #Utils.jsonEncode(config) - can't use as it produces unformatted json string
        $config_str = json_encode($config, JSON_PRETTY_PRINT);
        file_put_contents($config_file, $config_str);

        $this->fw->flash("controller_created", $controller_name);
        $this->fw->flash("controller_url", $controller_url);
        fw::redirect($this->base_url);
    }

    public function ExtractController(){
        fw::redirect($this->base_url);
    }


    private function _models(){
        $result=array();
        $dir = $this->fw->config->SITE_ROOT.'/php/models';
        $files = scandir($dir);
        foreach ($files as $value) {
            if (!preg_match('/^(.+)\.php$/', $value, $m)) continue;
            $result[]=$m[1];
        }
        return $result;
    }

    private function _controllers(){
        $result=array();
        $dir = $this->fw->config->SITE_ROOT.'/php/controllers';
        $files = scandir($dir);
        foreach ($files as $value) {
            if (!preg_match('/^(.+)\.php$/', $value, $m)) continue;
            $result[]=$m[1];
        }
        return $result;
    }

    #replaces strings in all files under defined dir
    #RECURSIVE!
    private function _replaceInFiles($dir, $strings){
        $subdirs=array();
        foreach (scandir($dir) as $filename) {
            if (preg_match('/^\.\.?$/', $filename, $m)) continue;
            if (is_dir($dir.'/'.$filename)) {
                $subdirs[]=$filename;
            }else{
                $this->_replaceInFile($dir.'/'.$filename, $strings);
            }
        }

        #dive into dirs
        foreach ($subdirs as $foldername) {
            $this->_replaceInFiles($dir.'/'.$foldername, $strings);
        }
    }

    private function _replaceInFile($filepath, $strings, $saveas=''){
        #logger("REPLACE: $filepath => $saveas");
        $content = file_get_contents($filepath);
        if ($content===FALSE || !strlen($content)) return FALSE;

        $content = $this->_replaceStrings($content, $strings);

        if ($saveas) $filepath=$saveas;
        file_put_contents($filepath, $content);

        return TRUE;
    }

    private function _replaceStrings($content, $strings){
        foreach ($strings as $str => $value) {
            $content = str_replace($str,$value,$content);
        }
        return $content;
    }

    #copy all directory content to other path
    #RECURSIVE
    private function _copyDirectory($src,$dst){
        #logger("COPY DIR $src => $dst");
        $dir = opendir($src);
        @mkdir($dst);
        while(false !== ( $file=readdir($dir)) ) {
            if (( $file!='.' ) && ( $file!='..' )) {
                if ( is_dir($src.'/'.$file) ) {
                    $this->_copyDirectory($src.'/'.$file,$dst.'/'.$file);
                }else{
                    copy($src.'/'.$file,$dst.'/'.$file);
                }
            }
        }
        closedir($dir);
    }

    #'demo_dicts => DemoDicts
    private function _tablename2model($table_name){
        $result = '';
        $pieces = explode('_', $table_name);
        foreach ($pieces as $value) {
            $result .= ucfirst($value);
        }
        return $result;
    }

}

?>