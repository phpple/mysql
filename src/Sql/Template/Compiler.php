<?php
/**
 *
 * @author: ronnie
 * @since: 2018/8/27 07:29
 * @copyright: 2018@hunbasha.com
 * @filesource: Temaplate.php
 */

namespace Phpple\Mysql\Sql\Template;

class Compiler
{
    const NONE = 'none';
    const SELECT = 'select';
    const SELECT_FOR_UPDATE = 'selectForUpdate';
    const SELECT_LAST_INSERT_ID = 'selectLastInsertId';
    const DESC_TABLE = 'desc';
    const SHOW_CREATE_TABLE = 'showCreateTable';
    const EXPLAIN = 'explain';
    const SHOW = 'show';

    const INSERT = 'insert';
    const INSERT_BATCH = 'insertBatch';
    const INSERT_IGNORE = 'insertIgnore';
    const INSERT_IGNORE_BATCH = 'insertIgnoreBatch';
    const INSERT_UPDATE = 'insertUpdate';
    const INSERT_UPDATE_BATCH = 'insertUpdateBatch';
    const UPDATE = 'update';
    const UPDATE_CASE = 'updateCase';
    const DELETE = 'delete';
    const REPLACE = 'replace';

    const LEFT_FLAG = '{';
    const RIGHT_FLAG = '}';
    /**
     * @var array sql操作和对应的模板
     */
    private static $sqlTemplates = [
        self::NONE => '',
        self::SELECT => 'SELECT {FIELDS} FROM {DB}.{TABLE}{JOIN}{WHERE}{ORDER}{GROUP}{LIMIT}',
        self::SELECT_FOR_UPDATE => 'SELECT {FIELDS} FROM {DB}.{TABLE}{JOIN}{WHERE}{ORDER}{GROUP}{LIMIT} FOR UPDATE',
        self::SELECT_LAST_INSERT_ID => 'SELECT LAST_INSERT_ID() AS LIID',
        self::DESC_TABLE => 'DESC {DB}.{TABLE}',
        self::SHOW_CREATE_TABLE => 'SHOW CREATE TABLE {DB}.{TABLE}',
        self::EXPLAIN => 'EXPLAIN {SQL}',
        self::SHOW => 'SHOW {SQL}',
        self::INSERT => 'INSERT INTO {DB}.{TABLE}({KEYS}) VALUES({VALUES})',
        self::INSERT_IGNORE => 'INSERT IGNORE INTO {DB}.{TABLE}({KEYS}) VALUES({VALUES})',
        self::INSERT_UPDATE => 'INSERT INTO {DB}.{TABLE}({KEYS}) VALUES({VALUES}) ON DUPLICATE KEY UPDATE {DUPUPDATES}',
        self::INSERT_BATCH => 'INSERT INTO {DB}.{TABLE}({KEYS}) VALUES{BATCHVALUES}',
        self::INSERT_IGNORE_BATCH => 'INSERT IGNORE INTO {DB}.{TABLE}({KEYS}) VALUES{BATCHVALUES}',
        self::INSERT_UPDATE_BATCH => 'INSERT INTO {DB}.{TABLE}({KEYS}) VALUES{BATCHVALUES} ON DUPLICATE KEY UPDATE {DUPUPDATES}',
        self::UPDATE => 'UPDATE {DB}.{TABLE} SET {UPDATES}{WHERE}',
        self::UPDATE_CASE => 'UPDATE {DB}.{TABLE} 
SET {FIELD}=CASE {PRIKEY}
{CASES}
END
{WHERE}',
        self::DELETE => 'DELETE FROM {DB}.{TABLE}{WHERE}',
        self::REPLACE => 'REPLACE INTO {DB}.{TABLE}({KEYS}) VALUES({VALUES})',
    ];

    /**
     * 用户自定义的模板
     * @var array
     */
    private static $userTemplates = [];

    /**
     * 添加模板
     * @param string $key
     * @param string $template
     * @throws \InvalidArgumentException 模板已经被定义
     */
    public static function addTemplate(string $key, string $template)
    {
        if (isset(self::$sqlTemplates[$key])) {
            throw new \DomainException('key defined');
        }
        self::$userTemplates[$key] = $template;
    }

    /**
     * 获取模板
     * @param string $key
     * @return bool
     */
    public static function validKey(string $key)
    {
        return isset(self::$sqlTemplates[$key]) || isset(self::$userTemplates[$key]);
    }

    /**
     * 编译某个模板
     * @param string $key
     * @param \callable $callback
     * @return string
     * @throws \InvalidArgumentException 回调函数必须为callable
     */
    public static function compile(string $key, $callback)
    {
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException('compiler.callbackMustBeCallable');
        }

        $template = '';
        if (isset(self::$sqlTemplates[$key])) {
            $template = self::$sqlTemplates[$key];
        } elseif (isset(self::$userTemplates[$key])) {
            $template = self::$userTemplates[$key];
        } else {
            throw new \InvalidArgumentException('compiler.templateKeyNotDefined ' . $key);
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

    /**
     * 使用变量编译模板
     * @param string $key
     * @param array $vars
     * @return string
     */
    public static function compileWithVars(string $key, array $vars)
    {
        return self::compile($key, function ($name) use ($vars) {
            if (array_key_exists($name, $vars)) {
                return $vars[$name];
            }
            return '';
        });
    }
}
