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

class MysqlConnector extends PDOConnection
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
     * 批量
     * @param $table
     * @param array $dataSet
     * @param int $limit
     * @return array|int
     * @throws \Throwable
     * @throws \Tmac\Exception\BindParamException
     */
    public function insertAll( $table, array $dataSet, int $limit = 0 )
    {
        $this->binds = [];
        if ( empty( $dataSet ) || !is_array( reset( $dataSet ) ) ) {
            return 0;
        }
        if ( 0 === $limit && count( $dataSet ) >= 5000 ) {
            $limit = 1000;
        }
        if ( $limit ) {
            // 分批写入 自动启动事务支持
            $this->startTrans();

            try {
                $array = array_chunk( $dataSet, $limit, true );
                $count = 0;

                foreach ( $array as $item ) {
                    $sql = $this->getInserAllSql( $item );
                    $count += $this->execute( $sql, $this->binds );
                }

                // 提交事务
                $this->commit();
            } catch ( \Exception | \Throwable $e ) {
                $this->rollback();
                throw $e;
            }

            return $count;
        }

        $sql = $this->getInserAllSql( $table, $dataSet );
        return $this->execute( $sql, $this->binds );
    }

    /**
     * 返回批量插入的bindValue关系
     * @param $data
     * @return array
     */
    private function parseBatchData( $k, $data )
    {
        $bind = [];
        foreach ( $data as $key => $value ) {
            $name = 'TmacBind_' . $key . '_' . $k . '_';
            $bind[ ':'.$name ] = $this->binds[ $name ] = $value;
        }
        return $bind;
    }

    /**
     * @param $dataSet
     * @return array
     */
    private function getInserAllSql( $table, $dataSet )
    {
        $values = [];
        $binds = [];
        foreach ( $dataSet as $index => $data ) {
            $bind = $this->parseBatchData( $index, $data );
            $values[] = '( ' . implode( ',', array_keys( $bind ) ) . ' )';

            if ( !isset( $insertFields ) ) {
                $insertFields = array_keys( $data );
            }
        }


        $sql = 'INSERT INTO ' . $table . ' (' . implode( ', ', $insertFields ) . ')' .
            ' VALUES ' . implode( ', ', $values );

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
