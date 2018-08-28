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
use Phpple\Mysql\Sql\Template\Compiler;
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

    /**
     * @before
     */
    public function initConfs()
    {
        Conf::init($this->confs);
    }

    public function testCount()
    {
        $sqlBuilder = SqlBuilder::withTable('u_user')
            ->where('status', 0);
        $db = Db::get('demo')->sqlBuilder($sqlBuilder);
        $count = $db->getCount();
        $this->assertGreaterThan(0, $count);

        // 测试count为0的情况
        $sqlBuilder->unsetWhere()->where('status', 9999);
        $count = $db->getCount();
        $this->assertEquals(0, $count);
    }

    public function testSelect()
    {
        $sqlBuilder = SqlBuilder::withTable('u_user')
            ->fields('id', 'username', 'city_id', 'email', 'phone')
            ->where('id', 10000, ISqlWhere::COMPARE_GREATER_OR_EQUAL)
            ->orderBy('id');
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

    public function testGetOne()
    {
        $id = 10000;
        $sqlBuilder = SqlBuilder::withTable('u_user')
            ->setData([
                'id' => $id,
                'username' => 'user' . $id,
                'password' => 'password' . $id,
            ])
            ->operation(Compiler::INSERT_IGNORE);
        $db = Db::get('demo')->sqlBuilder($sqlBuilder);
        $db->execute();

        $sqlBuilder->where('id', $id);
        $one = $db->getOne();
        $this->assertEquals($id, $one['id']);

        $id = 223;
        $sqlBuilder->unsetWhere()->where('id', $id);

        $exist = $db->getExist();

        $this->assertFalse($exist);

        $one = $db->getOne();
        $this->assertNull($one);
    }

    /**
     * @covers \Phpple\Mysql\Db::insert()
     * @covers \Phpple\Mysql\Db::getOne()
     */
    public function testInserts()
    {
        $sqlBuider = SqlBuilder::withTable('u_user');
        $db = Db::get('demo')->sqlBuilder($sqlBuider);

        // 先删除再创建
        $sqlBuider->whereParams('id>=12000 and id<=12099');
        $db->delete();
        $sqlBuider->unsetWhere();

        for ($i = 0; $i < 100; $i++) {
            $sqlBuider->setData([
                'id' => 12000 + $i,
                'status' => 1,
                'username' => 'ronnie' . $i,
                'password' => md5('password' . $i),
                'email' => 'ronnie' . $i . '@live.com',
            ]);
            $db->insert();
        }
        $id = random_int(12000, 12099);
        $sqlBuider->where('id', $id);
        $this->assertNotEmpty($db->getOne());
    }

    /**
     * 测试物理删除
     */
    public function testRealDel()
    {
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
        $sqlBuilder = SqlBuilder::withTable('u_user')
            ->fields('id', 'username')
            ->whereIn('id', [12006, 12008, 12010])
            ->setData([
                'del_flag' => 1,
            ]);
        $db = Db::get('demo');
        $db->sqlBuilder($sqlBuilder)
            ->update();

        $rows = $db->getAll();
        $this->assertNotEmpty($rows);
    }

    public function testAutoInc()
    {
        $lastId = Db::get('demo')
            ->sqlBuilder(SqlBuilder::withTable('u_user')
                ->setData([
                    'username' => 'test',
                    'password' => 'hello',
                    'status' => 1,
                ]))
            ->insertWithLastId();
        $this->assertNotEmpty($lastId);
        echo 'LastInsertId:' . $lastId . PHP_EOL;
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

        // 测试getSingle返回null
        $sqlBuilder->unsetWhere()->where('id', 3333);
        $viewNum = $db->getSingle();
        $this->assertNull($viewNum, $viewNum);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage db.ddlSqlRequired
     */
    public function testQueryForExecute()
    {
        Db::get('demo')
            ->sqlBuilder(
                SqlBuilder::withTable('u_user')
                    ->where('id', 12001)
            )
            ->execute();
    }

    /**
     * @covers \Phpple\Mysql\Db::isWrite()
     * @covers \Phpple\Mysql\Db::getAll()
     */
    public function testMultiSql()
    {
        $sql = 'SELECT * FROM `phpple`.`u_user` WHERE (`id` = 12002)';
        $useMaster = !!preg_match('#^(INSERT|UPDATE|DELETE|DROP|ALTER) #i', $sql);
        $this->assertFalse($useMaster);

        $sqlBuilder = SqlBuilder::withTable('u_user')->where('id', 12002);
        $sqlBuilder2 = SqlBuilder::withTable('u_user')->operation(Compiler::DESC_TABLE);
        $sqlBuilder->push($sqlBuilder2);

        $db = Db::get('demo')->sqlBuilder($sqlBuilder);
        $result = $db->getAll();

        $this->assertEquals(2, count($result));
        $this->assertArrayHasKey(Db::KEY_AFFECT_ROWS, $result[0]);
        $this->assertArrayHasKey(Db::KEY_FIELDS, $result[0]);
    }
}
