<?php

/**
 *  Power By Tmac PHP MVC framework
 *  $Author: zhangwentao $
 *  $Id: DbMySQLi.class.php 651 2016-11-17 11:29:22Z zhangwentao $
 */

namespace Tmac\Database\Connector;

use Tmac\Database\PDOConnection;
use Tmac\Database\Raw;
use Tmac\Debug;

class MysqlConnector extends PDOConnection
{

    /**
     * 初始化
     */
    public function __construct( $config, Debug $debug, $app_debug = false )
    {
        parent::__construct( $config, $debug, $app_debug );
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

    /**
     * 创建生成查询sql语句
     * @param $conditionBuilders
     * @param $options
     * @return string
     */
    public function buildSelectSql( $conditionBuilders )
    {
        $clauses = [
            $conditionBuilders[ 'select' ],
            $conditionBuilders[ 'from' ],
            $conditionBuilders[ 'join' ],
            $conditionBuilders[ 'where' ],
            $conditionBuilders[ 'group' ],
            $conditionBuilders[ 'having' ],
            $conditionBuilders[ 'union' ],
            $conditionBuilders[ 'order' ],
            $conditionBuilders[ 'limit' ],
            $conditionBuilders[ 'lock' ],
            $conditionBuilders[ 'force' ]
        ];
        $sql = implode( $this->separator, array_filter( $clauses ) );
        return $sql;
    }

    /**
     * 创建生成更新sql语句
     * @param $conditionBuilders
     * @return string
     */
    public function buildUpdateSql( $conditionBuilders )
    {
        $clauses = [
            'UPDATE',
            $conditionBuilders[ 'extra' ],
            $conditionBuilders[ 'table' ],
            'SET',
            $conditionBuilders[ 'data' ],
            $conditionBuilders[ 'join' ],
            $conditionBuilders[ 'where' ],
            $conditionBuilders[ 'order' ],
            $conditionBuilders[ 'limit' ],
            $conditionBuilders[ 'lock' ]
        ];
        $sql = implode( $this->separator, array_filter( $clauses ) );
        return $sql;
    }


    /**
     * 创建生成新增insert sql语句
     * @param $conditionBuilders
     * @return string
     */
    public function buildInsertSql( $conditionBuilders )
    {
        $clauses = [
            !empty( $conditionBuilders[ 'replace' ] ) ? 'REPLACE' : 'INSERT',
            $conditionBuilders[ 'extra' ],
            'INTO',
            $conditionBuilders[ 'table' ],
            '(' . $conditionBuilders[ 'field' ] . ')',
            'VALUES',
            '(' . $conditionBuilders[ 'data' ] . ')',
        ];
        $sql = implode( $this->separator, array_filter( $clauses ) );
        return $sql;
    }

    /**
     * 创建生成删除sql语句
     * @param $conditionBuilders
     * @return string
     */
    public function buildDeleteSql( $conditionBuilders )
    {
        $clauses = [
            'DELETE',
            $conditionBuilders[ 'extra' ],
            'FROM',
            $conditionBuilders[ 'table' ],
            $conditionBuilders[ 'join' ],
            $conditionBuilders[ 'where' ],
            $conditionBuilders[ 'order' ],
            $conditionBuilders[ 'limit' ],
            $conditionBuilders[ 'lock' ]
        ];
        $sql = implode( $this->separator, array_filter( $clauses ) );
        return $sql;
    }

    /**
     * @param $dataSet
     * @return array
     */
    public function buildInserAllSql( $conditionBuilders )
    {
        //$table, $dataSet, $replace
        $table = $conditionBuilders[ 'table' ];
        $dataSet = $conditionBuilders[ 'data' ];
        $replace = $conditionBuilders[ 'replace' ];

        $values = [];
        foreach ( $dataSet as $index => $data ) {
            $values[] = '( ' . implode( ',', array_values( $data ) ) . ' )';

            if ( !isset( $insertFields ) ) {
                $insertFields = array_keys( $data );
            }
        }

        $clauses = [
            !empty( $conditionBuilders[ 'replace' ] ) ? 'REPLACE' : 'INSERT',
            $conditionBuilders[ 'extra' ] ?? '',
            'INTO',
            $conditionBuilders[ 'table' ],
            '(' . implode( ', ', $insertFields ) . ')',
            'VALUES',
            implode( ', ', $values ),
        ];

        /*
        $sql = 'INSERT INTO ' . $table . ' (' . implode( ', ', $insertFields ) . ')' .
            ' VALUES ' . implode( ', ', $values );
        */
        $sql = implode( $this->separator, array_filter( $clauses ) );
        return $sql;
    }

    /**
     * @param $dataSet
     * @return array
     */
    private function getInserAllSql( $table, $dataSet, $replace = false )
    {
        $values = [];
        $binds = [];
        foreach ( $dataSet as $index => $data ) {
            $bind = $this->parseBatchInsertData( $index, $data );
            $values[] = '( ' . implode( ',', array_keys( $bind ) ) . ' )';

            if ( !isset( $insertFields ) ) {
                $insertFields = array_keys( $data );
            }
        }

        $insert_type = $replace ? 'REPLACE' : 'INSERT';
        $sql = $insert_type . ' INTO ' . $table . ' (' . implode( ', ', $insertFields ) . ')' .
            ' VALUES ' . implode( ', ', $values );

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
    public function insertAll( $table, array $dataSet, int $limit = 0, bool $replace = false )
    {
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
                    $sql = $this->getInserAllSql( $table, $item, $replace );
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
        $sql = $this->getInserAllSql( $table, $dataSet, $replace );
        return $this->execute( $sql, $this->binds );
    }

    /**
     * 返回批量插入的bindValue关系
     * @param $data
     * @return array
     */
    private function parseBatchInsertData( $k, $data )
    {
        $bind = [];
        foreach ( $data as $key => $value ) {
            if ( $value instanceof Raw ) {
                $bind[ $key ] = $value->getValue();
            } else {
                $name = 'TmacBind_' . $k . '_' . $key . '_';
                $bind[ ':' . $name ] = $this->binds[ $name ] = $value;
            }
        }
        return $bind;
    }


    /**
     * Closes the database connection.
     */
    public function __destruct()
    {
        is_resource( $this->linkID ) && self::close();
    }

}
