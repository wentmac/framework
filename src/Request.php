<?php
/**
 * Power By Tmac PHP MVC framework
 * $Author: zhangwentao $  <zwttmac@qq.com>
 * $Id: HttpRequest.class.php 325 2016-05-31 10:07:35Z zhangwentao $
 * http://www.t-mac.org；
 */

namespace Tmac;

use Tmac\Contract\ConfigInterface;

class Request
{
    private $config;

    /**
     * 设置参数并进行过滤
     *
     * @param array $request
     */
    public function __construct( ConfigInterface $config )
    {
        $this->config = $config;
    }

    public function init( $request )
    {
        if ( $this->config[ 'app.auto_filter' ] ) {
            $this->filter( $request );
            if ( !get_magic_quotes_gpc() ) {
                $this->filter( $_GET );
                $this->filter( $_POST );
                $this->filter( $_COOKIE );
                $this->filter( $_FILES );
            }
        }
        $param = $this->cleanArray( $request );
        $_GET = array_merge( $_GET, $param );
        unset( $param );
        unset( $request );
        unset( $_ENV );
        unset( $HTTP_ENV_VARS );
        unset( $_REQUEST );
        unset( $HTTP_POST_VARS );
        unset( $HTTP_GET_VARS );
        unset( $HTTP_POST_FILES );
        unset( $HTTP_COOKIE_VARS );
    }

    /**
     * 转义
     *
     * @param array $array 要过滤的数组
     * @access protected
     */
    protected function filter( &$array )
    {
        if ( is_array( $array ) ) {
            foreach ( $array as $key => $value ) {
                is_array( $value ) ? $this->filter( $value ) : $array[ $key ] = addslashes( $value );
            }
        }
    }

    /**
     * 清理数组
     *
     * @param array $array
     * @return array
     */
    protected function cleanArray( $array )
    {
        foreach ( $array as $key => $value ) {
            if ( empty( $key ) && empty( $value ) ) {
                unset( $array[ $key ] );
            }
        }
        return $array;
    }

    /**
    Get the userEmail field from the $_GET superglobal. Sanitize the value with the email sanitizer:
    从$ _GET超全局变量获取userEmail字段。 使用电子邮件净化器净化价值：
    $email = $request->getQuery('userEmail', 'email', 'some@example.com');


     */


    public function get($name = '', $default = null){}
    public function getQuery($name = '', $default = null){}
    public function getPost($name = '', $default = null){}
    public function getPut($name = '', $default = null){}
    public function getDelete($name = '', $default = null){}



    public function has($name){}
    public function hasQuery($name){}
    public function hasPost($name){}
    public function hasPut($name){}
    public function hasDelete($name){}
    public function hasServer($name){}
    public function hasFiles($onlySuccessful){}


    public function isAjax(){}
    public function isValidHttpMethod(){}
    public function isMethod(){}
    public function isPost(){}
    public function isGet(){}
    public function isPut(){}
    public function isPatch(){}
    public function isHead(){}
    public function isDelete(){}
    public function isOptions(){}
    public function isPurge(){}
    public function isTrace(){}
    public function isConnect(){}


    public function getHeader($name){}
    public function getHeaders(){}
    public function getScheme($name){}
    public function getUploadedFiles($onlySuccessful){}

    public function getHTTPReferer(){}
    public function getContentType(){}
    public function getAcceptableContent(){}
    public function getBestAccept(){}
    public function getClientCharsets(){}
    public function getBestCharset(){}
    public function getLanguages(){}
    public function getBestLanguage(){}
    public function getBasicAuth(){}
    public function getDigestAuth(){}
    public function getRawBody(){}
    public function getJsonRawBody($associative){}
    public function getServerAddress(){}
    public function getServerName(){}
    public function getHttpHost(){}
    public function getPort(){}
    public function getURI(){}
    public function getClientAddress(){}
    public function getServer($name){}
    public function getMethod(){}
    public function getUserAgent(){}

    //doto 增加filter
}

?>
