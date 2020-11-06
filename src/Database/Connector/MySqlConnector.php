<?php

/**
 *  Power By Tmac PHP MVC framework
 *  $Author: zhangwentao $
 *  $Id: DbMySQLi.class.php 651 2016-11-17 11:29:22Z zhangwentao $
 */

namespace Tmac\Database\Connector;

use Tmac\Cache\DriverCache;
use Tmac\Contract\ConfigInterface;
use Tmac\Database\PDOConnection;
use Tmac\Debug;
use Tmac\Exception\TmacException;

class MySqlConnector extends PDOConnection
{

    /**
     * 初始化
     */
    public function __construct( ConfigInterface $config, Debug $debug, DriverCache $cache )
    {
        parent::__construct( $config, $debug, $cache );
    }

    /**
     * 初始化数据库连接
     * @param bool $master 是否主服务器
     */
    public function initConnect( bool $master = true )
    {
        $config = $this->config[ 'database.mysql' ];
        if ( $config[ 'deploy' ] ) {
            if ( $master ) {
                if ( !$this->linkWrite ) {
                    $this->linkWrite = $this->connectMulti( true );
                }
                $this->linkID = $this->linkWrite;
            } else {
                if ( !$this->linkRead ) {
                    $this->linkRead = $this->connectMulti( false );
                }
                $this->linkID = $this->linkRead;
            }
        } else {
            //连接数据库
            $this->linkID = $this->connect( $this->config[ 'database.mysql' ] );
        }

        if ( $this->linkID ) {
            //设置编码与sql_mode
            mysqli_query( $this->linkID, "SET NAMES '{$this->config[ 'database.mysql.charset' ]}', sql_mode=''" );
        } else {
            throw new TmacException( '无法连接到read数据库:' . $this->getError() );
        }

    }


    /**
     * 连接数据库
     *
     * @return bool
     * @global array $TmacConfig
     */
    public function connect( $config )
    {
        $fun = $config[ 'pconnect' ] ? 'mysqli_pconnect' : 'mysqli_connect';
        return $fun( $config[ 'hostname' ], $config[ 'username' ], $config[ 'password' ], $config[ 'database' ], $config[ 'port' ] );
    }


    /**
     * 连接主/从库
     * @return bool
     */
    public function connectMulti( bool $master = false )
    {
        $config = $this->config[ 'database.mysql' ];
        if ( !$config[ 'deploy' ] ) {
            //没有配置分布式（主从，读写分离）
            return true;
        }

        if ( $master ) {
            $deploy_config = $config[ 'write' ];
        } else {
            $deploy_config = $config[ 'read' ];
        }

        if ( empty( $deploy_config ) ) {
            return true;
        }
        // 如果数组即多个读库，那么通过随机函数array_rand()挑一个，默认取第一个
        if ( isset( $deploy_config ) && is_array( $deploy_config ) ) {
            $config_choose = count( $deploy_config ) > 1
                ? array_rand( $deploy_config )
                : $deploy_config[ 0 ];
            $config = array_merge( $config, $config_choose );
        }
        //连接数据库
        return $this->connect( $config );
    }

    /**
     * 选择数据库
     *
     * @param string $database
     * @return bool
     */
    public function selectDatabase( $database )
    {
        return mysqli_select_db( $database, $this->linkID );
    }

    /**
     * 执行一条SQL查询语句 返回资源标识符
     *
     * @param string $sql
     */
    public function query( $sql )
    {
        //判断如果有手动设置读主库，默认是读从库
        $master = isset( $this->readMaster ) ? $this->readMaster : false;
        $this->initConnect( $master );
        $rs = mysqli_query( $this->linkID, $sql );
        if ( $rs ) {
            $this->queryNum++;
            $this->numRows = mysqli_affected_rows( $this->linkID );
            $this->debug( $sql );
            return $rs;
        } else {
            $this->debug( $sql, false, $this->getError() );
            $this->success = false;
            return false;
        }
    }

    /**
     * 执行一条SQL语句 返回似乎执行成功
     *
     * @param string $sql
     */
    public function execute( $sql )
    {
        //判断如果有手动设置读主库，默认是读从库
        $master = true;
        $this->initConnect( $master );

        //如果设置了主从分离 并且 配置开启了read_master参数，并且执行了写入操作，则后续该表所有的查询都会连接主服务器
        if ( !empty( $this->config[ 'database.mysql.deploy' ] ) && !empty( $this->config[ 'database.mysql.read_master' ] ) ) {
            $this->readMaster = true;
        }

        if ( mysqli_query( $this->linkID, $sql ) ) {
            $this->queryNum++;
            $this->numRows = mysqli_affected_rows( $this->linkID );
            $this->debug( $sql );

            //todo 清理缓存
            return true;
        } else {
            $this->debug( $sql, false, $this->getError() );
            $this->success = false;
            return false;
        }
    }

    /**
     * 从结果集中取出数据
     *
     * @param resource $rs
     */
    public function fetch( $rs )
    {
        return mysqli_fetch_assoc( $rs );
    }

    /**
     * 从结果集中取出对象
     *
     * @param resource $rs
     */
    public function fetch_object( $rs )
    {
        return mysqli_fetch_object( $rs );
    }

    /**
     * 返回结果集的数组形式row
     * @param <type> $result
     * @return <type>
     */
    public function fetch_row( $result )
    {
        return mysqli_fetch_row( $result );
    }

    /**
     * 开始事务
     *
     * @return bool
     */
    public function startTrans()
    {
        if ( !$this->linkID ) {
            return false;
        }
        ++$this->trans_level;
        if ( $this->trans_level == 1 && !$this->trans_status ) { //保证最外层的transtion才start
            $this->trans_status = $this->execute( 'START TRANSACTION' );
        }
        return $this->trans_status;
    }

    /**
     * 提交事务
     *
     * @return bool
     */
    public function commit()
    {
        if ( $this->trans_level == 1 ) { //如果当前事务只有一层时 才能提交
            $this->execute( 'COMMIT' );
            $this->trans_status = false;    //事务回滚完毕后 关闭事务的开始状态
        }
        --$this->trans_level;
        return true;
    }

    /**
     * 回滚事务
     *
     * @return bool
     */
    public function rollback()
    {
        if ( $this->trans_level == 1 ) { //如果当前事务只有一层时 才能回滚
            $this->execute( 'ROLLBACK' );
            $this->trans_status = false; //事务回滚完毕后 关闭事务的开始状态
            if ( $this->success === FALSE ) {
                $this->success = TRUE;
            }
        }
        --$this->trans_level;
        return true;
    }

    /**
     * 执行INSERT命令.返回AUTO_INCREMENT
     * 返回0为没有插入成功
     *
     * @param string $sql SQL语句
     * @access public
     * @return integer
     */
    public function insert( $sql )
    {
        $this->execute( $sql );
        return mysqli_insert_id( $this->linkID );
    }

    /**
     * 释放结果集
     *
     * @param resource $rs 结果集
     * @access protected
     * @return boolean
     */
    protected function free( $rs )
    {
        return mysqli_free_result( $rs );
    }

    /**
     * 关闭数据库
     *
     * @access public
     * @return boolean
     */
    public function close()
    {
        return mysqli_close( $this->linkID );
    }

    /**
     * 获取错误信息
     *
     * @return void
     * @access public
     */
    public function getError()
    {
        return mysqli_errno( $this->linkID ) . " : " . mysqli_error( $this->linkID );
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
     * 通过setWhere等方法来取查询的最终sql;
     * 主要是给UNION 或 UNION ALL用的  where IN($sql)
     * $query1= $dao->getSqlByWhere();
     * $query2= $dao->getSqlByWhere();
     * $res = $dao->getConn()->getAllObject($query1." UNION ".$query2);
     *
     * @return type
     */
    public function getSqlByWhere( QueryBuilderDatabase $query )
    {
        $sql = "SELECT ";
        if ( $query->getTop() != null ) {
            $sql .= "TOP {$this->getTop()} ";
        }
        $sql .= "{$query->getField()} "
            . "FROM {$query->getTable()} ";

        if ( $query->getJoinString() != null ) {
            $sql .= "{$query->getJoinString()} ";
        }
        if ( $query->getWhere() != null ) {
            $sql .= "WHERE {$query->getWhere()} ";
        }
        if ( $query->getGroupby() != null ) {
            $sql .= "GROUP BY {$query->getGroupby()} ";
        }
        if ( $query->getOrderBy() != null ) {
            $sql .= "ORDER BY {$query->getOrderBy()} ";
        }
        if ( $query->getLimit() != null && $query->getOffset() != null ) {
            $sql .= "LIMIT {$query->getLimit()} {$query->getOffset()}";
        }
        return $sql;
    }

    /**
     * 通过主键取数据库信息
     * @return type
     */
    public function getInfoSqlByPk( QueryBuilderDatabase $query )
    {
        $sql = "SELECT {$this->getField()} "
            . "FROM {$this->getTable()} "
            . "WHERE {$this->getPrimaryKey()}={$this->getPk()}";
        return $sql;
    }

    /**
     * 通过$where条件取数据库信息
     * @return type
     */
    public function getInfoSqlByWhere( QueryBuilderDatabase $query )
    {
        $sql = "SELECT {$query->getField()} "
            . "FROM {$query->getTable()} ";
        if ( $query->getJoinString() != null ) {
            $sql .= "{$query->getJoinString()} ";
        }
        $sql .= "WHERE {$query->getWhere()}";
        if ( $query->getOrderBy() !== null ) {
            $sql .= " ORDER BY {$query->getOrderBy()} ";
        }
        return $sql;
    }

    /**
     * 通过$where条件取总数
     * @return integer
     */
    public function getCountSqlByWhere( QueryBuilderDatabase $query )
    {
        $sql_count = "SELECT COUNT({$query->getCountField()}) FROM {$query->getTable()} ";
        if ( $query->getWhere() !== null ) {
            $sql_count .= "WHERE " . $query->getWhere();
        }
        return $sql_count;
    }

    /**
     * 通过主键删除一条记录{删除数据的操作请慎用}
     * @return type
     */
    public function getDeleteSqlByPk( QueryBuilderDatabase $query )
    {
        $sql = "DELETE FROM {$query->getTable()} "
            . "WHERE {$query->getPrimaryKey()}={$query->getPk()}";
        return $sql;
    }

    /**
     * 通过$where条件删除N条记录{删除数据的操作请慎用}
     * @return type
     */
    public function getDeleteSqlByWhere( QueryBuilderDatabase $query )
    {
        $sql = "DELETE FROM {$query->getTable()} "
            . "WHERE {$query->getWhere()}";
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
