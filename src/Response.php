<?php

/**
 * Power By Tmac PHP MVC framework
 * $Author: zhangwentao $  <zwttmac@qq.com>
 * $Id: HttpResponse.class.php 325 2016-05-31 10:07:35Z zhangwentao $
 * http://www.t-mac.org；
 */

namespace Tmac;

use Tmac\Contract\ConfigInterface;

class Response
{
    use DITrait;

    protected $tVar = []; // 模板输出变量
    protected $app;
    protected $config;
    protected $request;

    //自定义模板风格
    protected $template_style = '';

    public function __construct( ConfigInterface $config, App $app, Request $request )
    {
        $this->config = $config;
        $this->app = $app;
        $this->request = $request;
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
     * 返回模板变量数组
     * @return array
     */
    private function getTplVarArray()
    {
        return $array = [
            'action' => $this->request->getQuery( 'TMAC_ACTION' ),
            'APP_HOST_URL' => $this->config[ 'app.app_host' ],
            'APP_PHP_SELF' => basename( $this->request->getServer( 'SCRIPT_NAME' ) ),
            'STATIC_URL' => $this->config[ 'app.static_url' ],
            'STATIC_COMMON_URL' => $this->config[ 'app.static_url' ] . 'common/',
            'STATIC_APP_URL' => $this->config[ 'app.static_url' ] . APP_NAME . '/' . $this->config[ 'app.template.template_dir' ] . '/',
        ];
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
        $this->assign( $this->getTplVarArray() );
        //$this->template_style = 'blue';//自定义指定加载blue风格模板
        return $this->view( $tpl, $this->tVar );
    }


    /**
     * @param string|null $view
     * @param array|null $tVar
     */
    protected function view( string $view = null, array $tVar = null )
    {
        $template_app_name = empty( APP_NAME ) ? 'index' : APP_NAME;

        $template = $this->app->getWebTemplatePath() . $this->config[ 'app.template.template' ] . DIRECTORY_SEPARATOR;
        $template_style = empty( $this->template_style ) ? $this->config[ 'app.template.template_style' ] : $this->template_style;
        $options = array(
            'template' => $template, //设置系统模板文件的存放目录
            'template_style' => $template_style,
            'template_dir' => $template . $template_style . DIRECTORY_SEPARATOR,
            'cache_dir' => $this->app->getVarPath() . $this->config[ 'app.template.cache_dir' ] . DIRECTORY_SEPARATOR . $template_app_name . DIRECTORY_SEPARATOR . $template_style . DIRECTORY_SEPARATOR, //指定缓存文件存放目录
            /**
             * 当模板文件有改动时重新生成缓存 关闭该项会快一些 开发环境打开、生产环境建议关闭 上线模板后删除一下缓存
             * 生产环境打开的情况下，会调用check方法检查模板过期
             *
             * true:  模板缓存文件中的check方法会调用 如果模板文件有变动会重新生成模板缓存文件
             *
             * false: 模板缓存文件中的check方法不会调用 每次加载模板缓存时会跳过模板文件的变动检查，性能更高。
             *        适合在生产环境，此设置后模板变动，上线后清空一下模板缓存
             */
            'auto_update' => $this->config[ 'app.template.auto_update' ], //当模板文件有改动时重新生成缓存 [关闭该项会快一些]
            'cache_lifetime' => $this->config[ 'app.template.cache_lifetime' ], //缓存生命周期(分钟)，为 0 表示永久 [设置为 0 会快一些]
            'suffix' => $this->config[ 'app.template.suffix' ], //模板后缀
            /**
             * true: 加载模板时，会判断模板缓存文件是否存在。
             *                    不存在就生存模板缓存文件
             *                    存在就加载模板缓存文件（再根据auto_update状态是否需要check检查模板是否有更新）
             *
             * false: 加载模板时，会跳过判断模板缓存文件存在检查。每次都重新生成模板缓存文件
             */
            'cache_open' => $this->config[ 'app.template.cache_open' ], //是否开启缓存，程序调试时使用
            'value' => $tVar    //压到模板里的变量数据
        );
        $tmac_template_update_cache = $this->request->getQuery( 'tmac_template_update_cache', 0 );
        if ( $tmac_template_update_cache ) {
            $options[ 'auto_update' ] = true;//当模板文件有改动时重新生成缓存（适用于关闭主动更新时用于手动更新模板缓存）
        }
        $tpl = $this->container->template;
        $tpl->setOptions( $options ); //设置模板参数
        //如果是前台的模板就不用前缀（模板目录名）
        if ( empty( $view ) ) {
            //如果模板路径，文件名为空的话就尝试把TMAC_CONTROLLER_FILE当作模板路径，文件名
            $view_new = strtolower( $this->request->getQuery( 'TMAC_CONTROLLER_NAME' ) );  //都转成小写的
            $view = $view_new;  //前面加上模板目录名
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
        $this->assign( $this->getTplVarArray() );
        $this->display( $view, $this->tVar );
    }


    /**
     * 原生PHP输出模板
     * @param string $view 模板路径以及文件名 可以用.作为目录分割
     */
    public final function display( $view, $tVar = null )
    {
        $file = $this->app->getWebTemplatePath() . $this->config[ 'app.template.template' ]
            . DIRECTORY_SEPARATOR . $this->config[ 'app.template.template_style' ]
            . DIRECTORY_SEPARATOR . $view
            . $this->config[ 'app.template.suffix' ];
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
        $file = $this->app->getWebTemplatePath() . $this->config[ 'app.template.template' ]
            . DIRECTORY_SEPARATOR . $this->config[ 'app.template.template_style' ]
            . DIRECTORY_SEPARATOR . $view
            . $this->config[ 'app.template.suffix' ];
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
            'code' => 0,
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