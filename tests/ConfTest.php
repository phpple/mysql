<?php
/**
 *
 * @author: ronnie
 * @since: 2018/8/27 17:36
 * @copyright: 2018@hunbasha.com
 * @filesource: ConfTest.php
 */

namespace Phpple\Mysql\Tests;

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
                    'slave' => ['ip2', 'ip3'],
                ]
            ],
            'alias2' => [
                'instance' => 'ip2',
            ],
        ],
        'instance' => [
            'ip1' => ['host' => '127.0.0.1', 'port' => 3306, 'user' => 'root', 'pass' => 'root', 'charset' => 'utf8'],
            'ip2' => ['host' => '127.0.0.1', 'port' => 3309, 'user' => 'root', 'pass' => 'root', 'charset' => 'utf8'],
            'ip3' => ['host' => '127.0.0.1', 'port' => 3312, 'user' => 'root', 'pass' => 'root', 'charset' => 'utf8'],
        ],
        'db' => [
            'db1' => 'alias2',
            'db2' => 'alias1',
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
        $expectConf = $this->confs['instance']['ip2'];
        $expectConf['dbname'] = 'db1';
        $expectConf['persist'] = false;
        $this->assertEquals($expectConf, $conf);

        $conf = Conf::loadConf('db2', false, crc32('select * from user1'));
        $this->assertNotEquals(3306, $conf['port']);

        $conf = Conf::loadConf('db3', false, crc32('select * from user'));

        $expectConf = $this->confs['instance']['ip2'];
        $expectConf['dbname'] = 'phpple';
        $expectConf['persist'] = true;
        $this->assertEquals($expectConf, $conf);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage dbKeyNotFound
     */
    public function testFailedConf()
    {
        Conf::init([]);
    }
    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage instanceKeyNotFound
     */
    public function testFailedConfNoInstance()
    {
        Conf::init([
            'db' => []
        ]);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessageRegExp #^conf.dbNotDefined #
     */
    public function testFaildLoadNoDb()
    {
        Conf::init([
            'db' => [
                'foo' => [
                    'instance' => 'ip1',
                ]
            ],
            'instance' => [

            ],
        ]);
        Conf::loadConf('foo1', true, crc32('select * from user.u_user'));
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessageRegExp #^conf.aliasNotDefined #
     */
    public function testFaildLoadNoAlias()
    {
        Conf::init([
            'db' => [
                'foo' => 'alias'
            ],
            'instance' => [

            ],
        ]);
        Conf::loadConf('foo', true, crc32('select * from user.u_user'));
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage conf.aliasMustBeStringOrArray
     */
    public function testFaildLoadIllegalAlias()
    {
        Conf::init([
            'db' => [
                'foo' => true
            ],
            'instance' => [

            ],
        ]);
        Conf::loadConf('foo', true, crc32('select * from user.u_user'));
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage conf.instanceMustDefined
     */
    public function testFaildLoadNoInstance()
    {
        Conf::init([
            'db' => [
                'foo' => [

                ],
            ],
            'instance' => [

            ],
        ]);
        Conf::loadConf('foo', true, crc32('select * from user.u_user'));
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage conf.dbnameMustBeString
     */
    public function testFaildLoadIllegalDbName()
    {
        Conf::init([
            'db' => [
                'foo' => [
                    'dbname' => true,
                    'instance' => 'ip1',
                ],
            ],
            'instance' => [

            ],
        ]);
        Conf::loadConf('foo', true, crc32('select * from user.u_user'));
    }

    /**
     * @expectedException \UnexpectedValueException
     * @expectedExceptionMessageRegExp #^conf.instanceNotFound#
     */
    public function testFaildLoadInstanceNotFound()
    {
        Conf::init([
            'db' => [
                'foo' => [
                    'instance' => 'ip1',
                ],
            ],
            'instance' => [

            ],
        ]);
        Conf::loadConf('foo', true, crc32('select * from user.u_user'));
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage conf.instanceMustBeStringOrArray
     */
    public function testFaildLoadInstanceNotArray()
    {
        Conf::init([
            'db' => [
                'foo' => [
                    'instance' => true
                ],
            ],
            'instance' => [
                'ip1' => [],
            ],
        ]);
        Conf::loadConf('foo', true, crc32('select * from user.u_user'));
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage conf.masterAndSlaveMustDefinedBoth
     */
    public function testFaildLoadMasterSlaveBoth()
    {
        Conf::init([
            'db' => [
                'foo' => [
                    'instance' => [
                        'master' => 'ip1'
                    ]
                ],
            ],
            'instance' => [
                'ip1' => [],
            ],
        ]);
        Conf::loadConf('foo', true, crc32('select * from user.u_user'));
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage conf.masterInstanceMustDefined
     */
    public function testFaildLoadMasterDefined()
    {
        Conf::init([
            'db' => [
                'foo' => [
                    'instance' => [
                        'master' => 'ip1',
                        'slave' => ['ip1', 'ip2']
                    ]
                ],
            ],
            'instance' => [
                'ip' => [],
            ],
        ]);
        Conf::loadConf('foo', true, crc32('select * from user.u_user'));
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessageRegExp #^conf.slaveInstanceNotDefined#
     */
    public function testFaildLoadSlaveDefined()
    {
        Conf::init([
            'db' => [
                'foo' => [
                    'instance' => [
                        'master' => 'ip1',
                        'slave' => ['ip1', 'ip2']
                    ]
                ],
            ],
            'instance' => [
                'ip1' => [],
            ],
        ]);
        Conf::loadConf('foo', true, crc32('select * from user.u_user'));
    }
}
