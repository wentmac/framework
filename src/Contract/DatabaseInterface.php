<?php
declare ( strict_types=1 );

namespace Tmac\Contract;

/**
 * 缓存驱动接口
 */
interface  DatabaseInterface
{

    /**
     * 关闭数据库
     *
     * @access public
     * @return boolean
     */
    public function close();



}
