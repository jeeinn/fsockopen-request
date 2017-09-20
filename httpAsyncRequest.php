<?php
/**
 * http异步请求
 * Created by JetBrains PhpStorm.
 * User: jeeinn
 * Date: 17-9-20
 * Time: 上午11:20
 * 参考地址：https://segmentfault.com/q/1010000003990460
 */
class HttpAsyncRequest
{
    const METHOD_GET  = 'GET';
    const METHOD_POST = 'POST';
    const DEFAULT_PORT = 80;
    const PROTOCOL_VERSION_1_1 = '1.1';
    const PROTOCOL_VERSION_1_0 = '1.0';

    public $protocolVersion = '';
    public $connectTimeout = 1;
    public $urlsQueue = array();
    public $commonParams = array();
    public $isBlocking = true;
    public $successCb = null;
    public $errorCb = null;

    public function __construct()
    {
        $this->protocolVersion = self::PROTOCOL_VERSION_1_1;
    }

    /**
     * 设置http协议版本：支持1.0、1.1
     * @param string $version
     * @return $this
     */
    public function setProtocolVersion($version = '1.1')
    {
        switch ($version){
            default:
            case '1.1':
                $this->protocolVersion = self::PROTOCOL_VERSION_1_1;
                break;
            case '1.0':
                $this->protocolVersion = self::PROTOCOL_VERSION_1_0;
                break;
        }
        return $this;
    }

    /**
     * 设置超时时间
     * @param $second
     * @return $this
     */
    public function setTimeout($second)
    {
        $this->connectTimeout = $second;
        return $this;
    }

    /**
     * 添加GET请求urls
     * @param $urls
     * @param array $params
     * @return $this
     */
    public function addGetUrls($urls,$params = array())
    {
        if(is_array($urls)){
            foreach ($urls as $url){
                $this->dealUrl(self::METHOD_GET, $url, $params);
            }
        }else{
            $this->dealUrl(self::METHOD_GET, $urls, $params);
        }
        return $this;
    }

    /**
     * 添加POST请求urls
     * @param $urls
     * @param array $params
     * @return $this
     */
    public function addPostUrls($urls,$params = array())
    {
        if(is_array($urls)){
            foreach ($urls as $url){
                $this->dealUrl(self::METHOD_POST, $url, $params);
            }
        }else{
            $this->dealUrl(self::METHOD_POST, $urls, $params);
        }
        return $this;
    }


    public function addCommonParams($params = array())
    {
        $this->commonParams = http_build_query($params);
        return $this;
    }

    /**
     * 设置是否为阻塞模式
     * @param bool $enabled
     * @return $this
     */
    public function setBlocking($enabled = true)
    {
        if(!$enabled){
            $this->isBlocking = false;
        }
        return $this;
    }
    /**
     * 设置成功回调函数
     * @param $callback
     * @return $this
     */
    public function success($callback){
        $this->successCb = $callback;
        return $this;
    }

    /**
     * 设置失败回调函数
     * @param $callback
     * @return $this
     */
    public function error($callback){
        $this->errorCb = $callback;
        return $this;
    }

    /**
     * 处理url，并保存到待处理队列urlsQueue
     * @param $method
     * @param $url
     * @param $params
     * @return $this
     * @throws Exception
     */
    private function dealUrl($method, $url, $params)
    {
        $urlInfo = parse_url($url);
        if($urlInfo==false){
            throw new Exception('url parse error:' . $url);
        }
        $scheme = $urlInfo['scheme'];
        $host = $urlInfo['host'];
        $port = isset($urlInfo['port']) ? $urlInfo['port'] : self::DEFAULT_PORT;
        $path = isset($urlInfo['path']) ? $urlInfo['path'] : DIRECTORY_SEPARATOR;
        $params = http_build_query($params);
        if($scheme == 'https') {
            $host = 'ssl://' . $host;
        }
        if($method === self::METHOD_GET && strlen($params) > 0){
            $path .= '?' . $params;
        }
        if(strlen($this->commonParams) > 0){
            if(strpos($path, '?') === false){
                $path .= '?' . $this->commonParams;
            }else{
                $path .= '&' . $this->commonParams;
            }
        }
        array_push($this->urlsQueue,array(
            'method'=>  $method,
            'host'  =>  $host,
            'port'  =>  $port,
            'path'  =>  $path,
            'params'=>  $params
        ));
        return $this;
    }

    private function successCb($response)
    {
        if(is_callable($this->successCb)){
            call_user_func_array($this->successCb, array($response));
        }
    }

    private function errorCb($response)
    {
        if(is_callable($this->errorCb)){
            call_user_func_array($this->errorCb,array($response));
        }
    }

    /**
     * 发起http异步请求
     */
    public function exec()
    {
        // 遍历执行请求 iterate request
        foreach ($this->urlsQueue as $item){
            $fp = fsockopen($item['host'], $item['port'], $errorCode, $errorInfo, $this->connectTimeout);
            stream_set_blocking($fp,$this->isBlocking);
            $response = new stdClass();
            $response->errorCode = $errorCode;
            $response->errorInfo = $errorInfo;
            $response->data = $item;
            if($fp === false){
                $this->errorCb($response);
            } else {
                // 处理请求头 deal request header
                $header  = "{$item['method']} {$item['path']} HTTP/$this->protocolVersion\r\n";
                $header .= "Host: {$item['host']}\r\n";
                $header .= "Content-type: application/x-www-form-urlencoded\r\n";
                $item['method'] === self::METHOD_POST && $header .= "Content-Length: " . strlen($item['params']) . "\r\n";
                $header .= "\r\n";
                $item['method'] === self::METHOD_POST && $header .= $item['params'] . "\r\n\r\n";

                if(fwrite($fp, $header) === false || fclose($fp) === false){
                    $this->errorCb($response);
                }
                $this->successCb($response);
            }
        }
    }
}