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


    public function build()
    {
        $this->conditionBuilders[ 'distinct' ] = $this->buildDistinct( $this->getOptions( 'distinct' ) );
        $this->conditionBuilders[ 'select' ] = $this->buildSelect( $this->getOptions( 'field' ), $this->getOptions( 'distinct' ) );
        $this->conditionBuilders[ 'from' ] = $this->buildFrom( $this->getOptions( 'table' ) );
        $this->conditionBuilders[ 'join' ] = $this->buildjoin( $this->getOptions( 'where' ) );
        $this->conditionBuilders[ 'where' ] = $this->buildWhere( $this->getOptions( 'where' ) );
        $this->conditionBuilders[ 'group' ] = $this->buildGroupBy( $this->getOptions( 'group' ) );
        $this->conditionBuilders[ 'having' ] = $this->buildHaving( $this->getOptions( 'having' ) );
        $this->conditionBuilders[ 'order' ] = $this->buildOrderBy( $this->getOptions( 'order' ) );
        $this->conditionBuilders[ 'limit' ] = $this->buildLimit( $this->getOptions( 'limit' ) );
        $this->conditionBuilders[ 'union' ] = $this->buildUnion( $this->getOptions( 'union' ) );
        $this->conditionBuilders[ 'lock' ] = $this->buildLock( $this->getOptions( 'lock' ) );
        $this->conditionBuilders[ 'force' ] = $this->buildForce( $this->getOptions( 'force' ) );

        return $this->getConn()->buildSelectSql( $this->conditionBuilders, $this->options );
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
        if ( $this->getOptions( 'field' ) !== null ) {
            $this->options[ 'field' ] = $field;
        }
        $sql = $this->build();
        $result = $this->getConn()->getOne( $sql );
        if ( $result == false ) {
            return '';
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
    protected function buildjoin( $table ): string
    {
        return '';
        if ( empty( $table ) ) {
            return $this->getTable();
        }
        return 'FROM ' . $table;
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
    protected function buildUnion( array $union ): string
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

            $where = " {$logic} {$value['column']} {$value['operator']} ";
            if ( $value[ 'value' ] instanceof TmacDbExpr ) {
                $where .= "{$value['value']}";
            } else {
                $where .= "'{$value['value']}'";
            }
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
     * 去除查询参数
     * @access public
     * @param string $option 参数名 留空去除所有参数
     * @return $this
     */
    protected function removeOption( string $option = '' )
    {
        if ( '' === $option ) {
            $this->options = [];
            //todo $this->bind    = [];
        } elseif ( isset( $this->options[ $option ] ) ) {
            unset( $this->options[ $option ] );
        }

        return $this;
    }
}
