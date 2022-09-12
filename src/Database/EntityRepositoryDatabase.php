<?php
/**
 *
 * ============================================================================
 * Author: zhangwentao <wentmac@vip.qq.com>
 * Date: 2020/6/22 11:30 上午
 * Created by TmacPHP Framework <https://github.com/wentmac/TmacPHP>
 * http://www.t-mac.org；
 */

namespace Tmac\Database;


class EntityRepositoryDatabase extends QueryBuilderDatabase
{

    /**
     * entity.
     *
     * @var \Tmac\Database\EntityRepositoryDatabase
     */
    protected $entity;

    /**
     * 所有合法的数据表字段
     * @var array|int[]|string[]
     */
    protected array $entityFields = [];

    public function __construct( DriverDatabase $connection, string $table_name, array $schema, string $primaryKey, string $entity = '' )
    {
        parent::__construct( $connection, $table_name, $schema, $primaryKey );

        $this->entityFields = array_keys( $schema );

        if ( class_exists( $entity ) ) {
            $this->entity = $entity;
        }
    }

}