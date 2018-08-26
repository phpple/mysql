<?php
/**
 *
 * @author: ronnie
 * @since: 2018/8/26 22:51
 * @copyright: 2018@hunbasha.com
 * @filesource: TemplateCompiler.php
 */

namespace Phpple\Mysql\Sql\Template;

class Compiler
{
    const LEFT_FLAG = '{';
    const RIGHT_FLAG = '}';

    /**
     * 编译
     * @param string $template
     * @param \callable $callback
     * @return string
     * @throws \InvalidArgumentException 回调函数必须为callable
     */
    public static function compile(string $template, $callback)
    {
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException('callback must be callable');
        }
        $sqls = [];
        $startPos = 0;
        while (true) {
            $leftPos = strpos($template, self::LEFT_FLAG, $startPos);
            if ($leftPos === false) {
                $sqls[] = substr($template, $startPos);
                break;
            }
            $rightPos = strpos($template, self::RIGHT_FLAG, $leftPos + 1);
            if ($rightPos === false) {
                $sqls[] = substr($template, $startPos);
                break;
            }
            $tag = substr($template, $leftPos + 1, $rightPos - $leftPos - 1);

            $sqls[] = substr($template, $startPos, $leftPos - $startPos);
            $sqls[] = call_user_func($callback, $tag);

            $startPos = $rightPos + 1;
        }
        return implode('', $sqls);
    }
}
