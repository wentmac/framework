<?php

/**
 * 参数接收过滤
 * ============================================================================
 * TBlog TBlog博客系统　BY Tmac PHP MVC framework
 * $Author: zhangwentao $  <zwttmac@qq.com>
 * $Id: Input.class.php 398 2016-07-28 09:51:26Z zhangwentao $
 */

namespace Tmac\Plugin;
class Filter
{

    protected $field; //要过滤的参数
    protected $errorMessage; //单个的过滤失败信息
    protected $success = true; //Filter过滤参数的状态
    protected $requiredField = true; //Filter 当前field必选
    protected $failMessage; //最早的一次的失败信息
    
    /**
     * Filter constructor.
     */
    public function __construct()
    {
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
    protected function setErrorMessage( $message )
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
     * @param type $fieldDescription
     * @return type
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
    public function bigint()
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        if ( !preg_match( '/^(\d+)$/', $this->field ) ) {
            return intval( $this->field );
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
     * 过滤掉参数中的html标签
     * $room_id = Input::get('room_title')->required('房屋标题不能为空')->string();
     * @return type
     * @author zhangwentao
     */
    public function string()
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        $this->sqlInjectionFilter();
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
    public function forSearch()
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        $this->sqlInjectionFilter();
        return str_replace( array( '%', '_' ), array( '\%', '\_' ), $this->field );
    }

    /**
     * 验证邮箱是否正确
     * $email = Input::get('email')->required('请输入邮箱地址')->email();
     * @return type
     * @author zhangwentao
     */
    public function email()
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        if ( filter_var( $this->field, FILTER_VALIDATE_EMAIL ) === false ) {
            $this->setErrorMessage( '邮箱格式不正确' );
            return false;
        }
        return $this->field;
    }

    /**
     * 验证用户用户名是否正确
     * $username = Input::get('email')->required('用户名不能为空')->username();
     * @return type
     */
    public function username()
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        $username_len = mb_strwidth( $this->field, 'UTF8' );
        //验证用户所填写的信息是否正确
        if ( $username_len < 4 ) {
            $this->setErrorMessage( '请输入4个字母或2个汉字以上的用户名' );
            return false;
        } elseif ( $username_len > 20 ) {
            $this->setErrorMessage( '用户名不能超过20个字母或10个汉字' );
            return false;
        } elseif ( !preg_match( '/^[\x{4e00}-\x{9fa5}\w-]+$/u', $this->field ) ) {
            $this->setErrorMessage( '用户名只能使用字母数字下划线' );
            return false;
        } elseif ( preg_match( '/^1([3]|[5]|[8])[0-9]{9}$/', $this->field ) ) {
            $this->setErrorMessage( '用户名请使用非手机号码的格式' );
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
    public function password()
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        $password_len = strlen( $this->field );
        $this->sqlInjectionFilter();
        //验证密码是否为空
        if ( empty( $this->field ) ) {
            $this->setErrorMessage( '密码不能为空' );
            return false;
        } elseif ( preg_match( '/\s/', $this->field ) ) {
            $this->setErrorMessage( '密码请勿使用空格' );
            return false;
        } elseif ( $password_len < 6 ) {
            $this->setErrorMessage( '密码太短了，最少6位。' );
            return false;
        } elseif ( $password_len > 16 ) {
            $this->setErrorMessage( '密码太长了，最多16位。' );
            return false;
        }
        return $this->field;
    }

    /**
     * 验证手机号码是否正确
     * $tel = Input::get('tel')->required('请输入手机号码')->tel();
     * @return type
     */
    public function tel()
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        if ( !preg_match( '/^1([3]|[5]|[8]|[4]|[7]|[6]|[9])[0-9]{9}$/', $this->field ) ) {
            $this->setErrorMessage( '手机格式不正确' );
            return false;
        }
        return $this->field;
    }

    /**
     * 时间格式的验证
     * @return type
     */
    public function date()
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        if ( !preg_match( "#\d{4}(-)?\d{1,2}(-)?\d{1,2}#", $this->field ) ) {
            $this->setErrorMessage( '日期格式不正确' );
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
    public function pinyin()
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        if ( !preg_match( '/^([\w+]{1,250})$/', $this->field ) ) {
            $this->setErrorMessage( '参数格式不正确' );
            return false;
        }
        return $this->field;
    }

    /**
     * IP地址格式验证
     * @return boolean
     */
    public function ip()
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        if ( filter_var( $this->field, FILTER_VALIDATE_IP ) === false ) {
            $this->setErrorMessage( 'IP地址格式不正确' );
            return false;
        }
        return $this->field;
    }

    /**
     * URL格式验证
     * @return boolean
     */
    public function url()
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        if ( filter_var( $this->field, FILTER_VALIDATE_URL ) === false ) {
            $this->setErrorMessage( 'URL格式不正确' );
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
    public function sid()
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        if ( !preg_match( '/^([\d+]{6})$/', $this->field ) ) {
            $this->setErrorMessage( '商圈格式不正确' );
            return false;
        }
        return $this->field;
    }

    /**
     * 手机验证码格式
     * @return type
     */
    public function smsCode()
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        if ( !preg_match( '/^[0-9]{6}/', $this->field ) ) {
            $this->setErrorMessage( '验证码格式不正确' );
            return false;
        }
        return $this->field;
    }

    /**
     * 取1,2,3,4,5 int被字符串分割
     * @return type
     */
    public function intString()
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        if ( !preg_match( '/^([\d]+,)*?([\d]+)$/', $this->field ) ) {
            $this->setErrorMessage( '参数格式不正确' );
            return false;
        }
        return $this->field;
    }

    /**
     * 取1,2,3,-4,5 int被字符串分割
     * @return type
     */
    public function intNegativeString()
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        if ( !preg_match( '/^(-?[\d]+,)*?(-?[\d]+)$/', $this->field ) ) {
            $this->setErrorMessage( '参数格式不正确' );
            return false;
        }
        return $this->field;
    }

    /**
     * 取图片尺寸格式
     * @return type
     */
    public function imageSize()
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        if ( !preg_match( '/^(\d+|\d+x\d+)$/', $this->field ) ) {
            $this->setErrorMessage( '图片尺寸格式不正确' );
            return false;
        }
        return $this->field;
    }

    /**
     * 取图片ID格式
     * @return type
     */
    public function imageId()
    {
        if ( $this->requiredField !== true ) {
            return $this->requiredField;
        }
        if ( !preg_match( '/^([a-z0-9]{2}\/[a-z0-9]{2}\/[a-z0-9]{12})$/', $this->field ) ) {
            $this->setErrorMessage( '图片ID参数格式不正确' );
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
