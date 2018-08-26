<?php
/**
 *
 * @author: ronnie
 * @since: 2018/8/27 00:11
 * @copyright: 2018@hunbasha.com
 * @filesource: SqlWhere.php
 */

namespace Phpple\Mysql\Sql;

interface ISqlWhere
{
    const SQL_PARAM_FLAG = '?';

    const COMPARE_EQUAL = '=';
    const COMPARE_GREATER = '>';
    const COMPARE_LESS = '<';
    const COMPARE_NOT_EQUAL = '!=';
    const COMPARE_IS_NOT = 'is not';
    const COMPARE_GREATER_OR_EQUAL = '>=';
    const COMPARE_LESS_OR_EQUAL = '<=';

    const LOGIC_AND = 'AND';
    const LOGIC_OR = 'OR';

    const RANGE_IN = 'IN';
    const RANGE_BETWEEN = 'BETWEEN';
}
