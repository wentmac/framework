<?php
/**
 *
 * ============================================================================
 * Author: zhangwentao <wentmac@vip.qq.com>
 * Date: 2021/8/30 0:04
 * Created by TmacPHP Framework <https://github.com/wentmac/TmacPHP>
 * http://www.t-mac.org；
 */
declare( strict_types=1 );

namespace Tmac;

class ApplicationContext
{
    /**
     * @var null|Container
     */
    private static $container;

    /**
     * @throws \TypeError
     */
    public static function getContainer(): Container
    {
        return self::$container;
    }

    public static function hasContainer(): bool
    {
        return isset( self::$container );
    }

    public static function setContainer( Container $container ): Container
    {
        self::$container = $container;
        return $container;
    }
}