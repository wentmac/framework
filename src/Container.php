<?php
/**
 * Power By Tmac PHP MVC framework
 * $Author: zhangwentao $  <zwttmac@qq.com>
 * $Id: Controller.class.php 325 2016-05-31 10:07:35Z zhangwentao $
 * http://www.t-mac.org；
 */

namespace Tmac;

use ArrayAccess;
use Psr\Container\ContainerInterface;

class Container implements ArrayAccess, ContainerInterface
{
    /**
     * The current globally available container (if any).
     *
     * @var static
     */
    protected static $instance;

    /**
     * 容器中对对象实例
     * @var array
     */
    private $_instances = [];

    /**
     * 容器绑定array
     * @var array
     */
    private $_bind = [];


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
     * 设置容器实例
     * @param ContainerContract|null $container
     * @return ContainerContract|null
     */
    public static function setInstance( ContainerContract $container = null )
    {
        return static::$instance = $container;
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
     * Register a binding with the container.
     * 绑定类，闭包，实例，接口 到容器
     * @param $abstract
     * @param null $concrete
     * @return $this
     */
    public function bind( $abstract, $concrete = null )
    {

        if ( is_array( $abstract ) ) {
            foreach ( $abstract as $key => $val ) {
                $this->bind( $key, $val );
            }
        } elseif ( $concrete instanceof Closure ) {
            $this->bind[ $abstract ] = $concrete;
        } elseif ( is_object( $concrete ) ) {
            $this->instance( $abstract, $concrete );
        } else {
            $abstract = $this->getAlias( $abstract );

            $this->bind[ $abstract ] = $concrete;
        }

        return $this;
    }

    /**
     * 根据别名获取真实类名
     * @param string $abstract
     * @return string
     */
    public function getAlias( string $abstract ): string
    {
        if ( isset( $this->bind[ $abstract ] ) ) {
            $bind = $this->bind[ $abstract ];

            if ( is_string( $bind ) ) {
                return $this->getAlias( $bind );
            }
        }

        return $abstract;
    }

    /**
     * 绑定一个类实例到容器
     * @access public
     * @param string $abstract 类名或者标识
     * @param object $instance 类的实例
     * @return $this
     */
    public function instance( string $abstract, $instance )
    {
        $abstract = $this->getAlias( $abstract );

        $this->instances[ $abstract ] = $instance;

        return $this;
    }

    /**
     * 判断容器中是否存在类及标识
     * Determine if the given abstract type has been bound.
     * @access public
     * @param string $abstract 类名或者标识
     * @return bool
     */
    public function bound( string $abstract ): bool
    {
        return isset( $this->bind[ $abstract ] ) || isset( $this->instances[ $abstract ] );
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
        return $this->bound( $name );
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
     * 创建类的实例 已经存在则直接获取
     * Resolve the given type from the container.
     * @access public
     * @param string $abstract    类名或者标识
     * @param array  $vars        变量
     * @param bool   $newInstance 是否每次创建新的实例
     * @return mixed
     */
    public function make(string $abstract, array $vars = [], bool $newInstance = false)
    {
        $abstract = $this->getAlias($abstract);

        if (isset($this->instances[$abstract]) && !$newInstance) {
            return $this->instances[$abstract];
        }

        if (isset($this->bind[$abstract]) && $this->bind[$abstract] instanceof Closure) {
            // 如果是匿名函数（Anonymous functions），也叫闭包函数（closures）
            // 执行闭包函数，并将结果输出
            $object = $this->invokeFunction($this->bind[$abstract], $vars);
        } else {
            $object = $this->invokeClass($abstract, $vars);
        }

        if (!$newInstance) {
            $this->instances[$abstract] = $object;
        }

        return $object;
    }

    /**
     * 删除容器中的对象实例
     * @access public
     * @param string $name 类名或者标识
     * @return void
     */
    public function delete($name)
    {
        $name = $this->getAlias($name);

        if (isset($this->instances[$name])) {
            unset($this->instances[$name]);
        }
    }

    /**
     * 执行函数或者闭包方法 支持参数调用
     * @access public
     * @param string|Closure $function 函数或者闭包
     * @param array          $vars     参数
     * @return mixed
     */
    public function invokeFunction($function, array $vars = [])
    {
        try {
            $reflect = new ReflectionFunction($function);
        } catch (ReflectionException $e) {
            throw new FuncNotFoundException("function not exists: {$function}()", $function, $e);
        }

        $args = $this->bindParams($reflect, $vars);

        return $function(...$args);
    }

    /**
     * 调用反射执行类的实例化 支持依赖注入
     * @access public
     * @param string $class 类名
     * @param array  $vars  参数
     * @return mixed
     */
    public function invokeClass(string $class, array $vars = [])
    {
        try {
            $reflect = new ReflectionClass($class);
        } catch (ReflectionException $e) {
            throw new ClassNotFoundException('class not exists: ' . $class, $class, $e);
        }

        if ($reflect->hasMethod('__make')) {
            $method = $reflect->getMethod('__make');
            if ($method->isPublic() && $method->isStatic()) {
                $args = $this->bindParams($method, $vars);
                return $method->invokeArgs(null, $args);
            }
        }

        $constructor = $reflect->getConstructor();

        $args = $constructor ? $this->bindParams($constructor, $vars) : [];

        $object = $reflect->newInstanceArgs($args);

        $this->invokeAfter($class, $object);

        return $object;
    }


    public function __set($name, $value)
    {
        $this->bind($name, $value);
    }

    public function __get($name)
    {
        return $this->get($name);
    }

    public function __isset($name): bool
    {
        return $this->exists($name);
    }

    public function __unset($name)
    {
        $this->delete($name);
    }

    public function offsetExists($key)
    {
        return $this->exists($key);
    }

    public function offsetGet($key)
    {
        return $this->make($key);
    }

    public function offsetSet($key, $value)
    {
        $this->bind($key, $value);
    }

    public function offsetUnset($key)
    {
        $this->delete($key);
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