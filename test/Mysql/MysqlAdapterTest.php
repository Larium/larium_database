<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace Larium\Database\Mysql;

use Larium\Database\AdapterFactory;
use Larium\Database\Config;
use Larium\Database\Model\Author;
use Larium\Database\Model\Book;
use PHPUnit\Framework\TestCase;

class MysqlAdapterTest extends TestCase
{
    protected $adapter;

    public function setUp()
    {
        $config = (new Config())->getDatabase();

        $this->adapter = AdapterFactory::create($config);
    }

    public function testHydration()
    {
        $query = $this->adapter->createQuery(Author::class);

        $author = $query->from('authors')
            ->where(array('id'=>1))->fetch(Query::HYDRATE_OBJ);

        $books_result = $this->adapter->createQuery(Book::class)
            ->from('books')->where(array('author_id'=>$author->id))
            ->fetchAll(Query::HYDRATE_OBJ);

        $books = array();
        foreach ($books_result as $book) {
            $books[] = $book;
        }
        print_r($books);
    }
}
