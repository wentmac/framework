<?php

/**
 * Power By Tmac PHP MVC framework
 * $Author: zhangwentao $  <zwttmac@qq.com>
 * $Id: Tmac.class.php 325 2016-05-31 10:07:35Z zhangwentao $
 * http://www.t-mac.org；
 */

namespace Tmac;

class App
{

    use DITrait;

    protected $begin_time;

    /**
     * 应用根目录
     * @var
     */
    protected $root_path = '';
    /**
     * 框架目录
     * @var string
     */
    protected $tmac_path = '';

    /**
     * 应用业务代码目录
     * @var string
     */
    protected $app_path = '';

    protected $web_root_path = '';
    protected $config_path = '';

    /**
     * Model的instance数组
     * @var array
     */
    protected static $model = array();
    protected static $plugin = array();
    protected static $config = array();


    /**
     * App constructor.
     * @param string|string $root_path
     */
    public function __construct( string $root_path = '', string $web_root = '' )
    {
        $this->tmac_path = dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;
        $this->root_path = empty( $root_path ) ? $this->getDefaultRootPath() : rtrim( $root_path, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
        $this->app_path = $this->root_path . 'src' . DIRECTORY_SEPARATOR;
        $this->web_root_path = $web_root . 'src' . DIRECTORY_SEPARATOR;

        $this->config_path = $this->root_path . 'config' . DIRECTORY_SEPARATOR;
    }

    /**
     * 加载应用文件和配置
     */
    protected function load(): void
    {
        if ( is_file( $this->config_path . 'provider.php' ) ) {
            $this->container->setShared( include $this->config_path . 'provider.php' );
        }
    }

    /**
     * 项目启动
     */
    public function run()
    {
        $this->load();
        $this->initialize();
        $this->container->controller->init();
    }

    /**
     * 应用初始化
     */
    protected function initialize()
    {
        //设置编码
        header( "Content-type: text/html;charset={$this->container->config['app.charset']}" );
        //设置时区
        @date_default_timezone_set( $this->container->config[ 'app.default_timezone' ] );
        //生成htaccess文件
        $htaccess = $this->web_root_path . '.htaccess';
        if ( $this->container->config[ 'app.url_rewrite' ] ) {
            if ( is_file( $htaccess ) ) {
                if ( filesize( $htaccess ) > 132 ) {
                    //如果程序无法删除就需要手动删除
                    unlink( $htaccess );
                    file_put_contents( $htaccess, "RewriteEngine on\r\nRewriteBase " . ROOT . "\r\nRewriteCond %{SCRIPT_FILENAME} !-f\r\nRewriteCond %{SCRIPT_FILENAME} !-d\r\nRewriteRule ^.*$ index.php", LOCK_EX );
                }
            }
        }
        //不进行魔术过滤 php5.3废除了 set_magic_quotes_runtime(0);
        ini_set( "magic_quotes_runtime", 0 );
        //开启页面压缩
        if ( $this->container->config[ 'app.gzip' ] ) {
            function_exists( 'ob_gzhandler' ) ? ob_start( 'ob_gzhandler' ) : ob_start();
        } else {
            ob_start();
        }
        //页面报错
        $this->container->config[ 'app.error_report' ] ? error_reporting( E_ALL ) : error_reporting( 0 );
        //控制异常
        set_exception_handler( array( $this, 'exception' ) );
        //是否自动开启Session 您可以在控制器中初始化，也可以在系统中自动加载
        if ( $this->container->config[ 'app.session.start' ] ) {
            $this->container->session->init();
        }
    }

    /**
     * 载入Model
     * @param string $model Model名 类名必须与文件名一致 "/"作为目录分隔符
     * @param string $app_name 项目名（如果需要跨项目调用）
     * @param string $ext 后缀名
     * @return object
     * @access public
     * @static
     */
    public final static function model( $model, $app_name = APP_NAME, $ext = '.class.php' )
    {
        $modelName = $model . '_' . $app_name;
        //判断是否已经创建过此Model
        if ( !array_key_exists( $modelName, self::$model ) ) {
            //不存在
            $file = APPS_PATH . $app_name . DIRECTORY_SEPARATOR . APPLICATION . DIRECTORY_SEPARATOR . 'Model' . DIRECTORY_SEPARATOR . 'service' . DIRECTORY_SEPARATOR . $model . $ext;
            if ( is_file( $file ) ) {
                include( $file );
                if ( strpos( $model, '/' ) === false ) {
                    $className = $model . '_' . $app_name;
                } else {
                    $className = str_replace( '/', '_', $model ) . '_' . $app_name;
                }
                $className = 'service_' . $className; //框架三层架构默认Tmac::model('article',APP_ADMIN_NAME);调用Model/service/article.class.php
                $m = new $className();
                //执行_init方法
                in_array( '_init', get_class_methods( $className ) ) && $m->_init();
                //储存到model数组中 下次调用不再new
                self::$model[ $modelName ] = $m;
                return $m;
            } else {
                throw new TmacException ( '找不到Model文件:' . $file );
            }
        } else {
            in_array( '_init', get_class_methods( self::$model[ $modelName ] ) ) && self::$model[ $modelName ]->_init();
            return self::$model[ $modelName ];
        }
    }

    /**
     * 载入插件
     * @param string $plugin 插件名 插件类名必须与文件名一致 "."作为目录分隔符
     * @param string $app_name 项目名（如果需要跨项目调用）
     * @param array $param 插件参数
     * @param string $ext 插件后缀名
     * @return object
     * @access public
     * @static
     */
    public final static function plugin( $plugin, $app_name = null, $param = array(), $ext = '.class.php' )
    {
        //判断是否已经创建过此$plugin
        if ( !array_key_exists( $plugin, self::$plugin ) ) {
            if ( empty ( $app_name ) ) {
                $file = TMAC_PATH . 'Plugin' . DIRECTORY_SEPARATOR . $plugin . $ext;
            } else {
                $file = APPS_PATH . $app_name . DIRECTORY_SEPARATOR . APPLICATION . DIRECTORY_SEPARATOR . 'Plugin' . DIRECTORY_SEPARATOR . $plugin . $ext;
            }
            if ( !is_file( $file ) ) {
                throw new TmacException ( '找不到Plugin文件:' . $file );
            }
            include( $file );
            $pluginName = basename( $plugin );
            $p = empty ( $param ) ? new $pluginName() : new $pluginName ( $param );
            //储存到$plugin数组中 下次调用不再new
            self::$plugin[ $plugin ] = $p;
            return $p;
        } else {
            //存在
            return self::$plugin[ $plugin ];
        }
    }

    /**
     * 输出模板
     * @param string $view 模板路径以及文件名 可以用.作为目录分割
     */
    public final static function view( $view = null, $tVar = null )
    {
        global $TmacConfig;
        $options = array(
            'template_dir' => APPLICATION_ROOT . $TmacConfig[ 'Template' ][ 'template' ], //设置系统模板文件的存放目录
            'cache_dir' => VAR_ROOT . $TmacConfig[ 'Template' ][ 'cache_dir' ], //指定缓存文件存放目录
            'auto_update' => $TmacConfig[ 'Template' ][ 'auto_update' ], //当模板文件有改动时重新生成缓存 [关闭该项会快一些]
            'cache_lifetime' => $TmacConfig[ 'Template' ][ 'cache_lifetime' ], //缓存生命周期(分钟)，为 0 表示永久 [设置为 0 会快一些]
            'suffix' => $TmacConfig[ 'Template' ][ 'suffix' ], //模板后缀
            'cache_open' => $TmacConfig[ 'Template' ][ 'cache_open' ], //是否开启缓存，程序调试时使用
            'value' => $tVar    //压到模板里的变量数据                               
        );
        $tmac_template_update_cache = Input::get( 'tmac_template_update_cache', 0 )->int();
        if ( $tmac_template_update_cache ) {
            $options[ 'auto_update' ] = true;//当模板文件有改动时重新生成缓存（适用于关闭主动更新时用于手动更新模板缓存）
        }

        $tpl = Template::getInstance();
        $tpl->setOptions( $options ); //设置模板参数
        //如果是前台的模板就不用前缀（模板目录名）        
        if ( $view == $TmacConfig[ 'Template' ][ 'template_dir' ] . DIRECTORY_SEPARATOR ) {
            //如果模板路径，文件名为空的话就尝试把TMAC_CONTROLLER_FILE当作模板路径，文件名        
            $view_new = strtolower( $_GET[ 'TMAC_CONTROLLER_FILE' ] );  //都转成小写的
            $view = $view . $view_new;  //前面加上模板目录名
        }
        $tpl->show( $view );
    }

    /**
     * 原生PHP输出模板
     * @param string $view 模板路径以及文件名 可以用.作为目录分割
     */
    public final static function display( $view, $tVar = null )
    {
        $file = APPLICATION_ROOT . $GLOBALS[ 'TmacConfig' ][ 'Template' ][ 'template' ]
            . DIRECTORY_SEPARATOR . $GLOBALS[ 'TmacConfig' ][ 'Template' ][ 'template_dir' ]
            . DIRECTORY_SEPARATOR . $view
            . $GLOBALS[ 'TmacConfig' ][ 'Template' ][ 'suffix' ];
        if ( !empty ( $tVar ) ) {
            extract( $tVar, EXTR_OVERWRITE );
        }
        include $file;
    }

    /**
     * 在原生PHP模板中include其他模板
     * include Tmac::loadView ( 'inc/header' );
     * @param type $view
     */
    public final static function loadView( $view )
    {
        $file = APPLICATION_ROOT . $GLOBALS[ 'TmacConfig' ][ 'Template' ][ 'template' ]
            . DIRECTORY_SEPARATOR . $GLOBALS[ 'TmacConfig' ][ 'Template' ][ 'template_dir' ]
            . DIRECTORY_SEPARATOR . $view
            . $GLOBALS[ 'TmacConfig' ][ 'Template' ][ 'suffix' ];
        return $file;
    }

    /**
     * Searches for a file in the [Cascading Filesystem](Application)
     * @param string $file 文件路径
     * @return void
     * @access public
     * @static
     */
    public final static function findFile( $file, $app_name = APP_NAME, $ext = '.class.php' )
    {
        $filePath = APPS_PATH . $app_name . DIRECTORY_SEPARATOR . APPLICATION . DIRECTORY_SEPARATOR . 'Plugin' . DIRECTORY_SEPARATOR . $file . $ext;
        if ( is_file( $filePath ) ) {
            return $filePath;
        } else {
            throw new TmacException ( '找不到' . APP_NAME . '核心包中的Plugin文件:' . $filePath );
        }
    }

    /**
     * 获取File缓存实例
     * 使用方法 $cat_list = Tmac::getCache('cat_list', array($this->category_model, 'getCategoryList'), array(0), 86400);
     * @param type $variable
     * @param type $function_array
     * @param type $expire
     * @return type
     */
    public final static function getCache( $variable, $callback, $params = array(), $expire = 60 )
    {
        $cache = CacheDriver::getInstance();
        $cache->setMd5Key( false );
        $value = $cache->get( $variable );
        if ( $value === false || $expire == 0 ) {
            $value = call_user_func_array( $callback, $params );
            $cache->set( $variable, $value, $expire );
        }
        return $value;
    }

    /**
     * 获取Memcache缓存实例
     * 使用方法 $cat_list = Tmac::getMemcache('cat_list', array($this->category_model, 'getCategoryList'), array(0), 86400);
     * @param type $variable
     * @param type $function_array
     * @param type $expire
     * @return type
     */
    public final static function getMemcache( $variable, $callback, $params = array(), $expire = 60 )
    {
        $cache = CacheDriver::getInstance( 'Memcached' );
        $value = $cache->get( $variable );
        if ( $value === false || $expire == 0 ) {
            $value = call_user_func_array( $callback, $params );
            $cache->set( $variable, $value, $expire );
        }
        return $value;
    }

    /**
     * 控制异常的回调函数 回调函数必须为public
     * @param object $e
     * @access public
     */
    public final function exception( $e )
    {
        if ( $e instanceof Exception ) {
            $e->getError();
        } else {
            echo "Error code: " . $e->getCode() . '<br>';
            echo "Error message: " . $e->getMessage() . '<br>';
            echo "Error file: " . $e->getFile() . '<br>';
            echo "Error fileline: " . $e->getLine() . '<br>';
        }

    }

    /**
     * 获取应用根目录
     * @access protected
     * @return string
     */
    protected function getDefaultRootPath(): string
    {
        //$path = dirname( dirname( dirname( dirname( $this->tmac_path ) ) ) );
        $path = substr( dirname( $this->tmac_path ), 0, -25 ); //上级目录
        return $path . DIRECTORY_SEPARATOR;
    }

}
