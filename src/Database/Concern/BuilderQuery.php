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
    public function prepareValueAndOperator( $value, $operator, $useDefault = false )
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

        print_r( $query->options );

        $whereClosure = $this->buildWhere( $query->getOptions( 'where' ) ? : [] );

        if ( !empty( $whereClosure ) ) {
            $this->bind( $query->getBind( false ) );
            $where = ' ' . $logic . ' ( ' . $whereClosure . ' )';
        }

        return $where ?? '';
    }

    /**
     * 生成查询条件SQL
     * @access public
     * @param BaseQueryDatabase $query 查询对象
     * @param mixed $where 查询条件
     * @return string
     */
    private function buildWhere( array $where ): string
    {
        if ( empty( $where ) ) {
            $where = [];
        }
        $whereStr = '';
        foreach ( $where as $val ) {
            $str = $this->parseWhereLogic( $val );
            $logic = $val[ 'boolean' ];
            $whereStr .= empty( $whereStr ) ? substr( implode( ' ', $str ), strlen( $logic ) + 1 ) : implode( ' ', $str );
        }

        return $whereStr;
    }

    /**
     * 不同字段使用相同查询条件（AND）
     * @access protected
     * @param array $value 查询条件
     * @return array
     */
    protected function parseWhereLogic( array $value ): array
    {
        $logic = $value[ 'boolean' ];
        $where = [];

        if ( $value instanceof Raw ) {
            $where[] = ' ' . $logic . ' ( ' . $value . ' )';
        }

        if ( is_array( $value ) ) {
            if ( key( $value ) === 0 ) {
                throw new Exception( 'where express error:' . var_export( $value, true ) );
            }
            $where[] = " {$logic} {$value['column']}{$value['operator']}'{$value['value']}'";
        } elseif ( true === $value ) {
            $where[] = ' ' . $logic . ' 1 ';
        } elseif ( $value instanceof Closure ) {
            // 使用闭包查询
            $whereClosureStr = $this->parseClosureWhere( $this->newQuery(), $value, $logic );
            if ( $whereClosureStr ) {
                $where[] = $whereClosureStr;
            }
        }

        return $where;
    }

}
