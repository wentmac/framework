<?php

/**
 * Power By Tmac PHP MVC framework
 * $Author: zhangwentao $  <zwttmac@qq.com>
 * $Id: TmacClassException.class.php 325 2016-05-31 10:07:35Z zhangwentao $
 * http://www.t-mac.org；
 */
namespace Tmac\Exception;

use Exception;

class TmacClassException extends Exception
{

    /**
     * 构造器
     *
     * @param string $message
     * @param int $code
     * @access public
     */
    public function __construct( $message = 'Unknown Error', $code = -1 )
    {
        parent::__construct( $message, $code );

    }

}

?>
