<?php
/**
 *
 * @author: ronnie
 * @since: 2018/8/26 23:01
 * @copyright: 2018@hunbasha.com
 * @filesource: CompilerTest.php
 */

namespace Phpple\Mysql\Test;

use Phpple\Mysql\Sql\Template\Compiler;

class CompilerTest extends \PHPUnit\Framework\TestCase
{
    public function testCompile()
    {
        $datas = [
            'DB' => 'user',
            'TABLE' => 'u_user',
            'ID' => 243
        ];
        $template = 'SELECT * FROM `{DB}`.`{TABLE}` WHERE id={ID}';
        $sql = Compiler::compile($template, function ($tag) use ($datas) {
            return $datas[$tag] ?? '';
        });
        $this->assertEquals('SELECT * FROM `user`.`u_user` WHERE id=243', $sql);
    }
}
