<?php
/*
Site DB class/SQL functions - simplified access to site database
convenient wrapper for mysqli

Part of PHP osa framework  www.osalabs.com/osafw/php
(c) 2009-2024 Oleg Savchuk www.osalabs.com
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
 *     'upd_time'   => DB::NOW, #will set upd_time=now()
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
 *     'upd_time'   => DB::NOW, #will set upd_time=now()
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
 * @param string $value value to be quoted
 * @return int        integer value
 */
function dbqi($value) {
    return intval($value);
}

/**
 * explicitly quote variable. If $field_type not defined and $value is NULL or DB::NOW - pass as NULL or now() accoridngly
 * @param string $value value to be quoted
 * @param string $field_type 's'(string, default if empty), 'i'(int), 'x'(no quote)
 * @return string, integer or 'NULL' string (if $field_type is not defined and $value is null)
 */
function dbq($value, $field_type = null) {
    return DB::i()->quote($value, $field_type);
}

function dbq_ident($value) {
    return DB::i()->quote_ident($value);
}

/**
 * return one value (0 column or named column) from $sql or table/where/orderby
 * syntax 1: (raw sql)
 * @param string $sql sql query
 * @param string $field_name optional, field name to return. If ommited - 0 column fetched
 * syntax 2: (table/params)
 * @param string $table_name table name to read from
 * @param string $where array of (field => value) where conditions
 * @param string $field_name optional, field name to fetch and return. If not set - first field returned. Special case - "count(*)", will return count
 * @param string $order_by optional, order string to be added to ORDER BY
 *
 * @return string or null           return value from the field
 */
function db_value($sql_or_table, $field_or_where = null, $field_name = null, $order_by = null) {
    return DB::i()->value($sql_or_table, $field_or_where, $field_name, $order_by);
}

/**
 * return one row from $sql or table/where/orderby
 * syntax 1: (raw sql)
 * @param string $sql sql query
 * syntax 2: (table/params)
 * @param string $table_name table name to read from
 * @param string $where array of (field => value) where conditions
 * @param string $order_by optional, order string to be added to ORDER BY
 *
 * @return array                    assoc array (has keys as field names and values as field values)
 */
function db_row($sql_or_table, $where = null, $order_by = null) {
    return DB::i()->row($sql_or_table, $where, $order_by);
}

/**
 * return one table record by primary key
 * shortcut for db_row($table, array('id'=> $id));
 * @param string $table table name
 * @param int $id primary key id
 * @return array         assoc array
 */
function db_obj($table, $id) {
    return DB::i()->obj($table, $id);
}

/**
 * return one column of values (0 column or named column) from $sql or table/where/orderby
 * syntax 1: (raw sql)
 * @param string $sql sql query
 * @param string $field_name optional, field name to return. If ommited - 0 column fetched
 * syntax 2: (table/params)
 * @param string $table_name table name to read from
 * @param string $where array of (field => value) where conditions
 * @param string $field_name field name to return
 * @param string $order_by optional, order string to be added to ORDER BY
 *
 * @return array                    array of values from the column, empty array if no rows fetched
 */
function db_col($sql_or_table, $field_or_where = null, $field_name = null, $order_by = null) {
    return DB::i()->col($sql_or_table, $field_or_where, $field_name, $order_by);
}

/**
 * return one value (0 column or named column) from $sql or table/where/orderby/limit
 * syntax 1: (raw sql)
 * @param string $sql sql query
 * syntax 2: (table/params)
 * @param string $table_name table name to read from
 * @param string $where array of (field => value) where conditions
 * @param string $order_by optional, order string to be added to ORDER BY
 * @param string $limit optional, limit string to be added to LIMIT
 *
 * @return array                    array of arrays (outer array has numerical keys and values as one fetched row; inner arrays has keys as field names and values as field values)
 */
function db_array($sql_or_table, $where = null, $order_by = null, $limit = null) {
    return DB::i()->arr($sql_or_table, $where, $order_by, $limit);
}

/**
 * perform query and return result statement. Throws an exception if error occured.
 * @param string $sql SQL query
 * @param array $params optional, array of params for prepared queries
 * @return mysqli_result  object
 */
function db_query($sql, $params = null) {
    return DB::i()->query($sql, $params);
}

/**
 * exectute query without returning result set. Throws an exception if error occured.
 * @param string $sql SQL query
 * @param array $params optional, array of params for prepared queries
 * @return void
 */
function db_exec($sql, $params = null) {
    DB::i()->exec($sql, $params);
}

/**
 * get last inserted id
 * @return int  last inserted id or 0
 */
function db_identity() {
    return DB::i()->get_identity();
}

//******************** helpers for INSERT/UPDATE/DELETE

/**
 * delete record(s) from db
 * @param string $table table name to delete from
 * @param string $value id value
 * @param string $column optional, column name for value, default = 'id'
 * @param string|array $more_where additonal where for delete
 * @return void
 */
function db_delete($table, $value, $column = 'id', $more_where = '') {
    DB::i()->delete($table, $value, $column, $more_where);
}

/**
 * insert or replace record into db
 * @param string $table table name
 * @param array $vars assoc array of fields/values to insert
 * @param array $options optional, options: ignore, replace, no_identity
 * @return int              last insert id or null (if no_identity option provided)
 */
function db_insert($table, $vars, $options = array()) {
    return DB::i()->insert($table, $vars, $options);
}

/**
 * update record in db
 * syntax 1: (update by one key field with more options)
 * @param string $table table name
 * @param array $vars assoc array of fields/values to update
 * @param array $options optional, options: ignore, replace, no_identity
 * syntax 2: (update by where)
 * @param string $table table name
 * @param array $vars assoc array of fields/values to update
 * @param string $where array of (field => value) where conditions
 * *
 * @return int              last insert id or null (if no_identity option provided)
 */
function db_update($table, $vars, $key_id, $column = 'id', $more_set = '', $more_where = '') {
    DB::i()->update($table, $vars, $key_id, $column, $more_set, $more_where);
}

/**
 * return true if record exists or false if not. Optionally exclude check for other column/value
 * @param string $table_name table name
 * @param string $uniq_value value to check
 * @param string $column optional, column name for uniq_value
 * @param string $not_id optional, not id to check
 * @param string $not_id_column optional, not id column name
 * @return bool                 true if record exists or false if not
 */
function db_is_record_exists($table_name, $uniq_value, $column, $not_id = null, $not_id_column = 'id') {
    return DB::i()->is_record_exists($table_name, $uniq_value, $column, $not_id, $not_id_column);
}

class DBException extends Exception {
} #exception to be raised by our code

/**
 * DB class
 *
 * TODO - full OO sample
 */
class DB {
    const ERROR_TOO_MANY_CONNECTIONS      = 1040;
    const ERROR_TOO_MANY_CONNECTIONS_USER = 1203; #User XX already has more than 'max_user_connections' active connections
    const ERROR_TABLE_NOT_EXISTS          = 1146;
    const ERROR_CANT_CONNECT              = 2002;
    const ERROR_GONE_AWAY                 = 2006;
    const ERROR_DUPLICATE_ENTRY           = 1062;

    const NOW = '###special_case_value_for_current_timestamp###';
    const NOTNULL        = '###special_case_value_for_not_null###';
    const MORE_THAN_ZERO = '###special_case_value_for_more_than_zero###';

    public static $SQL_QUERY_CTR = 0; //counter for SQL queries in request
    public static $instance;

    public ?mysqli $dbh = null; //mysqli object
    public $config = array(); //should contain: DBNAME, USER, PWD, HOST, PORT, [SQL_SERVER], IS_LOG
    #can also contain:
    # CONNECT_ATTEMPTS - how many times to try to connect (default 16)
    # CONNECT_TIMEOUT - how many seconds to wait for connection (default 0 - no timeout)
    # WAIT_TIMEOUT - how many seconds to wait for query (default 0 - no timeout)
    # DEADLOCK_RETRY_ATTEMPTS - how many times to execute query+retry on deadlock (default 16 retries before exception, min=1 - no retries)
    # IS_LOG - if true - log all queries (default false)

    public $lastRows; #affected_rows from last exec operations
    public $is_connected = false; #set to true if connect() were successful

    //if IS_LOG - external function logger() will be called for logging
    public function __construct($config = null) {
        global $CONFIG;
        if (is_null($config)) {
            $this->config = $CONFIG['DB']; //use site config, if config not passed explicitly
        } else {
            $this->config = $config;
        }

        #mysqli_report(MYSQLI_REPORT_OFF);
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); #Throw mysqli_sql_exception for errors instead of warning
    }

    # return singleton instance

    /** @return DB */
    public static function i() {
        if (!DB::$instance) {
            DB::$instance = new DB();
        }
        return DB::$instance;
    }

    /**
     * connect to sql server using config passed in constructor. Also prepares connection params (MySQL: utf, sql mode). Throw an exception if connection error occurs.
     * @return void
     */
    public function connect() {
        $attempts = $this->config['CONNECT_ATTEMPTS'] ?? 16; #need enough repeats so when Aurora switches ACU we'll keep patient
        while ($attempts--) {
            try {
                $last_exception = null;

                if (isset($this->config['CONNECT_TIMEOUT'])) {
                    #if need to set connect timeout - initialize in a different way
                    $this->dbh = mysqli_init();
                    $this->dbh->options(MYSQLI_OPT_CONNECT_TIMEOUT, $this->config['CONNECT_TIMEOUT']);
                    $this->dbh->real_connect($this->config['HOST'], $this->config['USER'], $this->config['PWD'], $this->config['DBNAME'], ($this->config['PORT'] > '' ? (int)$this->config['PORT'] : null));
                } else {
                    #@ hides connection warning, which is unnecessary as exception thrown anyway
                    @$this->dbh = new mysqli($this->config['HOST'], $this->config['USER'], $this->config['PWD'], $this->config['DBNAME'], ($this->config['PORT'] > '' ? (int)$this->config['PORT'] : null));
                }

                $this->is_connected = true;

                $this->dbh->set_charset("utf8mb4");
                #above is preffered way $this->query("SET NAMES utf8mb4");
                #$this->query("SET SESSION sql_mode = ''"); #required fw to work on MySQL 5.5+

                if ($this->config['WAIT_TIMEOUT'] ?? 0 > 0) {
                    $this->query("SET SESSION wait_timeout=" . dbqi($this->config['WAIT_TIMEOUT']));
                }

                break;

            } catch (Exception $e) {
                #looks like for network connectivity issues we got ErrorException instead of mysqli_sql_exception - log some details for now
                #php_network_getaddresses: getaddrinfo failed: Temporary failure in name resolution
                #Error while reading greeting packet.
                $last_exception = $e;
                $this->logger('NOTICE', "Got Exception in connect()", "code=" . $e->getCode() . ", msg=" . $e->getMessage() . ", host=" . $this->config['HOST'], $e);
                if ($e instanceof mysqli_sql_exception) {
                    if ($attempts > 0 && in_array($e->getCode(), [self::ERROR_TOO_MANY_CONNECTIONS, self::ERROR_TOO_MANY_CONNECTIONS_USER, self::ERROR_CANT_CONNECT, self::ERROR_GONE_AWAY])) {
                        // too many connections, connection timed out/no route to host, server has gone away,
                        $this->logger('NOTICE', "Attempting to reconnect", $e->getMessage());
                        sleep(rand(1, 3)); #if got too many connections
                    } else {
                        break; #no repeat
                    }
                } else {
                    break; #no repeat
                }
            }
        }
        if ($last_exception) {
            $this->handle_error($last_exception);
        }
    }

    /**
     * check connection and reconnect if necessary
     * @return void
     * @throws DBException
     */
    public function check_connect() {
        $is_reconnect = !$this->is_connected || is_null($this->dbh);

        if (!$is_reconnect) {
            try {
                $is_reconnect = !@$this->dbh->ping(); #we don't need Warning: mysqli::ping(): MySQL server has gone away
            } catch (mysqli_sql_exception $e) {
                //if ping fails - MySQL server has gone away
                $is_reconnect = true;
            }
        }

        if ($is_reconnect) {
            $this->connect();
        }
    }

    /**
     * close connection to sql server
     * @return void
     */
    public function disconnect() {
        if (!is_null($this->dbh)) {
            $this->dbh->close();
        }

        $this->dbh = null;
    }

    /**
     * handle mysqli_sql_exception
     * @param Exception $ex original mysql exception
     * @throws DBException
     */
    public function handle_error($ex) {
        $err_str = '(' . $ex->getCode() . ') ' . $ex->getMessage();
        #$this->logger('ERROR', $ex); uncommenting this will duplicate error messages if errors logged at higher level
        throw new DBException($err_str, $ex->getCode());
    }

    /**
     * check if statement or result is FALSE and throw Exception
     * @param mixed $check statement or result to check
     * @return void, logger an error and throws an exception
     */
    public function handle_errorOLD($checkvar) {
        if ($checkvar === false) {
            $err_str = '(' . $this->dbh->errno . ') ' . $this->dbh->error;
            $this->logger('ERROR', $err_str);
            throw new DBException($err_str, $this->dbh->errno);
        }
    }

    /**
     * perform query and return result statement. Throws an exception if error occured.
     * Note: on deadlock - automatically tries to repeat query couple times
     * @param string $sql SQL query
     * @param array $params optional, array of params for prepared queries
     * @return mysqli_result  object or null
     * @throws DBException
     */
    public function query($sql, $params = null) {
        $result = null;
        $this->check_connect();
        DB::$SQL_QUERY_CTR++;

        $deadlock_attempts = $this->config['DEADLOCK_RETRY_ATTEMPTS'] ?? 16; #max deadlock retry attempts
        $last_ex           = null;

        while ($deadlock_attempts--) {
            try {
                $last_ex = null;
                $result  = $this->query_inner($sql, $params);
                break;
            } catch (DBException $ex) {
                $last_ex = $ex;
                $err_msg = $ex->getMessage();
                if (preg_match("/deadlock/i", $err_msg)) {
                    $this->logger('NOTICE', "Sleep/retry on deadlock", "attempts left:" . $deadlock_attempts, $err_msg);
                    sleep(rand(1, 3)); #if got deadlock - sleep 1-3s before repeat
                } else {
                    throw $ex;
                }
            }
        }
        if (is_null($result) && !is_null($last_ex)) {
            #looks like repeats not helped
            throw $last_ex;
        }

        return $result;
    }

    /**
     * perform query and return result statement. Throws an exception if error occured.
     * @param string $sql SQL query
     * @param array $params optional, array of params for prepared queries
     * @return null|bool|mysqli_result  object
     * @throws DBException
     */
    public function query_inner($sql, $params = null): null|bool|mysqli_result {
        $result = null;
        $host   = $this->config['HOST'];
        #for logger - just leave first name in domain
        $dbhost_info = substr($host, 0, strpos($host, '.')) . '(' . $this->config['USER'] . '):' . $this->config['DBNAME'] . ' ';

        try {
            if (is_array($params) && count($params)) {
                //use prepared query
                $this->logger('NOTICE', $dbhost_info . $sql, $params);

                $st = $this->dbh->prepare($sql);
                #$this->handle_error($st);

                $query_types  = str_repeat("s", count($params)); #just bind all params as strings, TODO - support of passing types
                $query_params = array($query_types);
                foreach ($params as $k => $v) {
                    $query_params[] = &$params[$k];
                }
                call_user_func_array(array($st, 'bind_param'), $query_params);

                $res = $st->execute();
                $this->lastRows = $this->dbh->affected_rows;
                #$this->handle_error($res);

                $result = $st->get_result();
                #if non-query - returns false, no need to check result_metadata
                if ($result === FALSE) {
                    $result = null;
                }

                $st->close();
            } else {
                //use direct query
                $this->logger('NOTICE', $dbhost_info . $sql);

                $result = $this->dbh->query($sql);
                $this->lastRows = $this->dbh->affected_rows;
                #no need to check for metadata here as query returns TRUE for non-select
                #$this->handle_error($result);
            }
        } catch (Exception $e) {
            $this->handle_error($e);
        }

        return $result;
    }

    /**
     * prepares sql and return prepared statement, use then in query_prepared()
     * Throws an exception if error occured.
     * @param string $sql sql query
     * @return mysqli_stmt prepared statement
     * @throws DBException
     */
    public function prepare($sql): mysqli_stmt {
        $this->check_connect();

        $dbhost_info = $this->config['HOST'] . ':' . $this->config['DBNAME'] . ' ';
        $this->logger('NOTICE', $dbhost_info . 'PREPARE SQL: ' . $sql);

        try {
            $st = $this->dbh->prepare($sql);
        } catch (Exception $e) {
            $this->handle_error($e);
        }

        return $st;
    }

    /**
     * executes previously prepared statement with params
     * Throws an exception if error occured.
     * @param mysqli_stmt $st prepared statement using prepare()
     * @param array $params optional, array of params for prepared queries. note! for associative arrays assign key/values in the same order as for prepare()
     * @return null|array null for non-select queries, array of rows for select queries
     * @throws DBException
     */
    public function query_prepared(mysqli_stmt $st, $params): null|array {
        DB::$SQL_QUERY_CTR++;

        $dbhost_info = $this->config['HOST'] . ':' . $this->config['DBNAME'] . ' ';
        $result      = null;

        if (count($params) > 2) {
            // log as separate params if more than 2
            $this->logger('NOTICE', $dbhost_info . 'EXEC PREPARED', $params);
        } else {
            // more compact log if less than 3 params
            $this->logger('NOTICE', $dbhost_info . 'EXEC PREPARED: ' . implode(',', array_values($params)));
        }

        try {
            #just bind all params as strings, TODO - support of passing types
            $query_params = [];
            #re-assign to new array as $params can be associative array and we only need values
            foreach ($params as $v) {
                $query_params[] = $v;
            }

            $st->bind_param(str_repeat("s", count($query_params)), ...$query_params);
            $st->execute();

            if ($st->field_count > 0) {
                #this is select query - get result
                $result = $st->get_result()->fetch_all(MYSQLI_ASSOC);
            }

        } catch (Exception $e) {
            $this->handle_error($e);
        }

        return $result;
    }

    /**
     * exectute query without returning result set. Throws an exception if error occured.
     * @param string $sql SQL query
     * @param array $params optional, array of params for prepared queries
     * @return void
     * @throws DBException
     */
    public function exec($sql, $params = null) {
        $this->query($sql, $params);
        $this->lastRows = $this->dbh->affected_rows;
    }

    /**
     * return one row from $sql or table/where/orderby
     * syntax 1: (raw sql)
     * @param string $sql sql query
     * syntax 2: (table/params)
     * @param string $table_name table name to read from
     * @param string $where array of (field => value) where conditions
     * @param string $order_by optional, order string to be added to ORDER BY
     *
     * @return array                  assoc array (has keys as field names and values as field values) or empty array if no rows returned
     */
    public function row($sql_or_table, $where = null, $order_by = null): array {
        $rows = $this->arr($sql_or_table, $where, $order_by, 1);
        if (count($rows)) {
            $result = $rows[0];
        } else {
            $result = [];
        }
        return $result;
    }

    /**
     * return one table record by primary key
     * shortcut for row($table, array('id'=> $id));
     * @param string $table table name
     * @param int $id primary key id
     * @return array         assoc array
     */
    public function obj($table, $id) {
        return $this->row($table, array('id' => $id));
    }

    /**
     * return one value (0 column or named column) from $sql or table/where/orderby
     * syntax 1: (raw sql)
     * @param string $sql sql query
     * syntax 2: (table/params)
     * @param string $table_name table name to read from
     * @param string $where array of (field => value) where conditions
     * @param string $order_by optional, order string to be added to ORDER BY
     * @param string $limit optional, limit string to be added to LIMIT
     *
     * @return array                    array of assoc arrays (outer array has numerical keys and values as inner array; inner arrays has keys as field names and values)
     */
    public function arr($sql_or_table, $where = null, $order_by = null, $limit = null) {
        $result = array();
        //detect syntax
        if (is_array($where)) {
            //syntax 2
            list($sql, $params) = $this->build_sql_params($sql_or_table, '*', $where, $order_by, $limit);
            $res = $this->query($sql, $params);
        } else {
            //syntax 1
            $res = $this->query($sql_or_table);
        }
        /* workaround if fetch_all not available
        while ($row = $res->fetch_assoc()) {
        $result[] = $row;
        }
         */
        $result = $res->fetch_all(MYSQLI_ASSOC);
        if (!is_array($result)) {
            $result = array();
        }

        $res->free();

        #$this->logger('DEBUG', $result);
        return $result;
    }

    /**
     * return one value (0 column or named column) from $sql or table/where/orderby
     * syntax 1: (raw sql)
     * @param string $sql sql query
     * @param string $field_name optional, field name to return. If ommited - 0 column fetched
     * syntax 2: (table/params)
     * @param string $table_name table name to read from
     * @param string $where array of (field => value) where conditions
     * @param string $field_name optional, field name to fetch and return. If not set - first field returned. Special case - "count(*),sum(field),avg,max,min", will return count/sum/...
     * @param string $order_by optional, order string to be added to ORDER BY
     *
     * @return string or null           return value from the field
     */
    public function value($sql_or_table, $field_or_where = null, $field_name = null, $order_by = null) {
        $result = null;
        //detect syntax
        if (is_array($field_or_where)) {
            //syntax 2
            $select_fields = '';
            if (is_null($field_name)) {
                $select_fields = '*';
            } elseif (str_starts_with($field_name, 'count(')) {
                $select_fields = $field_name;
                $field_name    = null; //reset to empty, so first field will be returned
            } elseif (preg_match('/^(\w+)\((\w+)\)$/', $field_name, $m)) {
                // sum, avg, max, min
                $func          = $m[1];
                $fld           = $this->quote_ident($m[2]);
                $select_fields = $func . '(' . $fld . ')';
                $field_name    = null;
            } else {
                $select_fields = $this->quote_ident($field_name);
            }

            list($sql, $params) = $this->build_sql_params($sql_or_table, $select_fields, $field_or_where, $order_by, 1);
            $res  = $this->query($sql, $params);
            $rows = $res->fetch_all(MYSQLI_ASSOC);
            if (!is_array($rows)) {
                $rows = array();
            }

            $res->free();
        } else {
            //syntax 1
            $field_name = $field_or_where;
            $rows       = $this->arr($sql_or_table);
        }

        if (count($rows)) {
            if ($field_name > '') {
                $result = $rows[0][$field_name];
            } else {
                $result = reset($rows[0]);
            }
        }

        return $result;
    }

    /**
     * return one column of values (0 column or named column) from $sql or table/where/orderby
     * syntax 1: (raw sql)
     * @param string $sql sql query
     * @param string $field_name optional, field name to return. If ommited - 0 column fetched
     * syntax 2: (table/params)
     * @param string $table_name table name to read from
     * @param string $where array of (field => value) where conditions
     * @param string $field_name field name to return
     * @param string $order_by optional, order string to be added to ORDER BY
     *
     * @return array                    array of values from the column, empty array if no rows fetched
     */
    public function col($sql_or_table, $field_or_where = null, $field_name = null, $order_by = null) {
        $result = array();
        //detect syntax
        if (is_array($field_or_where)) {
            //syntax 2
            list($sql, $params) = $this->build_sql_params($sql_or_table, (is_null($field_name) ? '*' : $this->quote_ident($field_name)), $field_or_where, $order_by);
            $res  = $this->query($sql, $params);
            $rows = $res->fetch_all(MYSQLI_ASSOC);
            if (!is_array($rows)) {
                $rows = array();
            }

            $res->free();
        } else {
            //syntax 1
            $field_name = $field_or_where;
            $rows       = $this->arr($sql_or_table);
        }

        foreach ($rows as $row) {
            if ($field_name > '') {
                $result[] = $row[$field_name];
            } else {
                $result[] = reset($row);
            }
        }

        return $result;
    }

    /**
     * delete record(s) from db
     * @param string $table table name to delete from
     * @param string $value id value
     * @param string $column optional, column name for value, default = 'id'
     * @param string|array $more_where additonal where for delete
     * @return void
     */
    public function delete($table, $value, $column = 'id', $more_where = '') {
        $where = $this->quote_ident($column) . (is_array($value) ? $this->insql($value) : '=' . $this->quote($value));
        if ($more_where) {
            $where .= ' AND ' . $this->build_where_str($more_where);
        }

        $sql = 'DELETE FROM ' . $this->quote_ident($table) . ' WHERE ' . $where;
        $this->exec($sql);
    }

    /**
     * insert or replace record into db
     * @param string $table table name
     * @param array $vars assoc array of fields/values to insert OR array of assoc arrays (multi-row mode insert)
     * @param array $options optional, options: ignore, replace, no_identity
     * @return int              last insert id or null (if no_identity option provided)
     *
     * Note - multi-insert doesn't support DB::NOW
     */
    public function insert($table, $vars, $options = array()) {
        $sql_command = 'INSERT';
        if (array_key_exists('replace', $options)) {
            $sql_command = 'REPLACE';
        }

        $sql_ignore = '';
        if (array_key_exists('ignore', $options)) {
            $sql_ignore = ' IGNORE';
        }

        $sql_insert = $sql_command . $sql_ignore . ' INTO ' . $this->quote_ident($table);

        if (isset($vars[0]) && is_array($vars[0])) {
            #multi row mode
            $MAX_BIND_PARAMS = 2000; #let's set some limit
            $rows_per_query  = floor($MAX_BIND_PARAMS / count($vars[0]));
            $anames          = $row_values = $avalues = $params = array();
            $is_anames_set   = false;

            foreach ($vars as $i => $row) {
                foreach ($row as $k => $v) {
                    if (!$is_anames_set) {
                        $anames[]     = $this->quote_ident($k);
                        $row_values[] = '?';
                    }
                    $params[] = $v;
                }
                $is_anames_set = true; #only remember names from first row

                $avalues[] = '(' . implode(',', $row_values) . ')';
                if (count($avalues) >= $rows_per_query) {
                    $sql = $sql_insert . '(' . implode(',', $anames) . ') VALUES ' . implode(',', $avalues);
                    $this->exec($sql, $params);
                    #reset for next set
                    $avalues = $params = array();
                }
            }

            #insert what's left
            if (count($avalues) > 0) {
                $sql = $sql_insert . '(' . implode(',', $anames) . ') VALUES ' . implode(',', $avalues);
                $this->exec($sql, $params);
            }
        } else {
            #single row mode
            list($vars_quoted, $params) = $this->quote_array_params($vars);

            $sql = $sql_insert . ' SET ' . implode(', ', $vars_quoted);
            $this->exec($sql, $params);
        }

        if (array_key_exists('no_identity', $options)) {
            return;
        } else {
            return $this->get_identity();
        }
    }

    /**
     * update record in db by one column value or multiple where conditions
     * syntax 1: (update by one key field with more options)
     * @param string $table table name
     * @param array $vars assoc array of fields/values to update
     * @param string $key_id column value for where
     * @param string $column optional, column id for where, default 'id'
     * @param string $more_set optional, additional string to include in set (you have to take care about quotes!)
     * @param string $more_where optional, additional string to include in where (you have to take care about quotes!)
     * syntax 2: (update by where)
     * @param string $table table name
     * @param array $vars assoc array of fields/values to update
     * @param string $where array of (field => value) where conditions
     * *
     * @return int              last insert id or null (if no_identity option provided)
     */
    public function update($table, $vars, $key_id_or_where, $column = 'id', $more_set = '', $more_where = '') {
        list($sql_set, $params_set) = $this->quote_array_params($vars);

        //detect syntax
        if (is_array($key_id_or_where)) {
            //syntax 2
            list($sql_where, $params_where) = $this->quote_array_params($key_id_or_where, true);
            $sql = 'UPDATE ' . $this->quote_ident($table) . ' SET ' . implode(', ', $sql_set);
            if ($sql_where) {
                #if we have non-empty where
                $sql .= ' WHERE ' . implode(' AND ', $sql_where);
            }
            $this->exec($sql, array_merge($params_set, $params_where));
        } else {
            //syntax 1
            $sql = 'UPDATE ' . $this->quote_ident($table) . ' SET ' . implode(', ', $sql_set) . ' ' . $more_set;
            if (strlen($key_id_or_where) > 0) {
                $sql .= ' WHERE ' . $this->quote_ident($column) . '=' . $this->quote($key_id_or_where) . ' ' . $more_where;
            }
            $this->exec($sql, $params_set);
        }
    }

    /**
     * return true if record exists or false if not. Optionally exclude check for other column/value
     * @param string $table_name table name
     * @param string $uniq_value value to check
     * @param string $column optional, column name for uniq_value
     * @param string $not_id optional, not id to check
     * @param string $not_id_column optional, not id column name
     * @return bool                 true if record exists or false if not
     */
    public function is_record_exists($table_name, $uniq_value, $column, $not_id = null, $not_id_column = 'id') {
        $not_sql = '';
        if (!is_null($not_id)) {
            $not_sql = ' AND ' . $this->quote_ident($not_id_column) . '<>' . $this->quote($not_id);
        }
        $sql = 'SELECT 1 FROM ' . $this->quote_ident($table_name) . ' WHERE ' . $this->quote_ident($column) . '=' . $this->quote($uniq_value) . $not_sql . ' LIMIT 1';
        $val = $this->value($sql);
        return $val == 1 ? true : false;
    }

    //************* helpers

    //to use with IN sql queries with proper quoting, ex:
    // $sql=" AND `sender` ".$this->insql($scopes);
    // note: if $values array empty the follwign sql returned: " IN (NULL) "
    public function insql($values) {
        #quote first
        $arr = array();
        foreach ($values as $value) {
            $arr[] = $this->q($value);
        }
        $sql = ($arr ? implode(",", $arr) : "NULL");
        #return sql
        return ' IN (' . $sql . ') ';
    }

    /**
     * same as insql but for integer values
     * (no quotes quoting, just convert all values to integers)
     * @param array $values array of values
     * @return string        "IN (1,2,3)" sql or IN (NULL) if empty params passed
     */
    public function insqli($values) {
        #quote first
        $arr = array();
        foreach ($values as $value) {
            $arr[] = intval($value);
        }
        $sql = ($arr ? implode(",", $arr) : "NULL");
        #return sql
        return ' IN (' . $sql . ') ';
    }

    //DEPRECATED, use insql()
    public function in_implode($values) {
        logger("WARN", "Deprecated function usage: in_implode");
        return $this->insql($values);
    }

    public function quote_array($vars) {
        $quoted = array();
        if (is_array($vars)) {
            foreach ($vars as $key => $value) {
                $quoted[] = $this->quote_ident($key) . '=' . $this->quote($value);
            }
        }
        return $quoted;
    }

    // is_where=true - this quoted for where (i.e. use IS NULL instead of "=")
    public function quote_array_params($vars, $is_where = false) {
        $quoted = array();
        $params = array();
        if (is_array($vars)) {
            foreach ($vars as $key => $value) {
                //special case for NULL and now()
                if ($value === DB::NOW) {
                    $quoted[] = $this->quote_ident($key) . '=NOW()';
                } elseif (is_null($value)) {
                    $quoted[] = $this->quote_ident($key) . ($is_where ? ' IS NULL' : '=NULL');
                } elseif ($value === DB::NOTNULL) {
                    $quoted[] = $this->quote_ident($key) . ($is_where ? ' IS NOT NULL' : '!=NULL');
                } elseif ($value === DB::MORE_THAN_ZERO) {
                    $quoted[] = $this->quote_ident($key) . ' > 0';
                } else {
                    $quoted[] = $this->quote_ident($key) . '=?';
                    $params[] = $value;
                }
            }
        }
        return array($quoted, $params);
    }

    /**
     * build where string from the array of fields/values
     * If string passed instead of array - it's returned unchanged
     * @param array $where fields/values
     * @return string           conditions to be included in where as string or empty string
     */
    public function build_where_str($where) {
        $result = '';
        if (!is_array($where)) {
            return $where;
        }

        $where_quoted = $this->quote_array($where);
        if (count($where_quoted)) {
            $result = implode(' AND ', $where_quoted);
        }
        return $result;
    }

    /**
     * build where string from array
     * @param array|int $where number (where id=number), array (where build as AND conditions against all array fields/values), if null - empty string returned
     * @return string        "where xxxx" string or empty if no
     */
    public function _where($where, $noident = false) {
        $result = '';
        if (is_array($where)) {
            $afields_sql = array();
            foreach ($where as $key => $value) {
                if (is_null($value)) {
                    #special case for NULL values
                    $afields_sql[] = ($noident ? $key : $this->qident($key)) . ' IS NULL';
                } elseif ($value === DB::NOTNULL) {
                    #special case for NULL values
                    $afields_sql[] = ($noident ? $key : $this->qident($key)) . ' IS NOT NULL';
                } elseif ($value === DB::MORE_THAN_ZERO) {
                    #special case for NULL values
                    $afields_sql[] = ($noident ? $key : $this->qident($key)) . ' > 0';
                } elseif (is_array($value)) {
                    $afields_sql[] = ($noident ? $key : $this->qident($key)) . $this->insql($value);
                } else {
                    $afields_sql[] = ($noident ? $key : $this->qident($key)) . '=' . $this->q($value);
                }
            }
            if (count($afields_sql)) {
                $result = "where " . implode(" and ", $afields_sql);
            }
        } elseif (!is_null($where)) {
            $result = " where id=" . $this->q($where);
        }
        return $result;
    }

    /**
     * build parametrized SELECT sql query for given table/where/order/limit
     * @param string $table table name
     * @param string $select_fields comma separated fields to select or '*'
     * @param array $where optional, where assoc array
     * @param string $order_by optional, string to append to ORDER BY
     * @param string $limit optional, string to append to LIMIT
     * @return array of (sql, params)
     */
    public function build_sql_params($table, $select_fields, $where, $order_by = null, $limit = null) {
        $sql = 'SELECT ' . $select_fields . ' FROM ' . $this->quote_ident($table);

        if (is_array($where)) {
            list($where_quoted, $params) = $this->quote_array_params($where, true);
            if (count($where_quoted)) {
                $sql .= ' WHERE ' . implode(' AND ', $where_quoted);
            }
        }

        if ($order_by > '') {
            $sql .= ' ORDER BY ' . $order_by;
        }
        if ($limit > '') {
            $sql .= ' LIMIT ' . $limit;
        }

        return array($sql, $params);
    }

    /**
     * quote table name with `` for MySQL
     * TODO - support of different types of SQL_SERVER quotes
     * @param string $table_name table name
     * @return string             quoted table name
     */
    public function quote_ident($table_name) {
        $table_name = str_replace("`", "", $table_name); #mysql names should'nt contain ` !
        return '`' . $table_name . '`';
    }

    #alias for quote_ident
    public function qident($table_name) {
        return $this->quote_ident($table_name);
    }

    #alias for quote
    public function q($value, $field_type = '') {
        return $this->quote($value, $field_type);
    }

    public function quote($value, $field_type = '') {
        $this->check_connect();

        if ($field_type == 'x') {
            $value = $value;
        } elseif ($field_type == 's') {
            $value = "'" . $this->dbh->real_escape_string($value) . "'";
        } elseif ($field_type == 'i') {
            $value = intval($value);
        } elseif (is_null($value)) {
            //null value
            $value = 'NULL';
        } elseif ($value === DB::NOTNULL) {
            //null value
            throw new DBException("Impossible use of NOTNULL");
        } elseif ($value === DB::NOW) {
            $value = 'NOW()';
        } else {
            $value = "'" . $this->dbh->real_escape_string($value) . "'"; //real_escape_string doesn't add '' at begin/end
        }

        return $value;
    }

    /**
     * get last inserted id
     * @return int  last inserted id or 0
     */
    public function get_identity() {
        return $this->dbh->insert_id;
    }

    /**
     * return list of tables in db
     * @return array plain array of table names
     */
    public function tables() {
        return $this->col("show tables");
    }

    public function table_schema($table_name) {
        $rows = $this->arr("SELECT
             c.column_name as `name`,
             c.data_type as `type`,
             CASE c.is_nullable WHEN 'YES' THEN 1 ELSE 0 END AS `is_nullable`,
             c.column_default as `default`,
             c.character_maximum_length as `maxlen`,
             c.numeric_precision as numeric_precision,
             c.numeric_scale as numeric_scale,
             c.character_set_name as `charset`,
             c.collation_name as `collation`,
             c.ORDINAL_POSITION as `pos`,
             CASE c.EXTRA WHEN 'auto_increment' THEN 1 ELSE 0 END as is_identity
            from information_schema.COLUMNS c
            where c.TABLE_SCHEMA=" . dbq($this->config['DBNAME']) . "
              and c.TABLE_NAME=" . dbq($table_name) . "
            ");
        foreach ($rows as $key => &$row) {
            $row["internal_type"] = $this->map_sqltype2internal($row["type"]);
        }
        unset($row);

        return $rows;
    }

    public function map_sqltype2internal($type) {
        switch (strtolower($type)) {
            #TODO - unsupported: image, varbinary
            case "tinyint":
            case "smallint":
            case "int":
            case "bigint":
            case "bit":
                $result = "int";
                break;

            case "real":
            case "numeric":
            case "decimal":
            case "money":
            case "smallmoney":
            case "float":
                $result = "float";
                break;

            case "datetime":
            case "datetime2":
            case "date":
            case "smalldatetime":
                $result = "datetime";
                break;

            default: #"text", "ntext", "varchar", "nvarchar", "char", "nchar"
                $result = "varchar";
                break;
        }
        return $result;
    }

    /**
     * db logger, calls external global logger()
     * by default all db logs set to NOTICE allowing to track code execution flow
     * @param string $log_type 'ERROR'|'DEBUG'|'NOTICE'|'INFO'
     * @param mixed $value value to log
     * @param mixed $value2 optional second value (usually params for param query)
     * @return void
     */
    public function logger(string $log_type, mixed $value, mixed $value2 = null): void {
        if ($this->config['IS_LOG']) {
            #do it separately depending if $value2 set for cleaner logs
            if (is_null($value2)) {
                logger($log_type, $value);
            } else {
                logger($log_type, $value, $value2);
            }
        }
    }
}
