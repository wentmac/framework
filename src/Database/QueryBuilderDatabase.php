<?php
/**
 *
 * ============================================================================
 * Author: zhangwentao <wentmac@vip.qq.com>
 * Date: 2020/6/22 0:08
 * Created by TmacPHP Framework <https://github.com/wentmac/TmacPHP>
 * http://www.t-mac.org；
 */

namespace Tmac\Database;

use Tmac\Database\Concern\AggregateQuery;
use Tmac\Database\Concern\Builder;
use Tmac\Database\Concern\Orm;
use Tmac\Database\Concern\ParamsBind;
use Closure;
use Tmac\Database\Concern\Where;

class QueryBuilderDatabase
{
    use Where;
    use Orm;
    use Builder;
    use AggregateQuery;
    use ParamsBind;

    protected $driverDatabase;

    /**
     * @var PDOConnection
     */
    protected $conn;

    protected $pk;
    private $primaryKey; //主键字段名
    protected $table;
    protected $schema;//实体类的数据表的schema
    protected $aliasMap = []; //join查询时 别名库的字段schema存储

    /**
     * @var string the separator between different fragments of a SQL statement.
     * Defaults to an empty space. This is mainly used by [[build()]] when generating a SQL statement.
     */
    protected $separator = ' ';

    /**
     * 当前查询参数
     * @var array
     */
    protected $options = [];


    /**
     * 当前查询参数
     * @var array
     */
    protected $conditionBuilders = [];
    /**
     * All of the available clause operators.
     *
     * @var array
     */
    public $operators = [
        '=', '<', '>', '<=', '>=', '<>', '!=', '<=>',
        'LIKE', 'LIKE BINARY', 'NOT LIKE', 'ILIKE', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN',
        '&', '|', '^', '<<', '>>',
        'RLIKE', 'NOT RLIKE', 'REGEXP', 'NOT REGEXP',
        '~', '~*', '!~', '!~*', 'SIMILAR TO',
        'NOT SIMILAR TO', 'NOT ILIKE', '~~*', '!~~*',
    ];

    /**
     * 子查询状态
     * @var bool
     */
    protected $subQuery = false;

    /**
     * 初始化
     */
    public function __construct( DriverDatabase $connection, $table, $schema, $primaryKey )
    {
        $this->driverDatabase = $connection;
        $this->conn = $connection->getInstance();
        $this->table = $table;
        $this->primaryKey = $primaryKey;
        $this->separator = $this->conn->getSeparator();
    }

    /**
     * 创建一个新的查询对象
     * @access public
     * @return BaseQuery
     */
    public function newQuery(): QueryBuilderDatabase
    {
        return new static( $this->driverDatabase, $this->table, $this->schema, $this->primaryKey );
    }

    /**
     * @return DriverDatabase
     */
    public function getDriverDatabase(): DriverDatabase
    {
        return $this->driverDatabase;
    }

    /**
     * 子查询其他表的Repo类，用来取得其他表的表名，实体实，schema
     * 生成时 闭包的 Repo对象
     * @param $repository
     */
    public function setRepository( $repository ): self
    {
        $this->driverDatabase = $repository->getDriverDatabase();
        $this->table = $repository->getTable();
        $this->schema = $repository->getSchema();
        $this->primaryKey = $repository->getPrimaryKey();
        return $this;
    }

    /**
     * @return mixed
     */
    public function getSchema()
    {
        return $this->schema;
    }

    /**
     * @return PDOConnection
     */
    public function getConn()
    {
        return $this->conn;
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }

    protected function setPrimaryKey( $primaryKey )
    {
        $this->primaryKey = $primaryKey;
        return $this;
    }

    /**
     * 获取当前的查询参数
     * @access public
     * @param string $name 参数名
     * @return mixed
     */
    public function getOptions( string $name = '' )
    {
        if ( '' === $name ) {
            return $this->options;
        }

        return $this->options[ $name ] ?? null;
    }

    /**
     * 分析表达式（可用于查询或者写入操作）
     * @access public
     * @return array
     */
    public function parseOptions(): array
    {
        $options = $this->getOptions();

        // 获取数据表
        if ( empty( $options[ 'table' ] ) ) {
            $table = $this->getTable();
            if ( !empty( $this->options[ 'alias' ][ $table ] ) ) {
                $alias = $this->options[ 'alias' ][ $table ];
                $options[ 'table' ] = $table . ' ' . $alias;
            } else {
                $options[ 'table' ] = $table;
            }
        }

        if ( !isset( $options[ 'where' ] ) ) {
            $options[ 'where' ] = [];
        }

        if ( !isset( $options[ 'field' ] ) ) {
            $options[ 'field' ] = '*';
        }

        foreach ( [ 'data', 'order', 'join', 'union' ] as $name ) {
            if ( !isset( $options[ $name ] ) ) {
                $options[ $name ] = [];
            }
        }

        foreach ( [ 'master', 'lock', 'fetch_sql', 'debug_sql', 'distinct', 'procedure' ] as $name ) {
            if ( !isset( $options[ $name ] ) ) {
                $options[ $name ] = false;
            }
        }

        foreach ( [ 'group', 'having', 'limit', 'force', 'comment', 'partition', 'duplicate', 'extra' ] as $name ) {
            if ( !isset( $options[ $name ] ) ) {
                $options[ $name ] = '';
            }
        }

        $this->options = $options;

        return $options;
    }

    /**
     * 指定数据表别名
     * @access public
     * @param array|string $alias 数据表别名
     * @return $this
     */
    public function alias( $alias )
    {
        if ( is_array( $alias ) ) {
            $this->options[ 'alias' ] = $alias;
        } else {
            $table = $this->getTable();

            $this->options[ 'alias' ][ $table ] = $alias;
        }

        return $this;
    }


    /**
     * join子句查询
     * 支持  left|right|outer|inner|left outer|right outer
     * 查询SQL组装 join
     *
     * @param type $joinTable 表名
     * @param type $on join时候的on语句
     * @param type $joinType 联表的类型
     * @return $this|QueryBuilderDatabase
     */
    public function join( $repository, string $condition = null, string $type = 'INNER', array $bind = [] )
    {
        $table = $this->getJoinTable( $repository );

        if ( !empty( $bind ) && $condition ) {
            $this->bindParams( $condition, $bind );
        }

        $repo_alias_array = $repository->getOptions( 'alias' );
        $repo_alias = $repo_alias_array[ $repository->getTable() ];
        $this->aliasMap[ $repo_alias ] = $repository->getSchema();

        $this_alias_array = $this->getOptions( 'alias' );
        $this_alias = $this_alias_array[ $this->getTable() ];
        $this->aliasMap[ $this_alias ] = $this->getSchema();

        $this->options[ 'join' ][] = [ $table[ 'table' ] . ' ' . $table[ 'alias' ], strtoupper( $type ), $condition ];

        return $this;
    }

    /**
     * LEFT JOIN
     * @access public
     * @param mixed $join 关联的表名
     * @param mixed $condition 条件
     * @param array $bind 参数绑定
     * @return $this
     */
    public function leftJoin( $join, string $condition = null, array $bind = [] )
    {
        return $this->join( $join, $condition, 'LEFT', $bind );
    }

    /**
     * RIGHT JOIN
     * @access public
     * @param mixed $join 关联的表名
     * @param mixed $condition 条件
     * @param array $bind 参数绑定
     * @return $this
     */
    public function rightJoin( $join, string $condition = null, array $bind = [] )
    {
        return $this->join( $join, $condition, 'RIGHT', $bind );
    }

    /**
     * FULL JOIN
     * @access public
     * @param mixed $join 关联的表名
     * @param mixed $condition 条件
     * @param array $bind 参数绑定
     * @return $this
     */
    public function fullJoin( $join, string $condition = null, array $bind = [] )
    {
        return $this->join( $join, $condition, 'FULL' );
    }

    /**
     * 获取Join表名及别名 支持
     * ['prefix_table或者子查询'=>'alias'] 'table alias'
     * @access protected
     * @param array|string|Raw $join JION表名
     * @param string $alias 别名
     * @return string|array
     */
    protected function getJoinTable( $repository )
    {
        $table = $repository->getTable();
        $alias = $repository->getOptions( 'alias' );
        return [
            'table' => $table,
            'alias' => $alias[ $table ]
        ];
    }


    /**
     * @param $table
     * @return $this
     */
    public function from( $table )
    {
        if ( is_array( $table ) ) {
            $table = implode( ',', $table );
        }
        $this->options[ 'table' ] = $table;

        return $this;
    }

    /**
     * @param string $direction
     * @return string
     */
    private function checkOrderByDirction( $direction = 'desc' )
    {
        $direction = strtoupper( $direction );
        if ( !in_array( $direction, [ 'ASC', 'DESC' ] ) ) {
            $direction = 'DESC';
        }
        return $direction;
    }

    /**
     * Add a descending "order by" clause to the query.
     *
     * @param string $column
     * @return $this
     */
    public function orderByDesc( $column )
    {
        return $this->orderBy( $column, 'desc' );
    }


    /**
     * 排序
     * @param $columns
     * @param string $direction
     * @return $this
     */
    public function orderBy( $columns, $direction = 'asc' )
    {
        $direction = $this->checkOrderByDirction( $direction );
        $this->options[ 'order' ] = '';
        if ( empty( $columns ) ) {
            return $this;
        }
        if ( is_string( $columns ) ) {
            //取了别名后的
            $key = $this->parseKey( $columns );
            $this->options[ 'order' ] = "{$key} {$direction}";
        } elseif ( is_array( $columns ) ) {
            $orderArray = [];
            foreach ( $columns as $column => $order ) {
                $order = $this->checkOrderByDirction( $order );
                //取了别名后的
                $key = $this->parseKey( $column );
                $orderArray[] = "{$key} {$order}";
            }
            $this->options[ 'order' ] = implode( ',', $orderArray );
        }
        return $this;
    }


    /**
     * Add a raw "order by" clause to the query.
     *
     * @param string $sql
     * @return $this
     */
    public function orderByRaw( $sql )
    {
        $this->options[ 'order' ] = $sql;
        return $this;
    }


    /**
     * Set the columns to be selected.
     *
     * @param array|mixed $columns
     * @return $this
     */
    public function select( $columns = [ '*' ] )
    {
        $this->columns = [];
        $columns = is_array( $columns ) ? $columns : func_get_args();
        $this->options[ 'field' ] = implode( ',', $columns );
        return $this;
    }

    /**
     * 指定查询数量
     * @access public
     * @param int $offset 起始位置
     * @param int $length 查询数量
     * @return $this
     */
    public function limit( int $offset, int $length = null )
    {
        $this->options[ 'limit' ] = $offset . ( $length ? ',' . $length : '' );
        $this->options[ 'offset' ] = $offset;
        $this->options[ 'length' ] = $length;
        return $this;
    }

    /**
     * 组装SQL语句的LIMIT语句
     *
     * 注:本方法与$this-&gt;limit()功能相类，区别在于:本方法便于分页,参数不同
     *
     * @access public
     *
     * @param integer $page 当前的页数
     * @param integer $listNum 每页显示的数据行数
     *
     * @return $this
     */
    public function page( int $page, int $listNum = 10 )
    {

        //参数分析
        $page = (int) $page;
        $listNum = (int) $listNum;

        $page = ( $page < 1 ) ? 1 : $page;

        $startId = (int) $listNum * ( $page - 1 );

        return $this->limit( $startId, $listNum );
    }

    /**
     * 指定group查询
     * @access public
     * @param string|array $group GROUP
     * @return $this
     */
    public function group( $group )
    {
        $group = is_array( $group ) ? $group : func_get_args();
        $group_array = [];
        foreach ( $group as $column ) {
            $group_array[] = $this->parseKey( $column );
        }
        $this->options[ 'group' ] = implode( ',', $group_array );
        return $this;
    }

    /**
     * 指定having查询
     * @access public
     * @param string $having having
     * @return $this
     */
    public function having( string $having )
    {
        $this->options[ 'having' ] = $having;
        return $this;
    }

    /**
     * 指定distinct查询
     * @access public
     * @param bool $distinct 是否唯一
     * @return $this
     */
    public function distinct( bool $distinct = true )
    {
        $this->options[ 'distinct' ] = $distinct;
        return $this;
    }

    /**
     * 查询SQL组装 union
     * @access public
     * @param mixed $union UNION
     * @param boolean $all 是否适用UNION ALL
     * @return $this
     */
    public function union( $union, bool $all = false )
    {
        $this->options[ 'union' ][ 'type' ] = $all ? 'UNION ALL' : 'UNION';

        if ( is_array( $union ) ) {
            $this->options[ 'union' ] = array_merge( $this->options[ 'union' ], $union );
        } else {
            $this->options[ 'union' ][] = $union;
        }

        return $this;
    }

    /**
     * 查询SQL组装 union all
     * @access public
     * @param mixed $union UNION数据
     * @return $this
     */
    public function unionAll( $union )
    {
        return $this->union( $union, true );
    }

    /**
     * 指定强制索引
     * @access public
     * @param string $force 索引名称
     * @return $this
     */
    public function force( string $force )
    {
        $this->options[ 'force' ] = $force;
        return $this;
    }

    /**
     * 指定查询lock
     * @access public
     * @param bool|string $lock 是否lock
     * @return $this
     */
    public function lock( $lock = false )
    {
        $this->options[ 'lock' ] = $lock;

        if ( $lock ) {
            $this->getConn()->setMaster( true );
        }

        return $this;
    }


    /**
     * USING支持 用于多表删除
     * @access public
     * @param mixed $using USING
     * @return $this
     */
    public function using( $using )
    {
        $this->options[ 'using' ] = $using;
        return $this;
    }

    /**
     * 存储过程调用
     * @access public
     * @param bool $procedure 是否为存储过程查询
     * @return $this
     */
    public function procedure( bool $procedure = true )
    {
        $this->options[ 'procedure' ] = $procedure;
        return $this;
    }

    /**
     * 设置是否REPLACE
     * @access public
     * @param bool $replace 是否使用REPLACE写入数据
     * @return $this
     */
    public function replace( bool $replace = true )
    {
        $this->options[ 'replace' ] = $replace;
        return $this;
    }

    /**
     * 设置当前查询所在的分区
     * @access public
     * @param string|array $partition 分区名称
     * @return $this
     */
    public function partition( $partition )
    {
        $this->options[ 'partition' ] = $partition;
        return $this;
    }

    /**
     * 设置查询的额外参数
     * @access public
     * @param string $extra 额外信息
     * @return $this
     */
    public function extra( string $extra )
    {
        $this->options[ 'extra' ] = $extra;
        return $this;
    }

    /**
     * 获取执行的SQL语句而不进行实际的查询
     * @access public
     * @param bool $fetch 是否返回sql
     * @return $this|Fetch
     */
    public function fetchSql( bool $fetch = true )
    {
        $this->options[ 'fetch_sql' ] = $fetch;
        return $this;
    }

    /**
     * 返回sql原生语法和bindValue Array和最终生成的语法
     * @access public
     * @param bool $fetch 是否返回sql
     * @return $this|Fetch
     */
    public function debugSql( bool $fetch = true )
    {
        $this->options[ 'debug_sql' ] = $fetch;
        return $this;
    }

    /**
     * todo 暂时没用
     * 查询缓存
     * @access public
     * @param mixed $key 缓存key
     * @param integer|\DateTime $expire 缓存有效期
     * @param string|array $tag 缓存标签
     * @return $this
     */
    public function cache( $key = true, $expire = null, $tag = null )
    {
        if ( false === $key || !$this->getConnection()->getCache() ) {
            return $this;
        }

        if ( $key instanceof \DateTimeInterface || $key instanceof \DateInterval || ( is_int( $key ) && is_null( $expire ) ) ) {
            $expire = $key;
            $key = true;
        }

        $this->options[ 'cache' ] = [ $key, $expire, $tag ];

        return $this;
    }


    public function __destruct()
    {

    }
}