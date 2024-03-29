<?php

namespace NetricTest;

use PHPUnit\Framework\TestCase;
use Netric;
use Netric\EntityQuery\EntityQuery;
use Netric\EntityDefinition\ObjectTypes;
use Netric\EntityQuery\Where;
use Netric\EntityQuery\OrderBy;

class EntityQueryTest extends TestCase
{
    /**
     * Test addition a filter condition to a collection
     */
    public function testWhere()
    {
        $query = new EntityQuery(ObjectTypes::CONTACT, 'TEST-ACCOUNT-ID');
        $query->where('name')->equals("test");
        //$query->orWhere('fieldname')->isGreaterThan("value");
        //$query->andWhere('fieldname')->isLessThan("value");

        // Get the protected and private values
        $refColl = new \ReflectionObject($query);
        $wheresProp = $refColl->getProperty('wheres');
        $wheresProp->setAccessible(true);

        // Test values
        $wheres = $wheresProp->getValue($query);
        $this->assertEquals("name", $wheres[0]->fieldName, "Where name not set");
        $this->assertEquals("test", $wheres[0]->value, "Where condtiion value not set");
    }

    /**
     * Test addition an order by condition to a collection
     */
    public function testOrderBy()
    {
        $query = new EntityQuery(ObjectTypes::CONTACT, 'TEST-ACCOUNT-ID');
        $query->orderBy("name");
        $orderBy = $query->getOrderBy();

        // Test values
        $this->assertEquals("name", $orderBy[0]->fieldName, "Order by name not set");
        $this->assertEquals(Netric\EntityQuery\OrderBy::ASCENDING, $orderBy[0]->direction, "Order by name not set");
    }

    /**
     * Make sure we can convert a query to an array
     */
    public function testToArray()
    {
        $query = new EntityQuery(ObjectTypes::CONTACT, 'TEST-ACCOUNT-ID');
        $query->where('name')->equals("test");
        $query->orderBy("name");

        $arrQuery = $query->toArray();
        $this->assertEquals(ObjectTypes::CONTACT, $arrQuery['obj_type']);
        $this->assertEquals($query->getOffset(), $arrQuery['offset']);
        $this->assertEquals($query->getLimit(), $arrQuery['limit']);

        /*
         * We do not need to test all order by props individually since
         * they are tested in OrderByTest::testToArray
         */
        $this->assertEquals(1, count($arrQuery['order_by']));
        $this->assertEquals("name", $arrQuery['order_by'][0]['field_name']);

        /*
         * We do not need to test all where props individually since
         * they are tested in Where::testToArray
         */
        $this->assertEquals(1, count($arrQuery['conditions']));
        $this->assertEquals(Where::COMBINED_BY_AND, $arrQuery['conditions'][0]['blogic']);
    }

    /**
     * Test reconstructing a query from an array
     */
    public function testFromArray()
    {
        $arrQueryData = [
            'obj_type' => ObjectTypes::CONTACT,
            'limit' => 100,
            'offset' => 20,
            'conditions' => [
                [
                    'field_name' => 'name',
                    'operator' => Where::OPERATOR_EQUAL_TO,
                    'value' => 'realname'
                ],
                [
                    'blogic' => Where::COMBINED_BY_AND,
                    'field_name' => 'name',
                    'operator' => Where::OPERATOR_NOT_EQUAL_TO,
                    'value' => 'fakename'
                ]
            ],
            'order_by' => [
                ['field_name' => 'name', 'direction' => OrderBy::DESCENDING]
            ]
        ];

        $query = new EntityQuery($arrQueryData['obj_type'], 'TEST-ACCOUNT-ID');
        $query->fromArray($arrQueryData);

        // Make sure it's all loaded up
        $this->assertEquals($arrQueryData['limit'], $query->getLimit());
        $this->assertEquals($arrQueryData['offset'], $query->getOffset());

        /*
         * We don't need to test all the properties of the Where object since
         * that is already tested in WhereTest::testFromArray
         */
        $wheres = $query->getWheres();
        $this->assertInstanceOf(Where::class, $wheres[0]);

        /*
         * We don't need to test all the properties of the OrderBy object since
         * that is already tested in OrderByTest::testFromArray
         */
        $orders = $query->getOrderBy();
        $this->assertInstanceOf(OrderBy::class, $orders[0]);
    }

    /**
     * Assure that the query can let consumers know if a field is being used as a condition
     *
     * @return void
     */
    public function testFieldIsInWheres()
    {
        $query = new EntityQuery(ObjectTypes::CONTACT, 'TEST-ACCOUNT-ID');
        $query->where('name')->equals('test');

        // Test both positive and negative results
        $this->assertTrue($query->fieldIsInWheres('name'));
        $this->assertFalse($query->fieldIsInWheres('age'));
    }
}
