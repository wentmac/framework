<?php
/**
 * Power By Tmac PHP MVC framework
 * $Author: zhangwentao $  <zwttmac@qq.com>
 * $Id: Config.php 325 2016-05-31 10:07:35Z zhangwentao $
 * http://www.t-mac.org；
 */

namespace Tmac;

use ArrayAccess;
use DateTimeInterface;
use Tmac\Contract\ConfigInterface;

/**
 * 配置管理类
 * @package think
 */
class Cookie implements ArrayAccess
{
    /**
     * 配置参数
     * @var array
     */
    protected $config = [
        // cookie 保存时间
        'expire' => 0,
        // cookie 保存路径
        'path' => '/',
        // cookie 有效域名
        'domain' => '',
        //  cookie 启用安全传输
        'secure' => false,
        // httponly设置
        'httponly' => false,
        // samesite 设置，支持 'strict' 'lax'
        'samesite' => '',
    ];

    /**
     * Cookie写入数据
     * @var array
     */
    protected $_cookies = [];
    protected $_exists_cookies = [];

    /**
     * @param int $expire
     */
    public function setExpire( int $expire )
    {
        $this->config[ 'expire' ] = $expire;
        return $this;
    }

    /**
     * @param string $path
     */
    public function setPath( string $path )
    {
        $this->config[ 'path' ] = $path;
        return $this;
    }

    /**
     * @param string $domain
     */
    public function setDomain( string $domain )
    {
        $this->config[ 'domain' ] = $domain;
        return $this;
    }

    /**
     * @param bool $secure
     */
    public function setSecure( bool $secure )
    {
        $this->config[ 'secure' ] = $secure;
        return $this;
    }

    /**
     * @param bool $httponly
     */
    public function setHttponly( bool $httponly )
    {
        $this->config[ 'httponly' ] = $httponly;
        return $this;
    }

    /**
     * @param string $samesite
     */
    public function setSamesite( string $samesite )
    {
        $this->config[ 'samesite' ] = $samesite;
        return $this;
    }

    /**
     * 构造方法
     * @access public
     */
    public function __construct( ConfigInterface $config )
    {
        $this->config = array_merge( $this->config, array_change_key_case( $config[ 'cookie' ] ) );
        $this->_exists_cookies = $_COOKIE;
    }

    /**
     * 获取cookie
     * @access public
     * @param mixed $name 数据名称
     * @param string $default 默认值
     * @return mixed
     */
    public function get( string $name = '', $default = null )
    {
        if ( empty( $name ) ) {
            return $this->_exists_cookies;
        }
        return isset( $this->_exists_cookies[ $name ] ) ? $this->_exists_cookies[ $name ] : $default;
    }

    /**
     * 是否存在Cookie参数
     * @access public
     * @param string $name 变量名
     * @return bool
     */
    public function has( string $name ): bool
    {
        return isset( $this->_exists_cookies[ $name ] );
    }

    /**
     * Cookie 设置
     *
     * @access public
     * @param string $name cookie名称
     * @param string $value cookie值
     * @return void
     */
    public function set( string $name, string $value, $option = null ): void
    {
        // 参数设置(会覆盖黙认设置)
        if ( !is_null( $option ) ) {
            if ( is_numeric( $option ) || $option instanceof DateTimeInterface ) {
                $option = [ 'expire' => $option ];
            }

            $config = array_merge( $this->config, array_change_key_case( $option ) );
        } else {
            $config = $this->config;
        }

        if ( $config[ 'expire' ] instanceof DateTimeInterface ) {
            $expire = $config[ 'expire' ]->getTimestamp();
        } else {
            $expire = !empty( $config[ 'expire' ] ) ? time() + intval( $config[ 'expire' ] ) : 0;
        }

        $this->_exists_cookies[ $name ] = $value;
        $this->setCookie( $name, $value, $expire, $config );
    }

    /**
     * Cookie 保存
     *
     * @access public
     * @param string $name cookie名称
     * @param string $value cookie值
     * @param int $expire 有效期
     * @param array $option 可选参数
     * @return void
     */
    protected function setCookie( string $name, string $value, int $expire, array $option = [] ): void
    {
        $this->_cookies[ $name ] = [ $value, $expire, $option ];
    }

    /**
     * Cookie删除
     * @access public
     * @param string $name cookie名称
     * @return void
     */
    public function remove( string $name ): void
    {
        $this->setCookie( $name, '', time() - 3600, $this->config );
    }

    /**
     * Removes all cookies.
     * @throws InvalidCallException if the cookie collection is read only
     */
    public function removeAll()
    {
        foreach ( $this->_exists_cookies as $name => $val ) {
            $this->remove( $name );
        }
    }

    /**
     * 获取cookie保存数据
     * @access public
     * @return array
     */
    public function getCookie(): array
    {
        return $this->_cookies;
    }

    /**
     * 保存Cookie
     * @access public
     * @return void
     */
    public function save(): void
    {
        foreach ( $this->_cookies as $name => $val ) {
            [ $value, $expire, $option ] = $val;

            $this->saveCookie(
                $name,
                $value,
                $expire,
                $option[ 'path' ],
                $option[ 'domain' ],
                $option[ 'secure' ] ? true : false,
                $option[ 'httponly' ] ? true : false,
                $option[ 'samesite' ]
            );
        }
    }

    /**
     * 保存Cookie
     * @access public
     * @param string $name cookie名称
     * @param string $value cookie值
     * @param int $expire cookie过期时间
     * @param string $path 有效的服务器路径
     * @param string $domain 有效域名/子域名
     * @param bool $secure 是否仅仅通过HTTPS
     * @param bool $httponly 仅可通过HTTP访问
     * @param string $samesite 防止CSRF攻击和用户追踪
     * @return void
     */
    protected function saveCookie( string $name, string $value, int $expire, string $path, string $domain, bool $secure, bool $httponly, string $samesite ): void
    {
        if ( PHP_VERSION_ID >= 70300 ) {
            setcookie( $name, $value, [
                'expires' => $expire,
                'path' => $path,
                'domain' => $domain,
                'secure' => $secure,
                'httponly' => $httponly,
                'samesite' => $samesite,
            ] );
        } else {
            // Work around for setting sameSite cookie prior PHP 7.3
            // https://stackoverflow.com/questions/39750906/php-setcookie-samesite-strict/46971326#46971326
            if ( !is_null( $samesite ) ) {
                $path .= '; samesite=' . $samesite;
            }
            setcookie( $name, $value, $expire, $path, $domain, $secure, $httponly );
        }
    }


    /**
     * This method is required by the interface [[\ArrayAccess]].
     * @param mixed $offset the offset to check on
     * @return bool
     * @throws \Exception
     */
    public function offsetExists( $offset )
    {
    }

    /**
     * This method is required by the interface [[\ArrayAccess]].
     * @param int $offset the offset to retrieve element.
     * @return mixed the element at the offset, null if no element is found at the offset
     * @throws \Exception
     */
    public function offsetGet( $offset )
    {
    }

    /**
     * This method is required by the interface [[\ArrayAccess]].
     * @param int $offset the offset to set element
     * @param mixed $item the element value
     * @throws \Exception
     */
    public function offsetSet( $offset, $item )
    {
    }

    /**
     * This method is required by the interface [[\ArrayAccess]].
     * @param mixed $offset the offset to unset element
     * @throws \Exception
     */
    public function offsetUnset( $offset )
    {

    }
}