<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace Larium\Database\Mysql;

use Larium\Database\AdapterFactory;

class QueryBuilderTest extends \PHPUnit_Framework_TestCase
{

    protected $adapter;

    public function setUp()
    {
        $config = (new \Config())->getDatabase();

        $this->adapter = AdapterFactory::create($config);
    }

    public function testSelectQuery()
    {
        $query = $this->adapter->createQuery()
            ->select('id, cars.name')
            ->from('cars')
            ->where(array('id'=>1));

        $this->assertEquals(
            $query->toRealSql(),
            "SELECT `cars`.id, `cars`.name FROM `cars` WHERE `cars`.id = 1"
        );
    }

    public function testSelectWithCountQuery()
    {
        $query = $this->adapter->createQuery()
            ->from('cars')
            ->where(array('id'=>1))
            ->count('DISTINCT id', 'totalCount');

        $this->assertEquals(
            $query->toRealSql(),
            "SELECT COUNT(DISTINCT id) as totalCount FROM `cars` WHERE `cars`.id = 1"
        );
    }

    public function testWhereQuery()
    {
        $query = $this->adapter->createQuery()
            ->from('cars')
            ->where(array('id'=>1));

        $this->assertEquals(
            $query->toRealSql(),
            "SELECT * FROM `cars` WHERE `cars`.id = 1"
        );
    }

    public function testAndWhereQuery()
    {
        $query = $this->adapter->createQuery()
            ->from('cars')
            ->where(array('id'=>1, 'name'=>'test'))
            ->andWhere(array('user_id'=>1));

        $this->assertEquals(
            $query->toRealSql(),
            "SELECT * FROM `cars` WHERE `cars`.id = 1 AND `cars`.name = 'test' AND `cars`.user_id = 1"
        );
    }

    public function testOrWhereQuery()
    {
        $query = $this->adapter->createQuery()
            ->from('cars')
            ->where(array('id'=>1))
            ->orWhere(array('name'=>'test'));

        $this->assertEquals(
            $query->toRealSql(),
            "SELECT * FROM `cars` WHERE `cars`.id = 1 OR `cars`.name = 'test'"
        );
    }

    public function testInQuery()
    {
        $query = $this->adapter->createQuery()
            ->from('cars')
            ->where(array('id'=>array(1,2,3,4)));

        $this->assertEquals(
            $query->toRealSql(),
            "SELECT * FROM `cars` WHERE `cars`.id IN ( 1, 2, 3, 4 )"
        );
    }

    public function testIsNullQuery()
    {
        $query = $this->adapter->createQuery()
            ->from('cars')
            ->where(array('id'=>1))
            ->whereIsNull('name')
            ->whereIsNotNull('color', 'OR');

        $this->assertEquals(
            $query->toRealSql(),
            "SELECT * FROM `cars` WHERE `cars`.id = 1 AND `cars`.name IS NULL OR `cars`.color IS NOT NULL"
        );
    }

    public function testComparisonOperator()
    {
        $query = $this->adapter->createQuery()
            ->from('cars')
            ->where(array('id'=>1), 'AND', '>');

        $this->assertEquals(
            $query->toRealSql(),
            "SELECT * FROM `cars` WHERE `cars`.id > 1"
        );
    }

    public function testWhereCase()
    {
        $query = $this->adapter->createQuery()
            ->from('cars')
            ->where(array('id = ?', 1));

        $this->assertEquals(
            $query->toRealSql(),
            "SELECT * FROM `cars` WHERE id = 1"
        );
    }

    public function testWhereLikeAndAlias()
    {
        $query = $this->adapter->createQuery()
            ->select('id, name')
            ->from('cars', 'c')
            ->whereLike('name', '%name%');

        $this->assertEquals(
            $query->toRealSql(),
            "SELECT `c`.id, `c`.name FROM `cars` as c WHERE `c`.name like '%name%'"
        );
    }

    public function testMultipleFields()
    {
        $query = $this->adapter->createQuery()
            ->select('c.id, c.name, d.name, d.id')
            ->from('cars', 'c')
            ->innerJoin('passengers as d', 'd.id', 'c.passengers_id');

        $this->assertEquals(
            $query->toRealSql(),
            "SELECT `c`.id, `c`.name, `d`.name, `d`.id FROM `cars` as c INNER JOIN `passengers` as d ON (`d`.id = `c`.passengers_id)"
        );
    }

    public function testMultipleFieldsWithWhere()
    {
        $query = $this->adapter->createQuery()
            ->select('c.id, c.name, d.name, d.id')
            ->from('cars', 'c')
            ->innerJoin('drivers as d', 'd.id', 'c.driver_id')
            ->where(array('d.id' => 1));

        $this->assertEquals(
            $query->toRealSql(),
            "SELECT `c`.id, `c`.name, `d`.name, `d`.id FROM `cars` as c INNER JOIN `drivers` as d ON (`d`.id = `c`.driver_id) WHERE `d`.id = 1"
        );
    }

    public function testInsertData()
    {
        $query = $this->adapter->createQuery();
        $adapter = $query->prepareInsert(
                'cars',
                array(
                    'name' => 'car_name',
                    'brand' => 'brand_name'
                )
            );

        $this->assertEquals(
            $query->toRealSql(),
            "INSERT INTO `cars` (`name`, `brand`) VALUES ('car_name', 'brand_name')"
        );
    }

    public function testUpdateTable()
    {
        $query = $this->adapter->createQuery();
        $adapter = $query->prepareUpdate(
                'cars',
                array(
                    'name' => 'car_name',
                ),
                array(
                    'id' => 1
                )
            );

        $this->assertEquals(
            $query->toRealSql(),
            "UPDATE `cars` SET `name` = 'car_name' WHERE `cars`.id = 1"
        );
    }

    public function testDeleteTable()
    {
        $query = $this->adapter->createQuery();
        $adapter = $query->prepareDelete(
                'cars',
                array(
                    'id' => 1
                )
            );

        $this->assertEquals(
            $query->toRealSql(),
            "DELETE FROM `cars` WHERE `cars`.id = 1"
        );
    }

    public function testNullValues()
    {
        $query = $this->adapter->createQuery()
            ->from('cars')
            ->where(array('id'=>1, 'name' => null));

        $this->assertEquals(
            $query->toRealSql(),
            "SELECT * FROM `cars` WHERE `cars`.id = 1 AND `cars`.name IS NULL"
        );
    }

    public function testAggregateMethods()
    {
        $query = $this->adapter->createQuery()
            ->count('DISTINCT cars.id', 'total')
            ->group_concat('DISTINCT cars.id', 'my_group')
            ->from('cars')
            ->where(array('id'=>1, 'name' => null));

        $this->assertEquals(
            $query->toRealSql(),
            "SELECT COUNT(DISTINCT cars.id) as total, GROUP_CONCAT(DISTINCT cars.id) as my_group FROM `cars` WHERE `cars`.id = 1 AND `cars`.name IS NULL"
        );
    }

    public function testAggregateMethodsWithSelect()
    {
        $query = $this->adapter->createQuery()
            ->select('id')
            ->count('DISTINCT cars.id', 'total')
            ->group_concat('DISTINCT cars.id', 'my_group')
            ->from('cars')
            ->where(array('id'=>1, 'name' => null));

        $this->assertEquals(
            $query->toRealSql(),
            "SELECT `cars`.id, COUNT(DISTINCT cars.id) as total, GROUP_CONCAT(DISTINCT cars.id) as my_group FROM `cars` WHERE `cars`.id = 1 AND `cars`.name IS NULL"
        );
    }
}
