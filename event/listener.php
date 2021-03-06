<?php
/**
*
* @package phpBB Extension - New topic after X posts
* @copyright (c) 2017 Татьяна5
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/
namespace tatiana5\newtopicafterxposts\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
* Event listener
*/
class listener implements EventSubscriberInterface
{
/**
* Assign functions defined in this class to event listeners in the core
*
* @return array
* @static
* @access public
*/
	static public function getSubscribedEvents()
	{
		return array(
			'core.posting_modify_submit_post_before'		=> 'find_next_topic',
			'core.submit_post_modify_sql_data'				=> 'fix_topic_poster',
			'core.submit_post_end'				=> 'post_new_topic',
			'core.acp_board_config_edit_add'	=> 'acp_settings'
		);
	}

	/** @var \phpbb\config\config */
	protected $config;

	/** @var \phpbb\db\driver\driver_interface */
	protected $db;

	/** @var \phpbb\user */
	protected $user;

	/** @var \phpbb\request\request */
	protected $request;

	/** @var string phpbb_root_path */
	protected $phpbb_root_path;

	/** @var string php_ext */
	protected $php_ext;

	/** @var string table_prefix */
	protected $table_prefix;

	/** @var string topics_table */
	protected $topics_table;

	/** @var string posts_table */
	protected $posts_table;

	/** @var string users_table */
	protected $users_table;

	/** @var string topic_title */
	protected $topic_title = '';

	/** @var string next_topic_title */
	protected $next_topic_title = '';

	/** @var number postcount */
	protected $postcount = 0;

	/** @var array new_topic_poster */
	protected $new_topic_poster = array();

	/**
	* Constructor
	*/
	public function __construct(\phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, \phpbb\user $user,
								\phpbb\request\request $request, $phpbb_root_path, $php_ext,
								$table_prefix, $topics_table, $posts_table, $users_table)
	{
		$this->config = $config;
		$this->db = $db;
		$this->user = $user;
		$this->request = $request;
		$this->phpbb_root_path = $phpbb_root_path;
		$this->php_ext = $php_ext;
		$this->table_prefix = $table_prefix;
		$this->topics_table = $topics_table;
		$this->posts_table = $posts_table;
		$this->users_table = $users_table;
	}

	public function find_next_topic($event)
	{
		$mode = $event['mode'];
		$data = $event['data'];
		$post_data = $event['post_data'];

		$this->postcount = (isset($post_data['topic_posts_approved']) && isset($post_data['topic_posts_unapproved'])) ? ($post_data['topic_posts_approved'] + $post_data['topic_posts_unapproved']) : 0;
		if ($mode == 'reply' && $this->postcount >= (int) $this->config['ntaxp_posts'])
		{
			if (isset($data['topic_title']))
			{
				$this->topic_title = $data['topic_title'];
			}
			else
			{
				$sql = 'SELECT topic_title FROM ' . $this->topics_table . ' WHERE topic_id = ' . (int) $data['topic_id'];
				$result = $this->db->sql_query_limit($sql, 1);
				$this->topic_title = $this->db->sql_fetchrow($result);
				$this->db->sql_freeresult($result);
			}

			//Number of topic
			$this->next_topic_subject = $this->topic_title;
			if (preg_match('/ № (\d+)$/', $this->next_topic_subject, $topic_number))
			{
				$next_topic_number = (int) $topic_number[1] + 1;
				$this->next_topic_subject = preg_replace('/ № (\d+)$/', '', $this->next_topic_subject);
			}
			else
			{
				$next_topic_number = 2;
			}

			// 112 = 120 - 7 ('Re: ' length + ' № ' length) - 1 (offset)
			$this->next_topic_subject = mb_substr($this->next_topic_subject, 0, 112 - strlen($next_topic_number));
			$this->next_topic_subject .= ' № ' . $next_topic_number;

			$sql = 'SELECT topic_id FROM ' . $this->topics_table . " WHERE topic_title = '" .  $this->db->sql_escape($this->next_topic_subject) . "'";
			$result = $this->db->sql_query_limit($sql, 1);
			$topic_exist = $this->db->sql_fetchrow($result);
			$this->db->sql_freeresult($result);

			if ($topic_exist)
			{
				$this->user->add_lang_ext('tatiana5/newtopicafterxposts', 'newtopicafterxposts');

				$redirect = append_sid("{$this->phpbb_root_path}viewtopic.$this->php_ext", 't=' . $topic_exist['topic_id']);
				$redirect = meta_refresh(3, $redirect);

				if ($this->request->is_ajax() && $this->request->is_set_post('qr'))
				{
					$json = new \phpbb\json_response();
					$json->send(array(
						'status'        => 'success',
						'url'  => $redirect,
						'MESSAGE_TITLE' => $this->user->lang['INFORMATION'],
						'MESSAGE_TEXT'  => sprintf($this->user->lang['NTAXP_JUMP_TO_TOPIC'], '<a href="' . $redirect . '">', '</a>', $this->next_topic_subject),
					));
				}

				trigger_error(sprintf($this->user->lang['NTAXP_JUMP_TO_TOPIC'], '<a href="' . $redirect . '">', '</a>', $this->next_topic_subject));
			}
		}
	}

	public function post_new_topic($event)
	{
		$data = $event['data'];
		$post_data = $event['post_data'];
		$mode = $event['mode'];

		if ($mode == 'reply' && $this->postcount >= (int) $this->config['ntaxp_posts'])
		{
			$this->user->add_lang_ext('tatiana5/newtopicafterxposts', 'newtopicafterxposts');

			// Lock old topic
			$this->db->sql_query('UPDATE ' . $this->topics_table . ' SET topic_status = ' . ITEM_LOCKED . ' WHERE topic_id = ' . (int) $data['topic_id'] . ' AND topic_moved_id = 0');

			$data['message'] = generate_text_for_edit($data['message'], $data['bbcode_uid'], true);
			$data['message'] = htmlspecialchars(html_entity_decode($data['message']['text']));

			// variables to hold the parameters for submit_post
			$poll = $uid = $bitfield = $options = '';
			$allow_bbcode = $data['enable_bbcode'];
			$allow_urls = $data['enable_urls'];
			$allow_smilies = $data['enable_smilies'];

			//Create next topic
			if ($this->config['ntaxp_copy_first_post'])
			{
				$topic_id = 0;

				$sql = 'SELECT p.post_text, p.post_id, p.poster_id, p.bbcode_uid, t.topic_first_poster_name, t.topic_id, u.user_colour, p.poster_ip FROM ' . $this->topics_table . ' t

				LEFT JOIN ' . $this->posts_table . ' p
				ON p.post_id = t.topic_first_post_id

				LEFT JOIN ' . $this->users_table . ' u
				ON u.user_id = p.poster_id

				WHERE t.topic_last_post_id = ' . (int) $data['post_id'];

				$result = $this->db->sql_query_limit($sql, 1);
				while ($row = $this->db->sql_fetchrow($result))
				{
					$next_topic_first_post = generate_text_for_edit($row['post_text'], $row['bbcode_uid'], true);
					$next_topic_first_post = htmlspecialchars(html_entity_decode($next_topic_first_post['text']));
					$next_topic_first_post = '[url=' . generate_board_url() . '/viewtopic.' . $this->php_ext . '?t=' . $data['topic_id'] . ']' . $this->user->lang['NTAXP_PREVIOUS_TOPIC'] . ': ' . $this->topic_title . '[/url]' . sprintf("\n") . $next_topic_first_post;

					//If old post has attachments
					$next_topic_first_post = $this->check_attachments($next_topic_first_post, $row['post_id']);

					$first_post_poster_id = $row['poster_id'];
					$first_post_username = $row['topic_first_poster_name'];
					$topic_id = $row['topic_id'];

					$this->new_topic_poster = array(
						'user_id'		=> $row['poster_id'],
						'username'		=> $row['topic_first_poster_name'],
						'user_colour'	=> $row['user_color'],
						'user_ip'		=> $row['poster_ip']
					);
				}
				$this->db->sql_freeresult($result);

				if (!$topic_id)
				{
					return;
				}
			}
			else
			{
				$next_topic_first_post   = '[url=' . generate_board_url() . '/viewtopic.' . $this->php_ext . '?t=' . $data['topic_id'] . ']' . $this->user->lang['NTAXP_PREVIOUS_TOPIC'] . ': ' . $this->topic_title . '[/url]' . sprintf("\n") . $data['message'];
				$first_post_poster_id = $data['poster_id'];
				$first_post_username = ($this->user->data['is_registered']) ? $this->user->data['username'] : $this->user->lang['GUEST'];
			}

			generate_text_for_storage($next_topic_first_post, $uid, $bitfield, $options, $allow_bbcode, $allow_urls, $allow_smilies);
			//$next_topic_first_post = str_replace('&lt;br /&gt;', '<br/>', $next_topic_first_post);

			$next_topic_data = array(
				'forum_id'		=> $data['forum_id'],
				'poster_id'		=> (int) $first_post_poster_id,
				'icon_id'		=> $data['icon_id'],
				'topic_title'	=> $this->next_topic_subject,
				'enable_bbcode'		=> $data['enable_bbcode'],
				'enable_smilies'	=> $data['enable_smilies'],
				'enable_urls'		=> $data['enable_urls'],
				'enable_sig'		=> $data['enable_sig'],
				'message'		=> $next_topic_first_post,
				'message_md5'	=> (string) md5($next_topic_first_post),

				'bbcode_bitfield'	=> $bitfield,
				'bbcode_uid'		=> $uid,

				'post_edit_locked'	=> $data['post_edit_locked'],
				'notify_set'		=> $data['notify_set'],
				'notify'			=> $data['notify'],
				'post_time' 		=> time(),
				'forum_name'		=> $data['forum_name'],
				'enable_indexing'	=> $data['enable_indexing'],
			);

			$redirect = submit_post('post', $this->next_topic_subject, $first_post_username, POST_NORMAL, $poll, $next_topic_data);

			//Answer in the new topic
			if ($this->config['ntaxp_copy_first_post'])
			{
				$next_topic_text = $data['message'];

				//If old post has attachments
				$next_topic_text = $this->check_attachments($next_topic_text, $data['post_id']);

				$uid = $bitfield = $options = '';
				generate_text_for_storage($next_topic_text, $uid, $bitfield, $options, $allow_bbcode, $allow_urls, $allow_smilies);
				//$next_topic_text = str_replace('&lt;br /&gt;', '<br/>', $next_topic_text);

				$redirect = str_replace('&amp;', '&', $redirect);
				parse_str($redirect, $topic_id);
				$topic_id = $topic_id['t'];

				if (!$topic_id)
				{
					return;
				}

				$next_topic_answer_data = array(
					'forum_id'		=> $data['forum_id'],
					'topic_id'		=> (int) $topic_id,
					'poster_id'		=> (int) $data['poster_id'],
					'icon_id'		=> $data['icon_id'],
					'topic_title'	=> $this->next_topic_subject,
					'enable_bbcode'		=> $data['enable_bbcode'],
					'enable_smilies'	=> $data['enable_smilies'],
					'enable_urls'		=> $data['enable_urls'],
					'enable_sig'		=> $data['enable_sig'],
					'message'		=> $next_topic_text,
					'message_md5'	=> (string) md5($next_topic_text),

					'bbcode_bitfield'	=> $bitfield,
					'bbcode_uid'		=> $uid,

					'post_edit_locked'	=> $data['post_edit_locked'],
					'notify_set'		=> $data['notify_set'],
					'notify'			=> $data['notify'],
					'post_time' 		=> time(),
					'forum_name'		=> $data['forum_name'],
					'enable_indexing'	=> $data['enable_indexing'],
				);

				$this->postcount = 1;
				$redirect = submit_post('reply', 'Re: ' . $this->next_topic_subject, (($this->user->data['is_registered']) ? $this->user->data['username'] : $this->user->lang['GUEST']), POST_NORMAL, $poll, $next_topic_answer_data);
			}

			//Edit old topic
			$this_topic_text = $data['message'] . sprintf("\n") . '[url=' . generate_board_url() . '/viewtopic.' . $this->php_ext . '?t=' . $next_topic_data['topic_id'] . ']' . $this->user->lang['NTAXP_NEXT_TOPIC'] . '[/url]';
			generate_text_for_storage($this_topic_text, $uid, $bitfield, $options, true, true, true);
			//$this_topic_text = str_replace('&lt;br /&gt;', '<br/>', $this_topic_text);

			$this_topic_data = array(
				'forum_id'				=> (int) $data['forum_id'],
				'poster_id'				=> (int) $data['poster_id'],
				'icon_id'				=> (int) $data['icon_id'],
				'post_approved'			=> (isset($data['post_approved'])) ? $data['post_approved'] : false,
				'enable_bbcode'			=> (bool) $data['enable_bbcode'],
				'enable_smilies'		=> (bool) $data['enable_smilies'],
				'enable_urls'			=> (bool) $data['enable_urls'],
				'enable_sig'			=> (bool) $data['enable_sig'],
				'topic_title'			=> $this->topic_title,
				'message_md5'			=> (string) md5($this_topic_text),
				'attachment_data'		=> $data['attachment_data'],
				'bbcode_bitfield'		=> $bitfield,
				'bbcode_uid'			=> $uid,
				'post_edit_locked'		=> (int) $data['post_edit_locked'],
				'message'				=> $this_topic_text,

				'topic_first_post_id'	=> (isset($data['topic_first_post_id'])) ? (int) $data['topic_first_post_id'] : 0,
				'topic_last_post_id'	=> (isset($data['topic_last_post_id'])) ? (int) $data['topic_last_post_id'] : 0,
				'topic_time_limit'		=> (int) $data['topic_time_limit'],
				'post_id'				=> (int) $data['post_id'],
				'topic_id'				=> (int) $data['topic_id'],
				'topic_posts_approved'	=> (isset($data['topic_posts_approved'])) ? (int) $data['topic_posts_approved'] : 0,
				'topic_posts_unapproved'	=> (isset($data['topic_posts_unapproved'])) ? (int) $data['topic_posts_unapproved'] : 0,
				'topic_posts_softdeleted'	=> (isset($data['topic_posts_softdeleted'])) ? (int) $data['topic_posts_softdeleted'] : 0,

				'enable_indexing'		=> (bool) $data['enable_indexing'],
				'post_time'				=> (isset($data['post_time'])) ? (int) $data['post_time'] : time(),
				'post_edit_reason'		=> $data['post_edit_reason'],
				'post_edit_user'		=> 0,
				'forum_name'			=> $data['forum_name'],
				'notify'				=> (isset($data['notify'])) ? $data['notify'] : false,
				'notify_set'			=> (isset($data['notify_set'])) ? $data['notify_set'] : false,
			);

			submit_post('edit', $event['subject'], (($this->user->data['is_registered']) ? $this->user->data['username'] : $this->user->lang['GUEST']), POST_NORMAL, $poll, $this_topic_data);

			//Adding to the log
			add_log('mod', $data['forum_id'], $data['topic_id'], 'LOG_TOPIC_ADDED', $this->next_topic_subject, ($this->user->data['is_registered']) ? $this->user->data['username'] : $this->user->lang['GUEST']);

			$event['url'] = $redirect;
		}
	}

	public function check_attachments($check_text, $post_id)
	{
		preg_match_all('/\[attachment=\d+\](.*)\[\/attachment\]/U', $check_text, $inline_attach);

		$sql = 'SELECT attach_id, real_filename, attach_comment, extension, mimetype, thumbnail FROM ' . $this->table_prefix . 'attachments' . ' WHERE post_msg_id = ' . (int) $post_id;
		$result = $this->db->sql_query($sql);

		while ($attach_row = $this->db->sql_fetchrow($result))
		{
			if (in_array($attach_row['real_filename'], $inline_attach[1]))
			{
				if (strpos($attach_row['mimetype'], 'image/') !== false)
				{
					$attach_string = '[img]' . generate_board_url();
					$attach_string .= '/download/file.php?id=' . (int) $attach_row['attach_id'];
					$attach_string .=	'[/img]';

					$check_text = preg_replace('/\[attachment=\d+\]' . preg_quote($attach_row['real_filename']) . '\[\/attachment\]/', $attach_string, $check_text);
				}
				else
				{
					$check_text = preg_replace('/\[attachment=\d+\]' . preg_quote($attach_row['real_filename']) . '\[\/attachment\]/', '[url=' . generate_board_url() . '/download/file.php?id=' . (int) $attach_row['attach_id'] . ']' . $attach_row['real_filename'] . '[/url]', $check_text);
				}
			}
			else
			{
				if (strpos($attach_row['mimetype'], 'image/') !== false)
				{
					$attach_string = '[img]' . generate_board_url();
					$attach_string .= '/download/file.php?id=' . (int) $attach_row['attach_id'];
					$attach_string .=	'[/img]';

					$check_text .= $attach_string;
				}
				else
				{
					$check_text .= sprintf("\n") . '[url=' . generate_board_url() . '/download/file.php?id=' . (int) $attach_row['attach_id'] . ']' . $attach_row['real_filename'] . '[/url]';
				}
			}
		}
		$this->db->sql_freeresult($result);

		return $check_text;
	}

	public function acp_settings($event)
	{
		$this->user->add_lang_ext('tatiana5/newtopicafterxposts', 'newtopicafterxposts');

		$display_vars = $event['display_vars'];
		array_pop($display_vars['vars']);
		$display_vars['vars']['ntaxp_posts'] = array('lang' => 'NTAXP_ACP_POSTS',	'validate' => 'int:0',	'type' => 'number:0:99999', 'explain' => true);
		$display_vars['vars']['ntaxp_copy_first_post'] = array('lang' => 'NTAXP_ACP_COPY_FIRST_POST',	'validate' => 'bool',	'type' => 'radio:yes_no', 'explain' => false);
		$display_vars['vars']['legend3'] = 'ACP_SUBMIT_CHANGES';
		$event['display_vars'] = $display_vars;
	}

	public function fix_topic_poster($event)
	{
		$post_mode = $event['post_mode'];

		if ($post_mode == 'post' && sizeof($this->new_topic_poster))
		{
			$sql_data = $event['sql_data'];

			$sql_data[$this->posts_table]['sql']['poster_id'] = $this->new_topic_poster['user_id'];
			$sql_data[$this->posts_table]['sql']['poster_ip'] = $this->new_topic_poster['user_ip'];
			$sql_data[$this->topics_table]['sql']['topic_poster'] = $this->new_topic_poster['user_id'];
			$sql_data[$this->topics_table]['sql']['topic_first_poster_name'] = $this->new_topic_poster['username'];
			$sql_data[$this->topics_table]['sql']['topic_first_poster_colour'] = ($this->new_topic_poster['user_colour']) ? $this->new_topic_poster['user_colour'] : '';

			$this->new_topic_poster = array();
			$event['sql_data'] = $sql_data;
		}
	}
}
