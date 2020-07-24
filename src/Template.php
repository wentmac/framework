<?php

/**
 * Power By Tmac PHP MVC framework
 * $Author: zhangwentao $  <zwttmac@qq.com>
 * $Id: Template.class.php 325 2016-05-31 10:07:35Z zhangwentao $
 * http://www.t-mac.org；
 * ---------------------------------------------------
 * 模板类 - 类似 Discuz 模板引擎解析 支持PHP5.3 缓存绝对路径做了修改
 * ---------------------------------------------------
 */

namespace Tmac;

use Tmac\Contract\ConfigInterface;
use Tmac\Exception\TmacException;

class Template
{
    const DIR_SEP = DIRECTORY_SEPARATOR;

    private $config;
    /**
     * 模板参数信息
     * @var array
     */
    protected $_options = [];

    /**
     * 模板include进来的文件名数组
     * @var type
     */
    protected $templates = [];

    /**
     * 系统当前时间
     * @var type
     */
    private $time;

    /**
     * 缓存文件是否需要检查过期
     * auto_update=true  的时候需要检查
     * auto_update=false 的时候不需要检查
     * @var bool
     */
    private $cacheFileCheckStatus = false;

    /**
     * 构造方法
     * @return void
     */
    public function __construct( ConfigInterface $config )
    {
        $this->config = $config;
        $template = $config[ 'web_template_path' ] . $config[ 'app.template.template' ] . DIRECTORY_SEPARATOR; //设置系统模板文件的存放目录
        $template_style = $config[ 'app.template.template_style' ];
        $this->_options = array(
            'template' => $template,
            'template_style' => $template_style,//设置模板风格目录名
            'template_dir' => $template . $template_style . DIRECTORY_SEPARATOR, //设置系统模板文件的存放目录
            'cache_dir' => $config[ 'var_path' ] . $config[ 'app.template.cache_dir' ] . DIRECTORY_SEPARATOR . APP_NAME . DIRECTORY_SEPARATOR . $template_style . DIRECTORY_SEPARATOR, //指定缓存文件存放目录
            /**
             * 当模板文件有改动时重新生成缓存 关闭该项会快一些 开发环境打开、生产环境建议关闭 上线模板后删除一下缓存
             * 生产环境打开的情况下，会调用check方法检查模板过期
             * 
             * true:  模板缓存文件中的check方法会调用 如果模板文件有变动会重新生成模板缓存文件
             * 
             * false: 模板缓存文件中的check方法不会调用 每次加载模板缓存时会跳过模板文件的变动检查，性能更高。
             *        适合在生产环境，此设置后模板变动，上线后清空一下模板缓存
             */
            'auto_update' => true, //当模板文件改动时是否重新生成缓存
            'cache_lifetime' => 0, //缓存生命周期(分钟)，为 0 表示永久
            'suffix' => '.html', //模板文件后缀
            /**
             * true: 加载模板时，会判断模板缓存文件是否存在。
             *                    不存在就生存模板缓存文件
             *                    存在就加载模板缓存文件（再根据auto_update状态是否需要check检查模板是否有更新）
             *
             * false: 加载模板时，会跳过判断模板缓存文件存在检查。每次都重新生成模板缓存文件
             */
            'cache_open' => true, //是否开启缓存，程序调试时使用
            'value' => array()
        );
        $this->setTime( $config[ 'now_time' ] );
        $this->setCacheFileCheckStatus();
    }

    private function getTime()
    {
        return $this->time;
    }

    private function setTime( $time )
    {
        $this->time = $time;
    }

    /**
     * 设定模板参数信息
     * @param array $options 参数数组
     * @return void
     */
    public function setOptions( array $options )
    {
        foreach ( $options as $name => $value )
            $this->set( $name, $value );
    }

    /**
     * 设定模板参数
     * @param string $name 参数名称
     * @param mixed $value 参数值
     * @return void
     */
    public function set( $name, $value )
    {
        switch ( $name ) {
            case 'template':
                $this->_options[ 'template' ] = $value;
                break;
            case 'template_style':
                $this->_options[ 'template_style' ] = $value;
                break;
            case 'template_dir':
                $value = $this->trimPath( $value );
                if ( !is_dir( $value ) )
                    throw new TmacException ( "未找到指定的模板目录 \"$value\"" );
                $this->_options[ 'template_dir' ] = $value;
                break;
            case 'cache_dir':
                if ( !is_dir( $value ) ) {
                    $makepath = $this->makePath( $value );
                }
                $this->_options[ 'cache_dir' ] = $value;
                break;
            case 'auto_update':
                $this->_options[ 'auto_update' ] = ( boolean ) $value;
                $this->setCacheFileCheckStatus();
                break;
            case 'cache_lifetime':
                $this->_options[ 'cache_lifetime' ] = ( float ) $value;
                break;
            case 'suffix':
                $this->_options[ 'suffix' ] = $value;
                break;
            case 'cache_open':
                $this->_options[ 'cache_open' ] = $value;
                break;
            case 'value':
                $this->_options[ 'value' ] = $value;
                break;
            default:
                throw new TmacException ( "未知的模板配置选项 \"$name\"" );
        }
    }

    /**
     * 缓存文件是否需要检查过期
     * @return bool 0：每次检查 1：不检查过期
     */
    private function getCacheFileCheckStatus()
    {
        return $this->cacheFileCheckStatus;
    }

    /**
     * 缓存文件是否需要检查过期
     * auto_update=true  的时候需要检查
     * auto_update=false 的时候不需要检查
     */
    private function setCacheFileCheckStatus()
    {
        $this->cacheFileCheckStatus = $this->_options[ 'auto_update' ] ? 0 : 1;
    }

    /**
     * 通过魔术方法设定模板参数
     * @param string $name 参数名称
     * @param mixed $value 参数值
     * @return void
     * @see    Template::set()
     */
    public function __set( $name, $value )
    {
        $this->set( $name, $value );
    }

    /**
     * 缓存是否开启
     * @return boolean
     */
    private function isCacheOpened()
    {
        return $this->_options[ 'cache_open' ];
    }

    /**
     * 获取模板文件
     * @param string $file 模板文件名称
     * @return string
     */
    private function getFile( $file )
    {
        $file = $file . $this->_options[ 'suffix' ];   //设置模板文件后缀
        $cachefile = $this->getCacheFile( $file );
        if ( $this->isCacheOpened() ) {
            if ( !is_file( $cachefile ) )
                $this->cache( $file );
        } else {
            $this->cache( $file );
        }
        return $cachefile;
    }

    /**
     * 打印模板
     * @param string $file 模板名
     */
    public function show( $file )
    {
        $file = $this->getFile( $file );
        if ( is_file( $file ) ) {
            // 模板阵列变量分解成为独立变量
            extract( $this->_options[ 'value' ], EXTR_OVERWRITE );
            include( $file );
        } else {
            throw new TmacException ( '找不到模板文件:' . $file );
        }
    }

    /**
     * 检测模板文件是否需要更新缓存
     * @param string $file 模板文件名称
     * @param type $fname 要检测的模板文件
     * @param type $expireTime 生成模板时间
     * @return type
     */
    private function check( $file, $fname, $expireTime )
    {
        //如果开启当模板文件有改动时重新生成缓存 且 模板文件的最后修改时间大于模板缓存生成时间
        if ( $this->_options[ 'auto_update' ] && filemtime( $fname ) > $expireTime ) {
            $this->cache( $file );
            return true;
        }
        if ( $this->_options[ 'cache_lifetime' ] != 0 && ( $this->getTime() - $expireTime >= $this->_options[ 'cache_lifetime' ] * 60 ) ) {
            $this->cache( $file );
            return true;
        }
        return false;
    }

    /**
     * 对模板文件进行缓存
     * @param string $file 模板文件名称
     * @return void
     */
    private function cache( $file )
    {
        $tplfile = $this->getTplFile( $file );

        if ( !is_readable( $tplfile ) ) {
            throw new TmacException ( "模板文件 \"$tplfile\" 未找到或者无法打开" );
        }
        //取得模板内容
        $template = file_get_contents( $tplfile );

        //开始执行把模板中的include文件读进来
        $this->templates = array();
        for ( $i = 1; $i <= 3; $i++ ) {//最多支持3级嵌套,模板中嵌套层级太多也没有啥意义了
            if ( $this->strExists( $template, '{template_common' ) ) {
                $template = preg_replace_callback( "/[\n\r\t]*(\<\!\-\-)?\{template_common\s+([a-z0-9_:\/]+)\}(\-\-\>)?[\n\r\t]*/is", function ( $matches ) {
                    return $this->loadTemplateCommon( $matches[ 2 ] );
                }, $template );
            }
            if ( $this->strExists( $template, '{template' ) ) {
                $template = preg_replace_callback( "/[\n\r\t]*(\<\!\-\-)?\{template\s+([a-z0-9_:\/]+)\}(\-\-\>)?[\n\r\t]*/is", function ( $matches ) {
                    return $this->loadTemplate( $matches[ 2 ] );
                }, $template );
            }
        }

        //过滤 <!--{}-->
        $template = preg_replace( "/\<\!\-\-\{(.+?)\}\-\-\>/s", "{\\1}", $template );

        //替换语言包变量
        //$template = preg_replace("/\{lang\s+(.+?)\}/ies", "languagevar('\\1')", $template);
        //替换 PHP 换行符
        $template = str_replace( "{LF}", "<?php echo \"\\n\";?>", $template );

        //替换{和} 大小括号
        $template = str_replace( "^{", "&#123", $template );
        $template = str_replace( "}^", "&#125", $template );

        //替换直接变量输出 ||增加了\-\>用来在模板中支持$article->title对象的输出
        //"(\[[a-zA-Z0-9_\-\.\"\'\[\]\$\x7f-\xff]+\])*)";变成"(\[[\$a-zA-Z0-9_\-\.\"\'\[\]\$\x7f-\xff]+)*)";
        //增加了\$用来支持[]中用变量，增加\-\>支持对象，删除\]    支持$room[0]->roomlist->pic 或 $room[$k]->roomlist->pic
        $varRegexp = "((\\\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff\-\>]*)"
            . "(\[[\$a-zA-Z0-9_\-\.\"\'\[\]\$\x7f-\xff\-\>]+)*)";
        //增加了\-\>用来在模板中支持$article->title对象的输出        
        $template = preg_replace( "/\{(\\\$[a-zA-Z0-9_\[\]\'\"\$\.\x7f-\xff\-\>]+)\}/s", "<?php echo \\1;?>", $template );
        $template = preg_replace_callback( "/$varRegexp/s", function ( $matches ) {
            return $this->addQuote( '<?php echo ' . $matches[ 1 ] . ';?>' );
        }, $template );
        $template = preg_replace_callback( "/\<\?php echo \<\?php echo $varRegexp;\?\>;\?\>/s", function ( $matches ) {
            return $this->addQuote( '<?php echo ' . $matches[ 1 ] . ';?>' );
        }, $template );

        //替换前台模板载入命令|edit 通用模板替换{tpl 'admin/index'}
        $template = preg_replace( "/[\n\r\t]*\{tpl\s+(.+?)\}[\n\r\t]*/is", "<?php self::show('{$this->config[ 'app.template.template_dir' ]}/\\1'); ?>", $template );
        //替换特定函数
        $template = preg_replace_callback( "/[\n\r\t]*\{eval\s+(.+?)\}[\n\r\t]*/is", function ( $matches ) {
            return $this->stripTags( '<?php ' . $matches[ 1 ] . ' ?>', '' );
        }, $template );
        /* 替换${} <?php ?> */
        $template = preg_replace_callback( "/[\n\r\t]*\\\${(.+?)\}[\n\r\t]*/is", function ( $matches ) {
            return $this->stripTags( '<?php ' . $matches[ 1 ] . ' ?>', '' );
        }, $template );
        $template = preg_replace_callback( "/[\n\r\t]*\{echo\s+(.+?)\}[\n\r\t]*/is", function ( $matches ) {
            return $this->stripTags( '<?php echo ' . $matches[ 1 ] . '; ?>', '' );
        }, $template );
        $template = preg_replace_callback( "/([\n\r\t]*)\{elseif\s+(.+?)\}([\n\r\t]*)/is", function ( $matches ) {
            return $this->stripTags( $matches[ 1 ] . '<?php } elseif(' . $matches[ 2 ] . ') { ?>' . $matches[ 3 ] . '', '' );
        }, $template );
        $template = preg_replace( "/([\n\r\t]*)\{else\}([\n\r\t]*)/is", "\\1<?php } else { ?>\\2", $template );


        //替换循环函数及条件判断语句
        $template = preg_replace_callback( "/\{if\s+(.+?)\}/is", function ( $matches ) {
            return $this->stripTags( '<?php if(' . $matches[ 1 ] . ') { ?>', '' );
        }, $template );
        $template = preg_replace_callback( "/\{else\}/is", function () {
            return $this->stripTags( '<?php } else { ?>', '' );
        }, $template );
        $template = preg_replace_callback( "/\{elseif\s+(.+?)\}/is", function ( $matches ) {
            return $this->stripTags( '<?php } elseif (' . $matches[ 1 ] . ') { ?>', '' );
        }, $template );
        $template = preg_replace_callback( "/\{\/if\}/is", function () {
            return $this->stripTags( '<?php } ?>', '' );
        }, $template );
        $template = preg_replace_callback( "/\{loop\s+\<\?php echo (\S+);\?\>\s+\<\?php echo (\S+);\?\>\}/is", function ( $matches ) {
            return $this->stripTags( '<?php if(is_array(' . $matches[ 1 ] . ')) foreach(' . $matches[ 1 ] . ' AS ' . $matches[ 2 ] . ') { ?>', '' );
        }, $template );
        $template = preg_replace_callback( "/\{loop\s+\<\?php echo (\S+);\?\>\s+\<\?php echo (\S+);\?\>\s+\<\?php echo (\S+);\?\>\}/is", function ( $matches ) {
            return $this->stripTags( '<?php if(is_array(' . $matches[ 1 ] . ')) foreach(' . $matches[ 1 ] . ' AS ' . $matches[ 2 ] . ' => ' . $matches[ 3 ] . ') { ?>', '' );
        }, $template );
        $template = preg_replace_callback( "/\{\/loop\}/is", function () {
            return $this->stripTags( '<?php } ?>', '' );
        }, $template );

        //常量替换
        $template = preg_replace( "/\{([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)\}/s", "<?php echo \\1; ?>", $template );

        //删除 PHP 代码断间多余的空格及换行
        $template = preg_replace( "/ \?\>[\n\r]*\<\?php /s", " ", $template );

        //其他替换
        $template = preg_replace_callback( "/\"(http)?[\w\.\/:]+\?[^\"]+?&[^\"]+?\"/", function ( $matches ) {
            return $this->transAmp( $matches[ 0 ] );
        }, $template );
        $template = preg_replace_callback( "/\<script[^\>]*?src=\"(.+?)\".*?\>\s*\<\/script\>/is", function ( $matches ) {
            return $this->stripScriptAmp( $matches[ 1 ] );
        }, $template );
        $template = preg_replace_callback( "/[\n\r\t]*\{block\s+([a-zA-Z0-9_]+)\}(.+?)\{\/block\}/is", function ( $matches ) {
            return $this->stripBlock( $matches[ 1 ], $matches[ 2 ] );
        }, $template );


        $headeradd = "\n";
        $headeradd .= '$this->getCacheFileCheckStatus()';
        $headeradd .= "\n";
        $headeradd .= '|| $this->check(' . "'$file', '$tplfile', " . $this->getTime() . ")\n";
        if ( !empty ( $this->templates ) ) {
            foreach ( $this->templates as $fname ) {
                $headeradd .= '|| $this->check(' . "'$file', '$fname', " . $this->getTime() . ")\n";
            }
        }
        $headeradd .= ';';
        $template = "<?php {$headeradd}?>\r\n$template";

        //写入缓存文件
        $cachefile = $this->getCacheFile( $file );
        $makepath = $this->makePath( $cachefile );
        if ( $makepath !== true )
            throw new TmacException ( "无法创建缓存目录 \"$makepath\"" );
        file_put_contents( $cachefile, $template );
    }

    /**
     * 将路径修正为适合操作系统的形式
     * @param string $path 路径名称
     * @return string
     */
    private function trimPath( $path )
    {
        return str_replace( array( '/', '\\', '//', '\\\\' ), self::DIR_SEP, $path );
    }

    /**
     * 获取模板文件名及路径
     * @param string $file 模板文件名称
     * @return string
     */
    private function getTplFile( $file )
    {
        return $this->trimPath( $this->_options[ 'template_dir' ] . self::DIR_SEP . $file );
    }

    /**
     * 获取模板缓存文件名及路径
     * @param string $file 模板文件名称
     * @return string
     */
    private function getCacheFile( $file )
    {
        //$file = preg_replace( '/\.[a-z0-9\-_]+$/i', '.cache.php', $file );
        $file = str_replace( $this->_options[ 'suffix' ], '.cache.php', $file );
        return $this->trimPath( $this->_options[ 'cache_dir' ] . self::DIR_SEP . $file );
    }

    /**
     * 根据指定的路径创建不存在的文件夹
     * @param string $path 路径/文件夹名称
     * @return string
     */
    private function makePath( $path )
    {
        /*      $dirs = explode(self::DIR_SEP, dirname($this->trimPath($path)));
          $tmp = '';
          foreach ($dirs as $dir) {
          $tmp .= $dir . self::DIR_SEP;
          if (!file_exists($tmp) && !@mkdir($tmp, 0777))
          return $tmp;
          } */
        $dirs = dirname( $this->trimPath( $path ) );
        if ( !is_dir( $dirs ) && !mkdir( $dirs, 0777, true ) ) {
            return $dirs;
        }
        return true;
    }

#----------------------------------
//以下是模板替换中需要用到的函数
#----------------------------------

    /**
     * 替换&,&amp;amp;,\" 为正常
     * @param $template 模板文件
     */
    private function transAmp( $template )
    {
        $template = str_replace( '&', '&amp;', $template );
        $template = str_replace( '&amp;amp;', '&amp;', $template );
        $template = str_replace( '\"', '"', $template );
        return $template;
    }

    /**
     * 替换tag中的反斜线
     */
    private function stripTags( $expr, $statement )
    {
        $expr = str_replace( "\\\"", "\"", preg_replace( "/\<\?php echo (\\\$.+?);\?\>/s", "\\1", $expr ) );
        $statement = str_replace( "\\\"", "\"", $statement );
        return $expr . $statement;
    }

    private function addQuote( $var )
    {
        return str_replace( "\\\"", "\"", preg_replace( "/\[([a-zA-Z0-9_\-\.\x7f-\xff]+)\]/s", "['\\1']", $var ) );
    }

    private function stripScriptAmp( $s )
    {
        $s = str_replace( '&amp;', '&', $s );
        return "<script src=\"$s\" type=\"text/javascript\"></script>";
    }

    private function stripBlock( $var, $s )
    {
        $s = str_replace( '\\"', '"', $s );
        $s = preg_replace( "/<\?php echo \\\$(.+?);\?>/", "{\$\\1}", $s );
        preg_match_all( "/<\?php echo (.+?);\?>/e", $s, $constary );
        $constadd = '';
        $constary[ 1 ] = array_unique( $constary[ 1 ] );
        foreach ( $constary[ 1 ] as $const ) {
            $constadd .= '$__' . $const . ' = ' . $const . ';';
        }
        $s = preg_replace( "/<\?php echo (.+?);\?>/", "{\$__\\1}", $s );
        $s = str_replace( '?>', "\n\$$var .= <<<EOF\n", $s );
        $s = str_replace( '<?', "\nEOF;\n", $s );
        return "<?php \n$constadd\$$var = <<<EOF\n" . $s . "\nEOF;\n?>";
    }

    private function strExists( $string, $find )
    {
        return !( strpos( $string, $find ) === FALSE );
    }

    /**
     * include模板并读出其内容
     * @param type $file
     * @return type
     */
    private function loadTemplate( $file )
    {
        $tplfile = $this->_options[ 'template_dir' ] . $file . $this->_options[ 'suffix' ];
        if ( $content = file_get_contents( $tplfile ) ) {
            $this->templates[] = $tplfile;
            return $content;
        } else {
            return '<!-- ' . $file . ' -->';
        }
    }

    /**
     * include公共区域模板并读出其内容
     * @param type $file
     * @return type
     */
    private function loadTemplateCommon( $file )
    {
        $tplfile = $this->_options[ 'template' ] . 'common' . DIRECTORY_SEPARATOR . $file . $this->_options[ 'suffix' ];
        if ( $content = file_get_contents( $tplfile ) ) {
            $this->templates[] = $tplfile;
            return $content;
        } else {
            return '<!-- ' . $file . ' -->';
        }
    }

}