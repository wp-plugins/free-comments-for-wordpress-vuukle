<?php
/*
	Plugin Name: Free Comments for WordPress Vuukle
	Plugin URI:  http://www.vuukle.com/
	Description: Easily integrate Vuukle Commenting system to your WordPress content.
	Version:     1.1
	Author:      Ravi Mittal
	Author URI:  http://vuukle.com
*/


if (!class_exists('Vuukle'))
{
	class Vuukle
	{
		function __construct()
		{
			$this->PluginFile = __FILE__;
			$this->PluginPath = dirname($this->PluginFile) . DIRECTORY_SEPARATOR;
			$this->PluginName = 'Vuukle';
			$this->SettingsURL = 'options-general.php?page='.dirname(plugin_basename($this->PluginFile)).'/'.basename($this->PluginFile);
			$this->SettingsName = 'Vuukle';
			$this->Settings = get_option($this->SettingsName);

			$this->SettingsDefaults = array(
				'AppId' => '',
			);

			register_activation_hook($this->PluginFile, array(&$this, 'Activate'));

			add_filter('plugin_action_links', array(&$this, 'ActionLinks'), 10, 2);
			add_action('admin_menu', array(&$this, 'AdminMenu'));
			add_shortcode('vuukle', array(&$this, 'ShortCode'));
			//add_filter('the_content', array(&$this, 'TheContent'), 100);
			add_filter('get_comments_number', array(&$this, 'CommentsNumber'), 10, 2);
			add_filter('comments_template', array(&$this, 'CommentsTemplate'));
		}


		function Activate()
		{
			if (is_array($this->Settings))
			{
				$Settings = array_merge($this->SettingsDefaults, $this->Settings);
				$Settings = array_intersect_key($Settings, $this->SettingsDefaults);

				update_option($this->SettingsName, $Settings);
			}
			else
			{
				add_option($this->SettingsName, $this->SettingsDefaults);
			}

			$this->Settings = get_option($this->SettingsName);


			if (!$this->Settings['AppId'])
			{
				$Response = wp_remote_retrieve_body(wp_remote_post('http://vuukle.com/api.asmx/quickRegister', 
					array(
						'method' => 'POST',
						'timeout' => 5,
						'redirection' => 5,
						'httpversion' => '1.0',
						'blocking' => true,
						'headers' => array('Content-type' => 'application/json; charset=utf-8'),
						'body' => json_encode( array('url' => site_url(), 'email' => get_option('admin_email')) ),
						'cookies' => array(),
					)
				));

				if ($Response && $ResponseData = json_decode($Response, true))
				{
					if (isset($ResponseData['d']) && $ResponseDataApp = json_decode($ResponseData['d'], true))
					{
						if (isset($ResponseDataApp['biz_id']))
						{
							$Settings = get_option($this->SettingsName);

							$Settings['AppId'] = $ResponseDataApp['biz_id'];

							update_option($this->SettingsName, $Settings);

							$this->Settings = get_option($this->SettingsName);
						}
					}
				}
			}
		}


		function ActionLinks($Links, $File)
		{
			static $FilePlugin;

			if (!$FilePlugin)
			{
				$FilePlugin = plugin_basename($this->PluginFile);
			}
	
			if ($File == $FilePlugin)
			{
				$Link = "<a href='$this->SettingsURL'>Settings</a>";

				array_push($Links, $Link);
			}

			return $Links;
		}


		function AdminMenu()
		{
			add_submenu_page('options-general.php', 'Vuukle &rsaquo; Settings', 'Vuukle', 'manage_options', $this->PluginFile, array(&$this, 'Admin'));
		}


		function Admin()
		{
			if (!current_user_can('manage_options'))
			{
				wp_die(__('You do not have sufficient permissions to access this page.'));
			}

			if (isset($_POST['action']) && $_POST['action'] == 'VuukleSaveSettings')
			{
				if (wp_verify_nonce($_POST['nonce'], $this->SettingsName))
				{
					foreach ($_POST as $Key => $Value)
					{
						if (array_key_exists($Key, $this->SettingsDefaults))
						{
							$Value = trim($Value);

							$Settings[$Key] = $Value;
						}
					}

					if (update_option($this->SettingsName, $Settings))
					{
						$this->Settings = get_option($this->SettingsName);
						print '<div class="updated"><p><strong>Settings saved.</strong></p></div>';
					}
				}
				else
				{
					print '<div class="error"><p><strong>Security check failed! Settings not saved.</strong></p></div>';
				}
			}

		?>

			<div class="wrap">

				<h2>Vuukle Settings</h2>

				<p>Vuukle Commenting is automatically displayed in place of WordPress default comments. You can also insert Vuukle Commenting system to any other part of your website by using ShortCode <code>[vuukle]</code>.</p>

				<form method="post" action="">

					<table class="form-table">

						<tr valign="top">
							<th scope="row">
								App Id
								<br /> <a target="_blank" href="http://www.vuukle.com/registration.aspx">Get App Id</a>
							</th>
							<td>
								<input name="AppId" type="text" value="<?php print $this->Settings['AppId']; ?>" class="regular-text" />
							</td>
						</tr>

					</table>

					<input name="nonce" type="hidden" value="<?php print wp_create_nonce($this->SettingsName); ?>" />
					<input name="action" type="hidden" value="VuukleSaveSettings" />

					<div class="submit"><input name="" type="submit" value="Save Settings" class="button-primary" /></div>

				</form>

			</div>

		<?php

		}


		function ShortCode($Attributes, $Content = null, $Code = '')
		{
			if (!$this->Settings['AppId'])
			{
				return '<p>Vuukle: Please add App ID in plugin settings page.</p>';
			}

			return '<div id="vuukle_div"></div><script src="http://www.vuukle.com/js/vuukle.js" type="text/javascript"></script><script type="text/javascript">create_vuukle_platform(\''.$this->Settings['AppId'].'\');</script>';
		}


		function TheContent($Content)
		{
			global $post;

			if (is_single() && $this->Settings['AppId'] && comments_open($post->ID) && stripos($Content, '[vuukle]') === false)
			{
				$Content .= '<div id="vuukle_div"></div><script src="http://www.vuukle.com/js/vuukle.js" type="text/javascript"></script><script type="text/javascript">create_vuukle_platform(\''.$this->Settings['AppId'].'\');</script>';
			}

			return $Content;
		}


		function CommentsNumber($Count, $PostId)
		{
			$Permalink = get_permalink($PostId);

			if ($this->Settings['AppId'])
			{
				$Response = wp_remote_retrieve_body(wp_remote_post('http://vuukle.com/api.asmx/getcommentcount', 
					array(
						'method' => 'POST',
						'timeout' => 5,
						'redirection' => 5,
						'httpversion' => '1.0',
						'blocking' => true,
						'headers' => array('Content-type' => 'application/json; charset=utf-8'),
						'body' => json_encode( array('uri' => $Permalink, 'id' => $this->Settings['AppId']) ),
						'cookies' => array(),
					)
				));

				if ($Response && $ResponseData = json_decode($Response, true))
				{
					if (isset($ResponseData['d']) && $ResponseDataApp = json_decode($ResponseData['d'], true))
					{
						if (isset($ResponseDataApp['count']))
						{
							$Count = $ResponseDataApp['count'];
						}
					}

				}
			}

			return $Count;
		}


		function CommentsTemplate($File)
		{
			$CommentsFile = $this->PluginPath.'comments.php';

			if ($this->Settings['AppId'] && file_exists($CommentsFile))
			{
				$File = $CommentsFile;
			}

			return $File;
		}

	}

	$Vuukle = new Vuukle();
}


?>