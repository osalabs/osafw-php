<?php
/*
Sample report class

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2024 Oleg Savchuk www.osalabs.com
*/

class ReportSample extends Reports {

    public function __construct() {
        parent::__construct();
    }

    public function getReportFilters() {
        $this->f = array_merge($this->f, array(
            #add there data for custom filters
            #'select_users' => Users::i()->listSelectOptions(),
            'is_dates' => $this->f['from_date'] || $this->f['to_date'] ? 1 : 0
        ));
        return $this->f;
    }

    public function getReportData($value = '') {
        $ps = array();

        $list_orderby = 'al.id desc'; #TODO setListSorting

        #apply filters from Me.f
        $where        = ' ';
        $where_params = [];
        if ($this->f['from_date']) {
            $where                     .= ' and al.add_time>=@from_date';
            $where_params['from_date'] = DateUtils::Str2SQL($this->f['from_date']);
        }
        if ($this->f['to_date']) {
            $where                   .= ' and al.add_time<DATE_ADD(@to_date, INTERVAL 1 DAY)'; #+1 because less than equal
            $where_params['to_date'] = DateUtils::Str2SQL($this->f['to_date']);
        }

        #define query
        #REMEMBER to filter out deleted items for each table, i.e. add call andNotDeleted([alias])
        $qactivity_logs = FwActivityLogs::i()->qTable();
        $qlog_types     = FwLogTypes::i()->qTable();
        $qfwentities    = FwEntities::i()->qTable();
        $qusers         = Users::i()->qTable();
        $sql            = "select al.*
                , lt.iname as event_name
                , et.iname as entity_name
                , u.fname
                , u.lname
                from $qactivity_logs al
                     INNER JOIN $qlog_types lt ON (lt.id=al.log_types_id)
                     INNER JOIN $qfwentities et ON (et.id=al.fwentities_id)
                     LEFT OUTER JOIN $qusers u ON (u.id=al.add_users_id " . $this->andNotDeleted("u.") . ")
                where 1=1
                $where
                order by $list_orderby
                LIMIT 50";

        $ps['rows']  = $this->db->arrp($sql, $where_params);
        $ps['count'] = count($ps['rows']);

        #perform calculations and add additional info for each result row
        /*
                foreach ($ps['rows'] as &$row) {
                    $row['event'] = $evmodel->one($row['events_id']);
                }
                unset($row);
                $ps["total_ctr"] = $this->_calcPerc($ps["rows"], 'ctr', 'perc'); #if you need calculate "perc" for each row based on each $row["ctr"]
        */
        return $ps;
        #hint: use <~rep[rows]> and <~f[from_date]> in /admin/reports/sample/report_html.html
    }
}
