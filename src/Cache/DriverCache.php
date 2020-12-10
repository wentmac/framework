<?php

/**
 * Power By Tmac PHP MVC framework
 * $Author: zhangwentao $  <zwttmac@qq.com>
 * $Id: CacheDriver.class.php 507 2016-10-31 18:21:39Z zhangwentao $
 * http://www.t-mac.org；
 */

namespace Tmac\Cache;
use Tmac\Contract\ConfigInterface;
use Tmac\Exception\InvalidArgumentException;
use Tmac\Exception\TmacException;

class DriverCache
{
    /**
     * @var ConfigInterface $config
     */
    private $config;

    /**
     * @var 实例
     */
    protected $instance;

    /**
     * @return mixed
     */
    public function getInstance()
    {
        return $this->instance;
    }


    public function __construct( $config )
    {
        $this->config = $config;
        $this->instance = $this->createCacheInstance();
    }

    /**
     * @return mixed|AbstractCache
     * @throws TmacException
     */
    public function createCacheInstance()
    {
        $config = $this->config;
        $default = $config[ 'default' ];
        if ( empty( $config[ 'stores' ][ $default ] ) ) {
            throw new InvalidArgumentException( "Unsupported cache driver [{$default}]." );
        }
        $cache_config = $config[ 'stores' ][ $default ];
        try {
            switch ( $default ) {
                case 'redis':
                    return new RedisCache( $cache_config );
                    break;
                case 'memcached':
                    return new MemcachedCache( $cache_config );
                    break;
                case 'file':
                    return new FileCache( $cache_config );
                    break;
                default:
                    $dbClassName = ucfirst( $default ) . 'Cache';
                    return new $dbClassName( $cache_config );
                    break;
            }
        } catch ( TmacException $e ) {
            throw new TmacException( $e->getMessage() );
        }

        throw new InvalidArgumentException( "Unsupported cache driver [{$config[ 'driver' ]}]." );
    }
}
