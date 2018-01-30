<?php

namespace PhalApi\Translate;

/**
 * PhalApi2-Translate 拓展类
 * @author: 喵了个咪 <wenzhenxi@vip.qq.com> 2018-01-30
 *
 * 使用方式在init.php中注册:
 * \PhalApi\DI()->translate = function () {
 *     return new \PhalApi\Translate\Lite("appId", "secKey");
 * };
 * 
 * 例子:
 * $rs = \PhalApi\DI()->translate->translate(array("上海市", "上海市", "杨浦区"), "auto", "jp");
 */

define("CURL_TIMEOUT", 10);
define("URL", "http://api.fanyi.baidu.com/api/trans/vip/translate");

// 成功
const TRANSLATE_SUCCESS = 52000;
// 请求超时
const TRANSLATE_OVERTIME = 52001;
// 系统错误
const TRANSLATE_ERROR = 52002;
// 未授权用户
const TRANSLATE_APPID_UNAUTHORIZED = 52003;
// 必填参数为空
const TRANSLATE_LACK_PARAMETER = 54000;
// 客户端IP非法
const TRANSLATE_IP_ILLEGAL = 58000;
// 签名错误
const TRANSLATE_SIGNATURE_ERROR = 54001;
// 访问频率受限
const TRANSLATE_FREQUENCY_LIMIT = 54003;
// 译文语言方向不支持
const TRANSLATE_LANGUAGE_NOT_SUPPORTED = 58001;
// 账户余额不足
const TRANSLATE_LACK_BALANCE = 54004;
// 长query请求频繁
const TRANSLATE_LONG_FREQUENT_QUERY_REQUEST = 54005;
// 参数类型不对需要传递数组类型
const TRANSLATE_NOT_SUPPORT_TYPES = 10001;


class Lite {

    // 通过百度开发者平台申请的APP_ID
    protected $appId = "";
    // 通过百度开发者平台申请的SEC_KEY
    protected $secKey = "";

    /**
     * Translate_Lite 构造函数传入ID和KEY
     *
     * @param $appId
     * @param $secKey
     */
    public function __construct($appId = "", $secKey = "") {
        if ($appId) {
            $this->appId = $appId;
        }
        if ($secKey) {
            $this->secKey = $secKey;
        }
    }

    //翻译入口
    public function translate($query, $from, $to) {

        if (is_array($query)) {
            $str = implode("| & |", $query);
        } else {
            throw new Translate_Exception_Base("not support types", 10001);
        }

        $args = array(
            'q'     => $str,
            'appid' => $this->appId,
            'salt'  => rand(10000, 99999),
            'from'  => $from,
            'to'    => $to,
        );
        $args['sign'] = $this->buildSign($str, $this->appId, $args['salt'], $this->secKey);
        $ret = $this->call(URL, $args);
        $ret = json_decode($ret, true);
        if (empty($ret["error_code"])) {
            $list = explode("| & |", $ret['trans_result'][0]["dst"]);
            $i = 0;
            foreach ($query as $k => $v) {
                $query[$k] = $list[$i];
                $i++;
            }
            return $query;
        }
        throw new Translate_Exception_Base("Abnormal results", $ret["error_code"]);
    }

    /**
     * 内部加密
     *
     * @param $query
     * @param $appID
     * @param $salt
     * @param $secKey
     *
     * @return string
     */
    protected function buildSign($query, $appID, $salt, $secKey) {
        $str = $appID . $query . $salt . $secKey;
        $ret = md5($str);
        return $ret;
    }

    /**
     * 网络请求
     *
     * @param        $url
     * @param null   $args
     * @param string $method
     * @param int    $testflag
     * @param int    $timeout
     * @param array  $headers
     *
     * @return bool|mixed
     */
    protected function call($url, $args = null, $method = "post", $testflag = 0, $timeout = CURL_TIMEOUT, $headers = array()) {
        $ret = false;
        $i = 0;
        while ($ret === false) {
            if ($i > 1) {
                break;
            }
            if ($i > 0) {
                sleep(1);
            }
            $ret = $this->callOnce($url, $args, $method, false, $timeout, $headers);
            $i++;
        }
        return $ret;
    }

    /**
     * 进行请求
     *
     * @param        $url
     * @param null   $args
     * @param string $method
     * @param bool   $withCookie
     * @param int    $timeout
     * @param array  $headers
     *
     * @return mixed
     */
    public function callOnce($url, $args = null, $method = "post", $withCookie = false, $timeout = CURL_TIMEOUT, $headers = array()) {
        $ch = curl_init();
        if ($method == "post") {
            $data = $this->convert($args);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_POST, 1);
        } else {
            $data = $this->convert($args);
            if ($data) {
                if (stripos($url, "?") > 0) {
                    $url .= "&$data";
                } else {
                    $url .= "?$data";
                }
            }
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        if ($withCookie) {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $_COOKIE);
        }
        $r = curl_exec($ch);
        curl_close($ch);
        return $r;
    }

    protected function convert(&$args) {
        $data = '';
        if (is_array($args)) {
            foreach ($args as $key => $val) {
                if (is_array($val)) {
                    foreach ($val as $k => $v) {
                        $data .= $key . '[' . $k . ']=' . rawurlencode($v) . '&';
                    }
                } else {
                    $data .= "$key=" . rawurlencode($val) . "&";
                }
            }
            return trim($data, "&");
        }
        return $args;
    }
}
