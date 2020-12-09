<?php
declare ( strict_types=1 );

namespace Tmac\Database\Concern;

/**
 * 聚合查询
 * ============================================================================
 * Trait AggregateQuery
 * @package Tmac\Database\Concern
 * Author: zhangwentao <wentmac@vip.qq.com>
 * Date: 2020/11/3 22:59
 * Created by TmacPHP Framework <https://github.com/wentmac/TmacPHP>
 * http://www.t-mac.org；
 */
trait AggregateQuery
{
    /**
     * 聚合查询
     * @access protected
     * @param string $aggregate 聚合方法
     * @param string $field 字段名
     * @param bool $force 强制转为数字类型
     * @return string|float|integer
     */
    protected function aggregate( string $aggregate, string $field, bool $force = false )
    {
        $field = $aggregate . '(' . $field . ')';

        $result = $this->value( $field, 0 );
        return $force ? (float) $result : $result;
    }

    /**
     * COUNT查询
     * @access public
     * @param string|Raw $field 字段名
     * @return int
     */
    public function count( string $field = '*' ): int
    {
        $count = $this->aggregate( 'COUNT', $field );
        return (int) $count;
    }

    /**
     * SUM查询
     * @access public
     * @param string|Raw $field 字段名
     * @return float
     */
    public function sum( $field ): float
    {
        return $this->aggregate( 'SUM', $field, true );
    }

    /**
     * MIN查询
     * @access public
     * @param string|Raw $field 字段名
     * @param bool $force 强制转为数字类型
     * @return mixed
     */
    public function min( $field, bool $force = true )
    {
        return $this->aggregate( 'MIN', $field, $force );
    }

    /**
     * MAX查询
     * @access public
     * @param string|Raw $field 字段名
     * @param bool $force 强制转为数字类型
     * @return mixed
     */
    public function max( $field, bool $force = true )
    {
        return $this->aggregate( 'MAX', $field, $force );
    }

    /**
     * AVG查询
     * @access public
     * @param string|Raw $field 字段名
     * @return float
     */
    public function avg( $field ): float
    {
        return $this->aggregate( 'AVG', $field, true );
    }

}
