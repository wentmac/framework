<?php
declare ( strict_types=1 );

namespace Tmac\Database\Concern;

use Tmac\Database\TmacDbExpr;

trait Orm
{
    /**
     * Finds an entity by its primary key / identifier.
     *
     * @param mixed $id The identifier.
     * @return object|null The entity instance or NULL if the entity can not be found.
     */
    public function find( $id )
    {
        if ( !empty( $id ) ) {
            $this->pk = $id;
        }
        $this->removeOption();
        $this->where( $this->getPrimaryKey(), $this->pk );
        $sql = $this->build();
        $res = $this->getConn()->getRowObject( $sql );
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
