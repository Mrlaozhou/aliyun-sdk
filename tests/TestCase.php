<?php
/**
 * Created by PhpStorm.
 * User: eagle
 * Date: 06/03/2018
 * Time: 13:26
 */

class TestCase extends PHPUnit_Framework_TestCase
{
    protected $profile;
    /** @var \DefaultAcsClient */
    protected $acsClient;

    public static function setUpBeforeClass()
    {
        if (!isset($_SERVER['DEFAULT_REGION_ID']) || !isset($_SERVER['ACCESS_KEY_ID']) || !isset($_SERVER['ACCESS_SECRET'])) {
            self::markTestSkipped(
                'Environment variables REGION_ID and/or ACCESS_KEY_ID and/or ACCESS_SECRET are missing'
            );
        }
    }

    protected function setUp()
    {
        $this->profile = \DefaultProfile::getProfile(
            $_SERVER['DEFAULT_REGION_ID'],
            $_SERVER['ACCESS_KEY_ID'],
            $_SERVER['ACCESS_SECRET']
        );

        $this->acsClient = new \DefaultAcsClient($this->profile);
    }

}