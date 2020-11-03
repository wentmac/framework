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

    public function __construct( DriverDatabase $connection, $table_name, $primaryKey )
    {
        parent::__construct( $connection, $table_name, $primaryKey );

    }

    /**
     * getRepository.
     *
     *
     * @return
     */
    public function getRepository( $obj )
    {
        return $this->container->getShared( $obj );
    }

}