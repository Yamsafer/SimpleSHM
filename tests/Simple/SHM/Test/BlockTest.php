<?php

namespace Simple\SHM\Test;

use Simple\SHM\Block;

class BlockTest extends \PHPUnit_Framework_TestCase
{
    public function testIsCreatingNewBlock()
    {
        $memory = new Block;
        $this->assertInstanceOf('Simple\\SHM\\Block', $memory);

        $memory->write('Sample');
        $data = $memory->read();
        $this->assertEquals('Sample', $data);
        $memory->delete();
    }

    public function testIsCreatingNewBlockWithId()
    {
        $memory = new Block(897);
        $this->assertInstanceOf('Simple\\SHM\\Block', $memory);
        $this->assertEquals(897, $memory->getId());

        $memory->write('Sample 2');
        $data = $memory->read();
        $this->assertEquals('Sample 2', $data);
    }

    public function testIsMarkingBlockForDeletion()
    {
        $memory = new Block(897);
        $memory->delete();
        $this->assertEquals($memory->exists($memory->getId()), false);
        $data = $memory->read();
        $this->assertEquals('Sample 2', $data);
    }

    public function testIsPersistingNewBlockWithoutId()
    {
        $memory = new Block;
        $id = $memory->getId();
        $this->assertInstanceOf('Simple\\SHM\\Block', $memory);
        $memory->write('Sample 3');
        unset($memory);

        $memory = new Block($id);
        $data = $memory->read();
        $this->assertEquals('Sample 3', $data);
        $this->assertEquals($id, $memory->getId());
        $memory->delete();
    }

    public function testIsSavingArray()
    {
        $memory = new Block;
        $id = $memory->getId();

        $array = ['test' => 'YamsaferTest', 'test1' => [154 => 333]];

        $memory->write($array);
        unset($memory);

        $memory = new Block($id);
        $data = $memory->read();
        $this->assertEquals($data['test'], $array['test']);
        $this->assertEquals($data['test'], 'YamsaferTest');
        $this->assertEquals($data['test1']['154'], 333);

        $array = ['test' => 'YamsaferTest2', 'test1' => [154 => 333]];
        $memory->write($array);
        $data = $memory->read();
        $this->assertEquals($data['test'], 'YamsaferTest2');

        $this->assertEquals(json_encode($data), json_encode($array));

        $memory->delete();
    }

    public function testWritingOnExistBlock()
    {
        $memory = new Block;
        $id = $memory->getId();
        $memory->write('Sample 5');
        unset($memory);

        $memory = new Block($id);
        $memory->write('Sample 5');
        $data = $memory->read();

        $this->assertEquals('Sample 5', $data);
        $memory->delete();
    }
}
