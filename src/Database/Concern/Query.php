<?php
declare ( strict_types=1 );

namespace Tmac\Database\Concern;

use Tmac\Database\TmacDbExpr;

trait Query
{

    //todo 如果实体中包含主键的话，就是使用主键更新
    public function update( $entity )
    {
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
