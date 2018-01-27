<?php
/*Configuration Controller for Developers
    - performs basic testing of configuration
    - asks user for config values like db and update config

    WARNING: delete this file before posting to production environment.

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2017 Oleg Savchuk www.osalabs.com
 */

class DevConfigureController extends FwController {
    const access_level = -1; #unlogged
    const route_default_action = 'index';

    public function IndexAction() {
        global $conf_server_name;
        $ps = array(
            'hide_sidebar' => true,
        );

        $ps['config_file_name'] = "/php/config.$conf_server_name.php";

        $ps['is_db_config']=false;
        if ($this->fw->config->DB['DBNAME'] && $this->fw->config->DB['USER']) $ps['is_db_config']=true;

        $ps['is_db_conn']=false;
        if ($ps['is_db_config']){
            try {
                $db = DB::i();
                @$db->connect();
                $ps['is_db_conn']=true;
            } catch (Exception $e) {
                $ps['db_conn_err']=$e->getMessage();
            }
        }

        $ps['is_db_tables']=false;
        if ($ps['is_db_conn']){
            try {
                $value=$db->value("select count(*) from fwevents_log"); #checking last table in a script as first tables might be filled
                $ps['is_db_tables']=true;
            } catch (Exception $e) {
                $ps['db_tables_err']=$e->getMessage();
            }
        }

        $ps['is_write_dirs']=false;
        if (is_writable($this->fw->config->PUBLIC_UPLOAD_DIR)) $ps['is_write_dirs']=true;

        $ps['is_error_log']=false;
        if (is_writable($this->fw->config->site_error_log)) $ps['is_error_log']=true;
        $ps['error_log_size']=Utils::bytes2str(filesize($this->fw->config->site_error_log));

        return $ps;
    }

}

?>