<?php
/**
 * Created by PhpStorm.
 * User: kdays
 * Date: 14/12/30
 * Time: 10:16
 */

namespace Akari\system\exception;

use Akari\Context;
use Akari\system\http\HttpCode;
use Akari\system\http\Response;
use Akari\utility\helper\ResultHelper;
use \Exception;

/**
 * Class FrameworkExceptionHandler
 * 分发器异常的处理，没有找到URI会转发到本异常处理器
 * 相关配置可见config，主要是页面找不到时默认地址
 *
 * @package Akari\system\exception
 */
Class FrameworkExceptionHandler {

    use ResultHelper;

    public function handleException(Exception $ex) {
        $config = Context::$appConfig;
        $response = Response::getInstance();

        // 调用框架的模板
        $view = function($path, $data) {
            ob_start();
            @extract($data, EXTR_PREFIX_SAME, 'a_');
            include(AKARI_PATH. '/template/'. $path. '.php');
            $content = ob_get_contents();
            ob_end_clean();

            return $content;
        };

        // CLI模式时为了方便调试 任何错误不捕获时全调用
        if (CLI_MODE) {
            return $this->_genTEXTResult($ex);
        }

        switch (get_class($ex)) {

            // 没有找到URI
            case 'Akari\system\router\NotFoundURI':
                $response->setStatusCode(HttpCode::NOT_FOUND);
                if (file_exists($config->notFoundTemplate)) {
                    return $this->_genTplResult([], $config->notFoundTemplate);
                } else {
                    // 处理$ex
                    if ($ex->getPrevious() !== NULL) {
                        $msg = $ex->getPrevious()->getMessage();
                    } else {
                        $msg = $ex->getMessage();
                    }

                    return $this->_genHTMLResult(
                        $view(404, [
                            'msg' => $msg,
                            "url" => Context::$uri,
                            "index" => Context::$appConfig->appBaseURL
                        ])
                    );
                }

            // 系统的fatal
            case 'Akari\system\exception\FatalException':
                $response->setStatusCode(HttpCode::INTERNAL_SERVER_ERROR);
                if (file_exists($config->serverErrorTemplate)) {
                    return $this->_genTplResult([], $config->serverErrorTemplate);
                } else {
                    return $this->_genHTMLResult(
                        $view(500, [
                            "message" => $ex->getMessage(),
                            "file" => basename($ex->getFile())
                        ])
                    );
                }
        }

    }



}