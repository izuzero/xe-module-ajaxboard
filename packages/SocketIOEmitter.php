<?php
/*! https://github.com/rase-/socket.io-php-emitter */
/**
 * @class       SocketIOEmitter
 * @author      Tony Kovanen (tony.kovanen@gmail.com)
 * @regenerator Eunsoo Lee (contact@ajaxboard.co.kr)
 */

if (!function_exists('msgpack_pack'))
{
	require_once(__DIR__ . '/msgpack_pack.php');
}

class Binary
{
	public function __construct($binarystring)
	{
		$this->binarystring = $binarystring;
	}

	public function __toString()
	{
		return $this->binarystring;
	}
}

class SocketIOEmitter
{
	const EVENT = 2;

	const BINARY_EVENT = 5;

	public function __construct($redis = FALSE, $opts = array())
	{
		if (is_array($redis))
		{
			$opts = $redis;
			$redis = FALSE;
		}

		$opts = array_merge(array('host' => '127.0.0.1', 'port' => 6379), $opts);

		if (!$redis)
		{
			if (extension_loaded('redis'))
			{
				if (!isset($opts['socket']))
				{
					if (!isset($opts['host']))
					{
						throw new Exception('Host should be provided when not providing a redis instance');
					}
					if (!isset($opts['port']))
					{
						throw new Exception('Port should be provided when not providing a redis instance');
					}
				}

				$redis = new Redis();
				if (isset($opts['socket']))
				{
					$redis->connect($opts['socket']);
				}
				else
				{
					$redis->connect($opts['host'], $opts['port']);
				}
			}
			else
			{
				$redis = new TinyRedisClient($opts['host'] . ':' . $opts['port']);
			}
		}

		if (!is_callable(array($redis, 'publish')))
		{
			throw new Exception('The Redis client provided is invalid. The client needs to implement the publish method. Try using the default client.');
		}

		$this->key = (isset($opts['key']) ? $opts['key'] : 'socket.io') . '#emitter';
		$this->redis = $redis;
		$this->_rooms = array();
		$this->_flags = array();
	}

	public function __get($flag)
	{
		$this->_flags[$flag] = TRUE;
		return $this;
	}

	private function readFlag($flag)
	{
		return isset($this->_flags[$flag]) ? $this->_flags[$flag] : FALSE;
	}

	public function in($room)
	{
		if (!in_array($room, $this->_rooms))
		{
			$this->_rooms[] = $room;
		}

		return $this;
	}

	public function to($room)
	{
		return $this->in($room);
	}

	public function of($nsp)
	{
		$this->_flags['nsp'] = $nsp;
		return $this;
	}

	public function emit()
	{
		$args = func_get_args();
		$arglen = count($args);
		$packet = array('type' => self::EVENT);

		for ($i = 0; $i < $arglen; $i++)
		{
			$arg = $args[$i];
			if ($arg instanceof Binary)
			{
				$args[$i] = strval($arg);
				$this->binary;
			}
		}

		$packet['data'] = $args;
		if ($this->readFlag('binary'))
		{
			$packet['type'] = self::BINARY_EVENT;
		}

		if (isset($this->_flags['nsp']))
		{
			$packet['nsp'] = $this->_flags['nsp'];
			unset($this->_flags['nsp']);
		}
		else
		{
			$packet['nsp'] = '/';
		}

		$packed = msgpack_pack(array($packet, array(
			'rooms' => $this->_rooms,
			'flags' => $this->_flags
		)));

		if ($packet['type'] == self::BINARY_EVENT)
		{
			$packed = str_replace(pack('c', 0xda), pack('c', 0xd8), $packed);
			$packed = str_replace(pack('c', 0xdb), pack('c', 0xd9), $packed);
		}

		$this->redis->publish($this->key, $packed);
		$this->_rooms = array();
		$this->_flags = array();

		return $this;
	}
}

/* End of file SocketIOEmitter.php */
/* Location: ./modules/ajaxboard/packages/SocketIOEmitter.php */
