<?php
namespace Akari\system\data;

use Akari\utility\BenchmarkHelper;
use \Exception;
use \Memcache;

!defined("AKARI_PATH") && exit;

Class MemcacheAdapter extends BaseCacheAdapter{
	public function __construct($confId = 'default'){
		if(!class_exists("memcache")){
			throw new Exception("[Akari.Data.MemcacheAdapter] server not found memcache");
		}

		$options = $this->getOptions("memcache", $confId, Array(
			"host"		=> "127.0.0.1",
			"port"		=> 11211,
			"timeout"	=> 30,
			"prefix"	=> ''
		));
		$this->options = $options;

		$this->handler = new Memcache();
		if(!$this->handler->connect($options['host'], $options['port'], $options['timeout'])){
			throw new Exception("[Akari.Data.MemcacheAdapter] Connect $options[host] Error");
		}
	}

	public function remove($name){
		return $this->handler->delete($this->options['prefix'].$name);
	}
	
	public function get($name, $defaultValue = NULL) {
		$result = $this->handler->get($this->options['prefix'].$name);
        if (!$result) {
            $this->benchmark(BenchmarkHelper::FLAG_MISS);
            return $defaultValue;
        }

        $this->benchmark(BenchmarkHelper::FLAG_HIT);
        return $result;
	}

	/**
	 * 写入缓存
     *
	 * @param string $name 缓存变量名
	 * @param mixed $value  存储数据
	 * @param integer $expire  有效时间（秒）
	 * @return boolean
	 */
	public function set($name, $value, $expire = null) {
		if(is_null($expire)) {
			$expire = $this->options['expire'];
		}

		$name = $this->options['prefix'].$name;
		if($this->handler->set($name, $value, 0, $expire)) {
			return true;
		}

		return false;
	}
}