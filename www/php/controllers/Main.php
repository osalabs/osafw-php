<?php
/*
Main Dashboard Controller

Part of PHP osa framework  www.osalabs.com/osafw/php
(c) 2009-2024 Oleg Savchuk www.osalabs.com
*/

class MainController extends FwController {
    const access_level         = Users::ACL_USER; #logged only
    const route_default_action = 'index';

    public function IndexAction() {
        $ps    = array();
        $panes = array();

        $one             = array();
        $one["type"]     = "bignum";
        $one["title"]    = "Pages";
        $one["url"]      = "/Admin/Spages";
        $one["value"]    = Spages::i()->getCount();
        $panes['plate1'] = $one;

        $one             = array();
        $one["type"]     = "bignum";
        $one["title"]    = "Uploads";
        $one["url"]      = "/Admin/Att";
        $one["value"]    = Att::i()->getCount();
        $panes['plate2'] = $one;

        $one             = array();
        $one["type"]     = "bignum";
        $one["title"]    = "Users";
        $one["url"]      = "/Admin/Users";
        $one["value"]    = Users::i()->getCount();
        $panes['plate3'] = $one;

        $one             = array();
        $one["type"]     = "bignum";
        $one["title"]    = "Demo items";
        $one["url"]      = "/Admin/DemosDynamic";
        $one["value"]    = Demos::i()->getCount();
        $panes['plate4'] = $one;

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

}
