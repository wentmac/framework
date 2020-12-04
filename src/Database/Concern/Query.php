<?php
declare ( strict_types=1 );

namespace Tmac\Database\Concern;

use Tmac\Database\TmacDbExpr;
use Tmac\Exception\DbException;

trait Query
{

    //todo 如果实体中包含主键的话，就是使用主键更新
    public function update( $entity )
    {
        if ( !is_object( $entity ) ) {
            throw new DbException( 'update database data must be entity' . var_export( $entity ) );
        }
        foreach ( $entity as $key => $value ) {
            if ( isset ( $value ) === false ) {//排除掉对象值为空的
                continue;
            }
            if ( !empty ( $primaryKeyField ) && $key === $primaryKeyField ) {//排除掉主键更新时的主键字段的误更新
                continue;
            }
            if ( $value instanceof TmacDbExpr ) {
                $set[] = $columnName . '=' . $value;
            } else {
                $set[] = $columnName . '=' . $this->parseDataBind( $columnName );
            }
        }
    }

    public function insert( $entity )
    {
    }

    public function insertAll( array $dataSet, int $limit = 0 )
    {
    }

    public function delete()
    {
    }

}
