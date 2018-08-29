<?php
/**
 *
 * @author: ronnie
 * @since: 2018/8/27 00:51
 * @copyright: 2018@hunbasha.com
 * @filesource: SqlBuilderTest.php
 */

namespace Phpple\Mysql\Test;

use Phpple\Mysql\ISplit;
use Phpple\Mysql\Sql\IExpression;
use Phpple\Mysql\Sql\SqlBuilder;
use Phpple\Mysql\Sql\Template\Compiler;
use PHPUnit\Framework\TestCase;

class SqlBuilderTest extends TestCase
{
    const DB_NAME = 'phpple';
    const TABLE_NAME = 'u_user';

    /**
     * @expectedException \DomainException
     * @expectedExceptionMessageRegExp  #^sqlBuilder.operationNotDefine #
     */
    public function testOperationIllegal()
    {
        (new SqlBuilder())->operation('sdfsdf');
    }

    /**
     * @expectedException DomainException
     * @expectedExceptionMessageRegExp #^sqlBuilder.varNotImplemented #
     */
    public function testMethodNotExist()
    {
        $operation = 'foo';
        Compiler::addTemplate($operation, 'hello {Hlsdfsdfsdf}}');
        (new SqlBuilder())->operation($operation)->toString();
    }

    /**
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage sqlBuilder.dataEmpty
     */
    public function testValuesNotDefine()
    {
        $operation = 'bar';
        Compiler::addTemplate($operation, 'TEST {VALUES}');
        (new SqlBuilder())->operation($operation)->toString();
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage sqlBuilder.dataNotAllowEmpty
     */
    public function testSetDataFailed()
    {
        (new SqlBuilder())->setData([]);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage sqlBuilder.illealField
     */
    public function testFieldIllegal()
    {
        (new SqlBuilder())
            ->db(self::DB_NAME)
            ->table(self::TABLE_NAME)
            ->setData([
                1 => 'a'
            ])
            ->insert()
            ->toString();
    }

    public function testGet()
    {
        $sqlBuilder = new SqlBuilder();
        $sqlBuilder->db(self::DB_NAME)
            ->table(self::TABLE_NAME)
            ->fields('user_id', 'username', 'email')
            ->where('user_id', 234)
            ->where('status', -1, IExpression::COMPARISON_NOT_EQUAL)
            ->orderBy('user_id')
            ->orderBy('username', false)
            ->limitOne()
            ->select();
        $this->assertEquals(
            'SELECT `user_id`, `username`, `email` FROM `phpple`.`u_user` WHERE (`user_id` = 234) AND (`status` != -1) ORDER BY `user_id` ASC, `username` DESC LIMIT 0, 1',
            $sqlBuilder->toString()
        );

        $this->assertEquals($sqlBuilder->toString(), $sqlBuilder->__toString());
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage sqlBuilder.orderByNullDefined
     */
    public function testOrderNullIllegal()
    {
        (new SqlBuilder())
            ->db(self::DB_NAME)
            ->table(self::TABLE_NAME)
            ->orderBy(null)
            ->orderBy('id', false)
            ->toString();
    }

    public function testOrderNull()
    {
        $sql = (new SqlBuilder())
            ->db(self::DB_NAME)
            ->table(self::TABLE_NAME)
            ->orderBy(null)
            ->toString();
        $this->assertContains(' ORDER BY NULL', $sql);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage sqlBuilder.fieldSortedYet
     */
    public function testOrderRepeat()
    {
        (new SqlBuilder())
            ->db(self::DB_NAME)
            ->table(self::TABLE_NAME)
            ->orderBy('id', true)
            ->orderBy('id', false)
            ->toString();
    }

    public function testOrderUnset()
    {
        $sql = (new SqlBuilder())
            ->db(self::DB_NAME)
            ->table(self::TABLE_NAME)
            ->orderBy('id', true)
            ->unsetOrderBy()
            ->orderBy('id', false)
            ->toString();
        $this->assertContains('ORDER BY `id` DESC', $sql);
    }

    public function testOrder()
    {
        $sql = (new SqlBuilder())
            ->db(self::DB_NAME)
            ->table(self::TABLE_NAME)
            ->orderBy('order_index')
            ->orderBy('id', false)
            ->limitTop(10)
            ->toString();
        $this->assertEquals(
            'SELECT * FROM `phpple`.`u_user` ORDER BY `order_index` ASC, `id` DESC LIMIT 0, 10',
            $sql
        );
    }

    public function testGroup()
    {
        $sqlBuilder = new SqlBuilder();
        $sqlBuilder->db(self::DB_NAME)
            ->table(self::TABLE_NAME)
            ->fields('city_id', 'count(0)')
            ->where('status', -1, '!=')
            ->groupBy('city_id')
            ->select();
        $this->assertEquals(
            'SELECT `city_id`, count(0) FROM `phpple`.`u_user` WHERE (`status` != -1) GROUP BY `city_id`',
            $sqlBuilder->toString()
        );
    }

    /**
     *
     */
    public function testWhere()
    {
        $sql = (new SqlBuilder())
            ->db(self::DB_NAME)
            ->table(self::TABLE_NAME)
            ->where('id', 10000)
            ->whereIn('status', [1, 2])
            ->whereParams('city_id=?', 110900)
            ->select()
            ->exist()
            ->toString();
        $this->assertEquals(
            'SELECT 1 FROM `phpple`.`u_user` WHERE (`id` = 10000) AND (`status` IN (1, 2)) AND (city_id=110900)',
            $sql
        );
    }


    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage sqlBuilder.invalidNumOfParams
     */
    public function testWhereParamsException()
    {
        (new SqlBuilder())
            ->db(self::DB_NAME)
            ->table(self::TABLE_NAME)
            ->whereParams('a=? and c=?', 1)
            ->select()
            ->toString();
    }

    public function testWhereOr()
    {
        $email = 'comdeng@live.com';
        $username = 'comdeng';
        $sql = (new SqlBuilder())
            ->db(self::DB_NAME)
            ->table(self::TABLE_NAME)
            ->where('status', 1)
            ->whereOr(
                [
                    'email=\'' . $email . '\'',
                    [
                        'username=?',
                        $username
                    ]
                ],
                [
                    [
                        'sex=?',
                        1
                    ],
                    [
                        'sex=0'
                    ]
                ]
            )
            ->toString();
        $this->assertEquals(
            'SELECT * FROM `phpple`.`u_user` WHERE (`status` = 1) AND ' .
            '(((email=\'comdeng@live.com\') AND (username=0x' . bin2hex($username) . ')) OR ((sex=1) AND (sex=0)))',
            $sql
        );
    }

    public function testSelectForUpdate()
    {
        $sqlBuilder = new SqlBuilder();
        $sqlBuilder->db(self::DB_NAME)
            ->table(self::TABLE_NAME)
            ->where('user_id', 10000)
            ->select(true);
        $this->assertEquals(
            'SELECT * FROM `phpple`.`u_user` WHERE (`user_id` = 10000) FOR UPDATE',
            $sqlBuilder->toString()
        );
    }

    public function testCount()
    {
        $sqlBuilder = new SqlBuilder();
        $sqlBuilder->db(self::DB_NAME)
            ->table(self::TABLE_NAME)
            ->fields('id')
            ->where('city_id', 110900)
            ->select();
        $this->assertEquals(
            'SELECT `id` FROM `phpple`.`u_user` WHERE (`city_id` = 110900)',
            $sqlBuilder->toString()
        );

        $sqlBuilder->count();
        $this->assertEquals(
            'SELECT COUNT(0) CNT FROM `phpple`.`u_user` WHERE (`city_id` = 110900)',
            $sqlBuilder->toString()
        );
    }

    public function testLastInsertId()
    {
        $sqlBuilder = SqlBuilder::lastInsertId();
        $this->assertEquals(
            'SELECT LAST_INSERT_ID() AS LIID',
            $sqlBuilder->toString()
        );

        $sqlBuilder = SqlBuilder::descTable(self::DB_NAME, self::TABLE_NAME);
        $this->assertEquals(
            'DESC `phpple`.`u_user`',
            $sqlBuilder->toString()
        );

        $sqlBuilder = SqlBuilder::showCreateTable(self::DB_NAME, self::TABLE_NAME);
        $this->assertEquals(
            'SHOW CREATE TABLE `phpple`.`u_user`',
            $sqlBuilder->toString()
        );
    }

    public function testInsert()
    {
        $data = [
            'id' => 10001,
            'username' => 'comdeng',
            'password' => md5('test'),
            'avatar' => '/m/sdfsd/sdfsd.jpg',
            'sex' => 2,
            'create_time' => date('Y/m/d H:i:s'),
        ];
        $sqlBuilder = new SqlBuilder();
        $sqlBuilder->db(self::DB_NAME)
            ->table(self::TABLE_NAME);
        try {
            $sqlBuilder->insert()->toString();
        } catch (\UnexpectedValueException $ex) {
            $this->assertEquals('sqlBuilder.dataEmpty', $ex->getMessage());
        }

        $sqlBuilder->setData($data)
            ->insert();

        $this->assertEquals(
            'INSERT INTO `phpple`.`u_user`(`id`, `username`, `password`, `avatar`, `sex`, `create_time`) VALUES(10001, 0x' .
            bin2hex($data['username']) . ', 0x' .
            bin2hex($data['password']) . ', 0x' .
            bin2hex($data['avatar']) . ', ' . $data['sex'] . ', 0x' .
            bin2hex($data['create_time']) . ')',
            $sqlBuilder->toString()
        );

        $sqlBuilder->insertIgnore();
        $this->assertContains('INSERT IGNORE INTO ', $sqlBuilder->toString());
    }

    public function testInsertUpdate()
    {
        $data = [
            'id' => 10000,
            'username' => 'comdeng',
            'password' => md5('test'),
            'status' => 1,
            'sex' => 2,
            'view_num' => 0,
            '@update_time' => 'CURRENT_TIMESTAMP()',
        ];
        $updates = [
            'status' => null,
            'sex' => null,
            '@view_num' => '(view_num+1)',
            'update_time' => null,
        ];
        $sqlBuilder = (new SqlBuilder())
            ->db(self::DB_NAME)
            ->table(self::TABLE_NAME)
            ->setData($data);

        try {
            $sqlBuilder->onDuplicate([]);
        } catch (\InvalidArgumentException $ex) {
            $this->assertEquals('sqlBuilder.updatesNotAllowEmpty', $ex->getMessage());
        }

        $sqlBuilder->onDuplicate($updates)
            ->insertUpdate();
        $this->assertContains(
            ' ON DUPLICATE KEY UPDATE `status` = VALUES(`status`), `sex` = VALUES(`sex`), `view_num` = ' .
            $updates['@view_num'] .
            ', `update_time` = VALUES(`update_time`)',
            $sqlBuilder->toString()
        );

        $sqlBuilder->onDuplicate(null);
        $this->assertContains(
            ' ON DUPLICATE KEY UPDATE `id` = VALUES(`id`), `username` = VALUES(`username`), `password` = VALUES(`password`), `status` = VALUES(`status`), `sex` = VALUES(`sex`), `view_num` = VALUES(`view_num`), `update_time` = CURRENT_TIMESTAMP()',
            $sqlBuilder->toString()
        );
    }

    public function testUpdate()
    {
        $data = [
            'email' => 'comdeng@live.com',
            '@update_time' => 'CURRENT_TIMESTAMP()',
        ];
        $sqlBuilder = new SqlBuilder();
        $sqlBuilder->db(self::DB_NAME)
            ->table(self::TABLE_NAME)
            ->where('id', 10000)
            ->update();

        try {
            $sqlBuilder->toString();
        } catch (\UnexpectedValueException $ex) {
            $this->assertEquals('sqlBuilder.dataEmpty', $ex->getMessage());
        }

        $sqlBuilder->setData($data);
        $this->assertEquals(
            'UPDATE `phpple`.`u_user` SET `email` = 0x' . bin2hex($data['email']) . ', `update_time` = ' . $data['@update_time'] . ' WHERE (`id` = 10000)',
            $sqlBuilder->toString()
        );

        $sqlBuilder->setData($data, ['email']);
        $this->assertEquals(
            'UPDATE `phpple`.`u_user` SET `email` = 0x' . bin2hex($data['email']) . ' WHERE (`id` = 10000)',
            $sqlBuilder->toString()
        );

        try {
            $sqlBuilder->setData($data, 'email');
        } catch (\InvalidArgumentException $ex) {
            $this->assertEquals('sqlBuilder.fieldsMustBeArray', $ex->getMessage());
        }

        try {
            $sqlBuilder->setData($data, ['email', 'username']);
        } catch (\InvalidArgumentException $ex) {
            $this->assertEquals('sqlBuilder.dataKeyRequired username', $ex->getMessage());
        }
    }

    public function testDelete()
    {
        $sqlBuilder = new SqlBuilder();
        $sqlBuilder->db(self::DB_NAME)
            ->table(self::TABLE_NAME)
            ->where('user_id', 4)
            ->where('status', -1, IExpression::COMPARISON_NOT_EQUAL)
            ->delete();
        $this->assertEquals(
            'DELETE FROM `phpple`.`u_user` WHERE (`user_id` = 4) AND (`status` != -1)',
            $sqlBuilder->toString()
        );
    }

    public function testAppend()
    {
        $data = [
            'username' => 'comdeng',
            '@password' => "'" . md5('test') . "'",
        ];
        $sqlBuilder = new SqlBuilder();
        $sqlBuilder->db(self::DB_NAME)
            ->table(self::TABLE_NAME)
            ->setData($data)
            ->insert()
            ->push(SqlBuilder::lastInsertId());

        $this->assertEquals(
            'INSERT INTO `phpple`.`u_user`(`username`, `password`) VALUES(0x' .
            bin2hex($data['username']) . ', ' .
            $data['@password'] . ');SELECT LAST_INSERT_ID() AS LIID',
            $sqlBuilder->toString()
        );
    }


    public function testUnsetWhere()
    {
        $sqlBuilder = new SqlBuilder();
        $sqlBuilder->where('status', 1)
            ->unsetWhere()
            ->where('id', 10000);

        try {
            $sqlBuilder->toString();
        } catch (\UnexpectedValueException $ex) {
            $this->assertEquals('sqlBuilder.dbNotDefined', $ex->getMessage());
            $sqlBuilder->db(self::DB_NAME);
        }
        try {
            $sqlBuilder->toString();
        } catch (\UnexpectedValueException $ex) {
            $this->assertEquals('sqlBuilder.tableNotDefined', $ex->getMessage());
            $sqlBuilder->table(self::TABLE_NAME);
        }
        $this->assertNotContains('status', $sqlBuilder->toString());
    }

    public function testMulti()
    {
        $builder1 = SqlBuilder::withTable('u_user');
        $builder2 = SqlBuilder::withTable('u_user_info');
        $builder1->push($builder2)->db('demo');

        $this->assertContains('`demo`', $builder2->toString());
    }

    public function testEscapeVal()
    {
        $data = [
            'key0' => 'str',
            'key1' => 3,
            'key2' => 0,
            'key3' => 0.4,
            'key4' => null,
            'key5' => true,
            'key6' => [1,true] // 有可能导致错误
        ];
        $sqlBuilder = (new SqlBuilder())
            ->db(self::DB_NAME)
            ->table(self::TABLE_NAME)
            ->setData($data);
        $sql = $sqlBuilder->insert()->toString();

        $this->assertContains(
            'VALUES(0x' . bin2hex($data['key0']) . ', 3, 0, 0.4, NULL, 1, 1, 1)',
            $sql
        );

        try {
            $sqlBuilder->setData([
                'key1' => new \Reflection(),
            ])->toString();
        } catch (\InvalidArgumentException $ex) {
            $this->assertEquals('sqlBuilder.notSupportType', $ex->getMessage());
        }

        try {
            $sqlBuilder->setData([
                'key1' => [],
            ])->toString();
        } catch (\InvalidArgumentException $ex) {
            $this->assertEquals('sqlBuilder.emptyNotAllowd', $ex->getMessage());
        }
    }

    public function testSplitTable()
    {
        // 按摸
        $id = 12237;
        $table = SqlBuilder::getNameBySplit(self::TABLE_NAME, ISplit::SPLIT_BY_MOD, $id, 100);
        $this->assertEquals(
            self::TABLE_NAME . ISplit::SPLIT_CONNECT_FLAG . '37',
            $table
        );

        // 按ID分段
        $table = SqlBuilder::getNameBySplit(self::TABLE_NAME, ISplit::SPLIT_BY_MOD, $id, 1234);
        $this->assertEquals(
            self::TABLE_NAME . ISplit::SPLIT_CONNECT_FLAG . ($id % 1234),
            $table
        );

        // 分段
        $arg = 4000000;
        $value = 123433222;
        $table = SqlBuilder::getNameBySplit(self::TABLE_NAME, ISplit::SPLIT_BY_DIV, $value, $arg);
        $this->assertEquals(
            self::TABLE_NAME . ISplit::SPLIT_CONNECT_FLAG . round($value / $arg),
            $table
        );

        $timestamp = strtotime('2018/8/29 10:23:44');
        // 按年
        $table = SqlBuilder::getNameBySplit(self::TABLE_NAME, ISplit::SPLIT_BY_YEAR, $timestamp);
        $this->assertEquals(
            self::TABLE_NAME . ISplit::SPLIT_CONNECT_FLAG . '2018',
            $table
        );

        // 按月份
        $table = SqlBuilder::getNameBySplit(self::TABLE_NAME, ISplit::SPLIT_BY_MONTH, $timestamp);
        $this->assertEquals(
            self::TABLE_NAME . ISplit::SPLIT_CONNECT_FLAG . '201808',
            $table
        );

        // 按天
        $table = SqlBuilder::getNameBySplit(self::TABLE_NAME, ISplit::SPLIT_BY_DAY, $timestamp);
        $this->assertEquals(
            self::TABLE_NAME . ISplit::SPLIT_CONNECT_FLAG . '20180829',
            $table
        );

        // 按不合法规则
        try {
            $table = SqlBuilder::getNameBySplit(self::TABLE_NAME, 'foobar', $timestamp);
        } catch (\DomainException $ex) {
            $this->assertInstanceOf('\DomainException', $ex);
        }
        $this->assertEquals(
            self::TABLE_NAME . ISplit::SPLIT_CONNECT_FLAG . '20180829',
            $table
        );

        $id = 12334322092;
        $sqlBuilder = SqlBuilder::withTable(self::TABLE_NAME)
            ->db(self::DB_NAME)
            ->tableSplit(ISplit::SPLIT_BY_MOD, 100)
            ->tableAlias('u')
            ->tableSplitValue($id)
            ->where('id', $id)
            ->select();
        $this->assertEquals(
            'SELECT * FROM `phpple`.`u_user_92` u WHERE (`id` = ' . $id . ')',
            $sqlBuilder->toString()
        );
    }

    public function testFollow()
    {
        $sqlBuilder = SqlBuilder::none();

        $sqls = [];
        $num = 10;
        for ($i = 0; $i < $num; $i++) {
            $sqlBuilder->push((new SqlBuilder())
                ->db(self::DB_NAME)
                ->table(self::TABLE_NAME)
                ->setData([
                    'order_index' => $i + 1,
                ])
                ->where('id', 10000 + $i + 1)
                ->update()
            );
            $sqls[] = 'UPDATE `phpple`.`u_user` SET `order_index` = ' . ($i + 1) . ' WHERE (`id` = ' . (10000 + $i + 1) . ')';
        }
        $this->assertEquals(
            implode(SqlBuilder::SQL_JOIN_FLAG, $sqls),
            $sqlBuilder->toString()
        );

        $total = 0;
        foreach ($sqlBuilder->fetchFollows() as $follow) {
            $total++;
        }
        $this->assertEquals($num, $total);

        $sqlBuilder->unsetFollows();
        $total = 0;
        foreach ($sqlBuilder->fetchFollows() as $follow) {
            $total++;
        }
        $this->assertEquals(0, $total);
    }

    public function testWhereLike()
    {
        $like = 'user%';
        $sqlBuilder = (new SqlBuilder())
            ->db(self::DB_NAME)
            ->table(self::TABLE_NAME)
            ->whereLike('username', $like)
            ->select();
        $this->assertContains(
            'WHERE (`username` LIKE 0x'.bin2hex($like).')',
            $sqlBuilder->toString()
        );

        $sqlBuilder->unsetWhere()
            ->whereBetween('view_num', 4, 5);
        $this->assertContains(
            'WHERE (`view_num` BETWEEN 4 AND 5)',
            $sqlBuilder->toString()
        );

        $emailExp = '\@163.com$';
        $sqlBuilder->unsetWhere()
            ->whereRegexp('email', $emailExp);
        $this->assertContains(
            'WHERE (`email` REGEXP 0x'.bin2hex($emailExp).')',
            $sqlBuilder->toString()
        );
    }
}
