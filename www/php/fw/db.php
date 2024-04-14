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
 * @param array $params optional, array of params for prepared queries
 * @return void
 * @throws DBException
 */
function db_exec($sql, $params = null): void {
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
 * delete record(s) from db
 * @param string $table table name to delete from
 * @param string $value id value
 * @param string $column optional, column name for value, default = 'id'
 * @param string|array $more_where additonal where for delete
 * @return void
 */
function db_delete(string $table, string $value, string $column = 'id', string|array $more_where = ''): void {
    DB::i()->delete($table, $value, $column, $more_where);
}

/**
 * insert or replace record into db
 * @param string $table table name
 * @param array $vars assoc array of fields/values to insert
 * @param array $options optional, options: ignore, replace, no_identity
 * @return int              last insert id or null (if no_identity option provided)
 */
function db_insert(string $table, array $vars, array $options = array()): int {
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
 * @return int number of affected rows
 */
function db_update(string $table, array $vars, $key_id, $column = 'id', $more_set = '', $more_where = ''): int {
    return DB::i()->update($table, $vars, $key_id, $column, $more_set, $more_where);
}

/**
 * return true if record exists or false if not. Optionally exclude check for other column/value
 * @param string $table_name table name
 * @param string $uniq_value value to check
 * @param string $column optional, column name for uniq_value
 * @param string|null $not_id optional, not id to check
 * @param string $not_id_column optional, not id column name
 * @return bool                 true if record exists or false if not
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
            default:
                //Throw New ApplicationException("Wrong DB OP")
                break;
        }
    }
}

/**
 * helper structure - describes query and params
 */
class DBQueryAndParams {
    public array $fields = []; // list of parametrized fields in order
    public string $sql = ''; // sql with parameter names, ex: field=@field
    public array $params = []; // parameter name => value, ex: field => 123
}

/**
 * DBSpecialValue class - special values for DB operations like 'NOW()'
 */
class DBSpecialValue {
    private $value;

    public function __construct($value) {
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

    const string NOTNULL        = '###special_case_value_for_not_null###'; #TODO REMOVE
    const string MORE_THAN_ZERO = '###special_case_value_for_more_than_zero###'; #TODO REMOVE

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
                $this->logger('NOTICE', "Got Exception in connect()", "code=" . $e->getCode() . ", msg=" . $e->getMessage() . ", host=" . $this->config['HOST'], $e);
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
                    $this->logger('NOTICE', "Sleep/retry on deadlock", "attempts left:" . $deadlock_attempts, $err_msg);
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
        $host   = $this->config['HOST'];
        #for logger - just leave first name in domain
        $dbhost_info = substr($host, 0, strpos($host, '.')) . '(' . $this->config['USER'] . '):' . $this->config['DBNAME'] . ' ';

        try {
            if (is_array($params) && count($params)) {
                //use prepared query
                $this->logger('NOTICE', $dbhost_info . $sql, $params);

                //check if params are named or positional
                $pkeys = array_keys($params);
                if (array_keys($pkeys) !== $pkeys) {
                    // this is an associative array, so we have named parameters
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
                            throw new Exception("DB->queryInner - Parameter '{$matches[0]}' not found in parameter list.");
                        }

                        $paramValues[] = $params[$actualKey]; // Add the corresponding value to the paramValues array
                        return '?'; // Replace the named placeholder with a question mark
                    }, $sql);

                } else {
                    //positional params
                    $newSql      = $sql;
                    $paramValues = $params;
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
                $this->logger('NOTICE', $dbhost_info . $sql);

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

    public function row(string $table, array $where, string $order_by = null): array {
        $qp = $this->buildSelect($table, $where, $order_by, 1);
        return $this->rowp($qp->sql, $qp->params);
    }

    /**
     * read single first row using parametrized sql query
     * @param string $sql sql query
     * @param array|null $params optional, array of params for prepared queries
     * @return array
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
     * @return array
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
     * @return array
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
        $result = $res->fetch_all(MYSQLI_NUM);
        $res->free();
        return array_map(fn($v) => $v[0], $result);
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
        $where = $this->qid($column) . (is_array($value) ? $this->insql($value) : '=' . $this->quote($value));
        if ($more_where) {
            $where .= ' AND ' . $this->build_where_str($more_where);
        }

        $sql = 'DELETE FROM ' . $this->qid($table) . ' WHERE ' . $where;
        $this->exec($sql);
    }

    //TODO make this main delete() method
    public function deleteWhere($table, $vars, $more_where = '') {
        $where = $this->build_where_str($vars);
        if ($more_where) {
            $where .= ' AND ' . $this->build_where_str($more_where);
        }

        $sql = 'DELETE FROM ' . $this->qid($table) . ' WHERE ' . $where;
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

        $sql_insert = $sql_command . $sql_ignore . ' INTO ' . $this->qid($table);

        if (isset($vars[0]) && is_array($vars[0])) {
            #multi row mode
            $MAX_BIND_PARAMS = 2000; #let's set some limit
            $rows_per_query  = floor($MAX_BIND_PARAMS / count($vars[0]));
            $anames          = $row_values = $avalues = $params = array();
            $is_anames_set   = false;

            foreach ($vars as $i => $row) {
                foreach ($row as $k => $v) {
                    if (!$is_anames_set) {
                        $anames[]     = $this->qid($k);
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
     * @return int number of affected rows
     */
    public function update($table, $vars, $key_id_or_where, $column = 'id', $more_set = '', $more_where = ''): int {
        list($sql_set, $params_set) = $this->quote_array_params($vars);

        //detect syntax
        if (is_array($key_id_or_where)) {
            //syntax 2
            list($sql_where, $params_where) = $this->quote_array_params($key_id_or_where, true);
            $sql = 'UPDATE ' . $this->qid($table) . ' SET ' . implode(', ', $sql_set);
            if ($sql_where) {
                #if we have non-empty where
                $sql .= ' WHERE ' . implode(' AND ', $sql_where);
            }
            $this->exec($sql, array_merge($params_set, $params_where));
        } else {
            //syntax 1
            $sql = 'UPDATE ' . $this->qid($table) . ' SET ' . implode(', ', $sql_set) . ' ' . $more_set;
            if (strlen($key_id_or_where) > 0) {
                $sql .= ' WHERE ' . $this->qid($column) . '=' . $this->quote($key_id_or_where) . ' ' . $more_where;
            }
            $this->exec($sql, $params_set);
        }

        return $this->lastRows;
    }

    /**
     * update or insert record in db
     * @param string $table
     * @param array $fields
     * @param array $where
     * @return int get last inserted id or 0 if updated
     */
    public function updateOrInsert(string $table, array $fields, array $where): int {
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
                $quoted[] = $this->qid($key) . '=' . $this->quote($value);
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
                if ($value instanceof DBSpecialValue) {
                    $quoted[] = $this->qid($key) . $value;
                } elseif (is_null($value)) {
                    $quoted[] = $this->qid($key) . ($is_where ? ' IS NULL' : '=NULL');
                } elseif ($value === DB::NOTNULL) {
                    $quoted[] = $this->qid($key) . ($is_where ? ' IS NOT NULL' : '!=NULL');
                } elseif ($value === DB::MORE_THAN_ZERO) {
                    $quoted[] = $this->qid($key) . ' > 0';
                } else {
                    $quoted[] = $this->qid($key) . '=?';
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
            list($where_quoted, $params) = $this->quote_array_params($where, true);
            if (count($where_quoted)) {
                $result->sql    .= ' WHERE ' . implode(' AND ', $where_quoted);
                $result->params = $params;
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

        $result = '';
        if ($field_type == 'x') {
            $result = $value;
        } elseif ($field_type == 's') {
            $result = "'" . $this->dbh->real_escape_string($value) . "'";
        } elseif ($field_type == 'i') {
            $result = intval($value);
        } elseif (is_null($value)) {
            //null value
            $result = 'NULL';
        } elseif ($value === DB::NOTNULL) {
            //null value
            throw new DBException("Impossible use of NOTNULL");
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
    public function get_identity() {
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

    public function table_schema($table_name): array {
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
