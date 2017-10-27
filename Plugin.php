<?php
if (!defined('__TYPECHO_ROOT_DIR__'))
    exit;

/**
 * 又拍云 Token 生成
 *
 * @package UpyunToken
 * @author 黎明余光
 * @version 1.0.0
 * @link https://blog.lim-light.com/
 */
class UpyunToken_Plugin implements Typecho_Plugin_Interface {
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     *
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate() {
        Typecho_Plugin::factory('Widget_Archive')->beforeRender = array('UpyunToken_Plugin', 'Widget_Archive_beforeRender');
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     *
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate() {
    }

    /**
     * 获取插件配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form) {
        $UpyunTokenOpen = new Typecho_Widget_Helper_Form_Element_Checkbox('UpyunTokenOpen', array('UpyunTokenOpen' => '启用'), NULL, _t('自动生成 Upyun Token'), _t("将自动对引用的资源添加 Token"));
        $form->addInput($UpyunTokenOpen);

        $UpyunDomain = new Typecho_Widget_Helper_Form_Element_Text('UpyunDomain', NULL, NULL, _t('域名'), _t('例如：<strong>static.lim-light.com</strong>'));
        $form->addInput($UpyunDomain);

        $UpyunToken = new Typecho_Widget_Helper_Form_Element_Text('UpyunToken', NULL, NULL, _t('Token'), _t('在又拍云上设置的 Token，用于自动生成签名'));
        $form->addInput($UpyunToken);

        $UpyunEtime = new Typecho_Widget_Helper_Form_Element_Text('UpyunEtime', NULL, NULL, _t('签名过期时间'), _t('单位为秒'));
        $form->addInput($UpyunEtime);

        $keyword_replace = new Typecho_Widget_Helper_Form_Element_Textarea('keyword_replace', null, null, _t('HTML关键词替换'), _t('作者：情留メ蚊子'));
        $form->addInput($keyword_replace);
    }

    /**
     * 个人用户的配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form) {
    }

    public static function Widget_Archive_beforeRender() {
        ob_start('UpyunToken_Plugin::Insert');
    }

    public static function Insert($buffer) {
        $settings = Helper::options()->plugin('UpyunToken');

        if ($settings->UpyunTokenOpen) {
            $buffer = self::getTokenResult($buffer, $settings->UpyunDomain, $settings->UpyunToken, $settings->UpyunEtime);
        }

        if ($settings->keyword_replace) {
            $list = explode("\r\n", $settings->keyword_replace);
            foreach ($list as $tmp) {
                list($old, $new) = explode('=', $tmp);
                $buffer = str_replace($old, $new, $buffer);
            }
        }

        return $buffer;
    }

    /**
     * Token 生成插入
     *
     * @author 黎明余光 <i@emiria.moe>
     * @version 1.0.0
     * @param string $html_source HTML源码
     * @return string 压缩后的代码
     */
    public static function getTokenResult($html_source, $domain, $key, $petime) {
        $patterns = "/(\"https:\/\/" . $domain . ".*?\"|'https:\/\/" . $domain . ".*?\'|\"http:\/\/" . $domain . ".*?\"|'http:\/\/" . $domain . ".*?'|\(http:\/\/" . $domain . ".*?\)|\(https:\/\/" . $domain . ".*?\))/msi";
        preg_match_all($patterns, $html_source, $out);
        $rawurl = array();
        $newurl = array();
        $i = 0;
        foreach ($out[0] as $url) {
            if (stripos($url, '"', 1)) $rawurl[$i] = explode('"', $url)[1];
            if (stripos($url, "'", 1)) $rawurl[$i] = explode("'", $url)[1];
            if (stripos($url, ")", 1)) $rawurl[$i] = explode("(", explode(")", $url)[0])[1];            
            $i++;
        }
        $i = 0;
        foreach ($rawurl as $uri) {
            $etime = time() + $petime;
            if (explode("https://" . $domain, $uri)[0] == "") $uri = explode("https://" . $domain, $uri)[1];
            if (explode("http://" . $domain, $uri)[0] == "") $uri = explode("http://" . $domain, $uri)[1];
            $path = $uri;
            $sign = substr(md5($key.'&'.$etime.'&'.$path), 12, 8).$etime;
            $newurl[$i] = $rawurl[$i] . "?_upt=" . $sign;
            $i++;
        }
        $i = 0;
        $flag = array();
        foreach ($newurl as $replacement) {
            if (!isset($flag[$rawurl[$i]])) {
                $html_source = str_replace($rawurl[$i], $replacement, $html_source);
                $flag[$rawurl[$i]] = true;
            }
            $i++;
        }
        return $html_source;
    }

}