<?php
/**
 * Created by PhpStorm.
 * User: kdays
 * Date: 14/12/31
 * Time: 10:42
 */

namespace Akari\action;

use Akari\system\console\ConsoleInput;
use Akari\system\console\ConsoleOutput;
use Akari\system\console\Request;
use Akari\system\ioc\DIHelper;
use Akari\utility\helper\ExceptionSetter;
use Akari\utility\helper\Logging;
use Akari\utility\helper\ValueHelper;

Class BaseTask {

    use Logging, ValueHelper, ExceptionSetter, DIHelper;

    protected $input;

    protected $output;

    protected $params;

    public function __construct() {
        $this->input = new ConsoleInput();
        $this->output = new ConsoleOutput();

        $this->params = Request::getInstance()->getParams();
    }

    public function _getQuery($key, $defaultValue = NULL) {
        return isset($this->params[$key]) ? $this->params[$key] : $defaultValue;
    }

    public function _getInput() {
        return trim($this->input->read());
    }

    public function message($message) {
        return $this->output->write($message);
    }
}