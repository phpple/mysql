<?php
/**
 *
 * @author: ronnie
 * @since: 2018/8/26 21:24
 * @copyright: 2018@hunbasha.com
 * @filesource: ISqlOperation.php
 */

namespace Phpple\Mysql\Sql;

interface ISqlOperation
{
    const SELECT = 0x01;
    const DESC = 0x02;
    const EXPLAIN = 0x04;
    const SHOW = 0x08;

    const INSERT = 0x0100;
    const INSERT_IGNORE = 0x0200;
    const INSERT_UPDATE = 0x0400;
    const UPDATE = 0x0800;
    const UPDATE_CASE = 0x1000;
    const DELETE = 0x2000;
    const REPLACE = 0x4000;
}
