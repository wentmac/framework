<?php
declare ( strict_types=1 );

namespace Tmac\Database\Concern;

use Tmac\Database\QueryBuilderDatabase;
use Tmac\Database\Raw;
use Exception;
use Closure;
use PDO;

trait Builder
{
    /**
     * 生成查询sql语句
     * @return mixed
     */
    public function getSelectSql()
    {
        $options = $this->parseOptions();
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
     * 闭包查询
     * @access protected
     * @param Query $query 查询对象
     * @param Closure $value 查询条件
     * @return string
     */
    protected function parseClosureWhere( QueryBuilderDatabase $query, Closure $value )
    {
        $query->subQuery = true;
        $query->aliasMap = $this->aliasMap;
        $query->options['join'] = $this->getOptions('join');
        $query->options['alias'] = $this->getOptions('alias');
        $value( $query );
        //print_r( $query->options );
        //$whereClosure = $this->parseWhere( $query->getOptions( 'where' ) ? : [] );
        //$whereClosure = $query->parseWhere( $query->getOptions( 'where' ) ? : [] );
        if ( $query->getTable() == $this->getTable() ) {
            //同一张表使用 的是闭包带括号的复杂查询，不需要在子查询中带select * form table where
            $whereClosure = $query->parseWhere( $query->getOptions( 'where' ) );
        } else {
            $whereClosure = $query->getSelectSql();
        }
        $this->bind( $query->getBind( false ) );
        $where = '( ' . $whereClosure . ' )';
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
            $joinStr .= $type . ' JOIN ' . $table . ' ON ' . $condition;
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
     * 解析 where
     * @param $where
     * @return string
     */
    protected function buildWhere( array $where ): string
    {
        $whereStr = $this->parseWhere( $where );
        return empty( $whereStr ) ? '' : 'WHERE' . $whereStr;
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
            } elseif ( $u instanceof Raw ) {
                $sql[] = $type . ' ' . $u->getValue();
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

        /*
         if ( $type == 'sql' && $value[ 'value' ] instanceof Raw ) {
            $where = " {$logic} " . $value[ 'value' ]->getValue();
        } elseif ( $type == 'raw' && $value[ 'value' ] instanceof Raw ) {
            $where = $value[ 'value' ]->getValue();
        }
         */
        if ( in_array( $type, [ 'sql', 'raw' ] ) && $value[ 'value' ] instanceof Raw ) {
            $where = " {$logic} " . $value[ 'value' ]->getValue();
        } elseif ( true === $value[ 'value' ] ) {
            $where = ' ' . $logic . ' 1 ';
        } elseif ( $value[ 'value' ] instanceof Closure ) {
            // 使用闭包查询
            $whereClosureStr = $this->parseClosureWhere( $this->newQuery(), $value[ 'value' ] );
            if ( $whereClosureStr ) {
                //取了别名后的
                $column = $this->parseKey( $value[ 'column' ] );
                $where = " {$logic} {$column} {$value['operator']} " . $whereClosureStr;
            }
        } elseif ( is_array( $value ) ) {
            if ( key( $value ) === 0 ) {
                throw new Exception( 'where express error:' . var_export( $value, true ) );
            }

            //解析where in notIn
            if ( in_array( $value[ 'operator' ], [ 'IN', 'NOT IN' ] ) ) {
                return $this->parseWhereSubExpLogic( $value, $logic );
            }
            //解析where exists notExists
            if ( in_array( $value[ 'operator' ], [ 'EXISTS', 'NOT EXISTS' ] ) ) {
                return $this->parseWhereExistsLogic( $value, $logic );
            }
            //解析where between
            if ( in_array( $value[ 'operator' ], [ 'BETWEEN', 'NOT BETWEEN' ] ) ) {
                return $this->parseWhereBetweenLogic( $value, $logic );
            }

            //解析where find_in_set
            if ( $value[ 'operator' ] === 'FIND_IN_SET' ) {
                return $this->parseWhereFindInSetLogic( $value, $logic );
            }
            //取了别名后的
            $column = $this->parseKey( $value[ 'column' ] );
            $where = " {$logic} {$column} {$value['operator']} ";


            if ( $value[ 'value' ] instanceof Raw ) {
                //比如NULL NOT NULL不需要进行pdo bindValue
                $where .= $value[ 'value' ]->getValue();
            } else {
                //进行数据bindValue
                $where .= $this->parseBuilderDataBind( $value[ 'column' ], $value[ 'value' ] );
            }

        }

        return $where;
    }

    /**
     * 解析where FindInSet 等的语句bind方法
     * @param $value
     */
    private function parseWhereFindInSetLogic( $where, $logic ): string
    {
        //取了别名后的
        $column = $this->parseKey( $where[ 'column' ] );
        $operator = $where[ 'operator' ];
        $value = $where[ 'value' ];
        $where_str = " {$logic} {$operator} ";
        if ( $value instanceof Raw ) {
            $where_str .= '(' . $value->getValue() . ',' . $column . ')';
        } else {
            //进行数据bindValue
            $where_str .= '(' . $this->parseBuilderDataBind( $where[ 'column' ], $value ) . ',' . $column . ')';
        }
        return $where_str;
    }


    /**
     * @param $where
     * @param $logic
     * @return string
     */
    private function parseWhereBetweenLogic( $where, $logic ): string
    {
        $column = $this->parseKey( $where[ 'column' ] );
        $operator = $where[ 'operator' ];
        $value = $where[ 'value' ];
        $where_str = " {$logic} {$column} {$operator} ";
        if ( is_array( $value ) && isset( $value[ 0 ] ) && isset( $value[ 1 ] ) ) {
            $start_value = $value[ 0 ];
            $end_value = $value[ 1 ];
            $start = $this->parseBuilderDataBind( $where[ 'column' ], $start_value );
            $end = $this->parseBuilderDataBind( $where[ 'column' ], $end_value );
            $where_str .= $start . ' AND ' . $end;
        } elseif ( $value instanceof Raw ) {
            $where_str .= $value->getValue();
        }
        return $where_str;
    }

    /**
     * @param $where
     * @param $logic
     * @return string
     */
    private function parseWhereExistsLogic( $where, $logic ): string
    {
        //取了别名后的
        $operator = $where[ 'operator' ];
        $value = $where[ 'value' ];
        $where_str = " {$logic} {$operator} ";
        if ( $value instanceof Raw ) {
            $where_str .= '(' . $value->getValue() . ')';
        } elseif ( $value instanceof Closure ) {
            // 使用闭包查询
            $whereClosureStr = $this->parseClosureWhere( $this->newQuery(), $value );
            $where_str .= '(' . $whereClosureStr . ')';
        } else {
            //进行数据bindValue
            $where_str .= '(' . $this->parseBuilderDataBind( $where[ 'column' ], $value ) . ')';
        }
        return $where_str;
    }


    /**
     * 解析where In exists 等的语句bind方法
     * @param $value
     */
    private function parseWhereSubExpLogic( $where, $logic ): string
    {
        //取了别名后的
        $column = $this->parseKey( $where[ 'column' ] );
        $operator = $where[ 'operator' ];
        $value = $where[ 'value' ];
        $where_str = " {$logic} {$column} {$operator} ";
        if ( is_array( $value ) ) {
            $bind_array = [];
            foreach ( $value as $val ) {
                $bind_array[] = $this->parseBuilderDataBind( $where[ 'column' ], $val );
            }
            $where_str .= '(' . implode( ',', $bind_array ) . ')';
        } elseif ( $value instanceof Raw ) {
            $where_str .= $value->getValue();
        } else {
            //进行数据bindValue
            $where_str .= '(' . $this->parseBuilderDataBind( $where[ 'column' ], $value ) . ')';
        }
        return $where_str;
    }

    /**
     * 解析正确的key名。主要是为了alias
     * @param $key
     * @return string
     */
    private function parseKey( $key )
    {
        //不需要取别名
        return $key;
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
        if ( $value instanceof Raw ) {
            return $value->getValue();
        }

        //$join_option = $this->getOptions('join');
        $schema = $this->schema;
        $schema_key = $key;
        $bind_name_key = $key;
        if ( !empty( $this->getOptions( 'join' ) ) && strpos( $key, '.' ) !== false ) {
            //join配置存在 并且 字段中存在.的。说明是join方法的
            //parse_key return ['a', 'is_delete']
            $parse_key = explode( '.', $key );

            $schema = $this->aliasMap[ $parse_key[ 0 ] ] ?? $this->schema; //别名表 实体类的 schema  article_id所有的article repo的schema
            $schema_key = $parse_key[ 1 ];//真实字段名，去掉别名的 比如 article_id

            $bind_name_key = $parse_key[ 0 ] . '_' . $parse_key[ 1 ];
        } else if ( $this->subQuery === true && !empty( $this->getOptions( 'alias' ) ) && strpos( $key, '.' ) !== false ) {
            $parse_key = explode( '.', $key );

            $schema = $this->aliasMap[ $parse_key[ 0 ] ] ?? $this->schema; //别名表 实体类的 schema  article_id所有的article repo的schema
            $schema_key = $parse_key[ 1 ];//真实字段名，去掉别名的 比如 article_id

            $bind_name_key = $parse_key[ 0 ] . '_' . $parse_key[ 1 ];
        }

        $name = $this->generateBindName( $bind_name_key );
        //直接从Repository中取字段的schema的类型。减少很个字段的判断
        if ( is_null( $value ) ) {
            $type = PDO::PARAM_NULL;
        } else if ( isset( $schema[ $schema_key ] ) ) {
            $type = $schema[ $schema_key ];
        } else {
            $type = null;
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
