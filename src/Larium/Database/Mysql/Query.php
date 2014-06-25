<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace Larium\Database\Mysql;

use Larium\Database\QueryInterface;

/**
 * Query builder Adapter for Mysql database.
 *
 * Allows easy build of mysql queries.
 *
 */
class Query implements QueryInterface
{
    const HYDRATE_ARRAY = 2;

    const HYDRATE_OBJ = 5;

    /**
     * Available aggregate functions for MySQL
     *
     * Used from {@link having()} method to validate if the aggregate
     * function provided by user exists.
     *
     * @var array
     */
    private $AGGREGATE = array(
        'AVG',
        'MAX',
        'MIN',
        'SUM',
        'COUNT',
        'BIT_AND',
        'BIT_OR',
        'BIT_XOR',
        'GROUP_CONCAT',
        'STD',
        'STDDEV_POP',
        'STDDEV_SAMP',
        'STDDEV',
        'VAR_POP',
        'VAR_SAMP',
        'VARIANCE',
    );

    /**
     * The class name to use for fetching results.
     *
     * @var string
     */
    protected $object = '\\stdClass';

    protected $select;

    protected $table;

    protected $alias;

    protected $where=array();

    protected $bind_params = array();

    protected $limit;

    protected $offset;

    protected $having;

    protected $group_by;

    protected $order_by;

    protected $aggregate=array();

    protected $query;

    protected $real_query;

    protected $join_conditions;

    protected $adapter;

    /**
     * Creates a Query object to perform Mysql queries.
     *
     * @param string           $object The class name to use for fetching results.
     * @param AdapterInterface $adapter The adapter instance with a database connection.
     */
    public function __construct($object=null, $adapter=null)
    {
        $this->object = $object;
        $this->adapter = $adapter;
    }

    public function toSql()
    {
        return $this->query ?: $this->build_sql();
    }

    public function toRealSql()
    {
        $sql = $this->toSql();
        $params = $this->getBindParams();
        foreach ($params as $value) {
            $this->adapter->sanitize($value);
            $sql = preg_replace('|\?|', $value, $sql, 1);
        }
        return $sql;
    }

    public function getBindParams()
    {
        return $this->bind_params;
    }

    public function setObject($object)
    {
        $this->object = $object;
    }

    public function getObject()
    {
        return $this->object;
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getTableAlias()
    {
        if (isset($this->alias[$this->getTable()])) {
            return $this->alias[$this->getTable()];
        }

        return $this->table;
    }

    /*- (Mysql JOIN) ---------------------------------------------------- */

    public function innerJoin(
        $join_table,
        $primary_key,
        $foreign_key,
        $additional = null
    ) {
        $this->join_query("INNER", $join_table, $primary_key, $foreign_key, $additional);
        return $this;
    }

    public function leftJoin($join_table, $primary_key, $foreign_key, $additional = null)
    {
        $this->join_query("LEFT", $join_table, $primary_key, $foreign_key, $additional);
        return $this;
    }

    private function join_query(
        $join_type,
        $join_table,
        $primary_key,
        $foreign_key,
        $additional = null
    ) {
        list($pri_table, $pri_field) = explode('.', $primary_key);
        list($for_table, $for_field) = explode('.', $foreign_key);
        $as = null;
        if (false !== strpos($join_table, 'as')) {
            list($join_table, $as) = explode('as', $join_table);
            $join_table = trim($join_table);
            $as = trim($as);
            $as = " as {$as}";
        }
        $join = "{$join_type} JOIN `{$join_table}`{$as} ON (`{$pri_table}`.{$pri_field} = `{$for_table}`.{$for_field}";
        $join .= $additional ? " {$additional})" : ")";
        $this->join_conditions[] = $join;
    }

    /*- (Mysql methods) ---------------------------------------------------- */

    /**
     * Select fields from a table.
     *
     * @param array|string $args the fields to select in array or comma(,)
     *                           seperated string
     */
    public function select($args="*")
    {
        $args = is_array($args) ? implode(',', $args) : $args;

        empty($this->select)
            ? $this->select .= $args
            : $this->select .= ", " . $args;

        return $this;
    }

    public function clearSelect()
    {
        $this->select = null;

        return $this;
    }

    public function clearGroup()
    {
        $this->group_by = null;

        return $this;
    }

    public function from($table, $alias = null)
    {
        $this->table = $table;

        if ($alias) {
            $this->alias[$table] = $alias;
        }

        return $this;
    }
    public function groupBy($args)
    {
        $this->group_by = $args;

        return $this;
    }

    public function orderBy($field, $order)
    {
        $this->order_by = null === $this->order_by
            ? "{$field} ${order}"
            : $this->order_by . ", {$field} ${order}";

        return $this;
    }

    public function __call($name, $args)
    {
        if (in_array(strtoupper($name), $this->AGGREGATE)) {
            return $this->aggregate($name, $args[0], isset($args[1]) ? $args[1] : null);
        }
        throw new \Exception("Invalid method ".get_class($this)."::$name");
    }

    protected function aggregate($function, $field, $as = null)
    {
        $function = strtoupper($function);
        $this->aggregate[] = array(
            'function' => $function,
            'field'    => $field,
            'as'       => $as
        );

        return $this;
    }

    public function limit($count, $offset=false)
    {
        $this->limit = (int) $count;
        if ($offset !== false || null === $this->offset) {
            $this->offset($offset);
        }
        return $this;
    }

    public function offset($count)
    {
        $this->offset = (int) $count;

        return $this;
    }

    public function having($aggregate_function, $column, $operator, $value)
    {
        if (!in_array($aggregate_function, $this->AGGREGATE)) {
            throw new \InvalidArgumentException(
                "Invalid aggregate function {$aggregate_function}"
            );
        }

        $this->having = array(
            'query' => "{$aggregate_function}(?) $operator ?",
            'binds' => array(
                $column,
                $value
            )
        );

        return $this;
    }

    /**
     * Create where conditions
     *
     * Example 1:
     * <code>
     *      Object::find()->where(array('id = ? and username = ?', '1', 'John'))
     * </code>
     *
     * Example 2:
     * <code>
     *      Object::find()->where(array('id'=>array('1','2'), 'name' =>'John'))
     * </code>
     *
     * @param array  $conditions
     * @param string $operator   The logical operator for conditions, AND | OR
     * @param string $comparison The comparison operator for conditions
     */
    public function where(array $conditions, $operator='AND', $comparison='=')
    {
        $array_keys = array_keys($conditions);
        if (reset($array_keys) === 0 &&
            end($array_keys) === count($conditions) - 1 &&
            !is_array(end($conditions))
        ) {
            $condition = array_shift($conditions);

            $this->where[] = array(
                'query' => $condition,
                'binds' => $conditions,
                'operator' => $operator
            );
        }  else {

            foreach ($conditions as $key => $value) {

                if (is_array($value)) {

                    $this->whereIn($key, $value, $operator);
                } elseif (is_null($value)) {

                    $this->whereIsNull($key, $operator);
                } else {

                    $field = $this->get_field_with_table($key);

                    $this->where[] = array(
                        'query' => "$field $comparison ?",
                        'binds' => array($value),
                        'operator' => $operator
                    );
                }
            }
        }

        return $this;
    }

    private function get_field_with_table($string)
    {
        $point = strpos($string, '.');
        if (false === $point) {
            $field = $this->apostrophe($this->getTableAlias()) . ".$string";
        } else {
            $table = substr($string, 0, $point);
            $field = str_replace($table, $this->apostrophe($table), $string);
        }

        return $field;
    }

    public function whereIn($field, array $values, $operator='AND')
    {
        $query = trim(str_repeat('?, ', count($values)), ', ');

        $field = $this->get_field_with_table($field);

        $this->where[] = array(
            'query' => "$field IN ( $query )",
            'binds' => $values,
            'operator' => $operator
        );

        return $this;
    }

    public function whereNotIn($field, array $values, $operator='AND')
    {
        $query = trim(str_repeat('?, ',count($values)), ', ');

        $field = $this->get_field_with_table($field);

        $this->where[] = array(
            'query' => "$field NOT IN ( $query )",
            'binds' => $values,
            'operator' => $operator
        );

        return $this;
    }

    public function andWhere(array $conditions, $comparison='=')
    {
        return $this->where($conditions, 'AND', $comparison);
    }

    public function orWhere(array $conditions, $comparison='=')
    {
        return $this->where($conditions, 'OR', $comparison);
    }

    public function whereIsNull($field, $operator='AND')
    {
        $field = $this->get_field_with_table($field);

        $this->where[] = array(
            'query' => "$field IS NULL",
            'binds' => array(),
            'operator' => $operator
        );

        return $this;
    }

    public function whereLike($field, $value, $operator='AND')
    {
        $field = $this->get_field_with_table($field);

        $this->where[] = array(
            'query' => "$field like ?",
            'binds' => array($value),
            'operator' => $operator
        );

        return $this;
    }

    public function whereIsNotNull($field, $operator='AND')
    {
        $field = $this->get_field_with_table($field);

        $this->where[] = array(
            'query' => "$field IS NOT NULL",
            'binds' => array(),
            'operator' => $operator
        );

        return $this;
    }

    /*- (Build methods) ----------------------------------------------------- */

    protected function build_where()
    {
        $query = "";

        foreach($this->where as $k=>$a) {
            $query .= ($k!=0 ? $a['operator'] ." " : null) . $a['query'] . " ";
            $this->bind_params = array_merge($this->bind_params, $a['binds']);
        }

        return trim($query);
    }

    protected function build_having()
    {

        $this->bind_params = array_merge($this->bind_params, $this->having['binds']);

        return $this->having['query'];
    }

    protected function build_select()
    {
        if (null === $this->select) {
            return;
        }

        $args = $this->select;

        if (!is_array($args)) {
            $args = array_unique(array_map('trim', explode(',', $args)));
        }

        $select = array();
        foreach ($args as $a) {
            $table = $this->getTableAlias();
            $column = null;
            $b = explode(".", $a);

            if (count($b) == 2) {
                list($table, $column) = $b;
                $table = trim($table);
            }

            if (!isset($column)) {
                $column = $a;
            }

            if (isset($table)) {
				// Checks for Mysql functions and don't prepend table name.
                if (preg_match('/[\w]+\(.*\)/', $column)) {
                    $select[] = $column;
                } else {
                    $select[] = $this->apostrophe($table) . ".{$column}";
                }
            }
        }

        $this->select = implode(', ',$select);

        return $this->select;
    }

    public function build_sql()
    {

        // SELECT
        $query = "SELECT ";

        $query .= $this->build_select();

        // aggregates
        $aggr = array();
        $aggregate = false;

        if (!empty($this->aggregate)) {
            foreach ($this->aggregate as $a) {
                $aggr[] = (null === $this->select ? null : ", ")
                    .$a['function']
                    ."({$a['field']})"
                    .(isset($a['as']) ? ' as '.$a['as'] : null);
            }
            $aggregate = true;
            $query .= implode(', ', $aggr);
        }

        if (  !empty($this->join_conditions)
            && null === $this->select
        ) {
            if (false === $aggregate) {
                $this->select = $this->apostrophe($this->getTable()) . ".*";
            }
            $query .= ($aggregate && $this->select ? ', ' : null) . $this->select;
        }

        $query .= null == $this->select && $aggregate == false ? '*' : null;

        // FROM
        $query .= " FROM ".$this->apostrophe($this->getTable());

        // ALIAS
        if (isset($this->alias[$this->getTable()])) {
            $query .= " as ".$this->alias[$this->getTable()];
        }

        if (!empty($this->join_conditions))
            $query .= " " . implode(" ", $this->join_conditions);

        //WHERE
        if (!empty($this->where)) {
            $query .= " WHERE {$this->build_where()}";
        }

        //GROUP
        if (isset($this->group_by)) {
            $query .= " GROUP BY {$this->group_by}";
        }

        //ORDER BY
        if (isset($this->order_by)) {
            $query .= " ORDER BY {$this->order_by}";
        }

        // HAVING
        if (!empty($this->having)) {
            $query .= " HAVING {$this->build_having()}";
        }

        //LIMIT
        if (isset($this->limit)) {
            $query .= " LIMIT {$this->offset}, {$this->limit}";
        }

        $this->query = $query;

        return $query;
    }

    /*- (Fetching methods) ------------------------------------------------- */

    public function fetchAll($hydration = null)
    {
        return $this->fetch_data('all', $hydration);
    }

    public function fetch($hydration = null)
    {
        return $this->fetch_data('one', $hydration);
    }

    protected function fetch_data($mode, $hydration = null)
    {
        $this->build_sql();

        $iterator = $this->adapter->execute($this, 'Load', $hydration);

        if ('all' == $mode) {

            return $iterator;
        } elseif ('one' == $mode) {

            return $iterator->current();
        }
    }

    public function execute($query, array $bind_params = array())
    {
        $this->query = $query;
        $this->bind_params = $bind_params;

        return $this->adapter->execute($this);
    }

    /*- (Persistence methods) ---------------------------------------------- */

    /**
     * Compiles an insert query and executes it.
     *
     * @param string $table  the table in database to insert into.
     * @param array  $params an array with keys as fields of table and values
     *                       as the values ti insert into.
     * @access public
     * @return int Last insert id.
     */
    public function insert($table, array $params)
    {
        return $this->prepareInsert($table, $params)->execute($this, 'Create');
    }

    /**
     * Compiles an insert query.
     *
     * @param string $table
     * @param array $params
     * @access public
     * @return Larium\Database\AdapterInterface
     */
    public function prepareInsert($table, array $params)
    {
        $ks = array_keys($params);
        foreach($ks as $index=>$key) {
            $ks[$index] = $this->apostrophe($key);
        }
        $keys = implode(', ', $ks);
        $values = trim(str_repeat('?, ',count($params)), ', ');
        $this->query = "INSERT INTO {$this->apostrophe($table)} ({$keys}) VALUES ({$values})";

        $this->bind_params = $params;

        return $this->adapter;

    }

    /**
     * Compiles an update query and executes it.
     *
     * @param string $table
     * @param array $params
     * @param array $where
     * @access public
     * @return int Affected rows.
     */
    public function update($table, array $params, array $where)
    {
        return $this->prepareUpdate($table, $params, $where)
            ->execute($this, 'Update');
    }

    /**
     * Compiles an update query.
     *
     * @param string $table
     * @param array $params
     * @param array $where
     * @access public
     * @return Larium\Database\AdapterInterface
     */
    public function prepareUpdate($table, array $params, array $where)
    {
        $this->from($table);

        $data = "";
        foreach ($params as $name=>$value) {
            $data .= $this->apostrophe($name) . " = ?, ";
        }
        $data = rtrim($data, ", ");

        $this->bind_params = $params;

        if (!empty($where)) {
            $this->where($where);
        }

        $where = $this->build_where();

        $this->query = "UPDATE {$this->apostrophe($table)} SET {$data} WHERE {$where}";

        return $this->adapter;
    }

    public function delete($table, array $where)
    {
        return $this->prepareDelete($table, $where)
            ->execute($this, 'Destroy');
    }

    public function prepareDelete($table, array $where)
    {

        $this->from($table);

        if (!empty($where)) {
            $this->where($where);
        }

        $where = $this->build_where();

        $this->query = "DELETE FROM {$this->apostrophe($table)} WHERE {$where}";

        return $this->adapter;
    }

    public function __toString()
    {
        return $this->toSql();
    }

    protected function apostrophe($table)
    {
        return "`{$table}`";
    }

    public function paginate($request, $per_page = 20)
    {
        $total_query = clone $this;
        $total = $total_query->count('*','total_count')->fetch()->total_count;
        $page = Paginator::page($request, $total, $per_page);

        $results = $this->forPage($page, $per_page)->fetchAll();

        return Paginator::make($results, $total, $per_page, $request);
    }

    public function forPage($page, $per_page)
    {
        return $this->offset(($page-1) * $per_page)->limit($per_page);
    }
}
