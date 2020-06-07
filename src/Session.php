<?php
/**
 * Power By Tmac PHP MVC framework
 * $Author: zhangwentao $  <zwttmac@qq.com>
 * $Id: Config.php 325 2016-05-31 10:07:35Z zhangwentao $
 * http://www.t-mac.org；
 */

namespace Tmac;


use Tmac\Contract\ConfigInterface;

/**
 * SESSION管理类
 * @package think
 */
class Session
{
    use DITrait;

    protected $config;

    /**
     * 构造方法
     * @access public
     */
    public function __construct( ConfigInterface $config )
    {
        $this->config = $config;
    }

    /**
     * session 启动初始化
     * @throws \Exception
     */
    public function init()
    {
        ini_set( 'app.session.auto_start', 0 ); //指定会话模块是否在请求开始时自动启动一个会话。默认为 0（不启动）。
        if ( !empty ( $this->config[ 'app.session.name' ] ) )
            session_name( $this->config[ 'app.session.name' ] ); //默认为 PHPSESSID
        if ( !empty ( $this->config[ 'app.session.path' ] ) )
            session_save_path( $this->config[ 'app.session.path' ] ); //如果选择了默认的 files 文件处理器，则此值是创建文件的路径。默认为 /tmp。
        if ( !empty ( $this->config[ 'app.session.expire' ] ) )
            ini_set( 'app.session.gc_maxlifetime', $this->config[ 'app.session.expire' ] );
        if ( $this->config[ 'app.session.type' ] == 'DB' ) {
            ini_set( 'app.session.save_handler', 'user' );  //默认值是 files
            $hander = $this->container->get( SessionDb::class ); //开始session存放mysql里的相关操作
            $hander->execute();
        } else if ( $this->config[ 'app.session.type' ] == 'memcache' ) {
            ini_set( "session.save_handler", "memcache" );
            ini_set( "session.save_path", "tcp://{$this->config[ 'memcached.host' ]}:{$this->config[ 'memcached.port' ]}" );
        } else if ( $this->config[ 'app.session.type' ] == 'memcached' ) {
            ini_set( "session.save_handler", "memcached" ); // 是memcached不是memcache
            ini_set( "session.save_path", "{$this->config[ 'memcached.host' ]}:{$this->config[ 'memcached.port' ]}" ); // 不要tcp:[/b]
        } else if ( $this->config[ 'app.session.type' ] == 'redis' ) {
            ini_set( "session.save_handler", "redis" ); // 是memcached不是memcache
            ini_set( "session.save_path", "tcp://{$this->config[ 'redis.default.host' ]}:{$this->config[ 'redis.default.port' ]}?auth={$this->config[ 'redis.default.password' ]}" );
        }
        session_start();
    }


}