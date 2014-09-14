<?php
namespace Akari\utility;

use Akari\Context;

Class TemplateHelperCommand{
    public static function getScreen() {
        return TemplateHelper::load(Context::$lastTemplate, false);
    }

    public static function lang($id, $command = ''){
        $command = explode("&", $command);
        foreach($command as $value){
            $tmp = explode("=", $value);
            $L[$tmp[0]] = $tmp[1];
        }

        echo I18n::get($id, $L);
    }

    public static function template($id) {
        return TemplateHelper::load($id, false, false);
    }

    public static function module($id, $data = '') {
        $appPath = Context::$appBasePath."/app/lib/{$id}Module.php";
        $corePath = Context::$appBasePath."/core/system/module/{$id}Module.php";

        if(file_exists($appPath)){
            $clsName = Context::$appBaseNS."\\lib\\{$id}Module";
        }elseif(file_exists($corePath)){
            $clsName = "Akari\\system\\module\\{$id}Module";
        }else{
            throw new \Exception("Module $id not found");
        }

        $clsObj = $clsName::getInstance();

        return $clsObj->run($data);
    }

    /**
     * 增加的关于addJS,addCSS的界面
     */
    public static function addJs($path) {
        TemplateHelper::addJs($path);
    }

    public static function addCss($path) {
        TemplateHelper::addCss($path);
    }
}