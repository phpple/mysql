<?php
/**
 *
 * @author: ronnie
 * @since: 2018/8/26 23:01
 * @copyright: 2018@hunbasha.com
 * @filesource: CompilerTest.php
 */

namespace Phpple\Mysql\Tests;

use Phpple\Mysql\Sql\Template\Compiler;

/**
 * Class CompilerTest
 * @package Phpple\Mysql\Test
 */
class CompilerTest extends \PHPUnit\Framework\TestCase
{
    private $datas = [
        'DB' => '`user`',
        'FIELDS' => '*',
        'TABLE' => '`u_user`',
        'WHERE' => ' WHERE `id` = 243',
    ];

    public function getDataValue($tag)
    {
        return $this->datas[$tag] ?? '';
    }

    /**
     * @covers \Phpple\Mysql\Sql\Template\Compiler::compile()
     */
    public function testCompile()
    {
        $sql = Compiler::compile(Compiler::SELECT, [$this, 'getDataValue']);
        $this->assertEquals('SELECT * FROM `user`.`u_user` WHERE `id` = 243', $sql);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage compiler.callbackMustBeCallable
     */
    public function testCompileFailed()
    {
        Compiler::compile(Compiler::SELECT, 'sdfsdfdsf');
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessageRegExp #^compiler.templateKeyNotDefined#
     */
    public function testCompiledNotFound()
    {
        Compiler::compile('sssssssssss', [$this, 'getDataValue']);
    }

    public function testAddTemplate()
    {
        $key = 'foo';
        Compiler::addTemplate($key, 'SELECT * FROM {TABLE}{JOIN} { WHERE status=1 limit 10');
        $sql = Compiler::compile($key, [$this, 'getDataValue']);
        $this->assertEquals(
            'SELECT * FROM `u_user` { WHERE status=1 limit 10',
            $sql
        );
        $sql2 = Compiler::compileWithVars($key, $this->datas);
        $this->assertEquals(
            $sql,
            $sql2
        );
    }

    /**
     * @expectedException \DomainException
     */
    public function testAddFailed()
    {
        Compiler::addTemplate(Compiler::INSERT_UPDATE, 'FOO');
    }

    public function testValid()
    {
        $valid = Compiler::validKey(Compiler::INSERT_UPDATE);
        $this->assertTrue($valid);

        $newKey = 'hello';
        $valid = Compiler::validKey($newKey);
        $this->assertFalse($valid);

        Compiler::addTemplate($newKey, 'FOO');
        $valid = Compiler::validKey($newKey);
        $this->assertTrue($valid);
    }
}
