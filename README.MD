Phpple Mysql
=============

`Phpple Mysql`一个基于PHP语言的Mysql类库，具有使用简单、操作优雅、代码严谨、效率优先等诸多优点。

[![Latest Stable Version](https://img.shields.io/packagist/v/phpunit/phpunit.svg?style=flat-square)](https://packagist.org/packages/phpple/mysql)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%207.0-8892BF.svg?style=flat-square)](https://php.net/)
[![Build Status](https://img.shields.io/travis/phpple/mysql/master.svg?style=flat-square)](https://travis-ci.org/phpple/mysql)
[![codecov](https://codecov.io/gh/phpple/mysql/branch/master/graph/badge.svg)](https://codecov.io/gh/phpple/mysql)


`Phpple Mysql`提供如下优秀的特性：

* SQL构建器
* yield支持
* 主/从库支持
* 长连接支持
* 分表/分库支持
* psr-4支持

### 使用示例

```php
use Phpple\Mysql\Conf;
use Phpple\Mysql\Sql\SqlBuilder;
use Phpple\Mysql\Db;

// 初始化数据库配置
$confs = [
    'db' => [
        'demo' => [
            'dbname' => 'phpple',
            'instance' => 'ip1'
        ],
    ],
    'instance' => [
        'ip1' => [
            'host' => '127.0.0.1',
            'port' => 3306,
            'user' => 'root',
            'pass' => '',
            'charset' => 'utf8'
        ],
    ]
];
Conf::init($confs);

$id = 12030;
// 创建一个Sql构建器
$sqlBuilder = SqlBuilder::withTable('u_user')
    ->fields('view_num')
    ->setData([
        '@view_num' => '(view_num+1)'
    ])
    ->where('id', $id);
// 绑定Sql构建器到Db对象
$db = Db::get('demo')->sqlBuilder($sqlBuilder);

// 获取原始view_num
$viewNum = $db->getSingle();
echo 'before:' . $viewNum . PHP_EOL;

// view_num 自增1
$db->update();

// 重新获取view_num
$newViewnum = $db->getSingle();
echo 'after:' . $newViewnum . PHP_EOL;
```
