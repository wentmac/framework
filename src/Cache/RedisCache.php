<?php

/**
 * Power By Tmac PHP MVC framework
 * $Author: zhangwentao $  <zwttmac@qq.com>
 * $Id: CacheRedis.class.php 507 2016-10-31 18:21:39Z zhangwentao $
 * http://www.t-mac.org；
 */

namespace Tmac\Cache;

use Redis;
use Tmac\Exception\TmacException;

class RedisCache extends AbstractCache
{

    /** @var \Predis\Client|\Redis */
    protected $handler;

    /**
     * 配置参数
     * @var array
     */
    protected $options = [
        'client' => 'phpredis',
        'replication' => false,
        'service' => 'mymaster',
        'port' => 6379,
        'password' => '',
        'timeout' => 0,
        'read_timeout' => 0,
        'expire' => 0,
        'select' => 0,
        'persistent' => false,
        'prefix' => '',
    ];

    /**
     * 构造器
     * 连接Memcached服务器
     *
     * @global array $TmacConfig
     * @access public
     */
    public function __construct( array $options = [] )
    {
        if ( !empty( $options ) ) {
            $this->options = array_merge( $this->options, $options );
        }
        if ( !extension_loaded( 'redis' ) ) {
            throw new TmacException( 'redis扩展没有开启!' );
        }

        if ( $this->options[ 'client' ] == 'predis' ) {
            $this->pRedisConnect();
        } else {
            $this->phpRedisConnect();
        }
    }

    /**
     * phpredis driver
     */
    public function phpRedisConnect(): void
    {
        if ( $this->options[ 'replication' ] === 'cluster' ) {
            $this->handler = new \RedisCluster( null, $this->options[ 'host' ], (int) $this->options[ 'timeout' ], (int) $this->options[ 'read_timeout' ], (bool) $this->options[ 'persistent' ], $this->options[ 'password' ] );
            return;
        }
        if ( $this->options[ 'replication' ] === 'sentinel' ) {
            $sentinel = new \RedisSentinel( $this->options[ 'host' ], (int) $this->options[ 'port' ], (int) $this->options[ 'timeout' ], 'persistent_id_' . $this->options[ 'select' ] );
            //mymaster
            $address = $sentinel->getMasterAddrByName( $this->options[ 'service' ] );
            $this->options[ 'host' ] = $address[ 'address' ];
            $this->options[ 'port' ] = $address[ 'port' ];
        }

        $this->handler = new Redis();


        if ( $this->options[ 'persistent' ] ) {
            $this->handler->pconnect( $this->options[ 'host' ], (int) $this->options[ 'port' ], (int) $this->options[ 'timeout' ], 'persistent_id_' . $this->options[ 'select' ] );
        } else {
            $this->handler->connect( $this->options[ 'host' ], (int) $this->options[ 'port' ], (int) $this->options[ 'timeout' ] );
        }

        if ( !empty( $this->options[ 'prefix' ] ) ) {
            $this->handler->setOption( Redis::OPT_PREFIX, $this->options[ 'prefix' ] );
        }

        if ( !empty( $this->options[ 'read_timeout' ] ) ) {
            $this->handler->setOption( Redis::OPT_READ_TIMEOUT, $this->options[ 'read_timeout' ] );
        }

        if ( '' != $this->options[ 'password' ] ) {
            $this->handler->auth( $this->options[ 'password' ] );
        }

        if ( !empty( $this->options[ 'database' ] ) ) {
            $this->handler->select( $this->options[ 'database' ] );
        }
    }

    /**
     * predis driver
     */
    public function pRedisConnect()
    {
        $params = [];
        foreach ( $this->options as $key => $val ) {
            if ( in_array( $key, [ 'aggregate', 'cluster', 'connections', 'exceptions', 'prefix', 'profile', 'replication', 'parameters' ] ) ) {
                $params[ $key ] = $val;
                unset( $this->options[ $key ] );
            }
        }

        if ( '' == $this->options[ 'password' ] ) {
            unset( $this->options[ 'password' ] );
        }

        $this->handler = new \Predis\Client( $this->options, $params );

        $this->options[ 'prefix' ] = '';
    }

    /**
     * 设置值  构建一个字符串
     * @param string $key KEY名称
     * @param string $value 设置值
     * @param int $timeOut 时间  0表示无过期时间
     */
    public function set( $key, $value, $timeOut = 0 )
    {
        $retRes = $this->handler->set( $key, $value );
        if ( $timeOut > 0 ) {
            $this->handler->expire( $key, $timeOut );
        }

        return $retRes;
    }

    /**
     * 获取一个已经缓存的变量
     *
     * @param String $key 缓存Key
     * @return mixed       缓存内容
     * @access public
     */
    public function get( $key )
    {
        $result = $this->handler->get( $key );
        return $result;
    }

    /**
     * 删除一个已经缓存的变量
     *
     * @param  $key
     * @return boolean       是否删除成功
     * @access public
     */
    public function del( $key )
    {
        return $this->handler->del( $key );
    }

    /**
     * 删除全部缓存变量
     *
     * @return boolean       是否删除成功
     * @access public
     */
    public function delAll()
    {
        return $this->handler->flushAll();
    }

    /**
     * 数据入队列(对应redis的list数据结构)
     * @param string $key KEY名称
     * @param string|array $value 需压入的数据
     * @param bool $right 是否从右边开始入
     * @return int
     */
    public function push( $key, $value, $right = true )
    {
        $rs = $right ? $this->handler->rPush( $key, $value ) : $this->handler->lPush( $key, $value );

        return $rs;
    }

    /**
     * 数据出队列（对应redis的list数据结构）
     * @param string $key KEY名称
     * @param bool $left 是否从左边开始出数据
     * @return mixed
     */
    public function pop( $key, $left = true )
    {
        $val = $left ? $this->handler->lPop( $key ) : $this->handler->rPop( $key );
        return $val;
    }

    /**
     * 数据自增
     * @param string $key KEY名称
     */
    public function increment( $key )
    {
        return $this->handler->incr( $key );
    }

    /**
     * 数据自减
     * @param string $key KEY名称
     */
    public function decrement( $key )
    {
        return $this->handler->decr( $key );
    }

    /**
     * 检测是否存在对应的缓存
     *
     * @param string $key 缓存Key
     * @return boolean      是否存在key
     * @access public
     */
    public function has( $key )
    {
        return $rs = $this->handler->exists( $key );
    }

    /**
     * Closes the redis connection.
     */
    public function __destruct()
    {
        /*
        if (  $this->options[ 'persistent' ] ) ) {
            $this->handler->close();
        }
        */
    }

}
