<?php

class MainController extends FwController {
    const access_level = 0; #logged only
    const route_default_action = 'index';

    public function IndexAction() {
        $ps = array();
        $panes = array();

        $one = array();
        $one["type"] = "bignum";
        $one["title"] = "Pages";
        $one["url"] = "/Admin/Spages";
        $one["value"] = $this->fw->model('Spages')->getCount();
        $panes['plate1'] = $one;

        $one = array();
        $one["type"] = "bignum";
        $one["title"] = "Uploads";
        $one["url"] = "/Admin/Att";
        $one["value"] = $this->fw->model('Att')->getCount();
        $panes['plate2'] = $one;

        $one = array();
        $one["type"] = "bignum";
        $one["title"] = "Users";
        $one["url"] = "/Admin/Users";
        $one["value"] = $this->fw->model('Users')->getCount();
        $panes['plate3'] = $one;

        $one = array();
        $one["type"] = "bignum";
        $one["title"] = "Demo items";
        $one["url"] = "/Admin/DemosDynamic";
        $one["value"] = $this->fw->model('Demos')->getCount();
        $panes['plate4'] = $one;

        $one = array();
        $one["type"] = "barchart";
        $one["title"] = "Logins per day";
        $one["id"] = "logins_per_day";
        #$one["url"] = "/Admin/Reports/sample";
        $one["rows"] = $this->db->arr("select CAST(el.add_time as date) as idate, CONCAT(MONTH(el.add_time),'/',DAY(el.add_time)) as ilabel, count(*) as ivalue
            from fwevents ev, fwevents_log el
            where ev.icode='login' and el.events_id=ev.id
            group by CAST(el.add_time as date), CONCAT(MONTH(el.add_time),'/',DAY(el.add_time))
            order by CAST(el.add_time as date) desc
            LIMIT 14");
        $panes['barchart'] = $one;

        $one = array();
        $one["type"] = "piechart";
        $one["title"] = "Users by Type";
        $one["id"] = "user_types";
        #$one["url"] = "/Admin/Reports/sample";
        $one["rows"] = $this->db->arr("select access_level, count(*) as ivalue from users group by access_level order by count(*) desc");
        foreach ($one["rows"] as $key => $row) {
            $one["rows"][$key]['ilabel'] = get_selvalue('/common/sel/access_level.sel', $row['access_level']);
        }
        $panes['piechart'] = $one;

        $one = array();
        $one["type"] = "table";
        $one["title"] = "Last Events";
        #$one["url"] = "/Admin/Reports/sample";
        $one["rows"] = $this->db->arr("select el.add_time as `On`, ev.iname as `Event` from fwevents ev, fwevents_log el where el.events_id=ev.id order by el.id desc LIMIT 10");
        $one["headers"] = array();
        if ($one["rows"]){
            $fields = array_keys($one["rows"][0]);
            foreach ($fields as $fld) {
                $one["headers"][]=array(
                    'field_name' => $fld,
                );
            }
            foreach ($one["rows"] as $key => $row) {
                $cols = array();
                foreach ($fields as $fld) {
                    $cols[] = array(
                        'row' => $row,
                        'field_name' => $fld,
                        'data' => $row[$fld],
                    );
                }
                $one["rows"][$key]['cols'] = $cols;
            }
        }
        $panes['tabledata'] = $one;

        $one = array();
        $one["type"] = "linechart";
        $one["title"] = "Events per day";
        $one["id"] = "eventsctr";
        #$one["url"] = "/Admin/Reports/sample";
        $one["rows"] = $this->db->arr("select CAST(el.add_time as date) as idate, CONCAT(MONTH(el.add_time),'/',DAY(el.add_time)) as ilabel, count(*) as ivalue
            from fwevents ev, fwevents_log el
            where el.events_id=ev.id
            group by CAST(el.add_time as date), CONCAT(MONTH(el.add_time),'/',DAY(el.add_time))
            order by CAST(el.add_time as date) desc
            LIMIT 14");
        $panes['linechart'] = $one;

        $ps['panes'] = $panes;
        return $ps;
    }

}

?>