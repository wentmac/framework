<?php

/**
 * Power By Tmac PHP MVC framework
 * $Author: zhangwentao $  <zwttmac@qq.com>
 * $Id: HttpResponse.class.php 325 2016-05-31 10:07:35Z zhangwentao $
 * http://www.t-mac.org；
 */

namespace Tmac;

class Response
{
    use DITrait;

    protected $tVar = []; // 模板输出变量
    
    public function __construct()
    {
    }

    /**
     * 模板变量赋值
     * @access protected
     * @param mixed $name
     * @param mixed $value
     */
    public function assign( $name, $value = '' )
    {
        if ( is_array( $name ) ) {
            $this->tVar = array_merge( $this->tVar, $name );
        } elseif ( is_object( $name ) ) {
            foreach ( $name as $key => $val )
                $this->tVar[ $key ] = $val;
        } else {
            $this->tVar[ $name ] = $value;
        }
    }

    /**
     * 显示前台模板
     * @param string $tpl 模板文件名 为空时是 CONTROLLER_ACTION
     * @access public
     * @return void
     */
    public final function V( $tpl = null )
    {
        //设置模板中的全局变量|前台模板目录
        $array = array(
            'action' => $this->container->request->getQuery( 'TMAC_ACTION' ),
            'APP_HOST_URL' => $this->container->config[ 'app.app_host' ],
            'APP_PHP_SELF' => basename( $this->container->request->getServer( 'SCRIPT_NAME' ) ),
            'STATIC_URL' => $this->container->config[ 'app.template.static_url' ],
            'STATIC_COMMON_URL' => $this->container->config[ 'app.template.static_url' ] . 'common/',
            'STATIC_APP_URL' => $this->container->config[ 'app.template.static_url' ] . APP_NAME . '/' . $this->container->config[ 'app.template.template_dir' ] . '/',
        );
        $this->assign( $array );
        $tpl = $this->container->config[ 'app.template.template_dir' ] . DIRECTORY_SEPARATOR . $tpl;
        return $this->view( $tpl, $this->tVar );
    }


    /**
     * @param string|null $view
     * @param array|null $tVar
     */
    protected function view( string $view = null, array $tVar = null )
    {
        $template_app_name = empty( APP_NAME ) ? 'index' : APP_NAME;
        $options = array(
            'template_dir' => $this->container->app->getWebTemplatePath() . $this->container->config[ 'app.template.template' ], //设置系统模板文件的存放目录
            'cache_dir' => $this->container->app->getVarPath() . $this->container->config[ 'app.template.cache_dir' ] . DIRECTORY_SEPARATOR . $template_app_name . DIRECTORY_SEPARATOR, //指定缓存文件存放目录
            'auto_update' => $this->container->config[ 'app.template.auto_update' ], //当模板文件有改动时重新生成缓存 [关闭该项会快一些]
            'cache_lifetime' => $this->container->config[ 'app.template.cache_lifetime' ], //缓存生命周期(分钟)，为 0 表示永久 [设置为 0 会快一些]
            'suffix' => $this->container->config[ 'app.template.suffix' ], //模板后缀
            'cache_open' => $this->container->config[ 'app.template.cache_open' ], //是否开启缓存，程序调试时使用
            'value' => $tVar    //压到模板里的变量数据
        );
        $tmac_template_update_cache = $this->container->request->getQuery( 'tmac_template_update_cache', 0 );
        if ( $tmac_template_update_cache ) {
            $options[ 'auto_update' ] = true;//当模板文件有改动时重新生成缓存（适用于关闭主动更新时用于手动更新模板缓存）
        }
        $tpl = $this->container->template;
        $tpl->setOptions( $options ); //设置模板参数
        //如果是前台的模板就不用前缀（模板目录名）
        if ( $view == $this->container->config[ 'app.template.template_dir' ] . DIRECTORY_SEPARATOR ) {
            //如果模板路径，文件名为空的话就尝试把TMAC_CONTROLLER_FILE当作模板路径，文件名
            $view_new = strtolower( $this->container->request->getQuery( 'TMAC_CONTROLLER_FILE' ) . $this->container->request->getQuery( 'TMAC_CONTROLLER_NAME' ) );  //都转成小写的
            $view = $view . $view_new;  //前面加上模板目录名
        }
        $tpl->show( $view );
    }


    /**
     * 显示原生前台模板
     * 主要是用来配置$this->assign();变量赋值使
     * @param string $tpl 模板文件名 为空时是 CONTROLLER_ACTION
     * @access public
     * @return void
     */
    public function view_php( $view )
    {
        //设置模板中的全局变量|前台模板目录
        $array = array(
            'STATIC_URL' => $this->container->config[ 'app.template.static_url' ],
            'STATIC_COMMON_URL' => $this->container->config[ 'app.template.static_url' ] . 'common/',
            'STATIC_APP_URL' => $this->container->config[ 'app.template.static_url' ] . $this->container->config[ 'app.template.template_dir' ] . '/',
        );
        $this->assign( $array );
        $this->display( $view, $this->tVar );
    }


    /**
     * 原生PHP输出模板
     * @param string $view 模板路径以及文件名 可以用.作为目录分割
     */
    public final function display( $view, $tVar = null )
    {
        $file = $this->container->app->getWebTemplatePath() . $this->container->config[ 'app.template.template' ]
            . DIRECTORY_SEPARATOR . $this->container->config[ 'app.template.template_dir' ]
            . DIRECTORY_SEPARATOR . $view
            . $this->container->config[ 'app.template.suffix' ];
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
    public final function loadView( $view )
    {
        $file = $this->container->app->getWebTemplatePath() . $this->container->config[ 'app.template.template' ]
            . DIRECTORY_SEPARATOR . $this->container->config[ 'app.template.template_dir' ]
            . DIRECTORY_SEPARATOR . $view
            . $this->container->config[ 'app.template.suffix' ];
        return $file;
    }

    /**
     * Api 返回值函数
     * @param type $data
     * @param type $debug
     * @param type $format
     */
    public function apiReturn( $data = array(), $debug = 0, $format = 'json' )
    {
        $return = array(
            'status' => 0,
            'success' => true,
            'data' => $data
        );
        if ( $debug == 1 ) {
            header( "Content-type: text/html; charset=utf-8" );
            echo '<pre>';
            print_r( $return );
            echo '</pre>';
        } else {
            if ( $format == 'json' ) {
                header( "Content-type: application/json; charset=utf-8" );
                echo json_encode( $return, JSON_UNESCAPED_UNICODE );
                exit;
            }
        }
    }
}