<?php

/**
 * Power By Tmac PHP MVC framework
 * $Author: zhangwentao $  <zwttmac@qq.com>
 * $Id: CacheRedis.class.php 507 2016-10-31 18:21:39Z zhangwentao $
 * http://www.t-mac.org；
 */

namespace Tmac\Cache;

use Tmac\Contract\CacheInterface;
use Tmac\Contract\ConfigInterface;

class RedisCache implements CacheInterface
{

    /**
     * Memcached实例
     *
     * @var objeact
     * @access private
     */
    private $redis;

    /**
     * 配置文件名称
     *
     * @var string
     * @access private
     * array(
     *    'host' 　=> '127.0.0.1',
     *    'port＇　=>  6379,
     *    'cluster' => false,
     *    'persistent' => false,
     *    'timeout' => 0,
     *    'read_timeout' => 0,
     *    'password' => '',
     *    'prefix' =>＇＇
     * )
     */
    private $redis_config;

    /**
     * 构造器
     * 连接Memcached服务器
     *
     * @access public
     */
    public function __construct( ConfigInterface $config )
    {
        if ( !extension_loaded( 'redis' ) ) {
            throw new TmacException( 'redis扩展没有开启!' );
        }
        if ( empty( $config ) ) {
            throw new TmacException( 'redis配置不存在!' );
        }
        $this->redis_config = $config;
        $timeout = empty( $config[ 'redis.timeout' ] ) ? 0 : $config[ 'redis.timeout' ];
        $this->redis = new Redis();
        if ( isset( $config[ 'redis.persistent' ] ) && $config[ 'redis.persistent' ] ) {
            $this->redis->pconnect( $config[ 'redis.host' ], $config[ 'redis.port' ], $timeout );
        } else {
            $this->redis->connect( $config[ 'redis.host' ], $config[ 'redis.port' ], $timeout );
        }
        if ( !empty( $config[ 'redis.prefix' ] ) ) {
            $this->redis->setOption( Redis::OPT_PREFIX, $config[ 'redis.prefix' ] );
        }
        if ( !empty( $config[ 'redis.read_timeout' ] ) ) {
            $this->redis->setOption( Redis::OPT_READ_TIMEOUT, $config[ 'redis.read_timeout' ] );
        }
        if ( !empty( $config[ 'redis.password' ] ) ) {
            $this->redis->auth( $config[ 'redis.password' ] );
        }
        if ( !empty( $config[ 'redis.database' ] ) ) {
            $this->redis->select( $config[ 'redis.database' ] );
        }

    }

    /**
     * 得到 Redis 原始对象可以有更多的操作
     *
     * @return redis object
     */
    public function getRedis()
    {
        return $this->redis;
    }

    /**
     * 设置值  构建一个字符串
     * @param string $key KEY名称
     * @param string $value 设置值
     * @param int $timeOut 时间  0表示无过期时间
     */
    public function set( $key, $value, $timeOut = 0 )
    {
        $retRes = $this->redis->set( $key, $value );
        if ( $timeOut > 0 ) {
            $this->redis->expire( $key, $timeOut );
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
        $result = $this->redis->get( $key );
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
        return $this->redis->del( $key );
    }

    /**
     * 删除全部缓存变量
     *
     * @return boolean       是否删除成功
     * @access public
     */
    public function delAll()
    {
        return $this->redis->flushAll();
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
        $rs = $right ? $this->redis->rPush( $key, $value ) : $this->redis->lPush( $key, $value );

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
        $val = $left ? $this->redis->lPop( $key ) : $this->redis->rPop( $key );
        return $val;
    }

    /**
     * 数据自增
     * @param string $key KEY名称
     */
    public function increment( $key )
    {
        return $this->redis->incr( $key );
    }

    /**
     * 数据自减
     * @param string $key KEY名称
     */
    public function decrement( $key )
    {
        return $this->redis->decr( $key );
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
        return $rs = $this->redis->exists( $key );
    }

    /**
     * Closes the redis connection.
     */
    public function __destruct()
    {
        if ( !isset( $this->redis_config[ 'redis.persistent' ] ) || empty( $this->redis_config[ 'redis.persistent' ] ) ) {
            $this->redis->close();
        }
    }

}
