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
     */
    public function delete($id, $is_perm = NULL): bool {
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
     * @return db array
     */
    public function oneByUrl($url, $parent_id) {
        $where = array(
            'parent_id' => $parent_id,
            'url'       => $url
        );
        return $this->db->row($this->table_name, $where, 'pub_time desc');
    }

    /**
     * return one latest record by full_url (i.e. relative url from root, without domain)
     * @param string $full_url full url (without domain)
     * @return db array
     */
    public function oneByFullUrl($full_url) {
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

        #item now contains page data for the url
        if ($item) {
            if ($item["head_att_id"] > '') {
                $item["head_att_id_url"] = Att::i()->getUrlDirect($item["head_att_id"]);
            }
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
     * @return db array
     */
    public function listChildren($parent_id) {
        return $this->db->arr("SELECT * FROM $this->table_name WHERE status<>127 and parent_id=" . dbqi($parent_id) . " ORDER BY iname");
    }

    /**
     * Read ALL rows from db according to where, then apply getPagesTree to return tree structure
     * @param string $where where to apply in sql
     * @param string $orderby order by fields to apply in sql
     * @return array            parsepage array with hierarcy (via "children" key)
     */
    public function tree($where, $orderby) {
        $rows       = $this->db->arr("SELECT * FROM $this->table_name WHERE $where ORDER BY $orderby");
        $pages_tree = $this->getPagesTree($rows, 0);
        return $pages_tree;
    }

    /**
     * return parsepage array list of rows with hierarcy (children rows added to parents as "children" key)
     * @param array $rows rows from tree(), not muted, by ref just to save memory
     * @param integer $parent_id parent id to search children for
     * @param integer $level
     * @param string $parent_full_url parent full url, call with '' for top, used to build 'full_url' for each page recursively
     * @return array                parsepage array (children rows added to parents as "children" key)
     * RECURSIVE!
     */
    public function getPagesTree(&$rows, $parent_id, $level = 0, $parent_full_url = '') {
        $result = array();

        foreach ($rows as $row) {
            if ($parent_id == $row["parent_id"]) {
                $row2             = $row; #clone
                $row2['full_url'] = $parent_full_url . (substr($parent_full_url, -1) == '/' ? '' : '/') . $row2['url'];
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
     * @param integer $level optional, used in recursive calls
     * @return array                parsepage array with "leveler" array added to each row with level>0
     * RECURSIVE!
     */
    public function getPagesTreeList(&$pages_tree, $level = 0) {
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
     * @param integer $level optional, used in recursive calls
     * @return string               HTML with options
     * RECURSIVE!
     */
    public function getPagesTreeSelectHtml($selected_id, &$pages_tree, $level = 0) {
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
    public function getFullUrl($id) {
        if (!$id) {
            return '';
        }

        $item = $this->one($id);
        return $this->getFullUrl($item["parent_id"]) . "/" . $item["url"];
    }

    #return correct url - TODO
    public function getUrl($id, $icode, $url = null) {
        if ($url > '') {
            if (preg_match("!^/!", $url)) {
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

    public function str2icode($str) {
        $str = Trim($str);
        $str = preg_replace("/[^\w ]/g", " ", $str);
        $str = preg_replace("/ +/g", "-", $str);
        return $str;
    }

    /**
     * render page by full url
     * @param string $full_url full page url (without domain)
     * @return none, parser called with output to browser
     */
    public function showPageByFullUrl($full_url) {
        $ps = array();

        #for navigation
        $pages_tree  = $this->tree('status=0', "parent_id, prio desc, iname"); #published only
        $ps['pages'] = $this->getPagesTreeList($pages_tree, 0);

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
