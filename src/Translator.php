<?php
/**
 * Power By Tmac PHP MVC framework
 * $Author: zhangwentao $  <zwttmac@qq.com>
 * $Id: Config.php 325 2016-05-31 10:07:35Z zhangwentao $
 * http://www.t-mac.org；
 */

namespace Tmac;

use ArrayAccess;
use Tmac\Contract\ConfigInterface;

/**
 * 多语言
 * i18n
 */
class Translator implements ArrayAccess
{
    /**
     * 配置参数
     * @var array
     */
    protected $config = [
        //默认i18的语言环境：中文
        'locale' => 'zh-cn',
        // 回退语言，当默认语言的语言文本没有提供时，就会使用回退语言的对应语言文本
        'fallback_locale' => 'en',
        'common_path' => '',
        'app_path' => '',
        'ext' => '.php'
    ];

    /**
     * 多语言令牌
     * @var array
     */
    protected $lang = [];

    /**
     * 当前语言
     * @var string
     */
    protected $locale = '';

    /**
     * 全局配置文件目录
     * @var string
     */
    protected $language_path = '';
    /**
     * App应用配置文件目录
     * @var string
     */
    protected $app_language_path = '';

    /**
     * 配置文件后缀
     * @var string
     */
    protected $ext = '';

    /**
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * 设置当前语言
     * @param $locale
     * @return $this
     */
    public function setLocale( $locale ): self
    {
        $this->locale = $locale;
        return $this;
    }

    /**
     * 构造方法
     * @access public
     */
    public function __construct( ConfigInterface $config )
    {
        $this->config = array_merge( $this->config, array_change_key_case( $config[ 'translation' ] ) );

        $this->language_path = $this->config[ 'common_path' ];
        $this->app_language_path = $this->config[ 'app_path' ];
        $this->ext = $this->config[ 'ext' ];
        $this->locale = $this->config[ 'locale' ];
    }

    /**
     * 加载配置文件（多种格式）
     * @access public
     * @param string $file 配置文件名
     * @param string $name 一级配置名
     * @return array
     */
    private function load( string $file = '', string $name = '', string $locate = '' ): array
    {
        $locate = $locate ? : $this->locale;
        if ( is_file( $file ) ) {
            $filename = $file;
        } elseif ( is_file( $this->language_path . $locate . DIRECTORY_SEPARATOR . $file . $this->ext ) ) {
            $filename = $this->language_path . $locate . DIRECTORY_SEPARATOR . $file . $this->ext;
        }
        if ( isset( $filename ) ) {
            return $this->parse( $filename, $name, $locate );
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
    protected function parse( string $file, string $name, string $locate ): array
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
        return is_array( $config ) ? $this->set( $config, strtolower( $name ), $locate ) : [];
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
     * 获取语言定义(不区分大小写)
     * @access public
     * @param string|null $name 语言变量
     * @param array $vars 变量替换
     * @param string $locate 语言作用域
     * @return mixed
     */
    public function get( string $name = null, array $vars = [], string $locate = '' )
    {
        $locate = $locate ? : $this->locale;
        if ( empty( $name ) ) {
            return '';
        }

        if ( false === strpos( $name, '.' ) ) {
            //如果没有找到.加载一级配置
            $lang_name = $name;
            $all_lang_status = true;
        } else {
            $name_array = explode( '.', $name );
            $lang_name = strtolower( $name_array[ 0 ] );
            $all_lang_status = false;
        }

        if ( empty( $this->lang[ $locate ][ $lang_name ] ) ) {
            $this->load( $lang_name, $lang_name, $locate );
            // app 项目中的配置合并覆盖公共的配置
            $app_language_file = $this->app_language_path . $locate . DIRECTORY_SEPARATOR . $lang_name . $this->ext;
            if ( is_file( $app_language_file ) ) {
                $this->load( $app_language_file, $lang_name, $locate );
            }
        }

        $lang = $this->lang[ $locate ] ?? [];
        if ( $all_lang_status ) {
            if ( isset( $lang[ $lang_name ] ) ) {
                return $this->parseParams( $name, $lang[ $lang_name ], $vars );
            }
            return $this->parseParams( $name, $lang, $vars );
        }
        // 按.拆分成多维数组进行判断
        foreach ( $name_array as $val ) {
            if ( isset( $lang[ $val ] ) ) {
                $lang = $lang[ $val ];
            } else {
                return $name;
            }
        }
        return $this->parseParams( $name, $lang, $vars );
    }

    // 变量解析
    private function parseParams( $name, $value, array $vars = [] )
    {
        // 如果是数组，说明未达到数组最后一层，返回默认
        if ( is_array( $value ) ) {
            return $name;
        }
        if ( !empty( $vars ) && is_array( $vars ) ) {
            /**
             * Notes:
             * 为了检测的方便，数字索引的判断仅仅是参数数组的第一个元素的key为数字0
             * 数字索引采用的是系统的 sprintf 函数替换，用法请参考 sprintf 函数
             */
            if ( key( $vars ) === 0 ) {
                // 数字索引解析
                /*
                 'file_format'    =>    '文件格式: %s,文件大小：%d',
                 {:lang('file_format',['jpeg,png,gif,jpg','2MB'])}
                */
                array_unshift( $vars, $value );
                $value = call_user_func_array( 'sprintf', $vars );
            } else {
                // 关联索引解析
                /*
                 'file_format'    =>    '文件格式: {:format},文件大小：{:size}',
                {:lang('file_format',['format' => 'jpeg,png,gif,jpg','size' => '2MB'])}
                 */
                $replace = array_keys( $vars );
                foreach ( $replace as &$v ) {
                    $v = "{:{$v}}";
                }
                $value = str_replace( $replace, $vars, $value );
            }
        }

        return $value;
    }

    /**
     * 设置配置参数 name为数组则为批量设置
     * @access public
     * @param array $lang 配置参数
     * @param string $name 配置名
     * @return array
     */
    public function set( array $lang, string $name = null, string $locate = '' ): array
    {
        $locate = $locate ? : $this->locale;
        if ( !empty( $name ) ) {
            if ( isset( $this->lang[ $locate ][ $name ] ) ) {
                $result = array_merge( $this->lang[ $locate ][ $name ], $lang );
            } else {
                $result = $lang;
            }

            $this->lang[ $locate ][ $name ] = $result;
        } else {
            $result = $this->lang[ $locate ] = array_merge( $this->lang[ $locate ], array_change_key_case( $lang ) );
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