<?php

/** Power By Tmac PHP MVC framework
 *  $Author: zhangwentao $
 *  $Id: DatabaseDriver.class.php 325 2016-05-31 10:07:35Z zhangwentao $
 */

namespace Tmac\Database;

use Tmac\Container;
use Tmac\Contract\ConfigInterface;
use Tmac\Database\Connector\MysqlConnector;
use Tmac\Debug;
use Tmac\Cache\DriverCache;
use Tmac\Exception\TmacException;

class DriverDatabase
{

    private $app_debug;
    /**
     * @var ConfigInterface $config
     */
    private $config;

    /**
     * @var Debug $debug
     */
    private $debug;

    /**
     * @var DriverCache $cache
     */
    private $cache;

    protected $instance;

    /**
     * @return mixed
     */
    public function getInstance()
    {
        return $this->instance;
    }


    public function __construct( Container $container, $config, $app_debug = false )
    {
        $this->config = $config;
        $this->debug = $container->get( 'debug' );
        $this->cache = $container->get( 'cache' );
        $this->app_debug = $app_debug;

        $this->instance = $this->createConnector();
    }

    /**
     * @return mixed|PDOConnection
     * @throws TmacException
     */
    public function createConnector()
    {
        $config = $this->config;
        $debug = $this->debug;
        $cache = $this->cache;
        $app_debug = $this->app_debug;

        try {
            switch ( $config[ 'type' ] ) {
                case 'mysql':
                    return new MysqlConnector( $config, $app_debug, $debug, $cache );
                    break;
                default:
                    $dbClassName = ucfirst( $config[ 'type' ] ) . 'Connector';
                    return new $dbClassName( $config, $app_debug, $debug, $cache );
                    break;
            }
        } catch ( TmacException $e ) {
            throw new TmacException( $e->getMessage() );
        }

        throw new InvalidArgumentException( "Unsupported driver [{$config[ 'driver' ]}]." );
    }

}