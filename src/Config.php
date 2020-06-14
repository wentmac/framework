<?php
/**
 * Power By Tmac PHP MVC framework
 * $Author: zhangwentao $  <zwttmac@qq.com>
 * $Id: Config.php 325 2016-05-31 10:07:35Z zhangwentao $
 * http://www.t-mac.org；
 */

namespace Tmac;


use Tmac\Contract\ConfigInterface;
use ArrayAccess;

/**
 * 配置管理类
 * @package think
 */
class Config implements ConfigInterface, ArrayAccess
{
    /**
     * 配置参数
     * @var array
     */
    protected $config = [];

    /**
     * 全局配置文件目录
     * @var string
     */
    protected $common_config_path;

    /**
     * 全局配置文件目录
     * @var string
     */
    protected $app_config_path;

    /**
     * 配置文件后缀
     * @var string
     */
    protected $ext;

    /**
     * 构造方法
     * @access public
     */
    public function __construct( string $root_path = null, string $app_name = null, string $ext = '.php' )
    {
        $root_path = $root_path ? : '';
        $this->common_config_path = $root_path . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR;
        $this->app_config_path = $root_path . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Module' . DIRECTORY_SEPARATOR . $app_name . DIRECTORY_SEPARATOR . 'Config' . DIRECTORY_SEPARATOR;
        $this->ext = $ext;
    }

    /**
     * 加载配置文件（多种格式）
     * @access public
     * @param string $file 配置文件名
     * @param string $name 一级配置名
     * @return array
     */
    public function load( string $file = '', string $name = '' ): array
    {
        if ( is_file( $file ) ) {
            $filename = $file;
        } elseif ( is_file( $this->common_config_path . $file . $this->ext ) ) {
            $filename = $this->common_config_path . $file . $this->ext;
        }
        if ( isset( $filename ) ) {
            return $this->parse( $filename, $name );
        }
        return [];
    }

    /**
     * 解析配置文件
     * @access public
     * @param string $file 配置文件名
     * @param string $name 一级配置名
     * @return array
     */
    protected function parse( string $file, string $name ): array
    {
        $type = pathinfo( $file, PATHINFO_EXTENSION );
        $config = [];
        switch ( $type ) {
            case 'php':
                $config = include $file;
                break;
            case 'yml':
            case 'yaml':
                if ( function_exists( 'yaml_parse_file' ) ) {
                    $config = yaml_parse_file( $file );
                }
                break;
            case 'ini':
                $config = parse_ini_file( $file, true, INI_SCANNER_TYPED ) ? : [];
                break;
            case 'json':
                $config = json_decode( file_get_contents( $file ), true );
                break;
        }
        return is_array( $config ) ? $this->set( $config, strtolower( $name ) ) : [];
    }

    /**
     * 检测配置是否存在
     * @access public
     * @param string $name 配置参数名（支持多级配置 .号分割）
     * @return bool
     */
    public function has( string $name ): bool
    {
        return !is_null( $this->get( $name ) );
    }


    /**
     * 获取配置参数 为空则获取所有配置
     * @access public
     * @param string $name 配置参数名（支持多级配置 .号分割）
     * @param mixed $default 默认值
     * @return mixed
     */
    public function get( string $name = null, $default = null )
    {
        if ( empty( $name ) ) {
            return '';
        }

        if ( false === strpos( $name, '.' ) ) {
            //如果没有找到.加载一级配置
            $config_name = $name;
            $all_config_status = true;
        } else {
            $name = explode( '.', $name );
            $config_name = strtolower( $name[ 0 ] );
            $all_config_status = false;
        }

        if ( empty( $this->config[ $config_name ] ) ) {
            $app_config_file = $this->app_config_path . $config_name . $this->ext;

            $app_config = [];
            if ( is_file( $app_config_file ) ) {
                $this->load( $app_config_file, $config_name );
            }
            $this->load( $config_name, $config_name );
        }
        $config = $this->config;
        if ( $all_config_status ) {
            if ( isset( $this->config[ $config_name ] ) ) {
                return $this->config[ $config_name ];
            }
            return $config;
        }
        // 按.拆分成多维数组进行判断
        foreach ( $name as $val ) {
            if ( isset( $config[ $val ] ) ) {
                $config = $config[ $val ];
            } else {
                return $default;
            }
        }
        return $config;
    }

    /**
     * 设置配置参数 name为数组则为批量设置
     * @access public
     * @param array $config 配置参数
     * @param string $name 配置名
     * @return array
     */
    public function set( array $config, string $name = null ): array
    {
        if ( !empty( $name ) ) {
            if ( isset( $this->config[ $name ] ) ) {
                $result = array_merge( $this->config[ $name ], $config );
            } else {
                $result = $config;
            }

            $this->config[ $name ] = $result;
        } else {
            $result = $this->config = array_merge( $this->config, array_change_key_case( $config ) );
        }

        return $result;
    }

    public function offsetExists( $key )
    {
        return $this->has( $key );
    }

    public function offsetGet( $key )
    {
        return $this->get( $key );
    }

    public function offsetSet( $key, $value )
    {
        $this->set( $value, $key );
    }

    public function offsetUnset( $key )
    {
        return true;
    }
}