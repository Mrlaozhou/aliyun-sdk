<?php
/**
 * Created by PhpStorm.
 * User: eagle
 * Date: 2018/4/4
 * Time: 11:09
 */

class VodTest extends TestCase
{

    public function testGetPlayInfoRequest()
    {
        $request = new Vod\Request\V20170321\GetPlayInfoRequest();
        $request->setVideoId("test"); // 错误 ID
        $request->setRegionId('cn-shanghai');

        try {
            $response = $this->acsClient->getAcsResponse($request);
        } catch (ServerException $e) {
            $this->assertEquals(404, $e->getHttpStatus());
            return;
        }
    }

}