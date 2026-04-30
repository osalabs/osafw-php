<?php
/*
Static Pages model class
*/

class Spages extends FwModel {

    public function __construct() {
        parent::__construct();

        $this->table_name = 'spages';
    }

    /**
     * delete record, but don't allow to delete home page
     * @param int $id page id
     * @param bool $is_perm if true - permanently delete from db
     * @return bool
     * @throws DBException
     */
    public function delete(int $id, bool $is_perm = false): bool {
        $item = $this->one($id);
        # home page cannot be deleted
        if ($item['is_home'] != 1) {
            parent::delete($id, $is_perm);
            return true;
        }
        return false;
    }

    /**
     * return one latest record by url (i.e. with most recent pub_time if there are more than one page with such url)
     * @param string $url url of the page (without parent url path)
     * @param int $parent_id parent page id to search within
     * @return array array
     * @throws DBException
     */
    public function oneByUrl(string $url, int $parent_id): array {
        $where = array(
            'parent_id' => $parent_id,
            'url'       => $url
        );
        return $this->db->row($this->table_name, $where, 'pub_time desc');
    }

    /**
     * return one latest record by full_url (i.e. relative url from root, without domain)
     * @param string $full_url full url (without domain)
     * @return array
     * @throws NoModelException|DBException
     */
    public function oneByFullUrl(string $full_url): array {
        $url_parts = explode('/', $full_url);
        $parent_id = 0;
        $item      = array();
        foreach ($url_parts as $i => $url_part) {
            if (!$i) {
                continue;
            } #skip first

            $item = $this->oneByUrl($url_part, $parent_id);
            if (!$item) {
                break;
            }
            $parent_id = $item['id'];
        }

        if (!$item) {
            return [];
        }

        #item now contains page data for the url
        if (!empty($item["head_att_id"])) {
            $item["head_att_id_url"] = Att::i()->getUrlDirect($item["head_att_id"]);
        }

        #page[top_url] used in templates navigation
        if (count($url_parts) >= 2) {
            $item["top_url"] = strtolower($url_parts[1]);
        }

        #columns
        if ($item["idesc_left"] > "") {
            if ($item["idesc_right"] > "") {
                $item["is_col3"] = true;
            } else {
                $item["is_col2_left"] = true;
            }
        } else {
            if ($item["idesc_right"] > "") {
                $item["is_col2_right"] = true;
            } else {
                $item["is_col1"] = true;
            }
        }

        return $item;
    }

    /**
     * list of children pages
     * @param int $parent_id parent id
     * @return array array
     * @throws DBException
     */
    public function listChildren(int $parent_id): array {
        return $this->db->arr($this->table_name, [
            'status'    => $this->db->opNOT(self::STATUS_DELETED),
            'parent_id' => $parent_id
        ], 'iname');
    }

    /**
     * Read ALL rows from db according to where, then apply getPagesTree to return tree structure
     * @param string $where where to apply in sql
     * @param array $params params to apply in sql
     * @param string $orderby order by fields to apply in sql
     * @return array            parsepage array with hierarcy (via "children" key)
     * @throws DBException
     */
    public function tree(string $where, array $params, string $orderby): array {
        $rows = $this->db->arrp("SELECT * FROM $this->table_name WHERE $where ORDER BY $orderby", $params);
        return $this->getPagesTree($rows, 0);
    }

    /**
     * return parsepage array list of rows with hierarcy (children rows added to parents as "children" key)
     * @param array $rows rows from tree(), not muted, by ref just to save memory
     * @param int $parent_id parent id to search children for
     * @param int $level
     * @param string $parent_full_url parent full url, call with '' for top, used to build 'full_url' for each page recursively
     * @return array                parsepage array (children rows added to parents as "children" key)
     * RECURSIVE!
     */
    public function getPagesTree(array &$rows, int $parent_id, int $level = 0, string $parent_full_url = ''): array {
        $result = array();

        foreach ($rows as $row) {
            if ($parent_id == $row["parent_id"]) {
                $row2             = $row; #clone
                $row2['full_url'] = $parent_full_url . (str_ends_with($parent_full_url, '/') ? '' : '/') . $row2['url'];
                $row2["_level"]   = $level;
                #$row2["_level1"] = level + 1 'to easier use in templates
                $row2["children"] = $this->getPagesTree($rows, $row['id'], $level + 1, $row2['full_url']);
                $result[]         = $row2;
            }
        }

        return $result;
    }

    /**
     * Generate parsepage array of plain list with levelers based on tree structure from getPagesTree()
     * @param array $pages_tree result of getPagesTree(), not muted
     * @param int $level optional, used in recursive calls
     * @return array                parsepage array with "leveler" array added to each row with level>0
     * RECURSIVE!
     */
    public function getPagesTreeList(array $pages_tree, int $level = 0): array {
        $result = array();

        if ($pages_tree) {
            foreach ($pages_tree as $row) {
                #add leveler
                if ($level > 0) {
                    $leveler = array();
                    for ($i = 1; $i <= $level; $i++) {
                        $leveler[] = array();
                    }
                    $row["leveler"] = $leveler;
                }
                $result[] = $row;
                #subpages
                $result = array_merge($result, $this->getPagesTreeList($row["children"], $level + 1));
            }
        }

        return $result;
    }

    /**
     * Generate HTML with options for select with indents for hierarcy
     * @param string $selected_id selected id
     * @param array   &$pages_tree result of getPagesTree()
     * @param int $level optional, used in recursive calls
     * @return string               HTML with options
     * RECURSIVE!
     */
    public function getPagesTreeSelectHtml(string $selected_id, array $pages_tree, int $level = 0): string {
        $result = array();
        if ($pages_tree) {
            foreach ($pages_tree as $row) {
                $result[] = "<option value=\"" . $row["id"] . "\"" . ($row["id"] == $selected_id ? " selected=\selected\" " : "") . ">" . str_repeat("&#8212; ", $level) . $row["iname"] . "</option>\n";
                #subpages
                $result[] = $this->getPagesTreeSelectHtml($selected_id, $row["children"], $level + 1);
            }
        }

        return implode('', $result);
    }

    /**
     * Return full url (without domain) for the page item, including url of the page
     * @param int $id page id
     * @return string           URL like /page/subpage/subsubpage
     * RECURSIVE!
     */
    public function getFullUrl(int $id): string {
        if (!$id) {
            return '';
        }

        $item = $this->one($id);
        return $this->getFullUrl($item["parent_id"]) . "/" . $item["url"];
    }

    #return correct url - TODO
    public function getUrl(int $id, string $icode, string $url = null): string {
        if ($url > '') {
            if (str_starts_with($url, "/")) {
                $url = $this->fw->config->ROOT_URL . $url;
            }
            return $url;
        } else {
            $icode = $this->str2icode($icode);
            if ($icode) {
                return $this->fw->ROOT_URL . "/Pages/" . $icode;
            } else {
                return $this->fw->ROOT_URL . "/Pages/" . $id;
            }
        }
    }

    public function str2icode(string $str): string {
        $str = trim($str);
        $str = preg_replace("/[^\w ]/", " ", $str);
        $str = preg_replace("/ +/", "-", $str);
        return $str;
    }

    /**
     * render page by full url
     * @param string $full_url full page url (without domain)
     * @return void, parser called with output to browser
     * @throws DBException
     * @throws NoModelException
     */
    public function showPageByFullUrl(string $full_url): void {
        $ps = array();

        #for navigation
        $pages_tree  = $this->tree('status=0', [], "parent_id, prio desc, iname"); #published only
        $ps['pages'] = $this->getPagesTreeList($pages_tree);

        $item = $this->oneByFullUrl($full_url);
        if (!$item || $item['status'] == 127) { #don't show deleted too
            $ps["hide_std_sidebar"] = true;
            $this->fw->parser("/error/404", $ps);
            return;
        }

        $ps["page"]             = $item;
        $ps["hide_std_sidebar"] = true; #TODO - control via item[template]
        $this->fw->parser("/home/spage", $ps);
    }

}
