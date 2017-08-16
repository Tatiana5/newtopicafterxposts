<?php
/**
 *
 * @package       New topic after X posts
 * @copyright (c) Татьяна5
 * @license       http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

/**
 * DO NOT CHANGE
 */
if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

$lang = array_merge($lang, array(
	'NTAXP_JUMP_TO_TOPIC'	=> '%sЭта тема уже заполнена. Перейти в следующую >>%s',
	'NTAXP_NEXT_TOPIC'		=> 'Следующая тема',
	'NTAXP_PREVIOUS_TOPIC'	=> 'Предыдущая тема',
	//
	//
	'NTAXP_ACP_COPY_FIRST_POST'	=> 'Копировать первое сообщение из предыдущей темы при автосоздании новой',
	'NTAXP_ACP_POSTS'			=> 'Количество ответов в теме для создания новой',
	'NTAXP_ACP_POSTS_EXPLAIN'	=> 'Первое сообщение в теме - это не ответ',
));
