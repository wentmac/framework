<?php
/**
 * Power By Tmac PHP MVC framework
 * $Author: zhangwentao $  <zwttmac@qq.com>
 * $Id: Config.php 325 2016-05-31 10:07:35Z zhangwentao $
 * http://www.t-mac.org；
 */

namespace Tmac;


use Tmac\Contract\ConfigInterface;
use ArrayAccess;

/**
 * SESSION管理类
 * @package think
 */
class Session implements ArrayAccess
{
    use DITrait;

    protected $config;

    /**
     * @var session
     * 会话状态
     * 会话是启用的，而且存在当前会话
     *
     * 默认状态是0 未加载状态
     */
    private $is_active = 0;

    /**
     * 构造方法
     * @access public
     */
    public function __construct( ConfigInterface $config )
    {
        $this->config = $config;
    }

    /**
     * session 启动初始化
     * @throws \Exception
     */
    public function start()
    {
        if ( $this->getIsActive() ) {
            return;
        }
        ini_set( 'app.session.auto_start', 0 ); //指定会话模块是否在请求开始时自动启动一个会话。默认为 0（不启动）。
        if ( !empty ( $this->config[ 'app.session.name' ] ) )
            session_name( $this->config[ 'app.session.name' ] ); //默认为 PHPSESSID
        if ( !empty ( $this->config[ 'app.session.path' ] ) )
            session_save_path( $this->config[ 'app.session.path' ] ); //如果选择了默认的 files 文件处理器，则此值是创建文件的路径。默认为 /tmp。
        if ( !empty ( $this->config[ 'app.session.expire' ] ) )
            ini_set( 'app.session.gc_maxlifetime', $this->config[ 'app.session.expire' ] );
        if ( $this->config[ 'app.session.type' ] == 'DB' ) {
            ini_set( 'app.session.save_handler', 'user' );  //默认值是 files
            $handler = $this->container->getShared( SessionDb::class ); //开始session存放mysql里的相关操作
            $handler->execute();
        } else if ( $this->config[ 'app.session.type' ] == 'memcache' ) {
            ini_set( "session.save_handler", "memcache" );
            ini_set( "session.save_path", "tcp://{$this->config[ 'memcached.host' ]}:{$this->config[ 'memcached.port' ]}" );
        } else if ( $this->config[ 'app.session.type' ] == 'memcached' ) {
            ini_set( "session.save_handler", "memcached" ); // 是memcached不是memcache
            ini_set( "session.save_path", "{$this->config[ 'memcached.host' ]}:{$this->config[ 'memcached.port' ]}" ); // 不要tcp:[/b]
        } else if ( $this->config[ 'app.session.type' ] == 'redis' ) {
            ini_set( "session.save_handler", "redis" ); // 是memcached不是memcache
            ini_set( "session.save_path", "{$this->config[ 'app.session.redis_dsn' ]}" );
            /*
             * session.save_handler = redis
             * session.save_path = "tcp://host1:6379?weight=1, tcp://host2:6379?weight=2&timeout=2.5, tcp://host3:6379?weight=2&read_timeout=2.5"
             */
        }
        session_start();
    }

    /**
     * @return bool whether the session has started
     */
    public function getIsActive()
    {
        if ( $this->is_active === 0 ) {
            //还未加载
            $this->is_active = session_status() === PHP_SESSION_ACTIVE;
        }
        return $this->is_active;
    }

    /**
     * Returns the number of items in the session.
     * @return int the number of session variables
     * @throws \Exception
     */
    private function getCount()
    {
        $this->start();
        return count( $_SESSION );
    }

    /**
     * Returns the number of items in the session.
     * This method is required by [[\Countable]] interface.
     * @return int number of items in the session.
     * @throws \Exception
     */
    public function count()
    {
        return $this->getCount();
    }

    /**
     * * Returns the session variable value with the session variable name.
     * If the session variable does not exist, the `$defaultValue` will be returned.
     * @param string $key the session variable name
     * @param mixed $defaultValue the default value to be returned when the session variable does not exist.
     * @return mixed the session variable value, or $defaultValue if the session variable does not exist.
     * @throws \Exception
     */
    public function get( $key, $defaultValue = null )
    {
        $this->start();
        return isset( $_SESSION[ $key ] ) ? $_SESSION[ $key ] : $defaultValue;
    }


    /**
     * Adds a session variable.
     * If the specified name already exists, the old value will be overwritten.
     * @param string $key session variable name
     * @param mixed $value session variable value
     * @throws \Exception
     */
    public function set( $key, $value )
    {
        $this->start();
        $_SESSION[ $key ] = $value;
    }


    /**
     * Removes a session variable.
     * @param string $key the name of the session variable to be removed
     * @return mixed the removed value, null if no such session variable.
     * @throws \Exception
     */
    public function remove( $key )
    {
        $this->start();
        if ( isset( $_SESSION[ $key ] ) ) {
            $value = $_SESSION[ $key ];
            unset( $_SESSION[ $key ] );

            return $value;
        }

        return null;
    }

    /**
     * Removes all session variables.
     * @throws \Exception
     */
    public function removeAll()
    {
        $this->start();
        foreach ( array_keys( $_SESSION ) as $key ) {
            unset( $_SESSION[ $key ] );
        }
    }

    /**
     * @param mixed $key session variable name
     * @return bool whether there is the named session variable
     * @throws \Exception
     */
    public function has( $key )
    {
        $this->start();
        return isset( $_SESSION[ $key ] );
    }


    /**
     * Ends the current session and store session data.
     */
    public function close()
    {
        if ( $this->getIsActive() ) {
            session_write_close();
        }
    }

    /**
     * Sets the session ID.
     * This is a wrapper for [PHP session_id()](https://secure.php.net/manual/en/function.session-id.php).
     * @param string $value the session ID for the current session
     */
    public function setId( $value )
    {
        session_id( $value );
    }

    /**
     * 销毁全部session会话
     * @throws \Exception
     */
    public function destroy()
    {
        if ( $this->getIsActive() ) {
            $sessionId = session_id();
            $this->close();
            $this->setId( $sessionId );
            $this->start();
            session_unset();
            session_destroy();
            $this->setId( $sessionId );
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
        return $this->has( $offset );
    }

    /**
     * This method is required by the interface [[\ArrayAccess]].
     * @param int $offset the offset to retrieve element.
     * @return mixed the element at the offset, null if no element is found at the offset
     * @throws \Exception
     */
    public function offsetGet( $offset )
    {
        $this->get( $offset );
    }

    /**
     * This method is required by the interface [[\ArrayAccess]].
     * @param int $offset the offset to set element
     * @param mixed $item the element value
     * @throws \Exception
     */
    public function offsetSet( $offset, $item )
    {
        return $this->set( $offset, $item );
    }

    /**
     * This method is required by the interface [[\ArrayAccess]].
     * @param mixed $offset the offset to unset element
     * @throws \Exception
     */
    public function offsetUnset( $offset )
    {
        return $this->remove( $offset );
    }

}