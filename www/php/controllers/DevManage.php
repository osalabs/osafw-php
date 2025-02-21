<?php
/*Manage controller for Developers

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2025 Oleg Savchuk www.osalabs.com
 */

class DevManageController extends FwController {
    const int access_level = 100;
    public string $base_url = '/Dev/Manage';

    public function IndexAction(): ?array {
        $ps = array();

        #table list
        $tables = $this->db->tables();
        sort($tables);

        $ps["select_tables"] = array();
        foreach ($tables as $table) {
            $ps["select_tables"][] = array(
                'id'    => $table,
                'iname' => $table,
            );
        }

        #models list - all clasess inherited from FwModel
        $ps["select_models"] = array();
        foreach ($this->_models() as $model_name) {
            $ps["select_models"][] = array(
                'id'    => $model_name,
                'iname' => $model_name,
            );
        }

        $ps["select_controllers"] = array();
        foreach ($this->_controllers() as $controller_name) {
            $ps["select_controllers"][] = array(
                'id'    => $controller_name,
                'iname' => $controller_name,
            );
        }

        return $ps;
    }

    public function ResetCacheAction() {
        $this->fw->flash("error", "Not applicable in PHP framework. Yet.");
        fw::redirect($this->base_url);
    }

    public function DumpLogAction() {
        $seek    = reqi("seek");
        $logpath = $this->fw->config->site_error_log;
        rw("Dump of last " . $seek . " bytes of the site log");

        $fs = fopen($logpath, "r");
        fseek($fs, -$seek);
        rw("<pre>");
        fpassthru($fs);
        rw("</pre>");

        rw("end of dump");
        fclose($fs);
    }

    public function CreateModelAction() {
        $item       = reqh("item");
        $table_name = trim($item["table_name"]);
        $model_name = trim($item["model_name"]);
        if (!$table_name || !$model_name || in_array($model_name, $this->_models())) {
            throw new ApplicationException("No table name or no model name or model exists");
        }

        #copy DemoDicts.vb to model_name.vb
        $path = $this->fw->config->SITE_ROOT . "/php/models";

        #replace: DemoDicts => ModelName, demo_dicts => table_name
        $replacements = array(
            "DemoDicts"  => $model_name,
            "demo_dicts" => $table_name,
        );
        if (!$this->_replaceInFile($path . "/DemoDicts.php", $replacements, $path . '/' . $model_name . '.php')) {
            throw new ApplicationException("Can't open DemoDicts.php");
        }

        $this->fw->flash("success", $model_name . ".php model created");
        fw::redirect($this->base_url);
    }

    public function CreateControllerAction() {
        $item             = reqh("item");
        $model_name       = trim($item["model_name"]);
        $controller_url   = trim($item["controller_url"]);
        $controller_name  = str_replace("/", "", $controller_url);
        $controller_title = trim($item["controller_title"]);
        $controller_type  = trim($item["controller_type"]);

        if (!$model_name || !$controller_url || !$controller_title) {
            throw new ApplicationException("No model or no controller name or no title");
        }
        if (in_array($controller_name, $this->_controllers())) {
            throw new ApplicationException("Such controller already exists");
        }

        #copy DemoDicts.php to $model_name.php
        $path    = $this->fw->config->SITE_ROOT . "/php/controllers";
        $path_to = $path;

        if ($controller_type == "vue") {
            $demos_controller = "/AdminDemosVue.php";
            #copy templates from
            $tpl_from = $this->fw->config->SITE_TEMPLATES . "/admin/demosvue";

            $replacements_controller = array(
                "AdminDemosVue"   => $controller_name,
                "/Admin/DemosVue" => $controller_url,
                "Demo Vue"        => $controller_title,
                "DemoDicts"       => $model_name,
                "Demos"           => $model_name,
            );

            #replace in templates: DemoVue to Title
            #replace in url.html /Admin/DemosVue to $controller_url
            $replacements_templates = array(
                "/Admin/DemosVue" => $controller_url,
                "Demo Vue"        => $controller_title,
            );

        } elseif ($controller_type == "api") {
            $demos_controller = "/v1/v1DemosApi.php";

            $tpl_from = ""; // no templates for API

            $replacements_controller = array(
                "v1DemosApi"   => $controller_name,
                "/v1/demosapi" => $controller_url,
                "Demo Api"     => $controller_title,
                "DemoDicts"    => $model_name,
                "Demos"        => $model_name,
            );
            $replacements_templates  = [];

            $path_to = $path . "/v1";

        } else {
            $demos_controller = "/AdminDemosDynamic.php";
            #copy templates from
            $tpl_from = $this->fw->config->SITE_TEMPLATES . "/admin/demosdynamic";

            $replacements_controller = array(
                "AdminDemosDynamic"   => $controller_name,
                "/Admin/DemosDynamic" => $controller_url,
                "Demo Dynamic"        => $controller_title,
                "DemoDicts"           => $model_name,
                "Demos"               => $model_name,
            );

            #replace in templates: DemoDynamic to Title
            #replace in url.html /Admin/DemosDynamic to $controller_url
            $replacements_templates = array(
                "/Admin/DemosDynamic" => $controller_url,
                "Demo Dynamic"        => $controller_title,
            );
        }

        #replace: DemoDicts => ModelName, demo_dicts => table_name
        if (!$this->_replaceInFile($path . $demos_controller, $replacements_controller, $path_to . "/" . $controller_name . ".php")) {
            throw new ApplicationException("Can't open $demos_controller");
        }

        #copy templates from demos folder to /controller/url
        if ($tpl_from) {
            $tpl_to = $this->fw->config->SITE_TEMPLATES . strtolower($controller_url);
            $this->_copyDirectory($tpl_from, $tpl_to);
            $this->_replaceInFiles($tpl_to, $replacements_templates);

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
            $config_file = $tpl_to . "/config.json";
            $config      = Utils::jsonDecode(file_get_contents($config_file));
            if (!$config) {
                $config = array();
            }
            logger("LOADED:", $config);

            $entity = [
                'model_name' => $model_name,
                'controller' => [
                    'url'   => $controller_url,
                    'title' => $controller_title,
                    'type'  => $controller_type
                ],
                #'table' => fw::model($model_name)->table_name,
            ];
            $config = FwDev::init($this->fw, $this->db)->updateControllerConfig($entity, $config);

            #Utils.jsonEncode(config) - can't use as it produces unformatted json string
            $config_str = json_encode($config, JSON_PRETTY_PRINT);
            file_put_contents($config_file, $config_str);

        }

        $this->fw->flash("controller_created", $controller_name);
        $this->fw->flash("controller_url", $controller_url);
        fw::redirect($this->base_url);
    }

    public function ExtractController() {
        fw::redirect($this->base_url);
    }

    public function LibManAction() {
        $jsonPath = $this->fw->config->SITE_ROOT . '/php/libman.json';
        $rootPath = $this->fw->config->SITE_ROOT;

        rw("started libman install");
        rw("jsonPath: $jsonPath");
        rw("rootPath: $rootPath");

        $libman = new PhpLibMan($jsonPath, $rootPath);
        $libman->install();

        rw("done");
    }

    private function _models() {
        $result = array();
        $dir    = $this->fw->config->SITE_ROOT . '/php/models';
        $files  = scandir($dir);
        foreach ($files as $value) {
            if (!preg_match('/^(.+)\.php$/', $value, $m)) {
                continue;
            }
            $result[] = $m[1];
        }
        return $result;
    }

    private function _controllers() {
        $result = array();
        $dir    = $this->fw->config->SITE_ROOT . '/php/controllers';
        $files  = scandir($dir);
        foreach ($files as $value) {
            if (!preg_match('/^(.+)\.php$/', $value, $m)) {
                continue;
            }
            $result[] = $m[1];
        }
        return $result;
    }

    #replaces strings in all files under defined dir
    #RECURSIVE!
    private function _replaceInFiles($dir, $strings) {
        $subdirs = array();
        foreach (scandir($dir) as $filename) {
            if (preg_match('/^\.\.?$/', $filename, $m)) {
                continue;
            }
            if (is_dir($dir . '/' . $filename)) {
                $subdirs[] = $filename;
            } else {
                $this->_replaceInFile($dir . '/' . $filename, $strings);
            }
        }

        #dive into dirs
        foreach ($subdirs as $foldername) {
            $this->_replaceInFiles($dir . '/' . $foldername, $strings);
        }
    }

    private function _replaceInFile($filepath, $strings, $saveas = '') {
        #logger("REPLACE: $filepath => $saveas");
        $content = file_get_contents($filepath);
        if ($content === FALSE || !strlen($content)) {
            return FALSE;
        }

        $content = $this->_replaceStrings($content, $strings);

        if ($saveas) {
            $filepath = $saveas;
        }
        file_put_contents($filepath, $content);

        return TRUE;
    }

    private function _replaceStrings($content, $strings) {
        foreach ($strings as $str => $value) {
            $content = str_replace($str, $value, $content);
        }
        return $content;
    }

    #copy all directory content to other path
    #RECURSIVE
    private function _copyDirectory($src, $dst) {
        #logger("COPY DIR $src => $dst");
        $dir = opendir($src);
        @mkdir($dst);
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    $this->_copyDirectory($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }

    #'demo_dicts => DemoDicts
    private function _tablename2model($table_name) {
        $result = '';
        $pieces = explode('_', $table_name);
        foreach ($pieces as $value) {
            $result .= ucfirst($value);
        }
        return $result;
    }

}
