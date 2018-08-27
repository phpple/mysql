<?php
/**
 *
 * @author: ronnie
 * @since: 2018/8/27 15:47
 * @copyright: 2018@hunbasha.com
 * @filesource: Mysql.php
 */

namespace Phpple\Mysql;

use Phpple\Mysql\Sql\SqlBuilder;

class Db
{
    /**
     * @var string
     */
    private $db;

    /**
     * @var 数据库真正的库名
     */
    private $dbRealName;

    /**
     * @var SqlBuilder
     */
    private $sqlBuilder;

    /**
     * @var array 数据库配置
     */
    private static $confs;

    /**
     * @var array 数据库配置
     */
    private $dbConf = null;

    /**
     * 数据库连接的缓存
     * @var array
     */
    private static $dbCache = [];

    /**
     * @param string $db
     * @return Db
     */
    public static function get(string $db)
    {
        return new self($db);
    }

    private function __construct($db)
    {
        // 获取数据库对应的配置，并获取数据库的库名
        $conf = Conf::loadConf($db, true, 0);
        $this->db = $db;
        $this->dbRealName = $conf['dbname'];
    }

    /**
     * 设定table，返回一个sqlBuilder
     * @param $table
     * @return SqlBuilder
     */
    public function table($table)
    {
        return $this->sqlBuilder = (new SqlBuilder())
            ->db($this->dbRealName)
            ->table($table);
    }

    /**
     * 真正执行sql查询
     * @param string $sql
     * @param \mysqli $mysqli
     * @return \mysqli_result|true
     */
    private function realQuery(string $sql, &$mysqli = null)
    {
        $master = preg_match('#^(INSERT|UPDATE|DELETE|DROP|ALTER) #i', $sql) !== false;
        $hash = crc32($sql);
        $conf = Conf::loadConf($this->db, $master, $hash);

        $mysqli = $this->getMysqli($conf);
        $ret = $mysqli->query($sql);
        if ($ret === false) {
            throw new \UnexpectedValueException($mysqli->error);
        }
        return $ret;
    }

    /**
     * 获取数据库连接
     * @param array $conf
     * @return \mysqli
     */
    private function getMysqli(array $conf)
    {
        $cacheKey = implode('-', [rawurlencode($conf['host']), $conf['port'], $conf['user'], $conf['pass']]);
        if (isset(self::$dbCache[$cacheKey])) {
            return self::$dbCache[$cacheKey];
        }
        $mysqli = new \mysqli();
        $mysqli->real_connect($conf['host'], $conf['user'], $conf['pass'], null, $conf['port']);
        $mysqli->set_charset($conf['charset']);
        return self::$dbCache[$cacheKey] = $mysqli;
    }

    /**
     * 获取全部记录
     * 使用yield获取每一行的值
     * @example
     * ```
     * foreach($db->fetchAll() as $row) {
     *    echo $row;
     * }
     * ```
     */
    public function getAll()
    {
        $sql = $this->sqlBuilder->toString();
        $rs = $this->realQuery($sql);
        while (($row = $rs->fetch_assoc())) {
            yield $row;
        }
        $rs->close();
    }

    /**
     * 获取一行记录
     * @
     */
    public function getOne()
    {
        $sql = $this->sqlBuilder->limitOne()->toString();
        $rs = $this->realQuery($sql);
        $row = $rs->fetch_assoc();
        $rs->close();
        return $row;
    }

    /**
     * 获取一个单一的值
     * @return mixed|null 没有找到时返回null
     */
    public function getSingle()
    {
        $sql = $this->sqlBuilder->limitOne()->toString();
        $rs = $this->realQuery($sql);
        $row = $rs->fetch_row();
        $rs->close();
        if ($row !== null) {
            return $row[0];
        }
        return null;
    }

    /**
     * 获取总数
     * @return int
     */
    public function getCount()
    {
        $sql = $this->sqlBuilder->count()->toString();
        $rs = $this->realQuery($sql);
        $row = $rs->fetch_row();
        $rs->close();
        if ($row === null) {
            return 0;
        }
        return $row[0];
    }

    /**
     * 获取满足条件的记录是否存在
     * @return bool
     */
    public function getExist()
    {
        $sql = $this->sqlBuilder->exist()->toString();
        $rs = $this->realQuery($sql);
        $row = $rs->fetch_row();
        $rs->close();
        return $row[0] == 1;
    }

    /**
     * 执行DDL操作
     * @return int 影响条数
     */
    public function execute()
    {
        $sql = $this->sqlBuilder->toString();
        $rs = $this->realQuery($sql, $mysqli);
        if ($rs !== true) {
            throw new \InvalidArgumentException('sql is not DDL mode');
        }
        return $mysqli->affected_rows;
    }
}
