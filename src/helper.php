<?php
declare( strict_types=1 );

use Tmac\Translator;
use Tmac\ApplicationContext;
use \Tmac\Contract\ConfigInterface;


if ( !function_exists( 'loadEnv' ) ) {
    function loadEnv()
    {
        //加载env配置文件
        if ( !is_file( PROJECT_BASE_PATH . '.env' ) ) {
            return '';
        }
        $env = parse_ini_file( PROJECT_BASE_PATH . '.env', true );
        foreach ( $env as $key => $val ) {
            $name = strtolower( $key );
            if ( is_array( $val ) ) {
                foreach ( $val as $k => $v ) {
                    $item = $name . '.' . strtolower( $k );
                    putenv( "{$item}={$v}" );
                }
            } else {
                putenv( "{$name}={$val}" );
            }
        }
    }
}

/**
 * 获取环境变量值
 * @access public
 * @param string $name 环境变量名（支持二级 .号分割）
 * @param string $default 默认值
 * @return mixed
 */
if ( !function_exists( 'env' ) ) {
    function env( $key, $default = null )
    {
        $value = getenv( $key );

        if ( $value === false ) {
            return $default;
        }

        return $value;
    }


}

if ( !function_exists( 'trans' ) ) {
    function trans( string $name = null, array $vars = [], ?string $locale = '' )
    {
        /** @var Translator $translator */
        $translator = ApplicationContext::getContainer()->getShared( Translator::class );
        return $translator->get( $name, $vars, $locale );
    }
}

if ( !function_exists( 'config' ) ) {
    function config( string $name = null, $default = null )
    {
        /** @var ConfigInterface $config */
        $config = ApplicationContext::getContainer()[ 'config' ];
        return $config->get( $name, $default );
    }
}