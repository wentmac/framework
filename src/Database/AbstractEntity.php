<?php
/**
 * 实体类的基类
 * ============================================================================
 * Author: zhangwentao <wentmac@vip.qq.com>
 * Date: 2020/12/4 4:30 下午
 * Created by TmacPHP Framework <https://github.com/wentmac/TmacPHP>
 * http://www.t-mac.org；
 */

namespace Tmac\Database;

use ArrayAccess;
use Tmac\Exception\DbException;

abstract class AbstractEntity
{

    /**
     * 修改器 设置数据对象的值
     * 设置一个默认的__set魔术方法，防止在操作表的实体类的时候，存取不存在的实体类（数据表）字段。
     * @access public
     * @param string $name 名称
     * @param mixed $value 值
     * @return void
     */
    public function __set( string $name, $value ): void
    {
        throw new DbException( 'propertyName:' . $name . ' unset in entity:' . get_called_class() );
    }

    /**
     * 获取器 获取数据对象的值
     * @access public
     * @param string $name 名称
     * @return mixed
     */
    public function __get( string $name )
    {
        throw new DbException( 'propertyName:' . $name . ' unset in entity:' . get_called_class() );
    }
}