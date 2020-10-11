<?php
declare ( strict_types=1 );

namespace Tmac\Database\Concern;

use Tmac\Database\BaseQueryDatabase;
use Tmac\Database\Raw;
use Tmac\Database\TmacDbExpr;
use Exception;
use Closure;

trait BuilderQuery
{


    public function build()
    {
        $this->conditionBuilders[ 'select' ] = $this->buildSelect( $this->getOptions( 'field' ), $this->getOptions( 'distinct' ) );
        $this->conditionBuilders[ 'from' ] = $this->buildFrom( $this->getOptions( 'table' ) );
        $this->conditionBuilders[ 'join' ] = $this->buildjoin( $this->getOptions( 'where' ) );
        $this->conditionBuilders[ 'where' ] = $this->buildWhere( $this->getOptions( 'where' ) );
        $this->conditionBuilders[ 'group' ] = $this->buildOrderBy( $this->getOptions( 'group' ) );
        $this->conditionBuilders[ 'order' ] = $this->buildOrderBy( $this->getOptions( 'order' ) );
        $this->conditionBuilders[ 'limit' ] = $this->buildLimit( $this->getOptions( 'limit' ) );

        return $this->getConn()->buildSelectSql( $this->conditionBuilders, $this->options );
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
    protected function parseClosureWhere( BaseQueryDatabase $query, Closure $value, string $logic )
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
     * 生成查询条件SQL
     * @access public
     * @param BaseQueryDatabase $query 查询对象
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
    protected function removeOption(string $option = '')
    {
        if ('' === $option) {
            $this->options = [];
            //todo $this->bind    = [];
        } elseif (isset($this->options[$option])) {
            unset($this->options[$option]);
        }

        return $this;
    }
}
