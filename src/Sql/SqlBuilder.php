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

    /**
     * 多条sql语句连接符号
     */
    const SQL_JOIN_FLAG = ';';

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
     * @var array|null 遇到重复key时更新的数据
     */
    private $dupUpdates = null;


    /**
     * @var SqlBuilder[] builder的跟随者
     */
    private $follows = [];

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
        // 将追加的SqlBuilder的db都重置成一样的
        foreach ($this->follows as $follow) {
            $follow->db($db);
        }
        return $this;
    }

    /**
     * 获取db的sql
     * @return string
     * @throws \UnexpectedValueException sqlBuilder.dbNotDefined
     */
    public function getDbSql()
    {
        if (!$this->db) {
            throw new \UnexpectedValueException('sqlBuilder.dbNotDefined');
        }
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
                    $subfix = intval($v);
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
     * @throws \UnexpectedValueException sqlBuilder.tableNotDefined
     */
    public function getTableSql()
    {
        if (!$this->table) {
            throw new \UnexpectedValueException('sqlBuilder.tableNotDefined');
        }
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
     * 清空所有的where查询
     * @return $this
     */
    public function unsetWhere()
    {
        $this->causes = [];
        return $this;
    }

    /**
     * IN查询
     * @param string $field
     * @param array $items
     * @return $this
     */
    public function whereIn(string $field, array $items)
    {
        $this->causes[] = IExpression::EXPR_AND;
        $this->causes[] = sprintf(
            '(%s %s (%s))',
            $this->wrapperField($field),
            IExpression::PREDICATE_IN,
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
    public function where(string $field, $value, string $compare = IExpression::COMPARISON_EQUAL, bool $escape = true)
    {
        $this->causes[] = IExpression::EXPR_AND;
        $this->causes[] = sprintf('(%s %s %s)', $this->wrapperField($field), $compare,
            $escape ? $this->escapeVal($value) : $value);
        return $this;
    }

    /**
     * 通过参数查询
     * @param string $cause 条件
     * @param mixed ...$params 参数
     * @example
     * ```php
     * ->whereParams('a=? and (b=?  or c=?)', 3, 4, 5)
     * ```
     * @return $this
     */
    public function whereParams(string $cause, ...$params)
    {
        $this->causes[] = IExpression::EXPR_AND;
        $this->causes[] = $this->buildParamWhere($cause, $params);

        return $this;
    }

    private function buildParamWhere($cause, array $params)
    {
        $num = substr_count($cause, IExpression::SQL_PARAM_FLAG);
        if ($num != count($params)) {
            throw new \InvalidArgumentException('sqlBuilder.invalidNumOfParams');
        }
        $cause = str_replace(IExpression::SQL_PARAM_FLAG, '%s', $cause);
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
        $this->causes[] = IExpression::EXPR_AND;

        $sqlA = $this->buildParamWheres($causeA);
        $sqlB = $this->buildParamWheres($causeB);

        $this->causes[] = "({$sqlA} " . IExpression::EXPR_OR . " {$sqlB})";

        return $this;
    }

    /**
     * 使用LIKE查询
     * @param string $field
     * @param string $expression 表达式。如 %title_%
     * @return $this
     */
    public function whereLike(string $field, string $expression)
    {
        $this->causes[] = IExpression::EXPR_AND;
        $this->causes[] = sprintf(
            '(%s %s %s)',
            $this->wrapperField($field),
            IExpression::PREDICATE_LIKE,
            $this->escapeVal($expression)
        );
        return $this;
    }

    /**
     * 使用BETWEEN查询
     * @param string $field
     * @param mixed $min
     * @param mixed $max
     * @example
     * ```
     * ->whereBetween('age', 20, 30)
     * ```
     * @return $this
     */
    public function whereBetween(string $field, $min, $max)
    {
        $this->causes[] = IExpression::EXPR_AND;
        $this->causes[] = sprintf(
            '(%s %s %s %s %s)',
            $this->wrapperField($field),
            IExpression::PREDICATE_BETWEEN,
            $this->escapeVal($min),
            IExpression::EXPR_AND,
            $this->escapeVal($max)
        );
        return $this;
    }

    /**
     * 使用REGEXP查询
     * @param string $field
     * @param string $regexp
     * @example
     * ```php
     * ->whereRegexp('email', '^user[0-9]+$')
     * ```
     * @return $this
     */
    public function whereRegexp(string $field, string $regexp)
    {
        $this->causes[] = IExpression::EXPR_AND;
        $this->causes[] = sprintf(
            '(%s %s %s)',
            $this->wrapperField($field),
            IExpression::PREDICATE_REGEXP,
            $this->escapeVal($regexp)
        );
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
            } elseif (is_array($cause) && !empty($cause)) {
                $num = count($cause);

                if ($num == 1) {
                    $sqls[] = '(' . $cause[0] . ')';
                } else {
                    $c = array_shift($cause);
                    $sqls[] = $this->buildParamWhere($c, $cause);
                }
            }
        }
        return '(' . implode(' ' . IExpression::EXPR_AND . ' ', $sqls) . ')';
    }

    /**
     * 将字符串进行escape，保证数据安全
     * @param $orig
     * @param int $level
     * @return string
     * @throws \InvalidArgumentException sqlBuilder.emptyNotAllowd
     * @throws \InvalidArgumentException sqlBuilder.notSupportType
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
                $dest = $orig;
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
                throw new \InvalidArgumentException('sqlBuilder.notSupportType');
        }
        return $dest;
    }

    /**
     * 将字段进行包装
     * @param string $fieldStr
     * @return string
     * @throws \InvalidArgumentException sqlBuilder.illealField
     * @TODO 需要进一步完善
     */
    public function wrapperField($fieldStr)
    {
        if (!$fieldStr || is_int($fieldStr)) {
            throw new \InvalidArgumentException('sqlBuilder.illealField');
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
     * @throws \InvalidArgumentException sqlBuilder.dataNotAllowEmpty
     * @throws \InvalidArgumentException sqlBuilder.fieldsMustBeArray
     * @throws \InvalidArgumentException sqlBuilder.dataKeyRequired
     */
    public function setData(array $data, $fields = null)
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('sqlBuilder.dataNotAllowEmpty');
        }
        $this->dataFields = $fields;
        if ($fields !== null) {
            if (!is_array($fields)) {
                throw new \InvalidArgumentException('sqlBuilder.fieldsMustBeArray');
            }
            $body = [];
            foreach ($fields as $key) {
                if (!array_key_exists($key, $data)) {
                    throw new \InvalidArgumentException('sqlBuilder.dataKeyRequired ' . $key);
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
     * @throws \UnexpectedValueException sqlBuilder.dataEmpty
     */
    public function getKeysSql()
    {
        if (empty($this->data)) {
            throw new \UnexpectedValueException('sqlBuilder.dataEmpty');
        }
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
     * @throws \UnexpectedValueException sqlBuilder.dataEmpty
     */
    public function getValuesSql()
    {
        if (empty($this->data)) {
            throw new \UnexpectedValueException('sqlBuilder.dataEmpty');
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
     * @throws \UnexpectedValueException sqlBuilder.dataEmpty
     */
    public function getUpdatesSql()
    {
        if (empty($this->data)) {
            throw new \UnexpectedValueException('sqlBuilder.dataEmpty');
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
     * 当遇到重复的键时需要更新的数据
     * @param array|null $updates
     * @return $this
     * @throws \InvalidArgumentException sqlBuilder.updatesNotAllowEmpty
     */
    public function onDuplicate($updates)
    {
        if ($updates !== null && is_array($updates) && empty($updates)) {
            throw new \InvalidArgumentException('sqlBuilder.updatesNotAllowEmpty');
        }
        $this->dupUpdates = $updates;
        return $this;
    }

    /**
     * 获取遇到重复的键时需要更新的数据的sql
     * @return string
     */
    public function getDupupdatesSql()
    {
        if (!empty($this->dupUpdates)) {
            $sqls = [];
            foreach ($this->dupUpdates as $key => $value) {
                $raw = false;
                if ($key[0] == self::RAW_VALUE_FLAG) {
                    $raw = true;
                    $key = substr($key, 1);
                }
                $key = self::SQL_KEYWORD_FLAG . $key . self::SQL_KEYWORD_FLAG;
                if ($value === null) {
                    $sqls[] = $key . ' = VALUES(' . $key . ')';
                } elseif ($raw) {
                    $sqls[] = $key . ' = ' . $value;
                } else {
                    $sqls[] = $key . ' = ' . $this->escapeVal($value);
                }
            }
            return implode(', ', $sqls);
        }

        // 没有设置dupUpdates时
        $sqls = [];
        foreach ($this->dataFields as $key) {
            $raw = false;
            $rawValue = null;
            if ($key[0] == self::RAW_VALUE_FLAG) {
                $raw = true;
                $rawValue = $this->data[$key];
                $key = substr($key, 1);
            }
            $key = self::SQL_KEYWORD_FLAG . $key . self::SQL_KEYWORD_FLAG;
            // 有可能是表达式
            if ($raw) {
                $sqls[] = $key . ' = ' . $rawValue;
            } else {
                $sqls[] = $key . ' = VALUES(' . $key . ')';
            }
        }
        return implode(', ', $sqls);
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
    public function limitTop(int $num)
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
        return sprintf(' LIMIT %d, %d', $this->limit[0], $this->limit[1]);
    }

    /**
     * 以什么字段排序
     * 可以多次调用
     * @param string $field 如果传入null，表示不进行任何排序，最后相当于order by null
     * @param bool $asc
     * @throws \InvalidArgumentException sqlBuilder.fieldSortedYet 字段不能被重复排序
     * @throws \InvalidArgumentException sqlBuilder.orderByNullDefined 已经定义过order by null
     * @return $this
     */
    public function orderBy($field, bool $asc = true)
    {
        $field = $field === null ? $field : $this->wrapperField($field);
        foreach ($this->orders as $o) {
            if ($o[0] === null) {
                throw new \InvalidArgumentException('sqlBuilder.orderByNullDefined');
            }
            if ($o[0] == $field) {
                throw new \InvalidArgumentException('sqlBuilder.fieldSortedYet');
            }
        }
        $this->orders[] = [$field === null ? null : $field, $asc];
        return $this;
    }

    /**
     * 重置orderBy条件
     * @return $this
     */
    public function unsetOrderBy()
    {
        $this->orders = [];
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
            if ($order[0] === null) {
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
     * 返回一个空构建器
     * @return SqlBuilder
     */
    public static function none()
    {
        return (new SqlBuilder())->operation(Compiler::NONE);
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
            throw new \DomainException("sqlBuilder.operationNotDefine " . $operation);
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
        $method = 'get' . ucfirst(strtolower($name)) . 'Sql';
        if (!method_exists($this, $method)) {
            throw new \DomainException('sqlBuilder.varNotImplemented ' . $method);
        }
        return call_user_func([$this, $method]);
    }

    /**
     * 追加一个SqlBuilder
     * @param SqlBuilder $builder
     * @return $this;
     */
    public function push(SqlBuilder $builder)
    {
        $this->follows[] = $builder;
        if ($this->db) {
            $builder->db($this->db);
        }
        return $this;
    }

    /**
     * 将follow清理掉
     * @return $this
     */
    public function unsetFollows()
    {
        $this->follows = [];
        return $this;
    }

    /**
     * 取出所有的follow
     * @return \Generator
     */
    public function fetchFollows()
    {
        foreach ($this->follows as $follow) {
            yield $follow;
        }
    }

    /**
     * 将sql对象转化为字符串
     * @return string
     */
    public function toString()
    {
        $sqls = [];
        $sql = Compiler::compile($this->operation, [$this, 'generateTplVar']);
        if ($sql) {
            $sqls[] = $sql;
        }

        foreach ($this->follows as $follow) {
            $sql = $follow->toString();
            if ($sql) {
                $sqls[] = $sql;
            }
        }
        $lastSql = implode(self::SQL_JOIN_FLAG, $sqls);
        error_log('sql:' . $lastSql, 4);
        return $lastSql;
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
