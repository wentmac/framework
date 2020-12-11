<?php

/** Power By Tmac PHP MVC framework
 *  $Author: zhangwentao $
 *  $Id: Database.class.php 651 2016-11-17 11:29:22Z zhangwentao $
 */

namespace Tmac\Database;

use PDO;
use PDOStatement;
use PDOException;
use Tmac\Contract\DatabaseInterface;
use Tmac\Debug;
use Tmac\Exception\BindParamException;

abstract class PDOConnection implements DatabaseInterface
{
    const PARAM_FLOAT = 21;
    /**
     * 数据库连接参数配置
     * @var array
     */
    protected $dbConnectionConfig = [
        // 数据库类型
        'type' => '',
        // 服务器地址
        'hostname' => '',
        // 数据库名
        'database' => '',
        // 用户名
        'username' => '',
        // 密码
        'password' => '',
        // 端口
        'port' => '',
        // 连接dsn
        'dsn' => '',
        // 数据库连接参数
        'params' => [],
        // 数据库编码默认采用utf8
        'charset' => 'utf8',
        // 数据库表前缀
        'prefix' => '',
        // 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
        'deploy' => 0,
        // 数据库读写是否分离 主从式有效
        'rw_separate' => false,
        // 读写分离后 主服务器数量
        'master_num' => 1,
        // 指定从服务器序号
        'slave_no' => '',
        // 模型写入后自动读取主服务器
        'read_master' => false,
        // 是否严格检查字段是否存在
        'fields_strict' => true,
        // 监听SQL
        'trigger_sql' => true,
        // Builder类
        'builder' => '',
        // 是否需要断线重连
        'break_reconnect' => false,
        // 断线标识字符串
        'break_match_str' => [],
    ];

    /**
     * PDO操作实例
     * @var PDOStatement
     */
    protected $PDOStatement;

    /**
     * 当前SQL指令
     * @var string
     */
    protected $queryStr = '';

    /**
     * 数据库连接ID 支持多个连接
     * @var array
     */
    protected $links = [];

    /**
     * 数据库连接标识
     *
     * @var resource
     */
    protected $linkID;


    /**
     * 当前读连接ID
     * @var object
     */
    protected $linkRead;

    /**
     * 当前写连接ID
     * @var object
     */
    protected $linkWrite;

    /**
     * 是否读取主库
     * @var bool
     */
    protected $readMaster;

    /**
     * 执行SQL语句的次数
     *
     * @var integer
     * @access protected
     */
    protected $queryNum = 0;

    /**
     * 返回或者影响记录数
     * @var type
     */
    protected $numRows = 0;

    protected $is_cache = false;

    /**
     * 事务计数器，只有transLevel==1时才真正的执行
     * @var type
     */
    protected $transLevel = 0;

    /**
     * 重连次数
     * @var int
     */
    protected $reConnectTimes = 0;

    /**
     * 查询结果类型
     * @var int
     */
    protected $fetchType = PDO::FETCH_ASSOC;

    /**
     * 字段属性大小写
     * @var int
     */
    protected $attrCase = PDO::CASE_LOWER;

    /**
     * 数据库左标识符
     * @var type
     */
    protected $identifier_left = '`';

    /**
     * 数据库右标识符
     * @var type
     */
    protected $identifier_right = '`';

    /**
     * Debug状态
     * @var type
     */
    protected $debug_status = false;

    /**
     * 查询开始时间
     * @var float
     */
    protected $queryStartTime;

    protected $config;
    protected $dbConfig;

    /**
     * @var string the separator between different fragments of a SQL statement.
     * Defaults to an empty space. This is mainly used by [[build()]] when generating a SQL statement.
     */
    protected $separator = ' ';

    /**
     * PDO连接参数
     * @var array
     */
    protected $params = [
        PDO::ATTR_CASE => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    /**
     * 服务器断线标识字符
     * @var array
     */
    protected $breakMatchStr = [
        'server has gone away',
        'no connection to the server',
        'Lost connection',
        'is dead or not enabled',
        'Error while sending',
        'decryption failed or bad record mac',
        'server closed the connection unexpectedly',
        'SSL connection has been closed unexpectedly',
        'Error writing data to the connection',
        'Resource deadlock avoided',
        'failed with errno',
    ];

    /**
     * 绑定参数
     * @var array
     */
    protected $binds = [];
    protected $types = [];

    /**
     * @return string
     */
    public function getSeparator(): string
    {
        return $this->separator;
    }

    /**
     * @return mixed
     */
    public function getConfig()
    {
        return $this->config;
    }


    protected function __construct( $config, Debug $debug, $app_debug = false )
    {
        $this->config = $config;
        $this->dbConnectionConfig = array_merge( $this->dbConnectionConfig, $this->config );

        $this->debug = $debug;
        //这里先不注入redis缓存了，防止不需要的时候浪费性能。需要的时候再外部注入缓存方法
        //$this->cache = $cache->getInstance();

        $this->debug_status = $app_debug;
    }


    /**
     * 解析pdo连接的dsn信息
     * @access protected
     * @param array $config 连接信息
     * @return string
     */
    abstract protected function parseDsn( array $config );


    /**
     * 设置debug打开关闭
     * @param type $open
     */
    public function setDebug( $open = false )
    {
        $this->debug_status = $open;
    }

    /**
     * 获取执行SQL语句的个数
     *
     * @access public
     * @return integer
     */
    public function getQueryNum()
    {
        return $this->queryNum;
    }

    /**
     * 取得前一次 MySQL 操作所影响的记录行数 Its an DELETE, INSERT, REPLACE, or UPDATE query
     * @return type
     */
    public function getNumRows()
    {
        return $this->numRows;
    }

    /**
     * 如果在大数据量或者特殊的情况下写入数据后可能会存在同步延迟的情况，可以调用setMaster(true)方法进行主库查询操作。
     * @param bool $master
     */
    public function setMaster( bool $master )
    {
        $this->readMaster = $master;
    }

    /**
     * 获取缓存实例
     *
     * @access protected
     * @final
     */
    protected final function getCache()
    {
        $this->is_cache = true;
    }


    /**
     * 初始化数据库连接
     * @param bool $master 是否主服务器
     */
    protected function initConnect( bool $master = true ): void
    {
        $config = $this->dbConnectionConfig;

        if ( !empty( $config[ 'deploy' ] ) ) {
            if ( $master || $this->transLevel ) {
                //主动选择主库 或 者使用事务时使用选择主库
                if ( !$this->linkWrite ) {
                    $this->linkWrite = $this->multiConnect( true );
                }
                $this->linkID = $this->linkWrite;
            } else {
                if ( !$this->linkRead ) {
                    $this->linkRead = $this->multiConnect( false );
                }
                $this->linkID = $this->linkRead;
            }
        } elseif ( !$this->linkID ) {
            //连接数据库
            $this->linkID = $this->connect();
        }
    }

    /**
     * 连接数据库方法
     * @access public
     * @param array $config 连接参数
     * @param integer $link_num 连接序号
     * @param array|bool $auto_connection 是否自动连接主数据库（用于分布式）
     * @return PDO
     * @throws PDOException
     */
    protected function connect( array $config = [], $link_num = 0, $auto_connection = false ): PDO
    {
        if ( isset( $this->links[ $link_num ] ) ) {
            return $this->links[ $link_num ];
        }
        if ( empty( $config ) ) {
            $config = $this->dbConnectionConfig;
        }
        // 连接参数
        if ( isset( $config[ 'params' ] ) && is_array( $config[ 'params' ] ) ) {
            $params = $config[ 'params' ] + $this->params;
        } else {
            $params = $this->params;
        }


        if ( !empty( $config[ 'break_match_str' ] ) ) {
            $this->breakMatchStr = array_merge( $this->breakMatchStr, (array) $config[ 'break_match_str' ] );
        }

        try {
            if ( empty( $config[ 'dsn' ] ) ) {
                $config[ 'dsn' ] = $this->parseDsn( $config );
            }

            //todo 以后可以 用来 监控执行时间
            // $startTime = microtime( true );

            $this->links[ $link_num ] = $this->createPdo( $config[ 'dsn' ], $config[ 'username' ], $config[ 'password' ], $params );


            return $this->links[ $link_num ];
        } catch ( PDOException $e ) {
            if ( $auto_connection ) {
                //$this->db->log( $e->getMessage(), 'error' );
                return $this->connect( $auto_connection, $link_num );
            } else {
                throw $e;
            }
        }
    }

    /**
     * 创建PDO实例
     * @param $dsn
     * @param $username
     * @param $password
     * @param $params
     * @return PDO
     */
    protected function createPdo( $dsn, $username, $password, $params )
    {
        return new PDO( $dsn, $username, $password, $params );
    }

    /**
     * 连接分布式服务器
     * @param bool $master 主服务器
     * @return PDO
     */
    protected function multiConnect( bool $master = false ): PDO
    {
        $config = $this->dbConnectionConfig;

        $config = [];

        // 分布式数据库配置解析
        foreach ( [ 'username', 'password', 'hostname', 'port', 'database', 'dsn', 'charset' ] as $name ) {
            $config[ $name ] = is_string( $config[ $name ] ) ? explode( ',', $config[ $name ] ) : $config[ $name ];
        }

        // 主服务器序号
        if ( empty( $config[ 'master_num' ] ) || $config[ 'master_num' ] == 1 ) {
            $m = 0;
        } elseif ( $config[ 'master_num' ] > 1 ) {
            $m = floor( mt_rand( 0, $config[ 'master_num' ] - 1 ) );
        }

        if ( $config[ 'rw_separate' ] ) {// 主从式采用读写分离
            if ( $master ) { // 主服务器写入
                $r = $m;
            } elseif ( is_numeric( $config[ 'slave_no' ] ) ) {
                // 指定服务器读
                $r = $config[ 'slave_no' ];
            } else {
                // 读操作连接从服务器 每次随机连接的数据库
                $r = floor( mt_rand( $config[ 'master_num' ], count( $config[ 'hostname' ] ) - 1 ) );
            }
        } else {
            // 读写操作不区分服务器 每次随机连接的数据库
            $read_host_count = count( $config[ 'hostname' ] );
            if ( $read_host_count > 1 ) {
                $r = floor( mt_rand( 0, count( $config[ 'hostname' ] ) - 1 ) );
            } else {
                $r = 0;
            }
        }

        $dbMaster = false;

        if ( $m != $r ) {
            $dbMaster = [];
            foreach ( [ 'username', 'password', 'hostname', 'port', 'database', 'dsn', 'charset' ] as $name ) {
                $dbMaster[ $name ] = $config[ $name ][ $m ] ?? $config[ $name ][ 0 ];
            }
        }

        $dbConfig = [];

        foreach ( [ 'username', 'password', 'hostname', 'hostport', 'database', 'dsn', 'charset' ] as $name ) {
            $dbConfig[ $name ] = $config[ $name ][ $r ] ?? $config[ $name ][ 0 ];
        }

        return $this->connect( $dbConfig, $r, $r == $m ? false : $dbMaster );
    }


    /**
     * 执行一条SQL查询语句 返回资源标识符  返回数据集
     * @param $sql
     * @param array $binds
     * @param bool $master
     * @return PDOStatement
     * @throws BindParamException
     * @throws \Throwable
     */
    public function query( $sql, array $binds = [], bool $master = false ): PDOStatement
    {
        $sth = $this->getPDOStatement( $sql, $binds, $master );

        //$resultSet = $this->getResult( $procedure = false );

        return $sth;
    }


    /**
     * 执行一条SQL语句 返回似乎执行成功 update insert delete
     * @access public
     * @param mixed $sql sql指令
     * @param array $bind 参数绑定
     * @return array
     * @throws BindParamException
     * @throws \PDOException
     * @throws \Exception
     * @throws \Throwable
     */
    public function execute( $sql, array $binds = [] ): int
    {
        $this->getPDOStatement( $sql, $binds, $master = true );

        if ( !empty( $this->config[ 'deploy' ] ) && !empty( $this->config[ 'read_master' ] ) ) {
            $this->readMaster = true;
        }
        $this->numRows = $this->PDOStatement->rowCount();
        return $this->numRows;
    }

    /**
     * 执行查询但只返回PDOStatement对象
     * @access public
     * @param string $sql sql指令
     * @param array $bind 参数绑定
     * @param bool $master 是否在主服务器读操作
     * @return PDOStatement
     * @throws BindParamException
     * @throws \PDOException
     * @throws \Exception
     * @throws \Throwable
     */
    protected function getPDOStatement( string $sql, array $params = [], bool $master = false, bool $procedure = false ): PDOStatement
    {
        $this->initConnect( $this->readMaster ? : $master );

        // 记录SQL语句
        $this->queryStr = $sql;

        $this->binds = $params;
        //$this->types = $types;
        try {
            // $this->queryStartTime = microtime( true );

            // 预处理
            $this->PDOStatement = $this->linkID->prepare( $sql );

            // 参数绑定
            if ( $procedure ) {
                $this->bindParam( $params );
            } else {
                $this->bindValue( $params );
            }

            // 执行查询
            $this->PDOStatement->execute();


            $this->reConnectTimes = 0;
            //记录sql日志
            $this->debug();
            return $this->PDOStatement;
        } catch ( \Throwable | \Exception $e ) {
            if ( $this->reConnectTimes < 4 && $this->isBreak( $e ) ) {
                ++$this->reConnectTimes;
                return $this->close()->getPDOStatement( $sql, $params, $master, $procedure );
            }

            if ( $e instanceof \PDOException ) {
                throw new PDOException( $e->getMessage() . ':' . $this->getLastsql() );
            } else {
                throw $e;
            }
        }
    }

    /**
     * 获取最近一次查询的sql语句
     * @access public
     * @return string
     */
    public function getLastSql(): string
    {
        return $this->getRealSql( $this->queryStr, $this->binds );
    }

    /**
     * 根据参数绑定组装最终的SQL语句 便于调试
     * @access public
     * @param string $sql 带参数绑定的sql语句
     * @param array $bind 参数绑定列表
     * @return string
     */
    public function getRealSql( string $sql, array $binds = [] ): string
    {
        foreach ( $binds as $key => $val ) {
            $value = $val;
            $type = $this->getType( $val );

            if ( ( self::PARAM_FLOAT == $type || PDO::PARAM_STR == $type ) && is_string( $value ) ) {
                $value = '\'' . addslashes( $value ) . '\'';
            } elseif ( PDO::PARAM_INT == $type && '' === $value ) {
                $value = 0;
            }

            // 判断占位符
            $sql = is_numeric( $key ) ?
                substr_replace( $sql, $value, strpos( $sql, '?' ), 1 ) :
                substr_replace( $sql, $value, strpos( $sql, ':' . $key ), strlen( ':' . $key ) );
        }

        return rtrim( $sql );
    }


    /**
     * 返回param绑定的value的的类型
     * @param $value
     * @param array $types
     * @return int|mixed
     */
    public function getType( $value )
    {
        switch ( true ) {
            case is_string( $value ):
                $type = PDO::PARAM_STR;
                break;
            case is_int( $value ):
                $type = PDO::PARAM_INT;
                break;
            case is_bool( $value ):
                $type = PDO::PARAM_BOOL;
                break;
            case is_null( $value ):
                $type = PDO::PARAM_NULL;
                break;
            case is_resource( $value ):
                $type = PDO::PARAM_LOB;
                break;
            default:
                $type = PDO::PARAM_STR;
                break;
        }
        return $type;
    }


    /**
     * 参数绑定
     * 支持 ['name'=>'value','id'=>123] 对应命名占位符
     * 或者 ['value',123] 对应问号占位符
     * @access public
     * @param array $bind 要绑定的参数列表
     * @return void
     * @throws BindParamException
     */
    protected function bindValue( array $binds = [] ): void
    {
        foreach ( $binds as $key => $value ) {
            // 占位符
            $param = is_numeric( $key ) ? $key + 1 : ':' . $key;

            if ( is_array( $value ) ) {
                /*
                    支持数组模式来设置值和类型
                    [             [
                        'id' => [2,\PDO::PARAM_BOOL]
                    ]
                 */
                if ( PDO::PARAM_INT == $value[ 1 ] && '' === $value[ 0 ] ) {
                    $value[ 0 ] = 0;
                } elseif ( self::PARAM_FLOAT == $value[ 1 ] ) {
                    $value[ 0 ] = is_string( $value[ 0 ] ) ? (float) $value[ 0 ] : $value[ 0 ];
                    $value[ 1 ] = PDO::PARAM_STR;
                }
                $variable = $value[ 0 ];
                $data_type = $value[ 1 ];
            } else {
                $variable = $value;
            }
            $type = $data_type ?? $this->getType( $variable );
            $result = $this->PDOStatement->bindValue( $param, $variable, $type );

            if ( !$result ) {
                throw new BindParamException(
                    "Error occurred  when binding parameters '{$param}'",
                    $this->config,
                    $this->getLastsql(),
                    $binds
                );
            }
        }
    }

    /**
     * 存储过程的输入输出参数绑定
     * @access public
     * @param array $bind 要绑定的参数列表
     * @return void
     * @throws BindParamException
     */
    protected function bindParam( array $binds ): void
    {
        foreach ( $binds as $key => $value ) {
            // 占位符
            $param = is_numeric( $key ) ? $key + 1 : ':' . $key;

            if ( is_array( $value ) ) {
                $variable = $value[ 0 ];
                $data_type = $value[ 1 ];
            } else {
                $variable = $value;
            }
            $type = $data_type ?? $this->getType( $variable );
            $result = $this->PDOStatement->bindParam( $param, $variable, $type );


            if ( !$result ) {
                throw new BindParamException(
                    "Error occurred  when binding parameters '{$param}'",
                    $this->config,
                    $this->getLastsql(),
                    $binds
                );
            }
        }
    }

    /**
     * 获得存储过程数据集
     * @access protected
     * @return array
     */
    protected function procedure(): array
    {
        $item = [];

        do {
            $result = $this->getResult();
            if ( !empty( $result ) ) {
                $item[] = $result;
            }
        } while ( $this->PDOStatement->nextRowset() );

        $this->numRows = count( $item );

        return $item;
    }

    /**
     * 执行数据库事务
     * @access public
     * @param callable $callback 数据操作方法回调
     * @return mixed
     * @throws PDOException
     * @throws \Exception
     * @throws \Throwable
     */
    public function transaction( callable $callback )
    {
        $this->startTrans();

        try {
            $result = null;
            if ( is_callable( $callback ) ) {
                $result = $callback( $this );
                //$result = call_user_func( $callback, $this );
            }

            $this->commit();
            return $result;
        } catch ( \Exception | \Throwable $e ) {
            $this->rollback();
            throw $e;
        }
    }

    //todo function transactionXa

    /**
     * 开始事务
     *
     * @return bool
     */
    public function startTrans(): void
    {
        $this->initConnect( true );

        ++$this->transLevel;
        try {
            if ( $this->transLevel == 1 ) { //保证最外层的transtion才start
                $this->linkID->beginTransaction();
                $this->debug( 'beginTransaction()' );
            } else if ( $this->transLevel > 1 && $this->supportSavepoint() ) {
                $parseSavepoint = $this->parseSavepoint( 'trans' . $this->transLevel );
                $this->linkID->exec( $parseSavepoint );
                $this->debug( $parseSavepoint );
            }
        } catch ( \Exception $e ) {
            if ( $this->reConnectTimes < 4 && $this->isBreak( $e ) ) {
                --$this->transTimes;
                ++$this->reConnectTimes;
                $this->close()->startTrans();
            }
            throw $e;
        }
    }

    /**
     * 提交事务
     *
     * @return bool
     */
    public function commit(): void
    {
        $this->initConnect( true );

        if ( $this->transLevel == 1 ) { //如果当前事务只有一层时 才能提交
            $this->linkID->commit();
            $this->debug( 'commit()' );
        }
        --$this->transLevel;
    }

    /**
     * 回滚事务
     *
     * @return bool
     */
    public function rollback(): void
    {
        $this->initConnect( true );

        if ( $this->transLevel == 1 ) { //如果当前事务只有一层时 才能回滚
            $this->linkID->rollBack();
            $this->debug( 'rollBack()' );
        } else if ( $this->transLevel > 1 && $this->supportSavepoint() ) {
            $parseSavepointRollBack = $this->parseSavepointRollBack( 'trans' . $this->transLevel );
            $this->linkID->exec( $parseSavepointRollBack );
            $this->debug( $parseSavepointRollBack );
        }
        --$this->transLevel;
    }

    /**
     * 是否支持事务嵌套
     * @return bool
     */
    protected function supportSavepoint(): bool
    {
        return false;
    }

    /**
     * 生成定义保存点的SQL
     * @access protected
     * @param string $name 标识
     * @return string
     */
    protected function parseSavepoint( string $name ): string
    {
        return 'SAVEPOINT ' . $name;
    }

    /**
     * 生成回滚到保存点的SQL
     * @access protected
     * @param string $name 标识
     * @return string
     */
    protected function parseSavepointRollBack( string $name ): string
    {
        return 'ROLLBACK TO SAVEPOINT ' . $name;
    }


    /**
     * 关闭数据库（或者重新连接）
     * @access public
     * @return $this
     */
    public function close()
    {
        $this->linkID = null;
        $this->linkWrite = null;
        $this->linkRead = null;
        $this->links = [];

        $this->free();
        $this->debug( 'close()' );
        return $this;
    }

    /**
     * 是否断线
     * @access protected
     * @param \PDOException|\Exception $e 异常对象
     * @return bool
     */
    protected function isBreak( $e ): bool
    {
        if ( !$this->config[ 'break_reconnect' ] ) {
            return false;
        }

        $error = $e->getMessage();

        foreach ( $this->breakMatchStr as $msg ) {
            if ( false !== stripos( $error, $msg ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取最近插入的ID
     * @access public
     * @param string $sequence 自增序列名
     * @return mixed
     */
    public function getLastInsID( string $sequence = null )
    {
        try {
            $insertId = $this->linkID->lastInsertId( $sequence );
        } catch ( \Exception $e ) {
            $insertId = '';
        }

        return $insertId;
    }

    /**
     * 释放查询结果
     * @access public
     */
    public function free(): void
    {
        $this->PDOStatement = null;
    }

    /**
     * 获取PDO对象
     * @access public
     * @return \PDO|false
     */
    public function getPdo()
    {
        if ( !$this->linkID ) {
            return false;
        }

        return $this->linkID;
    }

    /**
     * Adds identifier condition to the query components
     *
     * @param mixed[] $identifier Map of key columns to their values
     * @param string[] $columns Column names
     * @param mixed[] $values Column values
     * @param string[] $conditions Key conditions
     *
     * @throws Exception
     */
    private function addIdentifierCondition(
        array $identifier,
        array &$conditions,
        array &$binds = []
    ): void
    {
        foreach ( $identifier as $columnName => $value ) {
            if ( $value === null ) {
                continue;
            }
            $conditions[] = $columnName . ' = ' . $this->parseDataBind( $columnName, $value );
            $binds[ $columnName ] = $value;
        }
    }

    /**
     * Inserts a table row with specified data.
     *
     * Table expression and columns are not escaped and are not safe for user-input.
     *
     * @param string $table The expression of the table to insert data into, quoted or unquoted.
     * @param mixed[] $data An associative array containing column-value pairs.
     * @param int[]|string[] $types Types of the inserted data.
     *
     * @return int The number of affected rows.
     *
     * @throws Exception
     */
    public function insert( $table, array $data )
    {
        if ( empty( $data ) ) {
            return 0;
        }
        $columns = [];
        $set = [];

        foreach ( $data as $columnName => $value ) {
            $columns[] = $columnName;
            $set[] = $this->parseDataBind( $columnName, $value );
        }

        $sql = 'INSERT INTO ' . $table . ' (' . implode( ', ', $columns ) . ')' .
            ' VALUES (' . implode( ', ', $set ) . ')';
        $result = '' == $sql ? 0 : $this->execute( $sql, $data );
        if ( $result ) {
            return $this->getLastInsID();
        }
        return false;
    }

    /**
     * @param $table
     * @param array $data
     * @param array $identifier
     * @return array|int
     * @throws BindParamException
     * @throws \Throwable
     */
    public function update( $table, array $data, array $identifier )
    {
        if ( empty( $data ) ) {
            return 0;
        }
        $binds = [];
        $set = [];
        $conditions = [];

        foreach ( $data as $columnName => $value ) {
            $set[] = $columnName . '=' . $this->parseDataBind( $columnName, $value );
            $binds[ $columnName ] = $value;
        }
        $this->addIdentifierCondition( $identifier, $conditions, $binds );

        $sql = 'UPDATE ' . $table . ' SET ' . implode( ', ', $set )
            . ' WHERE ' . implode( ' AND ', $conditions );

        return $this->execute( $sql, $binds );
    }

    /**
     * @param $table
     * @param array $data
     * @param array $identifier
     * @return array|int
     * @throws BindParamException
     * @throws \Throwable
     */
    public function delete( $table, array $identifier )
    {
        if ( empty( $identifier ) ) {
            return 0;
        }
        $conditions = [];
        $binds = [];

        $this->addIdentifierCondition( $identifier, $conditions, $binds );

        $sql = 'DELETE FROM ' . $table . ' WHERE ' . implode( ' AND ', $conditions );
        return $this->execute( $sql, $binds );
    }

    /**
     * Prepares and executes an SQL query and returns the value of a single column
     * of the first row of the result.
     * @param $query_sql
     * @param array $params
     * @param array $types
     */
    public function fetch( $query_sql, array $params = [], bool $master = false )
    {
        $this->getPDOStatement( $query_sql, $params, $master );
        $result = $this->PDOStatement->fetch( $this->fetchType );
        if ( $result ) {
            $this->numRows = 1;
        }
        return $result;
    }

    /**
     * Prepares and executes an SQL query and returns the value of a single column
     * of the first row of the result.
     * @param $query_sql
     * @param mixed[] $params The prepared statement params.
     * @param int $column The 0-indexed column number to retrieve.
     */
    public function fetchColumn( $query_sql, array $params = [], $column = 0, bool $master = false )
    {
        $this->getPDOStatement( $query_sql, $params, $master );
        $result = $this->PDOStatement->fetchColumn( $column );
        $this->numRows = 0;
        if ( $result ) {
            $this->numRows = 1;
        }
        return $result;
    }

    /**
     * Prepares and executes an SQL query and returns the first row of the result
     * as an associative array.
     * @param $query_sql
     * @param array $params
     * @param array $types
     */
    public function fetchAssoc( $query_sql, array $params = [], bool $master = false )
    {
        $this->getPDOStatement( $query_sql, $params, $master );
        $result = $this->PDOStatement->fetch( PDO::FETCH_ASSOC );
        $this->numRows = 0;
        if ( $result ) {
            $this->numRows = 1;
        }
        return $result;
    }


    /**
     * Prepares and executes an SQL query and returns the first row of the result
     * as an associative object.
     * @param $query_sql
     * @param array $params
     * @param array $types
     */
    public function fetchAssocObject( $query_sql, array $params = [], bool $master = false, $class_name = '' )
    {
        $this->getPDOStatement( $query_sql, $params, $master );
        if ( empty( $class_name ) ) {
            $result = $this->PDOStatement->fetch( PDO::FETCH_OBJ );
        } else {
            $this->PDOStatement->setFetchMode( PDO::FETCH_CLASS, $class_name );
            $result = $this->PDOStatement->fetch();
        }


        /*
        if ( !empty( $class_name ) ) {
            $result = $this->PDOStatement->fetchObject( $class_name );
        } else {
            $result = $this->PDOStatement->fetchObject();
        }
        */
        $this->numRows = 0;
        if ( $result ) {
            $this->numRows = 1;
        }
        return $result;
    }


    /**
     * Prepares and executes an SQL query and returns the result as an associative array.
     * @param $query_sql
     * @param array $params
     * @param array $types
     */
    public function fetchAll( $query_sql, array $params = [], bool $master = false )
    {
        $this->getPDOStatement( $query_sql, $params, $master );
        $result = $this->PDOStatement->fetchAll( PDO::FETCH_ASSOC );
        $this->numRows = count( $result );
        return $result;
    }

    /**
     * Prepares and executes an SQL query and returns the result as an associative array.
     * @param $query_sql
     * @param array $params
     * @param array $types
     */
    public function fetchAllObject( $query_sql, array $params = [], bool $master = false, $class_name = '' )
    {
        $this->getPDOStatement( $query_sql, $params, $master );
        if ( empty( $class_name ) ) {
            $result = $this->PDOStatement->fetchAll( PDO::FETCH_OBJ );
        } else {
            $result = $this->PDOStatement->fetchAll( PDO::FETCH_CLASS, $class_name );
        }
        $this->numRows = count( $result );
        return $result;
    }

    /**
     * 数据绑定处理
     * @access protected
     * @param Query $query 查询对象
     * @param string $key 字段名
     * @param mixed $data 数据
     * @param array $bind 绑定数据
     * @return string
     */
    private function parseDataBind( string $name, $value ): string
    {
        if ( $value instanceof Raw ) {
            return $value->getValue();
        } else {
            return ':' . $name;
        }
    }

    /**
     * DEBUG信息
     *
     * @param string $sql
     * @param bool $success
     * @param error string
     */
    public function debug( $sql = '', $success = true, $error = null )
    {
        if ( $this->debug_status ) {
            $debug = $this->debug;
            $sql = empty( $sql ) ? $this->getLastSql() : $sql;
            $debug->setSQL( $sql, $success, $error );
        }
    }
}
