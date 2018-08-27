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

        $db = Db::get('demo');
        $db->table('u_user')->where('status', 0);
        $count = $db->getCount();
        $this->assertGreaterThan(0, $count);
    }

    public function testSelect()
    {
        Conf::init($this->confs);

        $db = Db::get('demo');
        $db->table('u_user')
            ->fields('id', 'username', 'city_id', 'email', 'phone')
            ->where('id', 10000, ISqlWhere::COMPARE_GREATER_OR_EQUAL);
        $rows = [];
        foreach ($db->getAll() as $row) {
            if ($row['phone'] != 0) {
                $row['verified'] = true;
            }
            $rows[] = $row;
        }
        $this->assertNotEmpty($rows);
    }
}
