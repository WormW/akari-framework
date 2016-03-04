<?php
/**
 * Created by PhpStorm.
 * User: kdays
 * Date: 14/12/27
 * Time: 14:56
 */

namespace Akari\utility\helper;

use Akari\system\tpl\TemplateHelper;
use Akari\utility\DataHelper;

trait ValueHelper {

    /**
     * 获得在DataHelper的数据 不会在模板展现
     *
     * @param string $key
     * @param bool $subKey
     * @param null $defaultValue
     * @return array|null|object
     */
    protected static function _getValue($key, $subKey = false, $defaultValue = NULL) {
        return DataHelper::get($key, $subKey, $defaultValue);
    }

    /**
     * 设置DataHelper的数据，不会在模板展现，模板展现用_bindValue
     *
     * @param string $key
     * @param mixed $data
     * @param bool $isOverwrite
     * @return bool
     */
    protected static function _setValue($key, $data, $isOverwrite = TRUE) {
        return DataHelper::set($key, $data, $isOverwrite);
    }

}