<?php
namespace Akari\utility;

use Akari\Context;
use Akari\system\http\Dispatcher;
use Exception;

!defined("AKARI_PATH") && exit;

Class TemplateHelper{
	public static $usingLayout = false;//防止子模板载入时，layout再次请求
	public static function load($tplName, $useLayout = true, $updateLast = true){
		if (self::$usingLayout) {
			$useLayout = false;
		}

		if($bDir = C("templateBaseDir")){
			$tplName = "$bDir/$tplName";
		}

		$tplPath = Context::$appBasePath."/app/template/$tplName";
		$tplPath .= Context::$appConfig->templateSuffix ? Context::$appConfig->templateSuffix : ".htm";

		if(!file_exists($tplPath)){
			throw new Exception("[Akari.Utility.TemplateHelper] not found [ $tplName ]");
		}
		if($updateLast) Context::$lastTemplate = $tplName;


		// 如果有Layout的话 处理layout
		if(C("closeLayout") === TRUE){
			$useLayout = FALSE; 
		}

		// 检查layout文件
		if ($useLayout) {
			$layoutDir = Context::$appBasePath."/app/template/layout/";
			$layoutPath = NULL;
			$layoutSuffix = Context::$appConfig->layoutSuffix ? Context::$appConfig->layoutSuffix : ".htm";

			if(C("customLayout")){
				$layoutPath = C("customLayout");
			} else {
				$layoutPath = Dispatcher::getInstance()->findPath(Context::$uri, "template/layout", $layoutSuffix);
			}

			if ($layoutPath) {
				$tplName = str_replace($layoutDir, '', $layoutPath);
				$tplName = str_replace($layoutSuffix, '', $tplName);

				$tplPath = $layoutPath;
				if (!file_exists($layoutPath)) {
					throw new \Exception("[Akari.Utility.TemplateHelper] not found layout [ $tplName ]");
				}
				self::$usingLayout = true;
			}
		}

		if($useLayout && $layoutPath) {
			$tplId = "Layout_".str_replace('/', '_', $tplName);
		} else {
			$tplId = str_replace('/', '_', $tplName);
		}

		$cachePath = Context::$appBasePath.Context::$appConfig->templateCache."/$tplId.php";
		if(file_exists($cachePath) && filemtime($tplPath) < filemtime($cachePath)){
			return $cachePath;
		}else{
			$content = "<?php !defined('AKARI_VERSION') && exit(); ?>";
            $content .= '<?php
                use Akari\Context; use Akari\utility\DataHelper;
                use Akari\utility\TemplateHelper;
                use Akari\utility\TemplateHelperCommand;
            ?>';
			$content .= self::parse(readover($tplPath));
            $content .= "<?php if(defined('DEBUG_MODE')): ?><!--#Akari TemplateHelper: $tplName #$tplId (".microtime().")--><?php endif ?>";

			writeover($cachePath, $content);
			return $cachePath;
		}
	}

	public static function parse($template){
		$const_regexp = "([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)";
		$var_regexp = "((\\\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*(\-\>)?[a-zA-Z0-9_\x7f-\xff]*)(\[[a-zA-Z0-9_\-\.\"\'\[\]\$\x7f-\xff]+\])*)";
		
		$template = preg_replace('/<!--#(.*?)-->/ieu', "self::command_parse('\\1')", $template);
		$template = preg_replace('/\{\%(.*?)\}/ieu', "self::command_lang('\\1')", $template);

        /*$$template = preg_replace("/\{$const_regexp\}/s", "<?=\\1?>", $template);
        $template = preg_replace("/$var_regexp/es", "self::addquote('<?=\\1?>')", $template);
        template = preg_replace("/\<\?\=\<\?\=$var_regexp\?\>\?\>/es", "self::addquote('<?=\\1?>')", $template);
        */

        $template = preg_replace_callback('/\{'.$const_regexp.'\}/s', function($matches) {
            return '<?='.$matches[1].'?>';
        }, $template);

        $template = preg_replace_callback("/$var_regexp/s", function($matches) {
            return TemplateHelper::addquote('<?='.$matches[1].'?>');
        }, $template);


        $template = preg_replace_callback('/\<\?\=\<\?\='.$var_regexp.'\?\>\?\>/s', function($matches) {
            return TemplateHelper::addquote('<?='.$matches[1].'?>');
        }, $template);

		$template = str_replace("_#_", "\$", $template);
		
		return $template;
	}

	
	public static function command_parse($str){
		$str = str_replace("$", "_#_", $str);
		$str = explode(" ", $str);
		$command = array_shift($str);
		$end_str = implode(" ", $str);
		
		switch($command){
			case "set":
				return "<?php $end_str; ?>";
			case "if":
				return "<?php if($end_str){?>";
			case "else":
				return "<?php }else{ ?>";
			case "elseif":
				return "<?php }elseif($end_str){ ?>";
			case "template":
				return "<?php require TemplateHelperCommand::template('$end_str'); ?>";
			case "layout":
				return '<?php require TemplateHelperCommand::getScreen();?>';
			case "module":
				if(isset($str[1])){
					return "<?php TemplateHelperCommand::module('$str[0]', \"$str[1]\"); ?>";
				}

				return "<?php TemplateHelperCommand::module('$end_str'); ?>";
			case "eval":
				return "<?php eval('$end_str'); ?>";
			case "var":
				return "<?php echo($end_str); ?>";
			case "for":
				return "<?php for($end_str){ ?>";
			case "loop":
				return "<?php if(is_array($str[0])||is_object($str[0]))foreach({$end_str}){ ?>";
			case "loopend":
			case "endloop":
			case "/loop":
			case "/if":
			case "/for":
			case "endfor":
			case "forend":
			case "endif":
			case "ifend":
				return "<?php } ?>";
			default:
				return "<!--[Invild Command: $command]-->";
		}
	}
	
	public static function addquote($var) {
		return str_replace("\\\"", "\"", preg_replace('/\[([a-zA-Z0-9_\-\.\x7f-\xff]+)\]/s', "['\\1']", $var));
	}
	
	public static function command_lang($str){
		$command = explode(" ", $str);
		$langid = array_shift($command);
		if(empty($command)){
			return "<?php TemplateHelperCommand::lang(\"$langid\"); ?>";
		}else{
			$commands = implode("&", $command);
			return "<?php TemplateHelperCommand::lang(\"$langid\", '$commands'); ?>";
		}
	}

	public static $asdata = [];

    /**
     * @param $key
     * @param $value
     * @return array
     */
    public static function assign($key, $value) {
		if (!$value && is_array($key)) {
			self::$asdata = array_merge(self::$asdata, $key);
		} elseif ($value !== NULL) {
			self::$asdata[ $key ] = $value;
		} elseif ($key === NULL && $value === NULL) {
			return self::$asdata;
		}
	}
}
