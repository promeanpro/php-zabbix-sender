<?php

use Net\Zabbix\Agent\ZabbixConfigurator;

class ZabbixAgentConfigTest extends PHPUnit_Framework_TestCase
{
	/** @var ZabbixConfigurator */
	private $zabbixConfigurator;


	public function setUp()
	{
		$this->zabbixConfigurator = new ZabbixConfigurator(__DIR__ . '/zabbix_agentd.conf');
	}


	public function test_getConfigFilename()
	{
		static::assertEquals(__DIR__ . '/zabbix_agentd.conf', $this->zabbixConfigurator->getCurrentConfigFilename());
	}


	public function test_configHasAllKeys()
	{
		$config = $this->zabbixConfigurator->getConfig();
		static::assertArrayHasKey('PidFile', $config);
		static::assertArrayHasKey('LogFile', $config);
		static::assertArrayHasKey('LogFileSize', $config);
		static::assertArrayHasKey('Server', $config);
		static::assertArrayHasKey('ServerActive', $config);
		static::assertArrayHasKey('Hostname', $config);
		static::assertArrayHasKey('Include', $config);
	}


	public function test_configValuesAreCorrect()
	{
		$config = $this->zabbixConfigurator->getConfig();
		static::assertSame('/var/run/zabbix/zabbix_agentd.pid', $config['PidFile']);
		static::assertSame('/var/log/zabbix/zabbix_agentd.log', $config['LogFile']);
		static::assertSame('0', $config['LogFileSize']);
		static::assertSame('127.0.0.1', $config['Server']);
		static::assertSame('127.0.0.1', $config['ServerActive']);
		static::assertSame('Zabbix server', $config['Hostname']);
		static::assertSame('/etc/zabbix/zabbix_agentd.d/', $config['Include']);
	}
}
