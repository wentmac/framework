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
        $field = $aggregate . '(' . $field . ') AS tmac_' . strtolower( $aggregate );

        $result = $this->value( $field, 0 );
        return $force ? (float) $result : $result;
    }

    /**
     * COUNT查询
     * 如果你要使用group进行聚合查询，需要自己实现查询，例如：
     *
     * Db::table('score')->field('user_id,SUM(score) AS sum_score')->group('user_id')->select();
     * $zpStarRepository->select( 'user_id,COUNT(score) as sum_score' )->where( 'id', '>', 2 )->group('user_id')->findAll();     
     *
     * @access public
     * @param string|Raw $field 字段名
     * @return int
     */
    public function count( string $field = '*' ): int
    {
        $where = $this->getOptions( 'where' );
        //if group
        if ( !empty( $this->getOptions( 'group' ) ) ) {
            $field = 'COUNT(DISTINCT ' . $this->getOptions( 'group' ) . ') AS tmac_count';
            $this->removeOption( 'group' );
            $count = $this->value( $field, 0 );
        } else {
            $count = $this->aggregate( 'COUNT', $field );
        }
        //这里count因为后续会有可能接着查询数据，可以复用where条件
        $this->options[ 'where' ] = $where;
        return (int) $count;
    }

    /**
     * SUM查询
     * @access public
     * @param string|Raw $field 字段名
     * @param bool $force 强制转为数字类型
     * @return mixed
     */
    public function sum( $field, bool $force = true )
    {
        return $this->aggregate( 'SUM', $field, $force );
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
     * @param bool $force 强制转为数字类型
     * @return mixed
     */
    public function avg( $field, bool $force = true )
    {
        return $this->aggregate( 'AVG', $field, $force );
    }

}
