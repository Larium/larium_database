<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace Larium\Database;

interface AdapterInterface
{
    const FETCH_ASSOC = 2;

    const FETCH_OBJ = 5;

    /**
     * Creates a connection between PHP and Mysql 
     * 
     * @throws \Exception
     *
     * @return void
     */
    public function connect();

    /**
     * Closes the connection to database and unset the connection property.
     *
     * @return void
     */
    public function disconnect();

    /**
     * Prepares and executes a query and return the statement
     * 
     * @param QueryInterface $query  The query object to execute.
     * @param string         $action optional the action that represent this query.
     *                               Create for INSERT, Update for UPDATE, Load for SELECT,
     *                               Delete for DELETE.
     */
    public function execute(QueryInterface $query, $action = 'Load');

    /**
     * Creates a new query instance.
     * 
     * @param mixed $object The class to use for fetching results if fetch style 
     *                      is AdapterInterface::FETCH_OBJ
     *
     * @return QueryInterface
     */
    public function createQuery($object = null);
    
    /**
     * Returns the auto generated id used in the last query.
     *
     * @params mixed $stmt The statement instance to retrieve the insert id (optional).
     */
    public function getInsertId($stmt = null);

    /**
     * Returns the connection between PHP and Mysql.
     */
    public function getConnection();
}
