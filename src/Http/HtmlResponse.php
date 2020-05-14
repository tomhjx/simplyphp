<?php

namespace Core\Http;

/**
 * 以HTML格式返回
 */
class HtmlResponse extends Response
{
    public function __construct(?string $html, int $status = 200, array $headers = array())
    {
        $this->headers['Content-Type'] =  'text/html';
        parent::__construct($html, $status, $headers);
    }

    public static function create($html = '', $status = 200, $headers = array())
    {
        return new static($html, $status, $headers);
    }

}
