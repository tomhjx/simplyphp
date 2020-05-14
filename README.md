# 索引

* 入门
    * [目录结构](#目录结构)
    * [路由](#路由)
    * [控制器](#控制器)
    * [视图](#视图)
    * [模型](#模型)
    * [数据库](#数据库)
    * [rpc](#rpc)
    * [日志](#日志)
    * [命令行工具](#命令行工具)
    * [计划任务](#计划任务)
    * [配置](#配置)
    * [http缓存](#http缓存)

* 核心架构

    * [常驻进程](#常驻进程)

* 功能

# 目录结构

## 框架结构

```
./
├── tests                             （测试用例）
└── src                         
     ├── Cache                     （缓存相关)  
     ├── Cookies                   （cookies相关)  
     ├── Session                   （会话相关)  
     ├── Database                  （数据库相关)  
     │    └── Connection.php           （数据库连接类，提供CRUD）
     ├── Console                   （命令行相关)  
     │    └── Command.php          （命令行基类）
     ├── Log                       （日志相关)  
     │    └── Logger.php               （日志工具类）
     ├── Http    
     │    ├── Exceptions           （http类型异常处理类）                 
     │    ├── Response.php             （响应对象）
     │    └── Request.php              （请求对象）
     └── Foundation                 
             ├── Controller.php        （控制器基类） 
             ├── Model.php             （模型基类） 
             └── Application.php       （入口启动类）
```

## 应用结构

```

./
├── app                          （应用代码）
│    ├── bootstrap               （目录包含引导框架并配置自动加载的文件）
│    ├── vendor                  （包含 Composer 管理的类库）
│    ├── src                     （应用程序类库）
│    │    ├── Tasks              （计划任务类）
│    │    ├── Models             （模型类）
│    │    ├── Controllers        （控制器类）
│    │    ├── Commands           （命令行工具类）
│    │    ├── Support            （公共方法类）
│    │    └── Exceptions         （异常处理类）
│    └── resources               （视图与资源文件）
│         ├── lang               （语言包）
│         └── views              （视图）
├── data                    （包含应用生成的文件）
│    ├── tmp                （临时文件）
│    ├── cache              （缓存）
│    └── logs               （日志）
├── config                  （配置文件）
├── deploy                  （部署相关）
│    └── database            (存放数据库语句文件）
│         └── 数据库         （以库名命名的目录）
│              └── 数据表.sql（以表名命名的sql文件）
├── tests                   （测试用例）
└── public                  （包含入口文件index.php、可直接访问的css、js等资源文件）


```


# 提供功能

* [x] 控制器
* [x] 模型
* [x] 视图
* [x] 配置统一管理
* [x] 数据库操作
* [x] 命令行工具
* [x] Composer 管理依赖包
* [x] 约定原则
* [x] 日志
* [ ] 日志延迟写
* [ ] cookies
* [ ] session
* [ ] cache
* [x] 计划任务管理
* [ ] 事件
* [ ] 多语言
* [ ] 脚手架
* [ ] 缓存管理
* [ ] 请求方式限制配置


# 起步

## 路由

url：

http://bi.ciyo.work.net/user/auth/login

映射控制器：

```php

\Controllers\User\Auth::loginAction();

```




## 控制器

* 新建一个控制器

```bash

vi app/Controllers/User/Auth.php

```

```php

namespace Controllers\User;

use Core\Foundation\Controller;

Class Auth extends Controller
{
    public function loginAction()
    {
        $request = $this->getRequset();
        $data = [
            'uname'=>$request->getPost('uname'),
            'pwd'=>$request->getPost('pwd')
        ];
        return $this->json($data);
    }

}

```

*  发起POST请求


```bash
$ curl -i -x 127.0.0.1:80 -d "uname=aaaa&&pwd=123"   "http://bi.ciyo.work.net/user/auth/login" 

```



*  返回

```json

{"uname":"aaaa","pwd":"123"}

```

* 获取请求参数

请求方式 | 控制器提供的调用方式
--------|----
GET     |  $this->getRequset()->getQuery
POST    |  $this->getRequset()->getPost
GET/POST|  $this->getRequset()->get


## 视图

## 模型

在控制器中调用模型

```php

$order = \App\Models\Business\House\Order::getInstance();

```

```bash
vi app/Models/User/Account.php
```

```php

namespace Models\User;

use Core\Foundation\Model;

Class Account extends Model
{
    public function login($account, $password)
    {}
}


```

## 数据库


### 配置

`app/config/db.php`

执行sql前需要获取数据库连接，通过以下方式获取

```php
$connection = $this->getApp()->getDataBaseConnection('配置key名');
```



* 执行自定义sql，并返回结果

```php

// show create table a
$connection->fetchOne('show create table a');
```


```php

// show create table a
$connection->fetchAll('show create table a');
```


```php

// select c1, c2 from a where id=1

$connection->fetchAll('select c1, c2 from a where id=?', [1]);
```



* 执行自定义sql，仅返回影响行数

```php

// show create table a
$connection->execute('show create table a');
```





### 查询


```php

// select id,ctime from mytable where id in (1,2,3)

$connection->select('mytable', ['id', 'ctime'], ['id'=> [1,2,3]]);
```

```php

// select id,ctime from mytable where id > 33

$connection->select('mytable', ['id', 'ctime'], 'id>?', [33]);

```

```php

// select id,ctime from mytable where id > 33 limit 100

$connection->select('mytable', ['id', 'ctime'], 'id>?', [33], 100);

```



```php

// select id,ctime from mytable where id > 33 order by id desc limit 100

$connection->select('mytable', 
              ['id', 'ctime'], 'id>?', [33], ['id'=>'desc'], 100);

```


```php

// select id,ctime from mytable where id > 33 order by id desc limit 1,100

$connection->select('mytable', 
             ['id', 'ctime'], 'id>?', [33], ['id'=>'desc'], [1, 100]);

```






### 新增

```php

// insert into mytable (id, ctime) values (1, 1111)

$connection->insert('mytable', ['id'=>1, 'ctime'=>1111]);
```

```php

// insert IGNORE into mytable (id, ctime) values (1, 1111)

$connection->insert('mytable', ['id'=>1, 'ctime'=>1111], true);
```

```php

// insert into mytable (id, ctime) values (1, 1111), (2, 2222), (3, 3333)

$connection->insertBatch('mytable', [ ['id'=>1, 'ctime'=>1111], ['id'=>2, 'ctime'=>2222], ['id'=>3, 'ctime'=>3333]]);
```

```php

// insert IGNORE into mytable (id, ctime) values (1, 1111), (2, 2222), (3, 3333)

$connection->insertBatch('mytable', 
      [ ['id'=>1, 'ctime'=>1111], ['id'=>2, 'ctime'=>2222], ['id'=>3, 'ctime'=>3333]], true);

```




```php

// 新增后返回新增记录id

$connection->insertRetLastId('mytable', ['id'=>1, 'ctime'=>1111]);

```

```php

// insert into mytable (id, ctime) values (1, 1111), (2, 2222)  
// on duplicate key update ctime = values(ctime)

$connection->insertBatchDuplicateKeyUpdate(
                'mytable',
                [['id'=>1, 'ctime'=>1111], ['id'=>2, 'ctime'=>2222]], 
                ['ctime'=>'values(ctime)']
             );

```








### 更新


```php

// update mytable set ctime=1234 where id = 1

$connection->update('mytable', ['ctime'=>1234], ['id'=>1]);

```

```php

// update mytable set ctime=1234 where id in (1,2,3)

$connection->update('mytable', ['ctime'=>1234], ['id'=>[1,2,3] ]);

```

```php

// update mytable set ctime=1234 where id > 1
$connection->update('mytable', ['ctime'=>1234], 'id>?', [1]);



```



### 删除

```php

// delete from mytable where id > 1
$connection->delete('mytable', 'id>?', 1);



```

```php

// delete from mytable where id in (1,2,3)
$connection->delete('mytable', ['id'=>[1,2,3]]);



```

```php

// delete from mytable where id between 100 and 200

$connection->delete('mytable', 'id between ? and ?', [100, 200]);



```








## rpc

### 客户端

* 调用服务配置

 ./config/dev/rpc.php

配置项 | 备注
------|---
host | rpc服务主机域名或ip

```php

<?php

return [
    'hotel' => [
        'host' => 'hotel.hiii-life.rpc',
    ],
    'order' => [
        'host' => 'order.hiii-life.rpc',
    ],
    'product' => [
        'host' => 'product.hiii-life.rpc',
    ],
    'supplier' => [
        'host' => 'supplier.hiii-life.rpc',
    ],
];

```

* 调用服务

调用服务`http://hotel.hiii-life.rpc/Hotel/Info/detail`

```php

$client = \Core\Rpc\Client::getInstance();
$client->call('hotel', ['Hotel', 'Info', 'detail'], ['aa']);

```

* 客户端传递 header

```php
# 代码片段
$client = \Core\Rpc\Client::getInstance();
$client->setHeader('appid', 1001);
$client->setHeader('appname', 'xgo-driver');

return $client->call('config', ['Geo', 'CallingCode', 'list'], []);
```

### 服务端


* nginx配置

```


server {
    listen 80;
    server_name hotel.hiii-life.rpc;
    root /data1/src/web/api.hotel.hiii-life.com/public;
    location / {
        rewrite ^(.*)$ /rpc.php?$query_string  last;
    }
    
    location = /rpc.php {
        fastcgi_intercept_errors on;
        fastcgi_pass            hotel.api.fpm;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include                 fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param           HTTPS off;
    }

    access_log  /data1/log/nginx/hotel.hiii-life.rpc-access.log;
    error_log  /data1/log/nginx/hotel.hiii-life.rpc-error.log;
}


```



* 接口代码编写

./app/src/RpcControllers/Hotel/Info.php

```php

<?php
namespace App\RpcControllers\Hotel;

use App\Exceptions\LangException;

class Info extends \Core\Foundation\RpcController
{
    public function detailAction($id)
    {
        return ['id'=>$id, 'where'=>__DIR__];
    }

    public function listAction($page=0, $size=100)
    {
        return ['list'=>[], 'page'=>$page, 'size'=>$size];
    }

    public function exceptionAction($type=1)
    {
        throw new LangException('house', 103005012);
    }

    public function sleepAction($sec=3)
    {
        \sleep($sec);
        return $sec;
    }

}


```


## 配置

配置文件存放在 `app/config/` 下，配置格式以php数组存在


 配置文件名        |参数名                        |  备注
-----------------|-----------------------------|---
 db              |host                         | 数据库域名或ip
 db              |port                         | 数据库端口
 db              |user                         | 访问数据库的用户名
 db              |password                     | 访问数据库的用户密码
 db              |database                     | 数据库名称
 db              |options                      | 驱动参数，目前仅支持pdo驱动
 crossOrigin     |jsonpCallbackUrlParamName    | jsonp callback参数名
 crossOrigin     |corsAllowHeaders             | cors 允许访问的请求header

```php

$configs = $this->getApp()->getConfig('db');


```




## 日志
 log             |class                        | 日志工具类名，默认`\Core\Log\Logger`，自定义的日志类则需要实现方法`write`
 log             |level                        | 记录日志的级别，高于指定级别的日志将被记录，默认是`error`




使用方式




```php

$this->getApp()->getLogger()->debug('test');

```

`storage/logs/debug/20180904.log`



## 命令行工具

```bash

$ ./console DismantleOrderDays

```

定义对应的工具脚本：

`app/Commands/DismantleOrderDays.php`

```php

<?php

namespace App\Commands;

use Core\Console\Command;

class DismantleOrderDays extends Command
{
    public function run()
    {
        \App\Models\Business\House\Order::getInstance()
        ->dismantleDays();
    }
}


```


## 计划任务

### 执行单个定时器


```bash

$ ./console core:task.run check

```


### 执行所有定时器

```bash

$ ./console core:schedule.run

```

任务类存放在应用项目的 `app/Tasks` 里


* 每小时执行

```php

<?php

namespace App\Tasks;

use Core\Console\Task;

class Demo extends Task
{
    public function run()
    {
        echo "hi";
    }

    public function getInterval()
    {
        return 3600;
    }

}


```


* 每天3点执行（每天至少在3点之后执行1次）

```php

<?php

namespace App\Tasks;

use Core\Console\Task;

class Demo extends Task
{
    public function run()
    {
        echo "hi";
    }

    public function getHour()
    {
        return 3;
    }
}


```


### http缓存

* 在入口文件定义默认缓存时间

`./pulbic/index.php`

```php

<?php
$app = include __DIR__ . '/../app/bootstrap/instance.php';
$kernel = new \Core\Http\Kernel();
// 缓存一分钟
$kernel->setCacheExpires(60);
$app->run($kernel);

```

* 在接口层控制缓存时间

```php

public function myAction()
{
    // 设置1分钟缓存
    $this->setCacheExpires(60);
}


```



# 核心架构

## 常驻进程
1. 配置文件

```sh
vi ~/Work/src/web/api.admin.hiii-life.com/config/common/always.php
```

2. 命令行传入参数

在`run`方法接收参数:
$this->getArgument('params');

使用方法:
./consle Test -params=123;
