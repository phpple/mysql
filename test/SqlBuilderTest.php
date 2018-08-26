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
            ->whereParams('user_id=? AND status != -1', 2)
            ->select();
        $this->assertEquals(
            'SELECT `user_id`,`username`,`email` FROM `user`.`u_user` WHERE (user_id=2 AND status != -1)',
            $sqlBuilder->__toString()
        );
        echo $sqlBuilder->__toString();
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
            $sqlBuilder->__toString()
        );
        echo $sqlBuilder->__toString();
    }

    public function testInsert()
    {

    }
}
