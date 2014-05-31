<?php

/**
 * power by rizalsmarts
 */
namespace DataBase;

class DbConn extends \PDO
{
    const FETCH_FROM_NEXT_ROW = 0;
    const FETCH_FROM_LAST_ROW = 1;

    const INSERT  = "INSERT INTO";
    const UPDATE  = "UPDATE";
    const REPLACE = "REPLACE";

    private static $config    = array();
    private static $instances = array();

    private static $exception_callback;

    private $prev_stmt        = array();
    private $prev_columns     = array();
    private $check_columns    = false;

    public $fetch_table_names = 0;

    public function __construct(
        string $dsn      = null,
        string $username = null,
        string $password = null,
        array  $options  = array()
    ) {
        // Default options
        $options = $options + array(
            \PDO::ATTR_STATEMENT_CLASS => array(
                "database\\Statement",
                array($this)
            ),
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        );

        try {
            // @ untuk memaksa mengabaikan warning PDOException.
            @parent::__construct($dsn, $username, $password, $options);
        } catch (\Exception $e) {
            if (null !== self::$exception_callback
                && is_callable(self::$exception_callback)) {
                call_user_func_array(
                    self::$exception_callback,
                    array($e)
                );
            } else {
                throw $e;
            }
        }
    }

    public static function getInstance($instance = 'default')
    {
        if(!array_key_exists($instance, self::$instances)) {
            if(!array_key_exists($instance, self::$config)) {
                throw new \Exception(
                    "Configuration is not set. "
                    ."Use DB::setConfig(options, [instance]) to set"
                );
            }

            self::$instances[$instance] = new self(
                self::$config[$instance]["dsn"],
                self::$config[$instance]["username"],
                self::$config[$instance]["password"],
                self::$config[$instance]["options"]
            );
        }

        return self::$instances[$instance];
    }

    public static function setConfig($config, $instance = 'default')
    {
        self::$config[$instance]['dsn']      = array_key_exists(
            'dsn',
            $config
        ) ? $config['dsn'] : "";
        self::$config[$instance]['username'] = array_key_exists(
            'username',
            $config
        ) ? $config['username'] : null;
        self::$config[$instance]['password'] = array_key_exists(
            'password',
            $config
        ) ? $config['password'] : null;
        self::$config[$instance]['options']  = array_key_exists(
            'options',
            $config
        ) ? $config['options'] : array();
    }

    private function checkColumns($set = null)
    {
        if ($set !== null) {
            $this->check_columns = $set;
        }

        return $this->check_columns;
    }

    private function removeNonExistentColumns(
        $table,
        &$data,
        $stmt_key = null
    ) {
        if ($this->check_columns) {

            // use previous columns or get new
            if (!empty($stmt_key)
                && empty($this->prev_columns[$stmt_key])) {

                $this->prev_columns[$stmt_key] = $this->getColumnsFromTable($table);
                $columns = $this->prev_columns[$stmt_key];

            } elseif (!empty($stmt_key)
                && !empty($this->prev_columns[$stmt_key])) {

                $columns = $this->prev_columns[$stmt_key];

            } else {
                $columns = $this->getColumnsFromTable($table);
            }

            $new_data = array();
            foreach ($columns as $column) {
                if (array_key_exists($column, $data)) {
                    $new_data[$column] = $data[$column];
                }
            }
            $data = $new_data;
        }
        return $data;
    }

    private function getSetStmt($syntax, $table, $data, $where = null)
    {
        $columns = array();

        foreach (array_keys($data) as $column) {
            $columns[] = "`" . $column . "` = ?";
        }
        $columns = implode(", ", $columns);

        $sql = "$syntax `$table` SET "
            . $columns . $this->buildWhere($where);

        return $this->prepare($sql);
    }

    private function executeBySyntax(
        $syntax,
        $table,
        $data,
        $where = null,
        $where_params = array(),
        $stmt_key = null
    ) {
        if (!is_null($where) && !is_array($where)) {
            $where = array($where);
        }

        if (is_object($data)) {
            $data = (array) $data;
        }

        $data = $this->removeNonExistentColumns(
            $table, $data, $stmt_key
        );

        if (!is_array($where_params)) {
            $where_params = array($where_params);
        }

        if (empty($stmt_key)) {
            $stmt = $this->getSetStmt($syntax, $table, $data, $where);
        } elseif (empty($this->prev_stmt[$stmt_key])) {
            $stmt = $this->getSetStmt($syntax, $table, $data, $where);
            $this->prev_stmt[$stmt_key] = $stmt;
        } else {
            $stmt = $this->prev_stmt[$stmt_key];
        }

        $stmt->execute(array_merge(array_values($data), $where_params));

        return $stmt;
    }

    public function insert($table, $data, $stmt_key = null)
    {
        return $this->executeBySyntax(
            self::INSERT,
            $table,
            $data,
            null,
            array(),
            $stmt_key
        );
    }

    public function update(
        $table,
        $data,
        $where,
        $where_params = array(),
        $stmt_key = null
    ) {
        return $this->executeBySyntax(
            self::UPDATE,
            $table,
            $data,
            $where,
            $where_params,
            $stmt_key
        );
    }

    public function replace($table, $data, $stmt_key = null)
    {
        return $this->executeBySyntax(
            self::REPLACE,
            $table,
            $data,
            null,
            array(),
            $stmt_key
        );
    }

    public function delete($table, $where, $where_params)
    {
        $sql  = "DELETE FROM " . $table . $this->buildWhere($where);
        $stmt = $this->executeQuery($sql, $where_params);

        return $stmt;
    }

    public function count($table, $where, $where_params = null)
    {
        $sql  = "SELECT COUNT(*) FROM "
            . $table . $this->buildWhere($where);
        $stmt = $this->executeQuery($sql, $where_params);

        return $stmt->fetchColumn();
    }

    public function executeQuery($sql, $params = null)
    {
        return $this->execQueryString($sql, $params);
    }

    public function execQueryString($sql, $params = null)
    {
        if (!is_array($params) && !is_null($params)) {
            $params = array($params);
        }

        $stmt = $this->prepare($sql);
        $stmt->execute($params);

        return $stmt;
    }

    public function execQuery(Query $query)
    {
        return $this->execQueryString(
            $query->getQuery(),
            $query->getParams()
        );
    }

    public function buildWhere($where, $operand = "AND")
    {
        if (empty($where)) {
            return "";
        }

        if (is_array($where)) {
            $wheres = array();
            foreach ($where as $k => $w) {
                $wheres[] = "(" . $w . ")";
            }
            $where = implode(" $operand ", $wheres);
        }

        return " WHERE " . $where;
    }

    public function createQuery()
    {
        return new Query($this);
    }

    public function select($statement = "")
    {
        return $this->createQuery()->select($statement);
    }

    public function getColumnsFromTable($table)
    {
        $sql = "DESCRIBE $table";

        return $this->executeQuery($sql)
            ->fetchAll(self::FETCH_COLUMN);
    }

    public function save($table, $data, $primary_key, $stmt_key = null)
    {
        // Update if not empty primary key in data set / insert new row
        if (!empty($data[$primary_key])) {
            return $this->update(
                $table,
                $data,
                $primary_key . " = ?",
                $data[$primary_key],
                $stmt_key
            );
        } else {
            return $this->insert($table, $data, $stmt_key);
        }
    }

    public function setFetchTableNames($option = 1)
    {
        $this->setAttribute(self::ATTR_FETCH_TABLE_NAMES, $option);
        $this->fetch_table_names = $option;
    }

    public static function registerExceptionCallback($callback)
    {
        self::$exception_callback = $callback;
    }
}
