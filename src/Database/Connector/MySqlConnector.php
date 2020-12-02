<?php

/**
 *  Power By Tmac PHP MVC framework
 *  $Author: zhangwentao $
 *  $Id: DbMySQLi.class.php 651 2016-11-17 11:29:22Z zhangwentao $
 */

namespace Tmac\Database\Connector;

use Tmac\Cache\DriverCache;
use Tmac\Container;
use Tmac\Contract\ConfigInterface;
use Tmac\Database\PDOConnection;
use Tmac\Debug;
use Tmac\Exception\TmacException;

class MySqlConnector extends PDOConnection
{

    /**
     * 初始化
     */
    public function __construct( $config, $app_debug = false, Debug $debug, DriverCache $cache )
    {
        parent::__construct( $config, $app_debug, $debug, $cache );
    }

    /**
     * 解析pdo连接的dsn信息
     * @access protected
     * @param array $config 连接信息
     * @return string
     */
    protected function parseDsn( array $config ): string
    {
        if ( !empty( $config[ 'socket' ] ) ) {
            $dsn = 'mysql:unix_socket=' . $config[ 'socket' ];
        } elseif ( !empty( $config[ 'hostport' ] ) ) {
            $dsn = 'mysql:host=' . $config[ 'hostname' ] . ';port=' . $config[ 'port' ];
        } else {
            $dsn = 'mysql:host=' . $config[ 'hostname' ];
        }
        $dsn .= ';dbname=' . $config[ 'database' ];

        if ( !empty( $config[ 'charset' ] ) ) {
            $dsn .= ';charset=' . $config[ 'charset' ];
        }

        return $dsn;
    }

    protected function supportSavepoint(): bool
    {
        return true;
    }

    public function buildSelectSql( $conditionBuilders, $options )
    {
        $clauses = [
            $conditionBuilders[ 'distinct' ],
            $conditionBuilders[ 'select' ],
            $conditionBuilders[ 'from' ],
            $conditionBuilders[ 'join' ],
            $conditionBuilders[ 'where' ],
            $conditionBuilders[ 'group' ],
            $conditionBuilders[ 'having' ],
            $conditionBuilders[ 'order' ],
            $conditionBuilders[ 'limit' ],
            $conditionBuilders[ 'union' ],
            $conditionBuilders[ 'lock' ],
            $conditionBuilders[ 'force' ]
        ];
        $sql = implode( $this->separator, array_filter( $clauses ) );
        return $sql;
    }

    /**
     * Closes the database connection.
     */
    public function __destruct()
    {
        is_resource( $this->linkID ) && self::close();
    }

}
