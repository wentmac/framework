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


class BaseQueryDatabase
{
    protected $conn;
    protected $pk;
    private $primaryKey; //主键字段名
    protected $table;
    protected $field = '*';
    protected $count_field = '*';
    protected $where;
    protected $orderby;
    protected $groupby;
    protected $limit;
    protected $top;
    protected $joinString;

    /**
     * 初始化
     */
    public function __construct( DriverDatabase $connection, $table, $primaryKey )
    {
        $this->conn = $connection->getInstance();
        $this->table = $table;
        $this->primaryKey = $primaryKey;
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

    public function getOrderby()
    {
        return $this->orderby;
    }

    function getGroupby()
    {
        return $this->groupby;
    }

    public function setField( $field )
    {
        $this->field = $field;
        return $this;
    }

    function setCountField( $count_field )
    {
        $this->count_field = $count_field;
        return $this;
    }

    function setGroupby( $groupby )
    {
        $this->groupby = $groupby;
        return $this;
    }

    public function setOrderby( $orderby )
    {
        $this->orderby = $orderby;
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

    public function setLimit( $limit )
    {
        $this->limit = $limit;
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

    protected function getPrimaryKey()
    {
        return $this->primaryKey;
    }

    protected function setPrimaryKey( $primaryKey )
    {
        $this->primaryKey = $primaryKey;
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
        $sql = "SELECT {$this->getField()} "
            . "FROM {$this->getTable()} "
            . "WHERE {$this->getPrimaryKey()}={$this->getPk()}";
        $res = $this->getConn()->getRowObject( $sql );
        return $res;
    }

    /**
     * 通过$where条件取数据库信息
     * @return type
     */
    public function getInfoByWhere()
    {
        $sql = "SELECT {$this->getField()} "
            . "FROM {$this->getTable()} ";
        if ( $this->joinString != null ) {
            $sql .= "{$this->joinString} ";
        }
        $sql .= "WHERE {$this->getWhere()}";
        if ( $this->getOrderby() != null ) {
            $sql .= " ORDER BY {$this->getOrderby()} ";
        }
        $res = $this->getConn()->getRowObject( $sql );
        return $res;
    }

    /**
     * 通过$where条件取多条数据库信息
     * @return type
     */
    public function getArrayListByWhere()
    {
        $sql = $this->getSqlByWhere();
        $res = $this->getConn()->getAll( $sql );
        return $res;
    }

    /**
     * 通过$where条件取多条数据库信息
     * @return type
     */
    public function getListByWhere()
    {
        $sql = $this->getSqlByWhere();
        $res = $this->getConn()->getAllObject( $sql );
        return $res;
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
    public function getSqlByWhere()
    {
        $sql = "SELECT ";
        if ( $this->getTop() != null ) {
            $sql .= "TOP {$this->getTop()} ";
        }
        $sql .= "{$this->getField()} "
            . "FROM {$this->getTable()} ";

        if ( $this->joinString != null ) {
            $sql .= "{$this->joinString} ";
        }
        if ( $this->getWhere() != null ) {
            $sql .= "WHERE {$this->getWhere()} ";
        }
        if ( $this->getGroupby() != null ) {
            $sql .= "GROUP BY {$this->getGroupby()} ";
        }
        if ( $this->getOrderby() != null ) {
            $sql .= "ORDER BY {$this->getOrderby()} ";
        }
        if ( $this->getLimit() != null ) {
            $sql .= "LIMIT {$this->getLimit()}";
        }
        return $sql;
    }

    /**
     * 通过$where条件取总数
     * @return integer
     */
    public function getCountByWhere()
    {
        $sql_count = "SELECT COUNT({$this->count_field}) FROM {$this->getTable()} ";
        if ( $this->getWhere() != null ) {
            $sql_count .= "WHERE " . $this->getWhere();
        }
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
        $sql = "DELETE FROM {$this->getTable()} "
            . "WHERE {$this->getPrimaryKey()}={$this->getPk()}";
        $res = $this->getConn()->execute( $sql );
        return $res;
    }

    /**
     * 通过$where条件删除N条记录{删除数据的操作请慎用}
     * @return type
     */
    public function deleteByWhere()
    {
        $sql = "DELETE FROM {$this->getTable()} "
            . "WHERE {$this->getWhere()}";
        $res = $this->getConn()->execute( $sql );
        return $res;
    }

    /**
     * 取有可能有where in的语句
     * @param  $field
     * @param  $value 支持 array,int_string,int
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
}