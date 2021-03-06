<?php

/**
 * Power By Tmac PHP MVC framework
 * $Author: zhangwentao $  <zwttmac@qq.com>
 * $Id: CacheMemcached.class.php 507 2016-10-31 18:21:39Z zhangwentao $
 * http://www.t-mac.org；
 */

namespace Tmac\Cache;

use Tmac\Contract\ConfigInterface;
use Tmac\Exception\TmacException;
use Memcache;

class MemcachedCache extends AbstractCache
{

    /**
     * Memcached实例
     *
     * @var objeact
     * @access private
     */
    private $memcached;

    /**
     * 是否启用Memcached压缩
     *
     * @var int
     * @access private
     */
    private $compression;

    private $config;

    /**
     * 构造器
     * 连接Memcached服务器
     *
     * @access public
     */
    public function __construct( ConfigInterface $config )
    {
        if ( !extension_loaded( 'memcache' ) ) {
            throw new TmacException( 'memcached扩展没有开启!' );
        }
        $this->config = $config;
        $this->memcached = new Memcache();
        $this->memcached->addServer( $this->config[ 'cache.memcached.host' ], $this->config[ 'cache.memcached.port' ], $this->config[ 'cache.memcached.persistent' ], $this->config[ 'cache.memcached.weight' ], $this->config[ 'cache.memcached.timeout' ] );
        $this->compression = ( $this->config[ 'cache.memcached.compression' ] ? MEMCACHE_COMPRESSED : 0 );
    }

    /**
     * 设置一个缓存变量
     *
     * @param String $key 缓存Key
     * @param mixed $value 缓存内容
     * @param int $expire 缓存时间(秒)
     * @return boolean       是否缓存成功
     * @access public
     * @abstract
     */
    public function set( $key, $value, $expire = 60 )
    {
        return $this->memcached->set( $key, $value, $this->compression, $expire );
    }

    /**
     * 对一个已有的key举行覆写操纵
     * @param type $key
     * @param type $value
     * @param type $expire
     * @return type
     */
    public function replace( $key, $value, $expire = 60 )
    {
        return $this->memcached->replace( $key, $value, $this->compression, $expire );
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
        return $this->memcached->get( $key );
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
        return $this->memcached->delete( $key );
    }

    /**
     * 删除全部缓存变量
     *
     * @return boolean       是否删除成功
     * @access public
     */
    public function delAll()
    {
        return $this->memcached->flush();
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
        return ( $this->get( $key ) === false ? false : true );
    }

    /**
     * Closes the memcached connection.
     */
    public function __destruct()
    {
        $this->memcached->close();
    }

}