<?php
namespace DataBase;

class Query
{
    protected $select = array();
    protected $from = array();
    protected $where = array();
    protected $having = array();
    protected $join = array();
    protected $params = array();
    protected $orderBy = array();
    protected $groupBy = array();
    protected $limit = "";

    protected $dbConn;

    public function __construct(DbConn $dbConn = null)
    {
        $this->dbConn = $dbConn;
    }

    public function select($statement)
    {
        $this->select[] = $statement;

        return $this;
    }

    public function from($statement)
    {
        $this->from[] = $statement;

        return $this;
    }

    public function where($statement, $params = null)
    {
        $this->where[] = $statement;
        $this->addParams($params);

        return $this;
    }

    public function whereIn($column, $params)
    {
        $this->prepareWhereInStatement($column, $params, false);
        $this->addParams($params);

        return $this;
    }

    public function whereNotIn($column, $params)
    {
        $this->prepareWhereInStatement($column, $params, true);

        return $this;
    }

    public function having($statement, $params = null)
    {
        $this->having[] = $statement;
        $this->addParams($params);

        return $this;
    }

    public function join($statement)
    {
        $this->join[] = $statement;

        return $this;
    }

    public function groupBy($statement)
    {
        $this->groupBy[] = $statement;

        return $this;
    }

    public function orderBy($statement)
    {
        $this->orderBy[] = $statement;

        return $this;
    }

    public function limit($limit, $offset = null)
    {
        $this->limit = '';

        if(!is_null($offset)) {
            $this->limit = $offset . ', ';
        }

        $this->limit .= $limit;

        return $this;
    }

    public function getQuery()
    {
        $sql = $this->prepareSelectString();
        $sql .= $this->prepareJoinString();
        $sql .= $this->prepareWhereString();
        $sql .= $this->prepareGroupByString();
        $sql .= $this->prepareHavingString();
        $sql .= $this->prepareOrderByString();
        $sql .= $this->prepareLimitString();

        return $sql;
    }

    private function prepareSelectString()
    {
        if (empty($this->select)) {
            $this->select("*");
        }

        return "SELECT " . implode(", ", $this->select)
            . " FROM " . implode(", ", $this->from) . " ";
    }

    public function execute()
    {
        if ($this->db === null) {
            $this->db = DB::getInstance();
        }

        return $this->db->execQuery($this);
    }

    public function clearSelect()
    {
        $this->select = array();

        return $this;
    }

    public function clearGroupBy()
    {
        $this->groupBy = array();

        return $this;
    }

    public function addParams($params)
    {
        if (is_null($params)) {
            return;
        }

        if (!is_array($params)) {
            $params = array($params);
        }

        $this->params = array_merge($this->params, $params);
    }

    public function getParams()
    {
        return $this->params;
    }

    private function prepareWhereInStatement(
        $column,
        $params,
        $not_in = false
    ) {
        $qm = array_fill(0, count($params), "?");
        $in = ($not_in) ? "NOT IN" : "IN";
        $this->where[] = $column . " " . $in
            . " (" . implode(", ", $qm) . ")";
    }

    private function prepareJoinString()
    {
        if (!empty($this->join)) {
            return implode(" ", $this->join) . " ";
        }

        return '';
    }

    private function prepareWhereString()
    {
        if (!empty($this->where)) {
            return "WHERE " . implode(" AND ", $this->where) . " ";
        }

        return '';
    }

    private function prepareGroupByString()
    {
        if (!empty($this->groupBy)) {
            return "GROUP BY " . implode(", ", $this->groupBy) . " ";
        }

        return '';
    }

    private function prepareHavingString()
    {
        if (!empty($this->having)) {
            return "HAVING " . implode(", ", $this->having) . " ";
        }

        return '';
    }

    private function prepareOrderByString()
    {
        if (!empty($this->orderBy)) {
            return "ORDER BY " . implode(", ", $this->orderBy) . " ";
        }

        return '';
    }

    private function prepareLimitString()
    {
        if (!empty($this->limit)) {
            return "LIMIT " . $this->limit;
        }

        return '';
    }
}
