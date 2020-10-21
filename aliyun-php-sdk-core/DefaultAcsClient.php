<?php

/*
 * Licensed to the Apache Software Foundation (ASF) under one
 * or more contributor license agreements.  See the NOTICE file
 * distributed with this work for additional information
 * regarding copyright ownership.  The ASF licenses this file
 * to you under the Apache License, Version 2.0 (the
 * "License"); you may not use this file except in compliance
 * with the License.  You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request as HttpRequest;
use Psr\Http\Message\ResponseInterface;

class DefaultAcsClient extends Client implements IAcsClient
{

    public $iClientProfile;

    private $locationService;

    /**
     * DefaultAcsClient constructor.
     * @param array $iClientProfile
     * @param array $config
     */
    public function __construct($iClientProfile, array $config = [])
    {
        $this->iClientProfile = $iClientProfile;
        $this->locationService = new LocationService($this->iClientProfile);

        parent::__construct(array_merge(['http_errors' => false], $config));
    }

    public function getAcsResponse(AcsRequest $request, $iSigner = null, $credential = null, $autoRetry = true, $maxRetryNumber = 3)
    {
        $httpResponse = $this->doActionImpl($request, $iSigner, $credential, $autoRetry, $maxRetryNumber);
        $respObject = self::parseAcsResponse($httpResponse);
        if (false == self::isSuccess($httpResponse)) {
            $this->buildApiException($respObject, $httpResponse->getStatusCode());
        }
        return $respObject;
    }

    public static function isSuccess(ResponseInterface $response)
    {
        if (200 <= $response->getStatusCode() && 300 > $response->getStatusCode()) {
            return true;
        }
        return false;
    }

    private function doActionImpl($request, $iSigner = null, $credential = null, $autoRetry = true, $maxRetryNumber = 3)
    {
        $httpRequest = $this->buildHttpRequest($request, $iSigner, $credential);
        $httpResponse = $this->send($httpRequest);

        $retryTimes = 1;
        while (500 <= $httpResponse->getStatusCode() && $autoRetry && $retryTimes < $maxRetryNumber) {
            $httpRequest = $this->buildHttpRequest($request, $iSigner, $credential);
            $httpResponse = $this->send($httpRequest);
            $retryTimes++;
        }
        return $httpResponse;
    }

    public function doAction($request, $iSigner = null, $credential = null, $autoRetry = true, $maxRetryNumber = 3)
    {
        trigger_error("doAction() is deprecated. Please use getAcsResponse() instead.", E_USER_NOTICE);
        return $this->doActionImpl($request, $iSigner, $credential, $autoRetry, $maxRetryNumber);
    }

    private function prepareRequest($request)
    {
        if (null == $request->getRegionId()) {
            $request->setRegionId($this->iClientProfile->getRegionId());
        }
        if (null == $request->getAcceptFormat()) {
            $request->setAcceptFormat($this->iClientProfile->getFormat());
        }
        if (null == $request->getMethod()) {
            $request->setMethod("GET");
        }
        return $request;
    }

    public function buildHttpRequest(AcsRequest $request, $iSigner = null, $credential = null)
    {
        if (null == $this->iClientProfile && (null == $iSigner || null == $credential
                || null == $request->getRegionId() || null == $request->getAcceptFormat())) {
            throw new ClientException("No active profile found.", "SDK.InvalidProfile");
        }
        if (null == $iSigner) {
            $iSigner = $this->iClientProfile->getSigner();
        }
        if (null == $credential) {
            $credential = $this->iClientProfile->getCredential();
        }
        $request = $this->prepareRequest($request);

        // Get the domain from the Location Service by speicified `ServiceCode` and `RegionId`.
        $domain = null;
        if (null != $request->getLocationServiceCode()) {
            $domain = $this->locationService->findProductDomain(
                $request->getRegionId(),
                $request->getLocationServiceCode(),
                $request->getLocationEndpointType(),
                $request->getProduct()
            );
        }
        if ($domain == null) {
            $domain = EndpointProvider::findProductDomain($request->getRegionId(), $request->getProduct());
        }

        if (null == $domain) {
            throw new ClientException("Can not find endpoint to access.", "SDK.InvalidRegionId");
        }

        return (new HttpRequest(
            $request->getMethod(),
            $request->composeUrl($iSigner, $credential, $domain),
            $request->getHeaders(),
            $this->getAcsRequestBody($request)
        ))->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withHeader('Accept', $this->convertStdHttpAccept($request->getAcceptFormat()));
    }

    /**
     * @param $format
     * @return string
     */
    public function convertStdHttpAccept($format)
    {
        if ("JSON" == $format) {
            return 'application/json';
        } elseif ("XML" == $format) {
            return 'text/xml';
        } elseif ("RAW" == $format) {
            return '*/*';
        } else {
            return '*/*';
        }
    }

    /**
     * @param AcsRequest $request
     * @return string
     */
    public function getAcsRequestBody(AcsRequest $request)
    {
        if (method_exists($request, 'getDomainParameter') && $request->getDomainParameter()) {
            return http_build_query($request->getDomainParameter());
        }
        return $request->getContent();
    }

    /**
     * 同时发送多个请求
     *
     * @param array $requests
     * @param callable|null $fulfilled
     * @param callable|null $rejected
     * @param int $concurrency
     * @param array $args
     * @return mixed
     */
    public function sendMultiRequests(
        array $requests,
        callable $fulfilled = null,
        callable $rejected = null,
        $concurrency = 5,
        array $args = []
    )
    {
        $pool = new \GuzzleHttp\Pool($this, $requests, [
            'fulfilled' => function (ResponseInterface $response, $index) use ($fulfilled, $requests, $args) {
                $respObject = self::parseAcsResponse($response);
                if (false == self::isSuccess($response)) {
                    $this->buildApiException($respObject, $response->getStatusCode());
                }
                $fulfilled && call_user_func($fulfilled, $respObject, $response, $index, $args);
            },
            'rejected' => function ($reason, $index) use ($rejected, $args) {
                $rejected && call_user_func($rejected, $reason, $index, $args);
            },
            'concurrency' => $concurrency,
        ]);

        return $pool->promise()->wait();
    }

    /**
     * @param $respObject
     * @param $httpStatus
     * @throws Exception
     * @throws ServerException|\Exception
     */
    private function buildApiException($respObject, $httpStatus)
    {
        if (is_object($respObject) && isset($respObject->RequestId)) {
            throw new ServerException($respObject->Message, $respObject->Code, $httpStatus, $respObject->RequestId);
        } elseif (is_string($respObject)) {
            throw new ServerException($respObject, -1, $httpStatus, 0);
        } else {
            throw new Exception("Invalid response object: " . gettype($respObject));
        }
    }

    /**
     * @param ResponseInterface $response
     * @return mixed|SimpleXMLElement|string
     */
    public static function parseAcsResponse(ResponseInterface $response)
    {
        $contentType = $response->getHeaderLine('Content-Type');
        $body = $response->getBody()->getContents();

        if (strpos($contentType, 'json') !== false) {
            return json_decode($body);
        } elseif (strpos($contentType, 'xml') !== false) {
            return @simplexml_load_string($body);
        } else {
            return $body;
        }
    }

}
