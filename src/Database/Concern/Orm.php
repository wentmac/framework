<?php
declare ( strict_types=1 );

namespace Tmac\Database\Concern;

use Tmac\Exception\DbException;

trait Orm
{

    public function getLastSql()
    {
        return $this->getConn()->getLastSql();
    }

    /**
     * 检测实体类合法性
     * @param $entity
     * @throws DbException
     */
    private function checkEntity( $entity ): void
    {
        if ( !is_object( $entity ) ) {
            throw new DbException( 'save database data must be entity:' . var_export( $entity ) );
        }
        /*
        //php8中可以使用 $entity::class
        $class_name = get_class( $entity );
        if ( $this->getClassName() !== $class_name ) {
            throw new DbException( 'save database data must be entity:' . $this->getClassName() );
        }
        */
    }

    /**
     * 如果实体中包含主键的话，就是使用主键更新
     * @param $entity
     * @return mixed
     * @throws DbException
     */
    public function update( $entity )
    {
        $this->checkEntity( $entity );
        foreach ( $entity as $key => $value ) {
            if ( isset ( $value ) === false ) {//排除掉对象值为空的
                continue;
            }
            $primaryKeyField = $this->getPrimaryKey();
            if ( !empty ( $primaryKeyField ) && $key === $primaryKeyField ) {//排除掉主键更新时的主键字段的误更新,并且把主键当成唯一更新条件
                $this->where( $this->getPrimaryKey(), $value );
                continue;
            }
            $set[] = $key . '=' . $this->parseBuilderDataBind( $key, $value );
        }
        $this->options[ 'data' ] = $set;

        $fetch_sql = $this->getOptions( 'fetch_sql' );

        $sql = $this->getUpdateSql();
        $binds = $this->getBind();

        if ( $fetch_sql === true ) { //返回构建的SQL语句
            return $this->getConn()->getRealSql( $sql, $binds );
        }

        $res = $this->getConn()->execute( $sql, $binds );
        return $res;
    }

    /**
     * INSERT 语法
     * @param $entity
     * @return mixed
     * @throws DbException
     */
    public function insert( $entity )
    {
        $this->checkEntity( $entity );
        foreach ( $entity as $key => $value ) {
            if ( isset ( $value ) === false ) {//排除掉对象值为空的
                continue;
            }
            $primaryKeyField = $this->getPrimaryKey();
            if ( !empty ( $primaryKeyField ) && $key === $primaryKeyField ) {//insert时主键不需要插入
                continue;
            }
            $columns[] = $key;
            $set[] = $this->parseBuilderDataBind( $key, $value );
        }
        $this->options[ 'field' ] = $columns;
        $this->options[ 'data' ] = $set;
        $fetch_sql = $this->getOptions( 'fetch_sql' );

        $sql = $this->getInsertSql();
        $binds = $this->getBind();

        if ( $fetch_sql === true ) { //返回构建的SQL语句
            return $this->getConn()->getRealSql( $sql, $binds );
        }

        $res = $this->getConn()->execute( $sql, $binds );
        if ( $res ) {
            return $this->getConn()->getLastInsID();
        }
        return false;
    }

    /**
     * 批量新增
     * @param array $dataSet
     * @param int $limit
     * @return mixed
     * @throws DbException
     */
    public function insertAll( array $dataSet, int $limit = 0 )
    {
        if ( empty( $dataSet ) || !is_object( reset( $dataSet ) ) ) {
            return 0;
        }
        if ( 0 === $limit && count( $dataSet ) >= 5000 ) {
            $limit = 1000;
        }
        $fetch_sql = $this->getOptions( 'fetch_sql' );
        if ( $limit ) {
            // 分批写入 自动启动事务支持
            $this->getConn()->startTrans();

            try {
                $array = array_chunk( $dataSet, $limit, true );
                $count = 0;
                foreach ( $array as $item ) {
                    $data = $this->handleInsertAllEntity( $item );
                    $sql = $this->getInsertAllSql( $data, $limit );
                    $binds = $this->getBind();
                    if ( $fetch_sql === true ) { //返回构建的SQL语句
                        $sql_array[] = $this->getConn()->getRealSql( $sql, $binds );
                    } else {
                        $count += $this->getConn()->execute( $sql, $binds );
                    }
                }
                // 提交事务
                $this->getConn()->commit();
            } catch ( \Exception | \Throwable $e ) {
                $this->getConn()->rollback();
                throw $e;
            }

            if ( $this->getOptions( 'fetch_sql' ) === true && isset( $sql_array ) && is_array( $sql_array ) ) { //返回构建的SQL语句
                return implode( '|' . $sql_array );
            } else {
                return $count;
            }
        }
        $data = $this->handleInsertAllEntity( $dataSet );

        $sql = $this->getInsertAllSql( $data, $limit );
        $binds = $this->getBind();

        if ( $fetch_sql === true ) { //返回构建的SQL语句
            return $this->getConn()->getRealSql( $sql, $binds );
        }
        $res = $this->getConn()->execute( $sql, $binds );
        return $res;
    }

    /**
     * 把批量insert Object Data 转成 insert Array Data
     * @param array $dataSet
     * @return array
     * @throws DbException
     */
    private function handleInsertAllEntity( array $dataSet ): array
    {
        $return = [];
        foreach ( $dataSet as $key => $entity ) {
            $this->checkEntity( $entity );

            $data = [];
            foreach ( $entity as $key => $value ) {
                if ( isset ( $value ) === false ) {//排除掉对象值为空的
                    continue;
                }
                $primaryKeyField = $this->getPrimaryKey();
                if ( !empty ( $primaryKeyField ) && $key === $primaryKeyField ) {//insert时主键不需要插入
                    continue;
                }
                $data[ $key ] = $this->parseBuilderDataBind( $key, $value );
            }
            $return[] = $data;
        }
        return $return;
    }

    /**
     * 执行删除
     * @return mixed
     */
    public function delete()
    {
        $fetch_sql = $this->getOptions( 'fetch_sql' );

        $sql = $this->getDeleteSql();
        $binds = $this->getBind();

        if ( $fetch_sql === true ) { //返回构建的SQL语句
            return $this->getConn()->getRealSql( $sql, $binds );
        }
        $res = $this->getConn()->execute( $sql, $binds );
        return $res;
    }

    /**
     * 创建子查询SQL
     * @access public
     * @param bool $sub 是否添加括号
     * @return string
     * @throws Exception
     */
    public function buildSql( bool $sub = true ): string
    {
        $sql = $this->getSelectSql();
        $binds = $this->getBind();
        $real_sql = $this->getConn()->getRealSql( $sql, $binds );
        return $sub ? '( ' . $real_sql . ' )' : $real_sql;
    }

    /**
     * Finds an entity by its primary key / identifier.
     *
     * @param mixed $id The identifier.
     * @return object|null The entity instance or NULL if the entity can not be found.
     */
    public function find( $id, bool $master = false )
    {
        if ( empty( $id ) ) {
            throw new DbException( 'method find must need params:id' );
        }
        $this->where( $this->getPrimaryKey(), $id );

        $fetch_sql = $this->getOptions( 'fetch_sql' );

        $sql = $this->getSelectSql();
        $binds = $this->getBind();

        if ( $fetch_sql === true ) { //返回构建的SQL语句
            return $this->getConn()->getRealSql( $sql, $binds );
        }

        $res = $this->getConn()->fetchAssocObject( $sql, $binds, $master );
        return $res;
    }


    public function findColumn( int $column = 0, bool $master = false )
    {
        $fetch_sql = $this->getOptions( 'fetch_sql' );

        $sql = $this->getSelectSql();
        $binds = $this->getBind();

        if ( $fetch_sql === true ) { //返回构建的SQL语句
            return $this->getConn()->getRealSql( $sql, $binds );
        }

        $res = $this->getConn()->fetchColumn( $sql, $binds, $column, $master );
        return $res;
    }

    /**
     * Finds all entities in the repository.
     *
     * @return array The entities.
     */
    public function findAll( bool $master = false )
    {
        $fetch_sql = $this->getOptions( 'fetch_sql' );

        $sql = $this->getSelectSql();
        $binds = $this->getBind();

        if ( $fetch_sql === true ) { //返回构建的SQL语句
            return $this->getConn()->getRealSql( $sql, $binds );
        }

        $res = $this->getConn()->fetchAllObject( $sql, $binds, $master );
        return $res;
    }


    /**
     * Finds a single entity by a set of criteria.
     *
     * @param array $criteria
     * @param array|null $orderBy
     *
     * @return object|null The entity instance or NULL if the entity can not be found.
     */
    public function findOne( bool $master = false )
    {
        $fetch_sql = $this->getOptions( 'fetch_sql' );

        $sql = $this->getSelectSql();
        $binds = $this->getBind();

        if ( $fetch_sql === true ) { //返回构建的SQL语句
            return $this->getConn()->getRealSql( $sql, $binds );
        }
        $res = $this->getConn()->fetchAssocObject( $sql, $binds, $master );
        return $res;
    }


    /**
     * @param string $sql
     * @param array $bind
     * @param bool $master
     * @return mixed
     */
    public function findBySql( string $sql, array $bind = [], bool $master = false )
    {
        $this->whereRaw( $sql, $bind );
        $fetch_sql = $this->getOptions( 'fetch_sql' );

        $sql = $this->getSelectSql();
        $binds = $this->getBind();

        if ( $fetch_sql === true ) { //返回构建的SQL语句
            return $this->getConn()->getRealSql( $sql, $binds );
        }
        $res = $this->getConn()->fetchAllObject( $sql, $binds, $master );
        return $res;
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
        $fetch_sql = $this->getOptions( 'fetch_sql' );

        $sql = $this->getSelectSql();
        $binds = $this->getBind();

        if ( $fetch_sql === true ) { //返回构建的SQL语句
            return $this->getConn()->getRealSql( $sql, $binds );
        }

        $result = $this->getConn()->fetchColumn( $sql, $binds );
        if ( $result == false ) {
            return $default;
        }
        return $result;
    }


}
