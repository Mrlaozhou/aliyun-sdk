<?php
/**
 * Ecs test case
 *
 * User: eagle
 * Date: 06/03/2018
 * Time: 15:05
 */

class EcsTest extends TestCase
{

    public function testDescribeInstances()
    {
        $request = new Ecs\Request\V20140526\DescribeInstancesRequest();
        $request->setRegionId('cn-shanghai');
        $response = $this->acsClient->getAcsResponse($request);
        $this->assertObjectHasAttribute( 'Instances', $response);
        $this->assertInternalType( 'object', $response->Instances);

        $this->assertObjectHasAttribute( 'Instance', $response->Instances);
        $this->assertInternalType('array', $response->Instances->Instance);
    }
}