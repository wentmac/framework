<?php
/**
 * Created by PhpStorm.
 * User: wentao
 * Date: 2018/11/28
 * Time: 9:49 PM
 */

namespace Tmac;

trait DITrait
{

    /* @var $container Container */
    protected $container;

    public function setDI( Container $container )
    {
        $this->container = $container;
    }


    public function getDI()
    {
        return $this->container;
    }

}