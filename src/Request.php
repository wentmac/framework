<?php
/**
 * Power By Tmac PHP MVC framework
 * $Author: zhangwentao $  <zwttmac@qq.com>
 * $Id: HttpRequest.class.php 325 2016-05-31 10:07:35Z zhangwentao $
 * http://www.t-mac.org；
 */

namespace Tmac;

use Tmac\Contract\ConfigInterface;
use Tmac\Plugin\Filter;
use Tmac\UploadedFile;

class Request
{
    protected $config;

    protected $request;
    protected $get;
    protected $post;
    protected $input;
    protected $input_data;

    protected $server;
    protected $header;
    protected $cookie;

    /**
     * 请求类型
     * @var string
     */
    protected $var_method = '_method';


    /**
     * 表单ajax伪装变量
     * @var string
     */
    protected $var_ajax = '_ajax';

    /**
     * HTTPS代理标识
     * @var string
     */
    protected $https_agent_name = '';

    /**
     * 当前FILE参数
     * @var array
     */
    protected $file = [];
    /**
     * 资源类型定义
     * @var array
     */
    protected $mimeType = [
        'xml' => 'application/xml,text/xml,application/x-xml',
        'json' => 'application/json,text/x-json,application/jsonrequest,text/json',
        'js' => 'text/javascript,application/javascript,application/x-javascript',
        'css' => 'text/css',
        'rss' => 'application/rss+xml',
        'yaml' => 'application/x-yaml,text/yaml',
        'atom' => 'application/atom+xml',
        'pdf' => 'application/pdf',
        'text' => 'text/plain',
        'image' => 'image/png,image/jpg,image/jpeg,image/pjpeg,image/gif,image/webp,image/*',
        'csv' => 'text/csv',
        'html' => 'text/html,application/xhtml+xml,*/*',
    ];

    protected $real_ip;

    /**
     * 前端代理服务器IP
     * @var array
     */
    protected $proxy_server_ip = [];

    /**
     * 前端代理服务器真实IP头
     * @var array
     */
    protected $proxy_server_ip_header = [ 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'HTTP_X_CLIENT_IP', 'HTTP_X_CLUSTER_CLIENT_IP' ];

    protected $filter;

    /**
     * 设置参数并进行过滤
     *
     * @param array $request
     */
    public function __construct( ConfigInterface $config, Filter $filter )
    {
        $this->get = $_GET;
        $this->post = $_POST;
        $this->input = file_get_contents( 'php://input' );
        $this->input_data = $this->getInputData( $this->input );
        $this->request = $_REQUEST;
        $this->cookie = $_COOKIE;
        $this->file = $_FILES ?? [];

        $this->header = $this->getHeaders();

        $this->filter = $filter;
        $this->server = $_SERVER;
    }

    /**
     * Get the userEmail field from the $_GET superglobal. Sanitize the value with the email sanitizer:
     * 从$ _GET超全局变量获取userEmail字段。 使用电子邮件净化器净化价值：
     * $email = $request->getQuery('userEmail', 'email', 'some@example.com');
     */

    /**
     * Gets the HTTP headers.
     * @return array|false
     */
    public function getHeaders()
    {
        if ( function_exists( 'apache_request_headers' ) && $result = apache_request_headers() ) {
            $header = $result;
        } else {
            $header = [];
            $server = $_SERVER;
            foreach ( $server as $key => $val ) {
                if ( 0 === strpos( $key, 'HTTP_' ) ) {
                    $key = str_replace( '_', '-', strtolower( substr( $key, 5 ) ) );
                    $header[ $key ] = $val;
                }
            }
            if ( isset( $server[ 'CONTENT_TYPE' ] ) ) {
                $header[ 'content-type' ] = $server[ 'CONTENT_TYPE' ];
            }
            if ( isset( $server[ 'CONTENT_LENGTH' ] ) ) {
                $header[ 'content-length' ] = $server[ 'CONTENT_LENGTH' ];
            }
        }
        return $this->header = array_change_key_case( $header );
    }

    /**
     * 设置或者获取当前的Header
     * @access public
     * @param string $name header名称
     * @param string $default 默认值
     * @return string|array
     */
    public function getHeader( string $name = '', string $default = null )
    {
        if ( '' === $name ) {
            return $this->header;
        }

        $name = str_replace( '_', '-', strtolower( $name ) );

        return $this->header[ $name ] ?? $default;
    }

    /**
     * 取出所有的input流数据 返回数组形式
     * @param $content
     * @return array
     */
    protected function getInputData( $content ): array
    {
        $contentType = $this->getContentType();
        if ( $contentType == 'application/x-www-form-urlencoded' ) {
            parse_str( $content, $data );
            return $data;
        } elseif ( false !== strpos( $contentType, 'json' ) ) {
            return (array) json_decode( $content, true );
        }

        return [];
    }

    /**
     * 获取server参数
     * @access public
     * @param string $name 数据名称
     * @param string $default 默认值
     * @return mixed
     */
    public function getServer( string $name = '', string $default = '' )
    {
        if ( empty( $name ) ) {
            return $this->server;
        } else {
            $name = strtoupper( $name );
        }

        return $this->server[ $name ] ?? $default;
    }


    /**
     * 设置请求类型
     * @access public
     * @param string $method 请求类型
     * @return $this
     */
    private function setMethod( string $method )
    {
        $this->method = strtoupper( $method );
        return $this;
    }

    /**
     * 当前的请求类型
     * @param bool $origin 是否获取原始请求类型
     * @return string
     */
    public function getMethod( bool $origin = false ): string
    {
        if ( $origin ) {
            // 获取原始请求类型
            return $this->getServer( 'REQUEST_METHOD' ) ? : 'GET';
        } elseif ( !$this->method ) {
            if ( isset( $this->post[ $this->var_method ] ) ) {
                $method = strtolower( $this->post[ $this->var_method ] );
                if ( in_array( $method, [ 'get', 'post', 'put', 'patch', 'delete' ] ) ) {
                    $this->method = strtoupper( $method );
                    $this->{$method} = $this->post;
                } else {
                    $this->method = 'POST';
                }
                unset( $this->post[ $this->var_method ] );
            } elseif ( $this->server( 'HTTP_X_HTTP_METHOD_OVERRIDE' ) ) {
                $this->method = strtoupper( $this->server( 'HTTP_X_HTTP_METHOD_OVERRIDE' ) );
            } else {
                $this->method = $this->getServer( 'REQUEST_METHOD' ) ? : 'GET';
            }
        }

        return $this->method;
    }


    /**
     * Get the userEmail field from the superglobal:
     * @param string $name
     * @param null $default
     */
    public function get( string $name = '', $default = null )
    {
        if ( empty( $name ) ) {
            return $this->request;
        }
        return $this->request[ $name ] ?? $default;
    }

    /**
     * Get the  field from the $_GET superglobal:
     * @param string $name
     * @param null $default
     */
    public function getQuery( $name = '', $default = null )
    {
        if ( empty( $name ) ) {
            return $this->get;
        }
        return $this->get[ $name ] ?? $default;
    }

    /**
     * Get the  field from the $_POST superglobal:
     * @param string $name
     * @param null $default
     * @return array|mixed|null
     */
    public function getPost( $name = '', $default = null )
    {
        if ( empty( $name ) ) {
            return $this->post;
        }
        return $this->post[ $name ] ?? $default;
    }

    /**
     * Examples Get the userEmail field from the PUT stream:
     * @param string $name
     * @param null $default
     * @return array|mixed|null
     */
    public function getPut( $name = '', $default = null )
    {
        if ( $this->isPut() === false ) {
            return $default;
        }
        return $this->getInputField( $name, $default );
    }


    /**
     * Examples Get the userEmail field from the PUT stream:
     * @param string $name
     * @param null $default
     * @return array|mixed|null
     */
    public function getPatch( $name = '', $default = null )
    {
        if ( $this->isPatch() === false ) {
            return $default;
        }
        return $this->getInputField( $name, $default );
    }

    /**
     * @param string $name
     * @param null $default
     * @return array|mixed|null
     */
    public function getDelete( $name = '', $default = null )
    {
        if ( $this->isDelete() === false ) {
            return $default;
        }
        return $this->getInputField( $name, $default );
    }


    /**
     * @param string $name
     * @param null $default
     * @return array|mixed|null
     */
    public function getInputField( $name = '', $default = null )
    {
        if ( empty( $name ) ) {
            return $this->input_data;
        }
        return $this->input_data[ $name ] ?? $default;
    }


    public function has( string $name ): bool
    {
        return isset( $this->request[ $name ] ) ? true : false;
    }

    public function hasQuery( string $name ): bool
    {
        return isset( $this->get[ $name ] ) ? true : false;
    }

    public function hasPost( string $name ): bool
    {
        return isset( $this->post[ $name ] ) ? true : false;
    }

    public function hasPut( string $name ): bool
    {
        if ( $this->isPut() === false ) {
            return false;
        }
        return isset( $this->input_data[ $name ] ) ? true : false;
    }

    public function hasDelete( string $name ): bool
    {
        if ( $this->isDelete() === false ) {
            return false;
        }
        return isset( $this->input_data[ $name ] ) ? true : false;
    }

    public function hasPatch( string $name ): bool
    {
        if ( $this->isPatch() === false ) {
            return false;
        }
        return isset( $this->input_data[ $name ] ) ? true : false;
    }

    public function hasServer( string $name ): bool
    {
        $name = strtoupper( $name );
        return isset( $this->server[ $name ] ) ? true : false;
    }

    public function hasFiles( $name ): bool
    {
        return isset( $this->file[ $name ] ) ? true : false;
    }

    /**
     * 当前是否ssl
     * @access public
     * @return bool
     */
    public function isSsl(): bool
    {
        if ( $this->server( 'HTTPS' ) && ( '1' == $this->getServer( 'HTTPS' ) || 'on' == strtolower( $this->getServer( 'HTTPS' ) ) ) ) {
            return true;
        } elseif ( 'https' == $this->getServer( 'REQUEST_SCHEME' ) ) {
            return true;
        } elseif ( '443' == $this->getServer( 'SERVER_PORT' ) ) {
            return true;
        } elseif ( 'https' == $this->getServer( 'HTTP_X_FORWARDED_PROTO' ) ) {
            return true;
        } elseif ( $this->https_agent_name && $this->getServer( $this->https_agent_name ) ) {
            return true;
        }

        return false;
    }

    /**
     * 当前是否Ajax请求
     * @access public
     * @param bool $ajax true 获取原始ajax请求
     * @return bool
     */
    public function isAjax( bool $ajax = false ): bool
    {
        $value = $this->getServer( 'HTTP_X_REQUESTED_WITH' );
        $result = $value && 'xmlhttprequest' == strtolower( $value ) ? true : false;

        if ( true === $ajax ) {
            return $result;
        }
        return $this->get( $this->var_ajax ) ? true : $result;
    }

    public function isPost(): bool
    {
        return $this->getMethod() == 'POST';
    }

    public function isGet(): bool
    {
        return $this->getMethod() == 'GET';
    }

    public function isPut(): bool
    {
        return $this->getMethod() == 'PUT';
    }

    public function isPatch(): bool
    {
        return $this->getMethod() == 'PATCH';
    }

    public function isHead(): bool
    {
        return $this->getMethod() == 'HEAD';
    }

    public function isDelete(): bool
    {
        return $this->getMethod() == 'DELETE';
    }

    public function isOptions(): bool
    {
        return $this->getMethod() == 'OPTIONS';
    }

    public function isPurge(): bool
    {
        return $this->getMethod() == 'OPTIONS';
    }

    public function isTrace(): bool
    {
        return $this->getMethod() == 'TRACE';
    }

    public function isConnect(): bool
    {
        return $this->getMethod() == 'CONNECT';
    }

    /**
     * 是否为cli
     * @access public
     * @return bool
     */
    public function isCli(): bool
    {
        return PHP_SAPI == 'cli';
    }

    /**
     * 检测是否使用手机访问
     * @access public
     * @return bool
     */
    public function isMobile(): bool
    {
        if ( $this->getServer( 'HTTP_VIA' ) && stristr( $this->getServer( 'HTTP_VIA' ), "wap" ) ) {
            return true;
        } elseif ( $this->getServer( 'HTTP_ACCEPT' ) && strpos( strtoupper( $this->getServer( 'HTTP_ACCEPT' ) ), "VND.WAP.WML" ) ) {
            return true;
        } elseif ( $this->getServer( 'HTTP_X_WAP_PROFILE' ) || $this->getServer( 'HTTP_PROFILE' ) ) {
            return true;
        } elseif ( $this->getServer( 'HTTP_USER_AGENT' ) && preg_match( '/(blackberry|configuration\/cldc|hp |hp-|htc |htc_|htc-|iemobile|kindle|midp|mmp|motorola|mobile|nokia|opera mini|opera |Googlebot-Mobile|YahooSeeker\/M1A1-R2D2|android|iphone|ipod|mobi|palm|palmos|pocket|portalmmm|ppc;|smartphone|sonyericsson|sqh|spv|symbian|treo|up.browser|up.link|vodafone|windows ce|xda |xda_)/i', $this->getServer( 'HTTP_USER_AGENT' ) ) ) {
            return true;
        }

        return false;
    }

    public function getHTTPReferer()
    {
        return $this->server[ 'HTTP_REFERER' ];
    }

    public function getContentType()
    {
        $contentType = $this->getHeader( 'Content-Type' );
        if ( $contentType ) {
            if ( strpos( $contentType, ';' ) ) {
                [ $type ] = explode( ';', $contentType );
            } else {
                $type = $contentType;
            }
            return trim( $type );
        }
        return '';
    }


    /**
     *  Gets an array with mime/types and their quality accepted by the browser/client
     * 获取包含MIME /类型的数组，其质量被浏览器/客户端接受
     * @return int|string
     */
    public function getAcceptableContent()
    {
        $accept = $this->getServer( 'HTTP_ACCEPT' );

        if ( empty( $accept ) ) {
            return '';
        }

        foreach ( $this->mime_type as $key => $val ) {
            $array = explode( ',', $val );
            foreach ( $array as $k => $v ) {
                if ( stristr( $accept, $v ) ) {
                    return $key;
                }
            }
        }

        return '';
    }

    public function getAccept()
    {
        return $accept = $this->getServer( 'HTTP_ACCEPT' );
    }

    public function getAcceptCharset()
    {
        return $this->getServer( 'HTTP_ACCEPT_CHARSET' );
    }

    public function getBestCharset()
    {
    }

    public function getLanguages()
    {
        return $this->getServer( 'HTTP_ACCEPT_LANGUAGE' );
    }

    public function getBestLanguage()
    {
    }

    public function getBasicAuth()
    {
        return $this->getServer( 'PHP_AUTH_USER' );
    }

    public function getDigestAuth()
    {
        return $this->getServer( 'PHP_AUTH_DIGEST' );
    }

    /**
     * Gets HTTP raw request body
     */
    public function getRowBodyInput()
    {
        return $this->input;
    }

    /**
     * Returns the IP address of the host server
     * @return mixed|string
     */
    public function getServerAddress()
    {
        return $this->getServer( 'SERVER_ADDR' );
    }

    /**
     * Returns the name of the host server (such as www.w3schools.com)
     */
    public function getServerName()
    {
        return $this->getServer( 'SERVER_NAME' );
    }

    public function getHttpHost()
    {
        $host = strval( $this->getServer( 'HTTP_X_REAL_HOST' ) ? : $this->getServer( 'HTTP_HOST' ) );
        return true === $strict && strpos( $host, ':' ) ? strstr( $host, ':', true ) : $host;
    }

    public function getPort()
    {
        return (int) $this->getServer( 'SERVER_PORT', '' );
    }

    public function getRemotePort()
    {
        return (int) $this->getServer( 'REMOTE_PORT', '' );
    }

    public function getClientIpAddress()
    {
        if ( !empty( $this->real_ip ) ) {
            return $this->real_ip;
        }

        $this->real_ip = $this->getServer( 'REMOTE_ADDR', '' );

        // 如果指定了前端代理服务器IP以及其会发送的IP头
        // 则尝试获取前端代理服务器发送过来的真实IP
        $proxyIp = $this->proxy_server_ip;
        $proxyIpHeader = $this->proxy_server_ip_header;

        if ( count( $proxyIp ) > 0 && count( $proxyIpHeader ) > 0 ) {
            // 从指定的HTTP头中依次尝试获取IP地址
            // 直到获取到一个合法的IP地址
            foreach ( $proxyIpHeader as $header ) {
                $tempIP = $this->getServer( $header );

                if ( empty( $tempIP ) ) {
                    continue;
                }

                $tempIP = trim( explode( ',', $tempIP )[ 0 ] );

                if ( !$this->isValidIP( $tempIP ) ) {
                    $tempIP = null;
                } else {
                    break;
                }
            }

            // tempIP不为空，说明获取到了一个IP地址
            // 这时我们检查 REMOTE_ADDR 是不是指定的前端代理服务器之一
            // 如果是的话说明该 IP头 是由前端代理服务器设置的
            // 否则则是伪装的
            if ( !empty( $tempIP ) ) {
                $real_ipBin = $this->ip2bin( $this->real_ip );

                foreach ( $proxyIp as $ip ) {
                    $serverIPElements = explode( '/', $ip );
                    $serverIP = $serverIPElements[ 0 ];
                    $serverIPPrefix = $serverIPElements[ 1 ] ?? 128;
                    $serverIPBin = $this->ip2bin( $serverIP );

                    // IP类型不符
                    if ( strlen( $real_ipBin ) !== strlen( $serverIPBin ) ) {
                        continue;
                    }

                    if ( strncmp( $real_ipBin, $serverIPBin, (int) $serverIPPrefix ) === 0 ) {
                        $this->real_ip = $tempIP;
                        break;
                    }
                }
            }
        }

        if ( !$this->isValidIP( $this->real_ip ) ) {
            $this->real_ip = '0.0.0.0';
        }

        return $this->real_ip;

    }

    public function getUserAgent()
    {
        return $this->getServer( 'HTTP_USER_AGENT', '' );
    }

    /**
     * : Gets HTTP schema (http/https)
     */
    public function getScheme()
    {
        return $this->isSsl() ? 'https' : 'http';
    }


    /**
     * 获取cookie参数
     * @access public
     * @param mixed $name 数据名称
     * @param string $default 默认值
     * @param string|array $filter 过滤方法
     * @return mixed
     */
    public function cookie( string $name = '', $default = null, $filter = '' )
    {
        if ( !empty( $name ) ) {
            $data = $this->getData( $this->cookie, $name, $default );
        } else {
            $data = $this->cookie;
        }
        return $data;
    }


    /**
     * 获取数据
     * @access public
     * @param array $data 数据源
     * @param string $name 字段名
     * @param mixed $default 默认值
     * @return mixed
     */
    protected function getData( array $data, string $name, $default = null )
    {
        foreach ( explode( '.', $name ) as $val ) {
            if ( isset( $data[ $val ] ) ) {
                $data = $data[ $val ];
            } else {
                return $default;
            }
        }

        return $data;
    }


    /**
     * 检测是否是合法的IP地址
     *
     * @param string $ip IP地址
     * @param string $type IP地址类型 (ipv4, ipv6)
     *
     * @return boolean
     */
    public function isValidIP( string $ip, string $type = '' ): bool
    {
        switch ( strtolower( $type ) ) {
            case 'ipv4':
                $flag = FILTER_FLAG_IPV4;
                break;
            case 'ipv6':
                $flag = FILTER_FLAG_IPV6;
                break;
            default:
                $flag = null;
                break;
        }

        return boolval( filter_var( $ip, FILTER_VALIDATE_IP, $flag ) );
    }

    /**
     * 将IP地址转换为二进制字符串
     *
     * @param string $ip
     *
     * @return string
     */
    public function ip2bin( string $ip ): string
    {
        if ( $this->isValidIP( $ip, 'ipv6' ) ) {
            $IPHex = str_split( bin2hex( inet_pton( $ip ) ), 4 );
            foreach ( $IPHex as $key => $value ) {
                $IPHex[ $key ] = intval( $value, 16 );
            }
            $IPBin = vsprintf( '%016b%016b%016b%016b%016b%016b%016b%016b', $IPHex );
        } else {
            $IPHex = str_split( bin2hex( inet_pton( $ip ) ), 2 );
            foreach ( $IPHex as $key => $value ) {
                $IPHex[ $key ] = intval( $value, 16 );
            }
            $IPBin = vsprintf( '%08b%08b%08b%08b', $IPHex );
        }

        return $IPBin;
    }

    /**
     * 获取上传的文件信息
     * @access public
     * @param string $name 名称
     * @return null|array|UploadedFile
     */
    public function file( string $name = '' )
    {
        $files = $this->file;
        if ( !empty( $files ) ) {

            if ( strpos( $name, '.' ) ) {
                [ $name, $sub ] = explode( '.', $name );
            }

            // 处理上传文件
            $array = $this->dealUploadFile( $files, $name );

            if ( '' === $name ) {
                // 获取全部文件
                return $array;
            } elseif ( isset( $sub ) && isset( $array[ $name ][ $sub ] ) ) {
                return $array[ $name ][ $sub ];
            } elseif ( isset( $array[ $name ] ) ) {
                return $array[ $name ];
            }
        }
    }

    protected function dealUploadFile( array $files, string $name ): array
    {
        $array = [];
        foreach ( $files as $key => $file ) {
            if ( is_array( $file[ 'name' ] ) ) {
                $item = [];
                $keys = array_keys( $file );
                $count = count( $file[ 'name' ] );

                for ( $i = 0; $i < $count; $i++ ) {
                    if ( $file[ 'error' ][ $i ] > 0 ) {
                        if ( $name == $key ) {
                            $this->throwUploadFileError( $file[ 'error' ][ $i ] );
                        } else {
                            continue;
                        }
                    }

                    $temp[ 'key' ] = $key;

                    foreach ( $keys as $_key ) {
                        $temp[ $_key ] = $file[ $_key ][ $i ];
                    }

                    $item[] = new UploadedFile( $temp[ 'tmp_name' ], $temp[ 'name' ], $temp[ 'type' ], $temp[ 'error' ] );
                }

                $array[ $key ] = $item;
            } else {
                if ( $file instanceof File ) {
                    $array[ $key ] = $file;
                } else {
                    if ( $file[ 'error' ] > 0 ) {
                        if ( $key == $name ) {
                            $this->throwUploadFileError( $file[ 'error' ] );
                        } else {
                            continue;
                        }
                    }

                    $array[ $key ] = new UploadedFile( $file[ 'tmp_name' ], $file[ 'name' ], $file[ 'type' ], $file[ 'error' ] );
                }
            }
        }

        return $array;
    }

    protected function throwUploadFileError( $error )
    {
        static $fileUploadErrors = [
            1 => 'upload File size exceeds the maximum value',
            2 => 'upload File size exceeds the maximum value',
            3 => 'only the portion of file is uploaded',
            4 => 'no file to uploaded',
            6 => 'upload temp dir not found',
            7 => 'file write error',
        ];

        $msg = $fileUploadErrors[ $error ];
        throw new Exception( $msg, $error );
    }
    //doto 增加filter

    /**
     * $username = $request->filter( $request->getQuery( 'username', '' ) )->required( '用户名不能为空' )->int();
     * $mobile = $request->filter( $request->getQuery( 'mobile', '' ) )->required( '手机号不能为空' )->tel();
     *
     * if ( $request->getFilterStatus() === false ) {
     *     throw new ApiException( $request->getFilterFailMessage() );
     * }
     *
     * 返回过滤器实例
     * @return Filter
     */
    public function filter( $return_value = '' )
    {
        $this->filter->setField( $return_value );
        return $this->filter;
    }

    public function getFilterStatus()
    {
        return $this->filter->getStatus();
    }

    public function getFilterFailMessage()
    {
        return $this->filter->getFailMessage();
    }
}

?>
