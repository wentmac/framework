<?php

/** Power By Tmac PHP MVC framework
 *  $Author: zhangwentao $
 *  $Id: DatabaseDriver.class.php 325 2016-05-31 10:07:35Z zhangwentao $
 */

namespace Tmac\Database;

use Tmac\Contract\ConfigInterface;
use Tmac\Debug;
use Tmac\Cache\DriverCache;
use Tmac\Exception\TmacException;

class DriverDatabase
{

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

    protected  $instance;

    /**
     * @return mixed
     */
    public function getInstance()
    {
        return $this->instance;
    }


    public function __construct( ConfigInterface $config, Debug $debug, DriverCache $cache )
    {
        $this->config = $config;
        $this->debug = $debug;
        $this->cache = $cache;

        $this->instance = $this->createConnector();
    }

    public function createConnector()
    {
        $config = $this->config;
        $debug = $this->debug;
        $cache = $this->cache;
        if ( !isset( $config[ 'database.mysql' ] ) ) {
            throw new TmacException( 'A driver must be specified.' );
        }

        switch ( $config[ 'database.mysql.driver' ] ) {
            case 'mysql':
                return new MysqlDatabase( $config, $debug, $cache );
            case 'mysqli':
                return new MysqliDatabase( $config, $debug, $cache );
            case 'mssql':
                return new MssqlDatabase( $config, $debug, $cache );
        }

        throw new InvalidArgumentException( "Unsupported driver [{$config[ 'database.mysql.driver' ]}]." );
    }

}