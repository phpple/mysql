<?php
/**
 *
 * @author: ronnie
 * @since: 2018/8/27 18:16
 * @copyright: 2018@hunbasha.com
 * @filesource: DbTest.php
 */

namespace Phpple\Mysql\Test;

use Phpple\Mysql\Conf;
use Phpple\Mysql\Db;
use Phpple\Mysql\Sql\ISqlWhere;
use Phpple\Mysql\Sql\SqlBuilder;
use PHPUnit\Framework\TestCase;

class DbTest extends TestCase
{
    private $confs = [
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
                'user' => 'phpple',
                'pass' => 'phpple',
                'charset' => 'utf8'
            ],
        ]
    ];

    public function testCount()
    {
        Conf::init($this->confs);

        $count = Db::get('demo')
            ->sqlBuilder(
                SqlBuilder::withTable('u_user')
                    ->where('status', 0)
            )
            ->getCount();
        $this->assertGreaterThan(0, $count);
    }

    public function testSelect()
    {
        Conf::init($this->confs);
        $sqlBuilder = SqlBuilder::withTable('u_user')
            ->fields('id', 'username', 'city_id', 'email', 'phone')
            ->where('id', 10000, ISqlWhere::COMPARE_GREATER_OR_EQUAL);
        $db = Db::get('demo')->sqlBuilder($sqlBuilder);
        $rows = [];
        foreach ($db->fetchAll() as $row) {
            if ($row['phone'] != 0) {
                $row['verified'] = true;
            }
            $rows[] = $row;
        }
        $this->assertNotEmpty($rows);
    }

    public function testInserts()
    {
        Conf::init($this->confs);

        $sqlBuider = SqlBuilder::withTable('u_user')
            ->insert();
        $db = Db::get('demo')->sqlBuilder($sqlBuider);
        for ($i = 0; $i < 100; $i++) {
            echo $i . PHP_EOL;
            $sqlBuider->setData([
                'id' => 12000 + $i,
                'status' => 1,
                'username' => 'ronnie' . $i,
                'password' => md5('password' . $i),
                'email' => 'ronnie' . $i . '@live.com',
            ]);
            $db->execute();
        }
    }

    /**
     * 测试物理删除
     */
    public function testRealDel()
    {
        Conf::init($this->confs);

        $sqlBuilder = SqlBuilder::withTable('u_user')
            ->whereIn('id', [12000, 12001, 12003]);
        $db = Db::get('demo');
        $db->sqlBuilder($sqlBuilder)
            ->delete();

        $rows = $db->getAll();
        $this->assertEmpty($rows);
    }

    /**
     * 测试逻辑删除
     */
    public function testLogicDel()
    {
        Conf::init($this->confs);

        $sqlBuilder = SqlBuilder::withTable('u_user')
            ->fields('id', 'username')
            ->whereIn('id', [12006, 12008, 12010])
            ->setData([
                'status' => -1,
            ]);
        $db = Db::get('demo');
        $db->sqlBuilder($sqlBuilder)
            ->update();

        $rows = $db->getAll();
        var_dump($rows);
        $this->assertNotEmpty($rows);
    }

    public function testAutoInc()
    {
        Conf::init($this->confs);
        $lastId = Db::get('demo')
            ->sqlBuilder(SqlBuilder::withTable('u_user')
                ->setData([
                    'username' => 'test',
                    'password' => 'hello',
                    'status' => 1,
                ]))
            ->insertWithLastId();
        $this->assertNotEmpty($lastId);
        echo 'LastInsertId:'.$lastId.PHP_EOL;
    }

    /**
     *
     * @example output:
     * sql:SELECT `view_num` FROM `phpple`.`u_user` WHERE (`id` = 12030) LIMIT 0,1
     * sql:UPDATE `phpple`.`u_user` SET `view_num` = (view_num+1) WHERE (`id` = 12030)
     * sql:SELECT `view_num` FROM `phpple`.`u_user` WHERE (`id` = 12030) LIMIT 0,1
     * before:3
     * after:4
     */
    public function testIncrement()
    {
        Conf::init($this->confs);
        $id = 12030;
        $sqlBuilder = SqlBuilder::withTable('u_user')
            ->fields('view_num')
            ->setData([
                '@view_num' => '(view_num+1)'
            ])
            ->where('id', $id);

        $db = Db::get('demo')->sqlBuilder($sqlBuilder);

        // 获取原始view_num
        $viewNum = $db->getSingle();
        echo 'before:' . $viewNum . PHP_EOL;

        // view_num 自增1
        $db->update();

        // 重新获取view_num
        $newViewnum = $db->getSingle();
        echo 'after:' . $newViewnum . PHP_EOL;

        $this->assertEquals($viewNum + 1, $newViewnum);
    }
}
