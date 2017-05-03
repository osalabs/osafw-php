<?php
/*
 Site DB class/SQL functions - simplified access to site database
 convenient wrapper for mysqli

 Part of PHP osa framework  www.osalabs.com/osafw/php
 (c) 2009-2015 Oleg Savchuk www.osalabs.com
*/

/**
 * Procedural shortcuts (uses DB singleton)
 *
 * Usage samples:
 *
 * #get one field value
 * $users_ctr = db_value('select count(*) from users');
 *
 * #get one field value with condition
 * $where = array(
 *     'id' => 1,
 * );
 * $email = db_value('user', $where, 'email');
 *
 * #get count of rows value with condition
 * $where = array(
 *     'status' => 0,
 * );
 * $active_users_ctr = db_value('user', $where, 'count(*)');
 *
 * #get one table row
 * $id=1;
 * $row = db_row("users", array('id' => $id));
 * $row = db_row("select * from users where id=".dbqi($id));
 *
 * #get one table row with condition and order by
 * $row = db_row("users", array('status' => 0), 'id desc');
 *
 * #get all table records
 * $rows = db_array('select * from users');
 * $rows = db_array('users', array());
 *
 * #get all table records with condition
 * $status=0;
 * $rows = db_array('select * from users where status='.dbqi($status));
 * $rows = db_array('users', array('status' => 0));
 * $rows = db_array('select * from users where email='.dbq('test@test.com'));
 * $rows = db_array('users', array('email' => 'test@test.com'));
 *
 * #get all table records with condition, order by 'id desc' and limit 2
 * $rows = db_array("users", array('status' => 0), 'id desc', 2);
 *
 * #get first column all values
 * $ids = db_col("select id from users");
 *
 * #get named column all values with condition and order
 * $emails = db_col("users", array('status' => 0), 'email', 'id desc');
 *
 * #raw select query execution
 * $res = db_query("select * from users");
 * $rows = $res->fetch_all();
 * $res->free();
 *
 * #raw non-select query execution
 * db_exec("update users set status=0");
 *
 * #insert a row
 * $vars=array(
 *     'nick'   => 'Jon',
 *     'email'  => 'john@test.com',
 * );
 * $new_id=db_insert('users', $vars);
 *
 * #replace a row (by primary or unique key)
 * $vars=array(
 *     'nick'   => 'John',
 *     'email'  => 'john@test.com',
 * );
 * $new_id=db_insert('users', $vars, array('replace'=>true));
 *
 * #insert with options - ignore and no_identity
 * $vars=array(
 *     'users_id'   => 1,
 *     'options_id'  => 23,
 * );
 * db_insert('users_options', $vars, array('ignore'=>true, 'no_identity'=>true));
 *
 * #multi-rows insert
 * $vars=array(
 *     array(
 *         'nick'   => 'John',
 *         'email'  => 'john@test.com',
 *     ),
 *     array(
 *         'nick'   => 'Bill',
 *         'email'  => 'bill@test.com',
 *     ),
 *     array(
 *         'nick'   => 'Robert',
 *         'email'  => 'robert@test.com',
 *     ),
 * );
 * db_insert('users', $vars);
 *
 * #update record by primary key
 * $id=1;
 * $vars=array(
 *     'nick'   => 'Jon',
 *     'email'  => 'john@test.com',
 * );
 * db_update('users', $vars, $id);
 *
 * #update record by column key
 * $email=1;
 * $vars=array(
 *     'nick'   => 'Jon',
 *     'upd_time'   => '~!now()', #will set upd_time=now()
 * );
 * db_update('users', $vars, $email, 'email');
 *
 * #update record by column key with additonal set/where
 * $email='john@test.com';
 * $vars=array(
 *     'nick'   => 'Jon',
 * );
 * db_update('users', $vars, $email, 'email', ', upd_time=now()', ' and status=0');
 *
 * #update record by where
 * $vars=array(
 *     'nick'   => 'John',
 *     'upd_time'   => '~!now()', #will set upd_time=now()
 * );
 * $where=array(
 *     'email'  => 'john@test.com',
 *     'status' => 0,
 * )
 * db_update('users', $vars, $where);
 *
 * #check if record exists for particular field/value
 * $is_exists=db_is_record_exists('users', 'john@test.com', 'email');
 *
 * #check if record exists for particular field/value and NOT with other id
 * $is_exists=db_is_record_exists('users', 'john@test.com', 'email', 1);  #will check: where email='john@test.com' and id<>1
 *
 * #check if record exists for particular field/value and NOT with other field/value
 * $is_exists=db_is_record_exists('users', 'john@test.com', 'email', 'John', 'nick');  #will check: where email='john@test.com' and nick<>'John'
 *
*/

//

/**
 * explicitly quote variable as integer
 * @param  string $value value to be quoted
 * @return int        integer value
 */
function dbqi($value){
    return intval($value);
}

/**
 * explicitly quote variable. If $field_type not defined and $value is '~!NULL' or '~!now()' - pass as NULL or now() accoridngly
 * @param  string  $value      value to be quoted
 * @param  string  $field_type 's'(string, default if empty), 'i'(int), 'x'(no quote)
 * @return string, integer or 'NULL' string (if $field_type is not defined and $value is null)
 */
function dbq($value, $field_type=null){
 return DB::i()->quote($value, $field_type);
}

function dbq_ident($value){
 return DB::i()->quote_ident($value);
}

/**
 * return one value (0 column or named column) from $sql or table/where/orderby
 * syntax 1: (raw sql)
 * @param  string $sql              sql query
 * @param  string $field_name       optional, field name to return. If ommited - 0 column fetched
 * syntax 2: (table/params)
 * @param  string $table_name       table name to read from
 * @param  string $where            array of (field => value) where conditions
 * @param  string $field_name       optional, field name to fetch and return. If not set - first field returned. Special case - "count(*)", will return count
 * @param  string $order_by         optional, order string to be added to ORDER BY
 *
 * @return string or null           return value from the field
 */
function db_value($sql_or_table, $field_or_where=NULL, $field_name=NULL, $order_by=NULL){
    return DB::i()->value($sql_or_table, $field_or_where, $field_name, $order_by);
}

/**
 * return one row from $sql or table/where/orderby
 * syntax 1: (raw sql)
 * @param  string $sql              sql query
 * syntax 2: (table/params)
 * @param  string $table_name       table name to read from
 * @param  string $where            array of (field => value) where conditions
 * @param  string $order_by         optional, order string to be added to ORDER BY
 *
 * @return array                    assoc array (has keys as field names and values as field values)
 */
function db_row($sql_or_table, $where=NULL, $order_by=NULL){
    return DB::i()->row($sql_or_table, $where, $order_by);
}

/**
 * return one table record by primary key
 * shortcut for db_row($table, array('id'=> $id));
 * @param  string $table table name
 * @param  int $id       primary key id
 * @return array         assoc array
 */
function db_obj($table, $id){
    return DB::i()->obj($table, $id);
}

/**
 * return one column of values (0 column or named column) from $sql or table/where/orderby
 * syntax 1: (raw sql)
 * @param  string $sql              sql query
 * @param  string $field_name       optional, field name to return. If ommited - 0 column fetched
 * syntax 2: (table/params)
 * @param  string $table_name       table name to read from
 * @param  string $where            array of (field => value) where conditions
 * @param  string $field_name       field name to return
 * @param  string $order_by         optional, order string to be added to ORDER BY
 *
 * @return array                    array of values from the column, empty array if no rows fetched
 */
function db_col($sql_or_table, $field_or_where=NULL, $field_name=NULL, $order_by=NULL){
    return DB::i()->col($sql_or_table, $field_or_where, $field_name, $order_by);
}

/**
 * return one value (0 column or named column) from $sql or table/where/orderby/limit
 * syntax 1: (raw sql)
 * @param  string $sql              sql query
 * syntax 2: (table/params)
 * @param  string $table_name       table name to read from
 * @param  string $where            array of (field => value) where conditions
 * @param  string $order_by         optional, order string to be added to ORDER BY
 * @param  string $limit            optional, limit string to be added to LIMIT
 *
 * @return array                    array of arrays (outer array has numerical keys and values as one fetched row; inner arrays has keys as field names and values as field values)
 */
function db_array($sql_or_table, $where=NULL, $order_by=NULL, $limit=NULL){
    return DB::i()->arr($sql_or_table, $where, $order_by, $limit);
}

/**
 * perform query and return result statement. Throws an exception if error occured.
 * @param  string $sql    SQL query
 * @param  array  $params optional, array of params for prepared queries
 * @return mysqli_result  object
 */
function db_query($sql, $params=NULL){
    return DB::i()->query($sql, $params);
}

/**
 * exectute query without returning result set. Throws an exception if error occured.
 * @param  string $sql    SQL query
 * @param  array  $params optional, array of params for prepared queries
 * @return nothing
 */
function db_exec($sql, $params=NULL){
    DB::i()->exec($sql, $params);
}

/**
 * get last inserted id
 * @return int  last inserted id or 0
 */
function db_identity(){
    return DB::i()->get_identity();
}

//******************** helpers for INSERT/UPDATE/DELETE

/**
 * delete record(s) from db
 * @param  string $table      table name to delete from
 * @param  string $value      id value
 * @param  string $column     optional, column name for value, default = 'id'
 * @param  string|array $more_where additonal where for delete
 * @return nothing
 */
function db_delete($table, $value, $column = 'id', $more_where=''){
    DB::i()->delete($table, $value, $column, $more_where);
}

/**
 * insert or replace record into db
 * @param  string $table    table name
 * @param  array $vars      assoc array of fields/values to insert
 * @param  array $options   optional, options: ignore, replace, no_identity
 * @return int              last insert id or null (if no_identity option provided)
 */
function db_insert($table, $vars, $options=array()){
    return DB::i()->insert($table, $vars, $options);
}

/**
 * update record in db
 * syntax 1: (update by one key field with more options)
 * @param  string $table    table name
 * @param  array $vars      assoc array of fields/values to update
 * @param  array $options   optional, options: ignore, replace, no_identity
 * syntax 2: (update by where)
 * @param  string $table    table name
 * @param  array $vars      assoc array of fields/values to update
 * @param  string $where            array of (field => value) where conditions
 * *
 * @return int              last insert id or null (if no_identity option provided)
 */
function db_update($table, $vars, $key_id, $column = 'id', $more_set='', $more_where=''){
    DB::i()->update($table, $vars, $key_id, $column, $more_set, $more_where);
}


/**
 * return true if record exists or false if not. Optionally exclude check for other column/value
 * @param  string $table_name   table name
 * @param  string $uniq_value   value to check
 * @param  string $column       optional, column name for uniq_value
 * @param  string $not_id       optional, not id to check
 * @param  string $not_id_column optional, not id column name
 * @return bool                 true if record exists or false if not
 */
function db_is_record_exists($table_name, $uniq_value, $column, $not_id=NULL, $not_id_column='id') {
    return DB::i()->is_record_exists($table_name, $uniq_value, $column, $not_id, $not_id_column);
}


/**
* DB class
*
* TODO - full OO sample
*/
class DB {
    public static $SQL_QUERY_CTR = 0; //counter for SQL queries in request
    public static $instance;

    public $dbh;                    //mysqli object
    public $config = array();       //should contain: DBNAME, USER, PWD, HOST, PORT, [SQL_SERVER], IS_LOG
                                    //if IS_LOG - external function logger() will be called for logging

    function __construct($config=NULL){
        global $CONFIG;
        if (is_null($config)){
            $this->config = $CONFIG['DB']; //use site config, if config not passed explicitly
        }
    }

    # return singleton instance
    public static function i(){
        if (!DB::$instance){
            DB::$instance = new DB();
        }
        return DB::$instance;
    }

    /**
     * connect to sql server using config passed in constructor. Also prepares connection params (MySQL: utf, sql mode). Throw an exception if connection error occurs.
     * @return nothing
     */
    public function connect(){
        $this->dbh=new mysqli($this->config['HOST'], $this->config['USER'], $this->config['PWD'], $this->config['DBNAME'], ( $this->config['PORT']>'' ? (int)$this->config['PORT'] : NULL ) );
        if ($this->dbh->connect_error){
            $msg='Cannot connect to the database because: ('.$this->dbh->connect_errno.') '.$this->dbh->connect_error;
            $this->logger('FATAL', $msg);
            throw new Exception($msg);
        }

        $res = $this->dbh->set_charset("utf8");
        $this->handle_error($res);

        #above is preffered way $this->query("SET NAMES utf8");
        $this->query("SET SESSION sql_mode = 'NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION'"); #required fw to work on MySQL 5.5+
    }

    /**
     * check connection and reconnect if necessary
     * @return nothing
     */
    public function check_connect(){
        if (is_null($this->dbh) || !$this->dbh->ping()){
            $this->connect();
        }
    }

    /**
     * close connection to sql server
     * @return nothing
     */
    public function disconnect(){
        $this->dbh->close();
        $this->dbh = null;
    }

    /**
     * check if statement or result is FALSE and throw Exception
     * @param  mixed $check     statement or result to check
     * @return nothing, logger an error and throws an exception
     */
    public function handle_error($checkvar){
        if ($checkvar===FALSE){
            $err_str = 'Error in DB operation: ('.$this->dbh->errno.') '.$this->dbh->error;
            $this->logger('ERROR', $err_str);
            throw new Exception($err_str);
        }
    }

    /**
     * perform query and return result statement. Throws an exception if error occured.
     * @param  string $sql    SQL query
     * @param  array  $params optional, array of params for prepared queries
     * @return mysqli_result  object
     */
    public function query($sql, $params=NULL){
        $this->check_connect();

        DB::$SQL_QUERY_CTR++;

        if (is_array($params) && count($params)){
            //use prepared query
            $this->logger('INFO', $sql);
            $this->logger('INFO', $params);

            $st = $this->dbh->prepare($sql);
            $this->handle_error($st);

            $query_types = str_repeat("s", count($params)); #just bind all params as strings, TODO - support of passing types
            $query_params = array($query_types);
            foreach ($params as $k => $v) {
                $query_params[] = &$params[$k];
            }
            call_user_func_array(array($st,'bind_param'), $query_params);

            $res = $st->execute();
            $this->handle_error($res);

            $meta = $st->result_metadata();
            if ($meta===FALSE){
                #this is non-select query, no need to get_result
            }else{
                $meta->free();
                $result = $st->get_result();
            }

            $st->close();
        }else{
            //use direct query
            $this->logger('INFO', $sql);

            $result = $this->dbh->query($sql);
            #no need to check for metadata here as query returns TRUE for non-select
            $this->handle_error($result);
        }

        return $result;
    }

    /**
     * exectute query without returning result set. Throws an exception if error occured.
     * @param  string $sql    SQL query
     * @param  array  $params optional, array of params for prepared queries
     * @return nothing
     */
    public function exec($sql, $params=NULL){
        $this->query($sql, $params);
    }

    /**
     * return one row from $sql or table/where/orderby
     * syntax 1: (raw sql)
     * @param  string $sql              sql query
     * syntax 2: (table/params)
     * @param  string $table_name       table name to read from
     * @param  string $where            array of (field => value) where conditions
     * @param  string $order_by         optional, order string to be added to ORDER BY
     *
     * @return array or FALSE           assoc array (has keys as field names and values as field values) or FALSE if no rows returned
     */
    public function row($sql_or_table, $where=NULL, $order_by=NULL){
        $rows = $this->arr($sql_or_table, $where, $order_by, 1);
        if (count($rows)){
            $result=$rows[0];
        }else{
            $result=FALSE;
        }
        return $result;
    }

    /**
     * return one table record by primary key
     * shortcut for row($table, array('id'=> $id));
     * @param  string $table table name
     * @param  int $id       primary key id
     * @return array         assoc array
     */
    public function obj($table, $id){
        return $this->row($table, array('id'=> $id));
    }

    /**
     * return one value (0 column or named column) from $sql or table/where/orderby
     * syntax 1: (raw sql)
     * @param  string $sql              sql query
     * syntax 2: (table/params)
     * @param  string $table_name       table name to read from
     * @param  string $where            array of (field => value) where conditions
     * @param  string $order_by         optional, order string to be added to ORDER BY
     * @param  string $limit            optional, limit string to be added to LIMIT
     *
     * @return array                    array of assoc arrays (outer array has numerical keys and values as inner array; inner arrays has keys as field names and values)
     */
    public function arr($sql_or_table, $where=NULL, $order_by=NULL, $limit=NULL){
        $result = array();
        //detect syntax
        if (is_array($where)){
            //syntax 2
            list($sql, $params) = $this->build_sql_params($sql_or_table, '*', $where, $order_by, $limit);
            $res = $this->query($sql, $params);
        }else{
            //syntax 1
            $res = $this->query($sql_or_table);
        }
        /* workaround if fetch_all not available
         while ($row = $res->fetch_assoc()) {
          $result[] = $row;
        }
        */
        $result = $res->fetch_all(MYSQLI_ASSOC);
        if (!is_array($result)) $result = array();
        $res->free();

        #$this->logger('DEBUG', $result);
        return $result;
    }


    /**
     * return one value (0 column or named column) from $sql or table/where/orderby
     * syntax 1: (raw sql)
     * @param  string $sql              sql query
     * @param  string $field_name       optional, field name to return. If ommited - 0 column fetched
     * syntax 2: (table/params)
     * @param  string $table_name       table name to read from
     * @param  string $where            array of (field => value) where conditions
     * @param  string $field_name       optional, field name to fetch and return. If not set - first field returned. Special case - "count(*)", will return count
     * @param  string $order_by         optional, order string to be added to ORDER BY
     *
     * @return string or null           return value from the field
     */
    public function value($sql_or_table, $field_or_where=NULL, $field_name=NULL, $order_by=NULL){
        $result = NULL;
        //detect syntax
        if (is_array($field_or_where)){
            //syntax 2
            $select_fields = '';
            if (is_null($field_name)){
                $select_fields = '*';
            }elseif ($field_name=='count(*)'){
                $select_fields = $field_name;
                $field_name=NULL;//reset to empty, so first field will be returned
            }else{
                $select_fields = $this->quote_ident($field_name);
            }

            list($sql, $params) = $this->build_sql_params($sql_or_table, $select_fields, $field_or_where, $order_by, 1);
            $res = $this->query($sql, $params);
            $rows = $res->fetch_all(MYSQLI_ASSOC);
            if (!is_array($rows)) $rows = array();
            $res->free();

        }else{
            //syntax 1
            $field_name = $field_or_where;
            $rows = $this->arr($sql_or_table);
        }

        if (count($rows)){
            if ($field_name>''){
                $result = $rows[0][$field_name];
            }else{
                $result = reset($rows[0]);
            }
        }

        return $result;
    }

    /**
     * return one column of values (0 column or named column) from $sql or table/where/orderby
     * syntax 1: (raw sql)
     * @param  string $sql              sql query
     * @param  string $field_name       optional, field name to return. If ommited - 0 column fetched
     * syntax 2: (table/params)
     * @param  string $table_name       table name to read from
     * @param  string $where            array of (field => value) where conditions
     * @param  string $field_name       field name to return
     * @param  string $order_by         optional, order string to be added to ORDER BY
     *
     * @return array                    array of values from the column, empty array if no rows fetched
     */
    public function col($sql_or_table, $field_or_where=NULL, $field_name=NULL, $order_by=NULL){
        $result = array();
        //detect syntax
        if (is_array($field_or_where)){
            //syntax 2
            list($sql, $params) = $this->build_sql_params($sql_or_table, ( is_null($field_name) ? '*' : $this->quote_ident($field_name) ), $field_or_where, $order_by);
            $res = $this->query($sql, $params);
            $rows = $res->fetch_all(MYSQLI_ASSOC);
            if (!is_array($rows)) $rows = array();
            $res->free();

        }else{
            //syntax 1
            $field_name = $field_or_where;
            $rows = $this->arr($sql_or_table);
        }

        foreach ($rows as $row) {
            if ($field_name>''){
                $result[] = $row[$field_name];
            }else{
                $result[] = reset($row);
            }
        }

        return $result;
    }

    /**
     * delete record(s) from db
     * @param  string $table      table name to delete from
     * @param  string $value      id value
     * @param  string $column     optional, column name for value, default = 'id'
     * @param  string|array $more_where additonal where for delete
     * @return nothing
     */
    public function delete($table, $value, $column = 'id', $more_where=''){
        $sql = 'DELETE FROM '.$this->quote_ident($table).' WHERE '.$this->quote_ident($column).'='.$this->quote($value).' '.$this->build_where_str($more_where);
        db_exec($sql);
    }


    /**
     * insert or replace record into db
     * @param  string $table    table name
     * @param  array $vars      assoc array of fields/values to insert OR array of assoc arrays (multi-row mode insert)
     * @param  array $options   optional, options: ignore, replace, no_identity
     * @return int              last insert id or null (if no_identity option provided)
     *
     * Note - multi-insert doesn't support ~!NULL and ~!now()
     */
    public function insert($table, $vars, $options=array()){
        $sql_command='INSERT';
        if ($options['replace']) $sql_command='REPLACE';

        $sql_ignore='';
        if ($options['ignore']) $sql_ignore=' IGNORE';

        $sql_insert=$sql_command.$sql_ignore.' INTO '.$this->quote_ident($table);

        if ( isset($vars[0]) && is_array($vars[0]) ) {
            #multi row mode
            $MAX_BIND_PARAMS=2000; #let's set some limit
            $rows_per_query = floor( $MAX_BIND_PARAMS/count($vars[0]) );
            $anames = $row_values = $avalues = $params = array();
            $is_anames_set=false;

            foreach ($vars as $i => $row) {
                foreach( $row as $k => $v ){
                    if (!$is_anames_set) {
                        $anames[]=$this->quote_ident($k);
                        $row_values[]='?';
                    }
                    $params[]=$v;
                }
                $is_anames_set=true; #only remember names from first row

                $avalues[]='('.implode(',', $row_values).')';
                if ( count($avalues) >= $rows_per_query ){
                    $sql = $sql_insert.'('.implode(',', $anames).') VALUES '.implode(',', $avalues);
                    $this->exec($sql, $params);
                    #reset for next set
                    $avalues = $params = array();
                }
            }

            #insert what's left
            if ( count($avalues)>0 ){
                $sql = $sql_insert.'('.implode(',', $anames).') VALUES '.implode(',', $avalues);
                $this->exec($sql, $params);
            }

        }else{
            #single row mode
            list($vars_quoted, $params) = $this->quote_array_params($vars);

            $sql = $sql_insert.' SET '.implode(', ', $vars_quoted);
            $this->exec($sql, $params);
        }

        if ($options['no_identity']){
            return;
        }else{
            return $this->get_identity();
        }
    }


    /**
     * update record in db by one column value or multiple where conditions
     * syntax 1: (update by one key field with more options)
     * @param  string $table    table name
     * @param  array  $vars     assoc array of fields/values to update
     * @param  string $key_id   column value for where
     * @param  string $column   optional, column id for where, default 'id'
     * @param  string $more_set optional, additional string to include in set (you have to take care about quotes!)
     * @param  string $more_where optional, additional string to include in where (you have to take care about quotes!)
     * syntax 2: (update by where)
     * @param  string $table    table name
     * @param  array $vars      assoc array of fields/values to update
     * @param  string $where    array of (field => value) where conditions
     * *
     * @return int              last insert id or null (if no_identity option provided)
     */
    public function update($table, $vars, $key_id_or_where, $column = 'id', $more_set='', $more_where=''){
        list($sql_set, $params_set) = $this->quote_array_params($vars);

        //detect syntax
        if (is_array($key_id_or_where)){
            //syntax 2
            list($sql_where, $params_where) = $this->quote_array_params($key_id_or_where);
            $sql='UPDATE '.$this->quote_ident($table).' SET '.implode(', ', $sql_set).' WHERE '.implode(' AND ', $sql_where);
            $this->exec($sql, array_merge($params_set, $params_where));

        }else{
            //syntax 1
            $sql='UPDATE '.$this->quote_ident($table).' SET '.implode(', ', $sql_set).' '.$more_set.' WHERE '.$this->quote_ident($column).'='.$this->quote($key_id_or_where).' '.$more_where;
            $this->exec($sql, $params_set);
        }
    }

    /**
     * return true if record exists or false if not. Optionally exclude check for other column/value
     * @param  string $table_name   table name
     * @param  string $uniq_value   value to check
     * @param  string $column       optional, column name for uniq_value
     * @param  string $not_id       optional, not id to check
     * @param  string $not_id_column optional, not id column name
     * @return bool                 true if record exists or false if not
     */
    public function is_record_exists($table_name, $uniq_value, $column, $not_id=NULL, $not_id_column='id') {
        $not_sql='';
        if (!is_null($not_id)){
            $not_sql = ' AND '.$this->quote_ident($not_id_column).'<>'.$this->quote($not_id);
        }
        $sql='SELECT 1 FROM '.$this->quote_ident($table_name).' WHERE '.$this->quote_ident($column).'='.$this->quote($uniq_value).$not_sql.' LIMIT 1';
        $val = $this->value($sql);
        return $val==1 ? true : false;
    }


    //************* helpers

    public function quote_array($vars){
        $quoted = array();
        if (is_array($vars)){
            foreach ($vars as $key => $value) {
                $quoted[] = $this->quote_ident($key).'='.$this->quote($value);
            }
        }
        return $quoted;
    }

    public function quote_array_params($vars){
        $quoted = array();
        $params = array();
        if (is_array($vars)){
            foreach ($vars as $key => $value) {
                if (preg_match("/^~!(?:NULL|now\(\))$/i", $value)){  //special case for NULL and now() - if started from ~! - don't quote, just remove '~!'
                    $quoted[] = $this->quote_ident($key).'='.substr($value, 2); #cut everything starting from position after ~!
                }else{
                    $quoted[] = $this->quote_ident($key).'=?';
                    $params[] = $value;
                }
            }
        }
        return array($quoted, $params);
    }

    /**
     * build where string from the array of fields/values
     * If string passed instead of array - it's returned unchanged
     * @param  array $where     fields/values
     * @return string           conditions to be included in where as string or empty string
     */
    public function build_where_str($where){
        $result = '';
        if (!is_array($where)) return $where;

        $where_quoted = $this->quote_array($where);
        if (count($where_quoted)){
            $result=implode(' AND ', $where_quoted);
        }
        return $result;
    }

    /**
     * build parametrized SELECT sql query for given table/where/order/limit
     * @param  string $table    table name
     * @param  string $select_fields    comma separated fields to select or '*'
     * @param  string $where    optional, where assoc array
     * @param  string $order_by optional, string to append to ORDER BY
     * @param  string $limit    optional, string to append to LIMIT
     * @return array of (sql, params)
     */
    public function build_sql_params($table, $select_fields, $where, $order_by=NULL, $limit=NULL){
        $sql='SELECT '.$select_fields.' FROM '.$this->quote_ident($table);

        if (is_array($where)){
            list($where_quoted, $params) = $this->quote_array_params($where);
            if (count($where_quoted)){
                $sql.=' WHERE '.implode(' AND ', $where_quoted);
            }
        }

        if ($order_by>''){
            $sql.=' ORDER BY '.$order_by;
        }
        if ($limit>''){
            $sql.=' LIMIT '.$limit;
        }

        return array($sql, $params);
    }

    /**
     * quote table name with `` for MySQL
     * TODO - support of different types of SQL_SERVER quotes
     * @param  string $table_name table name
     * @return string             quoted table name
     */
    public function quote_ident($table_name){
        $table_name=str_replace("`","",$table_name);    #mysql names should'nt contain ` !
        return '`'.$table_name.'`';
    }

    public function quote($value, $field_type=''){
        $this->check_connect();

        if ($field_type=='x'){
          $value=$value;

        }elseif ($field_type=='s'){ //explicit setting of string, no matter of ~!
          $value="'".$this->dbh->real_escape_string($value)."'";

        }elseif ($field_type=='i'){ //explicit setting of number, no matter of ~!
          $value=intval($value);

        }elseif ( is_null($value) ){ //null value
          $value='NULL';

        }elseif (preg_match("/^~!(?:NULL|now\(\))$/i", $value)){  //special case - if started from ~! - don't quote, just remove '~!'
          $value=substr($value, 2); #cut everything starting from position after ~!

        } else {
          $value="'".$this->dbh->real_escape_string($value)."'"; //real_escape_string doesn't add '' at begin/end
        }

        return $value;
    }

    /**
     * get last inserted id
     * @return int  last inserted id or 0
     */
    public function get_identity(){
        return $this->dbh->insert_id;
    }

    /**
     * [logger description]
     * @param  str $log_type 'ERROR'|'DEBUG'|'INFO'
     * @param  str $value    value to log
     * @return none
     */
    public function logger($log_type, $value){
        if ($this->config['IS_LOG']) logger($log_type, $value);
    }
}

 ?>