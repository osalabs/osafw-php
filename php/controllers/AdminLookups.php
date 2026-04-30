<?php
/*
Lookups Manager Controller

Part of PHP osa framework  www.osalabs.com/osafw/php
(c) 2009-2025 Oleg Savchuk www.osalabs.com
*/

class AdminLookupsController extends FwController {
    public string $base_url = '/Admin/Lookups';
    public string $model_name = 'FwControllers';

    public function __construct() {
        parent::__construct();
    }

    public function IndexAction(): ?array {
        $ps   = [];
        $rows = $this->model->listGrouped(); #ordered by igroup (group name), iname, already filtered by access_level

        $cols = []; #will contain array of arrays with "list_groups" keys, which contains array of arrays with "list_rows" keys, which contains $row from $rows
        # one group must be in one column (no split groups between columns)
        # and we need to spread groups between 4 columns in a way so each column has relatively equal number of rows
        # so one column can have more than one group
        # each list_groups array should have "igroup" and "list_rows" keys
        $columns = 4;

        # 1) Group rows by igroup
        $grouped = [];
        foreach ($rows as $row) {
            $grouped[$row['igroup']][] = $row;
        }

        # 2) Build an array of group-objects: [ 'igroup' => ..., 'list_rows' => [...] ]
        $allGroups = [];
        foreach ($grouped as $gName => $gRows) {
            $allGroups[] = [
                'igroup'    => $gName,
                'list_rows' => $gRows
            ];
        }

        # Prepare empty columns
        for ($i = 0; $i < $columns; $i++) {
            $cols[$i] = [
                'col_sm'      => (int)(12 / $columns),  # for Bootstrap's col-sm-x
                'list_groups' => [],
            ];
        }

        # Track how many rows are currently assigned to each column
        $colRowCounts = array_fill(0, $columns, 0);

        # 3) Distribute each group to the column with the smallest row count so far
        foreach ($allGroups as $group) {
            $minColIndex                         = array_search(min($colRowCounts), $colRowCounts);
            $cols[$minColIndex]['list_groups'][] = $group;
            # increment row-count by the size of this group
            $colRowCounts[$minColIndex] += count($group['list_rows']);
        }

        $ps = [
            "list_cols" => $cols,
        ];
        return $ps;
    }

}//end of class
