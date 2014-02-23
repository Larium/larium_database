<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace Larium\Database\Mysql;

use Larium\Database\AdapterFactory;

class MysqlAdapterTest extends \PHPUnit_Framework_TestCase
{
    protected $adapter;

    public function setUp()
    {
        $config = (new \Config())->getDatabase();

        $this->adapter = AdapterFactory::create($config);
    }

    public function testHydration()
    {
        $query = $this->adapter->createQuery('Author');

        $author = $query->from('authors')
            ->where(array('id'=>1))->fetch(Query::HYDRATE_OBJ);

        $books_result = $this->adapter->createQuery('Book')
            ->from('books')->where(array('author_id'=>$author->id))
            ->fetchAll(Query::HYDRATE_OBJ);

        $books = array();
        foreach ($books_result as $book) {
            $books[] = $book;
        }
        print_r($books);
    }
}
