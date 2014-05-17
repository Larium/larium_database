<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace Larium\Database\Mysql;

use Larium\Database\AdapterInterface;
use Larium\Database\QueryInterface;

/**
 * Adapter for a MySQL database connection.
 *
 * Using Mysqli extension to connect to a MySQL database.
 *
 */
class Adapter implements AdapterInterface
{

    const FETCH_ASSOC = 2;

    const FETCH_OBJ = 5;

    /**
     * An array with configuration data for this adapter
     *
     * Possible values are
     *
     * host         the host name or an IP address of MySQL server
     * port         Specifies the port number to attempt to connect to the MySQL
     *              server.
     * database     the database name to connect.
     * username     the user that has access to this database
     * password     password for this user
     * charset      the default client character set
     * fetch        the default fetch style for the result set row.
     *              Possible values are:
     *              AdapterInterface::FETCH_ASSOC || AdapterInterface::FETCH_OBJ
     *
     * @var array
     */
    protected $config;

    /**
     * The mysqli connection
     *
     * @var \mysqli
     */
    protected $connection;

    /**
     * An instance of logger to be used fro logging queries.
     *
     * @var mixed
     */
    protected $logger;

    /**
     * The default fetch style for the result set row.
     *
     * @var int
     */
    protected $fetch_style;

    /**
     * An array with all executed queries
     *
     * @var array
     */
    protected $query_array = array();

    private $real_query;

    /**
     * Creates an Adapter instance using an array of options.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Returns the current connection between PHP and MySQL database.
     *
     * @return \mysli
     */
    public function getConnection()
    {
        if (null === $this->connection) {
            $this->connect();
        }

        return $this->connection;
    }

    /**
     * {@inheritdoc}
     */
    public function connect()
    {
        if ( null === $this->connection ) {

            extract($this->config);

            $this->connection = new \mysqli(
                $host,
                $username,
                $password,
                $database,
                $port
            );

            if ($this->connection->connect_error) {
                throw new \Exception("Could not connect to database server! [" . $this->connection->connect_error . "]");
            }

            if (isset($fetch)) {
                $this->fetch_style = $fetch;
            }

            if (isset($charset)) {
                $this->connection->set_charset($charset);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function disconnect()
    {
        $this->connection->close();
        $this->connection = null;
    }

    /**
     * {@inheritdoc}
     *
     * @return int|ResultIterator
     */
    public function execute(QueryInterface $query, $action='Load', $hydration = null)
    {
        $params = $query->getBindParams();

        $stmt = $this->prepare($query->toSql());

        if (false === $stmt) {
            throw new \Exception($this->getConnection()->error);
        }
        $this->bind_params($stmt, $params);

        $this->real_query = $query->toRealSql();

        $start = microtime(true);

        $stmt->execute();

        if ($this->logger) {
            $this->getLogger()->logQuery(
                $this->real_query,
                $query->getObject(),
                (microtime(true) - $start),
                $action
            );
        }

        $this->query_array[] = $this->real_query;

        if ( 0 !== $stmt->errno ) {
            throw new \Exception($stmt->error);
        }

        switch (true) {
            case $stmt->insert_id !== 0 && $stmt->affected_rows !==-1:
                //INSERT statement

                return $stmt->insert_id;
                break;
            case $stmt->insert_id === 0 && $stmt->affected_rows ===-1:
                // SELECT statement

                if (Query::HYDRATE_OBJ == $hydration) {
                    $this->fetch_style = self::FETCH_OBJ;
                }

                $iterator = new ResultIterator(
                    $stmt->get_result(),
                    $hydration ?: $this->fetch_style,
                    $query->getObject()
                );

                return $iterator;
                break;
            default:
                // UPDATE, DELETE statement

                return $stmt->affected_rows;
                break;
        }

        //return $stmt;
    }

    /**
     * {@inheritdoc}
     */
    public function createQuery($object = null)
    {
        return new Query($object, $this);
    }

    /**
     * {@inheritdoc}
     *
     * @return int
     */
    public function getInsertId($stmt = null)
    {
        return (int) ($stmt ? $stmt->insert_id : $this->getConnection()->insert_id);
    }

    public function sanitize(&$value)
    {
        if (null === $value) {
            $value = 'NULL';

            return;
        }

        $value = $this->getConnection()->real_escape_string($value);
        $value = is_numeric($value) ? $value : $this->quote($value);
    }

    public function quote($string)
    {
        //if (substr_count($string, "'") == 2) return $string;

        return "'" . $string . "'";
    }

    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Gets an array with queries executed by this adapter.
     *
     * @return array An array with queries
     */
    public function getQueries()
    {
        return $this->query_array;
    }

    protected function prepare($query=null)
    {
        $query = $query ?: $this->query;

        $stmt = $this->getConnection()->prepare($query);

        if ($this->logger) {
            $this->getLogger()->log("Prepare: $query");
        }

        if (false == $stmt ) {
            throw new \Exception(
                 $this->getConnection()->error . "\n"
                 . "[{$this->getConnection()->errno}] "
                 . $query . "//"
            );
        }

        return $stmt;
    }

    protected function bind_params($stmt, $params)
    {
        if (!empty($params)) {
            $types = "";
            $ref = array();

            foreach($params as $key=>$value) {
                $types .= is_float($value)
                    ? 'd' : (is_int($value) ? 'i' : (is_string($value) ? 's' : 'b'));
                $ref[$key] = &$params[$key];
            }
            array_unshift($params, $types);
            $method = new \ReflectionMethod($stmt, 'bind_param');
            $method->invokeArgs($stmt, $params);
        }
    }
}
