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
use ReflectionException;
use ReflectionParameter;
use ReflectionFunctionAbstract;
use ArrayAccess;
use Psr\Container\ContainerInterface;
use Tmac\Exception\ClassNotFoundException;
use InvalidArgumentException;

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
     * @var array 可实例化对象定义索引
     */
    protected $_bind = [];

    /**
     * 容器绑定标识
     * 共享服务标识
     * @var array 可实例化对象定义索引
     */
    protected $_bind_shared = [];

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
     * 绑定一个类实例到容器
     * @access public
     * @param string $abstract 类名或者标识
     * @param object $instance 类的实例
     * @return $this
     */
    private function instance( string $abstract, $instance )
    {
        $this->_instances[ $abstract ] = $instance;
        return $this;
    }

    /**
     * 绑定一个类、闭包、实例、接口实现到容器
     * @access public
     * @param string|array $abstract 类标识、接口
     * @param mixed $concrete 要绑定的类、闭包或者实例
     * @return $this
     */
    private function bind( $abstract, $concrete = null )
    {
        //如果没有绑定类，默认把标识名当类
        if ( $concrete === null ) {
            $concrete = $abstract;
        }
        if ( is_array( $abstract ) ) {
            foreach ( $abstract as $key => $val ) {
                $this->bind( $key, $val );
            }
        } else {
            $this->_bind[ $abstract ] = $concrete;
        }
        return $this;
    }


    /**
     * 检测是否设置了共享服务
     * @param $abstract
     * @return bool
     */
    private function isShared( $abstract )
    {
        //setShared和set方法中是否初始绑定了共享服务
        if ( isset( $this->_bind_shared[ $abstract ] ) && $this->_bind_shared[ $abstract ] === true ) {
            return true;
        }
        return false;
    }

    /**
     * 绑定一个类、闭包、实例、接口实现到容器
     * @access public
     * @param string|array $abstract 类标识、接口
     * @param mixed $concrete 要绑定的类、闭包或者实例
     * @return $this
     */
    public function set( $abstract, $concrete = null )
    {
        //设置 注册的类 为非共享服务，每次调用初始化
        $this->_bind_shared[ $abstract ] = false;
        return $this->bind( $abstract, $concrete );
    }

    /**
     * 服务可以注册成“shared”类型的服务，这意味着这个服务将使用 [单例模式] 运行，
     * 一旦服务被首次解析后，这个实例将被保存在容器中，之后的每次请求都在容器中查找并返回这个实例
     *
     * 通过set和setShared方式注册服务，支持 匿名函数，类名字符串，已经实例化的对象，两者的区别是：
     *
     * set方式注册的，每次获取的时候都会重新实例化
     *
     * setShared方式的，则只实例化一次，也就是所谓的单例模式
     *
     * 当然，对于直接注册已经实例化的对象，如上代码中的a3服务，set和setShared效果是一样的。
     * 直接传入实例化的对象
     * $di->set('a3',new A("小唐"));
     * 对于直接注册已经实例化的对象，如上代码中的a3服务，set和setShared效果是一样的。
     * @param $class
     * @param null $concrete
     */
    public function setShared( $abstract, $concrete = null )
    {
        //设置共享服务
        $this->_bind_shared[ $abstract ] = true;
        return $this->bind( $abstract, $concrete );
    }

    /**
     * 获取目标实例
     *
     * @param string $abstract
     * 构造函数参数值列表，参数应按顺序提供
     * @param array $params a list of constructor parameter values. The parameters should be provided in the order
     * they appear in the constructor declaration. If you want to skip some parameters, you should index the remaining
     * ones with the integers that represent their positions in the constructor parameter list.
     * a list of name-value pairs that will be used to initialize the object properties
     *
     * 用来初始化对象属性的名称-值对列表用来初始化对象属性的名称-值对列表
     * @param array $properties
     * @return mixed|object
     * @throws Exception
     */
    public function get( $abstract, array $params = [], array $properties = [] )
    {
        // 如果容器中不存在则注册到容器
        if ( !isset( $this->_bind[ $abstract ] ) ) {
            $this->set( $abstract );
        }

        $check_bind_shared = $this->isShared( $abstract );
        //如果有共享返回共享服务
        if ( $check_bind_shared && isset( $this->_instances[ $abstract ] ) ) {
            $object = $this->_instances[ $abstract ];
            $this->bindMethod( $object, $properties );
            return $object;
        }

        //未设置过共享服务状态，默认不使用共享
        $object = $this->resolve( $this->_bind[ $abstract ], $params );
        $this->_instances[ $abstract ] = $object;
        //setter 注入
        $this->bindMethod( $object, $properties );
        return $object;
    }

    /**
     * 取共享服务，返回单例，无需初始化
     * @param $abstract
     * @param array $params
     * @param array $properties
     * @return mixed|object
     * @throws Exception
     */
    public function getShared( $abstract, $params = [], array $properties = [] )
    {
        //如果有共享返回共享服务
        if ( isset( $this->_instances[ $abstract ] ) ) {
            return $this->_instances[ $abstract ];
        }
        return $this->get( $abstract, $params, $properties );
    }

    /**
     * 解决依赖
     * @param $abstract
     * @param $params
     * @return mixed|object
     * @throws Exception
     */
    private function resolve( $abstract, $params )
    {
        if ( $abstract instanceof Closure ) {
            // 使用匿名函数去设置服务，这个实例将被延迟加载
            return $abstract( $this, $params );
        }
        try {
            $reflector = new ReflectionClass( $abstract );
        } catch ( ReflectionException $e ) {
            throw new ClassNotFoundException( 'class not exists: ' . $class, $class, $e );
        }
        // 检查类是否可以实例化
        if ( !$reflector->isInstantiable() ) {
            throw new Exception( "Class {$abstract} is not instantiable" );
        }
        // 通过反射获取到目标类的构造函数
        $constructor = $reflector->getConstructor();
        if ( is_null( $constructor ) ) {
            // 如果目标没有构造函数则直接返回实例化对象
            return $reflector->newInstance();
        }
        //获取到构造函数中的参数依赖
        $args = $this->bindParams( $constructor, $params );
        // 解决掉所有依赖问题并返回实例
        $object = $reflector->newInstanceArgs( $args );
        //自动注入 DI（Automatic Injecting of the DI itself）¶
        $this->autoInjectingDI( $reflector, $object );
        return $object;
    }

    /**
     * 自动 注入DI窗口
     * @param ReflectionClass $reflect
     * @param object $object
     */
    private function autoInjectingDI( ReflectionClass $reflect, object $object )
    {
        if ( $reflect->hasMethod( 'setDi' ) ) {
            $object->setDi( $this );
        }
    }

    /**
     * 绑定参数
     * @param ReflectionFunctionAbstract $reflect 反射类
     * @param array $vars 参数
     * @return array
     * @throws ReflectionException
     */
    private function bindParams( ReflectionFunctionAbstract $reflect, array $vars = [] ): array
    {
        //获取函数定义的参数数目，包括可选参数,构造函数参数数量如果为0，就返回空数据
        if ( $reflect->getNumberOfParameters() == 0 ) {
            return [];
        }
        // 判断数组类型 数字数组时按顺序绑定参数
        reset( $vars );
        //$associative=true时为关联数组 fase=索引数组
        $associative = key( $vars ) === 0 ? false : true;
        // 获取构造函数参数
        $parameters = $reflect->getParameters();
        $args = [];

        foreach ( $parameters as $param ) {
            $class = $param->getClass();
            $name = $param->getName();

            if ( $class ) {
                //将类型提示的参数实例化
                $args[] = $this->getObjectParam( $param, $associative, $vars );
            } elseif ( $associative === true && isset( $vars[ $name ] ) ) {
                // 如果参数是 关联数组 带有指定键的数组
                $args[] = $vars[ $name ];
            } elseif ( $associative === false && !empty( $vars ) ) {
                // 如果参数是 索引数组 索引是自动分配的（索引从 0 开始）：带有数字索引的数组
                $args[] = array_shift( $vars );
            } elseif ( $param->isDefaultValueAvailable() ) {
                // 如果参数有默认值
                $args[] = $param->getDefaultValue();
            } else {
                // 没有找到构造函数
                throw new InvalidArgumentException( 'Missing required parameter：' . $name . ' when calling ' . $reflect->getName() );
            }
        }
        return $args;
    }

    /**
     * 获取对象类型的参数值
     * @param ReflectionParameter $parameter
     * @param bool $associative
     * @param array $vars
     * @return mixed|object
     * @throws Exception
     */

    private function getObjectParam( ReflectionParameter $parameter, bool $associative, array &$vars )
    {
        $class = $parameter->getClass();
        $name = $parameter->getName();

        $className = $class->getName();
        if ( $associative && isset( $vars[ $name ] ) && $vars[ $name ] instanceof $className ) {
            //如果是关联数组
            $result = $vars[ $name ];
            unset( $vars[ $name ] );
        } else if ( !$associative && isset( $vars[ 0 ] ) && $vars[ 0 ] instanceof $className ) {
            //如果是索引数组
            $result = array_shift( $vars );
        } else {
            // 重新调用get() 方法获取需要依赖的类到容器中。使用getShared方法。共享自动创建的构造函数注入的服务
            $result = $this->getShared( $className );
        }

        return $result;
    }


    /**
     * Setter 和属性注入（Setter and Property Injection）
     *
     * $a = $di->get( 'c', [], [
     * 'bar' => 'bar_string',
     * 'setQux' => [ $di->get( \App\Service\PlainService::class ), 'bbbb' ],
     * ] );
     * @param object $class
     * @param array $properties
     * @return bool
     * @throws ReflectionException
     */
    private function bindMethod( object $class, array $properties )
    {
        if ( empty( $properties ) ) {
            return true;
        }
        $reflect = new ReflectionClass( $class );
        foreach ( $properties as $property => $args ) {
            if ( $reflect->hasMethod( $property ) == false ) {
                continue;
            }
            $method = $reflect->getMethod( $property );
            //检查是否是公有方法且是静态方法
            if ( $method->isPublic() ) {
                //调用该方法（__make），因为是静态的，所以第一个参数是null
                //因此，可得知，一个类中，如果有__make方法，在类实例化之前会首先被调用
                if ( is_array( $args ) ) {
                    $method->invokeArgs( $class, $args );
                } else {
                    $method->invoke( $class, $args );
                }
            }
        }
        return true;
    }

    /**
     * 判断容器中是否存在类及标识
     *  {@inheritdoc}
     * @access public
     * @param string $name 类名或者标识
     * @return bool
     */
    public function has( $id )
    {
        return isset( $this->_instances[ $id ] );
    }

    /**
     * 删除容器中的对象实例
     * @access public
     * @param string $name 类名或者标识
     * @return void
     */
    public function delete( $name )
    {
        if ( $this->has( $name ) ) {
            unset( $this->_instances[ $name ] );
        }
    }

    /**
     * 判断容器中是否存在对象实例
     * @param string $abstract
     * @return bool
     */
    public function exists( string $abstract ): bool
    {
        return isset( $this->_instances[ $abstract ] );
    }

    /**
     * 通过魔术方法的方式获取
     * $request = $di->get("request");
     * ====================================
     * $request = $di->getRequest();
     * ====================================
     * $request = $di["request"];
     * @param $name
     * @param $arguments
     */
    public function __call( $name, $arguments )
    {
        if ( strpos( $name, 'get' ) !== false ) {
            $service = lcfirst( substr( $name, 3 ) );
            // if(isset($this->_instances[]));
        }
        // 注意: $name 的值区分大小写
        $error = "Calling object method '$name' "
            . implode( ', ', $arguments ) . "\n";
        throw new ClassNotFoundException( $error );
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
        return true;
        $this->delete( $name );
    }

    public function offsetExists( $key )
    {
        return $this->exists( $key );
    }

    public function offsetGet( $key )
    {
        return $this->get( $key );
    }

    public function offsetSet( $key, $value )
    {
        $this->set( $key, $value );
    }

    public function offsetUnset( $key )
    {
        $this->delete( $key );
    }


}