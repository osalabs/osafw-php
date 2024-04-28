<?php
/*
Main Dashboard Controller

Part of PHP osa framework  www.osalabs.com/osafw/php
(c) 2009-2024 Oleg Savchuk www.osalabs.com
*/

class MainController extends FwController {
    const int    access_level         = Users::ACL_USER; #logged only
    const string route_default_action = 'index';

    public function __construct() {
        parent::__construct();
        $this->base_url = '/Main';
    }

    public function checkAccess(): void {
        // add custom actions to permissions mapping
        // Uncomment if using RBAC permissions
        //        $this->access_actions_to_permissions = [
        //            "UITheme" => Permissions::PERMISSION_LIST,
        //            "UIMode"  => Permissions::PERMISSION_LIST,
        //        ];
        parent::checkAccess();
    }

    public function IndexAction(): ?array {
        $ps    = array();
        $panes = array();

        $DIFF_DAYS = -7;
        $STATUSES  = [FwModel::STATUS_ACTIVE];

        $one                = array();
        $one["type"]        = "bignum";
        $one["title"]       = "Pages";
        $one["url"]         = "/Admin/Spages";
        $one["value"]       = Spages::i()->getCount($STATUSES);
        $one["value_class"] = "text-warning";
        $one["badge_value"] = Utils::percentChange(Spages::i()->getCount($STATUSES, $DIFF_DAYS), Spages::i()->getCount($STATUSES, $DIFF_DAYS * 2));
        $one["badge_class"] = "text-bg-warning";
        $one["icon"]        = "pages";
        $panes['plate1']    = $one;

        $one                = array();
        $one["type"]        = "bignum";
        $one["title"]       = "Uploads";
        $one["url"]         = "/Admin/Att";
        $one["value"]       = Att::i()->getCount($STATUSES);
        $one["value_class"] = "text-info";
        $one["badge_value"] = Utils::percentChange(Att::i()->getCount($STATUSES, $DIFF_DAYS), Att::i()->getCount($STATUSES, $DIFF_DAYS * 2));
        $one["badge_class"] = "text-bg-info";
        $one["icon"]        = "uploads";
        $panes['plate2']    = $one;

        $one                = array();
        $one["type"]        = "bignum";
        $one["title"]       = "Users";
        $one["url"]         = "/Admin/Users";
        $one["value"]       = Users::i()->getCount($STATUSES);
        $one["value_class"] = "text-success";
        $one["badge_value"] = Utils::percentChange(Users::i()->getCount($STATUSES, $DIFF_DAYS), Users::i()->getCount($STATUSES, $DIFF_DAYS * 2));
        $one["badge_class"] = "text-bg-success";
        $one["icon"]        = "users";
        $panes['plate3']    = $one;

        $one                = array();
        $one["type"]        = "bignum";
        $one["title"]       = "Events";
        $one["url"]         = "/Admin/Reports/sample";
        $one["value"]       = FwActivityLogs::i()->getCountByLogIType(FwLogTypes::ITYPE_SYSTEM, $STATUSES);
        $one["value_class"] = "";
        $one["badge_value"] = Utils::percentChange(FwActivityLogs::i()->getCountByLogIType(FwLogTypes::ITYPE_SYSTEM, $STATUSES, $DIFF_DAYS), FwActivityLogs::i()->getCountByLogIType(FwLogTypes::ITYPE_SYSTEM, $STATUSES, $DIFF_DAYS * 2));
        $one["badge_class"] = "text-bg-secondary";
        $one["icon"]        = "events";
        $panes['plate4']    = $one;

        $one               = array();
        $one["type"]       = "barchart";
        $one["title"]      = "Logins per day";
        $one["id"]         = "logins_per_day";
        $one["rows"]       = $this->db->arrp("select 
                CAST(al.add_time as date) as idate
                , CONCAT(MONTH(al.add_time),'/',DAY(al.add_time)) as ilabel
                , count(*) as ivalue
            from activity_logs al, log_types lt
            where lt.icode='login' and al.log_types_id=lt.id
            group by CAST(al.add_time as date), CONCAT(MONTH(al.add_time),'/',DAY(al.add_time))
            order by CAST(al.add_time as date) desc
            LIMIT 14");
        $panes['barchart'] = $one;

        $one          = array();
        $one["type"]  = "piechart";
        $one["title"] = "Users by Type";
        $one["id"]    = "user_types";
        #$one["url"] = "/Admin/Reports/sample";
        $one["rows"] = $this->db->arrp("select access_level, count(*) as ivalue from users group by access_level order by count(*) desc");
        foreach ($one["rows"] as $key => $row) {
            $one["rows"][$key]['ilabel'] = get_selvalue('/common/sel/access_level.sel', $row['access_level']);
        }
        $panes['piechart'] = $one;

        $one          = array();
        $one["type"]  = "table";
        $one["title"] = "Last Events";
        #$one["url"] = "/Admin/Reports/sample";
        $one["rows"]    = $this->db->arrp("select 
            al.add_time as `On`
            , lt.iname as `Event`
            , u.fname as `User` 
            from activity_logs al, log_types lt, users u 
            where al.log_types_id=lt.id and al.add_users_id=u.id 
            order by al.id desc 
            LIMIT 10");
        $one["headers"] = array();
        if ($one["rows"]) {
            $fields = array_keys($one["rows"][0]);
            foreach ($fields as $fld) {
                $one["headers"][] = array(
                    'field_name' => $fld,
                );
            }
            foreach ($one["rows"] as $key => $row) {
                $cols = array();
                foreach ($fields as $fld) {
                    $cols[] = array(
                        'row'        => $row,
                        'field_name' => $fld,
                        'data'       => $row[$fld],
                    );
                }
                $one["rows"][$key]['cols'] = $cols;
            }
        }
        $panes['tabledata'] = $one;

        $one          = array();
        $one["type"]  = "linechart";
        $one["title"] = "Events per day";
        $one["id"]    = "eventsctr";
        #$one["url"] = "/Admin/Reports/sample";
        $one["rows"]        = $this->db->arrp("select CAST(al.add_time as date) as idate, CONCAT(MONTH(al.add_time),'/',DAY(al.add_time)) as ilabel, count(*) as ivalue
            from activity_logs al, log_types lt
            where al.log_types_id=lt.id
            group by CAST(al.add_time as date), CONCAT(MONTH(al.add_time),'/',DAY(al.add_time))
            order by CAST(al.add_time as date) desc
            LIMIT 14");
        $panes['linechart'] = $one;

        $ps['panes'] = $panes;
        return $ps;
    }

    public function UIThemeAction(int $form_id): void {
        session_start();
        $_SESSION["ui_theme"] = $form_id;
        $fields               = ["ui_theme" => $form_id];
        if ($form_id == "30") {
            $fields["ui_mode"]   = "10"; #for blue theme - enforce light color mode
            $_SESSION["ui_mode"] = "10";
        }
        session_write_close();

        Users::i()->update($this->fw->userId(), $fields);

        $this->fw->redirect($this->base_url);
    }

    public function UIModeAction(int $form_id): void {
        session_start();
        $_SESSION["ui_mode"] = $form_id;
        session_write_close();
        Users::i()->update($this->fw->userId(), ["ui_mode" => $form_id]);

        $this->fw->redirect($this->base_url);
    }
}
