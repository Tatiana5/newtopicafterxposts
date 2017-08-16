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
	'NTAXP_JUMP_TO_TOPIC'	=> '%sThis topic is filled. Go to next >>%s',
	'NTAXP_NEXT_TOPIC'		=> 'Next topic',
	'NTAXP_PREVIOUS_TOPIC'	=> 'Previous topic',
	//
	'NTAXP_ACP_COPY_FIRST_POST'	=> 'Copying the first post from the previous topic when a new one is automatically created',
	'NTAXP_ACP_POSTS'			=> 'Number of replies in the topic to create a new one',
	'NTAXP_ACP_POSTS_EXPLAIN'	=> 'First post in the topic is not a reply',
));
