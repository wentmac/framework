<?php
declare ( strict_types=1 );

namespace Tmac\Database\Concern;

use Tmac\Database\TmacDbExpr;
use Tmac\Exception\DbException;

trait Orm
{
    /**
     * 如果实体中包含主键的话，就是使用主键更新
     * @param $entity
     * @return mixed
     * @throws DbException
     */
    public function update( $entity )
    {
        if ( !is_object( $entity ) ) {
            throw new DbException( 'update database data must be entity' . var_export( $entity ) );
        }
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
        $sql = $this->getUpdateSql();
        $binds = $this->getBind();
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
        if ( !is_object( $entity ) ) {
            throw new DbException( 'insert database data must be entity' . var_export( $entity ) );
        }
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
        $sql = $this->getInsertSql();
        $binds = $this->getBind();
        $res = $this->getConn()->execute( $sql, $binds );
        return $res;
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
        if ( !is_object( $entity ) ) {
            throw new DbException( 'insert database data must be entity' . var_export( $entity ) );
        }
        $sql = $this->getInsertAllSql( $dataSet, $limit );
        $binds = $this->getBind();
        $res = $this->getConn()->execute( $sql, $binds );
        return $res;
    }

    public function delete()
    {
    }

    /**
     * Finds an entity by its primary key / identifier.
     *
     * @param mixed $id The identifier.
     * @return object|null The entity instance or NULL if the entity can not be found.
     */
    public function find( $id )
    {
        if ( empty( $id ) ) {
            throw new DbException( 'method find must need params:id' );
        }
        $this->where( $this->getPrimaryKey(), $id );
        $sql = $this->getSelectSql();
        $binds = $this->getBind();
        $res = $this->getConn()->fetchAssocObject( $sql, $binds, $this->getClassName() );
        return $res;
    }


    public function findColumn( array $criteria, array $orderBy = null )
    {

    }

    /**
     * Finds all entities in the repository.
     *
     * @return array The entities.
     */
    public function findAll()
    {
        return $this->findBy( [] );
    }

    /**
     * Finds entities by a set of criteria.
     *
     * @param array $criteria
     * @param array|null $orderBy
     * @param int|null $limit
     * @param int|null $offset
     *
     * @return array The objects.
     */
    public function findBy( array $criteria, array $orderBy = null, $limit = null, $offset = null )
    {
        if ( $orderBy !== null ) {
            $this->setOrderBy( $orderBy );
        }
        if ( $limit !== null ) {
            $this->setLimit( $limit );
        }
        if ( $offset !== null ) {
            $this->setOffset( $offset );
        }

        return $this->getListByWhere();
    }


    /**
     * Finds a single entity by a set of criteria.
     *
     * @param array $criteria
     * @param array|null $orderBy
     *
     * @return object|null The entity instance or NULL if the entity can not be found.
     */
    public function findOneBy( array $criteria, array $orderBy = null )
    {
        $persister = $this->_em->getUnitOfWork()->getEntityPersister( $this->_entityName );

        return $persister->load( $criteria, null, null, [], null, 1, $orderBy );
    }

    public function findByNot( array $criteria, array $orderBy = null, $limit = null, $offset = null )
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $expr = $this->getEntityManager()->getExpressionBuilder();

        $qb->select( 'entity' )
            ->from( $this->getEntityName(), 'entity' );

        foreach ( $criteria as $field => $value ) {
            // IF INTEGER neq, IF NOT notLike
            if ( $this->getEntityManager()->getClassMetadata( $this->getEntityName() )->getFieldMapping( $field )[ "type" ] == "integer" ) {
                $qb->andWhere( $expr->neq( 'entity.' . $field, $value ) );
            } else {
                $qb->andWhere( $expr->notLike( 'entity.' . $field, $qb->expr()->literal( $value ) ) );
            }
        }

        if ( $orderBy ) {

            foreach ( $orderBy as $field => $order ) {

                $qb->addOrderBy( 'entity.' . $field, $order );
            }
        }

        if ( $limit )
            $qb->setMaxResults( $limit );

        if ( $offset )
            $qb->setFirstResult( $offset );

        return $qb->getQuery()
            ->getResult();
    }

    public function findByNot1( $field, $value )
    {
        $qb = $this->createQueryBuilder( 'a' );
        $qb->where( $qb->expr()->not( $qb->expr()->eq( 'a.' . $field, '?1' ) ) );
        $qb->setParameter( 1, $value );


        if ( $limit )
            $qb->setMaxResults( $limit );

        if ( $offset )
            $qb->setFirstResult( $offset );

        return $qb->getQuery()
            ->getResult();
    }

    public function findBySql()
    {
    }

}
