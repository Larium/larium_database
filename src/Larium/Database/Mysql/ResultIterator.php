<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace Larium\Database\Mysql;

use Larium\Database\AdapterInterface;
use Larium\Database\ResultIteratorInterface;

class ResultIterator implements ResultIteratorInterface, \ArrayAccess
{
    
    protected $result_set;
    
    private $index = 0;

    private $fetch_style = AdapterInterface::FETCH_OBJ;
    
    private $object = '\\stdClass';

    private $arg;

    private $fetch_methods = array(
        AdapterInterface::FETCH_OBJ   => 'fetch_object',
        AdapterInterface::FETCH_ASSOC => 'fetch_array',
    );

    /**
     * 
     * @param \mysqli_result $result_set 
     * @param int            $fetch_style AdapterInterface::FETCH_OBJ or 
     *                                    AdapterInterface::FETCH_ASSOC
     * @param mixed          $object      the name of the class to instantiate 
     *                                    when fetch style is 
     *                                    AdapterInterface::FETCH_OBJ
     * 
     * @return ResultIterator
     */
    public function __construct(
        \mysqli_result $result_set, 
        $fetch_style = AdapterInterface::FETCH_OBJ,
        $object = '\\stdClass'
    ) {
        
        $this->result_set = $result_set;
        $this->fetch_style = $fetch_style ?: $this->fetch_style; 
        $this->object = $object ?: '\\stdClass';

        if (AdapterInterface::FETCH_ASSOC === $fetch_style) {
            $this->arg = \MYSQLI_ASSOC;
        } else {
            $this->arg = $this->object;
        }
    }

    public function current()
    {
        $this->result_set->data_seek($this->index);

        $method = $this->fetch_methods[$this->fetch_style];

        return $this->result_set->$method($this->arg);
    }

    public function key()
    {
        return $this->index; 
    }

    public function next()
    {
        $this->index++;
    }

    public function rewind()
    {
        $this->index = 0;
    }

    public function valid()
    {
        return $this->key() < $this->count(); 
    }

    public function count()
    {
        return $this->result_set->num_rows;
    }

    public function offsetExists($key)
    {
        return $key < $this->result_set->num_rows;
    }

    public function offsetGet($key)
    {
        $this->result_set->data_seek($key);

        $method = $this->fetch_methods[$this->fetch_style];

        return $this->result_set->$method($this->arg);
    }

    public function offsetSet($key, $value)
    {
        return false;
    }

    public function offsetUnset($key)
    {
        return false;
    }

    public function __destruct()
    {
        $this->result_set->free();
    }
}
