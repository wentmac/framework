<?php
declare ( strict_types=1 );

namespace Tmac\Database\Concern;

use Tmac\Database\QueryBuilderDatabase;
use Tmac\Database\Raw;
use Closure;

trait Where
{


    /**
     * where解析
     * @param $column
     * @param null $operator
     * @param null $value
     * @param string $boolean
     * @return $this|QueryBuilderDatabase
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
            $subWhereSql = $this->parseClosureWhere( $this->newQuery(), $column );
            if ( $subWhereSql ) {
                $value = new Raw( $subWhereSql );
                $type = 'raw';
                $this->options[ 'where' ][] = compact(
                    'type', 'value', 'boolean'
                );
            }
            return $this;
        }

        //如果where的查询值是数组，就转成逗号分割的字符串，如果是默认=于操作的，改成in
        if ( is_array( $value ) ) {
            //$value = new Raw( '(' . implode( ',', $value ) . ')' );
            $operator = $operator === '=' ? 'IN' : $operator;
        }
        //像like这些操作符号转成大写
        if ( !empty( $operator ) ) {
            $operator = strtoupper( $operator );
        }

        $type = 'basic';
        $this->options[ 'where' ][] = compact(
            'type', 'column', 'operator', 'value', 'boolean'
        );

        return $this;
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
     * 通过设置主键筛选条件
     * 一般用在update delete select.find($id)
     * @param $id
     * @return $this|QueryBuilderDatabase
     */
    public function wherePk( $id )
    {
        return $this->where( $this->getPrimaryKey(), $id );
    }

    /**
     * where条件初始化
     * 取消原来->where条件中的初始化　
     * 这样->where和->andWhere是一样的了
     * 如果有需要where初始化的就使用此方法
     * @return $this
     */
    public function whereInit(): self
    {
        $this->options[ 'where' ] = [];//初始化where数组
        return $this;
    }

    /**
     * 设置where条件
     * @param $column
     * @param null $operator
     * @param null $value
     * @return $this|QueryBuilderDatabase
     */
    public function where( $column, $operator = null, $value = null )
    {
        [ $value, $operator ] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );
        return $this->parseWhereExp( $column, $operator, $value );
    }


    /**
     * andWhere
     * @param $column
     * @param null $operator
     * @param null $value
     * @return $this|QueryBuilderDatabase
     */
    public function andWhere( $column, $operator = null, $value = null )
    {
        return $this->where( $column, $operator, $value );
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
            $this->bindParams( $sql, $bind );
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
     * where IN 方法
     * @param $column
     * @param null $operator
     * @param null $value
     * @return $this
     */
    public function whereIn( $column, $operator = null, $value = null )
    {
        return $this->where( $column, 'IN', $value );
    }

    /**
     * or where IN 方法
     * @param $column
     * @param null $operator
     * @param null $value
     * @return $this
     */
    public function orwhereIn( $column, $value = null )
    {
        return $this->orWhere( $column, 'IN', $value );
    }

    /**
     * and where IN 方法
     * @param $column
     * @param null $operator
     * @param null $value
     * @return $this
     */
    public function andwhereIn( $column, $value = null )
    {
        return $this->andWhere( $column, 'IN', $value );
    }


    /**
     * where NOT IN 方法
     * @param $column
     * @param null $operator
     * @param null $value
     * @return $this
     */
    public function whereNotIn( $column, $value = null )
    {
        return $this->where( $column, 'NOT IN', $value );
    }

    /**
     * or where NOT IN 方法
     * @param $column
     * @param null $operator
     * @param null $value
     * @return $this
     */
    public function orWhereNotIn( $column, $value = null )
    {
        return $this->orWhere( $column, 'NOT IN', $value );
    }


    /**
     * and where NOT IN 方法
     * @param $column
     * @param null $operator
     * @param null $value
     * @return $this
     */
    public function andWhereNotIn( $column, $value = null )
    {
        return $this->andWhere( $column, 'NOT IN', $value );
    }

    /**
     * where LIKE 方法
     * @param $column
     * @param null $operator
     * @param null $value
     * @return $this
     */
    public function whereLike( $column, $value = null )
    {
        return $this->where( $column, 'LIKE', $value );
    }

    /**
     * or where LIKE 方法
     * @param $column
     * @param null $operator
     * @param null $value
     * @return $this
     */
    public function orWhereLike( $column, $value = null )
    {
        return $this->orWhere( $column, 'LIKE', $value );
    }

    /**
     * and where LIKE 方法
     * @param $column
     * @param null $operator
     * @param null $value
     * @return $this
     */
    public function andWhereLike( $column, $value = null )
    {
        return $this->andWhere( $column, 'LIKE', $value );
    }

    /**
     * where Between 方法
     * @param $column
     * @param null $operator
     * @param null $value
     * @return $this
     */
    public function whereBetween( $column, $value = null )
    {
        return $this->where( $column, 'BETWEEN', $value );
    }

    /**
     * or where Between 方法
     * @param $column
     * @param null $operator
     * @param null $value
     * @return $this
     */
    public function orWhereBetween( $column, $value = null )
    {
        return $this->orWhere( $column, 'BETWEEN', $value );
    }

    /**
     * or where Between 方法
     * @param $column
     * @param null $operator
     * @param null $value
     * @return $this
     */
    public function andWhereBetween( $column, $value = null )
    {
        return $this->andWhere( $column, 'BETWEEN', $value );
    }

    /**
     * 指定NotBetween查询条件
     * @access public
     * @param mixed $field 查询字段
     * @param mixed $condition 查询条件
     * @param string $logic 查询逻辑 and or xor
     * @return $this
     */
    public function whereNotBetween( $column, $value = null )
    {
        return $this->where( $column, 'NOT BETWEEN', $value );
    }


    /**
     * or 指定NotBetween查询条件
     * @access public
     * @param mixed $field 查询字段
     * @param mixed $condition 查询条件
     * @param string $logic 查询逻辑 and or xor
     * @return $this
     */
    public function orWhereNotBetween( $column, $value = null )
    {
        return $this->orWhere( $column, 'NOT BETWEEN', $value );
    }


    /**
     * and 指定NotBetween查询条件
     * @access public
     * @param mixed $field 查询字段
     * @param mixed $condition 查询条件
     * @param string $logic 查询逻辑 and or xor
     * @return $this
     */
    public function andWhereNotBetween( string $field, $condition, string $logic = 'AND' )
    {
        return $this->andWhere( $column, 'NOT BETWEEN', $value );
    }


    /**
     * 指定Null查询条件
     * @access public
     * @param mixed $field 查询字段
     * @param string $logic 查询逻辑 and or xor
     * @return $this
     */
    public function whereNull( $column )
    {
        return $this->where( $column, '=', new Raw( 'NULL' ) );
    }

    /**
     * or指定Null查询条件
     * @access public
     * @param mixed $field 查询字段
     * @param string $logic 查询逻辑 and or xor
     * @return $this
     */
    public function orWhereNull( $column )
    {
        return $this->orWhere( $column, '=', new Raw( 'NULL' ) );
    }

    /**
     * and 指定Null查询条件
     * @access public
     * @param mixed $field 查询字段
     * @param string $logic 查询逻辑 and or xor
     * @return $this
     */
    public function andWhereNull( $column )
    {
        return $this->andWhere( $column, 'is', new Raw( 'NULL' ) );
    }

    /**
     * 指定NotNull查询条件
     * @access public
     * @param mixed $field 查询字段
     * @param string $logic 查询逻辑 and or xor
     * @return $this
     */
    public function whereNotNull( $column )
    {
        return $this->where( $column, 'is', new Raw( 'NOT NULL' ) );
    }

    /**
     * or指定Null查询条件
     * @access public
     * @param mixed $field 查询字段
     * @param string $logic 查询逻辑 and or xor
     * @return $this
     */
    public function orwhereNotNull( $column )
    {
        return $this->orWhere( $column, 'is', new Raw( 'NOT NULL' ) );
    }

    /**
     * and 指定Null查询条件
     * @access public
     * @param mixed $field 查询字段
     * @param string $logic 查询逻辑 and or xor
     * @return $this
     */
    public function andwhereNotNull( $column )
    {
        return $this->andWhere( $column, 'is', new Raw( 'NOT NULL' ) );
    }

    /**
     * 指定Exists查询条件
     * @access public
     * @return $this
     */
    public function whereExists( $value )
    {
        return $this->where( '', 'EXISTS', $value );
    }


    /**
     * 指定Exists查询条件
     * @access public
     * @return $this
     */
    public function orWhereExists( $value )
    {
        return $this->orWhere( '', 'EXISTS', $value );
    }

    /**
     * 指定Exists查询条件
     * @access public
     * @return $this
     */
    public function andWhereExists( $value )
    {
        return $this->andWhere( '', 'EXISTS', $value );
    }

    /**
     * 指定Exists查询条件
     * @access public
     * @return $this
     */
    public function whereNotExists( $value )
    {
        return $this->where( '', 'NOT EXISTS', $value );
    }


    /**
     * 指定Exists查询条件
     * @access public
     * @return $this
     */
    public function orWhereNotExists( $value )
    {
        return $this->orWhere( '', 'NOT EXISTS', $value );
    }

    /**
     * 指定Exists查询条件
     * @access public
     * @return $this
     */
    public function andWhereNotExists( $value )
    {
        return $this->andWhere( '', 'NOT EXISTS', $value );
    }

    /**
     * 指定FIND_IN_SET查询条件
     * @access public
     * @param mixed $field 查询字段
     * @param mixed $condition 查询条件
     * @param string $logic 查询逻辑 and or xor
     * @return $this
     */
    public function whereFindInSet( $column, $value = null )
    {
        return $this->where( $column, 'FIND_IN_SET', $value );
    }

    /**
     * 指定FIND_IN_SET查询条件
     * @access public
     * @param mixed $field 查询字段
     * @param mixed $condition 查询条件
     * @param string $logic 查询逻辑 and or xor
     * @return $this
     */
    public function orWhereFindInSet( $column, $value = null )
    {
        return $this->orWhere( $column, 'FIND_IN_SET', $value );
    }

    /**
     * 指定FIND_IN_SET查询条件
     * @access public
     * @param mixed $field 查询字段
     * @param mixed $condition 查询条件
     * @param string $logic 查询逻辑 and or xor
     * @return $this
     */
    public function andWhereFindInSet( $column, $value = null )
    {
        return $this->andWhere( $column, 'FIND_IN_SET', $value );
    }
}
