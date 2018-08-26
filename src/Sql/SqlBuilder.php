<?php
/**
 *
 * @author: ronnie
 * @since: 2018/8/26 21:18
 * @copyright: 2018@hunbasha.com
 * @filesource: SqlBuilder.php
 */

namespace Phpple\Mysql\Sql;

use Phpple\Mysql\Sql\Template\Compiler;

class SqlBuilder
{
    const ALL_FIELDS_FLAG = '*';

    private $db;
    private $table;
    private $tableAlias;
    /**
     * @var array
     */
    private $fields = [];
    /**
     * @var SqlJoin[]
     */
    private $joins = [];
    /**
     * @var array
     */
    private $causes = [];

    /**
     * @var string where语句的模板
     */
    private $whereTpl = '';

    /**
     * @var array 限制条件
     */
    private $limit = [];

    /**
     * @var string 按什么排序
     */
    private $sort;

    /**
     * @var string 操作
     */
    private $operation = ISqlOperation::SELECT;

    /**
     * @var array sql操作和对应的模板
     */
    private static $sqlTemplates = [
        ISqlOperation::SELECT => 'SELECT {FIELDS} FROM `{DB}`.`{TABLE}`{JOIN}{WHERE}{ORDER}{GROUP}{LIMIT}{FORUPDATE}',
        ISqlOperation::DESC => 'DESC `{DB}`.`{TABLE}`',
        ISqlOperation::EXPLAIN => 'EXPLAIN {SQL}',
        ISqlOperation::SHOW => 'SHOW {SQL}',
        ISqlOperation::INSERT => 'INSERT INTO `{DB}`.`{TABLE}`({FIELDS}) VALUES({VALUES})',
        ISqlOperation::INSERT_IGNORE => 'INSERT IGNORE INTO `{DB}`.`{TABLE}`({FIELDS}) VALUES({VALUES})',
        ISqlOperation::INSERT_UPDATE => 'INSERT INTO `{DB}`.`{TABLE}`({FIELDS}) VALUES({VALUES}) ON DUPLICATE KEY UPDATE {UPDATES}',
        ISqlOperation::UPDATE => 'UPDATE `{DB}`.`{TABLE}` SET {UPDATES} {WHERE}',
        ISqlOperation::UPDATE_CASE => 'UPDATE `{DB}`.`{TABLE}` 
SET {FIELD}=CASE {PRIKEY}
{CASES}
END
{WHERE}',
        ISqlOperation::DELETE => 'DELETE FROM `{DB}`.`{TABLE}` {WHERE}',
        ISqlOperation::REPLACE => 'REPLACE INTO `{DB}`.`{TABLE}`({FIELDS}) VALUES({VALUES})',
    ];

    /**
     * 设置db名
     * @param string $db
     * @return $this
     */
    public function db(string $db)
    {
        $this->db = $db;
        return $this;
    }

    /**
     * 设置表名
     * @param string $table
     * @param string $alias
     * @return $this
     */
    public function table(string $table, string $alias = '')
    {
        $this->table = $table;
        $this->tableAlias = $alias ? $alias : '';
        return $this;
    }

    /**
     * 设置表的别名
     * @param string $alias
     * @return $this
     */
    public function tableAlias(string $alias)
    {
        $this->tableAlias = $alias;
        return $this;
    }

    /**
     * 设置返回的字段
     * @param mixed ...$fields
     * @return $this
     */
    public function fields(...$fields)
    {
        foreach ($fields as $field) {
            $this->fields[] = '`' . $field . '`';
        }
        return $this;
    }

    /**
     * 获取要查找的字段
     * @return string
     */
    public function getFields()
    {
        if (empty($this->fields)) {
            return self::ALL_FIELDS_FLAG;
        }
        return implode(',', $this->fields);
    }

    /**
     * IN查询
     * @param string $field
     * @param array $items
     * @return $this
     */
    public function whereIn(string $field, array $items)
    {
        $this->causes[] = ISqlWhere::LOGIC_AND;
        $this->causes[] = "(`{$field}` " . ISqlWhere::RANGE_IN . " (" . $this->escapeVal($items) . "))'";
        return $this;
    }

    /**
     * 查询条件
     * @param string $field
     * @param mixed $value
     * @param string $compare
     * @param bool $escape
     * @return $this
     */
    public function where(string $field, $value, string $compare = ISqlWhere::COMPARE_EQUAL, bool $escape = true)
    {
        $this->causes[] = ISqlWhere::LOGIC_AND;
        $this->causes[] = sprintf('(`%s` %s %s)', $field, $compare, $escape ? $this->escapeVal($value) : $value);
        return $this;
    }

    /**
     * 通过参数查询
     * @param $cause 条件
     * @param mixed ...$params 参数
     * @example ->whereParams('a=? and (b=?  or c=?)', 3, 4, 5)
     * @return $this
     */
    public function whereParams($cause, ...$params)
    {
        $this->causes[] = ISqlWhere::LOGIC_AND;
        $this->causes[] = $this->buildParamWhere($cause, $params);

        return $this;
    }

    private function buildParamWhere($cause, array $params)
    {
        $num = substr_count($cause, ISqlWhere::SQL_PARAM_FLAG);
        if ($num != count($params)) {
            throw new \InvalidArgumentException('invalid num of params');
        }
        $cause = str_replace(ISqlWhere::SQL_PARAM_FLAG, '%s', $cause);
        $params = array_map([$this, 'escapeVal'], $params);
        array_unshift($params, $cause);
        return '(' . call_user_func_array('sprintf', $params) . ')';
    }

    /**
     * 通过OR连接查询表达式
     * @param array $causeA
     * @param array $causeB
     * @example
     * ```php
     * $sb->whereOr([
     *   'a=3',
     *   ['b<?', 4],
     * ], [
     *   ['c in(?), [5,6]]
     * ]);
     * ```
     * =>
     * ( ( (a=3) and (b<4) ) OR ( (c in (5,6)) ) )
     * @return $this
     */
    public function whereOr(array $causeA, array $causeB)
    {
        $this->causes[] = ISqlWhere::LOGIC_AND;

        $sqlA = $this->buildParamWheres($causeA);
        $sqlB = $this->buildParamWheres($causeB);

        $this->causes[] = "({$sqlA}" . ISqlWhere::LOGIC_OR . " {$sqlB})";

        return $this;
    }

    /**
     * 对数组的条件语句构建sql
     * @param $causes
     * @return string
     */
    private function buildParamWheres($causes)
    {
        $sqls = [];
        foreach ($causes as $cause) {
            if (is_string($cause)) {
                $sqls[] = "($cause)";
            } elseif (is_array($cause)) {
                if (count($cause) < 2) {
                    throw new \InvalidArgumentException('cause length must greater than 1');
                }
                $c = array_shift($cause);
                $sqls[] = $this->buildParamWhere($c, $cause);
            }
        }
        return '(' . implode(ISqlWhere::LOGIC_AND, $sqls) . ')';
    }

    /**
     * 将字符串进行escape，保证数据安全
     * @param $orig
     * @param int $level
     * @return string
     */
    public function escapeVal($orig, $level = 0)
    {
        switch (true) {
            case is_int($orig):
                return $orig;
            case is_string($orig):
                return 'unhex(' . bin2hex($orig) . ')';
            case is_numeric($orig):
                if (0 == $orig) {
                    return "'0'";
                }
                return $orig;
            case is_null($orig):
                return 'NULL';
            case is_bool($orig):
                return $orig ? 1 : 0;
            case is_array($orig) && $level < 2:
                if (empty($orig)) {
                    throw new \InvalidArgumentException('not support type');
                }
                $arr = [];
                foreach ($orig as $v) {
                    $arr[] = $this->escapeVal($v, $level + 1);
                }
                return implode('.', $arr);
            default:
                throw new \InvalidArgumentException('not support type');

        }
        if (is_numeric($orig)) {
            return $orig;
        }
        return 'unhex(' . bin2hex($orig) . ')';
    }


    /**
     * 获取查询条件
     * @return string
     */
    private function getWhere()
    {
        if (count($this->causes) == 0) {
            return '';
        }
        return ' WHERE ' . implode(' ', array_slice($this->causes, 1));
    }

    /**
     * 设定操作为select
     * @return SqlBuilder
     */
    public function select()
    {
        return $this->operation(ISqlOperation::SELECT);
    }

    /**
     * 设定操作为更新
     * @return $this
     */
    public function update()
    {
        return $this->operation(ISqlOperation::UPDATE);
    }

    /**
     * 设定操作为删除
     * @return $this
     */
    public function delete()
    {
        return $this->operation(ISqlOperation::DELETE);
    }

    /**
     * 设定操作为插入
     * @return $this
     */
    public function insert()
    {
        return $this->operation(ISqlOperation::INSERT);
    }

    /**
     * 设定操作为插入，如果遇到重复则忽略
     * @return $this
     */
    public function insertIgnore()
    {
        return $this->operation(ISqlOperation::INSERT_IGNORE);
    }

    /**
     * 设定操作为插入，如果遇到重复则更新
     * @return $this
     */
    public function insertUpdate()
    {
        return $this->operation(ISqlOperation::INSERT_UPDATE);
    }

    /**
     * 设置操作为select
     * @param int $operation
     * @return $this
     * @throws \InvalidArgumentException 错误的类型
     */
    public function operation(int $operation)
    {
        if (!isset(self::$sqlTemplates[$operation])) {
            throw new \InvalidArgumentException("illegal operation");
        }
        $this->operation = $operation;
        return $this;
    }

    /**
     * 生成模板变量
     * @param $name
     */
    public function generateTplVar($name)
    {
        switch ($name) {
            case 'DB':
                return $this->db;
                break;
            case 'TABLE':
                return $this->table;
            case 'FIELDS':
                return $this->getFields();
            case 'WHERE':
                return $this->getWhere();
        }
    }

    /**
     * 将sql对象转化为字符串
     */
    public function __toString()
    {
        $template = self::$sqlTemplates[$this->operation];
        return Compiler::compile($template, array($this, 'generateTplVar'));
    }
}
