<?php
/**
 * Power By Tmac PHP MVC framework
 * $Author: zhangwentao $  <zwttmac@qq.com>
 * $Id: Controller.class.php 325 2016-05-31 10:07:35Z zhangwentao $
 * http://www.t-mac.org；
 */

namespace Tmac;

use Closure;
use Exception;
use ReflectionClass;
use ArrayAccess;
use Psr\Container\ContainerInterface;
use Tmac\Exception\ClassNotFoundException;

class Container implements ArrayAccess, ContainerInterface
{
    /**
     * The current globally available container (if any).
     *
     * @var static
     */
    protected static $instance;

    /**
     * 容器绑定标识
     * @var array
     */
    protected $_bind = [];

    /**
     * 容器中对对象实例
     * @var array
     */
    private $_instances = [];

    /**
     * Get the globally available instance of the container.
     * 用单例模式获取容器的实例
     * @return static
     */
    public static function getInstance()
    {
        if ( is_null( static::$instance ) ) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    /**
     * 绑定一个类、闭包、实例、接口实现到容器
     * @access public
     * @param string|array $abstract 类标识、接口
     * @param mixed        $concrete 要绑定的类、闭包或者实例
     * @return $this
     */
    public function bind($abstract, $concrete = null)
    {
        if (is_array($abstract)) {
            foreach ($abstract as $key => $val) {
                $this->bind($key, $val);
            }
        } elseif ($concrete instanceof Closure) {
            $this->bind[$abstract] = $concrete;
        } elseif (is_object($concrete)) {
            $this->instance($abstract, $concrete);
        } else {
            $abstract = $this->getAlias($abstract);

            $this->bind[$abstract] = $concrete;
        }

        return $this;
    }

    /**
     * 绑定一个类、闭包、实例、接口实现到容器
     * @access public
     * @param string|array $abstract 类标识、接口
     * @param mixed $concrete 要绑定的类、闭包或者实例
     * @return $this
     */
    public function set( $class, $concrete = null )
    {
        if ($concrete === null) {
            $concrete = $class;
        }
        $this->_instances[$class] = $concrete;
        return $this;
    }


    /**
     * 获取容器中的对象实例
     * @access public
     * @param string $abstract 类名或者标识
     * @return object
     */
    public function get( $abstract )
    {
        if ( $this->has( $abstract ) ) {
            return $this->make( $abstract );
        }

        throw new ClassNotFoundException( 'class not exists: ' . $abstract, $abstract );
    }

    /**
     * 创建类的实例 已经存在则直接获取
     * @access public
     * @param string $abstract 类名或者标识
     * @param array $vars 变量
     * @param bool $newInstance 是否每次创建新的实例
     * @return mixed
     */
    public function make( string $abstract, array $vars = [], bool $newInstance = false )
    {
        if ( isset( $this->_instances[ $abstract ] ) && !$newInstance ) {
            return $this->_instances[ $abstract ];
        }

        $object = $this->resolve( $abstract, $vars );

        if ( !$newInstance ) {
            $this->_instances[ $abstract ] = $object;
        }

        return $object;
    }

    /**
     * 解决依赖
     * @param $class
     * @param $param
     * @return mixed|object
     * @throws \ReflectionException
     */
    private function resolve( $class, $param )
    {
        if ( $class instanceof Closure ) {
            // 使用匿名函数去设置服务，这个实例将被延迟加载
            return $class( $this, $param );
        }
        $reflector = new ReflectionClass( $class );
        // 检查类是否可以实例化
        if ( !$reflector->isInstantiable() ) {
            throw new Exception( "Class {$class} is not instantiable" );
        }
        // 通过反射获取到目标类的构造函数
        $constructor = $reflector->getConstructor();
        if ( is_null( $constructor ) ) {
            // 如果目标没有构造函数则直接返回实例化对象
            return $reflector->newInstance();
        }

        // 获取构造函数参数
        $parameters = $constructor->getParameters();
        //获取到构造函数中的依赖
        $dependencies = $this->getDependencies( $parameters );
        // 解决掉所有依赖问题并返回实例
        return $reflector->newInstanceArgs( $dependencies );
    }

    /**
     * 解决依赖关系
     *
     * @param $parameters
     *
     * @return array
     * @throws Exception
     */
    private function getDependencies( $parameters )
    {
        $dependencies = [];
        foreach ( $parameters as $parameter ) {
            $dependency = $parameter->getClass();
            if ( $dependency === null ) {
                // 检查是否有默认值
                $dependencies[] = $this->resolveNonClass( $parameter );
            } else {
                // 重新调用get() 方法获取需要依赖的类到容器中。
                $dependencies[] = $this->get( $dependency->name );
            }
        }

        return $dependencies;
    }

    /**
     * @param \ReflectionParameter $parameter
     * @return mixed
     * @throws Exception
     */
    private function resolveNonClass( $parameter )
    {
        // 有默认值则返回默认值
        if ( $parameter->isDefaultValueAvailable() ) {
            // 获取参数默认值
            return $parameter->getDefaultValue();
        }

        throw new Exception( "无法解析依赖关系 {$parameter->name}" );
    }

    /**
     * 判断容器中是否存在类及标识
     *  {@inheritdoc}
     * @access public
     * @param string $name 类名或者标识
     * @return bool
     */
    public function has( $name ): bool
    {
        return isset( $this->bind[ $name ] ) || isset( $this->instances[ $name ] );
    }

    /**
     * 判断容器中是否存在对象实例
     * @access public
     * @param string $abstract 类名或者标识
     * @return bool
     */
    public function exists( string $abstract ): bool
    {
        $abstract = $this->getAlias( $abstract );

        return isset( $this->instances[ $abstract ] );
    }

    /**
     * 删除容器中的对象实例
     * @access public
     * @param string $name 类名或者标识
     * @return void
     */
    public function delete( $name )
    {
        $name = $this->getAlias( $name );

        if ( isset( $this->instances[ $name ] ) ) {
            unset( $this->instances[ $name ] );
        }
    }


    public function __set( $name, $value )
    {
        $this->set( $name, $value );
    }

    public function __get( $name )
    {
        return $this->get( $name );
    }

    public function __isset( $name ): bool
    {
        return $this->exists( $name );
    }

    public function __unset( $name )
    {
        $this->delete( $name );
    }

    public function offsetExists( $key )
    {
        return $this->exists( $key );
    }

    public function offsetGet( $key )
    {
        return $this->make( $key );
    }

    public function offsetSet( $key, $value )
    {
        $this->bind( $key, $value );
    }

    public function offsetUnset( $key )
    {
        $this->delete( $key );
    }

    /**
     * 取共享服务，返回单例，无需初始化
     */
    public function getShared( $name, $params = [], $alias = '' )
    {
        $alias = empty( $alias ) ? $name : $alias;
        if ( isset( $this->_instances[ $alias ] ) ) {
            $object = $this->_instances[ $alias ];
        } else {
            $classReflection = new \ReflectionClass( $name );
            $instance = $classReflection->newInstanceArgs( $params );
            $object = $this->_instances[ $alias ] = $instance;
        }

        return $object;
    }
}