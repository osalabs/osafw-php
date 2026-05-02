<?php
/*
 Virtual Vue Fw Controller class for standard module with list/form screens

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2025 Oleg Savchuk www.osalabs.com
*/

class FwVirtualController extends FwVueController {
    const int access_level = Users::ACL_SITE_ADMIN;

    public string $template_basedir = '/common/virtual';
    protected int $virtual_access_level = Users::ACL_SITE_ADMIN;

    public function __construct(array $fwcontroller) {
        parent::__construct(); #this will not load config.json as base_url is not yet set

        $this->virtual_access_level = intval($fwcontroller['access_level'] ?? Users::ACL_SITE_ADMIN);
        $this->base_url             = $fwcontroller['url'];
        $controller_basedir         = strtolower($this->base_url);
        // if /index subdir of such directory exists - use it, otherwise /common/virtual will be used
        // we check for /index subdir because we want to be able to have config.json for configs override without all templates
        if (is_dir($this->fw->config->SITE_TEMPLATES . $controller_basedir . '/index')) {
            $this->template_basedir = $controller_basedir;
        }

        //use cached config or create config on the fly
        $config = json_decode($fwcontroller['config'] ?? '', true);
        if (!$config) {
            $entity = [
                'model_name' => $fwcontroller['model'],
                'controller' => [
                    'url'                   => $fwcontroller['url'],
                    'title'                 => $fwcontroller['iname'],
                    'is_dynamic_index_edit' => Users::i()->isAccessLevel($fwcontroller['access_level_edit']),
                ]
            ];
            $config = FwDev::init($this->fw, $this->fw->db)->updateControllerConfig($entity, []);
            #logger("virtual config:", $config);
        }

        // now merge with hardcoded config.json in templates (if any, file has a higher priority)
        // first check controller basedir, then /common/virtual
        $is_conf_found = false;
        $conf_file     = $this->fw->config->SITE_TEMPLATES . $controller_basedir . '/config.json';
        if (file_exists($conf_file)) {
            $is_conf_found = true;
            $file_config   = json_decode(file_get_contents($conf_file), true);
            if (!$file_config) {
                logger("WARN", "Error decoding config from $conf_file");
            } else {
                logger("TRACE", "merging config from:", $file_config);
                $config = $this->mergeVirtualConfig($config, $file_config);
            }
        }

        if (!$is_conf_found) {
            # no controller-specific config, then check /common/virtual/config.json
            $conf_file = $this->fw->config->SITE_TEMPLATES . '/common/virtual/config.json';
            if (file_exists($conf_file)) {
                $file_config = json_decode(file_get_contents($conf_file), true);
                if (!$file_config) {
                    logger("WARN", "Error decoding config from $conf_file");
                } else {
                    logger("TRACE", "merging config from:", $file_config);
                    $config = $this->mergeVirtualConfig($config, $file_config);
                }
            }
        }

        $this->loadControllerConfig($config);
    }

    protected function mergeVirtualConfig(array $config, array $file_config): array {
        $result = array_replace_recursive($config, $file_config);

        // Layout-driven field arrays use numeric keys, so recursive merge mixes generated
        // defs with file overrides by index. Replace them wholesale when provided.
        foreach (['show_fields', 'showform_fields'] as $replace_key) {
            if (array_key_exists($replace_key, $file_config)) {
                $result[$replace_key] = $file_config[$replace_key];
            }
        }

        return $result;
    }

    public function checkAccess(): void {
        $current_user_level = $this->fw->userAccessLevel();
        if ($current_user_level < $this->virtual_access_level) {
            throw new AuthException("Access Denied (3)");
        }

        if ($current_user_level >= Users::ACL_VISITOR && $current_user_level < Users::ACL_SITE_ADMIN) {
            $action_more = $this->fw->route->action_more;
            if (
                ($this->fw->route->action === FW::ACTION_SAVE || $this->fw->route->action === FW::ACTION_SHOW_FORM)
                && !$this->fw->route->id
            ) {
                $action_more = FW::ACTION_MORE_NEW;
            }

            if (!Users::i()->isAccessByRolesResourceAction(
                $this->fw->userId(),
                $this->fw->route->controller,
                $this->fw->route->action,
                $action_more,
                $this->access_actions_to_permissions
            )) {
                throw new AuthException("Bad access - Not authorized (3)");
            }
        }
    }

}
