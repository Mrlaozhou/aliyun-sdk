<?php
/**
 * Created by PhpStorm.
 * User: eagle
 * Date: 06/03/2018
 * Time: 15:39
 */

use Ecs\Request\V20140526 as Ecs;

class ConcurrencyTest extends TestCase
{

    public function testDescribeInstances()
    {
        $request = new Ecs\DescribeInstancesRequest();

        // 创建10个请求，同时发送
        for ($i = 0; $i < 10; $i++) {
            $requests[] = $this->acsClient->buildHttpRequest($request);
        }

        $successful = function ($object, $response, $index, $args) {
            echo "Successful #$index: " . $object->RequestId . "\n";
        };
        $rejected = function ($reason, $index, $args) {
            echo "Failed #$index: $reason\n";
        };

        $this->acsClient->sendMultiRequests($requests, $successful, $rejected, 10, [123, 456]);
    }

}