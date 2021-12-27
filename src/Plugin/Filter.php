<?php

/**
 * 参数接收过滤
 * ============================================================================
 * TBlog TBlog博客系统　BY Tmac PHP MVC framework
 * $Author: zhangwentao $  <zwttmac@qq.com>
 * $Id: Input.class.php 398 2016-07-28 09:51:26Z zhangwentao $
 */

namespace Tmac\Plugin;

use Tmac\Contract\ConfigInterface;

class Filter
{

    protected $field; //要过滤的参数
    protected $errorMessage; //单个的过滤失败信息
    protected $success = true; //Filter过滤参数的状态
    protected $requiredField = true; //Filter 当前field必选
    protected $failMessage; //最早的一次的失败信息

    protected $config;

    /**
     * Filter constructor.
     */
    public function __construct( ConfigInterface $config )
    {
        $this->config = $config;
    }


    /**
     * 取Filter类中的错误信息
     * @return type
     */
    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    /**
     * 取Filter类中的最早的错误信息
     * @return type
     */
    public function getFailMessage()
    {
        return $this->failMessage;
    }

    /**
     * 取Filter类中的成功状态
     * --------------------
     *   if (Filter::getStatus() === false) {
     *       echo Filter::getFailMessage();
     *   }
     * --------------------
     * @return type
     */
    public function getStatus()
    {
        return $this->success;
    }

    /**
     * 设置验证出错消息
     * @param type $message
     * @author zhangwentao
     */
    protected function setErrorMessage( string $message )
    {
        $this->errorMessage = $message;
        if ( $this->success === true ) {
            $this->success = false;
            $this->failMessage = $message;
        }
    }

    /**
     * 设置字段值
     * @param type $message
     * @author zhangwentao
     */
    public function setField( $value )
    {
        $this->field = $value;
        if ( empty( $value ) ) {
            $this->requiredField = $value;
        } else {
            $this->requiredField = true;
        }
    }

    /**
     * 是否必选字段方法
     * @param string $fieldDescription
     * @return $this
     */
    public function required( $fieldDescription = '' )
    {
        if ( !empty( $fieldDescription ) && empty( $this->field ) ) {//如果有不能为空限制
            $this->setErrorMessage( $fieldDescription );
            $this->requiredField = false;
        }
        return $this;
    }

    /**
     * 取int形式的参数
     * $room_id = Input::get('room_id')->required('房屋ID不能为空')->int();
     * @return type
     * @author zhangwentao
     */
    public function int()
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        return intval( $this->field );
    }

    /**
     * 取长int形式的参数
     * $room_id = Input::get('room_id')->required('房屋ID不能为空')->bigint();
     * @return type
     * @author zhangwentao
     */
    public function bigint( string $error_message = '' )
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        if ( !preg_match( '/^(\d+)$/', $this->field ) ) {
            $message = empty( $error_message ) ? '数字格式不正确' : $error_message;
            $this->setErrorMessage( $message );
            return false;
        }
        return $this->field;
    }

    /**
     * 取float形式的参数
     * $room_id = Input::get('baidu_lat')->required('百度经纬度不能为空')->float();
     * @return type
     * @author zhangwentao
     */
    public function float()
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        return floatval( $this->field );
    }


    /**
     * @return
     */
    public function number( string $error_message = '' )
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        if ( !is_numeric( $this->field ) ) {
            $message = empty( $error_message ) ? '非数字格式' : $error_message;
            $this->setErrorMessage( $message );
            return false;
        }
        return $this->field;
    }

    /**
     * 过滤掉参数中的html标签
     * $room_id = Input::get('room_title')->required('房屋标题不能为空')->string();
     * @return string
     * @author zhangwentao
     */
    public function string( bool $usePdo = true )
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        $usePdo === false && $this->sqlInjectionFilter();
        return htmlspecialchars( $this->field, ENT_QUOTES );
    }

    /**
     * 过滤掉GET/POST参数中的sql注入
     * $room_id = Input::get('room_title')->required('房屋标题不能为空')->string();
     * @return type
     * @author zhangwentao
     */
    public function sql()
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        $this->sqlInjectionFilter();
        return $this->field;
    }

    /**
     * 处理字符串，以便可以正常进行搜索
     * $search = Input::get('room_title')->required('房屋标题不能为空')->forSearch();
     * @access public
     * @return string
     */
    public function forSearch( bool $usePdo = true )
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        $usePdo === false && $this->sqlInjectionFilter();
        return str_replace( array( '%', '_' ), array( '\%', '\_' ), $this->field );
    }

    /**
     * 验证邮箱是否正确
     * $email = Input::get('email')->required('请输入邮箱地址')->email();
     * @return type
     * @author zhangwentao
     */
    public function email( string $error_message = '' )
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        if ( filter_var( $this->field, FILTER_VALIDATE_EMAIL ) === false ) {
            $message = empty( $error_message ) ? '邮箱格式不正确' : $error_message;
            $this->setErrorMessage( $message );
            return false;
        }
        return $this->field;
    }

    /**
     * 验证用户用户名是否正确
     * $username = Input::get('email')->required('用户名不能为空')->username();
     * @return type
     */
    public function username( string $error_message = '' )
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        if ( !preg_match( '/^[a-zA-Z0-9_-]{4,20}$/', $this->field ) ) {
            $message = empty( $error_message ) ? '用户名格式：4到20位（字母，数字，下划线，减号）' : $error_message;
            $this->setErrorMessage( $message );
            return false;
        }
        return $this->field;
    }

    /**
     * 验证用户用户名是否正确
     * $username = Input::get('email')->required('用户名不能为空')->username();
     * @return type
     */
    public function nickname( string $error_message = '' )
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        $username_len = mb_strwidth( $this->field, 'UTF8' );
        //验证用户所填写的信息是否正确
        if ( $username_len < 4 ) {
            $message = empty( $error_message ) ? '请输入4个字母或2个汉字以上的昵称' : $error_message;
            $this->setErrorMessage( $message );
            return false;
        } elseif ( $username_len > 20 ) {
            $message = empty( $error_message ) ? '昵称不能超过20个字母或10个汉字' : $error_message;
            $this->setErrorMessage( $message );
            return false;
        } elseif ( !preg_match( '/^[\x{4e00}-\x{9fa5}\w-]+$/u', $this->field ) ) {
            $message = empty( $error_message ) ? '昵称只能使用字母数字下划线' : $error_message;
            $this->setErrorMessage( $message );
            return false;
        } elseif ( preg_match( '/^1([3]|[5]|[8])[0-9]{9}$/', $this->field ) ) {
            $message = empty( $error_message ) ? '昵称请使用非手机号码的格式' : $error_message;
            $this->setErrorMessage( $message );
            return false;
        }
        return $this->field;
    }

    /**
     * 密码验证方法
     * $password = Input::get('password')->required('用户名不能为空')->password();
     * @param type $password
     * @return type
     */
    public function password( bool $usePdo = true, string $error_message = '' )
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        $password_len = strlen( $this->field );
        $usePdo === false && $this->sqlInjectionFilter();
        //验证密码是否为空
        if ( empty( $this->field ) ) {
            $message = empty( $error_message ) ? '密码不能为空' : $error_message;
            $this->setErrorMessage( $message );
            return false;
        } elseif ( preg_match( '/\s/', $this->field ) ) {
            $message = empty( $error_message ) ? '密码请勿使用空格' : $error_message;
            $this->setErrorMessage( $message );
            return false;
        } elseif ( $password_len < 6 ) {
            $message = empty( $error_message ) ? '密码太短了，最少6位。' : $error_message;
            $this->setErrorMessage( $message );
            return false;
        } elseif ( $password_len > 20 ) {
            $message = empty( $error_message ) ? '密码太长了，最多20位。' : $error_message;
            $this->setErrorMessage( $message );
            return false;
        }
        return $this->field;
    }

    /**
     * 验证手机号码是否正确
     * $tel = Input::get('tel')->required('请输入手机号码')->tel();
     * @return type
     */
    public function tel( string $error_message = '' )
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        if ( !preg_match( '/^1([3]|[5]|[8]|[4]|[7]|[6]|[9])[0-9]{9}$/', $this->field ) ) {
            $message = empty( $error_message ) ? '手机格式不正确' : $error_message;
            $this->setErrorMessage( $message );
            return false;
        }
        return $this->field;
    }

    /**
     * 时间格式的验证
     * @return type
     */
    public function date( string $error_message = '' )
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        if ( !preg_match( "/^\d{4}(-)?\d{2}(-)?\d{2}$/", $this->field ) ) {
            $message = empty( $error_message ) ? '日期格式不正确' : $error_message;
            $this->setErrorMessage( $message );
            return false;
        }
        return $this->field;
        //return zhuna_input_Transverter('date', $this->field);
    }

    /**
     * 时间格式的验证
     * @return type
     */
    public function datetime( string $error_message = '' )
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        if ( !preg_match( "/^\d{4}(-)\d{2}(-)\d{2}\s+\d{2}:\d{2}:\d{2}$/", $this->field ) ) {
            $message = empty( $error_message ) ? '日期格式不正确' : $error_message;
            $this->setErrorMessage( $message );
            return false;
        }
        return $this->field;
        //return zhuna_input_Transverter('date', $this->field);
    }

    /**
     * 过滤拼音
     * @param type $pinyin
     * @return type
     */
    public function pinyin( string $error_message = '' )
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        if ( !preg_match( '/^([\w+]{1,250})$/', $this->field ) ) {
            $message = empty( $error_message ) ? '参数格式不正确' : $error_message;
            $this->setErrorMessage( $message );
            return false;
        }
        return $this->field;
    }

    /**
     * IP地址格式验证
     * @return boolean
     */
    public function ip( string $error_message = '' )
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        if ( filter_var( $this->field, FILTER_VALIDATE_IP ) === false ) {
            $message = empty( $error_message ) ? 'IP地址格式不正确' : $error_message;
            $this->setErrorMessage( $message );
            return false;
        }
        return $this->field;
    }

    /**
     * URL格式验证
     * @return boolean
     */
    public function url( string $error_message = '' )
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        if ( filter_var( $this->field, FILTER_VALIDATE_URL ) === false ) {
            $message = empty( $error_message ) ? 'URL格式不正确' : $error_message;
            $this->setErrorMessage( $message );
            return false;
        }
        return $this->field;
    }

    /**
     * 过滤cityid
     * @return type
     */
    public function cityid()
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        return $this->city( '城市' );
    }

    /**
     * 过滤pid
     * @return type
     */
    public function pid()
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        return $this->city( '省级' );
    }

    /**
     * 过滤aid
     * @return type
     */
    public function aid()
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        return $this->city( '行政区' );
    }

    /**
     * 过滤sid
     * @return type
     */
    public function sid( string $error_message = '' )
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        if ( !preg_match( '/^([\d+]{6})$/', $this->field ) ) {
            $message = empty( $error_message ) ? '商圈格式不正确' : $error_message;
            $this->setErrorMessage( $message );
            return false;
        }
        return $this->field;
    }

    /**
     * 手机验证码格式
     * @return type
     */
    public function smsCode( string $error_message = '' )
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        if ( !preg_match( '/^[0-9]{6}/', $this->field ) ) {
            $message = empty( $error_message ) ? '商圈格式不正确' : $error_message;
            $this->setErrorMessage( $message );
            return false;
        }
        return $this->field;
    }

    /**
     * 取1,2,3,4,5 int被字符串分割
     * @return type
     */
    public function intString( string $error_message = '' )
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        if ( !preg_match( '/^([\d]+,)*?([\d]+)$/', $this->field ) ) {
            $message = empty( $error_message ) ? '参数格式不正确' : $error_message;
            $this->setErrorMessage( $message );
            return false;
        }
        return $this->field;
    }

    /**
     * 取1,2,3,-4,5 int被字符串分割
     * @return type
     */
    public function intNegativeString( string $error_message = '' )
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        if ( !preg_match( '/^(-?[\d]+,)*?(-?[\d]+)$/', $this->field ) ) {
            $message = empty( $error_message ) ? '参数格式不正确' : $error_message;
            $this->setErrorMessage( $message );
            return false;
        }
        return $this->field;
    }

    /**
     * 取图片尺寸格式
     * @return type
     */
    public function imageSize( string $error_message = '' )
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        if ( !preg_match( '/^(\d+|\d+x\d+)$/', $this->field ) ) {
            $message = empty( $error_message ) ? '参数格式不正确' : $error_message;
            $this->setErrorMessage( $message );
            return false;
        }
        return $this->field;
    }

    /**
     * 取图片ID格式
     * @return type
     */
    public function imageId( string $error_message = '' )
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        if ( !preg_match( '/^([a-z0-9]{2}\/[a-z0-9]{2}\/[a-z0-9]{12})$/', $this->field ) ) {
            $message = empty( $error_message ) ? '图片ID参数格式不正确' : $error_message;
            $this->setErrorMessage( $message );
            return false;
        }
        return $this->field;
    }

    /**
     * 过滤字符串数组  传的参数要用都(,)号把每个值分开
     * @return boolean
     */
    public function stringList()
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }

        $tmp = array();
        if ( empty( $this->field ) ) {
            return false;
        } else {
            empty( $this->field ) || $this->field = preg_replace( '/[^0-9,a-zA-Z-|^_\x80-\xff ]+/i', '', trim( $this->field, ',' ) );
            if ( empty( $this->field ) ) {
                return false;
            } elseif ( strpos( $this->field, ',' ) !== false ) {
                $tmp = explode( ',', $this->field );
                foreach ( $tmp as $k => &$v ) {
                    if ( empty( $v ) ) {
                        unset( $tmp [ $k ] );
                    }
                }
            } else {
                $tmp = array( $this->field );
            }
        }
        return $tmp;
    }

    /**
     * 返回数组格式
     * @return array|bool
     */
    public function getArray( string $error_message = '' )
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        if ( !is_array( $this->field ) ) {
            $message = empty( $error_message ) ? '非数组格式' : $error_message;
            $this->setErrorMessage( $message );
            return false;
        }
        return $this->field;
    }

    /**
     * @return
     */
    public function getValue()
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        return $this->field;
    }

    /**
     * 图片上传尺寸，格式校验
     * @param array $validate
     * @return mixed
     */
    public function checkFileImage( $validate = [] )
    {
        $file = $this->file( 'image_file', $validate );
        if ( $file === false ) {
            return false;
        }

        $imageMd5 = md5_file( $file->getPathname() );
        $imageMd5 = substr( $imageMd5, 8, 16 );
        $imageId = substr( $imageMd5, 0, 2 ) . '/' . substr( $imageMd5, 2, 2 ) . '/' . substr( $imageMd5, 4 ); //$imageId=a2/xs/sajiknilijklkjj
        return $imageId;
    }


    /**
     * 文件上传尺寸，格式校验
     * @param string $type
     * @param array $validate
     * @return bool|UploadedFile
     */
    protected function file( $type = 'image_file', $validate = [] )
    {
        if ( empty( $this->field ) || !( $this->field instanceof UploadedFile ) ) {
            $this->setErrorMessage( 'file type error' );
            return false;
        }
        if ( empty( $validate ) ) {
            $validate_array = $this->config[ 'app.validate' ];
            $validate = $validate_array[ $type ];
        }
        if ( empty( $validate[ 'file_size' ] ) || empty( $validate[ 'file_mime' ] ) || !is_array( $validate[ 'file_mime' ] ) ) {
            $this->setErrorMessage( 'file validate file_size or file_mine invalid' );
            return false;
        }
        $file_size = $validate[ 'file_size' ];
        $file_mime = $validate[ 'file_mime' ];

        /* @var $file UploadedFile */
        $file = $this->field;
        if ( $file->getSize() > $file_size ) {
            $file_size_format = $file_size / 1024 / 1024;
            $this->setErrorMessage( "上传文件最大尺寸{$file_size_format}mb" );
            return false;
        }
        if ( !in_array( $file->getOriginalMime(), $file_mime ) ) {
            $file_mime_format = implode( ',', $file_mime );
            $this->setErrorMessage( "允许的文件类型：{$file_mime_format}" );
            return false;
        }
        return $file;
    }

    /**
     * 过滤 pid aid cityid
     * @return type
     */
    private function city( $name = '城市' )
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        if ( !preg_match( '/^([\d+]{4})$/', $this->field ) ) {
            $this->setErrorMessage( $name . '格式不正确' );
            return false;
        }
        return $this->field;
    }

    /**
     * sql注入语句过滤
     * @return boolean
     */
    private function sqlInjectionFilter()
    {
        $_pattern = array( '/waitfor%20delay/', '/waitfor delay/', '/select/i', '/\(/', '/\)/', '/insert/i', '/update/i', '/delete/i', '/\'/', '/\//', '/\.\.\//', '/\.\//', '/union/i', '/into/i', '/load_file/i', '/outfile/i', '/\'/', '/&#039;/', '/--/', '/%27/', '/%22/', '/%5c/i' );
        $_replacement = array( '', '', '\select', '（', '）', '\insert', '\update', '\delete', '\'', '\/', '\.\.\/', '\.\/', '\union', '\into', '\load_file', '\outfile', '‘', '‘', '——', '\\\'', '\"', '\\\\\\' );
        $this->field = preg_replace( $_pattern, $_replacement, $this->field );
        return true;
    }

}
