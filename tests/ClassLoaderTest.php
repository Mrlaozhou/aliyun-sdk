<?php

use Ecs\Request\V20140526 as Ecs;
use aegis\Request\V20161111 as aegis;

class ClassLoaderTest extends TestCase
{

    public function testNewObjects()
    {
        $request = new Ecs\DescribeInstancesRequest();
        $request->setRegionId('cn-shanghai');
        $this->assertInternalType('object', $request);
        $this->assertEquals('cn-shanghai', $request->getRegionId());
    }

    public function testLowerNamespace()
    {
        $request = new aegis\DescribeStratetyRequest();
        $this->assertInternalType('object', $request);
    }

}