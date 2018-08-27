<?php
/**
 *
 * @author: ronnie
 * @since: 2018/8/27 00:51
 * @copyright: 2018@hunbasha.com
 * @filesource: SqlBuilderTest.php
 */

namespace Phpple\Mysql\Test;

use Phpple\Mysql\Sql\ISqlWhere;
use Phpple\Mysql\Sql\SqlBuilder;
use PHPUnit\Framework\TestCase;

class SqlBuilderTest extends TestCase
{
    public function testGet()
    {
        $sqlBuilder = new SqlBuilder();
        $sqlBuilder->db('user')
            ->table('u_user')
            ->fields('user_id', 'username', 'email')
            ->where('user_id', 234)
            ->where('status', -1, ISqlWhere::COMPARE_NOT_EQUAL)
            ->orderBy('user_id')
            ->orderBy('username', false)
            ->limitOne()
            ->select();
        $this->assertEquals(
            'SELECT `user_id`,`username`,`email` FROM `phpple`.`u_user` WHERE (`user_id` = 234) AND (`status` != -1) ORDER BY `user_id` ASC,`username` DESC LIMIT 0,1',
            $sqlBuilder->toString()
        );
        echo $sqlBuilder->toString();
    }

    public function testGroup()
    {
        $sqlBuilder = new SqlBuilder();
        $sqlBuilder->db('user')
            ->table('u_user')
            ->fields('city_id', 'count(0)')
            ->where('status', -1, '!=')
            ->groupBy('city_id')
            ->select();
        $this->assertEquals(
            'SELECT `city_id`,count(0) FROM `phpple`.`u_user` WHERE (`status` != -1) GROUP BY `city_id`',
            $sqlBuilder->toString()
        );
        echo $sqlBuilder->toString();
    }


    public function testSelectForUpdate()
    {
        $sqlBuilder = new SqlBuilder();
        $sqlBuilder->db('user')
            ->table('u_user')
            ->where('user_id', 10000)
            ->select(true);
        $this->assertEquals(
            'SELECT * FROM `phpple`.`u_user` WHERE (`user_id` = 10000) FOR UPDATE',
            $sqlBuilder->toString()
        );
        echo $sqlBuilder->toString();
    }

    public function testCount()
    {
        $sqlBuilder = new SqlBuilder();
        $sqlBuilder->db('phpple')
            ->table('u_user')
            ->fields('id')
            ->where('city_id', 110900)
            ->select();
        $this->assertEquals(
            'SELECT `id` FROM `phpple`.`u_user` WHERE (`city_id` = 110900)',
            $sqlBuilder->toString()
        );
        echo $sqlBuilder->toString().PHP_EOL;

        $sqlBuilder->count();
        $this->assertEquals(
            'SELECT COUNT(0) CNT FROM `phpple`.`u_user` WHERE (`city_id` = 110900)',
            $sqlBuilder->toString()
        );
        echo $sqlBuilder->toString();
    }

    public function testLastInsertId()
    {
        $sqlBuilder = SqlBuilder::lastInsertId();
        $this->assertEquals(
            'SELECT LAST_INSERT_ID() AS LIID',
            $sqlBuilder->toString()
        );

        $sqlBuilder = SqlBuilder::descTable('phpple', 'u_user');
        $this->assertEquals(
            'DESC `phpple`.`u_user`',
            $sqlBuilder->toString()
        );

        $sqlBuilder = SqlBuilder::showCreateTable('user', 'u_user');
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
        $sqlBuilder->db('phpple')
            ->table('u_user')
            ->setData($data)
            ->insert();
        $this->assertEquals(
            'INSERT INTO `phpple`.`u_user`(`id`, `username`, `password`, `avatar`, `sex`, `create_time`) VALUES(10001, 0x' .
            bin2hex($data['username']) . ', 0x' .
            bin2hex($data['password']) . ', 0x' .
            bin2hex($data['avatar']) . ', ' . $data['sex'] . ', 0x' .
            bin2hex($data['create_time']) . ')',
            $sqlBuilder->toString()
        );
        echo $sqlBuilder->toString();
    }

    public function testUpdate()
    {
        $data = [
            'email' => 'comdeng@live.com',
            '@update_time' => 'CURRENT_TIMESTAMP()',
        ];
        $sqlBuilder = new SqlBuilder();
        $sqlBuilder->db('phpple')
            ->table('u_user')
            ->setData($data)
            ->where('id', 10000)
            ->update();
        $this->assertEquals(
            'UPDATE `phpple`.`u_user` SET `email` = 0x' . bin2hex($data['email']) . ', `update_time` = ' . $data['@update_time'] . ' WHERE (`id` = 10000)',
            $sqlBuilder->toString()
        );
        echo $sqlBuilder->toString();
    }

    public function testDelete()
    {
        $sqlBuilder = new SqlBuilder();
        $sqlBuilder->db('phpple')
            ->table('u_user')
            ->where('user_id', 4)
            ->where('status', -1, ISqlWhere::COMPARE_NOT_EQUAL)
            ->delete();
        $this->assertEquals(
            'DELETE FROM `phpple`.`u_user` WHERE (`user_id` = 4) AND (`status` != -1)',
            $sqlBuilder->toString()
        );
        echo $sqlBuilder->toString();
    }
}
