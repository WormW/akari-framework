<?php
namespace Akari\system\router;

use Akari\Context;
use Akari\system\http\Request;

!defined("AKARI_PATH") && exit;

Class Router{
    private $config;
    private $request;
    private static $r;

    public static function getInstance() {
        if (self::$r == null) {
            self::$r = new self();
        }
        return self::$r;
    }

    private function __construct(){
        $this->request = Request::getInstance();
        $this->config = Context::$appConfig;
    }

    private function clearURI($uri){
        $queryString = $_SERVER['QUERY_STRING'];
        if(strlen($queryString) > 0){
            $uri = substr($uri, 0, -strlen($queryString)  -1);
        }

        $scriptName = Context::$appEntryName;
        $scriptNameLen = strlen($scriptName);
        if (substr($uri, 1, $scriptNameLen) === $scriptName) {
            $uri = substr($uri, $scriptNameLen + 1);
        }

        return $uri;
    }

    public function resolveURI(){
        $uri = null;

        $config = $this->config;
        switch($config->uriMode){
            case AKARI_URI_AUTO:
                $uri = $this->request->getPathInfo();
                if(empty($uri)){
                    if(isset($_GET['uri']))	$uri = $_GET['uri'];
                    if(empty($uri)){
                        $uri = $this->clearURI($this->request->getRequestURI());
                    }
                }
                break;

            case AKARI_URI_PATHINFO:
                $uri = $this->request->getPathInfo(); break;

            case AKARI_URI_QUERYSTRING:
                if(isset($_GET['uri']))	$uri = $_GET['uri']; break;

            case AKARI_URI_REQUESTURI:
                $uri = $this->clearURI($this->request->getRequestURI());break;
        }

        $urlInfo = parse_url(Context::$appConfig->appBaseURL);

        // 如果基础站点URL不是根目录时
        if (isset($urlInfo['path']) && $urlInfo['path'] != '/') {
            $uriPrefix = rtrim($urlInfo['path'], '/');
            $uriPrefixLength = strlen($uriPrefix);
            if (substr($uri, 0, $uriPrefixLength) === $uriPrefix) {
                $uri = substr($uri, $uriPrefixLength);
            }
        }

        $uri = preg_replace('/\/+/', '/', $uri); //把多余的//替换掉..

        if(!$uri || $uri == '/' || $uri == '/'.Context::$appEntryName){
            $uri = $config->defaultURI;
        }

        $uriParts = explode('/', $uri);
        if(count($uriParts) < 3){
            //$uri = dirname($config->defaultURI).'/'.array_pop($uriParts);
        }

        if (substr($uri, -1) === '/') {
            $uri .= 'index';
        }else{
            if (!empty($config->uriSuffix)) {
                $suffix = substr($uri, -strlen($config->uriSuffix));
                if ($suffix === $config->uriSuffix) {
                    $uri = substr($uri, 0, -strlen($config->uriSuffix));
                } else {
                    throw new \Exception('Invalid URI');
                }
            }
        }

        if($uri[0] == '/'){
            $uri = substr($uri, 1);
        }

        return $uri;
    }
}