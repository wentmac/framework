<?php
declare ( strict_types=1 );

namespace Tmac\Database\Concern;

use Tmac\Database\BaseQueryDatabase;
use Tmac\Database\QueryBuilderDatabase;
use Tmac\Database\Raw;
use Tmac\Database\TmacDbExpr;
use Exception;
use Closure;

trait Builder
{

    /**
     * 生成查询sql语句
     * @return mixed
     */
    public function getSelectSql()
    {
        $options = $this->parseOptions();
        $this->conditionBuilders[ 'distinct' ] = $this->buildDistinct( $options[ 'distinct' ] );
        $this->conditionBuilders[ 'select' ] = $this->buildSelect( $options[ 'field' ], $options[ 'distinct' ] );
        $this->conditionBuilders[ 'from' ] = $this->buildFrom( $options[ 'table' ] );
        $this->conditionBuilders[ 'join' ] = $this->buildJoin( $options[ 'join' ] );
        $this->conditionBuilders[ 'where' ] = $this->buildWhere( $options[ 'where' ] );
        $this->conditionBuilders[ 'group' ] = $this->buildGroupBy( $options[ 'group' ] );
        $this->conditionBuilders[ 'having' ] = $this->buildHaving( $options[ 'having' ] );
        $this->conditionBuilders[ 'order' ] = $this->buildOrderBy( $options[ 'order' ] );
        $this->conditionBuilders[ 'limit' ] = $this->buildLimit( $options[ 'limit' ] );
        $this->conditionBuilders[ 'union' ] = $this->buildUnion( $options[ 'union' ] );
        $this->conditionBuilders[ 'lock' ] = $this->buildLock( $options[ 'lock' ] );
        $this->conditionBuilders[ 'force' ] = $this->buildForce( $options[ 'force' ] );

        $sql = $this->getConn()->buildSelectSql( $this->conditionBuilders );
        $this->removeOption();
        return $sql;
    }

    /**
     * 生成更新sql语句
     * @return mixed
     */
    public function getUpdateSql()
    {
        $options = $this->parseOptions();
        $this->conditionBuilders[ 'extra' ] = $this->buildExtra( $options[ 'extra' ] );
        $this->conditionBuilders[ 'table' ] = $options[ 'table' ];
        $this->conditionBuilders[ 'data' ] = $this->buildData( $options[ 'data' ] );
        $this->conditionBuilders[ 'join' ] = $this->buildJoin( $options[ 'join' ] );
        $this->conditionBuilders[ 'where' ] = $this->buildWhere( $options[ 'where' ] );
        $this->conditionBuilders[ 'order' ] = $this->buildOrderBy( $options[ 'order' ] );
        $this->conditionBuilders[ 'limit' ] = $this->buildLimit( $options[ 'limit' ] );
        $this->conditionBuilders[ 'lock' ] = $this->buildLock( $options[ 'lock' ] );

        $sql = $this->getConn()->buildUpdateSql( $this->conditionBuilders );
        $this->removeOption();
        return $sql;
    }

    /**
     * 生成新增sql语句
     * @return mixed
     */
    public function getInsertSql()
    {
        $options = $this->parseOptions();
        $this->conditionBuilders[ 'replace' ] = $options[ 'replace' ];
        $this->conditionBuilders[ 'extra' ] = $this->buildExtra( $options[ 'extra' ] );
        $this->conditionBuilders[ 'table' ] = $options[ 'table' ];
        $this->conditionBuilders[ 'field' ] = $this->buildData( $options[ 'field' ] );
        $this->conditionBuilders[ 'data' ] = $this->buildData( $options[ 'data' ] );

        $sql = $this->getConn()->buildInsertSql( $this->conditionBuilders );
        $this->removeOption();
        return $sql;
    }

    /**
     * 生成新增all sql语句
     * @return mixed
     */
    public function getInsertAllSql( array $data, int $limit = 0 )
    {
        $options = $this->parseOptions();
        $this->conditionBuilders[ 'replace' ] = $options[ 'replace' ];
        $this->conditionBuilders[ 'extra' ] = $this->buildExtra( $options[ 'extra' ] );
        $this->conditionBuilders[ 'table' ] = $options[ 'table' ];
        $this->conditionBuilders[ 'data' ] = $data;

        $sql = $this->getConn()->buildInserAllSql( $this->conditionBuilders );
        $this->removeOption();
        return $sql;
    }

    /**
     * 生成删除sql语句
     * @return mixed
     */
    public function getDeleteSql()
    {
        $options = $this->parseOptions();
        $this->conditionBuilders[ 'extra' ] = $this->buildExtra( $options[ 'extra' ] );
        $this->conditionBuilders[ 'table' ] = $options[ 'table' ];
        $this->conditionBuilders[ 'join' ] = $this->buildJoin( $options[ 'join' ] );
        $this->conditionBuilders[ 'where' ] = $this->buildWhere( $options[ 'where' ] );
        $this->conditionBuilders[ 'order' ] = $this->buildOrderBy( $options[ 'order' ] );
        $this->conditionBuilders[ 'limit' ] = $this->buildLimit( $options[ 'limit' ] );
        $this->conditionBuilders[ 'lock' ] = $this->buildLock( $options[ 'lock' ] );

        $sql = $this->getConn()->buildDeleteSql( $this->conditionBuilders );
        $this->removeOption();
        return $sql;
    }

    /**
     * 得到某个字段的值
     * @access public
     * @param string $field 字段名
     * @param mixed $default 默认值
     * @return string
     */
    public function value( string $field, $default = null )
    {
        $options = $this->parseOptions();
        if ( $options[ 'field' ] !== null ) {
            $this->options[ 'field' ] = $field;
        }
        $sql = $this->getSelectSql();
        $binds = $this->getBind();
        $result = $this->getConn()->fetchColumn( $sql, $binds );
        if ( $result == false ) {
            return $default;
        }
        return $result;
    }

    /**
     * Prepare the value and operator for a where clause.
     *
     * @param string $value
     * @param string $operator
     * @param bool $useDefault
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    protected function prepareValueAndOperator( $value, $operator, $useDefault = false )
    {
        if ( $useDefault ) {
            return [ $operator, '=' ];
        } elseif ( $this->invalidOperatorAndValue( $operator, $value ) ) {
            throw new InvalidArgumentException( 'Illegal operator and value combination.' );
        }

        return [ $value, $operator ];
    }

    /**
     * Determine if the given operator and value combination is legal.
     *
     * Prevents using Null values with invalid operators.
     *
     * @param string $operator
     * @param mixed $value
     * @return bool
     */
    protected function invalidOperatorAndValue( $operator, $value )
    {
        return is_null( $value ) && in_array( $operator, $this->operators ) &&
            !in_array( $operator, [ '=', '<>', '!=' ] );
    }

    /**
     * Add an array of where clauses to the query.
     *
     * @param array $column
     * @param string $boolean
     * @param string $method
     * @return $this
     */
    protected function addArrayOfWheres( $column, $boolean, $method = 'andWhere' )
    {
        foreach ( $column as $key => $value ) {
            if ( is_numeric( $key ) && is_array( $value ) ) {
                $this->{$method}( ...array_values( $value ) );
            } else {
                $this->$method( $key, '=', $value, $boolean );
            }
        }
        return $this;
    }

    /**
     * 闭包查询
     * @access protected
     * @param Query $query 查询对象
     * @param Closure $value 查询条件
     * @param string $logic Logic
     * @return string
     */
    protected function parseClosureWhere( QueryBuilderDatabase $query, Closure $value, string $logic )
    {
        $value( $query );

        //print_r( $query->options );

        $whereClosure = $this->parseWhere( $query->getOptions( 'where' ) ? : [] );

        if ( !empty( $whereClosure ) ) {
            $this->bind( $query->getBind( false ) );
            $where = ' ' . $logic . '  ( ' . $whereClosure . ' )';
        }

        return $where ?? '';
    }


    /**
     * 解析table
     * @param $table
     * @return string
     */
    protected function buildFrom( $table ): string
    {
        if ( empty( $table ) ) {
            $table = $this->getTable();
        }
        return 'FROM ' . $table;
    }


    /**
     * 解析table
     * @param $table
     * @return string
     */
    protected function buildJoin( array $join = [] ): string
    {
        $joinStr = '';
        foreach ( $join as $item ) {
            [ $table, $type, $on ] = $item;
            $condition = $on;
            $joinStr .= ' ' . $type . ' JOIN ' . $table . ' ON ' . $condition;
        }

        return $joinStr;
    }

    /**
     * 解析要更新语句
     * @param array $data
     * @return string
     */
    protected function buildData( array $data ): string
    {
        return implode( ',', $data );
    }

    /**
     * 解析field
     * @param $table
     * @return string
     */
    protected function buildSelect( $field, $distinct ): string
    {
        if ( empty( $field ) ) {
            $field = '*';
        }
        $select = $distinct ? 'SELECT DISTINCT' : 'SELECT';
        return $select . $this->separator . $field;
    }

    /**
     * distinct分析
     *
     * ->distinct('id')->
     * ->distinct(true)->select('id')
     * ->select('id',true)
     *
     * @access protected
     * @param Query $query 查询对象
     * @param mixed $distinct
     * @return string
     */
    protected function buildDistinct( $field ): string
    {
        if ( !empty( $field ) ) {
            $field !== true && $this->select( $field );
            return 'DISTINCT ';
        } else {
            return '';
        }
    }


    /**
     * 解析 where
     * @param $where
     * @return string
     */
    protected function buildWhere( array $where ): string
    {
        $whereStr = $this->parseWhere( $where );
        return empty( $whereStr ) ? '' : 'WHERE ' . $whereStr;
    }

    /**
     * order by 语句编译解析
     * @param $orderBy
     * @return string
     */
    protected function buildOrderBy( $orderBy ): string
    {
        if ( empty( $orderBy ) ) {
            return '';
        }
        return 'ORDER BY ' . $orderBy;
    }

    /**
     * limit 语句编译解析
     * @param $orderBy
     * @return string
     */
    protected function buildLimit( $limit ): string
    {
        if ( empty( $limit ) ) {
            return '';
        }
        return 'LIMIT ' . $limit;
    }

    /**
     * union分析
     * @access protected
     * @param array $union
     * @return string
     */
    protected function buildUnion( $union ): string
    {
        if ( empty( $union ) ) {
            return '';
        }

        $type = $union[ 'type' ];
        unset( $union[ 'type' ] );

        foreach ( $union as $u ) {
            if ( $u instanceof Closure ) {
                $sql[] = $type . ' ' . $this->parseClosureWhere( $this->newQuery(), $u );
            } elseif ( is_string( $u ) ) {
                $sql[] = $type . ' ( ' . $u . ' )';
            }
        }

        return implode( ' ', $sql );
    }

    /**
     * index分析，可在操作链中指定需要强制使用的索引
     * @access protected
     * @param Query $query 查询对象
     * @param mixed $index
     * @return string
     */
    protected function buildForce( $index ): string
    {
        if ( empty( $index ) ) {
            return '';
        }

        if ( is_array( $index ) ) {
            $index = join( ',', $index );
        }

        return sprintf( "FORCE INDEX ( %s ) ", $index );
    }


    /**
     * 设置锁机制
     * @access protected
     * @param Query $query 查询对象
     * @param bool|string $lock
     * @return string
     */
    protected function buildLock( $lock = false ): string
    {
        if ( is_bool( $lock ) ) {
            return $lock ? 'FOR UPDATE ' : '';
        }

        if ( is_string( $lock ) && !empty( $lock ) ) {
            return trim( $lock ) . ' ';
        } else {
            return '';
        }
    }

    /**
     * group by 语句编译解析
     * @param $group
     * @return string
     */
    protected function buildGroupBy( $group ): string
    {
        if ( empty( $group ) ) {
            return '';
        }
        return 'GROUP BY ' . $group;
    }

    /**
     * group by 语句编译解析
     * @param $having
     * @return string
     */
    protected function buildHaving( $having ): string
    {
        return !empty( $having ) ? 'HAVING ' . $having : '';
    }

    /**
     * 生成查询条件SQL
     * @access public
     * @param QueryBuilderDatabase $query 查询对象
     * @param mixed $where 查询条件
     * @return string
     */
    protected function parseWhere( array $where ): string
    {
        if ( empty( $where ) ) {
            $where = [];
        }
        $whereStr = '';
        foreach ( $where as $val ) {
            $str = $this->parseWhereLogic( $val );
            $logic = $val[ 'boolean' ];
            $whereStr .= empty( $whereStr ) ? substr( $str, strlen( $logic ) + 1 ) : $str;
        }

        return $whereStr;
    }

    /**
     * Compile a "where in" clause.
     * @param $field
     * @param $value
     * @return string
     */
    protected function parseWhereIn( $field, $value, $not = false )
    {
        $type = $not ? 'NotIn' : 'In';
        if ( !empty( $value ) ) {
            return $this->wrap( $where[ 'column' ] ) . ' in (' . $this->parameterize( $where[ 'values' ] ) . ')';
        }

        return '0 = 1';
    }

    /**
     * 不同字段使用相同查询条件（AND）
     * @access protected
     * @param array $value 查询条件
     * @return string
     */
    protected function parseWhereLogic( $value ): string
    {
        $logic = strtoupper( $value[ 'boolean' ] );
        $type = $value[ 'type' ];
        $where = '';

        if ( $type == 'sql' && $value[ 'value' ] instanceof Raw ) {
            $where = " {$logic} " . $value[ 'value' ]->getValue();
        } elseif ( $type == 'raw' && $value[ 'value' ] instanceof Raw ) {
            $where = $value[ 'value' ]->getValue();
        } elseif ( is_array( $value ) ) {
            if ( key( $value ) === 0 ) {
                throw new Exception( 'where express error:' . var_export( $value, true ) );
            }
            //取了别名后的
            $column = $this->parseKey($value['column']);
            $where = " {$logic} {$column} {$value['operator']} ";
            //进行数据bindValue
            $where .= $this->parseBuilderDataBind( $value[ 'column' ], $value[ 'value' ] );
        } elseif ( true === $value ) {
            $where = ' ' . $logic . ' 1 ';
        } elseif ( $value instanceof Closure ) {
            // 使用闭包查询
            $whereClosureStr = $this->parseClosureWhere( $this->newQuery(), $value, $logic );
            if ( $whereClosureStr ) {
                $where = $whereClosureStr;
            }
        }

        return $where;
    }

    /**
     * 解析正确的key名。主要是为了alias
     * @param $key
     * @return string
     */
    private function parseKey( $key )
    {
        //判断别名
        $alias = $this->getOptions( 'alias' );
        $table = $this->getTable();
        if ( isset( $alias[ $table ] ) ) {
            $column = $alias[ $table ] . '.' . $key;
        } else {
            $column = $key;
        }
        return $column;
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
    private function parseBuilderDataBind( string $key, $value ): string
    {
        if ( $value instanceof TmacDbExpr ) {
            return $value->getValue();
        }
        $name = $this->generateBindName( $key );

        //直接从Repository中取字段的schema的类型。减少很个字段的判断
        if ( empty( $this->schema[ $key ] ) ) {
            $type = null;
        } else {
            $type = $this->schema[ $key ];
        }
        $this->bindValue( $value, $type, $name );
        return ':' . $name;
    }

    /**
     * 去除查询参数
     * @access public
     * @param string $option 参数名 留空去除所有参数
     * @return $this
     */
    protected function removeOption( string $option = '' )
    {
        if ( '' === $option ) {
            $this->options = [];
            $this->conditionBuilders = [];
        } elseif ( isset( $this->options[ $option ] ) ) {
            unset( $this->options[ $option ], $this->conditionBuilders[ $option ] );
        }

        return $this;
    }

    /**
     * 随机排序
     * @access protected
     * @return string
     */
    protected function buildRand(): string
    {
        return 'rand()';
    }

    /**
     * 查询额外参数分析
     * @access protected
     * @param string $extra 额外参数
     * @return string
     */
    protected function buildExtra( $extra = '' ): string
    {
        if ( empty( $extra ) ) {
            return '';
        }
        return preg_match( '/^[\w]+$/i', $extra ) ? ' ' . strtoupper( $extra ) : '';
    }

    /**
     * Partition 分析
     * @access protected
     * @param string|array $partition 分区
     * @return string
     */
    protected function buildPartition( $partition ): string
    {
        if ( '' == $partition ) {
            return '';
        }

        if ( is_string( $partition ) ) {
            $partition = explode( ',', $partition );
        }

        return ' PARTITION (' . implode( ' , ', $partition ) . ') ';
    }

}
