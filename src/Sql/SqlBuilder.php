<?php
/**
 *
 * @author: ronnie
 * @since: 2018/8/26 21:18
 * @copyright: 2018@hunbasha.com
 * @filesource: SqlBuilder.php
 */

namespace Phpple\Mysql\Sql;

use Phpple\Mysql\ISplit;
use Phpple\Mysql\Sql\Template\Compiler;

class SqlBuilder
{
    const ALL_FIELDS_FLAG = '*';
    /**
     * 使用此标识的字段，不对内容进行escapeVar操作
     */
    const RAW_VALUE_FLAG = '@';

    /**
     * sql语句的关键字段的区别符号
     */
    const SQL_KEYWORD_FLAG = '`';

    private $db;
    private $table;
    private $tableAlias;

    /**
     * @var array table的分库策略
     */
    private $tableSplit = null;
    /**
     * @var int 用来实现table分表的值
     */
    private $tableSplitValue;

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
     * @var array 限制条件
     */
    private $limit = [];

    /**
     * @var string[] 按什么排序
     */
    private $orders = [];

    /**
     * @var string[] 按什么分组
     */
    private $groups = [];

    /**
     * @var string 操作
     */
    private $operation = Compiler::SELECT;

    /**
     * @var array 需要更新的数据
     */
    private $data = [];

    /**
     * @var array 设置哪些是需要保存的数据
     */
    private $dataFields = [];


    /**
     * @var SqlBuilder
     */
    private $tail = null;

    /**
     * SqlBuilder快速初始化
     * @param $table
     * @return SqlBuilder
     */
    public static function withTable($table)
    {
        return (new SqlBuilder())->table($table);
    }

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
     * 获取db的sql
     * @return string
     */
    public function getDbSql()
    {
        return self::SQL_KEYWORD_FLAG . $this->db . self::SQL_KEYWORD_FLAG;
    }

    /**
     * 设置表名
     * @param string $table
     * @return $this
     */
    public function table(string $table)
    {
        $this->table = $table;
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
     * 设置分表方法和参数
     * @param string $method
     * @param mixed $args
     * @return $this
     */
    public function tableSplit(string $method, $args)
    {
        $this->tableSplit = [
            'method' => $method,
            'args' => $args
        ];
        return $this;
    }

    /**
     * 设置实现分表的决定值
     * @param int $value
     * @return $this
     */
    public function tableSplitValue(int $value)
    {
        $this->tableSplitValue = $value;
        return $this;
    }

    /**
     * 获取分表的名称
     * @param string $name
     * @param string $splitMethod
     * @param int $splitValue
     * @param mixed $splitArgs
     * @return string
     * @throws \DomainException sqlBuilder.splitMethodNotDefined
     */
    public static function getNameBySplit(string $name, string $splitMethod, int $splitValue, $splitArgs = null)
    {
        $subfix = '';
        switch ($splitMethod) {
            case ISplit::SPLIT_BY_MOD:
                if ($splitArgs == 10 || $splitArgs == 100 || $splitArgs == 1000) {
                    $len = strlen($splitArgs . '');
                    $v = substr($splitValue . '', -($len - 1));
                    if ($v === false) {
                        $subfix = $splitValue;
                    } else {
                        $subfix = intval($v);
                    }
                } else {
                    $subfix = $splitValue % $splitArgs;
                }
                break;
            case ISplit::SPLIT_BY_DIV:
                $subfix = round($splitValue / $splitArgs);
                break;
            case ISplit::SPLIT_BY_YEAR:
                $subfix = date('Y', $splitValue);
                break;
            case ISplit::SPLIT_BY_MONTH:
                $subfix = date('Ym', $splitValue);
                break;
            case ISplit::SPLIT_BY_DAY:
                $subfix = date('Ymd', $splitValue);
                break;
            default:
                throw new \DomainException('sqlBuilder.splitMethodNotDefined ' . $splitMethod);
        }
        return $name . ISplit::SPLIT_CONNECT_FLAG . $subfix;
    }

    /**
     * 获取table的sql
     * @return string
     */
    public function getTableSql()
    {
        if ($this->tableSplitValue && $this->tableSplit) {
            $table = self::getNameBySplit(
                $this->table,
                $this->tableSplit['method'],
                $this->tableSplitValue,
                $this->tableSplit['args']
            );
        } else {
            $table = $this->table;
        }
        $ret = self::SQL_KEYWORD_FLAG . $table . self::SQL_KEYWORD_FLAG;
        if ($this->tableAlias) {
            $ret .= ' ' . $this->tableAlias;
        }
        return $ret;
    }

    public function getJoinSql()
    {
        return '';
    }

    /**
     * 设置返回的字段
     * @param mixed ...$fields
     * @return $this
     */
    public function fields(...$fields)
    {
        $this->fields = [];
        foreach ($fields as $field) {
            $this->fields[] = $this->wrapperField($field);
        }
        return $this;
    }

    /**
     * 获取要查找的字段
     * @return string
     */
    public function getFieldsSql()
    {
        if (empty($this->fields)) {
            return self::ALL_FIELDS_FLAG;
        }
        return implode(', ', $this->fields);
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
        $this->causes[] = sprintf(
            '%s %s (%s)',
            $this->wrapperField($field),
            ISqlWhere::RANGE_IN,
            $this->escapeVal($items)
        );
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
        $this->causes[] = sprintf('(%s %s %s)', $this->wrapperField($field), $compare,
            $escape ? $this->escapeVal($value) : $value);
        return $this;
    }

    /**
     * 通过参数查询
     * @param string $cause 条件
     * @param mixed ...$params 参数
     * @example ->whereParams('a=? and (b=?  or c=?)', 3, 4, 5)
     * @return $this
     */
    public function whereParams(string $cause, ...$params)
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
        return '(' . implode(' ' . ISqlWhere::LOGIC_AND . ' ', $sqls) . ')';
    }

    /**
     * 将字符串进行escape，保证数据安全
     * @param $orig
     * @param int $level
     * @return string
     */
    public function escapeVal($orig, $level = 0)
    {
        $dest = $orig;
        switch (true) {
            case is_int($orig):
                $dest = $orig;
                break;
            case is_string($orig):
                $dest = '0x' . bin2hex($orig);
                break;
            case is_numeric($orig):
                if (0 == $orig) {
                    $dest = "'0'";
                    break;
                }
                break;
            case is_null($orig):
                $dest = 'NULL';
                break;
            case is_bool($orig):
                $dest = $orig ? 1 : 0;
                break;
            case is_array($orig) && $level < 2:
                if (empty($orig)) {
                    throw new \InvalidArgumentException('sqlBuilder.emptyNotAllowd');
                }
                $arr = [];
                foreach ($orig as $v) {
                    $arr[] = $this->escapeVal($v, $level + 1);
                }
                $dest = implode(', ', $arr);
                break;
            default:
                throw new \DomainException('sqlBuilder.notSupportType');
        }
        return $dest;
    }

    /**
     * 将字段进行包装
     * @param string $fieldStr
     * @return string
     * @TODO 需要进一步完善
     */
    public function wrapperField($fieldStr)
    {
        if (!$fieldStr || is_int($fieldStr)) {
            return $fieldStr;
        }
        $pos1 = strpos($fieldStr, '.');
        $pos2 = strpos($fieldStr, '(');
        if ($pos1 === false && $pos2 === false) {
            return self::SQL_KEYWORD_FLAG . $fieldStr . self::SQL_KEYWORD_FLAG;
        }
        return $fieldStr;
    }

    /**
     * 获取查询条件
     * @return string
     */
    private function getWhereSql()
    {
        if (count($this->causes) == 0) {
            return '';
        }
        return ' WHERE ' . implode(' ', array_slice($this->causes, 1));
    }

    /**
     * 设置保存哪些数据
     * @param array $data
     * @param null|array $fields 需要保存的字段是哪些
     * @return $this
     */
    public function setData(array $data, $fields = null)
    {
        $this->dataFields = $fields;
        if ($fields !== null) {
            $body = [];
            foreach ($fields as $key) {
                if (!array_key_exists($data, $key)) {
                    throw new \InvalidArgumentException('data key required:' . $key);
                } else {
                    $body[$key] = $data[$key];
                }
            }
            $this->data = $body;
        } else {
            $this->data = $data;
            $this->dataFields = array_keys($data);
        }
        return $this;
    }

    /**
     * 获取要更新的字段
     * @return string
     */
    public function getKeysSql()
    {
        $keys = [];
        foreach ($this->dataFields as $key) {
            if ($key[0] == self::RAW_VALUE_FLAG) {
                $keys[] = $this->wrapperField(substr($key, 1));
            } else {
                $keys[] = $this->wrapperField($key);
            }
        }
        return implode(', ', $keys);
    }

    /**
     * 获取要插入的数据
     */
    public function getValuesSql()
    {
        if (empty($this->data)) {
            return '';
        }
        $values = [];
        foreach ($this->dataFields as $field) {
            $value = $this->data[$field];
            if ($field[0] == self::RAW_VALUE_FLAG) {
                $values[] = $value;
            } else {
                $values[] = $this->escapeVal($this->data[$field]);
            }
        }
        return implode(', ', $values);
    }

    /**
     * 获取update的更新语句
     */
    public function getUpdatesSql()
    {
        if (empty($this->data)) {
            return '';
        }
        $kvs = [];
        foreach ($this->dataFields as $field) {
            if ($field[0] == self::RAW_VALUE_FLAG) {
                $kvs[] = sprintf('%s = %s', $this->wrapperField(substr($field, 1)), $this->data[$field]);
            } else {
                $kvs[] = sprintf('%s = %s', $this->wrapperField($field), $this->escapeVal($this->data[$field]));
            }
        }
        return implode(', ', $kvs);
    }


    /**
     * 限制获取多少条数据
     * @param int $num 多少条数据
     * @param int $offset 偏移量
     * @return $this
     */
    public function limit(int $offset, int $num)
    {
        $this->limit = [$offset, $num];
        return $this;
    }

    /**
     * 限制获取一条数据
     * @return $this
     */
    public function limitOne()
    {
        return $this->limit(0, 1)->select();
    }

    /**
     * 限制获取最前面的多少条数据
     * @param int $num
     * @return $this
     */
    public function limitFirst(int $num)
    {
        return $this->limit(0, $num)->select();
    }

    /**
     * 获取limit的查询sql
     */
    public function getLimitSql()
    {
        if (!$this->limit) {
            return '';
        }
        return sprintf(' LIMIT %d,%d', $this->limit[0], $this->limit[1]);
    }

    /**
     * 以什么字段排序
     * 可以多次调用
     * @param string $field 如果传入null，表示不进行任何排序，最后相当于order by null
     * @param bool $asc
     * @throws \InvalidArgumentException field sorted 字段不能被重复排序
     * @throws \InvalidArgumentException order by null defined 已经定义过order by null
     * @return $this
     */
    public function orderBy($field, bool $asc = true)
    {
        $field = $field === null ? $field : $this->wrapperField($field);
        foreach ($this->orders as $o) {
            if ($o[0] === null) {
                throw new \InvalidArgumentException('order by null defined');
            }
            if ($o[0] == $field) {
                throw new \InvalidArgumentException('field sorted');
            }
        }
        $this->orders[] = [$field === null ? 'NULL' : $field, $asc];
        return $this;
    }

    /**
     * 获取排序的sql
     * @return string
     */
    public function getOrderSql()
    {
        if (empty($this->orders)) {
            return '';
        }
        $sqls = [];
        foreach ($this->orders as $order) {
            if ($order[0] === 'NULL') {
                $sqls[] = 'NULL';
                continue;
            }
            $sqls[] = sprintf('%s %s', $order[0], $order[1] ? 'ASC' : 'DESC');
        }
        return ' ORDER BY ' . implode(', ', $sqls);
    }

    /**
     * 基于哪个字段分组
     * @param $fields
     * @return $this
     */
    public function groupBy(...$fields)
    {
        foreach ($fields as $key => $field) {
            $fields[$key] = $this->wrapperField($field);
        }
        $this->groups = $fields;
        return $this;
    }

    /**
     * 获取分组的sql
     * @return string
     */
    public function getGroupSql()
    {
        if (!$this->groups) {
            return '';
        }
        return ' GROUP BY ' . implode(', ', $this->groups);
    }

    /**
     * 设定操作为select
     * @param bool $forUpdate 是否锁定等待更新
     * @return SqlBuilder
     */
    public function select(bool $forUpdate = false)
    {
        if ($forUpdate) {
            return $this->operation(Compiler::SELECT_FOR_UPDATE);
        }
        return $this->operation(Compiler::SELECT);
    }

    /**
     * 获取数量
     * @param string $countField
     * @return $this
     */
    public function count($countField = 'CNT')
    {
        $this->fields = ['COUNT(0) ' . $countField];
        $this->select();
        return $this;
    }

    /**
     * 获取是否存在
     * @param int $existVal 如果存在时返回的字段值为多少
     * @return $this
     */
    public function exist($existVal = 1)
    {
        $this->fields = [$existVal];
        $this->select();
        return $this;
    }

    /**
     * 获取最后插入id的SqlBuilder
     * @return SqlBuilder
     */
    public static function lastInsertId()
    {
        return (new SqlBuilder())->operation(Compiler::SELECT_LAST_INSERT_ID);
    }

    /**
     * 描述一个表的结构
     * @param $db
     * @param $table
     * @return SqlBuilder
     */
    public static function descTable($db, $table)
    {
        return (new SqlBuilder())
            ->db($db)
            ->table($table)
            ->operation(Compiler::DESC_TABLE);
    }

    /**
     * 获取创建表的语句
     * @param $db
     * @param $table
     * @return SqlBuilder
     */
    public static function showCreateTable($db, $table)
    {
        return (new SqlBuilder())
            ->db($db)
            ->table($table)
            ->operation(Compiler::SHOW_CREATE_TABLE);
    }

    /**
     * 设定操作为更新
     * @return $this
     */
    public function update()
    {
        return $this->operation(Compiler::UPDATE);
    }

    /**
     * 设定操作为删除
     * @return $this
     */
    public function delete()
    {
        return $this->operation(Compiler::DELETE);
    }

    /**
     * 设定操作为插入
     * @return $this
     */
    public function insert()
    {
        return $this->operation(Compiler::INSERT);
    }

    /**
     * 设定操作为插入，如果遇到重复则忽略
     * @return $this
     */
    public function insertIgnore()
    {
        return $this->operation(Compiler::INSERT_IGNORE);
    }

    /**
     * 设定操作为插入，如果遇到重复则更新
     * @return $this
     */
    public function insertUpdate()
    {
        return $this->operation(Compiler::INSERT_UPDATE);
    }

    /**
     * 设置操作为select
     * @param string $operation
     * @return $this
     * @throws \InvalidArgumentException 错误的类型
     */
    public function operation(string $operation)
    {
        if (!Compiler::validKey($operation)) {
            throw new \InvalidArgumentException("illegal operation");
        }
        $this->operation = $operation;
        return $this;
    }

    /**
     * 生成模板变量
     * @param $name
     * @throws \InvalidArgumentException 变量尚未实现
     * @return string
     */
    public function generateTplVar($name)
    {
        $method = 'get' . ucfirst($name) . 'Sql';
        if (!method_exists($this, $method)) {
            throw new \DomainException('sqlBuilder.varNotImplemented ' . $name);
        }
        return call_user_func([$this, $method]);
    }

    /**
     * 追加一个SqlBuilder
     * @param SqlBuilder $builder
     * @return $this;
     */
    public function append(SqlBuilder $builder)
    {
        $this->tail = $builder;
        return $this;
    }

    /**
     * 将sql对象转化为字符串
     * @return string
     */
    public function toString()
    {
        $ret = Compiler::compile($this->operation, [$this, 'generateTplVar']);
        if ($this->tail) {
            $ret .= ';' . $this->tail->toString();
        }
        return $ret;
    }

    /**
     * 序列化输出字符
     * @return string
     */
    public function __toString()
    {
        return $this->toString();
    }
}
