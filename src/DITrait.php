<?php
/**
 * Created by PhpStorm.
 * User: wentao
 * Date: 2018/11/28
 * Time: 9:49 PM
 */

namespace Tmac;

trait DITrait
{

    /**
     * 直接在调用的method中使用$this->getDI()方法来取得Service实例，方便资源的更高效的使用。只在用的时候调用需要的Service，减少不必要的加载
     * 比如
     * $articleRepository = $this->getDI()->get( ArticleRepository::class ); //每次调用都会实例化
     * $articleRepository = $this->getDI()->getShare( ArticleRepository::class ); //调用容器中共享的服务，只会实例化一次
     *
     * 相比于在__construct的自动注入方式，构造方法中自动注入更适用于整个Class中所有的method都能用到的服务。可以这样注入，这样不会造成不需要的实例化。
     *
     * public function __construct( ConfigInterface $config,ArticleRepository $articleRepository RedisCache $redis ){}
     *
     * @return Container
     *
     */
    public function getDI()
    {
        return ApplicationContext::getContainer();
    }

}