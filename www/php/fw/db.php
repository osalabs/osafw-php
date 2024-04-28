<?php
/*
DB class/SQL functions - simplified access to a site database
convenient wrapper for mysqli

Part of PHP osa framework  www.osalabs.com/osafw/php
(c) 2009-2024 Oleg Savchuk www.osalabs.com
 */

//<editor-fold desc="Procedural shortcuts (uses DB singleton)">
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
function dbqi(string $value): int {
    return intval($value);
}

/**
 * explicitly quote variable. If $field_type not defined and $value is NULL or DB::NOW - pass as NULL or now() accoridngly
 * @param string $value value to be quoted
 * @param string|null $field_type 's'(string, default if empty), 'i'(int), 'x'(no quote)
 * @return string, integer or 'NULL' string (if $field_type is not defined and $value is null)
 */
function dbq(string $value, string $field_type = null): string {
    return DB::i()->quote($value, $field_type);
}

function dbqid(string $value): string {
    return DB::i()->qid($value);
}

/**
 * return one value from table/where/orderby
 * @param string $table
 * @param array $where
 * @param string|null $field_name
 * @param string|null $order_by
 * @return string|null
 * @throws DBException
 */
function db_value(string $table, array $where, string $field_name = null, string $order_by = null): ?string {
    return DB::i()->value($table, $where, $field_name, $order_by);
}

/**
 * return one value from $sql with params
 * @param string $sql
 * @param array|null $params
 * @return string|null
 * @throws DBException
 */
function db_valuep(string $sql, array $params = null): ?string {
    return DB::i()->valuep($sql, $params);
}

/**
 * return one row from table/where/orderby
 * @param string $table table name
 * @param array $where array of (field => value) where conditions
 * @param string|null $order_by optional, order string to be added to ORDER BY
 * @return array                   assoc array (has keys as field names and values as field values)
 * @throws DBException
 */
function db_row(string $table, array $where, string $order_by = null): array {
    return DB::i()->row($table, $where, $order_by);
}

/**
 * return one row from $sql with params
 * @param string $sql sql query
 * @param array|null $params
 * @return array
 * @throws DBException
 */
function db_rowp(string $sql, array $params = null): array {
    return DB::i()->rowp($sql, $params);
}

/**
 * return one column of values from table/where/orderby
 * @param string $table
 * @param array $where
 * @param string|null $field_name
 * @param string|null $order_by
 * @return array
 * @throws DBException
 */
function db_col(string $table, array $where, string $field_name = null, string $order_by = null): array {
    return DB::i()->col($table, $where, $field_name, $order_by);
}

/**
 * return one column of values from $sql with params
 * @param string $sql
 * @param array|null $params
 * @return array
 * @throws DBException
 */
function db_colp(string $sql, array $params = null): array {
    return DB::i()->colp($sql, $params);
}

/**
 * return all rows from table/where/orderby/limit
 * @param string $table
 * @param array|null $where
 * @param string|null $order_by
 * @param int|null $limit
 * @param array|string $selected_fields optional, fields to select, default = "*", can be array of unquoted fields or comma-separated already quoted string of fields
 * @return array
 * @throws DBException
 */
function db_array(string $table, array $where = null, string $order_by = null, int $limit = null, array|string $selected_fields = "*"): array {
    return DB::i()->arr($table, $where, $order_by, $limit, $selected_fields);
}

/**
 * return all rows from $sql with params
 * @param string $sql
 * @param array|null $params
 * @return array
 * @throws DBException
 */
function db_arrayp(string $sql, array $params = null): array {
    return DB::i()->arrp($sql, $params);
}

/**
 * perform query and return result statement. Throws an exception if error occurred.
 * @param string $sql SQL query
 * @param array|null $params optional, array of params for prepared queries
 * @return mysqli_result|bool object
 * @throws DBException
 */
function db_query(string $sql, array $params = null): mysqli_result|bool {
    return DB::i()->query($sql, $params);
}

/**
 * execute query without returning result set. Throws an exception if error occurred.
 * @param string $sql SQL query
 * @param array|null $params optional, array of params for prepared queries
 * @return void
 * @throws DBException
 */
function db_exec(string $sql, array $params = null): void {
    DB::i()->exec($sql, $params);
}

/**
 * get last inserted id
 * @return int  last inserted id or 0
 */
function db_identity(): int {
    return DB::i()->get_identity();
}

//******************** helpers for INSERT/UPDATE/DELETE

/**
 * delete record(s) from db by where condition
 * @param string $table table name
 * @param array $where array of (field => value) where conditions
 * @param string|null $order_by optional, order string to be added to ORDER BY
 * @param string|null $limit optional, limit string to be added to LIMIT
 * @return int number of deleted rows
 * @throws DBException
 */
function db_delete(string $table, array $where, string $order_by = null, string $limit = null): int {
    return DB::i()->delete($table, $where, $order_by, $limit);
}

/**
 * insert or replace record into db
 * @param string $table table name
 * @param array $vars assoc array of fields/values to insert or multi-array for multi-insert
 * @param array $options optional, options: ignore, replace, no_identity
 * @return int              last insert id or null (if no_identity option provided)
 * @throws DBException
 */
function db_insert(string $table, array $vars, array $options = array()): int {
    return DB::i()->insert($table, $vars, $options);
}

/**
 * update record(s) in db by table/fields/where
 * @param string $table
 * @param array $fields
 * @param array $where
 * @return int
 * @throws DBException
 */
function db_update(string $table, array $fields, array $where): int {
    return DB::i()->update($table, $fields, $where);
}

/**
 * update record(s) in db by parametrized query
 * @param string $sql
 * @param array|null $params
 * @return int
 * @throws DBException
 */
function db_updatep(string $sql, array $params = null): int {
    return DB::i()->updatep($sql, $params);
}

/**
 * return true if record exists or false if not. Optionally exclude check for other column/value
 * @param string $table_name table name
 * @param string $uniq_value value to check
 * @param string $column optional, column name for uniq_value
 * @param string|null $not_id optional, not id to check
 * @param string $not_id_column optional, not id column name
 * @return bool                 true if record exists or false if not
 * @throws DBException
 */
function db_is_record_exists(string $table_name, string $uniq_value, string $column, string $not_id = null, string $not_id_column = 'id'): bool {
    return DB::i()->isRecordExists($table_name, $uniq_value, $column, $not_id, $not_id_column);
}

//</editor-fold>

class DBException extends Exception {
} #exception to be raised by our code

/**
 * DB operations enum
 */
enum DBOps {
    case EQ;            // =
    case NOT;           // <>
    case LE;            // <=
    case LT;            // <
    case GE;            // >=
    case GT;            // >
    case ISNULL;        // IS NULL
    case ISNOTNULL;     // IS NOT NULL
    case IN;            // IN
    case NOTIN;         // NOT IN
    case LIKE;          // LIKE
    case NOTLIKE;       // NOT LIKE
    case BETWEEN;        // BETWEEN
}

/**
 * helper class - describes DB operation
 */
class DBOperation {
    public DBOps $op;
    public string $opstr; // string value for op
    public bool $is_value = true; // if false - operation is unary (no value)
    public mixed $value; // can be array for IN, NOT IN, OR
    public string $sql = ""; // raw value to be used in sql query string if !is_value

    public function __construct(DBOps $op, mixed $value = null) {
        $this->op = $op;
        $this->setOpStr();
        $this->value = $value;
    }

    public function setOpStr(): void {
        switch ($this->op) {
            case DBOps::ISNULL:
                $this->opstr    = "IS NULL";
                $this->is_value = false;
                break;
            case DBOps::ISNOTNULL:
                $this->opstr    = "IS NOT NULL";
                $this->is_value = false;
                break;
            case DBOps::EQ:
                $this->opstr = "=";
                break;
            case DBOps::NOT:
                $this->opstr = "<>";
                break;
            case DBOps::LE:
                $this->opstr = "<=";
                break;
            case DBOps::LT:
                $this->opstr = "<";
                break;
            case DBOps::GE:
                $this->opstr = ">=";
                break;
            case DBOps::GT:
                $this->opstr = ">";
                break;
            case DBOps::IN:
                $this->opstr = "IN";
                break;
            case DBOps::NOTIN:
                $this->opstr = "NOT IN";
                break;
            case DBOps::BETWEEN:
                $this->opstr = "BETWEEN";
                break;
            case DBOps::LIKE:
                $this->opstr = "LIKE";
                break;
            case DBOps::NOTLIKE:
                $this->opstr = "NOT LIKE";
                break;
        }
    }
}

/**
 * helper structure - describes query and params
 */
class DBQueryAndParams {
    public array $fields = []; // plain unquoted list of parametrized fields in order
    public string $sql = ''; // sql with parameter names, ex: field=@field
    public array $params = []; // parameter name => value, ex: field => 123
}

/**
 * DBSpecialValue class - special values for DB operations like 'NOW()'
 */
class DBSpecialValue {
    private string $value;

    public function __construct(string $value) {
        $this->value = $value;
    }

    public function __toString() {
        return $this->value;
    }
}


/**
 * DB class
 *
 * TODO - full OO sample
 */
class DB {
    const int ERROR_TOO_MANY_CONNECTIONS      = 1040;
    const int ERROR_TOO_MANY_CONNECTIONS_USER = 1203; #User XX already has more than 'max_user_connections' active connections
    const int ERROR_TABLE_NOT_EXISTS          = 1146;
    const int ERROR_CANT_CONNECT              = 2002;
    const int ERROR_GONE_AWAY                 = 2006;
    const int ERROR_DUPLICATE_ENTRY           = 1062;

    const int CONNECT_ATTEMPTS        = 16; #default number of attempts to connect
    const int DEADLOCK_RETRY_ATTEMPTS = 16; #default number of attempts to retry on deadlock
    const int SLEEP_RETRY_MIN         = 1; # min time for sleep between retries
    const int SLEEP_RETRY_MAX         = 3; # max time for sleep between retries

    public static string $lastSQL = ""; // last executed sql
    public static int $SQL_QUERY_CTR = 0; //counter for SQL queries in request
    public static ?self $instance = null;

    public ?mysqli $dbh = null; //mysqli object
    public array $config = []; //config
    #should contain:
    # DBNAME - database name
    # USER - db user
    # PWD - db password
    # HOST - db host
    # PORT - db port
    #can also contain:
    # CONNECT_ATTEMPTS - how many times to try to connect (default CONNECT_ATTEMPTS)
    # CONNECT_TIMEOUT - how many seconds to wait for connection (default 0 - no timeout)
    # WAIT_TIMEOUT - how many seconds to wait for query (default 0 - no timeout)
    # DEADLOCK_RETRY_ATTEMPTS - how many times to execute query+retry on deadlock (default DEADLOCK_RETRY_ATTEMPTS retries before exception, min=1 - no retries)
    # IS_LOG - if true - log all queries (default false)

    public int|string $lastRows; #affected_rows from last exec operations
    public bool $is_connected = false; #set to true if connect() were successful

    public static function NOW(): DBSpecialValue {
        return new DBSpecialValue('NOW()');
    }

    /**
     * split multiple sql statements by:
     * ;+newline
     * ;+newline+GO
     * newline+GO
     * @param string $sql
     * @return array
     */
    public static function splitMultiSQL(string $sql): array {
        $sql = preg_replace('/^--\s.*[\r\n]*/m', '', $sql); //first, remove lines starting with '-- ' sql comment
        return preg_split('/;[\n\r]+(?:GO[\n\r]*)?|[\n\r]+GO[\n\r]+/', $sql);
    }

    //if IS_LOG - external function logger() will be called for logging
    public function __construct($config = null) {
        global $CONFIG;
        if (is_null($config)) {
            $this->config = $CONFIG['DB']; //use site config, if config not passed explicitly
        } else {
            $this->config = $config;
        }

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); #Throw mysqli_sql_exception for errors instead of warning
    }

    /**
     * return singleton instance
     * @return DB
     */
    public static function i(): DB {
        if (!DB::$instance) {
            DB::$instance = new DB();
        }
        return DB::$instance;
    }

    /**
     * connect to sql server using config passed in constructor.
     * Also prepares connection params (MySQL: utf, sql mode).
     * Throw an exception if connection error occurs.
     * @return void
     * @throws DBException
     */
    public function connect(): void {
        $last_exception = null;

        $attempts = $this->config['CONNECT_ATTEMPTS'] ?? self::CONNECT_ATTEMPTS;
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
                #$this->query("SET SESSION sql_mode = ''"); #might be required fw to work on MySQL 5.5+

                $wait_timeout = $this->config['WAIT_TIMEOUT'] ?? 0;
                if ($wait_timeout > 0) {
                    $this->query("SET SESSION wait_timeout=" . dbqi($wait_timeout));
                }

                break;

            } catch (Exception $e) {
                #looks like for network connectivity issues we got ErrorException instead of mysqli_sql_exception - log some details for now
                #php_network_getaddresses: getaddrinfo failed: Temporary failure in name resolution
                #Error while reading greeting packet.
                $last_exception = $e;
                $this->logger('NOTICE', "Got Exception in connect()", "code=" . $e->getCode() . ", msg=" . $e->getMessage() . ", host=" . $this->config['HOST']);
                if ($e instanceof mysqli_sql_exception) {
                    if ($attempts > 0 && in_array($e->getCode(), [self::ERROR_TOO_MANY_CONNECTIONS, self::ERROR_TOO_MANY_CONNECTIONS_USER, self::ERROR_CANT_CONNECT, self::ERROR_GONE_AWAY])) {
                        // too many connections, connection timed out/no route to host, server has gone away,
                        $this->logger('NOTICE', "Attempting to reconnect", $e->getMessage());
                        sleep(rand(self::SLEEP_RETRY_MIN, self::SLEEP_RETRY_MAX)); #if got too many connections
                    } else {
                        break; #no repeat
                    }
                } else {
                    break; #no repeat
                }
            }
        }
        if ($last_exception) {
            $this->handleError($last_exception);
        }
    }

    /**
     * check connection and reconnect if necessary
     * @return void
     * @throws DBException
     */
    public function checkConnect(): void {
        $is_reconnect = !$this->is_connected || is_null($this->dbh);

        if (!$is_reconnect) {
            try {
                $is_reconnect = !@$this->dbh->ping(); #we don't need Warning: mysqli::ping(): MySQL server has gone away
            } catch (mysqli_sql_exception) {
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
    public function disconnect(): void {
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
    public function handleError(Exception $ex) {
        $err_str = '(' . $ex->getCode() . ') ' . $ex->getMessage();
        #$this->logger('ERROR', $ex); uncommenting this will duplicate error messages if errors logged at higher level
        throw new DBException($err_str, $ex->getCode());
    }

    /**
     * perform query and return result statement. Throws an exception if error occurred.
     * Note: on deadlock - automatically tries to repeat query couple times
     * @param string $sql SQL query
     * @param array|null $params optional, array of params for prepared queries (named or positional)
     * @return mysqli_result|bool
     * @throws DBException
     */
    public function query(string $sql, array $params = null): mysqli_result|bool {
        $result = null;
        $this->checkConnect();

        self::$lastSQL = $sql;
        DB::$SQL_QUERY_CTR++;

        $deadlock_attempts = $this->config['DEADLOCK_RETRY_ATTEMPTS'] ?? self::DEADLOCK_RETRY_ATTEMPTS; #max deadlock retry attempts
        $last_ex           = null;

        while ($deadlock_attempts--) {
            try {
                $last_ex = null;
                $result  = $this->queryInner($sql, $params);
                break;
            } catch (DBException $ex) {
                $last_ex = $ex;
                $err_msg = $ex->getMessage();
                if (preg_match("/deadlock/i", $err_msg)) {
                    $this->logger('NOTICE', "Sleep/retry on deadlock", "attempts left:" . $deadlock_attempts . $err_msg);
                    sleep(rand(self::SLEEP_RETRY_MIN, self::SLEEP_RETRY_MAX)); #if got deadlock - sleep 1-3s before repeat
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
     * perform query and return result statement. Throws an exception if error occurred.
     * @param string $sql SQL query
     * @param array|null $params optional, array of params for prepared queries (named or positional)
     * @return null|bool|mysqli_result  object
     * @throws DBException
     */
    public function queryInner(string $sql, array $params = null): null|bool|mysqli_result {
        $result = null;

        try {
            if (is_array($params) && count($params)) {
                //use prepared query

                //check if params are named or positional
                $pkeys = array_keys($params);
                if (array_keys($pkeys) !== $pkeys) {
                    // this is an associative array, so we have named parameters

                    $this->paramsExpand($sql, $params);
                    $this->loggerInner($sql, $params);

                    //Prepare SQL and Params for positional binding
                    $paramValues = [];
                    $newSql      = preg_replace_callback('/[@:](\w+)/', function ($matches) use (&$params, &$paramValues) {
                        $paramKey = $matches[1]; // This is the key extracted from SQL without any leading character

                        // Check all variants of the key - no leading, leading with ':', leading with  '@' in the params array
                        $prefixes = ['', ':', '@'];
                        foreach ($prefixes as $prefix) {
                            $actualKey = array_key_exists($prefix . $paramKey, $params) ? $prefix . $paramKey : null;
                            if ($actualKey !== null) {
                                break;
                            }
                        }
                        if ($actualKey === null) {
                            throw new Exception("DB->queryInner - Parameter '$matches[0]' not found in parameter list.");
                        }

                        $paramValues[] = $params[$actualKey]; // Add the corresponding value to the paramValues array
                        return '?'; // Replace the named placeholder with a question mark
                    }, $sql);

                } else {
                    //positional params
                    $newSql      = $sql;
                    $paramValues = $params;
                    $this->loggerInner($newSql, $paramValues);
                }

                // Execute the query with the positional parameters
                $result         = $this->dbh->execute_query($newSql, $paramValues);
                $this->lastRows = $this->dbh->affected_rows;

                #if non-query - returns false, no need to check result_metadata
                if ($result === FALSE) {
                    $result = null;
                }

            } else {
                //use direct query
                $this->loggerInner($sql);
                $result         = $this->dbh->query($sql);
                $this->lastRows = $this->dbh->affected_rows;
                #no need to check for metadata here as query returns TRUE for non-select
                #$this->handle_error($result);
            }
        } catch (Exception $e) {
            $this->handleError($e);
        }

        return $result;
    }

    /**
     * in case @params contains an array (example: [1,2,3]) - then sql query has something like "id IN (@ids)"
     * need to expand array into single params like: "id IN (@ids_0,@ids_1,@ids_2)"
     * @param string $sql
     * @param array $params
     * @return void
     */
    protected function paramsExpand(string &$sql, array &$params): void {
        foreach ($params as $p => $v) {
            if (is_array($v)) {
                $arrstr = [];
                foreach ($v as $i => $item) {
                    $pnew          = $p . "_" . $i;
                    $params[$pnew] = $item;
                    if ($i > 0) {
                        $arrstr[] = ',';
                    }
                    $arrstr[] = "@" . $pnew;
                }
                $sql = str_replace("@" . $p, implode('', $arrstr), $sql);
                unset($params[$p]);
            }
        }
    }

    /**
     * log sql with params before query execution. Params logged only if passed
     * @param string $sql
     * @param array|null $params
     * @return void
     */
    protected function loggerInner(string $sql, array $params = null): void {
        $host = $this->config['HOST'];
        #for logger - just leave first name in domain
        $dbhost_info = substr($host, 0, strpos($host, '.')) . '(' . $this->config['USER'] . '):' . $this->config['DBNAME'] . ' ';

        if ($params) {
            if (count($params) > 2) {
                // log as separate params if more than 2
                $this->logger('NOTICE', $dbhost_info . $sql, $params);
            } else {
                // more compact log if less than 3 params
                $this->logger('NOTICE', $dbhost_info . $sql . ' {' . implode(',', array_values($params)) . '}');
            }
        } else {
            $this->logger('NOTICE', $dbhost_info . $sql);
        }
    }

    /**
     * prepares sql and return prepared statement, use then in queryPrepared()
     * Throws an exception if error occurred.
     * @param string $sql sql query
     * @return mysqli_stmt prepared statement
     * @throws DBException
     */
    public function prepare(string $sql): mysqli_stmt {
        $this->checkConnect();

        $dbhost_info = $this->config['HOST'] . ':' . $this->config['DBNAME'] . ' ';
        $this->logger('NOTICE', $dbhost_info . 'PREPARE SQL: ' . $sql);

        try {
            $st = $this->dbh->prepare($sql);
        } catch (Exception $e) {
            $this->handleError($e);
        }

        return $st;
    }

    /**
     * executes previously prepared statement with params
     * Throws an exception if error occurred.
     * @param mysqli_stmt $st prepared statement using prepare()
     * @param array $params optional, array of params for prepared queries. note! for associative arrays assign key/values in the same order as for prepare()
     * @return null|array null for non-select queries, array of rows for select queries
     * @throws DBException
     */
    public function queryPrepared(mysqli_stmt $st, array $params): null|array {
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
            $this->handleError($e);
        }

        return $result;
    }

    /**
     * execute query without returning result set.
     * @param string $sql SQL query
     * @param array|null $params optional, array of params for prepared queries
     * @param bool $is_get_identity optional, if true - will return last inserted id
     * @return int number of affected rows or last inserted id
     * @throws DBException
     */
    public function exec(string $sql, array $params = null, bool $is_get_identity = false): int {
        $this->query($sql, $params);
        $this->lastRows = $this->dbh->affected_rows;
        if ($is_get_identity) {
            return $this->get_identity();
        } else {
            return $this->lastRows;
        }
    }

    /**
     * execute multiple sql statements from a single string (like file script)
     * Important! Use only to execute trusted scripts
     *
     * @param string $sql sql script text
     * @param bool $is_ignore_errors if true - if error happened, it's ignored and next statements executed anyway
     * @return int number of successfully executed statements
     * @throws DBException
     */
    public function execMultipleSQL(string $sql, bool $is_ignore_errors = false): int {
        $result = 0;

        //extract separate each sql statement
        $asql = self::splitMultiSQL($sql);
        foreach ($asql as $sqlone1) {
            $sqlone = trim($sqlone1);
            if (strlen($sqlone) > 0) {
                if ($is_ignore_errors) {
                    try {
                        $this->exec($sqlone);
                        $result += 1;
                    } catch (Exception $ex) {
                        $this->logger('WARN', $ex->getMessage());
                    }
                } else {
                    $this->exec($sqlone);
                    $result += 1;
                }
            }
        }
        return $result;
    }

    /**
     * read single first row from the table based on conditions/order
     * @param string $table table name
     * @param array $where array of (field => value) where conditions
     * @param string|null $order_by optional, order string to be added to ORDER BY
     * @return array<string, mixed>
     * @throws DBException
     */
    public function row(string $table, array $where, string $order_by = null): array {
        $qp = $this->buildSelect($table, $where, $order_by, 1);
        return $this->rowp($qp->sql, $qp->params);
    }

    /**
     * read single first row using parametrized sql query
     * @param string $sql sql query
     * @param array|null $params optional, array of params for prepared queries
     * @return array<string, mixed>
     * @throws DBException
     */
    public function rowp(string $sql, array $params = null): array {
        $res = $this->query($sql, $params);
        #we only need a first row from the result
        $row = $res->fetch_assoc();
        $res->free();
        return $row ?? [];
    }

    /**
     * return all rows with all(or selected) fields from the table based on conditions/order/limit
     * @param string $table
     * @param array $where
     * @param string|null $order_by optional, order string to be added to ORDER BY, field names should be already quoted
     * @param string|null $limit optional, limit string to be added to LIMIT, example: "10" or "10,20" (with offset)
     * @param array|string $select_fields optional, fields to select, default - all fields(*), can be array of unquoted fields or comma-separated already quoted string
     * @return array<int, array<string, mixed>>
     * @throws DBException
     */
    public function arr(string $table, array $where, string $order_by = null, string $limit = null, array|string $select_fields = '*'): array {
        if (is_array($select_fields)) {
            $select_fields = implode(',', array_map(fn($v) => $this->qid($v), $select_fields)); // quote all fields
        }
        $qp = $this->buildSelect($table, $where, $order_by, $limit, $select_fields);
        return $this->arrp($qp->sql, $qp->params);
    }

    /**
     * read all rows using parametrized query
     * @param string $sql
     * @param array|null $params
     * @return array<int, array<string, mixed>>
     * @throws DBException
     */
    public function arrp(string $sql, array $params = null): array {
        $res    = $this->query($sql, $params);
        $result = $res->fetch_all(MYSQLI_ASSOC);
        $res->free();
        return $result;
    }

    /**
     * return one value (first column or named column) from $sql or table/where/orderby
     * @param string $table table name to read from
     * @param array $where array of (field => value) where conditions
     * @param string|null $field_name field name to return. If omitted - first column fetched. Special case - "count(*),sum(field),avg,max,min", will return count/sum/...
     * @param string|null $order_by order string to be added to ORDER BY
     * @return string|null
     * @throws DBException
     */
    public function value(string $table, array $where, string $field_name = null, string $order_by = null): string|null {
        if (is_null($field_name)) {
            $field_name = '*';
        } elseif (str_starts_with($field_name, 'count(')) {
            //$field_name = $field_name; // for count - do not quote (there could be different variants like count(*), count(field), count(1))
        } elseif (preg_match('/^(\w+)\((\w+)\)$/', $field_name, $m)) {
            $field_name = $m[1] . '(' . $this->qid($m[2]) . ')'; // sum, avg, max, min
        } else {
            $field_name = $this->qid($field_name);
        }
        $qp = $this->buildSelect($table, $where, $order_by, 1, $field_name);
        return $this->valuep($qp->sql, $qp->params);
    }

    /**
     * return just first value from column
     * @param string $sql
     * @param array|null $params
     * @return string|null
     * @throws DBException
     */
    public function valuep(string $sql, array $params = null): string|null {
        $res    = $this->query($sql, $params);
        $result = $res->fetch_row();
        $res->free();
        return $result ? $result[0] : null;
    }

    /**
     * return one column of values (first column or named column) from $sql or table/where/orderby
     * @param string $table
     * @param array $where
     * @param string|null $field_name optional, field name to return. If ommited - first column fetched
     * @param string|null $order_by
     * @return array
     * @throws DBException
     */
    public function col(string $table, array $where, string $field_name = null, string $order_by = null): array {
        if (is_null($field_name)) {
            $field_name = '*';
        } else {
            $field_name = $this->qid($field_name);
        }
        $qp = $this->buildSelect($table, $where, $order_by, null, $field_name);
        return $this->colp($qp->sql, $qp->params);
    }

    /**
     * return one first column of values from $sql with params
     * @param string $sql
     * @param array|null $params
     * @return array
     * @throws DBException
     */
    public function colp(string $sql, array $params = null): array {
        $res    = $this->query($sql, $params);
        $result = $res->fetch_all();
        $res->free();
        return array_map(fn($v) => $v[0], $result);
    }

    /**
     * Build and execute raw select statement with offset/limit according to server type (MySQL for now)
     * !All parameters must be already properly enquoted
     * @param string $fields
     * @param string $from
     * @param string $where
     * @param array $where_params
     * @param string $orderby
     * @param int $offset
     * @param int $limit
     * @return array<int, array<string, mixed>>
     * @throws DBException
     */
    public function selectRaw(string $fields, string $from, string $where, array $where_params, string $orderby, int $offset = 0, int $limit = -1): array {
        $sql = "SELECT " . $fields . " FROM " . $from . " WHERE " . $where . " ORDER BY " . $orderby . " LIMIT " . $offset . ", " . $limit;
        return $this->arrp($sql, $where_params);
    }

    /**
     * build delete query and params
     * @param string $table
     * @param array $where
     * @param string|null $order_by
     * @param string|null $limit
     * @return DBQueryAndParams
     */
    public function buildDelete(string $table, array $where, string $order_by = null, string $limit = null): DBQueryAndParams {
        $result      = new DBQueryAndParams();
        $result->sql = 'DELETE FROM ' . $this->qid($table);
        if ($where) {
            $where_params   = $this->prepareParams($table, $where);
            $result->sql    .= ' WHERE ' . $where_params->sql;
            $result->params = $where_params->params;
        }
        if ($order_by) {
            $result->sql .= ' ORDER BY ' . $order_by;
        }
        if ($limit) {
            $result->sql .= ' LIMIT ' . $limit;
        }
        return $result;
    }

    /**
     * delete record(s) from db
     * @param string $table
     * @param array $where
     * @param string|null $order_by
     * @param string|null $limit
     * @return int number of affected rows
     * @throws DBException
     */
    public function delete(string $table, array $where, string $order_by = null, string $limit = null): int {
        $qp = $this->buildDelete($table, $where, $order_by, $limit);
        return $this->exec($qp->sql, $qp->params);
    }

    public function buildInsert(string $table, array $fields, array $options = []): DBQueryAndParams {
        $result      = new DBQueryAndParams();
        $result->sql = 'INSERT';
        if (array_key_exists('replace', $options)) {
            $result->sql = 'REPLACE';
        }

        $result->sql .= array_key_exists('ignore', $options) ? ' IGNORE' : '';
        $result->sql .= ' INTO ' . $this->qid($table);

        if (isset($fields[0]) && is_array($fields[0])) {
            // multi row mode

            foreach ($fields as $row) {
                $insert_params = $this->prepareParams($table, $row, 'insert');
                if (empty($result->fields)) {
                    $result->fields = $insert_params->fields;
                    $sql_fields     = implode(', ', array_map(fn($v) => $this->qid($v), $insert_params->fields));
                    $result->sql    .= ' (' . $sql_fields . ') VALUES ';
                }
                $result->sql    .= '(' . $insert_params->sql . '), ';
                $result->params = array_merge($result->params, $insert_params->params);
            }

        } else {
            // single row mode
            $insert_params = $this->prepareParams($table, $fields, 'insert');
            $sql_fields    = implode(', ', array_map(fn($v) => $this->qid($v), $insert_params->fields));

            $result->sql    .= ' (' . $sql_fields . ') VALUES (' . $insert_params->sql . ')';
            $result->params = $insert_params->params;
        }

        return $result;
    }

    /**
     * insert record(s) into db
     * @param string $table
     * @param array $fields - single record (field => value) or array of records (array of field => value arrays
     * @param array $options optional, options: [ignore=>true, replace=>true, no_identity=>true]
     * @return int last insert id or 0 (if no_identity option provided or multi-row insert)
     * @throws DBException
     */
    public function insert(string $table, array $fields, array $options = []): int {
        if (count($fields) == 0) {
            return 0; // nothing to insert
        }

        $is_get_identity = !array_key_exists('no_identity', $options);

        if (isset($fields[0]) && is_array($fields[0])) {
            // multi row mode
            $MAX_BIND_PARAMS = 2000; #let's set some limit
            $rows_per_exec   = floor($MAX_BIND_PARAMS / count($fields[0])); # group insert by this number of rows

            $rows = [];
            foreach ($fields as $row) {
                $rows[] = $row;
                if (count($rows) >= $rows_per_exec) {
                    $qp = $this->buildInsert($table, $rows, $options);
                    $this->exec($qp->sql, $qp->params, $is_get_identity);
                    $rows = [];
                }
            }
            if (count($rows) > 0) {
                $qp = $this->buildInsert($table, $rows, $options);
                return $this->exec($qp->sql, $qp->params, $is_get_identity);
            }
            return 0;

        } else {
            // single row mode
            $qp = $this->buildInsert($table, $fields, $options);
            return $this->exec($qp->sql, $qp->params, $is_get_identity);
        }
    }

    public function buildUpdate(string $table, array $fields, array $where): DBQueryAndParams {
        $result      = new DBQueryAndParams();
        $result->sql = 'UPDATE ' . $this->qid($table) . ' SET ';

        $qp_set      = $this->prepareParams($table, $fields, 'update', '_SET');
        $result->sql .= $qp_set->sql;

        if ($where) {
            $qp_where       = $this->prepareParams($table, $where);
            $result->sql    .= ' WHERE ' . $qp_where->sql;
            $result->params = array_merge($qp_set->params, $qp_where->params);
        }

        return $result;
    }

    /**
     * update record(s) in db with table/fields/where
     * @param string $table table name
     * @param array $fields assoc array of (field => value) to update
     * @param array $where array of (field => value) where conditions
     * @return int number of affected rows
     * @throws DBException
     */
    public function update(string $table, array $fields, array $where): int {
        $qp = $this->buildUpdate($table, $fields, $where);
        return $this->updatep($qp->sql, $qp->params);
    }

    /**
     * update record(s) in db with parametrized sql
     * @param string $sql
     * @param array|null $params
     * @return int number of affected rows
     * @throws DBException
     */
    public function updatep(string $sql, array $params = null): int {
        return $this->exec($sql, $params);
    }

    /**
     * update or insert record in db
     * @param string $table
     * @param array $fields
     * @param array $where
     * @return int get last inserted id or 0 if updated
     * @throws DBException
     */
    public function upsert(string $table, array $fields, array $where): int {
        // try to update first
        $result       = 0;
        $updated_rows = $this->update($table, $fields, $where);
        if ($updated_rows == 0) {
            // if no rows updated - insert
            $result = $this->insert($table, $fields);
        }
        return $result;
    }

    /**
     * return true if record exists or false if not. Optionally exclude check for other column/value
     * @param string $table_name table name
     * @param mixed $uniq_value value to check
     * @param string $column column name for uniq_value
     * @param string|null $not_id optional, not id to check
     * @param string $not_id_column optional, not id column name
     * @return bool                 true if record exists or false if not
     * @throws DBException
     */
    public function isRecordExists(string $table_name, mixed $uniq_value, string $column, string $not_id = null, string $not_id_column = 'id'): bool {
        $not_sql = '';
        $params  = array($uniq_value);
        if (!is_null($not_id)) {
            $not_sql  = ' AND ' . $this->qid($not_id_column) . '<>?';
            $params[] = $not_id;
        }
        $sql = 'SELECT 1 FROM ' . $this->qid($table_name) . ' WHERE ' . $this->qid($column) . '=?' . $not_sql . ' LIMIT 1';
        $val = $this->valuep($sql, $params);
        return $val == 1;
    }

    //************* helpers

    /**
     * prepare query and parameters
     * @param string $table table name
     * @param array $fields fields/values
     * @param string $join_type "where"(default), "update"(for SET), "insert"(for VALUES)
     * @param string $suffix optional suffix to append to each param name
     * @return DBQueryAndParams
     */
    public function prepareParams(string $table, array $fields, string $join_type = 'where', string $suffix = ''): DBQueryAndParams {
        if (count($fields) == 0) {
            return new DBQueryAndParams();
        }

        $is_for_insert  = $join_type == 'insert';
        $is_for_where   = $join_type == 'where'; // if for where "IS NULL" will be used instead of "=NULL"
        $join_delimiter = $is_for_where ? ' AND ' : ',';
        $fields_list    = [];
        $params_sqls    = [];
        $params         = [];

        foreach ($fields as $fname => $value) {
            #logger("prepareParams", $fname, "=", $value);
            $dbop = $this->field2Op($table, $fname, $value, $is_for_where);
            #logger("dbop:", $dbop);

            $delim      = ' ' . $dbop->opstr . ' ';
            $param_name = preg_replace('/\W/', '_', $fname) . $suffix; // replace any non-alphanum in param names and add suffix

            // for insert VALUES it will be form @p1,@p2,... i.e. without field names
            // for update/where we need it in form like "field="
            $sql = $is_for_insert ? '' : $fname . $delim;

            if ($dbop->is_value) {
                // if we have value - add it to params
                if ($dbop->op == DBOps::BETWEEN) {
                    // special case for between
                    $params[$param_name . '_1'] = $value[0];
                    $params[$param_name . '_2'] = $value[1];
                    // BETWEEN @p1 AND @p2
                    $sql .= '@' . $param_name . '_1 AND @' . $param_name . '_2';
                } elseif ($dbop->op == DBOps::IN || $dbop->op == DBOps::NOTIN) {
                    $sql_params = array();
                    $i          = 1;
                    foreach ($dbop->value as $pvalue) {
                        $params[$param_name . '_' . $i] = $pvalue;
                        $sql_params[]                   = '@' . $param_name . '_' . $i;
                        $i                              += 1;
                    }
                    // [NOT] IN (@p1,@p2,@p3...)
                    $sql .= '(' . (count($sql_params) > 0 ? implode(',', $sql_params) : 'NULL') . ')';
                } else {
                    if ($dbop->value instanceof DBSpecialValue) {
                        // if value is NOW object - don't add it to params, just use NOW()/GETDATE() in sql
                        $sql .= $dbop->value;
                    } elseif ($dbop->value instanceof DateTime) {
                        $params[$param_name] = $dbop->value->format('Y-m-d H:i:s');
                        $sql                 .= '@' . $param_name;
                    } else {
                        $params[$param_name] = $dbop->value;
                        $sql                 .= '@' . $param_name;
                    }
                }
                $fields_list[] = $fname; // only if field has a parameter - include in the list
            } else {
                $sql .= $dbop->sql; //if no value - add operation's raw sql if any
            }
            $params_sqls[] = $sql;
        }

        $result         = new DBQueryAndParams();
        $result->fields = $fields_list;
        $result->sql    = implode($join_delimiter, $params_sqls);
        $result->params = $params;
        return $result;
    }

    /*
     *     public DBOperation field2Op(string table, string field_name, object field_value_or_op, bool is_for_where = false)
    {
        DBOperation dbop;
        if (field_value_or_op is DBOperation dbop1)
            dbop = dbop1;
        else
        {
            // if it's equal - convert to EQ db operation
            if (is_for_where)
                // for WHERE xxx=NULL should be xxx IS NULL
                dbop = opEQ(field_value_or_op);
            else
                // for update SET xxx=NULL should be as is
                dbop = new DBOperation(DBOps.EQ, field_value_or_op);
        }

        return field2Op(table, field_name, dbop, is_for_where);
    }

    // return DBOperation class with value converted to type appropriate for the db field
    public DBOperation field2Op(string table, string field_name, DBOperation dbop, bool is_for_where = false)
    {
        connect();
        loadTableSchema(table);
        field_name = field_name.ToLower();
        Hashtable schema_table = (Hashtable)schema[table];
        if (!schema_table.ContainsKey(field_name))
        {
            throw new ApplicationException("field " + table + "." + field_name + " does not defined in FW.config(\"schema\") ");
        }

        string field_type = (string)schema_table[field_name];
        //logger(LogLevel.DEBUG, "field2Op IN: ", table, ".", field_name, " ", field_type, " ", dbop.op, " ", dbop.value);

        // db operation
        if (dbop.op == DBOps.IN || dbop.op == DBOps.NOTIN)
        {
            ArrayList result = new(((IList)dbop.value).Count);
            foreach (var pvalue in (IList)dbop.value)
                result.Add(field2typed(field_type, pvalue));
            dbop.value = result;
        }
        else if (dbop.op == DBOps.BETWEEN)
        {
            ((IList)dbop.value)[0] = field2typed(field_type, ((IList)dbop.value)[0]);
            ((IList)dbop.value)[1] = field2typed(field_type, ((IList)dbop.value)[1]);
        }
        else
        {
            // convert to field's type
            dbop.value = field2typed(field_type, dbop.value);
            if (is_for_where && dbop.value == DBNull.Value)
            {
                // for where if we got null value here for EQ/NOT operation - make it ISNULL/ISNOT NULL
                // (this could happen when comparing int field to empty string)
                if (dbop.op == DBOps.EQ)
                    dbop = opISNULL();
                else if (dbop.op == DBOps.NOT)
                    dbop = opISNOTNULL();
            }
        }

        return dbop;
    }
     */
    public function field2Op(string $table, string $field_name, mixed $field_value_or_op, bool $is_for_where = false): DBOperation {
        $dbop = $field_value_or_op instanceof DBOperation ? $field_value_or_op : new DBOperation(DBOps::EQ, $field_value_or_op);

        // $field_name = strtolower($field_name);
        //        $schema     = $this->loadTableSchema($table);
        //        if (!array_key_exists($field_name, $schema)) {
        //            throw new Exception("field $table.$field_name does not defined in FW.config(\"schema\") ");
        //        }
        //        $field_type = $schema[$field_name];
        $field_type = 'x';//TBD

        if ($dbop->op == DBOps::IN || $dbop->op == DBOps::NOTIN) {
            $result = array();
            foreach ($dbop->value as $pvalue) {
                $result[] = $this->field2typed($field_type, $pvalue);
            }
            $dbop->value = $result;
        } elseif ($dbop->op == DBOps::BETWEEN) {
            $dbop->value[0] = $this->field2typed($field_type, $dbop->value[0]);
            $dbop->value[1] = $this->field2typed($field_type, $dbop->value[1]);
        } else {
            $dbop->value = $this->field2typed($field_type, $dbop->value);
            if ($is_for_where && is_null($dbop->value)) {
                if ($dbop->op == DBOps::EQ) {
                    $dbop = $this->opISNULL();
                } elseif ($dbop->op == DBOps::NOT) {
                    $dbop = $this->opISNOTNULL();
                }
            }
        }

        return $dbop;
    }

    // TBD if needed
    public function field2typed(string $field_type, mixed $value): mixed {
        return $value;
    }

    //<editor-fold desc="DBOperation support">

    /**
     * EQUAL operation, basically the same as assigning value directly
     * But for null values - return ISNULL operation - equivalent to opISNULL()
     * Example: $rows = $db->arr("users", ["status", db.opEQ(0)])
     *          select * from users where status=0
     * @param mixed $value
     * @return DBOperation
     */
    public function opEQ(mixed $value): DBOperation {
        if (is_null($value)) {
            return $this->opISNULL();
        } else {
            return new DBOperation(DBOps::EQ, $value);
        }
    }

    /**
     * NOT EQUAL operation
     * Example: $rows = $db->arr("users", ["status", db.opNOT(127)])
     *          select * from users where status<>127
     * @param mixed $value
     * @return DBOperation
     */
    public function opNOT(mixed $value): DBOperation {
        return new DBOperation(DBOps::NOT, $value);
    }

    /**
     * LESS OR EQUAL than operation
     * Example: $rows = $db->arr("users", ["access_level", db.opLE(50)])
     *          select * from users where access_level<=50
     * @param mixed $value
     * @return DBOperation
     */
    public function opLE(mixed $value): DBOperation {
        return new DBOperation(DBOps::LE, $value);
    }

    /**
     * LESS than operation
     * Example: $rows = $db->arr("users", ["access_level", db.opLT(50)])
     *          select * from users where access_level<50
     * @param mixed $value
     * @return DBOperation
     */
    public function opLT(mixed $value): DBOperation {
        return new DBOperation(DBOps::LT, $value);
    }

    /**
     * GREATER OR EQUAL than operation
     * Example: $rows = $db->arr("users", ["access_level", db.opGE(50)])
     *          select * from users where access_level>=50
     * @param mixed $value
     * @return DBOperation
     */
    public function opGE(mixed $value): DBOperation {
        return new DBOperation(DBOps::GE, $value);
    }

    /**
     * GREATER than operation
     * Example: $rows = $db->arr("users", ["access_level", db.opGT(50)])
     *          select * from users where access_level>50
     * @param mixed $value
     * @return DBOperation
     */
    public function opGT(mixed $value): DBOperation {
        return new DBOperation(DBOps::GT, $value);
    }

    /**
     * Example: $rows = $db->array("users", ["field", db.opISNULL()])
     *          select * from users where field IS NULL
     * @return DBOperation
     */
    public function opISNULL(): DBOperation {
        return new DBOperation(DBOps::ISNULL, null);
    }

    /**
     * Example: $rows = $db->array("users", ["field", db.opISNOTNULL()])
     *          select * from users where field IS NOT NULL
     * @return DBOperation
     */
    public function opISNOTNULL(): DBOperation {
        return new DBOperation(DBOps::ISNOTNULL, null);
    }

    // continue for opLIKE, opNOTLIKE, opIN, opNOTIN, opBETWEEN, opNOTBETWEEN

    /**
     * LIKE operation
     * Example: $rows = $db->arr("users", ["fname", db.opLIKE("John")])
     *          select * from users where fname LIKE '%John%'
     * @param mixed $value
     * @return DBOperation
     */
    public function opLIKE(mixed $value): DBOperation {
        return new DBOperation(DBOps::LIKE, $value);
    }

    /**
     * NOT LIKE operation
     * Example: $rows = $db->arr("users", ["fname", db.opNOTLIKE("John")])
     *          select * from users where fname NOT LIKE '%John%'
     * @param mixed $value
     * @return DBOperation
     */
    public function opNOTLIKE(mixed $value): DBOperation {
        return new DBOperation(DBOps::NOTLIKE, $value);
    }

    /**
     * IN operation
     * Example: $rows = $db->arr("users", ["id", db.opIN([1,2,3])])
     *          select * from users where id IN (1,2,3)
     * @param mixed $value
     * @return DBOperation
     */
    public function opIN(mixed $value): DBOperation {
        return new DBOperation(DBOps::IN, $value);
    }

    /**
     * NOT IN operation
     * Example: $rows = $db->arr("users", ["id", db.opNOTIN([1,2,3])])
     *          select * from users where id NOT IN (1,2,3)
     * @param mixed $value
     * @return DBOperation
     */
    public function opNOTIN(mixed $value): DBOperation {
        return new DBOperation(DBOps::NOTIN, $value);
    }

    /**
     * BETWEEN operation
     * Example: $rows = $db->arr("users", ["age", db.opBETWEEN([18, 25])])
     *          select * from users where age BETWEEN 18 AND 25
     * @param mixed $value
     * @return DBOperation
     */
    public function opBETWEEN(mixed $value): DBOperation {
        return new DBOperation(DBOps::BETWEEN, $value);
    }
    //</editor-fold>

    /**
     * convert array of values to quoted string for IN sql query
     * to use with IN sql queries with proper quoting, ex:
     *      $sql=" AND `sender` ".$this->insql($scopes);
     *      note: if $values array empty the following sql returned: " IN (NULL) "
     * @param array $values
     * @return string " IN (1,2,3)" sql or IN (NULL) if empty params passed
     */
    public function insql(array $values): string {
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
    public function insqli(array $values): string {
        #quote first
        $arr = array();
        foreach ($values as $value) {
            $arr[] = intval($value);
        }
        $sql = ($arr ? implode(",", $arr) : "NULL");
        #return sql
        return ' IN (' . $sql . ') ';
    }

    /**
     * build parametrized SELECT sql query for given table/where/order/limit
     * @param string $table table name
     * @param array $where optional, where assoc array
     * @param string|null $order_by optional, string to append to ORDER BY
     * @param string|null $limit optional, string to append to LIMIT
     * @param string $select_fields comma separated fields to select or '*'
     * @return DBQueryAndParams
     */
    public function buildSelect(string $table, array $where, string $order_by = null, string $limit = null, string $select_fields = "*"): DBQueryAndParams {
        $result = new DBQueryAndParams();

        $result->sql = "SELECT " . $select_fields . " FROM " . $this->qid($table);

        if (count($where) > 0) {
            $qp = $this->prepareParams($table, $where);
            if ($qp->sql > '') {
                $result->sql    .= ' WHERE ' . $qp->sql;
                $result->params = $qp->params;
            }
        }

        if ($order_by > '') {
            $result->sql .= ' ORDER BY ' . $order_by;
        }
        if ($limit > '') {
            $result->sql .= ' LIMIT ' . $limit;
        }

        return $result;
    }

    /**
     * #alias for qid
     * @param string $table_name table name
     * @return string             quoted table name
     * @deprecated use qid()
     */
    public function quote_ident(string $table_name): string {
        return $this->qid($table_name);
    }

    /**
     * alias for quote_ident
     * @param string $table_name
     * @return string
     * @deprecated use qid()
     */
    public function qident(string $table_name): string {
        return $this->qid($table_name);
    }

    /**
     * quote table name with `` for MySQL
     * TODO - support of different types of SQL_SERVER quotes
     * @param string $table_name
     * @return string
     */
    public function qid(string $table_name): string {
        $table_name = str_replace("`", "", $table_name); #mysql names shouldn't contain ` !
        return '`' . $table_name . '`';
    }

    #alias for quote
    public function q($value, $field_type = ''): string {
        return $this->quote($value, $field_type);
    }

    public function quote($value, $field_type = ''): string {
        $this->checkConnect();

        if ($field_type == 'x') {
            $result = $value;
        } elseif ($field_type == 's') {
            $result = "'" . $this->dbh->real_escape_string($value) . "'";
        } elseif ($field_type == 'i') {
            $result = intval($value);
        } elseif (is_null($value)) {
            //null value
            $result = 'NULL';
        } elseif ($value instanceof DBSpecialValue) {
            $result = $value;
        } else {
            $result = "'" . $this->dbh->real_escape_string($value) . "'"; //real_escape_string doesn't add '' at begin/end
        }

        return $result;
    }

    /**
     * get last inserted id
     * @return int  last inserted id or 0
     */
    public function get_identity(): int {
        return $this->dbh->insert_id;
    }

    /**
     * return list of tables in db
     * @return array plain array of table names
     * @throws DBException
     */
    public function tables(): array {
        return $this->colp("show tables");
    }

    public function tableSchema($table_name): array {
        $rows = $this->arrp("SELECT
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
            where c.TABLE_SCHEMA=@TABLE_SCHEMA
              and c.TABLE_NAME=@TABLE_NAME
            ", [
            '@TABLE_SCHEMA' => $this->config['DBNAME'],
            '@TABLE_NAME'   => $table_name
        ]);
        foreach ($rows as &$row) {
            $row["fw_type"]    = $this->mapTypeSQL2Fw($row["type"]);
            $row["fw_subtype"] = strtolower($row["type"]);
        }
        unset($row);

        return $rows;
    }

    public function mapTypeSQL2Fw($type): string {
        return match (strtolower($type)) {
            "tinyint", "smallint", "int", "bigint", "bit" => "int",
            "real", "numeric", "decimal", "money", "smallmoney", "float" => "float",
            "datetime", "datetime2", "date", "smalldatetime" => "datetime",
            default => "varchar",
        };
    }

    /***
     * load table schema from db
     * @param string $table
     * @return array field_name => fw_type
     */
    public function loadTableSchema(string $table): array {
        $fields = $this->tableSchema($table);
        $result = [];
        foreach ($fields as $field) {
            $result[strtolower($field['name'])] = $field['fw_type'];
        }
        return $result;
    }


    public function schemaFieldType(string $table, string $field_name): string {
        $schema     = $this->loadTableSchema($table);
        $field_name = strtolower($field_name);
        if (!array_key_exists($field_name, $schema)) {
            return "";
        }
        $field_type = $schema[$field_name];

        $result = "";
        if (str_contains($field_type, "int")) {
            $result = "int";
        } elseif ($field_type == "datetime") {
            $result = $field_type;
        } elseif ($field_type == "float") {
            $result = $field_type;
        } elseif ($field_type == "decimal") {
            $result = $field_type;
        } else {
            $result = "varchar";
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
