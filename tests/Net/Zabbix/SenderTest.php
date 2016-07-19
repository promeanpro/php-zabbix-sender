<?php

use Net\Zabbix\Agent\ZabbixConfigurator;
use Net\Zabbix\Sender;

class Zabbix_SenderTest extends \PHPUnit_Framework_TestCase
{
	/** @var Sender */
	private $sender;


	public function setUp()
	{
		$agentConfig = new ZabbixConfigurator();
		$this->sender = new Sender('localhost', 10051);
		$this->sender->importAgentConfig($agentConfig);
	}


	public function test_set_getTimeout()
	{
		$timeout = 99;
		$this->sender->setTimeout($timeout);
		static::assertEquals($timeout, $this->sender->getTimeout());
	}


	public function test_addData()
	{
		$this->addData($this->sender);
		$dataArray = $this->sender->getDataArray();
		static::assertCount(3, $dataArray);
	}


	public function test_unsetData()
	{
		$this->addData($this->sender);
		$this->sender->initData();
		$dataArray = $this->sender->getDataArray();
		static::assertCount(0, $dataArray);
	}


	/**
	 * @expectedException Net\Zabbix\Exception\SenderNetworkException
	 */
	public function test_sendFailInvalidHostname()
	{
		$this->sender->setServerName('invalid-hostname');
		$result = $this->sender->send();
		static::assertFalse($result);
	}


	/**
	 * @expectedException Net\Zabbix\Exception\SenderNetworkException
	 */
	public function test_SendFailInvalidPort()
	{
		$this->sender->setServerPort(11111);
		$result = $this->sender->send();
		static::assertFalse($result);
	}


	public function test_send()
	{
		$this->addData($this->sender);
		$result = $this->sender->send();
		static::assertTrue($result);
		static::assertEquals(3, $this->sender->getLastFailed());
		static::assertEquals(0, $this->sender->getLastProcessed());
		static::assertEquals(3, $this->sender->getLastTotal());
		static::assertGreaterThanOrEqual(0.000000001, $this->sender->getLastSpent());
		static::assertArrayHasKey('info', $this->sender->getLastResponseArray());
		static::assertArrayHasKey('response', $this->sender->getLastResponseArray());
		static::assertRegExp(
			'/processed: \d+; failed: \d+; total: \d+; seconds spent: \d+\.\d+/',
			$this->sender->getLastResponseInfo()
		);
	}


	private function addData(Sender $sender)
	{
		$sender->addData('hostname1', 'key1', 'value1');
		$sender->addData('hostname2', 'key2', 'value2');
		$sender->addData('hostname3', 'key3', 'value3', 1234567890);
	}

}
