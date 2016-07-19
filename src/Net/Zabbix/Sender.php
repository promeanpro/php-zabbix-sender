<?php

namespace Net\Zabbix;

use InvalidArgumentException;
use Net\Zabbix\Exception\SenderNetworkException;
use Net\Zabbix\Exception\SenderProtocolException;

class Sender
{
	/** @var string */
	private $serverName;
	/** @var int */
	private $serverPort;
	/** @var int */
	private $timeout = 30;

	/** @var string */
	private $protocolHeaderString = 'ZBXD';
	/** @var int */
	private $protocolVersion = 1;

	/** @var null */
	private $lastResponseInfo;
	/** @var null */
	private $lastResponseArray;
	/** @var null */
	private $lastProcessed;
	/** @var null */
	private $lastFailed;
	/** @var null */
	private $lastSpent;
	/** @var null */
	private $lastTotal;

	/** @filesource */
	private $socket;
	private $data;


	/**
	 * @param  string $servername
	 * @param  integer $serverport
	 */
	public function __construct($servername = 'localhost', $serverport = 10051)
	{
		$this->setServerName($servername);
		$this->setServerPort($serverport);
		$this->initData();
	}


	/**
	 * main
	 * @throws SenderNetworkException
	 * @throws SenderProtocolException
	 *
	 */
	public function send()
	{
		$sendData = $this->_buildSendData();
		$datasize = strlen($sendData);

		$this->connect();

		/* send data to zabbix server */
		$sentsize = $this->write($this->socket, $sendData);
		if ($sentsize === false or $sentsize != $datasize) {
			throw new SenderNetworkException('cannot receive response');
		}

		/* receive data from zabbix server */
		$recvData = $this->read($this->socket);
		if ($recvData === false) {
			throw new SenderNetworkException('cannot receive response');
		}   

		$this->connectionClose();

		$recvProtocolHeader = substr($recvData, 0, 4);
		if ($recvProtocolHeader === "ZBXD") {
			$responseData = substr($recvData, 13);
			$responseArray = json_decode($responseData, true);
			if ($responseArray === null) {
				throw new SenderProtocolException('Invalid json data in receive data');
			}
			$this->lastResponseArray = $responseArray;
			$this->lastResponseInfo = $responseArray['info'];
			$parsedInfo = $this->parseResponseinfo($this->lastResponseInfo);
			$this->lastProcessed = $parsedInfo['processed'];
			$this->lastFailed = $parsedInfo['failed'];
			$this->lastSpent = $parsedInfo['spent'];
			$this->lastTotal = $parsedInfo['total'];
			if ($responseArray['response'] === 'success') {
				$this->initData();
				return true;
			} else {
				$this->clearLastResponseData();
				return false;
			}
		} else {
			$this->clearLastResponseData();
			throw new SenderProtocolException('invalid protocol header in receive data');
		}
	}


	/**
	 * @param Agent\ZabbixConfigurator $agentConfig
	 * @return $this
	 */
	public function importAgentConfig(Agent\ZabbixConfigurator $agentConfig)
	{
		$this->setServerName($agentConfig->getZabbixServer());
		$this->setServerPort($agentConfig->getServerPort());
		return $this;
	}


	public function initData()
	{
		$this->data = [
			'request' => 'sender data',
			'data' => [],
		];
	}


	/**
	 * @param null|string $hostname
	 * @param null|string $key
	 * @param null|string|int $value
	 * @param $clock
	 * @return $this
	 */
	public function addData($hostname = null, $key = null, $value = null, $clock = null)
	{
		$input = [
			'host' => $hostname,
			'value' => $value,
			'key' => $key,
		];
		if ($clock !== null) {
			$input['clock'] = $clock;
		}
		$this->data['data'][] = $input;
		return $this;
	}


	/**
	 * @return mixed
	 */
	public function getDataArray()
	{
		return $this->data['data'];
	}


	/**
	 * @return string
	 */
	private function _buildSendData()
	{
		$json_data = json_encode(array_map(
				function ($t) {
					return is_string($t) ? utf8_encode($t) : $t;
				},
				$this->data
			)
		);
		$json_length = strlen($json_data);
		$data_header = pack('aaaaCV2',
			$this->protocolHeaderString[0],
			$this->protocolHeaderString[1],
			$this->protocolHeaderString[2],
			$this->protocolHeaderString[3],
			(int)$this->protocolVersion,
			$json_length,
			$json_length >> 32
		);
		return ($data_header . $json_data);
	}


	/**
	 * @param null $info
	 * @return array|null
	 */
	protected function parseResponseinfo($info = null)
	{
		# info: "Processed 1 Failed 1 Total 2 Seconds spent 0.000035"
		$parsedInfo = null;
		if ($info !== null) {
			list(, $processed, , $failed, , $total, , , $spent) = explode(' ', $info);
			$parsedInfo = [
				'processed' => (int)$processed,
				'failed' => (int)$failed,
				'total' => (int)$total,
				'spent' => $spent,
			];
		}
		return $parsedInfo;
	}


	private function clearLastResponseData()
	{
		$this->lastResponseInfo = null;
		$this->lastResponseArray = null;
		$this->lastProcessed = null;
		$this->lastFailed = null;
		$this->lastSpent = null;
		$this->lastTotal = null;
	}


	private function connectionClose()
	{
		if ($this->socket) {
			fclose($this->socket);
		}
	}


	/**
	 * Connect to Zabbix server
	 * @throws SenderNetworkException
	 *
	 */
	private function connect()
	{
		$this->socket = @fsockopen($this->serverName,
			(int)$this->serverPort,
			$errno,
			$errmsg,
			$this->timeout);
		if (!$this->socket) {
			throw new SenderNetworkException($errmsg);
		}
	}


	/**
	 * Write data to socket
	 * @param $socket
	 * @param $data
	 * @return bool|int
	 * @throws \Net\Zabbix\Exception\SenderNetworkException
	 */
	private function write($socket, $data)
	{
		if (!$socket) {
			throw new SenderNetworkException('Socket is not writable, connection failed.');
		}
		$totalWritten = 0;
		$length = strlen($data);
		while ($totalWritten < $length) {
			$writeSize = @fwrite($socket, $data);
			if ($writeSize === false) {
				return false;
			} else {
				$totalWritten += $writeSize;
				$data = substr($data, $writeSize);
			}
		}
		return $totalWritten;
	}


	/**
	 * Read data from socket
	 * @param $socket
	 * @return bool|string
	 * @throws \Net\Zabbix\Exception\SenderNetworkException
	 */
	private function read($socket)
	{
		if (!$socket) {
			throw new SenderNetworkException('socket was not readable,connect failed.');
		}
		$recvData = '';
		while (!feof($socket)) {
			$buffer = fread($socket, 8192);
			if ($buffer === false) {
				return false;
			}
			$recvData .= $buffer;
		}
		return $recvData;
	}


	/**
	 * @param $serverName
	 * @return $this
	 */
	public function setServerName($serverName)
	{
		$this->serverName = $serverName;
		return $this;
	}


	/**
	 * @param int $serverPort
	 * @return $this
	 * @throws InvalidArgumentException
	 */
	public function setServerPort($serverPort)
	{
		if (is_int($serverPort)) {
			$this->serverPort = $serverPort;
		}
		return $this;
	}


	/**
	 * @param int $timeout
	 * @return $this
	 */
	public function setTimeout($timeout = 0)
	{
		if ((is_int($timeout) or is_numeric($timeout)) and intval($timeout) > 0) {
			$this->timeout = $timeout;
		}
		return $this;
	}


	/**
	 * @return int
	 */
	public function getTimeout()
	{
		return $this->timeout;
	}


	/**
	 * @param $headerString
	 * @return $this
	 */
	public function setProtocolHeaderString($headerString)
	{
		$this->protocolHeaderString = $headerString;
		return $this;
	}


	/**
	 * @param $version
	 * @return $this
	 */
	public function setProtocolVersion($version)
	{
		if (is_int($version) and $version > 0) {
			$this->protocolVersion = $version;
		}
		return $this;
	}


	/**
	 * @return null
	 */
	public function getLastResponseInfo()
	{
		return $this->lastResponseInfo;
	}


	/**
	 * @return null|array
	 */
	public function getLastResponseArray()
	{
		return $this->lastResponseArray;
	}


	/**
	 * @return null
	 */
	public function getLastProcessed()
	{
		return $this->lastProcessed;
	}


	/**
	 * @return null
	 */
	public function getLastFailed()
	{
		return $this->lastFailed;
	}


	/**
	 * @return null
	 */
	public function getLastSpent()
	{
		return $this->lastSpent;
	}


	/**
	 * @return null
	 */
	public function getLastTotal()
	{
		return $this->lastTotal;
	}
}


