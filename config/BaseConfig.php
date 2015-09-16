<?php
/**
 * Created by PhpStorm.
 * User: kdays
 * Date: 14/12/27
 * Time: 14:30
 */

namespace Akari\config;

use Akari\Context;
use Akari\exception\ExceptionProcessor;

Class BaseConfig {

    public $appName = "Application";
    public $appBaseURL;
    public $defaultURI = 'index';

    public $notFoundTemplate = "";
    public $serverErrorTemplate = "";

    // 如果没有result时的callback
    public $nonResultCallback = NULL;

    public $cache = [
        'default' => [
            'handler' => 'Akari\system\cache\handler\FileCacheHandler',
            'baseDir' => '/runtime/cache/',
            'indexPath' => 'index.json'
        ],

        'redis' => [
            'handler' => 'Akari\system\cache\handler\RedisCacheHandler',
            'host' => '127.0.0.1'
        ]
    ];

    public $database = [];

    public $defaultExceptionHandler = 'Akari\system\exception\DefaultExceptionHandler';

    public $uriMode = AKARI_URI_AUTO;
    public $uriSuffix = '';

    public $templateSuffix = ".htm";
    public $templateCacheDir = '/runtime/tpl';

    public $encrypt = [
        'default' => [
            'cipher' => 'Akari\system\security\cipher\AESCipher',
            'key' => 'Hello, Akari Framework'
        ],

        'cookie' => [
            'cipher' => 'Akari\system\security\cipher\AESCipher',
            'key' => 'Answer is 42.'
        ]
    ];

    public $cookiePrefix = 'w_';
    public $cookieTime = 86400;
    public $cookiePath = '/';
    public $cookieSecure = false;
    public $cookieDomain = '';
    public $csrfTokenName = '_akari';

    public $trigger = [
        // URL路由分发前，可以对URL进行简单处理
        'beforeDispatch' => [
            //['/\.json/', 'JSONSupport']
        ],

        // URL路由分发后，执行操作前，可以对权限之类进行检查
        'applicationStart' => [],

        // 执行操作后返回结果的处理，可以记录性能或者繁体化等
        'applicationEnd' => []
    ];

    public $uriRewrite = [];

    public $uploadDir = 'web/attachment/';
    public $allowUploadExt = [];

    public function getDBConfig($name = "default"){
        if(!is_array(current($this->database)))	return $this->database;
        if($name == "default")	return current($this->database);
        if (!isset($this->database[$name])) throw new \Exception("not found DB config: ". $name);
        
        return $this->database[$name];
    }

    /**
     * @var string $key
     * @return null
     */
    public function loadExternalConfig($key) {
        $namePolicies = [
            Context::$mode. DIRECTORY_SEPARATOR. $key,
            Context::$mode. ".". $key,
            $key
        ];
        $baseConfig = Context::$appEntryPath. DIRECTORY_SEPARATOR. "config". DIRECTORY_SEPARATOR;

        foreach ($namePolicies as $name) {
            if (file_exists($baseConfig. $name. ".php")) {
                return include($baseConfig. $name. ".php");
            }

            if (file_exists($baseConfig. $name. ".yml")) {
                return \Spyc::YAMLLoad($baseConfig. $name. ".yml");
            }

        }
        return NULL;
    }

    public function __get($key) {
        if (!isset($this->$key)) {
            $this->$key = $this->loadExternalConfig($key);
        }

        return isset($this->$key) ? $this->$key : NULL;
    }

    public static $c;
    public static function getInstance(){
        $h = get_called_class();
        if (!self::$c){
            self::$c = new $h();
        }

        return self::$c;
    }
}