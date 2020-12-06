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

use Tmac\Contract\DatabaseInterface;
use Tmac\Database\Concern\AggregateQuery;
use Tmac\Database\Concern\Builder;
use Tmac\Database\Concern\Orm;
use Tmac\Database\Concern\Query;
use Tmac\Database\Concern\ParamsBind;
use Closure;

class QueryBuilderDatabase
{
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
    protected $className;//实体类的className
    protected $schema;//实体类的数据表的schema

    protected $field = '*';
    protected $countField = '*';
    protected $where;
    protected $orderBy;
    protected $groupBy;
    protected $limit;
    protected $top;
    protected $joinString;

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
        'like', 'like binary', 'not like', 'ilike',
        '&', '|', '^', '<<', '>>',
        'rlike', 'not rlike', 'regexp', 'not regexp',
        '~', '~*', '!~', '!~*', 'similar to',
        'not similar to', 'not ilike', '~~*', '!~~*',
    ];

    /**
     * 初始化
     */
    public function __construct( DriverDatabase $connection, $table, $className, $schema, $primaryKey )
    {
        $this->driverDatabase = $connection;
        $this->conn = $connection->getInstance();
        $this->table = $table;
        $this->className = $className;
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
        return new static( $this->driverDatabase, $this->table, $this->className, $this->schema, $this->primaryKey );
    }

    /**
     * @return mixed
     */
    public function getClassName()
    {
        return $this->className;
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

    public function getField()
    {
        return $this->field;
    }

    public function getOrderBy()
    {
        return $this->orderBy;
    }

    function getGroupby()
    {
        return $this->groupBy;
    }

    public function setField( $field )
    {
        $this->field = $field;
        return $this;
    }

    /**
     * @return string
     */
    public function getCountField(): string
    {
        return $this->countField;
    }

    function setCountField( $countField )
    {
        $this->countField = $countField;
        return $this;
    }

    function setGroupby( $groupBy )
    {
        $this->groupBy = $groupBy;
        return $this;
    }

    public function setOrderBy( $orderBy )
    {
        $this->orderBy = $orderBy;
        return $this;
    }

    public function getWhere()
    {
        return $this->where;
    }

    public function setWhere( $where )
    {
        $this->where = $where;
        return $this;
    }

    public function getLimit()
    {
        return $this->limit;
    }

    public function setLimit( int $limit, int $offset = null )
    {
        $this->limit = $limit . ( $offset ? ',' . $offset : '' );
        return $this;
    }

    public function getPk()
    {
        return $this->pk;
    }

    public function setPk( $pk )
    {
        $this->pk = $pk;
        return $this;
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
            if ( !empty( $this->options[ 'alias' ][ $table ] ) ) {
                $alias = $this->options[ 'alias' ][ $table ];
                $options[ 'table' ] = $this->getTable() . ' ' . $alias;
            } else {
                $options[ 'table' ] = $this->getTable();
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

        foreach ( [ 'master', 'lock', 'fetch_sql', 'distinct', 'procedure' ] as $name ) {
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
     * @return mixed
     */
    public function getJoinString()
    {
        return $this->joinString;
    }

    /**
     * @param mixed $joinString
     */
    public function setJoinString( $joinString )
    {
        $this->joinString = $joinString;
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
     * @return type
     */
    public function join( $join, string $condition = null, string $type = 'INNER', array $bind = [] )
    {
        $table = $this->getJoinTable( $join );

        if ( !empty( $bind ) && $condition ) {
            $this->bindParams( $condition, $bind );
        }

        $this->options[ 'join' ][] = [ $table, strtoupper( $type ), $condition ];

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
    protected function getJoinTable( $join )
    {
        if ( is_array( $join ) ) {
            return $join[ 0 ] . ' ' . $join[ 1 ];
        } elseif ( $join instanceof Raw ) {
            return $join;
        }

        $join = trim( $join );

        if ( false !== strpos( $join, '(' ) ) {
            // 使用子查询
            return $join;
        }
        // 使用别名
        if ( strpos( $join, ' ' ) ) {
            // 使用别名
            [ $table, $alias ] = explode( ' ', $join );
        } else {
            $table = $join;
            if ( false === strpos( $join, '.' ) ) {
                $alias = $join;
            }
        }


        return $table;
    }


    /**
     * where解析
     */
    private function parseWhereExp( $column, $operator = null, $value = null, $boolean = 'and' )
    {
        // If the column is an array, we will assume it is an array of key-value pairs
        // and can add them each as a where clause. We will maintain the boolean we
        // received when the method was called and pass it into the nested where.
        if ( is_array( $column ) ) {
            return $this->addArrayOfWheres( $column, $boolean );
        }

        // If the columns is actually a Closure instance, we will assume the developer
        // wants to begin a nested where statement which is wrapped in parenthesis.
        // We'll add that Closure to the query then return back out immediately.
        if ( $column instanceof Closure ) {
            $subWhereSql = $this->parseClosureWhere( $this->newQuery(), $column, $boolean );
            if ( $subWhereSql ) {
                $value = new TmacDbExpr( $subWhereSql );
                $type = 'raw';
                $this->options[ 'where' ][] = compact(
                    'type', 'value', 'boolean'
                );
            }
            return $this;
        }

        //如果where的查询值是数组，就转成逗号分割的字符串，如果是默认=于操作的，改成in
        if ( is_array( $value ) ) {
            $value = new TmacDbExpr( '(' . implode( ',', $value ) . ')' );
            $operator = $operator === '=' ? 'IN' : $operator;
        }

        $type = 'basic';
        $this->options[ 'where' ][] = compact(
            'type', 'column', 'operator', 'value', 'boolean'
        );

        return $this;
    }

    public function from( $table )
    {
        if ( is_array( $table ) ) {
            $table = implode( ',', $table );
        }
        $this->options[ 'table' ] = $table;

        return $this;
    }

    public function where( $column, $operator = null, $value = null )
    {
        [ $value, $operator ] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );
        $this->options[ 'where' ] = [];//初始化where数组
        return $this->parseWhereExp( $column, $operator, $value );
    }


    public function andWhere( $column, $operator = null, $value = null )
    {
        [ $value, $operator ] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );
        return $this->parseWhereExp( $column, $operator, $value );
    }

    /**
     * Add an "or where" clause to the query.
     *
     * @param \Closure|string|array $column
     * @param mixed $operator
     * @param mixed $value
     * @return $this
     */
    public function orWhere( $column, $operator = null, $value = null )
    {
        [ $value, $operator ] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );
        return $this->parseWhereExp( $column, $operator, $value, 'or' );
    }

    /**
     * Add a raw where clause to the query.
     *
     * @param string $sql
     * @param mixed $bindings
     * @param string $boolean
     * @return $this
     */
    public function whereRaw( string $sql, array $bind = [], string $boolean = 'and' )
    {
        if ( !empty( $bind ) ) {
            $this->bindParams( $where, $bind );
        }

        $type = 'sql';
        $value = new Raw( $sql );
        $this->options[ 'where' ][] = compact(
            'type', 'value', 'boolean'
        );

        return $this;
    }

    /**
     * 指定表达式查询条件 OR
     * @access public
     * @param string $where 查询条件
     * @param array $bind 参数绑定
     * @return $this
     */
    public function orWhereRaw( string $sql, array $bind = [] )
    {
        return $this->whereRaw( $sql, $bind, 'OR' );
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
        $this->bindings[ 'select' ] = [];
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
     * @return object
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