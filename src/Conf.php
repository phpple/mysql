<?php
/**
 * 数据库配置类
 * @author: ronnie
 * @since: 2018/8/27 16:43
 * @copyright: 2018@hunbasha.com
 * @filesource: Conf.php
 * @example 一个典型的配置文件如下
 * ```
 * [
 *   'alias' => [
 *       'alias1' => [
 *          'dbname' => 'pre_{key}',
 *          'instance' => [
 *             'master' => 'ip1',
 *             'slave' => ['ip2'],
 *          ]
 *       ],
 *       'alias2' => [
 *          'instance' => 'ip2',
 *       ],
 *   ],
 *   'instance' => [
 *      'ip1' => ['host' => '127.0.0.1', 'port' => 3306, 'user' => 'root', 'pass' => 'root', 'charset' => 'utf8'],
 *      'ip2' => ['host' => '127.0.0.1', 'port' => 3309, 'user' => 'root', 'pass' => 'root', 'charset' => 'utf8'],
 *   ],
 *   'db' => [
 *      'db1' => 'alias1',
 *      'db2' => 'alias2',
 *      'db3' => [
 *        'dbname' => 'phpple',
 *        'instance' => 'ip2',
 *        'persist' => true,
 *        'splits' => [
 *          'post' => [
 *            'field' => 'id',
 *            'method' => 'day',
 *            'args' => 4000000,
 *          ]
 *        ]
 *      ]
 *   ]
 * ]
 * ```
 */

namespace Phpple\Mysql;

class Conf
{
    private static $confs;
    /**
     * @var array 每个db对应的配置文件，key为dbname_[master|slave]
     */
    private static $dbConfs = array();

    const KEY_DBS = 'db';
    const KEY_ALIAS = 'alias';
    const KEY_INSTANCES = 'instance';

    const KEY_MASTER = 'master';
    const KEY_SLAVE = 'slave';

    const CONF_DB_NAME = 'dbname';
    const CONF_DB_INSTANCE = 'instance';
    const CONF_DB_PERSIST = 'persist';
    const CONF_DB_FLAG = '{key}';



    /**
     * 初始化db的全部配置
     * @param array $confs
     * @throws \InvalidArgumentException conf.keyNotFound
     */
    public static function init(array $confs)
    {
        if (!isset($confs[self::KEY_DBS])) {
            throw new \InvalidArgumentException('conf.dbKeyNotFound');
        }
        if (!isset($confs[self::KEY_INSTANCES])) {
            throw new \InvalidArgumentException('conf.instanceKeyNotFound');
        }
        self::$confs = $confs;
    }

    /**
     * 获取某个数据库对应的配置
     * @param string $db
     * @param bool $master
     * @param string $hash sql的hash值，用来明确使用哪个从库
     * @return array
     * ```
     * [
     *   'host' =>,
     *   'port' =>,
     *   'user' => ,
     *   'pass' => ,
     *   'db' => ,
     *   'charset' => ,
     *   'persist' => ,
     * ]
     * ```
     */
    public static function loadConf(string $db, $master, $hash)
    {
        if (!isset(self::$dbConfs[$db])) {
            if (!isset(self::$confs[self::KEY_DBS][$db])) {
                throw new \InvalidArgumentException('conf.dbNotDefined ' . $db);
            }
            self::initDbConf($db);
        }
        $confs = self::$dbConfs[$db];
        if ($master) {
            return $confs[self::KEY_MASTER];
        }
        $slaveNum = count($confs[self::KEY_SLAVE]);
        if ($slaveNum == 1) {
            return $confs[self::KEY_SLAVE][0];
        }
        // 通过一致性hash找出需要的从库
        $index = floor(($hash % 360) / (360 / $slaveNum));
        return $confs[self::KEY_SLAVE][$index];
    }

    /**
     * 初始化db的配置
     * @param $db
     */
    private static function initDbConf($db)
    {
        $alias = self::$confs[self::KEY_DBS][$db];
        if (is_string($alias)) {
            if (!isset(self::$confs[self::KEY_ALIAS][$alias])) {
                throw new \UnexpectedValueException('conf.aliasNotDefined ' . $alias);
            }
            $conf = self::$confs[self::KEY_ALIAS][$alias];
        } elseif (is_array($alias)) {
            $conf = $alias;
        } else {
            throw new \UnexpectedValueException('conf.aliasMustBeStringOrArray');
        }

        $dbConfs = [
            self::KEY_MASTER => [],
            self::KEY_SLAVE => [],
        ];

        // 检查instance
        if (!isset($conf[self::CONF_DB_INSTANCE])) {
            throw new \UnexpectedValueException('conf.instanceMustDefined');
        }
        // 初始化名称
        if (!isset($conf[self::CONF_DB_NAME])) {
            $conf[self::CONF_DB_NAME] = $db;
        } elseif (is_string($conf[self::CONF_DB_NAME])) {
            $name = str_replace(self::CONF_DB_FLAG, $db, $conf[self::CONF_DB_NAME]);
            $conf[self::CONF_DB_NAME] = $name;
        } else {
            throw new \UnexpectedValueException('conf.dbnameMustBeString');
        }
        // 初始化持久化
        if (!isset($conf[self::CONF_DB_PERSIST])) {
            $conf[self::CONF_DB_PERSIST] = false;
        }

        // 初始化instance
        $instances = $conf[self::CONF_DB_INSTANCE];
        if (is_string($instances)) {
            if (!isset(self::$confs[self::KEY_INSTANCES][$instances])) {
                throw new \UnexpectedValueException('conf.instanceNotFound '.$instances);
            }

            $dbConfs[self::KEY_MASTER] = self::$confs[self::KEY_INSTANCES][$instances];
            $dbConfs[self::KEY_MASTER][self::CONF_DB_NAME] = $conf[self::CONF_DB_NAME];
            $dbConfs[self::KEY_MASTER][self::CONF_DB_PERSIST] = $conf[self::CONF_DB_PERSIST];

            $dbConfs[self::KEY_SLAVE] = [$dbConfs[self::KEY_MASTER]];

            self::$dbConfs[$db] = $dbConfs;
            return;
        }

        if (!is_array($instances)) {
            throw new \UnexpectedValueException('conf.instanceMustBeStringOrArray');
        }

        // 如果instance是数组，则必须同时定义master和slave，且slave必须是数组
        if (!isset($instances[self::KEY_MASTER])
            || !isset($instances[self::KEY_SLAVE])
            || !is_array($instances[self::KEY_SLAVE])) {
            throw new \UnexpectedValueException('conf.masterAndSlaveMustDefinedBoth');
        }

        // 检查对应的实例是否已经被定义
        if (!isset(self::$confs[self::KEY_INSTANCES][$instances[self::KEY_MASTER]])) {
            throw new \UnexpectedValueException('conf.masterInstanceMustDefined');
        }

        // 初始化主库配置
        $masterConf = self::$confs[self::KEY_INSTANCES][$instances[self::KEY_MASTER]];
        $masterConf[self::CONF_DB_NAME] = $conf[self::CONF_DB_NAME];
        $masterConf[self::CONF_DB_PERSIST] = $conf[self::CONF_DB_PERSIST];
        $dbConfs[self::KEY_MASTER] = $masterConf;

        // 初始化从库配置
        $slaveConfs = [];
        foreach ($instances[self::KEY_SLAVE] as $slaveInstance) {
            if (!isset(self::$confs[self::KEY_INSTANCES][$slaveInstance])) {
                throw new \UnexpectedValueException('conf.slaveInstanceNotDefined ' . $slaveInstance);
            }
            $slaveConf = self::$confs[self::KEY_INSTANCES][$slaveInstance];
            $slaveConf[self::CONF_DB_NAME] = $conf[self::CONF_DB_NAME];
            $slaveConf[self::CONF_DB_PERSIST] = $conf[self::CONF_DB_PERSIST];
            $slaveConfs[] = $slaveConf;
        }
        $dbConfs[self::KEY_SLAVE] = $slaveConfs;
        self::$dbConfs[$db] = $dbConfs;
    }
}
