<?php
declare (strict_types = 1);

namespace Tmac\Contract;

/**
 * 缓存驱动接口
 */
interface  ConfigInterface
{
    public function load( string $file, string $name = '' );
    public function has( string $name );
    public function get( string $name = null, $default = null );
    public function set( array $config, string $name = null );

}
