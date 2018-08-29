<?php
/**
 *
 * @author: ronnie
 * @since: 2018/8/27 00:11
 * @copyright: 2018@hunbasha.com
 * @filesource: SqlWhere.php
 */

namespace Phpple\Mysql\Sql;

/**
 * Sql 表达式
 * // @see https://dev.mysql.com/doc/refman/5.6/en/expressions.html
 * @package Phpple\Mysql\Sql
 */
interface IExpression
{
    const SQL_PARAM_FLAG = '?';

    const EXPR_AND = 'AND';
    const EXPR_OR = 'OR';
    const EXPR_XOR = 'XOR';
    const EXPR_NOT = 'NOT';
    const EXPR_IS_NOT = 'IS NOT';
    const EXPR_IS = 'IS';

    const COMPARISON_EQUAL = '=';
    const COMPARISON_GREATER_OR_EQUAL = '>=';
    const COMPARISON_GREATER = '>';
    const COMPARISON_LESS_OR_EQUAL = '<=';
    const COMPARISON_LESS = '<';
    const COMPARISON_NOT_EQUAL = '!=';

    const PREDICATE_LIKE = 'LIKE';
    const PREDICATE_IN = 'IN';
    const PREDICATE_BETWEEN = 'BETWEEN';
    const PREDICATE_REGEXP = 'REGEXP';
}
