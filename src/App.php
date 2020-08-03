<?php

/**
 * Power By Tmac PHP MVC framework
 * $Author: zhangwentao $  <zwttmac@qq.com>
 * $Id: Tmac.class.php 325 2016-05-31 10:07:35Z zhangwentao $
 * http://www.t-mac.org；
 */

namespace Tmac;

use Exception;
use Tmac\Exception\TmacException;

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

    /**
     * 缓存目录
     * @var string
     */
    protected $var_path = '';

    protected $web_root_path = '';

    protected $web_template_path = '';

    protected $config_path = '';

    /**
     * @return string
     */
    public function getVarPath(): string
    {
        return $this->var_path;
    }


    /**
     * @return string
     */
    public function getWebTemplatePath(): string
    {
        return $this->web_template_path;
    }

    /**
     * @return string
     */
    public function getAppPath(): string
    {
        return $this->app_path;
    }
    
    /**
     * App constructor.
     * @param string $root_path
     * @param string $web_root
     */
    public function __construct( string $root_path = null, string $web_root = null )
    {
        $this->tmac_path = dirname( __DIR__ ) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR;
        $this->root_path = empty( $root_path ) ? $this->getDefaultRootPath() : rtrim( $root_path, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
        $this->app_path = $this->root_path . 'src' . DIRECTORY_SEPARATOR;
        $this->var_path = $this->root_path . 'var' . DIRECTORY_SEPARATOR;
        $this->web_root_path = empty( $web_root ) ? $this->root_path . 'public' . DIRECTORY_SEPARATOR : $web_root . DIRECTORY_SEPARATOR;

        if ( empty( APP_NAME ) ) {
            $web_template_path = '';
        } else {
            $web_template_path = 'Module' . DIRECTORY_SEPARATOR . APP_NAME . DIRECTORY_SEPARATOR;
        }
        $this->web_template_path = $this->app_path . $web_template_path;
        $this->config_path = $this->root_path . 'config' . DIRECTORY_SEPARATOR;

        $this->begin_time = time();
    }

    /**
     * 加载应用文件和配置
     */
    protected function load(): void
    {
        if ( is_file( $this->config_path . 'provider.php' ) ) {
            $this->container->setShared( include $this->config_path . 'provider.php' );
        }
        $config_array = [
            'web_template_path' => $this->web_template_path,
            'var_path' => $this->var_path,
            'now_time' => $this->begin_time
        ];
        $this->container->config->set( $config_array);
    }

    /**
     * 项目启动
     */
    public function run()
    {
        $this->load();
        $this->initialize();
        $this->container->route->init();
    }

    /**
     * 应用初始化
     */
    protected function initialize()
    {
        //设置编码
        header( "Content-type: text/html;charset={$this->container->config['app.charset']}" );
        //设置时区
        date_default_timezone_set( $this->container->config[ 'app.default_timezone' ] );
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
        set_exception_handler( array( $this, 'tmacException' ) );
        //是否自动开启Session 您可以在控制器中初始化，也可以在系统中自动加载
        if ( $this->container->config[ 'app.session.start' ] ) {
            $this->container->session->start();
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
    public final function tmacException( $e )
    {
        if ( $e instanceof TmacException ) {
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
