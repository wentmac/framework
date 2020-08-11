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
use ReflectionFunction;
use ReflectionParameter;
use ReflectionFunctionAbstract;
use ArrayAccess;
use Psr\Container\ContainerInterface;
use Tmac\Exception\ClassNotFoundException;
use InvalidArgumentException;
use Tmac\Exception\FuncNotFoundException;
use Tmac\Exception\TmacException;

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
    private function setInstance( string $abstract, $instance )
    {
        //未设置过的重新设置，container中保存第一次声明初始化的class
        isset( $this->_instances[ $abstract ] ) || $this->_instances[ $abstract ] = $instance;
        return $this;
    }


    /**
     * 根据别名获取真实类名
     * @param string $abstract
     * @return string
     */
    private function getAlias( string $abstract ): string
    {
        if ( isset( $this->_bind[ $abstract ] ) && $abstract !== $this->_bind[ $abstract ] ) {
            $bind = $this->_bind[ $abstract ];

            if ( is_string( $bind ) ) {
                return $this->getAlias( $bind );
            }
            if ( is_array( $bind ) && isset( $bind[ 'class' ] ) ) {
                //返回真实的绑定key。alias class name或者原始set的容器key名
                $abstract = $this->getBindName( $abstract, $bind );
            }
        }
        return $abstract;
    }


    /**
     * 绑定一个类、闭包、实例、接口实现到容器
     * @access public
     * @param string|array $abstract 类标识、接口
     * @param mixed $concrete 要绑定的类、闭包或者实例
     * @param boolean $shared_status 设置 注册的类 共享服务，只初始化一次|非共享服务，每次调用初始化
     * @return $this
     */
    private function bind( $abstract, $concrete = null, $shared_status = false )
    {
        if ( is_array( $abstract ) ) {
            foreach ( $abstract as $key => $val ) {
                $this->bind( $key, $val, $shared_status );
            }
            return $this;
        }

        //如果是闭包，设置好绑定关系，返回
        if ( $concrete instanceof Closure ) {
            $this->_bind[ $abstract ] = $concrete;
            $this->setBindShared( $abstract, $shared_status );
            return $this;
        }

        /**
          处理 设置$concrete为Array中有'class'并且设置class为Closure 匿名函数回调
          ====================================================================
            $container->setShared( 'fileLogger', [
               'class' => function ( App $app ) {
                   $log = new Logger( 'redis' );
                   $log->pushHandler( new StreamHandler( $app->getVarPath() . 'log/monolog/file_error.log', Logger::WARNING ) );
                   return $log;
               },
               'alias' => \Psr\Log\LoggerInterface::class
            ] );
          ==================================================================

         */
        if ( is_array( $concrete ) && isset( $concrete[ 'class' ] ) && $concrete[ 'class' ] instanceof Closure ) {
            $this->_bind[ $abstract ] = $concrete[ 'class' ];
            $this->setBindShared( $abstract, $shared_status );

            //如果设置了alias比如设置了接口类当别名，就绑定在别名上，使用（ConfigInterface $config）之类就能自动解析
            $bind_name = $this->getObjectBindName( $abstract, $concrete );
            if ( $bind_name !== false ) {
                $this->_bind[ $bind_name ] = $abstract;
            }
            return $this;
        }

        /**
         * 如果设置的是一个对象，每次调用都会返回实例化
         * 直接注册一个实例
         * $di->set("request", new Request());
         * $di["request"] = new Request();
         * //直接传入实例化的对象
         * $di->set('a3',new A("小唐"));
         * $a3 = $di['a3'];//可以直接通过数组方式获取服务对象
         * echo $a3->name."<br/>";//小唐
         * 对于直接注册已经实例化的对象，如上代码中的a3服务，set和setShared效果是一样的。
         *
         * 类实例（CLASS INSTANCES）¶
         * 这种类型注册服务需要一个对象。实际上，这个服务不再需要初始化，因为它已经是一个对象，
         * 可以说，这不是一个真正的依赖注入，但是如果你想强制总是返回相同的对象/值，使用这种方式还是有用的:
         *
         *
         * use Phalcon\Http\Request;
         *
         * // 返回 Phalcon\Http\Request(); 对象
         * $di->set(
         * "request",
         * new Request()
         * );
         *
         * //YII2中的实现方法
         * // 注册一个组件实例
         * // $container->get('pageCache') 每次被调用时都会返回同一个实例。
         * $container->set('pageCache', new FileCache);
         */
        if ( is_object( $concrete ) ) {
            $this->_bind[ $abstract ] = $concrete;
            $this->setBindShared( $abstract, $shared_status );
            $this->setInstance( $abstract, $concrete );
            return $this;
        }

        /**
          处理 设置$concrete为Array中有'class'并且设置class为Ojbect
          ============================================================

        $container->setShared( 'boat_c', [
             'class' => new \App\Service\BoatService('abc'),
             'alias' => \App\Service\BoatService::class,
        ] );

          ============================================================

         */
        if ( is_array( $concrete ) && isset( $concrete[ 'class' ] ) && is_object( $concrete[ 'class' ] ) ) {
            $this->_bind[ $abstract ] = $concrete[ 'class' ];
            $this->setBindShared( $abstract, $shared_status );
            $this->setInstance( $abstract, $concrete[ 'class' ] );

            //如果设置了alias比如设置了接口类当别名，就绑定在别名上，使用（ConfigInterface $config）之类就能自动解析
            $bind_name = $this->getObjectBindName( $abstract, $concrete );
            if ( $bind_name !== false ) {
                $this->_bind[ $bind_name ] = $abstract;
            }
            return $this;
        }

        //如果没有绑定类，默认把标识名当类
        if ( $concrete === null ) {
            $concrete = $abstract;
        }
        /**
         * 支持set时 设置初始化配置 构造函数
         * // 通过配置注册一个类
         * // 通过 get() 初始化时，配置将会被使用。
         * $container->set('yii\db\Connection', [
         * 'dsn' => 'mysql:host=127.0.0.1;dbname=demo',
         * 'username' => 'root',
         * 'password' => '',
         * 'charset' => 'utf8',
         * ]);
         *
         * // 通过类的配置注册一个别名
         * // 这种情况下，需要通过一个 “class” 元素指定这个类
         * $container->set('db', [
         * 'class' => 'yii\db\Connection',
         * 'dsn' => 'mysql:host=127.0.0.1;dbname=demo',
         * 'username' => 'root',
         * 'password' => '',
         * 'charset' => 'utf8',
         * ]);
         */
        if ( is_array( $concrete ) && !isset( $concrete[ 'class' ] ) ) {
            if ( strpos( $abstract, '\\' ) !== false ) {
                $concrete[ 'class' ] = $abstract;
            } else {
                throw new ClassNotFoundException( 'Container Set Error:A class definition requires a "class" member.' . $abstract . var_export( $concrete ) );
            }
        }
        if ( is_array( $concrete ) ) {
            //如果是数组，取真实绑定名
            //如果设置了alias比如设置了接口类当别名，就绑定在别名上，使用（ConfigInterface $config）之类就能自动解析
            /**
             alias（别名可以用来自动注入时，绑定个性化配置的类）如下
             public function __construct( ConfigInterface $tmac_config, Filter $filter )
             =========================================
             $container->setShared( 'tmac_config', [
                 'class' => Config::class,
                 'alias' => ConfigInterface::class,
                 'params' => [
                     $root_path,
                     APP_NAME,
                     '.php'
                  ]
             ] );
             =========================================
             */
            $bind_name = $this->getBindName( $abstract, $concrete );
            //$bind_name = $concrete[ 'class' ];
            $this->setBindShared( $bind_name, $shared_status );

            if ( empty( $concrete[ 'alias' ] ) ) {
                //如果绑定时 没有设置 类别名 alias class 直接绑定注入内容到set的key值中a中 set('a',$concrete);
                $this->_bind[ $abstract ] = $concrete;
            } else {
                //如果绑定时 设置的有 类别名 alias class .
                //_bind[]中 [config] => Tmac\Contract\ConfigInterface $config
                $this->_bind[ $abstract ] = $bind_name;

                /*

                [Tmac\Contract\ConfigInterface $config] => Array
                (
                    [class] => Tmac\Config
                    [alias] => Tmac\Contract\ConfigInterface
                    [params] => Array
                        (
                            [0] => /var/www/personal/shanhaijingclub/shanhaijing
                            [1] => admin
                            [2] => .php
                        )

                    [class_alias] => Tmac\Contract\ConfigInterface $config
                )
                 */
                $this->_bind[ $bind_name ] = $concrete;
                $this->_bind[ $bind_name ][ 'class_alias' ] = $bind_name;
            }
        } else {
            //var_dump($abstract.'='.$concrete.'='.$shared_status);
            //字符串类型的服务别名绑定 比如 $container->setShared('config',Tmac\Config::class);
            //绑定服务别名的服务共享状态 exemple:'config','request'
            /**
             * 这里不需要set container _bind_shared[] config=1 因为在容器中取类的时候，有getAlias方法查询真实instance名
             * $this->setBindShared( $abstract, $shared_status );
             */
            //绑定服务真实类名 example:Tmac\Config::class Tmac\Request::class
            $this->setBindShared( $concrete, $shared_status );
            $this->_bind[ $abstract ] = $concrete;
        }
        return $this;
    }


    /**
     * 取真实的绑定名称
     * alias（别名可以用来自动注入时，绑定个性化配置的类）如下
     * ==================================================
     * public function __construct( ConfigInterface $tmac_config )
     * ==================================================
     * @param $abstract
     * @param $concrete
     * @return string
     */
    private function getBindName( $abstract, $concrete )
    {
        //class_alias
        if ( !empty( $concrete[ 'class_alias' ] ) ) {
            $bind_name = $concrete[ 'class_alias' ];
        } else if ( !empty( $concrete[ 'alias' ] ) ) {
            $bind_name = $concrete[ 'alias' ] . ' $' . $abstract;
        } else {
            // $bind_name = $concrete[ 'class' ];
            /**
             比如这种情况，没有 设置 alias绑定别名。此时在_bind _bind_shared _instances中绑定的key就返回boat_b
                $container->setShared( 'boat_b', [
                    'class' => \App\Service\BoatService::class,
                    'params' => [
                        'double duck'
                    ]
                ] );
             */
            $bind_name = $abstract;
        }
        return $bind_name;
    }

    /**
     * 取真实的绑定名称 Object或Closure 对象或闭包回调
     * alias（别名可以用来自动注入时，绑定个性化配置的类）如下
     * ==================================================
     * public function __construct( ConfigInterface $tmac_config )
     * ==================================================
     * @param $abstract
     * @param $concrete
     * @return bool|string
     */
    private function getObjectBindName( $abstract, $concrete )
    {
        if ( !empty( $concrete[ 'alias' ] ) ) {
            $bind_name = $concrete[ 'alias' ] . ' $' . $abstract;
        } else {
            //Object或Closure 对象或闭包回调 没有设置 类别名 alias
            return false;
        }
        return $bind_name;
    }

    /**
     * 设置绑定的共享状态
     * @param $abstract
     * @param bool $shared_status
     */
    private function setBindShared( $abstract, $shared_status = false ): void
    {
        //$abstract_name = $this->getAlias( $abstract );
        $abstract_name = $abstract;
        !isset( $this->_bind_shared[ $abstract_name ] ) && $this->_bind_shared[ $abstract_name ] = $shared_status;
    }


    /**
     * 取得容器中 依赖关系名称 绑定的共享状态。
     * 如果找到依赖关系名称有别名，并且未绑定过，就绑定设置别名相相同的共享状态
     * 解决了依赖关系名称与别名服务共享状态保持一致问题。
     * @param $abstract
     * @return bool|mixed
     */
    private function getBindShared( $abstract )
    {
        if ( isset( $this->_bind_shared[ $abstract ] ) ) {
            return $this->_bind_shared[ $abstract ];
        }
        //没有找到默认返回False不设置服务共享
        return false;
    }


    /**
     * 检测是否设置了共享服务
     * @param $abstract
     * @return bool
     */
    private function isShared( $abstract )
    {
        /**
         * 这种类型注册服务需要一个对象。
         * 实际上，这个服务不再需要初始化，因为它已经是一个对象，可以说，这不是一个真正的依赖注入，
         * 但是如果你想强制总是返回相同的对象/值，使用这种方式还是有用的:
         */
        $abstract_name = $this->_bind[ $abstract ];
        //返回直接声明对象的，排除匿名函数回调
        if ( is_object( $abstract_name ) && ( $abstract_name instanceof Closure ) === false ) {
            return true;
        }
        //setShared和set方法中是否初始绑定了共享服务
        $abstract_bind_shared = $this->_bind_shared[ $abstract ];
        if ( isset( $abstract_bind_shared ) && $abstract_bind_shared === true ) {
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
        return $this->bind( $abstract, $concrete, false );
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
     *
     * 如果一个服务不是注册成“shared”类型，而你又想从DI中获取服务的“shared”实例，你可以使用getShared方法：
     *
     * @param $class
     * @param null $concrete
     */
    public function setShared( $abstract, $concrete = null )
    {
        //设置共享服务
        return $this->bind( $abstract, $concrete, true );
    }


    /**
     * 获取目标实例
     *
     * @param string $abstract_alias_name 原始绑定需要调用的别名
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
    public function get( $abstract_alias_name, array $params = [], array $properties = [] )
    {
        //取得容器中 依赖关系名称 绑定的共享状态。如果找到依赖关系名称有别名，并且未绑定过，就绑定设置别名相相同的共享状态
        //解决了依赖关系名称与别名服务共享状态保持一致问题。
        $abstract_shared_status = $this->getBindShared( $abstract_alias_name );
        $abstract = $this->getAlias( $abstract_alias_name );
        // 如果容器中不存在则注册到容器
        if ( !isset( $this->_bind[ $abstract ] ) ) {
            $this->bind( $abstract, null, $abstract_shared_status );
        }
        $check_bind_shared = $this->isShared( $abstract );

        //如果有共享返回共享服务
        if ( $check_bind_shared && isset( $this->_instances[ $abstract ] ) ) {
            $object = $this->_instances[ $abstract ];
            $this->bindMethod( $object, $properties );
            return $object;
        }

        $definition = $this->_bind[ $abstract ];

        //如果绑定的是匿名函数回调
        if ( $definition instanceof Closure ) {
            // 使用匿名函数去设置服务，这个实例将被延迟加载
            //$object = $definition( $this, ...$params );
            $object = $this->resolveFunction( $definition, $params );
            $this->setInstance( $abstract, $object );
            //setter 注入
            $this->bindMethod( $object, $properties );
            return $object;
        }

        //如果bind设置的绑定是数组
        if ( is_array( $definition ) ) {
            //绑定的抽象类是数组型，从数组中返回要实例化的class_name
            $abstract_name = $definition[ 'class' ];
            //如果有 class alias 类别名的，下面绑定类别名到 container的_instance[]
            $bind_name = $this->getBindName( $abstract, $definition );

            if ( !empty( $definition[ 'params' ] ) && empty( $params ) ) {
                $params = $definition[ 'params' ];//构造函数参数注入
            }
            if ( !empty( $definition[ 'properties' ] ) && empty( $properties ) ) {
                $properties = $definition[ 'properties' ];//通过属性注入的服务
            }
        } else {
            //直接绑定的 #字符串类型的服务别名绑定 比如 $container->setShared('config',Tmac\Config::class);
            $abstract_name = $definition;
            $bind_name = $definition;
        }
        //未设置过共享服务状态，默认不使用共享
        $object = $this->resolveClass( $abstract_name, $params );
        $this->setInstance( $bind_name, $object );
        //setter 注入
        $this->bindMethod( $object, $properties );
        return $object;
    }


    /**
     * 执行函数或者闭包方法 支持参数调用
     * @param string|Closure $function 函数或者闭包
     * @param array $params
     * @return mixed
     * @throws ReflectionException
     */
    private function resolveFunction( $function, array $params = [] )
    {
        try {
            $reflect = new ReflectionFunction( $function );
        } catch ( ReflectionException $e ) {
            throw new FuncNotFoundException( "function not exists: {$function}()", $function, $e );
        }
        //获取到构造函数中的参数依赖
        $args = $this->bindParams( $reflect, $params );
        return $function( ...$args );
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
    private function resolveClass( $abstract, $params )
    {
        try {
            $reflector = new ReflectionClass( $abstract );
        } catch ( ReflectionException $e ) {
            throw new ClassNotFoundException( 'class not exists: ' . $abstract, $abstract, $e );
        }
        // 检查类是否可以实例化
        if ( !$reflector->isInstantiable() ) {
            throw new TmacException( "Class {$abstract} is not instantiable" );
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
        if ( $reflect->hasMethod( 'setDI' ) ) {
            $object->setDI( $this );
        }
        $init_method = '_init';
        if ( $reflect->hasMethod( $init_method ) === false ) {
            return true;
        }
        $method = $reflect->getMethod( $init_method );
        if ( $method->isPublic() === FALSE ) {
            return true;
        }
        $args = $this->bindParams( $method );
        $method->invokeArgs( $object, $args );
        //$method->invoke( $object );


    }


    /**
     * 绑定参数
     * @param ReflectionFunctionAbstract $reflect 反射类
     * @param array $vars 参数
     * @return array
     * @throws ReflectionException
     */
    public function bindParams( ReflectionFunctionAbstract $reflect, array $vars = [] ): array
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

        /*
        add start @20200715 支持 alias 类声明自动注入的别名
        ===========================================
        $container->setShared( 'boat_a', [
            'class' => \App\Service\BoatService::class,
            'alias' => \App\Service\BoatService::class,
            'params' => [
                'fuck1111'
            ]
        ] );
        ===========================================
        public function indexAction( BoatService $boat_a )
        ===========================================
        $container->setShared( 'redisLogger', [
            'class' => Logger::class,
            'alias' => \Psr\Log\LoggerInterface::class,
            'params' => [
                'redis',
                [ new StreamHandler( $container->get( 'app' )->getVarPath() . 'log/monolog/redis_error.log', Logger::WARNING ) ],
                [],
                new DateTimeZone( date_default_timezone_get() ? : 'UTC' )
            ]
        ] );
        ===========================================
        public function indexAction( LoggerInterface $redisLogger )
        ===========================================
        自动注入的解析
        */
        $customize_class_name = $parameter->getClass()->getName() . ' $' . $name;
        if ( $parameter->getClass() != null
            && ( $parameter->getClass()->isInterface() || isset( $this->_bind[ $customize_class_name ] ) ) ) {
            /**
             * 绑定到接口实现。使用接口作为依赖注入的类型
             * public function hello(LoggerInterface $log){}
             */
            $className = $customize_class_name;
        }
        //add end @20200715

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
    public function __call( $name, $arguments = null )
    {
        //get开头表示获取
        if ( strpos( $name, 'get' ) !== false ) {
            $possible_service = lcfirst( substr( $name, 3 ) );
            //如果set 绑定过服务 可以返回
            if ( isset( $this->_bind[ $possible_service ] ) ) {
                if ( count( $arguments ) ) {
                    $object = $this->get( $possible_service, $arguments );
                } else {
                    $object = $this->get( $possible_service );
                }
                return $object;
            }
        }
        //set注入
        if ( strpos( $name, 'set' ) !== false && !empty( $arguments[ 0 ] ) ) {
            $possible_service = lcfirst( substr( $name, 3 ) );
            $this->set( $possible_service, $arguments[ 0 ] );
            return null;
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