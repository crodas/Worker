<?php

use crodas\Worker\Config;
use crodas\Worker\Server;
use crodas\Worker\Client;


class BasicTest extends \phpunit_framework_testcase
{
    public function testConfigAndDefaultViews()
    {
        $config = new Config;
        $config['foo'] = 'bar';
        $this->assertEquals($config['host'], '127.0.0.1');
        $this->assertEquals($config['non_defined'], false);
        $this->assertEquals($config['foo'], 'bar');
    }

    protected function getConfig()
    {
        $config = new Config;
        $config->addDirectory(__DIR__)
            ->setEngine('gearman');
        return $config;
    }

    public function testInit()
    {

        $server = new Server($this->getConfig());
        $services = $server->getServices();
        $this->AssertTrue(isset($services[ 'task:simple' ]));
        $this->AssertTrue(isset($services[ 'task:complex' ]));
    }

    public function testAsync()
    {
        $s = initServer();
        $client = new Client($this->getConfig());
        $job = $client->pushSync('task:simple', ['foobar']);
        $client->wait();
        $this->assertEquals($job->result, 'raboof');
    }
}
