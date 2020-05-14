<?php

namespace Core\Http;

use RuntimeException;

class Request
{
    const HEADER_FORWARDED = 0b00001; // When using RFC 7239
    const HEADER_X_FORWARDED_FOR = 0b00010;
    const HEADER_X_FORWARDED_HOST = 0b00100;
    const HEADER_X_FORWARDED_PROTO = 0b01000;
    const HEADER_X_FORWARDED_PORT = 0b10000;
    const HEADER_X_FORWARDED_ALL = 0b11110; // All "X-Forwarded-*" headers
    const HEADER_X_FORWARDED_AWS_ELB = 0b11010; // AWS ELB doesn't send X-Forwarded-Host

    const METHOD_HEAD = 'HEAD';
    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';
    const METHOD_PUT = 'PUT';
    const METHOD_PATCH = 'PATCH';
    const METHOD_DELETE = 'DELETE';
    const METHOD_PURGE = 'PURGE';
    const METHOD_OPTIONS = 'OPTIONS';
    const METHOD_TRACE = 'TRACE';
    const METHOD_CONNECT = 'CONNECT';

    /**
     * @var string[]
     */
    protected static $trustedProxies = array();

    /**
     * @var string[]
     */
    protected static $trustedHostPatterns = array();

    /**
     * @var string[]
     */
    protected static $trustedHosts = array();

    protected static $httpMethodParameterOverride = false;

    /**
     * Custom parameters.
     *
     * @var array
     */
    public $attributes;

    /**
     * Request body parameters ($_POST).
     *
     * @var array
     */
    public $request;

    /**
     * Query string parameters ($_GET).
     *
     * @var array
     */
    public $query;

    /**
     * Server and execution environment parameters ($_SERVER).
     *
     * @var array
     */
    public $server;

    /**
     * Uploaded files ($_FILES).
     *
     * @var string
     */
    public $files;

    /**
     * Cookies ($_COOKIE).
     *
     * @var array
     */
    public $cookies;


    /**
     * Request body raw parameters (raw posts).
     *
     * @var string
     */
    public $rawRequest;

    /**
     * Headers (taken from the $_SERVER).
     *
     * @var array
     */
    public $headers;

    /**
     * @var string|resource|false|null
     */
    protected $content;

    /**
     * @var array
     */
    protected $languages;

    /**
     * @var array
     */
    protected $charsets;

    /**
     * @var array
     */
    protected $encodings;

    /**
     * @var array
     */
    protected $acceptableContentTypes;

    /**
     * @var string
     */
    protected $pathInfo;

    /**
     * @var string
     */
    protected $requestUri;

    /**
     * @var string
     */
    protected $baseUrl;

    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var string
     */
    protected $method;

    /**
     * @var string
     */
    protected $format;

    /**
     * @var \Symfony\Component\HttpFoundation\Session\SessionInterface
     */
    protected $session;

    /**
     * @var string
     */
    protected $locale;

    /**
     * @var string
     */
    protected $defaultLocale = 'en';

    /**
     * @var array
     */
    protected static $formats;

    protected static $requestFactory;

    private $isHostValid = true;
    private $isForwardedValid = true;

    private static $trustedHeaderSet = -1;

    private static $forwardedParams = array(
        self::HEADER_X_FORWARDED_FOR => 'for',
        self::HEADER_X_FORWARDED_HOST => 'host',
        self::HEADER_X_FORWARDED_PROTO => 'proto',
        self::HEADER_X_FORWARDED_PORT => 'host',
    );


    /**
     * Create a new Illuminate HTTP request from server variables.
     *
     * @return static
     */
    public static function capture()
    {
        return new static($_GET, $_POST, array(), $_COOKIE, $_FILES, $_SERVER);
    }


    /**
     * @param array                $query      The GET parameters
     * @param array                $request    The POST parameters
     * @param array                $attributes The request attributes (parameters parsed from the PATH_INFO, ...)
     * @param array                $cookies    The COOKIE parameters
     * @param array                $files      The FILES parameters
     * @param array                $server     The SERVER parameters
     * @param string|resource|null $content    The raw body data
     */
    public function __construct(array $query = array(), array $request = array(), array $attributes = array(), array $cookies = array(), array $files = array(), array $server = array(), $content = null)
    {
        $this->initialize($query, $request, $attributes, $cookies, $files, $server, $content);
    }


    /**
     * Sets the parameters for this request.
     *
     * This method also re-initializes all properties.
     *
     * @param array                $query      The GET parameters
     * @param array                $request    The POST parameters
     * @param array                $attributes The request attributes (parameters parsed from the PATH_INFO, ...)
     * @param array                $cookies    The COOKIE parameters
     * @param array                $files      The FILES parameters
     * @param array                $server     The SERVER parameters
     * @param string|resource|null $content    The raw body data
     */
    public function initialize(array $query = array(), array $request = array(), array $attributes = array(), array $cookies = array(), array $files = array(), array $server = array(), $content = null)
    {
        $this->request = $request;
        $this->query = $query;
        $this->attributes = $attributes;
        $this->cookies = $cookies;
        $this->files = $files;
        $this->server = $server;
        $this->content = $content;
        $this->languages = null;
        $this->charsets = null;
        $this->encodings = null;
        $this->acceptableContentTypes = null;
        $this->pathInfo = null;
        $this->requestUri = null;
        $this->baseUrl = null;
        $this->basePath = null;
        $this->method = null;
        $this->format = null;
    }

    /**
     * Return the Request instance.
     *
     * @return $this
     */
    public function instance()
    {
        return $this;
    }

    /**
     * Get the request method.
     *
     * @return string
     */
    public function getMethod()
    {
        if ($this->method) {
            return $this->method;
        }
        if (isset($this->server['REQUEST_METHOD'])) {
            $this->method = strtoupper($this->server['REQUEST_METHOD']);
        } else {
            $this->method = 'GET';
        }
        return $this->method;
    }

    /**
     * Get the current path info for the request.
     *
     * @return string
     */
    public function getPath()
    {
        $uri = $this->server['REQUEST_URI'];
        if (empty($uri)) {
            return '/';
        }
        $path = explode('?', $uri);
        $path = reset($path);
        $path = trim($path,'/');
        if (''==$path) {
            return '/';
        }
        return $path;
    }


    /**
     * Get the current decoded path info for the request.
     *
     * @return string
     */
    public function decodedPath()
    {
        return rawurldecode($this->getPath());
    }

    /**
     * Get all of the segments for the request path.
     *
     * @return array
     */
    public function segments()
    {
        $segments = explode('/', $this->decodedPath());

        return array_values(array_filter($segments, function ($value) {
            return $value !== '';
        }));
    }
    
    /**
     * Get the client user agent.
     *
     * @return string|null
     */
    public function userAgent()
    {
        if (isset($this->server['HTTP_USER_AGENT'])) {
            return $this->server['HTTP_USER_AGENT'];
        }
        return null;
    }

    /**
     *
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function get($key, $default = null)
    {
       if (isset($this->attributes[$key])) {
           return $this->attributes[$key];
       }

       if (isset($this->query[$key])) {
           return $this->query[$key];
       }

       if (isset($this->request[$key])) {
           return $this->request[$key];
       }

       return $default;
    }

    public function gets($keyList=null)
    {
        if (is_null($keyList)) {
            return array_merge(
                $this->attributes, 
                $this->query, 
                $this->request
            );
        }
        $return = [];
        foreach ($keyList as $key => $default) {
            $return[$key] = $this->get($key, $default);
        }
        return $return;
    }

    /**
     * 获取get请求参数
     *
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function getQuery($key, $default = null)
    {
        return isset($this->query[$key]) ? $this->query[$key] : $default;
    }

    public function getQuerys($keyList=null)
    {
        if (is_null($keyList)) {
            return $this->query;
        }
        $return = [];
        foreach ($keyList as $key => $default) {
            $return[$key] = $this->getQuery($key, $default);
        }
        return $return;
    }

    /**
     * 获取post请求参数
     *
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function getPost($key, $default = null)
    {
        return isset($this->request[$key]) ? $this->request[$key] : $default;
    }

    public function getPosts($keyList=null)
    {
        if (is_null($keyList)) {
            return $this->request;
        }
        $return = [];
        foreach ($keyList as $key => $default) {
            $return[$key] = $this->getPost($key, $default);
        }
        return $return;
    }

    public function getRawPosts()
    {
        if (!is_null($this->rawRequest)) {
            return $this->rawRequest;
        }
        $this->rawRequest = file_get_contents('php://input');
        return $this->rawRequest;
    }

    /**
     * 获取header参数
     *
     * @param $key
     * @return null
     */
    public function getHeader($key)
    {
        $key = 'HTTP_'.strtoupper($key);
        if (isset($this->server[$key])) {
            return $this->server[$key];
        }
        return null;
    }

    public function getHost()
    {
        if (empty($this->server['HTTP_HOST'])) {
            return '';
        }
        return $this->server['HTTP_HOST'];
    }

    public function getCookie($name)
    {
        if (isset($this->cookies[$name])) {
            return $this->cookies[$name];
        }
        return null;
    }

}
