<?php
!defined("AKARI_PATH") && exit;

Class Dispatcher{
	private $config;
	private static $r;
	
	/**
	 * 单例
	 * @return Dispatcher
	 */
	public static function getInstance() {
		if (self::$r == null) {
			self::$r = new self();
		}
		return self::$r;
	}
	
	/**
	 * 构造函数
	 */
	private function __construct(){
		$this->config = Context::$appConfig;
	}
	
	/**
	 * CLI模式下 任务路径的分发
	 * 
	 * @param string $URI uri路径
	 * @return boolean|string
	 */
	public function invokeTask($URI = ''){
		$list = explode("/", $URI);
		$taskName = array_shift($list);

		$path = Context::$appBasePath."/app/task/$taskName.php";
		if(!file_exists($path)){
			Logging::_logErr("Task [ $taskName ] Not Found");
			return false;
		}

		return $path;
	}

	/**
	 * 临时注册某个路由
	 * 
	 * @param string $re URL匹配正则
	 * @param string $url 重写到哪个文件
	 */
	public function register($re, $url){
		Context::$appConfig->URLRewrite[$re] = $url;
	}

	/**
	 * 根据URL数组查找
	 * 
	 * @param array|string $list URL数组
	 * @return string|boolean
	 **/
	public function findPath($list, $dir = "action", $ext = ".php"){
		if(!is_array($list))	$list = explode("/", $list);

		$basePath = Context::$appBasePath."/app/$dir/";
		$count = count($list);

		if($count > 10){
			throw new Exception("invalid URI");
		}

		// 如果有子目录的操作会处理发送最近一个
		if($count > 1){
			for($i = 0; $i < $count - 1; $i++){
				$filename = array_pop($list);
				$name = implode(DIRECTORY_SEPARATOR, $list);

				$path = $basePath.$name.DIRECTORY_SEPARATOR.$filename.$ext;
				if(file_exists($path)){
					return $path;
				}

				$path = $basePath.$name.DIRECTORY_SEPARATOR."default".$ext;
				if(file_exists($path)){
					return $path;
				}
			}
		}

		//首先检查是否有类似名称的
		if(file_exists($path = $basePath.array_shift($list).$ext)){
			return $path;
		}

		if($count == 1 && file_exists($path = $basePath."default".$ext)){
			 return $path;
		}
		return false;
	}
	
	/**
	 * 由于应用使用了Context::$appBaseURL作为基础连接
	 * 但某些时候，如ajax之类 必须保证http和https在一个页面才可以触发
	 *
	 * @param string 
	 */
	public function rewriteBaseURL($URI) {
		$isSSL = Request::getInstance()->isSSL();
		$URI = preg_replace('/https|http/i', $isSSL ? 'https' : 'http' , $URI);

		return $URI;
	}

	/**
	 * 通常请求下的分配路径
	 * 
	 * @param string $URI URI路径
	 * @throws Exception
	 * @return string|boolean
	 */
	public function invoke($URI = ''){
		$list = explode("/", $URI);

		$basePath = Context::$appBasePath."/app/action/";
		$URLRewrite = Context::$appConfig->URLRewrite;

		foreach($URLRewrite as $key => $value){
			if(preg_match($key, $URI)){
				if(is_callable($value)){
					$value = $value($URI);
					if($value){
						$list = $value;
						break;
					}
				}else{
					if(stripos($value, 'app/') === false){
						$value = "/app/action/$value";
					}
				}
			}
		}

		return $this->findPath($list);
	}
}