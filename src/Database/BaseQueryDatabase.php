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

use Tmac\Database\Concern\BuilderQuery;
use Tmac\Database\Concern\OrmQuery;
use Tmac\Database\Concern\ParamsBind;
use Closure;

class BaseQueryDatabase
{
    use OrmQuery;
    use BuilderQuery;
    use ParamsBind;

    protected $driverDatabase;
    protected $conn;
    protected $pk;
    private $primaryKey; //主键字段名
    protected $table;
    protected $field = '*';
    protected $countField = '*';
    protected $where;
    protected $orderBy;
    protected $groupBy;
    protected $limit;
    protected $top;
    protected $joinString;

    /**
     * 当前查询参数
     * @var array
     */
    protected $options = [];
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
    public function __construct( DriverDatabase $connection, $table, $primaryKey )
    {
        $this->driverDatabase = $connection;
        $this->conn = $connection->getInstance();
        $this->table = $table;
        $this->primaryKey = $primaryKey;
    }

    /**
     * 创建一个新的查询对象
     * @access public
     * @return BaseQuery
     */
    public function newQuery(): BaseQueryDatabase
    {
        return new static( $this->driverDatabase, $this->table, $this->primaryKey );
    }


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

    public function getTop()
    {
        return $this->top;
    }

    public function setTop( $top )
    {
        $this->top = $top;
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
     * 通过主键取数据库信息
     * @return type
     */
    public function getInfoByPk( $id = 0 )
    {
        if ( !empty( $id ) ) {
            $this->pk = $id;
        }
        $sql = $this->getConn()->getInfoSqlByPk( $this );
        $res = $this->getConn()->getRowObject( $sql );
        return $res;
    }

    /**
     * 通过$where条件取数据库信息
     * @return type
     */
    public function getInfoByWhere()
    {
        $sql = $this->getConn()->getInfoSqlByWhere( $this );
        $res = $this->getConn()->getRowObject( $sql );
        return $res;
    }

    /**
     * 通过$where条件取多条数据库信息
     * @return type
     */
    public function getArrayListByWhere()
    {
        $sql = $this->getConn()->getSqlByWhere( $this );
        $res = $this->getConn()->getAll( $sql );
        return $res;
    }

    /**
     * 通过$where条件取多条数据库信息
     * @return type
     */
    public function getListByWhere()
    {
        $sql = $this->getConn()->getSqlByWhere( $this );
        $res = $this->getConn()->getAllObject( $sql );
        return $res;
    }

    /**
     * 通过$where条件取总数
     * @return integer
     */
    public function getCountByWhere()
    {
        $sql_count = $this->getConn()->getCountSqlByWhere( $this );
        $count = $this->getConn()->getOne( $sql_count );
        return $count;
    }

    /**
     * 通过主键更新数据
     * @param  $entity
     * @return boolean
     */
    public function updateByPk( $entity )
    {
        if ( empty( $this->pk ) ) {
            return false;
        }
        $where = $this->getPrimaryKey() . '=' . $this->pk;
        $rs = $this->getConn()->updateObject( $this->getTable(), $entity, $where, $this->getPrimaryKey() );
        return $rs;
    }

    /**
     * 通过$where条件更新数据
     * @param $entity
     * @return bool
     */
    public function updateByWhere( $entity )
    {
        $rs = $this->getConn()->updateObject( $this->getTable(), $entity, $this->getWhere() );
        return $rs;
    }

    /**
     * 插入数据
     * @param $entity
     * @return int
     */
    public function insert( $entity )
    {
        return $this->getConn()->insertObject( $this->getTable(), $entity );
    }

    /**
     * 通过主键删除一条记录{删除数据的操作请慎用}
     * @return type
     */
    public function deleteByPk()
    {
        $sql = $this->getConn()->getDeleteSqlByPk( $this );
        $res = $this->getConn()->execute( $sql );
        return $res;
    }

    /**
     * 通过$where条件删除N条记录{删除数据的操作请慎用}
     * @return type
     */
    public function deleteByWhere()
    {
        $sql = $this->getConn()->getDeleteSqlByWhere( $this );
        $res = $this->getConn()->execute( $sql );
        return $res;
    }

    /**
     * 取有可能有where in的语句
     * @param string $field
     * @param array|string $value 支持 array,int_string,int
     * @return type
     */
    public function getWhereInStatement( $field, $value )
    {
        if ( is_array( $value ) ) {
            $value_string = implode( ',', $value );
            return "{$field} IN({$value_string}) ";
        }
        if ( strpos( $value, ',' ) !== false ) {
            return "{$field} IN({$value}) ";
        } else {
            return "{$field} ={$value} ";
        }
    }

    /**
     * join子句查询
     * 支持  left|right|outer|inner|left outer|right outer
     *
     * $dao = dao_factory_base::getGoodsDao();
     * $goods_image_dao = dao_factory_base::getGoodsImageDao();
     * $dao->join($goods_image_dao->getTable(),$goods_image_dao->getTable().'goods_id='.$dao->getTable().'.goods_id','left');
     * $dao->setWhere($goods_image_dao->getTable().'.uid='.$uid);
     * $res = $dao->getListByWhere();
     *
     * @param type $joinTable 表名
     * @param type $on join时候的on语句
     * @param type $joinType 联表的类型
     * @return type
     */
    public function join( $joinTable, $on, $joinType = '' )
    {
        $joinTypeArray = array( 'LEFT', 'RIGHT', 'OUTER', 'INNER', 'LEFT OUTER', 'RIGHT OUTER' );
        $joinTypeString = 'JOIN'; //默认joinType为空时 是JOIN
        if ( !empty( $joinType ) ) {
            $joinType = strtoupper( $joinType );
            if ( in_array( $joinType, $joinTypeArray ) ) {
                $joinTypeString = $joinType . ' JOIN';
            }
        }
        $this->joinString = $joinTypeString . ' ' . $joinTable . ' ON ' . $on;
        return $this;
    }


    /**
     * where解析
     */
    private function parseWhere( $column, $operator = null, $value = null, $boolean = 'and' )
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
                $this->options[ 'where' ][] = new Raw( $subWhereSql );
            }
            return $this;
        }

        $this->options[ 'where' ][] = compact(
            'column', 'operator', 'value', 'boolean'
        );

        return $this;
    }

    public function where( $column, $operator = null, $value = null )
    {
        [ $value, $operator ] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );
        $this->options[ 'where' ] = [];//初始化where数组
        return $this->parseWhere( $column, $operator, $value );
    }


    public function andWhere( $column, $operator = null, $value = null )
    {
        [ $value, $operator ] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );
        return $this->parseWhere( $column, $operator, $value );
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
        return $this->parseWhere( $column, $operator, $value, 'or' );
    }

    /**
     * Add a raw where clause to the query.
     *
     * @param string $sql
     * @param mixed $bindings
     * @param string $boolean
     * @return $this
     */
    public function whereRaw( $sql, $bindings = [], $boolean = 'and' )
    {
        $this->wheres[] = [ 'type' => 'raw', 'sql' => $sql, 'boolean' => $boolean ];

        $this->addBinding( (array) $bindings, 'where' );

        return $this;
    }


    /**
     * $sql = "select * from posts where post_title=? and post_time >? order by post_id desc limit 0, 10";
     * $postModel->query($sql, array('doitphp', '2010-5-4'))->fetchAll();
     *
     * 执行SQL语句
     *
     * 注：用于执行查询性的SQL语句（需要数据返回的情况）。
     *
     * @access public
     *
     * @param string $sql SQL语句
     * @param array $params 待转义的参数值
     *
     * @return mixed
     */
    public function query( $sql )
    {

    }


    /**
     * 例二、 $sql = "update posts set post_title=? where id=5";
     * $postModel->execute($sql, 'lucky tommy every day');
     *
     * 执行SQL语句
     *
     * 注：本方法用于无需返回信息的操作。如：更改、删除、添加数据信息(即：用于执行非查询SQL语句)
     *
     * @access public
     *
     * @param string $sql 所要执行的SQL语句
     * @param array $params 待转义的数据。注：本参数支持字符串及数组，如果待转义的数据量在两个或两个以上请使用数组
     *
     * @return boolean
     */
    public function execute( $sql, $params = null )
    {

    }


    public function getQuery()
    {
    }

    public function __destruct()
    {

    }
}