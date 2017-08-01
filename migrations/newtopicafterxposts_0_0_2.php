<?php
/**
*
* @package phpBB Extension - New topic after X posts
* @copyright (c) 2017 Татьяна5
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/
namespace tatiana5\newtopicafterxposts\migrations;

class newtopicafterxposts_0_0_2 extends \phpbb\db\migration\migration
{
	public function effectively_installed()
	{
		return isset($this->config['ntaxp_version']) && version_compare($this->config['ntaxp_version'], '0.0.2', '>=');
	}

	static public function depends_on()
	{
		return array('\tatiana5\newtopicafterxposts\migrations\newtopicafterxposts_0_0_1');
	}

	public function update_data()
	{
		return array(
			// Current version
			array('config.update', array('ntaxp_version', '0.0.2')),
		);
	}
}
