<?php

namespace Net\Zabbix\Agent;

/**
 * Class Config
 * @package Net\Zabbix\Agent
 */
class ZabbixConfigurator
{
	/* @var string */
	private $configFilePath;
	/* @var array */
	private $config = [];


	/**
	 * Config constructor.
	 * @param string|null $filename
	 */
	public function __construct($filename = null)
	{
		$this->configFilePath = isset($filename) && is_readable($filename)
			? $filename : '/etc/zabbix/zabbix_agentd.conf';
		$this->config = $this->load($this->configFilePath);
	}


	/**
	 * @return array
	 */
	public function getConfig()
	{
		return $this->config;
	}


	/**
	 * @return string|null
	 */
	public function getZabbixServer()
	{
		$zabbixServer = null;
		if (array_key_exists('Server', $this->config)) {
			$zabbixServer = $this->config['Server'];
		}
		return $zabbixServer;
	}


	/**
	 * @return int|null
	 */
	public function getServerPort()
	{
		$serverPort = null;
		if (array_key_exists('ServerPort', $this->config)
			and is_numeric($this->config['ServerPort'])
		) {
			$serverPort = (int)$this->config['ServerPort'];
		}
		return $serverPort;
	}


	/**
	 * @return string|null
	 */
	public function getCurrentConfigFilename()
	{
		return $this->configFilePath;
	}


	/**
	 * @param string|null $filename
	 * @return array
	 */
	private function load($filename = null)
	{
		$config = [];
		if ($filename !== null && is_readable($filename)) {
			$configLines = file($filename);
			$configLines = preg_grep("/^\s*[A-Za-z].+\=.+/", $configLines);
			foreach ($configLines as $number => $line) {
				list($key, $value) = explode('=', $line, 2);
				$key = trim($key);
				$value = trim($value);
				$config[$key] = $value;
			}
		}
		return $config;
	}
}


