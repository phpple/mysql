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
            'SELECT `user_id`,`username`,`email` FROM `user`.`u_user` WHERE (`user_id` = 234) AND (`status` != -1) ORDER BY `user_id` ASC,`username` DESC LIMIT 0,1',
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
            'SELECT `city_id`,count(0) FROM `user`.`u_user` WHERE (`status` != -1) GROUP BY `city_id`',
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
            'SELECT * FROM `user`.`u_user` WHERE (`user_id` = 10000) FOR UPDATE',
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

        $sqlBuilder = SqlBuilder::descTable('user', 'u_user');
        $this->assertEquals(
            'DESC `user`.`u_user`',
            $sqlBuilder->toString()
        );

        $sqlBuilder = SqlBuilder::showCreateTable('user', 'u_user');
        $this->assertEquals(
            'SHOW CREATE TABLE `user`.`u_user`',
            $sqlBuilder->toString()
        );
    }

    public function testDelete()
    {
        $sqlBuilder = new SqlBuilder();
        $sqlBuilder->db('user')
            ->table('u_user')
            ->where('user_id', 4)
            ->where('status', -1, ISqlWhere::COMPARE_NOT_EQUAL)
            ->delete();
        $this->assertEquals(
            'DELETE FROM `user`.`u_user` WHERE (`user_id` = 4) AND (`status` != -1)',
            $sqlBuilder->toString()
        );
        echo $sqlBuilder->toString();
    }

    public function testInsert()
    {

    }
}
