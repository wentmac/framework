<?php

/**
 * Power By Tmac PHP MVC framework
 * $Author: zhangwentao $  <zwttmac@qq.com>
 * $Id: TmacException.class.php 325 2016-05-31 10:07:35Z zhangwentao $
 * http://www.t-mac.org； 
 */
namespace Tmac\Exception;

use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;
use Throwable;

class ClassNotFoundException extends RuntimeException implements NotFoundExceptionInterface
{
    protected $class;

    public function __construct(string $message, string $class = '', Throwable $previous = null)
    {
        $this->message = $message;
        $this->class   = $class;

        parent::__construct($message, 0, $previous);
    }

    /**
     * 获取类名
     * @access public
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }
}
