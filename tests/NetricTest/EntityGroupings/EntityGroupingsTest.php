<?php

namespace NetricTest\EntityGroupings;

use PHPUnit\Framework\TestCase;
use Netric\EntityGroupings\EntityGroupings;
use Netric\EntityGroupings\Group;

class EntityGroupingsTest extends TestCase
{
    /**
     * Test adding a grouping
     */
    public function testAdd()
    {
        $groupings = new EntityGroupings("test");
        $group = new Group();
        $group->name = "My Test";
        $groupings->add($group);

        $ret = $groupings->getAll();
        $this->assertEquals($group->name, $ret[0]->name);
    }

    /**
     * Test adding a grouping
     */
    public function testDelete()
    {
        $groupings = new EntityGroupings("test");
        $group = new Group();
        $group->id = 1;
        $group->name = "My Test";
        $groupings->add($group);
        $groupings->delete($group->id);

        $ret = $groupings->getDeleted();
        $this->assertEquals($group->id, $ret[0]->id);

        $ret = $groupings->getAll();
        $this->assertEquals(0, count($ret));
    }

    /**
     * Test adding a grouping
     */
    public function testGetById()
    {
        $groupings = new EntityGroupings("test");
        $group = new Group();
        $group->id = 1;
        $group->name = "My Test";
        $groupings->add($group);

        $ret = $groupings->getById($group->id);
        $this->assertEquals($group->id, $ret->id);
    }

    /**
     * Test adding a grouping
     */
    public function testGetByName()
    {
        $groupings = new EntityGroupings("test");
        $group = new Group();
        $group->id = 1;
        $group->name = "My Test";
        $groupings->add($group);

        $ret = $groupings->getByName("My Test");
        $this->assertEquals($group->id, $ret->id);
    }

    /**
     * Test adding a grouping
     */
    public function testGetByPath()
    {
        $groupings = new EntityGroupings("test");

        $group = new Group();
        $group->id = 1;
        $group->name = "My Test";
        $groupings->add($group);

        $group2 = new Group();
        $group2->id = 2;
        $group2->parentId = $group->id;
        $group2->name = "Sub Test";
        $groupings->add($group2);

        $ret = $groupings->getByPath("My Test/Sub Test");
        $this->assertEquals($group2->id, $ret->id);
    }

    /**
     * Test adding a grouping
     */
    public function testGetPath()
    {
        $groupings = new EntityGroupings("test");

        $group = new Group();
        $group->id = 1;
        $group->name = "My Test";
        $groupings->add($group);

        $group2 = new Group();
        $group2->id = 2;
        $group2->parentId = $group->id;
        $group2->name = "Sub Test";
        $groupings->add($group2);

        $ret = $groupings->getPath($group2->id);
        $this->assertEquals("My Test/Sub Test", $ret);
    }

    public function testCreate()
    {
        $groupings = new EntityGroupings("test");
        $group = $groupings->create();
        $this->assertInstanceOf(Group::class, $group);
    }

    public function testGetChildren()
    {
        $groupings = new EntityGroupings("test");

        $group = new Group();
        $group->id = 1;
        $group->name = "My Test";
        $groupings->add($group);

        $group2 = new Group();
        $group2->id = 2;
        $group2->parentId = $group->id;
        $group2->name = "Sub Test";
        $groupings->add($group2);

        $group3 = new Group();
        $group3->id = 3;
        $group3->parentId = $group2->id;
        $group3->name = "Sub Test";
        $groupings->add($group3);

        $ret = $groupings->getChildren($group->id);
        $this->assertEquals($group2->id, $ret[0]->id);
        $this->assertEquals($group3->id, $ret[1]->id);
    }

    public function testGetHeirarch()
    {
        $groupings = new EntityGroupings("test");

        $group = new Group();
        $group->id = 1;
        $group->name = "My Test";
        $groupings->add($group);

        $group2 = new Group();
        $group2->id = 2;
        $group2->parentId = $group->id;
        $group2->name = "Sub Test";
        $groupings->add($group2);

        $group3 = new Group();
        $group3->id = 3;
        $group3->parentId = $group2->id;
        $group3->name = "Sub Test";
        $groupings->add($group3);

        $ret = $groupings->getHeirarch($group->id);
        $this->assertEquals($group2->id, $ret[0]->id);
        $this->assertEquals($group3->id, $ret[0]->children[0]->id);
    }

    /**
     * Test standardized hash for filters
     */
    public function testGetFiltersHash()
    {
        // Make sure no filters results in default none hash
        $this->assertEquals(null, EntityGroupings::getFiltersHash(array()));

        // Make sure filters are sorted right
        $this->assertEquals("test=2user_id=1", EntityGroupings::getFiltersHash(array("user_id" => 1, "test" => 2)));
    }
}
