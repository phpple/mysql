<?php
/**
 *
 * @author: ronnie
 * @since: 2018/8/28 10:17
 * @copyright: 2018@hunbasha.com
 * @filesource: ISplit.php
 */

namespace Phpple\Mysql;

interface ISplit
{
    /**
     * 分库/表连接符
     */
    const SPLIT_CONNECT_FLAG = '_';

    // ID取模
    const SPLIT_BY_MOD = 'mod';
    // ID区间
    const SPLIT_BY_DIV = 'div';
    // 按年分
    const SPLIT_BY_YEAR = 'year';
    // 按月份
    const SPLIT_BY_MONTH = 'month';
    // 按天分
    const SPLIT_BY_DAY = 'day';
}
