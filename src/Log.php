<?php

/**
 * 日志类
 * Power By Tmac PHP MVC framework
 * $Author: zhangwentao $  <zwttmac@qq.com>
 * $Id: Log.class.php 325 2016-05-31 10:07:35Z zhangwentao $
 * http://www.t-mac.org；
 */

/*
  $this->log->init( 'sms_error' )->level( 'info' )->write('test');
  $this->getDI()->get( 'log' )->init( 'sms_error' )->level( 'info' )->write(
       sprintf(
           '请求【%s】接口出错，请求数据【%s】，错误原因【%s】',
           $url,
           json_encode( $json_params, JSON_UNESCAPED_UNICODE ),
           json_encode( $res, JSON_UNESCAPED_UNICODE )
       )
  );
*/

namespace Tmac;

use Tmac\Contract\ConfigInterface;

class Log
{

    protected $config;
    protected $level = 'INFO'; //默认info
    protected $streamHandler;

    const FOPEN_WRITE_CREATE = 'ab'; //写入方式打开，将文件指针指向文件末尾。如果文件不存在则尝试创建之。 append=true
    const FOPEN_WRITE_CREATE_DESTRUCTIVE = 'wb'; //写入方式打开，将文件指针指向文件头并将文件大小截为零。如果文件不存在则尝试创建之。

    /**
     * log实例
     *
     * @var object
     * @static
     */
    protected $instance = [];


    /**
     * 构造函数
     * @param type $log_name
     */
    public function __construct( ConfigInterface $config )
    {
        $this->config = $config[ 'log' ];
    }

    /**
     * @param $log_name
     * @return array|false
     * @throws \Exception
     */
    public function createStreamHandler( $log_name )
    {
        if ( empty( $this->config[ $log_name ] ) ) {
            return false;
        }
        $logConfigArray = $this->config[ $log_name ];
        preg_match_all( "/(?:\[)(.*)(?:\])/i", $logConfigArray[ 'File' ], $out );
        if ( !empty( $out[ 0 ][ 0 ] ) ) {
            $logConfigArray[ 'File' ] = str_replace( $out[ 0 ][ 0 ], date( $out[ 1 ][ 0 ] ), $logConfigArray[ 'File' ] );
        }
        preg_match_all( "/(?:\[)(.*)(?:\])/i", $logConfigArray[ 'ConversionPattern' ], $out );
        if ( !empty( $out[ 0 ][ 0 ] ) ) {
            $logConfigArray[ 'ConversionPattern' ] = str_replace( $out[ 0 ][ 0 ], date( $out[ 1 ][ 0 ] ), $logConfigArray[ 'ConversionPattern' ] );
        }

        $stream_handler = [];
        $stream_handler[ 'path' ] = $logConfigArray[ 'File' ];
        $stream_handler[ 'conversionPattern' ] = $logConfigArray[ 'ConversionPattern' ];
        $stream_handler[ 'append' ] = $logConfigArray[ 'Append' ];

        $this->checkFolder( dirname( $logConfigArray[ 'File' ] ) );

        return $stream_handler;
    }


    /**
     * @param $log_name
     * @return $this
     */
    public function init( $log_name )
    {
        if ( !isset( $this->instance[ $log_name ] ) ) {
            $this->instance[ $log_name ] = $this->createStreamHandler( $log_name );
        }
        $this->streamHandler = $this->instance[ $log_name ];
        return $this;
    }

    /**
     * 设置level级别
     * @param type $level
     * @return $this;
     */
    public function level( $level )
    {
        $this->level = strtoupper( $level );
        return $this;
    }

    /**
     * 写日志
     * @param $message
     * @return bool
     */
    public function write( $message )
    {
        if ( empty( $this->streamHandler[ 'append' ] ) || empty( $this->streamHandler[ 'conversionPattern' ] ) || empty( $this->streamHandler[ 'path' ] ) ) {
            return false;
        }

        $head = '';

        $append = $this->streamHandler[ 'append' ];
        $conversionPattern = $this->streamHandler[ 'conversionPattern' ];
        $path = $this->streamHandler[ 'path' ];

        $level = $this->level;

        if ( $append ) {
            $file_mode = self::FOPEN_WRITE_CREATE;
        } else {
            $file_mode = self::FOPEN_WRITE_CREATE_DESTRUCTIVE;
        }
        if ( !$fp = fopen( $path, $file_mode ) ) {
            return false;
        }

        $head .= '[' . $conversionPattern . '] ' . $level . ': ';

        flock( $fp, LOCK_EX ); //要取得独占锁定（写入的程序），
        fwrite( $fp, "\n" . $head );
        fwrite( $fp, $message );
        flock( $fp, LOCK_UN ); //要释放锁定（无论共享或独占），
        fclose( $fp );

        chmod( $path, 0666 );

        return true;
    }

    /**
     * 创建目录 递归创建多级目录
     * @param type $filedir
     * @return boolean
     */
    private function checkFolder( $filedir )
    {
        if ( !is_dir( $filedir ) ) {
            if ( !mkdir( $filedir, 0777, true ) ) {
                throw new \Exception( '指定的路径权限不足 =>' . $filedir );
            }
        }
        return true;
    }

}