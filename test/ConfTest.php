<?php
/**
 *
 * @author: ronnie
 * @since: 2018/8/27 17:36
 * @copyright: 2018@hunbasha.com
 * @filesource: ConfTest.php
 */

namespace Phpple\Mysql\Test;

use phpDocumentor\Reflection\Types\Context;
use Phpple\Mysql\Conf;
use PHPUnit\Framework\TestCase;

class ConfTest extends TestCase
{
    private $confs = [
        'alias' => [
            'alias1' => [
                'dbname' => 'pre_{key}',
                'instance' => [
                    'master' => 'ip1',
                    'slave' => ['ip2'],
                ]
            ],
            'alias2' => [
                'instance' => 'ip2',
            ],
        ],
        'instance' => [
            'ip1' => ['host' => '127.0.0.1', 'port' => 3306, 'user' => 'root', 'pass' => 'root', 'charset' => 'utf8'],
            'ip2' => ['host' => '127.0.0.1', 'port' => 3309, 'user' => 'root', 'pass' => 'root', 'charset' => 'utf8'],
        ],
        'db' => [
            'db1' => 'alias1',
            'db2' => 'alias2',
            'db3' => [
                'dbname' => 'phpple',
                'instance' => 'ip2',
                'persist' => true,
            ]
        ]
    ];

    public function testLoadConf()
    {
        Conf::init($this->confs);
        $conf = Conf::loadConf('db1', true, crc32('select * from user'));
        $expectConf = $this->confs['instance']['ip1'];
        $expectConf['dbname'] = 'pre_db1';
        $expectConf['persist'] = false;
        $this->assertEquals($expectConf, $conf);

        $conf = Conf::loadConf('db3', false, crc32('select * from user'));

        $expectConf = $this->confs['instance']['ip2'];
        $expectConf['dbname'] = 'phpple';
        $expectConf['persist'] = true;
        $this->assertEquals($expectConf, $conf);
    }
}
