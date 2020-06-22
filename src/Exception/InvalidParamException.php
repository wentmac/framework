<?php

namespace Tmac\Exception;

use Exception;


class InvalidParamException extends Exception
{
    const INVALID_PARAM = 1000;

    /**
     * Bootstrap.
     *
     * @param string $message
     * @param int $code
     * @param array|string $raw
     * @param null|Throwable $previous
     * @author yansongda <me@yansongda.cn>
     *
     */
    public function __construct( string $message = '', int $code = InvalidParamException::INVALID_PARAM )
    {
        if ( empty( $message ) ) {
            $message = '无效参数';
        }
        parent::__construct( $message, $code );
    }
}
