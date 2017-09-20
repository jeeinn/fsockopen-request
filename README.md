# fsockopen-request
simulate async request with php fsockopen
PHP模拟异步请求，不返回结果。主要用于定时触发任务使用：

### 例子 Example
```

require ("./httpAsyncRequest.php");

// 连接本地的 Redis 服务
$redis = new Redis();
$connected =  $redis->connect('192.168.99.100', 32769, 5);
if(!$connected) die("connect failed !".PHP_EOL);

$urls = $redis->lRange('cron_urls', 0, -1);
var_dump($urls);

// 这里开始使用
$request = new HttpAsyncRequest();
$request->setProtocolVersion('1.0');
$request->setTimeout(1)->setBlocking(false);
$request->addGetUrls($urls);
$request->success(function ($res){
    var_dump($res);
});
$request->error(function ($err){
    var_dump($err);
});

$request->exec();
```

### 方法 Method

* setProtocolVersion（设置http协议版本号）
    
    仅支持http 1.0、1.1，默认是1.1
    ```
    $request->setProtocolVersion('1.1');
    ```
    
* setTimeout（设置超时时间）
    
    默认是1秒
    ```
    $request->setTimeout('5');
    ```
* setBlocking（设置是否为阻塞模式）
    
    默认是阻塞模式
    ```
    $request->setBlocking(false);
    ```    
* addGetUrls（添加要GET访问的urls）
    
    支持单条url，多条urls请以数组方式传入
    ```
    $request->addGetUrls(['http://jeeinn.com','http://izy123.com']);
    ```        
* addPostUrls（添加要POST访问的urls）
    
    支持单条url，多条urls请以数组方式传入
    ```
    $request->addGetUrls(['http://jeeinn.com','http://izy123.com']);
    ```         
* addCommonParams（添加共同参数）
    
    使用数组传入参数
    ```
        $request->addCommonParams(['hello'=>'world']);
    ```  
* success（添加每个url访问成功回调函数）
    
    可以以匿名方式添加
    ```
    $request->success(function ($res){
                          var_dump($res);
                      });
    // 以下是输出对象
    object(stdClass)[5]
      public 'errorCode' => int 0
      public 'errorInfo' => string '' (length=0)
      public 'data' => 
        array (size=5)
          'method' => string 'POST' (length=4)
          'host' => string '127.0.0.1' (length=9)
          'port' => int 80
          'path' => string '/popen/url_3.php' (length=16)
          'params' => string '' (length=0)

    ```
* error（添加每个url访问失败的回调函数）
    
    同 `success`方法
    
* exec（执行访问）
    最后的执行函数，使用`fsockopen`遍历访问所添加的urls
    ```
    $request->exec();
    ```
    
### 支持链式写法 Chain

```$xslt
$request = new HttpAsyncRequest();
$request->setTimeout(5)
    ->addGetUrls('http://jeeinn.com')
    ->addPostUrls('http://izy123.com')
    ->addCommonParams(['hello'=>'world'])
    ->error(function($res){
        echo $res->errorInfo;
    })
    ->exec();
```

### 场景 Scene

小A想每隔一分钟触发N个可扩展任务URL，目的是保证任务一直在执行，但每个任务耗时可能超过一分钟；
1. 在每个任务开始时标识工作状态，处于工作状态的直接返回；
2. 根据任务情况确认是否增加、减少URL（可操作redis）；
3. 使用本工具每隔一定时间读取URL列表遍历访问

### 待完善功能 TODO

1.可以对每个URL添加不同参数支持（在寻找优雅的方式中）