<?php
namespace Akari\system\http;

use Akari\system\ioc\Injectable;
use Akari\system\security\FilterFactory;

Class Request extends Injectable{
    
    protected $requestMethod;
    protected $requestURI;
    protected $host;
    protected $remoteIP;
    protected $serverIP;
    protected $queryString;
    protected $accept;
    protected $referrer;
    protected $scriptName;
    protected $userAgent;
    protected $requestTime;
    protected $pathInfo;

    /**
     * 构造函数
     */
    public function __construct(){
        $arr = Array(
            'requestMethod' => 'REQUEST_METHOD',
            'requestURI' => 'REQUEST_URI',
            'host' => 'HTTP_HOST',
            'remoteIP' => 'REMOTE_ADDR',
            'serverIP' => 'SERVER_ADDR',
            'serverPort' => 'SERVER_PORT',
            'queryString' => 'QUERY_STRING',
            'accept' => 'HTTP_ACCEPT',
            'referrer' => 'HTTP_REFERER',
            'scriptName' => 'SCRIPT_NAME',
            'userAgent' => 'HTTP_USER_AGENT',
            'pathInfo' => 'PATH_INFO'
        );

        foreach($arr as $key => $value){
            if(isset($_SERVER[$value])){
                $this->$key = $_SERVER[$value];
            } 
        }

        $this->requestTime = isset($_SERVER['REQUEST_TIME']) ? $_SERVER['REQUEST_TIME'] : TIMESTAMP;
    }

    // http://cn2.php.net/manual/en/function.ip2long.php#104163
    private function bin2ip($bin) {
        if(strlen($bin) <= 32) {
            return long2ip(base_convert($bin,2,10));
        } // 32bits (ipv4)

        if(strlen($bin) != 128) {
            return false;
        }

        $pad = 128 - strlen($bin);
        for ($i = 1; $i <= $pad; $i++) {
            $bin = "0". $bin;
        }

        $ipv6 = "";
        $bits = 0;
        while ($bits <= 7) {
            $bin_part = substr($bin, ($bits * 16), 16);
            $ipv6 .= dechex(bindec($bin_part)).":";
            $bits++;
        }

        return inet_ntop(inet_pton(substr($ipv6, 0, -1)));
    }

    /**
     * 获得用户IP
     * @return string
     */
    public function getUserIP() {
        $onlineIp = $this->getRemoteIP();

        // try ipv6 => ipv4
        if (filter_var($onlineIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $ipv4Address = $this->bin2ip(base_convert(ip2long($onlineIp), 10, 2));
            if ($ipv4Address) $onlineIp = $ipv4Address;
        }

        $isInternal = false;
        $ipAddress = explode(".", $onlineIp);
        if ($ipAddress[0] == 10) {
            $isInternal = true;
        } elseif ($ipAddress[0] == 172 && $ipAddress[1] > 15 && $ipAddress[1] < 32) {
            $isInternal = true;
        } elseif ($ipAddress[0] == 192 && $ipAddress[1] == 168) {
            $isInternal = true;
        }

        // 如果确定是内网IP的话 再检查X-FORWARDED-FOR字段,避免伪造
        if ($isInternal && isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $onlineIp = $_SERVER['HTTP_X_FORWARDED_FOR'];

            if ($onlineIp && strstr($onlineIp, ',')) {
                $x = explode(',', $onlineIp);
                $onlineIp = end($x);
            }
        }

        if (!filter_var($onlineIp, FILTER_VALIDATE_IP)) {
            $onlineIp = '0.0.0.0';
        }

        return $onlineIp;
    }

    /**
     * 链接是否是SSL安全
     * @return bool
     */
    public function isSSL() {
        $protocol = strtoupper($_SERVER['SERVER_PROTOCOL']);
        if (isset($_SERVER['HTTPS'])) {
            return !!(strtoupper($_SERVER['HTTPS']) == 'ON');
        }
        
        if (isset($_SERVER['KEL_SSL'])) { // HOSTKER判断字段
            return TRUE;
        }

        return !!(strpos($protocol, 'HTTPS') !== FALSE);
    }

    /**
     * 获得请求的字符串
     * @return string
     */
    public function getQueryString() {
        return $this->queryString;
    }

    /**
     * 获得PathInfo
     * @return string
     */
    public function getUrlPathInfo(){
        return $this->pathInfo;
    }

    /**
     * @return int
     */
    public function getRequestTime() {
        return $this->requestTime;
    }

    /**
     * 获得引用页路径
     * @return string
     */
    public function getReferrer(){
        return $this->referrer;
    }

    /**
     * 获得远程IP
     * @return string
     */
    public function getRemoteIP() {
        return $this->remoteIP;
    }

    /**
     * 获得服务器IP
     * @return string
     */
    public function getServerIP() {
        return $this->serverIP;
    }

    /**
     * 获得请求的URI
     * @return string
     */
    public function getRequestURI() {
        return $this->requestURI;
    }

    /**
     * @return string
     */
    public function getFullURI() {
        return $this->host. $this->requestURI;
    }

    /**
     * @return string
     */
    public function getHost() {
        return $this->host;
    }

    /**
     * 获得请求的脚本名称
     * @return string
     */
    public function getScriptName(){
        return $this->scriptName;
    }

    /**
     * 获得userAgent
     * @return string
     */
    public function getUserAgent(){
        return $this->userAgent;
    }

    /**
     * 获得不带参数的URL地址
     * 
     * @return string
     */
    public function getUrlPath() {
        $url = $this->requestURI;
        $urlInfo = parse_url($url);

        return $urlInfo['path'];
    }

    /**
     * 获得请求模式 (PUT/GET/POST)
     * @return mixed
     */
    public function getRequestMethod() {
        return strtoupper($this->requestMethod);
    }

    /**
     * 是否是POST请求
     *
     * @return bool
     */
    public function isPost() {
        return $this->getRequestMethod() == 'POST';
    }

    /**
     * @return bool
     */
    public function isGet() {
        return $this->getRequestMethod() == 'GET';
    }

    /**
     * GET或者POST是否有参数
     * 
     * @param string $key
     * @return bool
     */
    public function has($key) {
        return array_key_exists($key, $_REQUEST);
    }

    /**
     * 获得REQUEST(GET或者POST)下的参数
     *
     * @param string|NULL $key
     * @param mixed $defaultValue
     * @param string $filter
     * @return mixed
     */
    public function get($key, $defaultValue = NULL, $filter = "default") {
        if ($key == NULL) return $_REQUEST;
        if (array_key_exists($key, $_REQUEST)) {
            return FilterFactory::doFilter($_REQUEST[$key], $filter);
        }
        
        return $defaultValue;
    } 

    /**
     * POST中是否有参数
     * 
     * @param string $key
     * @return bool
     */
    public function hasPost($key) {
        return array_key_exists($key, $_POST);
    }

    /**
     * 获得POST参数
     *
     * @param string|NULL $key
     * @param mixed $defaultValue
     * @param string $filter
     * @return mixed
     */
    public function getPost($key, $defaultValue = NULL, $filter = "default") {
        if ($key == NULL) return $_POST;
        if (array_key_exists($key, $_POST)) {
            return FilterFactory::doFilter($_POST[$key], $filter);
        }
        
        return $defaultValue;
    }

    public function hasQuery($key) {
        return array_key_exists($key, $_GET);
    }

    /**
     * 获得GET的参数
     *
     * @param string|NULL $key
     * @param mixed $defaultValue
     * @param string $filter
     * @return mixed
     */
    public function getQuery($key, $defaultValue = NULL, $filter = "default") {
        if ($key == NULL) return $_GET;
        if (array_key_exists($key, $_GET)) {
            return FilterFactory::doFilter($_GET[$key], $filter);
        }
        return $defaultValue;
    }

    /**
     * 是否是Ajax请求
     * @return bool
     */
    public function isAjax() {
        if ($this->hasHeader('HTTP_SEND_BY')) {
            return TRUE;
        }
        
        // Andorid游览器也会发送这个头 内容是X-Requested-With	com.android.browser 所以必须特殊判断
        if ($this->hasHeader('HTTP_X_REQUESTED_WITH')) {
            $requestedWith = $this->getHeader('HTTP_X_REQUESTED_WITH');
            if (strtolower($requestedWith) == strtolower('XMLHttpRequest')) {
                return TRUE;
            }
        }
        
        return FALSE;
    }

    /**
     * 是否是移动设备
     * @return bool
     */
    public function isMobileDevice() {
        if ($this->isIOSDevice()) {
            return True;
        }

        $ua = $this->getUserAgent();
        $keyword = ["ucweb", "Windows Phone", "android", "opera mini", "blackberry"];
        foreach ($keyword as $value) {
            if (preg_match("/$value/i", $ua)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string
     */
    public function getRawBody() {
        return file_get_contents('php://input');
    }

    /**
     * 是否是iphone
     * @return bool
     */
    public function isIOSDevice(){
        $ua = $this->getUserAgent();

        return (preg_match('/ipod/i', $ua) || preg_match('/iphone/i', $ua));
    }

    /**
     * 判断是否是微信的
     * @return bool
     */
    public function isInWeChat() {
        $ua = $this->getUserAgent();
        return (preg_match('/MicroMessenger/i', $ua) || preg_match('/Window Phone/i', $ua));
    }

    /**
     * 获得所有文件上传
     * 
     * @param bool $skipNoFiles
     * @return FileUpload[]
     */
    public function getUploadedFiles($skipNoFiles = TRUE) {
        $files = [];

        foreach ($_FILES as $key => $now) {
            // 处理检查是否是multi多维数组 如果是的话 那么需要单独处理
            if (is_array($now['error'])) {
                $keys = array_keys($now);
                $counter = array_keys($now['error']);
                
                foreach ($counter as $c) {
                    $values = [];
                    foreach ($keys as $k) {
                        $values[] = $now[$k][$c];
                    }
                    
                    $form = array_combine($keys, $values);
                    if ($form['error'] == UPLOAD_ERR_NO_FILE && $skipNoFiles) {
                        continue;
                    }
                    
                    $form['multiKey'] = $c;
                    $form['multiBase'] = $key;
                    $files[] = new FileUpload($form, $key. ".". $c);
                }
            } else {
                if ($now['error'] == UPLOAD_ERR_NO_FILE && $skipNoFiles) {
                    continue;
                }
                
                $files[] = new FileUpload($now, $key);
            }
        }

        return $files;
    }

    /**
     * @param string $name 如果用数组传递时 比如Upload[2]时 可以使用Upload[]作为name查询 这个时候会返回数组 或者 使用key 比如Upload.2查询,直接使用Upload是查不到的
     * @param bool $skipNoFiles
     * @return FileUpload|null|FileUpload[]
     */
    public function getUploadedFile($name, $skipNoFiles = TRUE) {
        $files = self::getUploadedFiles($skipNoFiles);
        
        // 检索的时候注意 如果使用的是upload[]的时候 代表返回查询的是.开头有upload的字段 故可能可以返回多个数组
        if (in_string($name, '[]')) {
            $result = [];
            
            $checkKey = substr($name, 0, -2);
            foreach ($files as $file) {
                $name = $file->getName();
                
                $pos = strpos($name, ".");
                if ($pos > 0 && substr($name, 0, $pos) == $checkKey) {
                    $result[] = $file;
                }
            }
            
            return $result;
        } else {
            foreach ($files as $file) {
                if ($file->getName() == $name) {
                    return $file;
                }
            }
            
            return NULL;
        }
        
        
    }

    /**
     * @return bool
     */
    public function hasFiles() {
        return count($_FILES) > 0;
    }
    
    /**
     * 是否有Cookie
     * 
     * @param string $name
     * @return bool
     */
    public function hasCookie($name) {
        return $this->cookies->exists($name);
    }

    /**
     * 获得Cookies
     * 也可以在Controller中直接$this->cookies方法操作
     * 
     * @param string $name
     * @param bool $autoPrefix
     * @return array|mixed|null
     */
    public function getCookie($name, $autoPrefix = TRUE) {
        return $this->cookies->get($name, $autoPrefix);
    }
    
    public function hasHeader($key) {
        return array_key_exists($key, $_SERVER);
    }
    
    public function getHeader($key, $defaultValue = NULL, $filter = "default") {
        if ($this->hasHeader($key)) {
            return FilterFactory::doFilter($_SERVER[$key], $filter);
        }
        
        return $defaultValue;
    }
}