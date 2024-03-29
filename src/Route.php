<?php

/**
 * Power By Tmac PHP MVC framework
 * $Author: zhangwentao $  <zwttmac@qq.com>
 * $Id: Controller.class.php 325 2016-05-31 10:07:35Z zhangwentao $
 * http://www.t-mac.org；
 */

namespace Tmac;

use Tmac\Contract\ConfigInterface;
use Tmac\Exception\ClassNotFoundException;
use Tmac\Exception\TmacException;
use ReflectionClass;

class Route
{
    use DITrait;

    /**
     * URL参数
     *
     * @var array
     */
    private $param;
    private $url;
    private $config;


    /**
     * 构造函数.
     *
     * @return void
     * @access public
     *
     */
    public function __construct( ConfigInterface $config )
    {
        $this->config = $config;
    }

    public function init()
    {
        $this->parsePath();
        $_GET = array_merge( $_GET, $this->param );
        $this->initControllerMethod( $this->initController() );
    }

    /**
     * 解析URL路径
     *
     * @return void
     * @access private
     *
     */
    private function parsePath(): void
    {
        if ( $this->config[ 'app.url_case_insensitive' ] ) {
            // URL地址中不区分大小写
            if ( !empty( $_GET[ 'M' ] ) && empty( $_GET[ 'm' ] ) ) {
                $_GET[ 'm' ] = $_GET[ 'M' ];
            }
            $_GET[ 'm' ] = strtolower( $_GET[ 'm' ] );
        }
        //确定Controller以及Action
        if ( empty( $_GET[ 'm' ] ) ) {
            //如果没有参数任何参数
            $this->param[ 'TMAC_CONTROLLER_FILE' ] = 'IndexController';
            $this->param[ 'TMAC_CONTROLLER' ] = '';
            $this->param[ 'TMAC_CONTROLLER_NAME' ] = 'index';
            $this->param[ 'TMAC_ACTION' ] = 'index';
            return;
        }
        $queryString = $_GET[ 'm' ];
        unset( $_GET[ 'm' ] );
        $action = '';
        if ( ( $urlSeparatorPosition = strrpos( $queryString, $this->config[ 'app.url_separator' ] ) ) > 0 ) {//如果query_string中有url separator就来取controller和method
            $controller = substr( $queryString, 0, $urlSeparatorPosition );
            $action = substr( $queryString, $urlSeparatorPosition + 1 );
        } else {
            $controller = $queryString;
        }
        //Controller的第一个字符必须为字母
        if ( $this->isLetter( $controller ) === false ) {
            $message = "错误的Controller请求";
            $message .= $this->config[ 'app.debug' ] ? ": [{$controller}]" : "";
            throw new TmacException( $message );
        }
        if ( empty( $action ) )
            $action = 'index';

        $tmac_controller = basename( $controller );
        if ( $tmac_controller === $controller ) {
            //没有二级目录的控制器，默认都在Conttroller目录下的控制器
            $tmac_controller_file = '';
        } else {
            //如果子目录的控制器  比如 src/App/web/Controller/user/IndexController.php，取出目录结构
            $tmac_controller_file = $this->getControllerFilePath( $controller );
        }

        // Action的分隔符  index.php?m=system.permission-save 默认使用中线：- 推荐使用下划线或中线，目的是为了把action（Controller中的method)转成驼峰写法
        if ( !empty( $this->config[ 'app.url_action_separator' ] ) ) {
            // 如果配置了Action分隔符
            /*
             index.php?m=system.permission-save
            to
             system/permission/save (permissionSaveAction)
             */
            if ( false !== strpos( $action, $this->config[ 'app.url_action_separator' ] ) ) {
                // 如果query_string中有url separator就来取controller和method
                $action_params_array = explode( $this->config[ 'app.url_action_separator' ], $action );
                $action_temp = '';
                foreach ( $action_params_array as $k => $action_param ) {
                    if ( $k === 0 ) {
                        $action_temp .= $action_param;
                    } else {
                        $action_temp .= ucwords( $action_param );
                    }
                }
                $action = $action_temp;
            }
        }

        $this->param[ 'TMAC_CONTROLLER' ] = ucfirst( $tmac_controller ) . 'Controller';
        $this->param[ 'TMAC_CONTROLLER_FILE' ] = $tmac_controller_file;
        $this->param[ 'TMAC_CONTROLLER_NAME' ] = $tmac_controller;
        $this->param[ 'TMAC_ACTION' ] = $action;
    }

    /**
     * user/ return user\
     * user/bill/ return user\bill\
     * @param $controller
     * @return string|string[]
     */
    private function getControllerFilePath( $controller )
    {
        $urlSeparatorPosition = strrpos( $controller, '/' );
        $controller = substr( $controller, 0, $urlSeparatorPosition + 1 );
        return str_replace( '/', '\\', $controller );
    }

    /**
     * 根据解析的URL获取Controller文件
     *
     * @return object
     * @access private
     *
     */
    private function initController()
    {
        $class_name = ucfirst( APP_NAME ) . '\Controller\\' . $this->param[ 'TMAC_CONTROLLER_FILE' ] . $this->param[ 'TMAC_CONTROLLER' ];
        try {
            $controller_object = $this->getDI()->get( $class_name );
        } catch ( ClassNotFoundException $e ) {
            $message = "错误的请求，找不到Controller文件";
            if ( $this->config[ 'app.debug' ] ) {
                $message .= "[ $class_name ]";
                $message .= $e->getMessage();
            }
            throw new TmacException( $message );
        }
        return $controller_object;
    }

    /**
     * 初始化执行init()
     * @param object $controller_object
     * @param ReflectionClass $reflector
     * @return bool
     */
    private function initControllerInitMethod( object $controller_object, ReflectionClass $reflector )
    {
        $init_method = '_initAction';

        if ( $reflector->hasMethod( $init_method ) === false ) {
            return true;
        }
        $method = $reflector->getMethod( $init_method );
        if ( $method->isPublic() === FALSE ) {
            return true;
        }
        $args = $this->getDI()->bindParams( $method );
        $method->invokeArgs( $controller_object, $args );
        //$method->invoke( $controller_object );
    }

    /**
     * 根据Controller文件名获取Controller类名并且执行
     *
     * @return void
     * @access private
     *
     */
    private function initControllerMethod( object $controller_object )
    {
        $reflector = new ReflectionClass( $controller_object );
        $controller_method = $this->param[ 'TMAC_ACTION' ] . 'Action';

        if ( $reflector->hasMethod( $controller_method ) === false ) {
            $message = "错误的请求，找不到Action";
            $message .= $this->config[ 'app.debug' ] ? ":[{$this->param['TMAC_ACTION']}]" : "";
            throw new TmacException( $message );
        }
        $method = $reflector->getMethod( $controller_method );
        $args = $this->getDI()->bindParams( $method );

        if ( $method->isPublic() === FALSE ) {
            $message = "Action为私有方法";
            $message .= $this->config[ 'app.debug' ] ? ":[{$this->param['TMAC_ACTION']}]" : "";
            throw new TmacException( $message );
        }
        //执行init方法
        $this->initControllerInitMethod( $controller_object, $reflector );
        //执行action方法
        $method->invokeArgs( $controller_object, $args );
        //$method->invoke( $controller_object );
    }

    /**
     * 判断第一个字符是否为字母
     *
     * @param string $char
     * @return boolean
     */
    private function isLetter( $char )
    {
        $ascii = ord( $char[ 0 ] );
        return ( $ascii >= 65 && $ascii <= 90 ) || ( $ascii >= 97 && $ascii <= 122 );
    }

}