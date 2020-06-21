<?php
declare ( strict_types=1 );

namespace Tmac\Contract;

/**
 * 缓存驱动接口
 */
interface  DatabaseInterface
{

    public function initConnect(bool $master = true);

    /**
     * 连接数据库
     */
    public function connect( array $config );

    /**
     * 选择数据库
     *
     * @param string $database
     */
    public function selectDatabase( $database );

    /**
     * 执行一条SQL查询语句 返回资源标识符
     *
     * @param string $sql
     */
    public function query( $sql );

    /**
     * 执行一条SQL语句 返回似乎执行成功
     *
     * @param string $sql
     */
    public function execute( $sql );

    /**
     * 执行INSERT命令.返回AUTO_INCREMENT
     * 返回0为没有插入成功
     *
     * @param string $sql SQL语句
     * @access public
     * @return integer
     */
    public function insert( $sql );

    /**
     * 关闭数据库
     *
     * @access public
     * @return boolean
     */
    public function close();

    /**
     * 获取错误信息
     *
     * @return void
     * @access public
     */
    public function getError();

}
