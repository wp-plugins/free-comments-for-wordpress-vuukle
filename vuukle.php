<?php
/*
	Plugin Name: Vuukle Social Enagement
	Plugin URI:  http://vuukle.com
	Description: Easily integrate Vuukle Commenting system to your WordPress blog.
	Version:     1.5
	Author:      Vuukle
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

			register_activation_hook($this->PluginFile, array($this, 'Activate'));

			add_filter('plugin_action_links_'.plugin_basename($this->PluginFile), array($this, 'ActionLinks'));
			add_action('admin_menu', array($this, 'VuukleModeration'));
			add_action('admin_menu', array($this, 'AdminMenu'));
			add_shortcode('vuukle', array($this, 'ShortCode'));
			//add_filter('the_content', array($this, 'TheContent'), 100);
			add_filter('get_comments_number', array($this, 'CommentsNumber'), 10, 2);
			add_filter('comments_template', array($this, 'CommentsTemplate'));
			add_action('wp_ajax_vkimport', array($this, 'VkImport'));
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


		function ActionLinks($Links)
		{
			$Link = "<a href='$this->SettingsURL'>Settings</a>";

			array_push($Links, $Link);

			return $Links;
		}

		function VuukleModeration()
		{
			add_menu_page( 'Vuukle Moderation', 'Vuukle', 'manage_options', 'free-comments-for-wordpress-vuukle/moderation.php', '', plugins_url( 'free-comments-for-wordpress-vuukle/icon.png' ), '' );
		
		}

		function AdminMenu()
		{
			add_submenu_page('options-general.php', 'Vuukle &rsaquo; Settings', 'Vuukle', 'manage_options', $this->PluginFile, array($this, 'Admin'));
		}


		function Admin()
		{
			if (!current_user_can('manage_options'))
			{
				wp_die(__('You do not have sufficient permissions to access this page.'));
			}

			if (isset($_POST['action']) && !wp_verify_nonce($_POST['nonce'], $this->SettingsName))
			{
				wp_die(__('Security check failed! Settings not saved.', $this->TextDomain));
			}

			if (isset($_POST['action']) && $_POST['action'] == 'VuukleSaveSettings')
			{
				foreach ($_POST as $Key => $Value)
				{
					if (array_key_exists($Key, $this->SettingsDefaults))
					{
						$Value = trim($Value);

						$this->Settings[$Key] = $Value;
					}
				}

				if (update_option($this->SettingsName, $this->Settings))
				{
					print '<div class="updated"><p><strong>Settings saved.</strong></p></div>';
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

				<!--## Import View ##-->
				<h3>Exporting your Wordpress Comments </h3>
				<span>Just Hit the Export button below and we will transfer your existing comments to your new Vuukle Comment System</span>
				<br/>
				<h3>Exporting your Disqus Comments </h3>
				<ul>
					<li>Re-activate your disqus plugin(in case you have disabled it) </li>
					<li>Go to the &quot;Advanced Options&quot; tab</li>
					<li>Under the &quot;Import / Export&quot; section you will see an option to <b>Sync Comments</b> click this 
						button to sync your comments with Wordpress.</li>
					<li>Now click the below <b>Export</b> button to transfer all your Disqus comments to your new Vuukle Comment System</li>
				</ul>
		
				<script src=<?php echo plugins_url('export.js', __FILE__); ?>></script>
				<div id="vk_import" class="submit"><a class="button-primary" href="javascript:vk_export_start('<?php echo admin_url( 'admin-ajax.php'); ?>');"> Export </a></div>
				<?php 
				// automatic sync code for dsq to sync
				if (function_exists(dsq_sync_comments)){
						echo '<script> 
						var vk_import_btn = document.getElementById(\'vk_import\');
						vk_import_btn.style.display = "none";
						vk_load(\'', site_url(),'/wp-admin/index.php?cf_action=import_comments&last_comment_id=0&wipe=0\', 
								function (r){
									
									vk_import_btn.style.display = "block";
									//console.log(r);
								});	
						</script> ';
				
				}
									?>
					

				<!--##ENDOF Import View ##-->
			</div>

		<?php

		}


		function ShortCode($Attributes, $Content = null, $Code = '')
		{
			if (!$this->Settings['AppId'])
			{
				return '<p>Vuukle: Please add App ID in plugin settings page.</p>';
			}

			global $post;

			return '<div id="vuukle_div"></div><script src="http://vuukle.com/js/vuukle.js" type="text/javascript"></script><script type="text/javascript">create_vuukle_platform(\''.$this->Settings['AppId'].'\', \''.$post->ID.'\', \'0\', \''.strip_tags(get_the_category_list(',', '', $post->ID)).'\', \''.the_title_attribute(array('echo' => false)).'\');</script>';
		}


		function TheContent($Content)
		{
			global $post;

			if (is_single() && $this->Settings['AppId'] && comments_open($post->ID) && stripos($Content, '[vuukle]') === false)
			{
				$Content .= '<div id="vuukle_div"></div><script src="http://vuukle.com/js/vuukle.js" type="text/javascript"></script><script type="text/javascript">create_vuukle_platform(\''.$this->Settings['AppId'].'\', \''.$post->ID.'\', \'0\', \''.strip_tags(get_the_category_list(',', '', $post->ID)).'\', \''.the_title_attribute(array('echo' => false)).'\');</script>';
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


		function VkImport()
		{
			/*
				 1: vk_request count_comments : send total number of in post & comments join 
				 2: vk_request send_comments=n : send 


			*/
			global $wpdb;
			$json_string = '';
			$result =  array(); //r2d2
			// count comments 
			
			if(isset($_REQUEST['count_comments'])) {
				//select sum(comment_count) as `total` from wp_posts where comment_count > 0 

				$a = $wpdb->get_results("select count(id) as `total_post`, sum(comment_count) as `total_comments` from $wpdb->posts where comment_count > 0");	
				$result = $a[0];
				echo 'FFFFFFFFFFFF!<br>', get_option('disqus_api_key');
			}
			

			// retrive comments 
			if(isset($_REQUEST['send_comments'])) {
				$posts = $wpdb->get_results( "select id, guid as `url`, comment_count  from $wpdb->posts where comment_count > 0 " );	

				
				$no_posts = sizeof($posts);
				for($i= 0; $i< $no_posts; $i+=1) {
					
					$comments = $wpdb->get_results( "select comment_author as 'name', 
							comment_author_email as 'email', 
							'' as 'facebookid',   
							comment_date as 'timestamp',
							comment_content as 'comment'
							from $wpdb->comments where comment_post_id = '".$posts[$i]->id."'" );	
					
					/*
					$no_comments = sizeof($comments);

					for($j=0; $j < $no_comments; $j+=1) {

						//$comments[$j]->comment =  reg_replace("@'@",'`',$comments[$j]->comment); //  	
						$comments[$j]->comment =  addslashes($comments[$j]->comment); //  	
						//echo addslashes($comments[$j]->comment) ,'<br />';
					}

					//*/

					$post_comments  = array(
						'url' => $posts[$i]->url, 
						'data'=> $comments
					);
					array_push($result, $post_comments);
					


				}
				//echo '<pre>';
				//print_r($result);
				
				
			} 

			$this->VK_import_flash($result);
			exit();
		}
	
		function VK_import_flash($result){
				$json_string = json_encode($result);

				if(isset($_REQUEST['callback'])){
					echo $_REQUEST['callback'], '(', $json_string, ');';
				}else{
					echo  $json_string;
				}
				
		}

	}

	$Vuukle = new Vuukle();
}


?>
