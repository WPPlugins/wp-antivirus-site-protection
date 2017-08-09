<?php
/*
Plugin Name: WP Antivirus Site Protection (by SiteGuarding.com)
Plugin URI: http://www.siteguarding.com/en/website-extensions
Description: Adds more security for your WordPress website. Server-side scanning. Performs deep website scans of all the files. Virus and Malware detection.
Version: 7.5
Author: SiteGuarding.com (SafetyBis Ltd.)
Author URI: http://www.siteguarding.com
License: GPLv2
TextDomain: plgavp
*/
define( 'SITEGUARDING_SERVER', 'http://www.siteguarding.com/ext/antivirus/index.php');
define( 'SITEGUARDING_SERVER_IP1', '185.72.157.169');
define( 'SITEGUARDING_SERVER_IP2', '185.72.157.170');

define( 'SGAVP_UPDATE', true);


if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') define('DIRSEP', '\\');
else define('DIRSEP', '/');

//error_reporting(E_ERROR | E_WARNING);
//error_reporting(E_ERROR);

// Cron check
if( !is_admin() ) 
{
    function plgavp_login_page() 
    {
    	$params = plgwpavp_GetExtraParams();
        if ($params['protect_login_page'] == 1 && $params['captcha_site_key'] != '' && $params['captcha_secret_key'] != '' && file_exists((dirname(__FILE__).'/sgantivirus.login.keys.php')) ) 
        {
            SGAntiVirus::CheckWPLogin_file();
        }
    }
    add_action('login_head', 'plgavp_login_page');
    
    

	if ( isset($_GET['task']) && $_GET['task'] == 'upgrade' )
	{
		error_reporting(0);
		
		$access_key = trim($_GET['access_key']);
	
		$params = plgwpavp_GetExtraParams();
	
		if ($params['access_key'] == $access_key)
		{
				include_once(dirname(__FILE__).DIRSEP.'sgantivirus.class.php');
				
				if (!class_exists('SGAntiVirus_module'))
				{
					// Error module is not loaded
					exit;
				}
				
                $version = trim($_GET['version']);
                
                $update_info = SGAntiVirus::GetUpdateInfo(get_site_url(), $access_key, $version);
                
                echo '<pre>'.print_r($update_info, true).'</pre>';
                
				// Download zip version from server
				$result = SGAntiVirus::DownloadFromWordpress_Link($update_info['update_url']);
				if ($result === false) die('Can\'t download new version from wordpress.org');
				
				// Update
				/*if (function_exists('system'))
				{
					$extract_path = ABSPATH.'plugins'.DIRSEP.'1'.DIRSEP;
					$cmd = "unzip ".dirname(__FILE__).DIRSEP.'tmp'.DIRSEP.'update.zip'." -d ".$extract_path;
					echo $cmd;
					system($cmd);
				}
				else */if (class_exists('ZipArchive'))
			    {
			    	$zip = new ZipArchive;
					if ($zip->open(dirname(__FILE__).DIRSEP.'tmp'.DIRSEP.'update.zip') === TRUE) {
						$extract_path = ABSPATH.'wp-content'.DIRSEP.'plugins'.DIRSEP.'wp-antivirus-site-protection'.DIRSEP;
						
						echo "Extract path: ".$extract_path."<br>";
						
					    $unzip_status = $zip->extractTo($extract_path);
					    
					    for($i = 0; $i < $zip->numFiles; $i++) 
						{
							$filename = $zip->getNameIndex($i);
							echo $filename."<br>";
					        $zip->extractTo($extract_path, array($zip->getNameIndex($i)));
					    }
    
					    $zip->close();
					    if ($unzip_status === false) echo 'Unzip failed'."<br>";
                        
                        echo "<br>".'Result List:'."<br>";
                        
                        foreach (glob($extract_path."*.php") as $filename) 
                        {
                            echo "$filename [" . filesize($filename) . " bytes]". "<br>";
                        }
                        
                        echo "<br>";
                        
                        unlink(dirname(__FILE__).DIRSEP.'tmp'.DIRSEP.'update.zip');
                        
					    echo 'Update finished'."<br>";
					} else {
					    echo 'Update failed'."<br>";
					}
		    	}
		}
		else die('access_key or IP is not correct');
		
		exit;
	}
	
	
	if ( isset($_GET['task']) && $_GET['task'] == 'cron' )
	{
		error_reporting(0);
		
		$access_key = trim($_GET['access_key']);
	
		$params = plgwpavp_GetExtraParams();
	
		if ($params['access_key'] == $access_key)
		{
				include_once(dirname(__FILE__).DIRSEP.'sgantivirus.class.php');
				
				if (!class_exists('SGAntiVirus_module'))
				{
					// Error module is not loaded
					exit;
				}
				
				$license_info = SGAntiVirus::GetLicenseInfo(get_site_url(), $access_key);
				$session_report_key = md5($domain.'-'.rand(1,1000).'-'.time());
				
				SGAntiVirus_module::MembershipFile($license_info['membership'], $license_info['scans'], $params['show_protectedby']);
				
				// Prepare scan
				$_POST['scan_path'] = ABSPATH;
				$_POST['access_key'] = $access_key;
				$_POST['do_evristic'] = $params['do_evristic'];
				$_POST['domain'] = get_site_url();
				$_POST['email'] = get_option( 'admin_email' );
				$_POST['session_report_key'] = $session_report_key;
				$_POST['membership'] = $license_info['membership'];
				
				// Start scan
				SGAntiVirus_module::scan(false, false);
		}
		
		exit;
	}
    
    
	if ( isset($_GET['task']) && $_GET['task'] == 'standalone' )
	{
		error_reporting(0);
		
		$access_key = trim($_GET['access_key']);
	
		$params = plgwpavp_GetExtraParams();
	
		if ($params['access_key'] == $access_key)
		{
        		if (!defined('ABSPATH') || strlen(ABSPATH) < 8) 
        		{
        			$site_path = dirname(__FILE__);
        			$site_path = str_replace(DIRSEP.'wp-content'.DIRSEP.'plugins'.DIRSEP.'wp-antivirus-site-protection', DIRSEP, $site_path);
        		}
                else $site_path = ABSPATH;
                
                if (!file_exists($site_path.DIRSEP.'webanalyze'))
                {
                    if ( !mkdir($site_path.DIRSEP.'webanalyze') ) die( "Can't create folder ".$site_path.DIRSEP.'webanalyze' );
                }
                
                // Create antivirus.php
                $link = 'https://www.siteguarding.com/_get_file.php?file=antivirus&time='.time();
                $file = $site_path.DIRSEP.'webanalyze'.DIRSEP.'antivirus.php';
                $file_content = SGAntiVirus::DownloadRemoteFile($link, $file);
                
                // Create antivirus_config.php
                $file_content = '<?php'."\n".
                	'define("ACCESS_KEY", "'.$access_key.'");'."\n".
                    '?>'."\n";
                $file = $site_path.DIRSEP.'webanalyze'.DIRSEP.'antivirus_config.php';
                SGAntiVirus::CreateFile($file, $file_content);
                
				die("OK");
		}
		
		exit;
	}
    
	
	// Remote request malware files
	if ( isset($_GET['task']) && $_GET['task'] == 'get_malware_files' )
	{
		error_reporting(0);
		
		$access_key = trim($_GET['access_key']);
	
		$params = plgwpavp_GetExtraParams();
	
		if ($params['access_key'] == $access_key)
		{
				include_once(dirname(__FILE__).DIRSEP.'sgantivirus.class.php');
				
				if (!class_exists('SGAntiVirus_module'))
				{
					// Error module is not loaded
					echo 'Error module is not loaded';
					exit;
				}
				
				
				$license_info = SGAntiVirus::GetLicenseInfo(get_site_url(), $params['access_key']);
	
				if ($license_info === false) { echo 'Wrong access_key'; exit; }
				
				
				if (intval($_GET['showcontent']) == 1)
				{
					SGAntiVirus::ShowFilesForAnalyze($license_info['last_scan_files']);
					exit;
				}
				

				$a = SGAntiVirus::SendFilesForAnalyze( $license_info['last_scan_files'], $license_info['email'] );
				if ($a === true)
				{
					$tmp_txt = 'Files sent for analyze. You will get report by email '.$license_info['email'].' Files:'.print_r( $license_info['last_scan_files'],true);
					
					$result_txt = array(
						'status' => 'OK',
						'description' => $tmp_txt
					);
					SGAntiVirus_module::DebugLog($tmp_txt);
				}
				else {
					$tmp_txt = 'Operation is failed. Nothing sent for analyze. Files:'.print_r( $license_info['last_scan_files'],true);
					
					$result_txt = array(
						'status' => 'ERROR',
						'description' => $tmp_txt
					);
					SGAntiVirus_module::DebugLog($tmp_txt);
				}
				
				echo json_encode($result_txt);
		}
		
		exit;
	}
	
	
	// Remote request malware files
	if ( isset($_GET['task']) && $_GET['task'] == 'remove_malware_files' )
	{
		error_reporting(0);
		
		$access_key = trim($_GET['access_key']);
	
		$params = plgwpavp_GetExtraParams();
	
		if ($params['access_key'] == $access_key && ($_SERVER["REMOTE_ADDR"] == SITEGUARDING_SERVER_IP1 || $_SERVER["REMOTE_ADDR"] == SITEGUARDING_SERVER_IP2))
		{
				include_once(dirname(__FILE__).DIRSEP.'sgantivirus.class.php');
				
				if (!class_exists('SGAntiVirus_module'))
				{
					// Error module is not loaded
					exit;
				}
				
				$license_info = SGAntiVirus::GetLicenseInfo(get_site_url(), $params['access_key']);
	
				if ($license_info === false) { exit; }
				
				
				$a = SGAntiVirus::QuarantineFiles($license_info['last_scan_files']['main']);
				if ($a === true)
				{
					SGAntiVirus_module::DebugLog('Malware moved to quarantine and deleted from the server. Files:'.print_r( $license_info['last_scan_files'],true));
				}
				else {
					SGAntiVirus_module::DebugLog('Operation is failed. Some files are not moved to quarantine or not deleted. Files:'.print_r( $license_info['last_scan_files'],true) );
				}
				
				$a = SGAntiVirus::QuarantineFiles($license_info['last_scan_files']['heuristic']);
				if ($a === true)
				{
					SGAntiVirus_module::DebugLog('Malware moved to quarantine and deleted from the server. Files:'.print_r( $license_info['last_scan_files'],true));
				}
				else {
					SGAntiVirus_module::DebugLog('Operation is failed. Some files are not moved to quarantine or not deleted. Files:'.print_r( $license_info['last_scan_files'],true) );
				}
				
		}
		else die('access_key or IP is not correct');
		
		exit;
	}
	
	
	
	if ( isset($_GET['task']) && $_GET['task'] == 'status' )
	{
		error_reporting(0);
		
		include_once(dirname(__FILE__).DIRSEP.'sgantivirus.class.php');
		
		$access_key = trim($_GET['access_key']);
	
		$params = plgwpavp_GetExtraParams();
	
		if ($params['access_key'] == $access_key)
		{
			$a = array(
				'status' => 'ok',
				'answer' => md5($_GET['answer']),
				'version' => SGAntiVirus_module::$antivirus_version
			);
			
			echo json_encode($a);
		}
		
		exit;
	}
    
    
    
	if ( isset($_GET['task']) && $_GET['task'] == 'settings' )
	{
		error_reporting(0);
		
		include_once(dirname(__FILE__).DIRSEP.'sgantivirus.class.php');
		
		$access_key = trim($_GET['access_key']);
	
		$params = plgwpavp_GetExtraParams();
	
		if ($params['access_key'] == $access_key)
		{
			$settings_name = trim($_GET['settings_name']);
			$settings_value = trim($_GET['settings_value']);
            
            $settings = SGAntiVirus_module::UpdateSettungsValue($settings_name, $settings_value);
            
            echo print_r($settings, true);
		}
		
		exit;
	}
	
	
	if ( isset($_GET['task']) && $_GET['task'] == 'view_file' )
	{
		error_reporting(0);
		
		include_once(dirname(__FILE__).DIRSEP.'sgantivirus.class.php');
		
		$access_key = trim($_GET['access_key']);
	
		$params = plgwpavp_GetExtraParams();
	
		if ($params['access_key'] == $access_key && ($_SERVER["REMOTE_ADDR"] == SITEGUARDING_SERVER_IP1 || $_SERVER["REMOTE_ADDR"] == SITEGUARDING_SERVER_IP2))
		{
			$filename = $_GET['file'];
			
			switch ($filename)
			{
				case 'debug':
					$filename = dirname(__FILE__).DIRSEP.'tmp'.DIRSEP.'debug.log';
					break;
					
				case 'filelist':
					$filename = dirname(__FILE__).DIRSEP.'tmp'.DIRSEP.'filelist.txt';
					break;
					
				default:
					$filename = ABSPATH.$filename;
			}
			
			echo "\n\n";
			
			if (file_exists($filename)) echo 'File exists: '.$filename."\n";
			else {echo 'File is absent: '.$filename."\n\n"; exit;}
			
			echo 'File size: '.filesize($filename)."\n";
			echo 'File MD5: '.strtoupper(md5_file($filename))."\n\n";
			
			$handle = fopen($filename, "r");
			$contents = fread($handle, filesize($filename));
			fclose($handle);
			echo '----- File Content [start] -----'."\n";
			echo $contents;
			echo '----- File Content [end] -----'."\n";
		}
		else die('access_key or IP is not correct');
		
		exit;
	}
	
	
	
	/*
	function plgavp_login_head_add_field()
	{
		$params = plgwpavp_GetExtraParams();

		if ( (isset($params['show_protectedby']) && $params['show_protectedby'] == 1) || $params['membership'] == 'free')
		{
		?>
			<div style="font-size:11px; padding:3px 0;position: fixed;bottom:0;z-index:10;width:100%;text-align:center;background-color:#F1F1F1">Protected with <a href="https://www.siteguarding.com/en/website-antivirus" target="_blank">antivirus</a> developed by <a href="https://www.siteguarding.com" target="_blank" title="SiteGuarding.com - Website Security. Website Antivirus Protection. Malware Removal services. Professional security services against hacker activity.">SiteGuarding.com</a></div>
		<?php
		}
		
	}
	add_action( 'login_head', 'plgavp_login_head_add_field' );
	*/

	// Show Protected by
	function plgavp_footer_protectedby() 
	{
        if (strlen($_SERVER['REQUEST_URI']) < 5)
        {
    		if ( file_exists( dirname(__FILE__).DIRSEP.'tmp'.DIRSEP.'membership.log'))
    		{
    		      $links = array(
                    'https://www.siteguarding.com/en/',
                    'https://www.siteguarding.com/en/website-antivirus',
                    'https://www.siteguarding.com/en/protect-your-website',
                    'https://www.siteguarding.com/en/services/malware-removal-service'
                  );
                  $link = $links[ mt_rand(0, count($links)-1) ];
    			?>
    				<div style="font-size:10px; padding:0 2px;position: fixed;bottom:0;right:0;z-index:1000;text-align:center;background-color:#F1F1F1;color:#222;opacity:0.8;">Protected with <a style="color:#4B9307" href="<?php echo $link; ?>" target="_blank" title="Website Security services. Website Malware removal. Website Antivirus protection.">SiteGuarding.com Antivirus</a></div>
    			<?php
    		}
        }	
	}
	add_action('wp_footer', 'plgavp_footer_protectedby', 100);

}




if( is_admin() ) {
	
	error_reporting(0);
	
    
    add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'plgwpavp_add_action_link', 10, 2 );
    function plgwpavp_add_action_link( $links, $file )
    {
  		$faq_link = '<a target="_blank" href="https://www.siteguarding.com/en/protect-your-website">Get Premium</a>';
		array_unshift( $links, $faq_link );
        
  		$faq_link = '<a target="_blank" href="https://www.siteguarding.com/en/contacts">Help</a>';
		array_unshift( $links, $faq_link );
        
  		$faq_link = '<a href="admin.php?page=plgavp_Antivirus">Run Antivirus</a>';
		array_unshift( $links, $faq_link );

		return $links;
    } 
    
    
	function plgwpavp_activation()
	{
		global $wpdb;
		$table_name = $wpdb->prefix . 'plgwpavp_config';
		if( $wpdb->get_var( 'SHOW TABLES LIKE "' . $table_name .'"' ) != $table_name ) {
			$sql = 'CREATE TABLE IF NOT EXISTS '. $table_name . ' (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `var_name` char(255) CHARACTER SET utf8 NOT NULL,
                `var_value` LONGTEXT CHARACTER SET utf8 NOT NULL,
                PRIMARY KEY (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;';

			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql ); // Creation of the new TABLE
            
            // Notify user
   			include_once(dirname(__FILE__).'/sgantivirus.class.php');
            $message = 'Dear Customer!'."<br><br>";
			$message .= 'Thank you for installation of our security plugin. We will do the best to keep your website safe and secured.'."<br><br>";
			$message .= 'One more step to secure your website. Please login to Dashboard of your WordPress website. Find in menu "Antivirus", follow the instructions.'."<br><br>";
			$message .= 'Please visit <a href="https://www.siteguarding.com/en/website-extensions">SiteGuarding.com Extentions<a> and learn more about our security solutions.'."<br><br>";
			$subject = 'Antivirus Installation';
			$email = get_option( 'admin_email' );
			
			SGAntiVirus_module::SendEmail($email, $message, $subject);
		}
        
        if (!SGAntiVirus::CheckStandAloneVersion())
        {
            SGAntiVirus::InstalltandAloneVersion();
        }
	}
	register_activation_hook( __FILE__, 'plgwpavp_activation' );
    
    
	function plgwpavp_uninstall()
	{
		
		global $wpdb;
		$table_name = $wpdb->prefix . 'plgwpavp_config';
		$wpdb->query( 'DROP TABLE ' . $table_name );
        
        // Send email to admin
		include_once(dirname(__FILE__).'/sgantivirus.class.php');
        $message = 'Dear owner of '.get_option( 'blogname' ).'!'."<br><br>";

        $msg = "We have detected that WordPress Antivirus website protection is uninstalled. Your website is not protected anymore.";
		$message .= '<style>.msg_alert{color:#D8000C;}</style><p>'.$msg.'</p>';
		$subject = 'Security alert !!! WordPress SiteGuarding.com antivirus is uninstalled on "'.get_option( 'blogname' ).'"';
		$email = $params['email_for_notifications'];
        if ($email == '') $email = get_option( 'admin_email' );
		
		SGAntiVirus_module::SendEmail($email, $message, $subject);
        
        SGAntiVirus::PatchWPLogin_file(false);
		
	}
	register_uninstall_hook( __FILE__, 'plgwpavp_uninstall' );
	
	
    
	add_action( 'admin_init', 'plgavp_admin_init' );
	function plgavp_admin_init()
	{
		wp_register_style( 'plgavp_LoadStyle', plugins_url('css/antivirus.css', __FILE__) );	
		wp_register_style( 'plgavp_LoadStyle_modal', plugins_url('css/semantic.min.css', __FILE__) );
        wp_register_script('plgavp_LoadJS_modal', plugins_url('js/semantic.min.js', __FILE__) , array (), false, false);	
	}
    
    
    add_action( 'admin_head', 'plgavp_admin_head_function' );
    function plgavp_admin_head_function() 
    {
        $current_user = wp_get_current_user();
        
        if (!in_array('administrator', $current_user->roles)) return;
        
        session_start();
        
        if (isset($_SESSION['session_plgavp_admin_check_last_logged']) && $_SESSION['session_plgavp_admin_check_last_logged'] != '')
        {
            if (isset($_SESSION['session_plgavp_admin_check_last_logged_time']) && $_SESSION['session_plgavp_admin_check_last_logged_time'] == 777) $_SESSION['session_plgsgabpn_admin_check_last_logged_time'] = time() + 30;

            if (isset($_SESSION['session_plgavp_admin_check_last_logged_time']) && $_SESSION['session_plgavp_admin_check_last_logged_time'] > time())
            {
                $time_left = $_SESSION['session_plgavp_admin_check_last_logged_time'] - time();
                echo str_replace("{DISAPPEAR_TIME}", $time_left, $_SESSION['session_plgavp_admin_check_last_logged']);
            }
            else {
                unset($_SESSION['session_plgavp_admin_check_last_logged']);
                unset($_SESSION['session_plgavp_admin_check_last_logged_time']);
            }
        }
        
        if (isset($_SESSION['session_plgavp_admin_head_function'])) $params = $_SESSION['session_plgavp_admin_head_function'];
        else $params = plgwpavp_GetExtraParams(array('check_settings_reminder'));

        $_SESSION['session_plgavp_admin_head_function'] = $params;
        
        if (!isset($params['check_settings_reminder']))
        {
            $data = array( 'check_settings_reminder' => time() - 10 );
            plgwpavp_SetExtraParams($data);
            return;
        }
        if ($params['check_settings_reminder'] < time()) return;
        
        $left = intval($params['check_settings_reminder']) - time();
        $left = SGAntiVirus::toDateInterval($left)->format('%a days %h hours');
        
    	echo '<p style="text-align:center; padding:2px 0; margin:0 0 2px 0; color: #D8000C;background-color: #FFBABA; font-weight:bold">We have detected important security changes in the settings. If you did not make them, <a href="/wp-admin/admin.php?page=plgavp_Antivirus_settings_page">click here</a> (The alert will be removed in '.$left.')</p>';
    }
    
    
    
    add_action('admin_footer', 'plgavp_admin_footer_function');
    function plgavp_admin_footer_function() 
    {
        if (isset($_POST['show_redirect']) && intval($_POST['show_redirect']) == 1) 
        {
            wp_enqueue_style( 'plgavp_LoadStyle' );
            wp_enqueue_style( 'plgavp_LoadStyle_modal' );
            wp_enqueue_script( 'plgavp_LoadJS_modal' );
            ?>
            <script>
            jQuery(document).ready(function(){
                jQuery('#ui_redirect').modal('show');
            });
            </script>
            <div class="ui small basic test modal" id="ui_redirect">
              <div class="content">
                <p style="text-align: center;font-size: 150%;">Please wait. We will redirect you to the right page...</p>
              </div>
            </div>
            <?php
            return;
        }
        if (isset($_POST['confirm_decision'])) return;
        
        $current_user = wp_get_current_user();
        
        if (!in_array('administrator', $current_user->roles)) return;
        
        $show_alert = false;
        
        session_start();
        
        if (isset($_SESSION['session_plgavp_admin_footer_function'])) $params = $_SESSION['session_plgavp_admin_footer_function'];
        else $params = plgwpavp_GetExtraParams(array('google_files', 'wp_admins', 'last_logged', 'check_google_files', 'check_wp_admins', 'check_last_logged', 'send_notifications', 'email_for_notifications'));

        $_SESSION['session_plgavp_admin_footer_function'] = $params;
        
        
        
        
        // Check for login sessions
    if (!isset($params['check_last_logged']) || $params['check_last_logged'] == 1 )
    {
        $ip = $_SERVER['REMOTE_ADDR'];
        $params['last_logged'] = (array)json_decode($params['last_logged'], true);
        if ( /*(!isset($params['last_logged']['sessions'])) ||*/ !isset($params['last_logged']['user_data'][$current_user->ID]) )
        {

            $params['last_logged']['sessions'][$ip] = array(
                'time' => time(),
                'user' => $current_user->user_login,
                'country' => SGAntiVirus::RecognizeCountryCode($ip)
            );

            $params['last_logged']['sessions'] = SGAntiVirus::OrganizeSessionData($params['last_logged']['sessions'], 3);
            
            $params['last_logged']['user_data'][$current_user->ID] = SGAntiVirus::CalculateSessionMD5($params['last_logged']['sessions']);

            session_start();
            
            plgwpavp_SetExtraParams( array('last_logged' => json_encode($params['last_logged'])) );

            $_SESSION['plgavp_last_logged'] = 1;
            
            if (count($params['last_logged']['sessions']) >= 2) $show_alert = true; // Show alert

        }
        else {
            $last_session_md5 = SGAntiVirus::CalculateSessionMD5($params['last_logged']['sessions']);

            if ($params['last_logged']['user_data'][$current_user->ID] != $last_session_md5 )
            {
                // Show alert
                $show_alert = true;
            }
            
            $params['last_logged']['sessions'][$ip] = array(
                'time' => time(),
                'user' => $current_user->user_login,
                'country' => SGAntiVirus::RecognizeCountryCode($ip)
            );
            
            $params['last_logged']['sessions'] = SGAntiVirus::OrganizeSessionData($params['last_logged']['sessions'], 3);
            
            $params['last_logged']['user_data'][$current_user->ID] = SGAntiVirus::CalculateSessionMD5($params['last_logged']['sessions']);
            
            session_start();
            
            if ( intval($_SESSION['plgavp_last_logged']) == 0 || $show_alert === true)
            {
                plgwpavp_SetExtraParams( array('last_logged' => json_encode($params['last_logged'])) );
            }

            $_SESSION['plgavp_last_logged'] = 1;
        }
//$show_alert = true;
        if ($show_alert === true)
        {
            // Show alert
            $list_html = '<style>.avp_tbl{float:right;width:25%;font-size:80%!important;color: #D8000C;background-color: #FFBABA;padding-botton:10px}
            .avp_tbl td{text-align:center}</style><table class="avp_tbl"><thead><tr><th>Latest login</th><th>Username</th><th>IP</th><th>Country</th></tr></thead><tbody>';
            foreach ($params['last_logged']['sessions'] as $ip => $row)
            {
                $list_html .= '<tr><td>'.date("Y-m-d H:i:s", $row['time']).'</td><td>'.$row['user'].'</td><td>'.$ip.'</td><td>'.SGAntiVirus::$country_list[ $row['country'] ].'</td></tr>';
            }
            $list_html .= '<tr><td colspan="4">this alert will disappear in {DISAPPEAR_TIME} seconds</td></tr></tbody></table>';

            $show_alert = false;
            
            $_SESSION['session_plgavp_admin_check_last_logged'] = $list_html;
            $_SESSION['session_plgavp_admin_check_last_logged_time'] = 777;
            
            /*
            $msg = "We have detected strage activity of your adminstrator accounts. Please check the latest access sessions to your WordPress backend:<br>".$list_html."If you see <span class='msg_alert'><b>login sessions not from your country</b></span> or <span class='msg_alert'><b>fake admin accounts</b></span>. You need immediately change the passwords for all administrators and remove fake/old accounts.<br><br><span class='msg_alert'>If the hacker has access to your website, he can modify any file, install malicious plugins, insert spam content or fake links to another websites, send spam from your website and redirect or infect your visitors.</span>";
            $show_alert = true;
            $popup_data = array(
                'title' => '<i class="warning sign icon msg_alert"></i> Latest login sessions (Your IP: '.$ip.')',
                'msg' => $msg,
                'bttn_ok' => 'Show my administrators',
                'action_task' => 'last_logged',
                'show_redirect' => 1
            );
            */
            
            if (!isset($params['send_notifications']) || intval($params['send_notifications']) == 1)
            {
                // Send email to admin
       			include_once(dirname(__FILE__).'/sgantivirus.class.php');
                $message = 'Dear owner of '.get_option( 'blogname' ).'!'."<br><br>";
                $list_html = '<table style="width:100%;border:1px solid #777;"><thead><tr><th>Date</th><th>Username</th><th>IP</th><th>Country</th></tr></thead><tbody>';
                foreach ($params['last_logged']['sessions'] as $ip => $row)
                {
                    $list_html .= '<tr><td style="text-align:center">'.date("Y-m-d H:i:s", $row['time']).'</td><td style="text-align:center">'.$row['user'].'</td><td style="text-align:center">'.$ip.'</td><td style="text-align:center">'.SGAntiVirus::$country_list[ $row['country'] ].'</td></tr>';
                }
                $list_html .= '</tbody></table>';
      
                $msg = "We have detected strage activity of your adminstrator accounts. Please check the latest access sessions to your WordPress backend:<br><br>".$list_html."<br><br>If you see <span class='msg_alert'><b>login sessions not from your country</b></span> or <span class='msg_alert'><b>fake admin accounts</b></span>. You need immediately change the passwords for all administrators and remove fake/old accounts.<br><br><span class='msg_alert'>If the hacker has access to your website, he can modify any file, install malicious plugins, insert spam content or fake links to another websites, send spam from your website and redirect or infect your visitors.</span>";
    			$message .= '<style>.msg_alert{color:#D8000C;}</style><p>'.$msg.'</p>';
    			$subject = 'Security alert !!! Latest login sessions of "'.get_option( 'blogname' ).'"';
    			$email = $params['email_for_notifications'];
    			
    			SGAntiVirus_module::SendEmail($email, $message, $subject);
            }
        }
        
        //print_r($params['last_logged']);
    }



        
    // Check for google verification files
    /*
    if (!isset($params['check_google_files']) || $params['check_google_files'] == 1 )
    {
        if (!isset($params['check_google_files']))
        {
            $params_tmp = array();
            $params_tmp['allowed'] = array();
            $params_tmp['remind'] = time() + 1 * 60 * 60;
            
            plgwpavp_SetExtraParams( array('google_files' => json_encode($params_tmp)) );
            session_start();
            unset($_SESSION['session_plgavp_admin_footer_function']);
        }
        else {
            $params['google_files'] = (array)json_decode($params['google_files'], true);
            if ( ( !isset($params['google_files']) || intval($params['google_files']['remind']) < time() ) && $show_alert === false )
            {
        		if (!defined('ABSPATH') || strlen(ABSPATH) < 8) 
        		{
        			$scan_path = dirname(__FILE__);
        			$scan_path = str_replace(DIRSEP.'wp-content'.DIRSEP.'plugins'.DIRSEP.'wp-antivirus-site-protection', DIRSEP, $scan_path);
        		}
                else $scan_path = ABSPATH;
                
                $list = array();
                foreach (glob($scan_path.DIRSEP."google*.html") as $filename) 
                {
                    $filename = basename($filename);
                    if ($filename != 'google91cf177f7782707c.html' && $filename != 'google99751a429cc49c77.html' && $filename != 'google64985e9e3fd706d5.html')   // Belongs to SiteGuarding.com
                    {
                        $list[] = basename($filename);
                    }
                }
                
                if (count($list))
                {
                    if (!isset($params['google_files']['allowed'])) $params['google_files']['allowed'] = array();
                    //$params['google_files']['allowed'] = (array)json_decode($params['google_files']['allowed']);
                    foreach ($list as $k => $filename)
                    {
                        if (in_array($filename, $params['google_files']['allowed'])) 
                        {
                            unset($list[$k]);
                        }
                    }
                }
                
                if (count($list))
                {
                    $msg = "We have detected unknown google files:<br><br><span class='msg_alert'>".implode("<br>", $list)."</span><br><br>Blackhat SEO hacks trying to verify additional accounts as owners of compromised sites in Google Search Console. Spammers can submit fake links, content and sitemap files. <span class='msg_alert'>If the files are fake verification, you can lose your Google search result position.</span><br><br>If these files are not part of your Google Webmaster account or does not belong to you remove them immediately (use FTP or hosting panel file manager) or if the files belong to you add them to allowed list.";
                    $show_alert = true;
                    $popup_data = array(
                        'title' => '<i class="warning sign icon msg_alert"></i> Important notice (Google webmaster file verification)',
                        'msg' => $msg,
                        'bttn_ok' => 'Yes, I confirm these files',
                        'action_task' => 'google_files'
                    );
                }
                else {
                    $params_tmp = array();
                    $params_tmp['allowed'] = $params['google_files']['allowed'];
                    $params_tmp['remind'] = time() + 24 * 60 * 60;
                    
                    plgwpavp_SetExtraParams( array('google_files' => json_encode($params_tmp)) );
                    session_start();
                    unset($_SESSION['session_plgavp_admin_footer_function']);
                }
            }
        }
    }
    */
    

    // Check WP admin change password
    /*
    if (!isset($params['check_wp_admins']) || $params['check_wp_admins'] == 1 )
    {
        $params['wp_admins'] = (array)json_decode($params['wp_admins'], true);
        if ( ( !isset($params['wp_admins']) || intval($params['wp_admins']['remind']) < time() ) && $show_alert === false )
        {
            // Get all admin users
            $args = array(
            	'role'         => 'administrator',
            	'fields'       => 'all'
             ); 
            $wp_admin_users = get_users( $args );

            $list = array();
            $flag_save_changes = false;
            foreach ($wp_admin_users as $wp_admin_user)
            {
                $user_id = $wp_admin_user->ID;
                $user_login = $wp_admin_user->user_login;
                $user_pass_md5 = md5($wp_admin_user->user_pass);
                
                if ($user_login == 'siteguarding' || $user_login == 'siteguarding2') continue;
                
                if (!isset($params['wp_admins']['users'][$user_id]))
                {
                    // New
                    $params['wp_admins']['users'][$user_id] = array(
                        'login' => $user_login,
                        'pass' => $user_pass_md5,
                        'remind' => time() + 24 * 60 * 60
                    );
                }
                else {
                    // Record exists
                    if ($user_pass_md5 != $params['wp_admins']['users'][$user_id]['pass'])
                    {
                        // Password is changed
                        $params['wp_admins']['users'][$user_id] = array(
                            'pass' => $user_pass_md5,
                            'remind' => time() + 365 * 24 * 60 * 60
                        );
                        $flag_save_changes = true;
                    }
                    else if ($params['wp_admins']['users'][$user_id]['remind'] < time())
                        {
                            $list[] = $user_login;
                        }
                }
            }

            
            
            
            if (count($list))
            {
                $msg = "We have detected that you did not change the password for these admin accounts for a long time:<br><br><span class='msg_alert'>".implode("<br>", $list)."</span><br><br>Keep your WordPress admin password updated and strong. By statistics 95% of all WordPress sites hacked thru admin panel with simple, stolen or bruteforced passwords. Change your passwords every 30 days.<br><br><span class='msg_alert'>If your website was hacked before, 100% the hacker left a fake admin account or made a dump of your SQL with all the passwords.</span><br><br>Keep your website secured, change all admin passwords today.";
                $show_alert = true;
                $popup_data = array(
                    'title' => '<i class="warning sign icon msg_alert"></i> Password security',
                    'msg' => $msg,
                    'bttn_ok' => 'Show my administrators',
                    'action_task' => 'wp_admins',
                    'show_redirect' => 1
                );
                
                if ($flag_save_changes)
                {
                    $params_tmp = array();
                    $params_tmp['users'] = $params['wp_admins']['users'];
                    $params_tmp['remind'] = time() + 3 * 60;
                    
                    plgwpavp_SetExtraParams( array('wp_admins' => json_encode($params_tmp)) );
                    session_start();
                    unset($_SESSION['session_plgavp_admin_footer_function']);
                }
            }
            else {
                $params_tmp = array();
                $params_tmp['users'] = $params['wp_admins']['users'];
                $params_tmp['remind'] = time() + 3 * 60 * 60;
                
                plgwpavp_SetExtraParams( array('wp_admins' => json_encode($params_tmp)) );
                session_start();
                unset($_SESSION['session_plgavp_admin_footer_function']);
            }
        }
    }*/
    
        
        

        if ($show_alert) 
        {
            wp_enqueue_style( 'plgavp_LoadStyle' );
            wp_enqueue_style( 'plgavp_LoadStyle_modal' );
            wp_enqueue_script( 'plgavp_LoadJS_modal' );

        ?>
            <script>
            jQuery(document).ready(function(){
                jQuery('.ui.modal').modal('show');
            });
            function SG_SubmitDecision(action_task, action_value, show_redirect)
            {
                jQuery('#action_value').val(action_value);
                jQuery('#action_task').val(action_task);
                jQuery('#show_redirect').val(show_redirect);
                jQuery('#plgwpagp_decision_page').submit();
            }
            </script>
            <style>
            #adminmenuwrap {z-index: 100};
            </style>
            <form method="post" id="plgwpagp_decision_page" action="admin.php?page=plgavp_Antivirus&tab=0">
            <?php
            wp_nonce_field( 'name_49FD96F7C7F5' );
            ?>			
            <input type="hidden" name="page" value="plgavp_Antivirus"/>
            <input type="hidden" name="confirm_decision" value="1"/>
            <input type="hidden" name="show_redirect" id="show_redirect" value="0"/>
            <input type="hidden" name="action" value="confirm_decision"/>
            <input type="hidden" name="action_value" id="action_value" value=""/>
            <input type="hidden" name="action_task" id="action_task" value=""/>
            </form>

            <div class="ui modal">
              <i class="close icon"></i>
              <div class="header">
                <?php echo $popup_data['title']; ?>
              </div>
              <div class="content">
                <div class="description">
                  <p><?php echo $popup_data['msg']; ?></p>
                  <p>Please review and make decision or contact <a href='https://www.siteguarding.com/en/contacts' target='_blank'>SiteGuarding.com support</a> for more information.</p>
                </div>
              </div>
              <div class="actions">
                <div class="ui black deny button" onclick="SG_SubmitDecision('<?php echo $popup_data['action_task']; ?>', 'later', 0)">
                  Remind me later
                </div>
                <div class="ui positive right button" onclick="SG_SubmitDecision('<?php echo $popup_data['action_task']; ?>', 'confirm', '<?php echo $popup_data['show_redirect']; ?>')">
                  <?php echo $popup_data['bttn_ok']; ?>
                </div>
              </div>
            </div>
        <?php
        }
        
    }


    include_once(dirname(__FILE__).DIRSEP.'sgantivirus.class.php');
	$avp_params = plgwpavp_GetExtraParams();
    
		// Refresh Status
		if (isset($_GET['action']) && $_GET['action'] == 'AVP_RefreshStatus')
		{
		    $data = array('cache_license_info_time' => 0);
            plgwpavp_SetExtraParams($data);
            session_start();
            unset($_SESSION['session_plgavp_admin_footer_function']);
		}
    
	if (count($avp_params) && $avp_params !== false)
	{
		$avp_license_info = SGAntiVirus::GetLicenseInfo(get_site_url(), $avp_params['access_key']);
		
        /**
        * Global Alerts
        */
		// Save membership type
		$data = array('membership' => $avp_license_info['membership']);
		plgwpavp_SetExtraParams($data);
        session_start();
        unset($_SESSION['session_plgavp_admin_footer_function']);
		
		$avp_alert_main = 0;
		if (count($avp_license_info['last_scan_files']['main']))
		{
			foreach ($avp_license_info['last_scan_files']['main'] as $tmp_file)
			{
				if (file_exists(ABSPATH.'/'.$tmp_file)) $avp_alert_main++;
			}
		}
		if ($avp_license_info['membership'] != 'pro') $avp_alert_main = $avp_license_info['last_scan_files_counters']['main'];
	
		$avp_alert_heuristic = 0;
		if (count($avp_license_info['last_scan_files']['heuristic']))
		{
			foreach ($avp_license_info['last_scan_files']['heuristic'] as $tmp_file)
			{
				if (file_exists(ABSPATH.'/'.$tmp_file)) $avp_alert_heuristic++;
			}
		}
		if ($avp_license_info['membership'] != 'pro') $avp_alert_heuristic = $avp_license_info['last_scan_files_counters']['heuristic'];
	
		if ($avp_alert_main > 0 || $avp_alert_heuristic > 0)
		{
			$avp_alert_txt = '<span class="update-plugins"><span class="update-count">'.$avp_alert_main.'/'.$avp_alert_heuristic.'</span></span>';	
		} 
		else $avp_alert_txt = '';
		
	
    if (isset($avp_license_info['membership']))	
    {
		if ($avp_alert_main > 0 || $avp_alert_heuristic > 0 || $avp_license_info['blacklist']['google'] != 'ok' )
		{
			$avp_eachpage_alert_txt = '<b>Antivirus Important Notice:</b>';
			if ($avp_alert_main > 0)
			{
				$avp_eachpage_alert_txt .= ' Virus code detected: '.$avp_alert_main.' file(s).'; 
			}
			if ($avp_alert_heuristic > 0)
			{
				$avp_eachpage_alert_txt .= ' Unsafe code detected: '.$avp_alert_heuristic.' file(s).'; 
			}
 			if (isset($avp_license_info['blacklist']['google']) && $avp_license_info['blacklist']['google'] != 'ok')
			{
				$avp_eachpage_alert_txt .= ' Blacklisted, Reason ['.$avp_license_info['blacklist']['google'].']'; 
			}
		} 
		else if ($avp_license_info['membership'] != 'pro' && $avp_license_info['membership'] != 'trial') $avp_eachpage_alert_txt .= '<b>Antivirus Important Notice:</b> Your license is expired and antivirus has limits. Some features are disabled.';
			else  $avp_eachpage_alert_txt = '';
		
		if ($avp_license_info['membership'] != 'pro' && $avp_license_info['membership'] != 'trial') $avp_eachpage_alert_txt .= '';
        
        if ( intval($avp_license_info['filemonitoring']['plan_id']) > 2) $avp_eachpage_alert_txt = '';
     }   
        /**
         * Global Updates and Restoring
         */ 
        if ($avp_params['last_core_update'] < date("Y-m-d") && SGAVP_UPDATE === true)  
        {
    		$data = array('last_core_update' => date("Y-m-d"));
    		plgwpavp_SetExtraParams($data);
            session_start();
            unset($_SESSION['session_plgavp_admin_footer_function']);
            
			$result = SGAntiVirus::DownloadFromWordpress_Link($avp_license_info['update_url']);
			if ($result === true && class_exists('ZipArchive') && $avp_license_info['latest_version'] > SGAntiVirus_module::$antivirus_version) 
		    {
        		if (!defined('ABSPATH') || strlen(ABSPATH) < 8) 
        		{
        			$site_path = dirname(__FILE__);
        			$site_path = str_replace(DIRSEP.'wp-content'.DIRSEP.'plugins'.DIRSEP.'wp-antivirus-site-protection', DIRSEP, $site_path);
        		}
                else $site_path = ABSPATH;
                
                // Copy core files
                $corefile_list = SGAntiVirus_module::CoreFile_get_list();
                $corefile_list_tmp = SGAntiVirus_module::CoreFile_copy_to_TMP($corefile_list);
                
                $update_status = false;
                for ($i = 1; $i <= 10; $i++)
                {
    		    	$zip = new ZipArchive;
    				if ($zip->open(dirname(__FILE__).DIRSEP.'tmp'.DIRSEP.'update.zip') === TRUE) 
                    {
    					$extract_path = $site_path.'wp-content'.DIRSEP.'plugins'.DIRSEP;
    
    				    $unzip_status = $zip->extractTo($extract_path);
    
    				    $zip->close();
    				    
    				} 
                    
                    // Check full core update
                    if (SGAntiVirus_module::CoreFile_check_updated_files())
                    {
                        $update_status = true;
                        break;
                    }
                }
                
                // Return/Remove core files
                if ($update_status === false) SGAntiVirus_module::CoreFile_copy_from_TMP($corefile_list_tmp);
                SGAntiVirus_module::CoreFile_clean_TMP($corefile_list_tmp);
                
	    	}
            
            // Check installation of standalone version
            if (!SGAntiVirus::CheckStandAloneVersion())
            {
                SGAntiVirus::InstalltandAloneVersion();
            }
        }      
	}


	add_action('admin_menu', 'register_plgavp_settings_page');

	
	function antivirus_admin_notice() 
	{
		global $avp_eachpage_alert_txt;
        
        $avp_params = plgwpavp_GetExtraParams();
        
        if ($avp_params['hide_alert_till'] > date('Y-m-d') || intval($_GET['hide_alert']) == 1) return;
        
		if ($avp_eachpage_alert_txt != '') 
		{
	    ?>
		    <div class="error">
		        <p style="color:#DD3D36;font-size:20px;"><?php echo $avp_eachpage_alert_txt; ?></p>
		        <p><a href="/wp-admin/admin.php?page=plgavp_Antivirus&hide_alert=1">View details & Hide</a></p>
		    </div>
		    <?php
	    }
	}
	add_action( 'admin_notices', 'antivirus_admin_notice' );



    add_action( 'wp_ajax_plgavp_ajax_scan_sql', 'plgavp_ajax_scan_sql' );
    function plgavp_ajax_scan_sql() 
    {
        AVP_SEO_SG_Protection::MakeAnalyze();
        echo 'OK';
        wp_die();
    }


	function register_plgavp_settings_page() 
	{
		global $avp_alert_txt;
		
		add_menu_page('plgavp_Antivirus', 'Antivirus'.$avp_alert_txt, 'activate_plugins', 'plgavp_Antivirus', 'plgavp_settings_page_callback', plugins_url('images/', __FILE__).'antivirus-logo.png');
	}

	function plgavp_settings_page_callback() 
	{
		// PHP version check
        $php_version = explode('.', PHP_VERSION);
        $php_version = floatval($php_version[0].'.'.$php_version[1]);
        
		/*if ($php_version <= 5.2)
		{
			// Error class module is not loaded
			SGAntiVirus::ShowMessage('Your PHP version is too old ['.PHP_VERSION.']. Please ask your hoster to upgrade PHP.<br><br>This version for PHP 5.3 and older. If you want to use our scanner on your server please download WP Antivirus version 4.8.2. <a href="https://www.siteguarding.com/files/wp-antivirus-site-protection-4.8.2.zip">Click to download</a>');
			return;
		}*/
        
		// Load class
		if (!file_exists(dirname(__FILE__).'/sgantivirus.class.php'))
		{
			// Error class module is not loaded
			SGAntiVirus::ShowMessage('File '.dirname(__FILE__).'/sgantivirus.class.php is not exist.');
			return;
		}
		
		include_once(dirname(__FILE__).'/sgantivirus.class.php');
		
		if (!class_exists('SGAntiVirus_module'))
		{
			// Error module is not loaded
			SGAntiVirus::ShowMessage('Main antivirus scanner module is not loaded. Please try again.');
			return;
		}
		
		wp_enqueue_style( 'plgavp_LoadStyle' );
		//wp_enqueue_style( 'plgavp_LoadStyle_modal' );
		
		?>
			<h2 class="avp_header icon_radar">WP Antivirus Site Protection</h2>
			
		<?php
		
		
		// Actions
		// Confirm Registration
		if (isset($_POST['action']) && $_POST['action'] == 'ConfirmRegistration' && check_admin_referer( 'name_254f4bd3ea8d' ))
		{
			$errors = SGAntiVirus::checkServerSettings(true);
			$access_key = md5(time().get_site_url());
			$email = trim($_POST['email']);
			$result = SGAntiVirus::sendRegistration(get_site_url(), $email, $access_key, $errors);
			if ($result === true)
			{
				$data = array('registered' => 1, 'email' => $email, 'access_key' => $access_key);
				plgwpavp_SetExtraParams($data);
				
				// Send access_key to user
				$message = 'Dear Customer!'."<br><br>";
				$message .= 'Thank you for registration your copy of WP Antivirus Site Protection. Please keep this email for your records, it contains your registration information and you will need it in the future.'."<br><br>";
				$message .= '<b>Registration information:</b>'."<br><br>";
				$message .= '<b>Domain:</b> '.get_site_url()."<br>";
				$message .= '<b>Email:</b> '.$email."<br>";
				$message .= '<b>Access Key:</b> '.$access_key."<br><br>";
				$subject = 'AntiVirus Registration Information';
				
				SGAntiVirus_module::SendEmail($email, $message, $subject);
			}
			else {
				// Show error
				SGAntiVirus::ShowMessage($result);
				return;
			}
		}
        



		// Popup confirmation 
		if (isset($_POST['action']) && $_POST['action'] == 'confirm_decision' && check_admin_referer( 'name_49FD96F7C7F5' ))
		{
		    $action_task = trim($_POST['action_task']);
		    $action_value = trim($_POST['action_value']);
            
            switch ($action_task)
            {
                case 'google_files':
                    $params_tmp = plgwpavp_GetExtraParams(array('google_files'));
                    $params_tmp['google_files'] = (array)json_decode($params_tmp['google_files'], true);
                    
                    if ($action_value == 'later')
                    {
                        $params_tmp['google_files']['remind'] = time() + 24 * 60 * 60;
                    }
                    
                    if ($action_value == 'confirm')
                    {
                		if (!defined('ABSPATH') || strlen(ABSPATH) < 8) 
                		{
                			$scan_path = dirname(__FILE__);
                			$scan_path = str_replace(DIRSEP.'wp-content'.DIRSEP.'plugins'.DIRSEP.'wp-antivirus-site-protection', DIRSEP, $scan_path);
                		}
                        else $scan_path = ABSPATH;
                        
                        $list = array();
                        foreach (glob($scan_path.DIRSEP."google*.html") as $filename) 
                        {
                            $filename = basename($filename);
                            if ($filename != 'google91cf177f7782707c.html' && $filename != 'google99751a429cc49c77.html')   // Belongs to SiteGuarding.com
                            {
                                $list[] = trim(basename($filename));
                            }
                        }
                        $params_tmp['google_files']['allowed'] = $list;
                    }
                    
                    $params_tmp['google_files'] = json_encode($params_tmp['google_files']);
                    
                    plgwpavp_SetExtraParams( $params_tmp );
                    session_start();
                    unset($_SESSION['session_plgavp_admin_footer_function']);
                    break;
                    
                
                
                case 'wp_admins':
                    $params_tmp = plgwpavp_GetExtraParams(array('wp_admins'));
                    $params_tmp['wp_admins'] = (array)json_decode($params_tmp['wp_admins'], true);
                    
                    if ($action_value == 'later')
                    {
                        $params_tmp['wp_admins']['remind'] = time() + 24 * 60 * 60;
                    }
                    
                    if ($action_value == 'confirm')
                    {
                		$params_tmp['wp_admins']['remind'] = time() + 1 * 60 * 60;
                        $redirect_link = '/wp-admin/users.php?role=administrator';
                		?>
                        <script>
                        jQuery(location).attr('href','<?php echo $redirect_link; ?>');
                        jQuery(window).attr('location','<?php echo $redirect_link; ?>');
                        jQuery(location).prop('href', '<?php echo $redirect_link; ?>');
                        </script>
                        <?php
                    }
                    
                    $params_tmp['wp_admins'] = json_encode($params_tmp['wp_admins']);
                    
                    plgwpavp_SetExtraParams( $params_tmp );
                    session_start();
                    unset($_SESSION['session_plgavp_admin_footer_function']);
                    break;
                    
                case 'last_logged':
                    $params_tmp = plgwpavp_GetExtraParams(array('wp_admins'));
                    $params_tmp['wp_admins'] = (array)json_decode($params_tmp['wp_admins'], true);
                    
                    if ($action_value == 'later')
                    {
                        
                    }
                    
                    if ($action_value == 'confirm')
                    {
                        $redirect_link = '/wp-admin/users.php?role=administrator';
                		?>
                        <script>
                        jQuery(location).attr('href','<?php echo $redirect_link; ?>');
                        jQuery(window).attr('location','<?php echo $redirect_link; ?>');
                        jQuery(location).prop('href', '<?php echo $redirect_link; ?>');
                        </script>
                        <?php
                    }
                    break;
                    
            }
		}
        
        
		// Start Scan
		if (isset($_GET['action']) && $_GET['action'] == 'StartWPTests_iframe')
		{
		    $data = array('latest_WP_scan_date' => date("Y-m-d H:i:s"));
            plgwpavp_SetExtraParams($data);
            
            $security_tests = SGAntiVirus_WP_tests::$security_tests;
            $chk_class = new SGAntiVirus_WP_tests();
            
            foreach ($security_tests as $k => $row)
            {
                if (method_exists($chk_class, $k))
                {
                    $test_answer = SGAntiVirus_WP_tests::$k();
        		    $data = array($k => json_encode($test_answer));
                    plgwpavp_SetExtraParams($data);
                }
            }

			return;
		}
        
        
        
		
		// Start Scan
		if (isset($_POST['action']) && $_POST['action'] == 'StartScan' && check_admin_referer( 'name_254f4bd3ea8d' ))
		{
			$data = array('allow_scan' => intval($_POST['allow_scan']), 'do_evristic' => intval($_POST['do_evristic']));
			plgwpavp_SetExtraParams($data);
			
			$params = plgwpavp_GetExtraParams();
			
			// Check if something in progress
			$progress_info = SGAntiVirus::GetProgressInfo(get_site_url(), $params['access_key']);
			if ($progress_info['in_progress'] > 0)
			{
				$msg = 'Another scanning process is in progress. In 5-10 minutes you will get report by email or it will be available in Latest Reports section.';
				SGAntiVirus::ShowMessage($msg);
				return;
			} 
			
			global $avp_license_info;
			$session_id = md5(time().'-'.rand(1,10000));
			ob_start();
			session_start();
			ob_end_clean();
			$_SESSION['scan']['session_id'] = $session_id;
			SGAntiVirus::ScanProgress($session_id, ABSPATH, $params, $avp_license_info);
			return;
		}
        
		

		// Quarantine & Malware remove
		if (isset($_POST['action']) && $_POST['action'] == 'QuarantineFiles' && check_admin_referer( 'name_254f4bd3ea8d' ))
		{
			$params = plgwpavp_GetExtraParams();
			
			$license_info = SGAntiVirus::GetLicenseInfo(get_site_url(), $params['access_key']);

			if ($license_info === false) { SGAntiVirus::page_ConfirmRegistration(); return; }
			
			if ($license_info['membership'] == 'pro')
			{ 
				if (isset($_POST['filelist']))
				{
					$filelist_type = trim($_POST['filelist']);
					switch($filelist_type)
					{
						case 'main':
						case 'heuristic':
							$a = SGAntiVirus::QuarantineFiles($license_info['last_scan_files'][$filelist_type]);
							break;
							
						default:
							die('filelist is not allowed');
							break;
					}	
				}
					
				if ($a === true)
				{
					SGAntiVirus::ShowMessage('Malware moved to quarantine and deleted from the server.');	
				}
				else {
					SGAntiVirus::ShowMessage('Operation is failed. Some files are not moved to quarantine or not deleted.', 'error');
				}
			}
		}
		
		
		// Send files to SiteGuarding.com
		if (isset($_POST['action']) && $_POST['action'] == 'SendFilesForAnalyze' && check_admin_referer( 'name_254f4bd3ea8d' ))
		{
			$params = plgwpavp_GetExtraParams();
			
			$license_info = SGAntiVirus::GetLicenseInfo(get_site_url(), $params['access_key']);

			if ($license_info === false) { SGAntiVirus::page_ConfirmRegistration(); return; }
			
			if ($license_info['membership'] == 'pro')
			{ 
				$a = SGAntiVirus::SendFilesForAnalyze($license_info['last_scan_files'], $license_info['email'] );	
				if ($a === true)
				{
					SGAntiVirus::ShowMessage('Files sent for analyze. SiteGuarding.com support will contact with you within 24-48 hours. You will get report by email '.$license_info['email']);	
				}
				else {
					SGAntiVirus::ShowMessage('Operation is failed. Nothing sent for analyze.', 'error');
				}
			}
		}
		
		
		
		


		// Get params
		$params = plgwpavp_GetExtraParams();
		
		
		// Check if website is registered
		//SGAntiVirus::page_ConfirmRegistration(); return;
		if (!isset($params['registered']) || intval($params['registered']) == 0) { SGAntiVirus::page_ConfirmRegistration(); return; }
		
		// Get data from siteguading about number of scans and exp date
		$license_info = SGAntiVirus::GetLicenseInfo(get_site_url(), $params['access_key']);
		if ($license_info === false) { SGAntiVirus::page_ConfirmRegistration(); return; }
		
		// Check server settings
		if (!SGAntiVirus::checkServerSettings()) return;

		/*
		echo '<pre>';
		print_r($license_info);
		print_r($params);
		echo '</pre>';
		*/
		
		

		global $avp_license_info;
		SGAntiVirus_module::MembershipFile($avp_license_info['membership'], $avp_license_info['scans'], $params['show_protectedby']);



		foreach ($license_info as $k => $v)
		{
			$params[$k] = $v;	
		}
		
		
		SGAntiVirus::page_PreScan($params);

	}
	
	


	
	
	add_action('admin_menu', 'register_plgavp_settings_subpage');

	function register_plgavp_settings_subpage() {
		add_submenu_page( 'plgavp_Antivirus', 'Settings', 'Settings', 'manage_options', 'plgavp_Antivirus_settings_page', 'plgavp_antivirus_settings_page_callback' ); 
	}
	
	
	function plgavp_antivirus_settings_page_callback()
	{
		wp_enqueue_style( 'plgavp_LoadStyle' );
		//wp_enqueue_style( 'plgavp_LoadStyle_modal' );

		$img_path = plugins_url('images/', __FILE__);
		
		if (isset($_POST['action']) && $_POST['action'] == 'update' && check_admin_referer( 'name_AFAD78D85E01' ))
		{
			$data = array('access_key' => trim($_POST['access_key']));
			if (trim($_POST['access_key']) != '') 
			{
				$data['registered'] = 1;
				$data['email'] = get_option( 'admin_email' );
			}
			
			$data['show_protectedby'] = intval($_POST['show_protectedby']);
			
			global $avp_license_info;
			if ($avp_license_info['membership'] == 'free') $data['show_protectedby'] = 1;
            
            // reCAPTCHA settings
            $data['protect_login_page'] = intval($_POST['protect_login_page']);
            $data['captcha_site_key'] = trim($_POST['captcha_site_key']);
            $data['captcha_secret_key'] = trim($_POST['captcha_secret_key']);
            $data['send_notifications'] = intval($_POST['send_notifications']);
            $data['email_for_notifications'] = trim($_POST['email_for_notifications']);
            if ($data['email_for_notifications'] == '') $data['email_for_notifications'] = get_option( 'admin_email' );
            $data['check_wp_admins'] = intval($_POST['check_wp_admins']);
            $data['check_last_logged'] = intval($_POST['check_last_logged']);
            $data['check_google_files'] = intval($_POST['check_google_files']);
            if ( /*$data['check_wp_admins'] == 0 || */$data['check_last_logged'] == 0 || $data['check_google_files'] == 0)
            {
                $data['check_settings_reminder'] = time() + 7 * 24 * 60 * 60;
            }
            else {
                $data['check_settings_reminder'] = 1;
                session_start();
                unset($_SESSION['session_plgavp_admin_head_function']);
            }
            
            $error_txt = '';
            if ($data['captcha_site_key'] == '' || $data['captcha_secret_key'] == '') 
            {
                $data['protect_login_page'] = 0;
                $error_txt = ' Protect Login page is disabled. Wrong reCAPTCHA keys.';
            }
            
            // Save keys to file
            $fp = fopen(dirname(__FILE__).'/sgantivirus.login.keys.php', 'w');
            fwrite($fp, '<?php $captcha_key_site = "'.$data['captcha_site_key'].'"; $captcha_key_secret = "'.$data['captcha_secret_key'].'"; ?>');
            fclose($fp);
            
            // Patch wp-login.php
            if ($data['protect_login_page'] == 1) 
            {
                if (SGAntiVirus::PatchWPLogin_file(true) === false)
                {
                    $data['protect_login_page'] = 0;
                    $error_txt = ' Protect Login page is disabled. Can\'t modify wp-login.php';
                }
            }
            if ($data['protect_login_page'] == 0) 
            {
                SGAntiVirus::PatchWPLogin_file(false);
            }
			
			plgwpavp_SetExtraParams($data);
			
			SGAntiVirus::ShowMessage('Settings saved.'.$error_txt);
		}
		
		$params = plgwpavp_GetExtraParams();
		
		?>
		<h2 class="avp_header icon_settings">WP Antivirus Settings</h2>
		
<form method="post" id="plgwpagp_settings_page" action="admin.php?page=plgavp_Antivirus_settings_page" onsubmit="return SG_CheckForm(this);">


			<table id="settings_page">


			<tr class="line_4">
			<th scope="row"><?php _e( 'Access Key', 'plgwpavp' )?></th>
			<td>
	            <input type="text" name="access_key" id="access_key" value="<?php echo $params['access_key']; ?>" class="regular-text">
	            <br />
	            <span class="description">This key is necessary to access to <a target="_blank" href="http://www.siteguarding.com">SiteGuarding API</a> features. Every website has uniq access key. Don't change it fo you don't know what is it.</span>
			</td>
			</tr>
            
			<tr class="line_4">
			<th scope="row">&nbsp;</th>
			<td>
	            <hr />
			</td>
			</tr>
            
			<tr class="line_4">
			<th scope="row"><?php _e( 'Protect Login page', 'plgwpavp' )?></th>
			<td>
	            <input name="protect_login_page" type="checkbox" id="show_protect_login_page" value="1" <?php if (intval($params['protect_login_page']) == 1) echo 'checked="checked"'; ?>>
                &nbsp;<span class="msg_alert">Bruteforce Protection.</span> Activates special captcha page against bruteforce attack.&nbsp;[<a href="https://www.siteguarding.com/en/bruteforce-attack" target="_blank">Info</a>]
			</td>
			</tr>
            
			<tr class="line_4">
			<th scope="row"><?php _e( 'reCAPTCHA Site Key', 'plgwpavp' )?></th>
			<td>
	            <input type="text" name="captcha_site_key" id="captcha_site_key" value="<?php echo $params['captcha_site_key']; ?>" class="regular-text">
			</td>
			</tr>
            
			<tr class="line_4">
			<th scope="row"><?php _e( 'reCAPTCHA Secret key', 'plgwpavp' )?></th>
			<td>
	            <input type="text" name="captcha_secret_key" id="captcha_secret_key" value="<?php echo $params['captcha_secret_key']; ?>" class="regular-text">
			</td>
			</tr>
            
			<tr class="line_4">
			<th scope="row">&nbsp;</th>
			<td>
	            Get reCAPTCHA keys for your site here <a target="_blank" href="https://www.google.com/recaptcha/intro/index.html">https://www.google.com/recaptcha/intro/index.html</a>
                <br />
                <p><span class="dashicons dashicons-editor-help"></span> <a href="javascript:;" onclick="javascript:jQuery('#google_help').toggle();">How me how to do it</a></p>
                <p id="google_help" style="display: none;"><b>Step 1. Fill the form</b><br><br>
                <img src="<?php echo plugins_url('images/', __FILE__).'help1.jpg'; ?>"/><br><br>
                
                <br><b>Step 2. Copy and Insert the keys</b><br><br>
                <img src="<?php echo plugins_url('images/', __FILE__).'help2.jpg'; ?>"/>
                </p>
			</td>
			</tr>
            
            
			<tr class="line_4">
			<th scope="row">&nbsp;</th>
			<td>
	            <hr />
			</td>
			</tr>
            
			<tr class="line_4">
			<th scope="row"><?php _e( 'Send Notifications', 'plgwpavp' )?></th>
			<td>
                <?php
                if (!isset($params['send_notifications'])) $params['send_notifications'] = 1;
                ?>
	            <input name="send_notifications" type="checkbox" id="send_notifications" value="1" <?php if (intval($params['send_notifications']) == 1) echo 'checked="checked"'; ?>>
                &nbsp;<span class="msg_alert">We will send important notifications by email.</span>
			</td>
			</tr>
            
			<tr class="line_4">
			<th scope="row"><?php _e( 'Email for Notifications', 'plgwpavp' )?></th>
			<td>
                <?php
                if (trim($params['email_for_notifications']) == '') $params['email_for_notifications'] = get_option( 'admin_email' );
                ?>
	            <input type="text" name="email_for_notifications" id="email_for_notifications" value="<?php echo $params['email_for_notifications']; ?>" class="regular-text">
			</td>
			</tr>
            
			<tr class="line_4">
			<th scope="row"><?php _e( 'Check Logged Admins', 'plgwpavp' )?></th>
			<td>
                <?php
                if (!isset($params['check_last_logged'])) $params['check_last_logged'] = 1;
                ?>
	            <input name="check_last_logged" type="checkbox" id="check_last_logged" value="1" <?php if (intval($params['check_last_logged']) == 1) echo 'checked="checked"'; ?>>
                &nbsp;Popup alert about strange login sessions
			</td>
			</tr>
            
            
			<tr class="line_4">
			<th scope="row"><?php _e( 'Check Google files', 'plgwpavp' )?></th>
			<td>
                <?php
                if (!isset($params['check_google_files'])) $params['check_google_files'] = 1;
                ?>
	            <input name="check_google_files" type="checkbox" id="check_google_files" value="1" <?php if (intval($params['check_google_files']) == 1) echo 'checked="checked"'; ?>>
                &nbsp;Popup alert about strange Google webmaster verification files
			</td>
			</tr>
<?php /*
			<tr class="line_4">
			<th scope="row"><?php _e( 'Password Change advice', 'plgwpavp' )?></th>
			<td>
                <?php
                if (!isset($params['check_wp_admins'])) $params['check_wp_admins'] = 1;
                ?>
	            <input name="check_wp_admins" type="checkbox" id="check_wp_admins" value="1" <?php if (intval($params['check_wp_admins']) == 1) echo 'checked="checked"'; ?>>
                &nbsp;Popup alert to change all admin passwords
			</td>
			</tr>
*/ ?>
			<tr class="line_4">
			<th scope="row">&nbsp;</th>
			<td>
	            <hr />
			</td>
			</tr>
			
			<tr class="line_4">
			<th scope="row"><?php _e( 'Show \'Protected by\'', 'plgwpavp' )?></th>
			<td>
	            <input <?php if ($params['membership'] == 'free') {echo 'disabled';$params['show_protectedby'] = 1;} ?> name="show_protectedby" type="checkbox" id="show_protectedby" value="1" <?php if (intval($params['show_protectedby']) == 1) echo 'checked="checked"'; ?>>
			</td>
			</tr>

			
			</table>

<?php
wp_nonce_field( 'name_AFAD78D85E01' );
?>			
<p class="submit">
  <input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes">
</p>

<input type="hidden" name="page" value="plgavp_Antivirus_settings_page"/>
<input type="hidden" name="action" value="update"/>
</form>


		<h3>Cron Settings</h3>
		
		<p>
		If you want to enable daily scan of your website. Add this line in your hosting panel in cron settings.<br /><br />
		<b>Unix time settings:</b> 0 0 * * *<br />
		<b>Command:</b> wget -O /dev/null "<?php echo get_site_url(); ?>/index.php?task=cron&access_key=<?php echo $params['access_key']; ?>"
		</p>

		<?php
	}
	
	
	add_action('admin_menu', 'register_plgavp_extentions_subpage');

	function register_plgavp_extentions_subpage() {
		add_submenu_page( 'plgavp_Antivirus', 'Extentions', 'Extentions', 'manage_options', 'plgavp_Antivirus_extentions_page', 'plgavp_antivirus_extentions_page_callback' ); 
	}
	
	
	function plgavp_antivirus_extentions_page_callback()
	{
		wp_enqueue_style( 'plgavp_LoadStyle' );
		//wp_enqueue_style( 'plgavp_LoadStyle_modal' );
		
		?>
		
		<h2 class="avp_header icon_settings">Security Extentions</h2>
		
		<div class="grid-box width25 grid-h" style="width: 250px;">
		  <div class="module mod-box widget_black_studio_tinymce">
		    <div class="deepest">
		      <h3 class="module-title">WordPress GEO Protection</h3>
		      <div class="textwidget">
		        <table class="table-val" style="height: 180px;">
		          <tbody>
		            <tr>
		              <td class="table-vat">
		                <ul style="list-style-type: circle;">
		                  <li>
		                    Ban the visitors from unwanted countries
		                  </li>
		                  <li>
                    		Ban the visitors to your backend login page
		                  </li>
		                  <li>
		                    Ban IP addresses which are bruteforcing your passwords
		                  </li>
		                  <li>
		                    It's easy to setup and free to use
		                  </li>
		                </ul>
		              </td>
		            </tr>
		            <tr>
		              <td class="table-vab">
		                <a class="button button-primary extbttn" href="https://www.siteguarding.com/en/wordpress-geo-website-protection">
		                  Learn More
		                </a>
		              </td>
		            </tr>
		          </tbody>
		        </table>
		        <p>
		          <img class="imgpos_ext" alt="WordPress Admin Protection" src="<?php echo plugins_url('images/wp-geo-website-protection.png', __FILE__); ?>">
		        </p>
		      </div>
		    </div>
		  </div>
		</div>
        
		<div class="grid-box width25 grid-h" style="width: 250px;">
		  <div class="module mod-box widget_black_studio_tinymce">
		    <div class="deepest">
		      <h3 class="module-title">WordPress Admin Protection</h3>
		      <div class="textwidget">
		        <table class="table-val" style="height: 180px;">
		          <tbody>
		            <tr>
		              <td class="table-vat">
		                <ul style="list-style-type: circle;">
		                  <li>
		                    Prevents password brute force attack with strong 'secret key'
		                  </li>
		                  <li>
                    		White & Black IP list access
		                  </li>
		                  <li>
		                    Notifications by email about all not authorized actions
		                  </li>
		                  <li>
		                    Protection for login page with captcha code
		                  </li>
		                </ul>
		              </td>
		            </tr>
		            <tr>
		              <td class="table-vab">
		                <a class="button button-primary extbttn" href="https://www.siteguarding.com/en/wordpress-admin-protection">
		                  Learn More
		                </a>
		              </td>
		            </tr>
		          </tbody>
		        </table>
		        <p>
		          <img class="imgpos_ext" alt="WordPress Admin Protection" src="<?php echo plugins_url('images/wpAdminProtection-logo.png', __FILE__); ?>">
		        </p>
		      </div>
		    </div>
		  </div>
		</div>
		
		
		<div class="grid-box width25 grid-h" style="width: 250px;">
		  <div class="module mod-box widget_black_studio_tinymce">
		    <div class="deepest">
		      <h3 class="module-title">Graphic Captcha Protection</h3>
		      <div class="textwidget">
		        <table class="table-val" style="height: 180px;">
		          <tbody>
		            <tr>
		              <td class="table-vat">
		                <ul style="list-style-type: circle;">
		                  <li>
		                    Strong captcha protection
		                  </li>
		                  <li>
                    		Easy for human, complicated for robots
		                  </li>
		                  <li>
		                    Prevents password brute force attack on login page
		                  </li>
		                  <li>
		                    Blocks spam software
		                  </li>
		                  <li>
		                    Different levels of the security
		                  </li>
		                </ul>
		              </td>
		            </tr>
		            <tr>
		              <td class="table-vab">
		                <a class="button button-primary extbttn" href="https://www.siteguarding.com/en/wordpress-graphic-captcha-protection">
		                  Learn More
		                </a>
		              </td>
		            </tr>
		          </tbody>
		        </table>
		        <p>
		          <img class="imgpos_ext" alt="WordPress Graphic Captcha Protection" src="<?php echo plugins_url('images/wpGraphicCaptchaProtection-logo.png', __FILE__); ?>">
		        </p>
		      </div>
		    </div>
		  </div>
		</div>
		
		
		<div class="grid-box width25 grid-h" style="width: 250px;">
		  <div class="module mod-box widget_black_studio_tinymce">
		    <div class="deepest">
		      <h3 class="module-title">Admin Graphic Protection</h3>
		      <div class="textwidget">
		        <table class="table-val" style="height: 180px;">
		          <tbody>
		            <tr>
		              <td class="table-vat">
		                <ul style="list-style-type: circle;">
		                  <li>
		                    Good solution if you access to your website from public places or infected computers
		                  </li>
		                  <li>
		                    Prevent password brute force attack with strong "graphic password"
		                  </li>
		                  <li>
		                    Notifications by email about all not authorized actions
		                  </li>
		                </ul>
		              </td>
		            </tr>
		            <tr>
		              <td class="table-vab">
		                <a class="button button-primary extbttn" href="https://www.siteguarding.com/en/wordpress-admin-graphic-password">
		                  Learn More
		                </a>
		              </td>
		            </tr>
		          </tbody>
		        </table>
		        <p>
		          <img class="imgpos_ext" alt="WordPress Admin Graphic Protection" src="<?php echo plugins_url('images/wpAdminGraphicPassword-logo.png', __FILE__); ?>">
		        </p>
		      </div>
		    </div>
		  </div>
		</div>
		
		
		<div class="grid-box width25 grid-h" style="width: 250px;">
		  <div class="module mod-box widget_black_studio_tinymce">
		    <div class="deepest" >
		      <h3 class="module-title">User Access Notification</h3>
		      <div class="textwidget">
		        <table class="table-val" style="height: 180px;">
		          <tbody>
		            <tr>
		              <td class="table-vat">
		                <ul style="list-style-type: circle;">
		                  <li>
		                    Catchs successful and failed login actions
		                  </li>
		                  <li>
		                    Sends notifications to the user and to the administrator by email
		                  </li>
		                  <li>
		                    Shows Date/Time of access action, Browser, IP address, Location (City, Country)
		                  </li>
		                </ul>
		              </td>
		            </tr>
		            <tr>
		              <td class="table-vab">
		                <a class="button button-primary extbttn" href="https://www.siteguarding.com/en/wordpress-user-access-notification">
		                  Learn More
		                </a>
		              </td>
		            </tr>
		          </tbody>
		        </table>
		        <p>
		          <img class="imgpos_ext" alt="WordPress User Access Notification" src="<?php echo plugins_url('images/wpUserAccessNotification-logo.jpeg', __FILE__); ?>">
		        </p>
		      </div>
		    </div>
		  </div>
		</div>
		
		
		

				
		<?php
	}
	


}






/**
 * Functions
 */


function plgwpavp_UpdateSQLStructure()
{
	global $wpdb;
    
	$table_name = $wpdb->prefix . 'plgwpavp_config';

    $sql = 'ALTER TABLE `'. $table_name . '` CHANGE `var_value` `var_value` LONGTEXT CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;';
    
    $rows = $wpdb->get_results($sql); 

}

function plgwpavp_GetExtraParams($var_name_arr = array())
{
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'plgwpavp_config';
    
    $ppbv_table = $wpdb->get_results("SHOW TABLES LIKE '".$table_name."'" , ARRAY_N);
    if(!isset($ppbv_table[0])) return false;
    
    if (count($var_name_arr) > 0) 
    {
        foreach ($var_name_arr as $k => $v) 
        {
            $var_name_arr[$k] = "'".$v."'";
        }
        $sql_where = "WHERE var_name IN (".implode(",", $var_name_arr).")";
    }
    else $sql_where = '';
    $rows = $wpdb->get_results( 
    	"
    	SELECT *
    	FROM ".$table_name."
    	".$sql_where
    );
    
    $a = array();
    if (count($rows))
    {
        foreach ( $rows as $row ) 
        {
        	$a[trim($row->var_name)] = trim($row->var_value);
        }
    }

    return $a;
}


function plgwpavp_SetExtraParams($data = array())
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'plgwpavp_config';

    if (count($data) == 0) return;   
    
    foreach ($data as $k => $v)
    {
        $tmp = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $table_name . ' WHERE var_name = %s LIMIT 1;', $k ) );
        
        if ($tmp == 0)
        {
            // Insert    
            $wpdb->insert( $table_name, array( 'var_name' => $k, 'var_value' => $v ) ); 
        }
        else {
            // Update
            $data = array('var_value'=>$v);
            $where = array('var_name' => $k);
            $wpdb->update( $table_name, $data, $where );
        }
    } 
}




class SGAntiVirus_WP_tests {

    public static $security_tests = array('ver_check' => array('title' => 'Check if WordPress core is up to date.',
                                 'msg_ok' => 'You are using the latest version of WordPress.',
                                 'msg_bad' => 'You are not using the latest version of WordPress.',
                                 'help' => '<p>Keeping the WordPress core up to date is one of the most important aspects of keeping your site secure. If vulnerabilities are discovered in WordPress and a new version is released to address the issue, the information required to exploit the vulnerability is almost certainly in the public domain. This makes old versions more open to attacks, and is one of the primary reasons you should always keep WordPress up to date.</p>
											<p>Thanks to automatic updates updating is very easy. Just go to <a href="update-core.php">Dashboard - Updates</a> and click "Upgrade". <b>Remember</b> - always backup your files and database before upgrading</p>'),

          'plugins_ver_check' => array('title' => 'Check if plugins are up to date.',
                                 'msg_ok' => 'All plugins are up to date.',
                                 'msg_bad' => 'Some plugins (%s) are outdated.',
                                 'help' => '<p>As with the WordPress core, keeping plugins up to date is one of the most important and easier way to keep your site secure. Since most plugins are free and therefore their code is available to anyone having the latest version will ensure you\'re not prone to attacks based on known vulnerabilities.</p>
                                            <p>If you downloaded a plugin from the official WP repository you can easily check if there are any upgrades available, and upgrade it by opening <a href="update-core.php">Dashboard - Updates</a>. If you bought the plugin from CodeCanyon be sure to check the item\'s page and upgrade manually. <b>Remember</b> - always backup your files and database before upgrading!</p>'),

          'themes_ver_check' => array('title' => 'Check if themes are up to date.',
                                 'msg_ok' => 'All themes are up to date.',
                                 'msg_bad' => 'Some themes (%s) are outdated.',
                                 'help' => '<p>As with the WordPress core, keeping the themes up to date is one of the most important and easier way to keep your site secure. Since most themes are free and therefore their code is available to anyone having the latest version will ensure you\'re not prone to attacks based on known vulnerabilities. Also, having the latest version will ensure your theme is compatible with the latest version of WP.</p>
                                            <p>If you downloaded a theme from the official WP repository you can easily check if there are any upgrades available, and upgrade it by opening <a href="themes.php">Appearance - Themes</a>. If you bought the theme from ThemeForest be sure to check the theme\'s page and upgrade manually. <b>Remember</b> - always backup your files and database before upgrading!</p>'),


          'readme_check' => array('title' => 'Check if <i>readme.html</i> file is accessible via HTTP on the default location.',
                                 'msg_ok' => '<i>readme.html</i> is not accessible at the default location.',
                                 'msg_warning' => 'Unable to determine status of <i>readme.html</i>.',
                                 'msg_bad' => '<i>readme.html</i> is accessible via HTTP on the default location.', 
								 'help' => '<p>As mentioned in the previous test - you should be proud that your site is powered by WordPress but also hide the exact version you are using. <i>readme.html</i> contains WP version info and if left on the default location (WP root) attackers can easily find out your WP version.</p>
											<p>This is a very easy problem to solve. Rename the file to something more unique like "readme-876.html"; delete it; move it to another location or chmod it so that it is not accessible via HTTP.</p>'),

          'anyone_can_register' => array('title' => 'Check if "anyone can register" option is enabled.',
                                 'msg_ok' => '"Anyone can register" option is disabled.',
                                 'msg_bad' => '"Anyone can register" option is enabled.', 
								 'help' => '<p>Unless you are running some kind of community based site this option needs to be disabled. Although it only provides the attacker limited access to your backend it is enough to start exploiting other security issues.</p>
											<p>Go to <a href="options-general.php">Options - General</a> and uncheck the "Membership - anyone can register" checkbox.</p>'),



          'file_editor' => array('title' => 'Check if plugins/themes file editor is enabled.',
                                 'msg_ok' => 'File editor is disabled.',
                                 'msg_bad' => 'File editor is enabled.', 
								 'help' => '<p>Plugins and themes file editor is a very convenient tool because it enables you to make quick changes without the need to use FTP. Unfortunately it is also a security issue because it not only shows PHP source but it also enables the attacker to inject malicious code in your site if he manages to gain access to the admin.</p>
											<p>Editor can easily be disabled by placing the following code in theme&prime;s <i>functions.php</i> file.</p>
											<pre>define(\'DISALLOW_FILE_EDIT\', true);</pre>'),

          'debug_check' => array('title' => 'Check if general debug mode is enabled.',
                                 'msg_ok' => 'General debug mode is disabled.',
                                 'msg_bad' => 'General debug mode is enabled.', 
								 'help' => '<p>Having any kind of debug mode (general WP debug mode in this case) or error reporting mode enabled on a production server is extremely bad. Not only will it slow down your site, confuse your visitors with weird messages it will also give the potential attacker valuable information about your system.</p>
											<p>General WordPress debugging mode is enabled/disabled by a constant defined in <i>wp-config.php</i>. Open that file and look for a line similar to:</p>
											<pre>define(\'WP_DEBUG\', true);</pre>
											<p>Comment it out, delete it or replace with the following to disable debugging:</p>
											<pre>define(\'WP_DEBUG\', false);</pre>
											<p>If your blog still fails on this test after you made the changes it means some plugin is enabling debug mode. Disable plugins one by one to find out which one is doing it.</p>'),

          'install_file_check' => array('title' => 'Check if <i>install.php</i> file is accessible via HTTP on the default location.',
                                 'msg_ok' => '<i>install.php</i> is not accessible on the default location.',
                                 'msg_warning' => 'Unable to determine status of <i>install.php</i> file.',
                                 'msg_bad' => '<i>install.php</i> is accessible via HTTP on the default location.', 
								 'help' => '<p>There have already been a couple of security issues regarding the <i>install.php</i> file. Once you install WP this file becomes useless and there is no reason to keep it in the default location and accessible via HTTP.</p>
											<p>This is a very easy problem to solve. Rename <i>install.php</i> (you will find it in the <i>wp-admin</i> folder) to something more unique like "install-876.php"; delete it; move it to another location or chmod it so it is not accessible via HTTP.</p>'),

          'upgrade_file_check' => array('title' => 'Check if <i>upgrade.php</i> file is accessible via HTTP on the default location.',
                                 'msg_ok' => '<i>upgrade.php</i> is not accessible on the default location.',
                                 'msg_warning' => 'Unable to determine status of <i>upgrade.php</i> file.',
                                 'msg_bad' => '<i>upgrade.php</i> is accessible via HTTP on the default location.', 
								 'help' => '<p>There have already been a couple of security issues regarding this file. Besides the security issue it&prime;s never a good idea to let people run any database upgrade scripts without your knowledge. This is a useful file but it should not be accessible on the default location.</p>
											<p>This is a very easy problem to solve. Rename <i>upgrade.php</i> (you will find it in the <i>wp-admin</i> folder) to something more unique like "upgrade-876.php"; move it to another location or chmod it so it is not accessible via HTTP. Don&prime;t delete it! You may need it later on.</p>'),


          'expose_php_check' => array('title' => 'Check if <i>expose_php</i> PHP directive is turned off.',
                                 'msg_ok' => '<i>expose_php</i> PHP directive is turned off.',
                                 'msg_bad' => '<i>expose_php</i> PHP directive is turned on.',
								 'help' => '<p>It is not wise to disclose the exact PHP version you are using because it makes the job of attacking your site much easier.</p>
											<p>If you have access to php.ini file locate</p>
											<pre>expose_php = on</pre>
											<p>and change it to:</p>
											<pre>expose_php = off</pre>'),

          'user_exists' => array('title' => 'Check if user with username "admin" exists.',
                                 'msg_ok' => 'User "admin" doesn\'t exist.',
                                 'msg_bad' => 'User "admin" exists.',
								 'help' => '<p>If someone tries to guess your username and password or tries a brute-force attack they will most probably start with username "admin". This is the default username used by too many sites and should be removed.</p>
											<p><a href="user-new.php">Create a new user</a> and assign him the "administrator" role. Try not to use usernames like: "root", "god", "null" or similar ones. Once you have the new user created delete the "admin" one and assign all post/pages he may have created to the new user.</p>'),


          'db_table_prefix_check' => array('title' => 'Check if database table prefix is the default one (<i>wp_</i>).',
                                 'msg_ok' => 'Database table prefix is not default.',
                                 'msg_bad' => 'Database table prefix is default.',
								 'help' => '<p>Knowing the names of your database tables can help an attacker dump the table&prime;s data and get to sensitive information like password hashes. Since WP table names are predefined the only way you can change table names is by using a unique prefix. One that&prime;s different from "wp_" or any similar variation such as "wordpress_".</p>
											<p>If you are doing a fresh installation defining a unique table prefix is easy. Open <i>wp-config.php</i> and go to line #61 where the table prefix is defined. Enter something unique like "frog99_" and install WP.</p>
											<p>If you already have WP site running and want to change the table prefix things are a bit more complicated and you should only do the change if you are comfortable doing some changes to your DB data via phpMyAdmin or a similar GUI. Detailed 6-step instruction can be found on <a href="http://tdot-blog.com/wordpress/6-simple-steps-to-change-your-table-prefix-in-wordpress">Tdot blog</a>. <b>Remember</b> - always backup your files and database before making any changes to the database!</p>'),

          'salt_keys_check' => array('title' => 'Check if security keys and salts have proper values.',
                                 'msg_ok' => 'All keys have proper values set.',
                                 'msg_bad' => 'Following keys don\'t have proper values set: %s.',
								 'help' => '<p>Security keys are used to ensure better encryption of information stored in the user&prime;s cookies and hashed passwords. You don&prime;t have to remember these keys. In fact once you set them you will never see them again. Therefore there is no excuse for not setting them properly.</p>
											<p>Security keys (there are eight) are defined in <i>wp-config.php</i> as constants on lines #45-52. They should be as unique and as long as possible. WordPress made a <a href="https://api.wordpress.org/secret-key/1.1/salt/">great script</a> which helps you generate those strings. Please use it! After the script generates strings those 8 lines of code should look something like this:</p>'),

          'db_password_check' => array('title' => 'Test the strength of WordPress database password.',
                                 'msg_ok' => 'Database password is strong enough.',
                                 'msg_bad' => 'Database password is weak (%s).',
								 'help' => '<p>There is no such thing as an "unimportant password"! The same goes for WordPress database password. Although most servers are configured so that the database can&prime;t be accessed from other hosts that doesn&prime;t mean your database passsword should be "12345". Choose a proper password, at least 8 characters long with a combination of letters, numbers and special characters.</p>
											<p>To change the database password open cPanel, Plesk or some other hosting control panel you have. Find the option to change the database password and be sure you make the new password strong enough. If you can\'t find that option or you\'re uncomfortable changing it contact your hosting provider. After the password is changed open wp<i>-config.php</i> and change the password on line #25:</p>
											<pre>/** MySQL database password */
define(\'DB_PASSWORD\', \'YOUR_NEW_DB_PASSWORD_GOES_HERE\');</pre>'),

          'db_debug_check' => array('title' => 'Check if database debug mode is enabled.',
                                 'msg_ok' => 'Database debug mode is disabled.',
                                 'msg_bad' => 'Database debug mode is enabled.',
								 'help' => '<p>Having any kind of debug mode (WP DB debug mode in this case) or error reporting mode enabled on a production server is extremely bad. Not only will it slow down your site, confuse your visitors with weird messages it will also give the potential attacker valuable information about your system.</p>
											<p>WordPress DB debugging mode is enabled with the following command:</p>
											<pre>$wpdb-&gt;show_errors();</pre>
											<p>In most cases this debugging mode is enabled by plugins so the only way to solve the problem is to disable plugins one by one and find out which one enabled debugging.</p>'),

          'script_debug_check' => array('title' => 'Check if JavaScript debug mode is enabled.',
                                 'msg_ok' => 'JavaScript debug mode is disabled.',
                                 'msg_bad' => 'JavaScript debug mode is enabled.',
								 'help' => '<p>Having any kind of debug mode (WP JavaScript debug mode in this case) or error reporting mode enabled on a production server is extremely bad. Not only will it slow down your site, confuse your visitors with weird messages it will also give the potential attacker valuable information about your system.</p>
											<p>WordPress JavaScript debugging mode is enabled/disabled by a constant defined in <i>wp-config.php</i> open your config file and look for a line similar to:</p>
											<pre>define(\'SCRIPT_DEBUG\', true);</pre>
											<p>Comment it out, delete it or replace with the following to disable debugging:</p>
											<pre>define(\'SCRIPT_DEBUG\', false);</pre>
											<p>If your blog still fails on this test after you made the change it means some plugin is enabling debug mode. Disable plugins one by one to find out which one is doing it.</p>'),

          'display_errors_check' => array('title' => 'Check if <i>display_errors</i> PHP directive is turned off.',
                                 'msg_ok' => '<i>display_errors</i> PHP directive is turned off.',
                                 'msg_bad' => '<i>display_errors</i> PHP directive is turned on.',
								 'help' => '<p>Displaying any kind of debug info or similar information is extremely bad. If any PHP errors happen on your site they should be logged in a safe place and not displayed to visitors or potential attackers.</p>
											<p>Open <i>wp-config.php</i> and place the following code just above the <i>require_once</i> function at the end of the file:</p>
											<pre>ini_set(\'display_errors\', 0);</pre>'),


          'config_chmod' => array('title' => 'Check if <i>wp-config.php</i> file has the right permissions (chmod) set.',
                                 'msg_ok' => 'WordPress config file has the right chmod set.',
                                 'msg_warning' => 'Unable to read chmod of <i>wp-config.php</i>.',
                                 'msg_bad' => 'Current <i>wp-config.php</i> chmod (%s) is not ideal and other users on the server can access the file.',
								 'help' => '<p><i>wp-config.php</i> file contains sensitive information (database username and password) in plain text and should not be accessible to anyone except you and WP (or the web server to be more precise).</p>
											<p>What\'s the best chmod for your <i>wp-config.php</i> depends on the way your server is configured but there are some general guidelines you can follow. If you\'re hosting on a Windows based server ignore all of the following.</p>
												<ul>
												<li>try setting chmod to 0400 or 0440 and if the site works normally that\'s the best one to use</li>
												<li>"other" users should have no privileges on the file so set the last octal digit to zero</li>
												<li>"group" users shouldn\'t have any access right as well unless Apache falls under that category, so set group rights to 0 or 4</li>
												</ul>'),

       'register_globals_check' => array('title' => 'Check if <i>register_globals</i> PHP directive is turned off.',
                                 'msg_ok' => '<i>register_globals</i> PHP directive is turned off.',
								 'msg_bad' => '<i>register_globals</i> PHP directive is turned on.',
								 'help' => '<p>This is one of the biggest security issues you can have on your site! If your hosting company has this this directive enabled by default switch to another company immediately! <a href="http://php.net/manual/en/security.globals.php">PHP manual</a> has more info why this is so dangerous.</p>
											<p>If you have access to php.ini file locate</p>
											<pre>register_globals = on</pre>
											<p>and change it to:</p>
											<pre>register_globals = off</pre>
											<p>Alternatively open <i>.htaccess</i> and put this directive into it:</p>
											<pre>php_flag register_globals off</pre>
											<p>If you\'re still unable to disable <i>register_globals</i> contact a security professional immediately!</p>'),

       'safe_mode_check' => array('title' => 'Check if PHP safe mode is disabled.',
                                 'msg_ok' => 'Safe mode is disabled.',
                                 'msg_bad' => 'Safe mode is enabled.',
								 'help' => '<p>PHP safe mode is an attempt to solve the shared-server security problem. It is architecturally incorrect to try to solve this problem at the PHP level, but since the alternatives at the web server and OS levels aren\'t very realistic, many people, especially ISP\'s, use safe mode for now. If your hosting company still uses safe mode it might be a good idea to switch. This feature is deprecated in new version of PHP (5.3).</p>
											<p>If you have access to php.ini file locate</p>
											<pre>safe_mode = on</pre>
											<p>and change it to:</p>
											<pre>safe_mode = off</pre>'),

       'allow_url_include_check' => array('title' => 'Check if <i>allow_url_include</i> PHP directive is turned off.',
                                 'msg_ok' => '<i>allow_url_include</i> PHP directive is turned off.',
                                 'msg_bad' => '<i>allow_url_include</i> PHP directive is turned on.',
								 'help' => '<p>Having this PHP directive will leave your site exposed to cross-site attacks (XSS). There\'s absolutely no valid reason to enable this directive and using any PHP code that requires it is very risky.</p>
											<p>If you have access to php.ini file locate</p>
											<pre>allow_url_include = on</pre>
											<p>and change it to:</p>
											<pre>allow_url_include = off</pre>
											<p>If you\'re still unable to disable <i>allow_url_include</i> contact a security professional immediately!</p>'),

       'uploads_browsable' => array('title' => 'Check if <i>uploads</i> folder is browsable by browsers.',
                                 'msg_ok' => 'Uploads folder is not browsable.',
                                 'msg_warning' => 'Unable to determine status of uploads folder.',
                                 'msg_bad' => '<a href="%s" target="_blank">Uploads folder</a> is browsable.',
								 'help' => '<p>Allowing anyone to view all files in the <a href="echo $tmp[\'baseurl\']" target="_blank">uploads folder</a> just by point the brower to it will allow them to easily download all your uploaded files. It is a security and a copyright issue.</p>
											<p>To fix the problem open <i>.htaccess</i> and add this directive into it:</p>
											<pre>Options -Indexes</pre>'),

       'id1_user_check' => array('title' => 'Test if user with ID "1" exists.',
                                 'msg_ok' => 'Such user does not exist.',
                                 'msg_bad' => 'User with ID "1" exists.',
								 'help' => '<p>Although technically not a security issue having a user (which is in 99% cases an admin) with the ID 1 can help an attacker in some circumstances.</p>
											<p>Fixing is easy; create a new user with the same privileges. Then delete the old one with ID 1 and tell WP to transfer all of his content to the new user.</p>'),

       'mysql_external' => array('title' => 'Check if MySQL server is connectable from outside with the WP user.',
                                 'msg_ok' => 'No, you can only connect to the MySQL from localhost.',
                                 'msg_warning' => 'Test results are not conclusive for MySQL user %s.',
                                 'msg_bad' => 'You can connect to the MySQL server from any host.',
								 'help' => '<p>Since MySQL username and password are written in plain-text in <i>wp-config.php</i> it is advisable not to allow any client to use that account unless he is connecting to MySQL from your server (localhost). Allowing him to connect from any host will make some attacks easier to bad people.</p>
											<p>Fixing this issue involves changing the MySQL user or server config and it is not something that can be described in a few words so we advise asking someone to fix it for you. If you are really eager to do it we suggest creating a new MySQL user and under "hostname" enter "localhost". Set other properties such as username and password to your own liking and, of course, update <i>wp-config.php</i> with the new user details.</p>')); // $security_tests



   function captcha() {

     $return = array();

     $check = get_option(WPSL_FIXIT_KEY);



     if ($check['captcha']) {

       $return['status'] = 10;

     } else {

       $return['status'] = 0;

     }



     return $return;

   } // captcha



   // check if user with DB ID 1 exists

   function id1_user_check() {

     $return = array();



     $check = get_userdata(1);

     if ($check) {

       $return['status'] = 0;

     } else {

       $return['status'] = 10;

     }



     return $return;

   } // id1_user_check



   // check if wp-config is present on the default location

   function config_location() {

     $return = array();



     $check = @file_exists(ABSPATH . 'wp-config.php');

     if ($check) {

       $return['status'] = 0;

     } else {

       $return['status'] = 10;

     }



     return $return;

   } // config_location



   // check if the WP MySQL user can connect from an external host

   function mysql_external() {

     $return = array();

     global $wpdb;



     $check = $wpdb->get_var('SELECT CURRENT_USER()');

     if (strpos($check,'@%') !== false) {

       $return['status'] = 0;

     } elseif (strpos($check, '@127.0.0.1') !== false || stripos($check, '@localhost') !== false) {

       $return['status'] = 10;

     } else {

       $return['status'] = 5;

       $return['msg'] = $check;

     }



     return $return;

   } // mysql_external



   // check if WLW link ispresent in header

   function wlw_meta() {

    $return = array();



    if (!class_exists('WP_Http')) {

      require( ABSPATH . WPINC . '/class-http.php' );

    }



    $http = new WP_Http();

    $response = (array) $http->request(get_bloginfo('wpurl'));

    $html = $response['body'];



    if ($html) {

      $return['status'] = 10;

      // extract content in <head> tags

      $start = strpos($html, '<head');

      $len = strpos($html, 'head>', $start + strlen('<head'));

      $html = substr($html, $start, $len - $start + strlen('head>'));

      // find all link tags

      preg_match_all('#<link([^>]*)>#si', $html, $matches);

      $meta_tags = $matches[0];



      foreach ($meta_tags as $meta_tag) {

        if (stripos($meta_tag, 'wlwmanifest') !== false) {

          $return['status'] = 0;

          break;

        }

      }

    } else {

      // error

      $return['status'] = 5;

    }



    return $return;

  } // wlw_meta





  // check if RPC link ispresent in header

   function rpc_meta() {

    $return = array();



    if (!class_exists('WP_Http')) {

      require( ABSPATH . WPINC . '/class-http.php' );

    }



    $http = new WP_Http();

    $response = (array) $http->request(get_bloginfo('wpurl'));

    $html = $response['body'];



    if ($html) {

      $return['status'] = 10;

      // extract content in <head> tags

      $start = strpos($html, '<head');

      $len = strpos($html, 'head>', $start + strlen('<head'));

      $html = substr($html, $start, $len - $start + strlen('head>'));

      // find all link tags

      preg_match_all('#<link([^>]*)>#si', $html, $matches);

      $meta_tags = $matches[0];



      foreach ($meta_tags as $meta_tag) {

        if (stripos($meta_tag, 'EditURI') !== false) {

          $return['status'] = 0;

          break;

        }

      }

    } else {

      // error

      $return['status'] = 5;

    }



    return $return;

  } // rpc_meta



   // check if register_globals is off

   function register_globals_check() {

    $return = array();



    $check = (bool) ini_get('register_globals');

    if ($check) {

      $return['status'] = 0;

    } else {

      $return['status'] = 10;

    }



    return $return;

   } // register_globals_check



   // check if display_errors is off

   function display_errors_check() {

    $return = array();



    $check = (bool) ini_get('display_errors');

    if ($check) {

      $return['status'] = 0;

    } else {

      $return['status'] = 10;

    }



    return $return;

   } // display_errors_check



   // is theme/plugin editor disabled?

   function file_editor() {

    $return = array();



    if (defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT) {

      $return['status'] = 10;

    } else {

      $return['status'] = 0;

    }



    return $return;

   } // file_editor



   // check if expose_php is off

   function expose_php_check() {

    $return = array();



    $check = (bool) ini_get('expose_php');

    if ($check) {

      $return['status'] = 0;

    } else {

      $return['status'] = 10;

    }



    return $return;

   } // expose_php_check





   // check if allow_url_include is off

   function allow_url_include_check() {

    $return = array();



    $check = (bool) ini_get('allow_url_include');

    if ($check) {

      $return['status'] = 0;

    } else {

      $return['status'] = 10;

    }



    return $return;

   } // allow_url_include_check





   // check if safe mode is off

   function safe_mode_check() {

    $return = array();



    $check = (bool) ini_get('safe_mode');

    if ($check) {

      $return['status'] = 0;

    } else {

      $return['status'] = 10;

    }



    return $return;

   } // safe_mode_check



   // check if anyone can register on the site

   function anyone_can_register() {

     $return = array();

     $test = get_option('users_can_register');



     if ($test) {

       $return['status'] = 0;

     } else {

       $return['status'] = 10;

     }



     return $return;

   } // anyone_can_register





  // check WP version

  function ver_check() {

    $return = array();



    if (!function_exists('get_preferred_from_update_core') ) {

      require_once(ABSPATH . 'wp-admin/includes/update.php');

    }



    // get version

    wp_version_check();

    $latest_core_update = get_preferred_from_update_core();



    if (isset($latest_core_update->response) && ($latest_core_update->response == 'upgrade') ){

      $return['status'] = 0;

    } else {

      $return['status'] = 10;

    }



    return $return;

  } // ver_check





  // check if certain username exists

  function user_exists($username = 'admin') {

    $return = array();



    // Define the function

    require_once(ABSPATH . WPINC . '/registration.php');



    if (username_exists($username) ) {

      $return['status'] = 0;

    } else {

      $return['status'] = 10;

    }



    return $return;

  } // user_exists





  // check if plugins are up to date

  function plugins_ver_check() {

    $return = array();



    //Get the current update info

    $current = get_site_transient('update_plugins');



    if (!is_object($current)) {

      $current = new stdClass;

    }



    set_site_transient('update_plugins', $current);



    // run the internal plugin update check

    wp_update_plugins();



    $current = get_site_transient('update_plugins');



    if (isset($current->response) && is_array($current->response) ) {

      $plugin_update_cnt = count($current->response);

    } else {

      $plugin_update_cnt = 0;

    }



    if($plugin_update_cnt > 0){

      $return['status'] = 0;

      $return['msg'] = sizeof($current->response);

    } else {

      $return['status'] = 10;

    }



    return $return;

  } // plugins_vec_check





  // check themes versions

  function themes_ver_check() {

    $return = array();



    $current = get_site_transient('update_themes');



    if (!is_object($current)){

      $current = new stdClass;

    }



    set_site_transient('update_themes', $current);

    wp_update_themes();



    $current = get_site_transient('update_themes');



    if (isset($current->response) && is_array($current->response)) {

      $theme_update_cnt = count($current->response);

    } else {

      $theme_update_cnt = 0;

    }



    if($theme_update_cnt > 0){

      $return['status'] = 0;

      $return['msg'] = sizeof($current->response);

    } else {

      $return['status'] = 10;

    }



    return $return;

  } // themes_ver_check





  // check DB table prefix

  function db_table_prefix_check() {

    global $wpdb;

    $return = array();



    if ($wpdb->prefix == 'wp_' || $wpdb->prefix == 'wordpress_' || $wpdb->prefix == 'wp3_') {

      $return['status'] = 0;

    } else {

      $return['status'] = 10;

    }



    return $return;

  } // db_table_prefix_check





  // check if global WP debugging is enabled

  function debug_check() {

    $return = array();



    if (defined('WP_DEBUG') && WP_DEBUG) {

      $return['status'] = 0;

    } else {

      $return['status'] = 10;

    }



    return $return;

  } // debug_check





  // check if global WP JS debugging is enabled

  function script_debug_check() {

    $return = array();



    if (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) {

      $return['status'] = 0;

    } else {

      $return['status'] = 10;

    }



    return $return;

  } // script_debug_check





  // check if DB debugging is enabled

  function db_debug_check() {

    global $wpdb;

    $return = array();



    if ($wpdb->show_errors == true) {

      $return['status'] = 0;

    } else {

      $return['status'] = 10;

    }



    return $return;

  } // db_debug_check





  // does readme.html exist?

  function readme_check() {

    $return = array();

    $url = get_bloginfo('wpurl') . '/readme.html?rnd=' . rand();

    $response = wp_remote_get($url);



    if(is_wp_error($response)) {

      $return['status'] = 5;

    } elseif ($response['response']['code'] == 200) {

      $return['status'] = 0;

    } else {

      $return['status'] = 10;

    }



    return $return;

  } // readme_check





  // does WP install.php file exist?

  function install_file_check() {

    $return = array();

    $url = get_bloginfo('wpurl') . '/wp-admin/install.php?rnd=' . rand();

    $response = wp_remote_get($url);



    if(is_wp_error($response)) {

      $return['status'] = 5;

    } elseif ($response['response']['code'] == 200) {

      $return['status'] = 0;

    } else {

      $return['status'] = 10;

    }



    return $return;

  } // install_file_check





  // does WP install.php file exist?

  function upgrade_file_check() {

    $return = array();

    $url = get_bloginfo('wpurl') . '/wp-admin/upgrade.php?rnd=' . rand();

    $response = wp_remote_get($url);



    if(is_wp_error($response)) {

      $return['status'] = 5;

    } elseif ($response['response']['code'] == 200) {

      $return['status'] = 0;

    } else {

      $return['status'] = 10;

    }



    return $return;

  } // upgrade_file_check





  // check if wp-config.php has the right chmod

  function config_chmod() {

    $return = array();



    if (file_exists(ABSPATH . '/wp-config.php')) {

      $mode = substr(sprintf('%o', @fileperms(ABSPATH . '/wp-config.php')), -4);

    } else {

      $mode = substr(sprintf('%o', @fileperms(ABSPATH . '/../wp-config.php')), -4);

    }



    if (!$mode) {

      $return['status'] = 5;

    } elseif (substr($mode, -1) != 0) {

      $return['status'] = 0;

      $return['msg'] = $mode;

    } else {

      $return['status'] = 10;

    }



    return $return;

  } // config_chmod





  // check for unnecessary information on failed login

  function check_failed_login_info() {

    $return = array();



    $params = array('log' => 'sn-test_3453344355',

                    'pwd' => 'sn-test_2344323335');



    if (!class_exists('WP_Http')) {

      require( ABSPATH . WPINC . '/class-http.php' );

    }



    $http = new WP_Http();

    $response = (array) $http->request(get_bloginfo('wpurl') . '/wp-login.php', array('method' => 'POST', 'body' => $params));



    if (stripos($response['body'], 'invalid username') !== false){

      $return['status'] = 0;

    } else {

      $return['status'] = 10;

    }



    return $return;

  } // check_failed_login_info







  // check if php headers contain php version

  function php_headers() {

    $return = array();



    if (!class_exists('WP_Http')) {

      require( ABSPATH . WPINC . '/class-http.php' );

    }



    $http = new WP_Http();

    $response = (array) $http->request(get_bloginfo('siteurl'));



    if((isset($response['headers']['server']) && stripos($response['headers']['server'], phpversion()) !== false) || (isset($response['headers']['x-powered-by']) && stripos($response['headers']['x-powered-by'], phpversion()) !== false)) {

      $return['status'] = 0;

    } else {

      $return['status'] = 10;

      $return['msg'] = self::$security_tests[__FUNCTION__]['msg_ok'];

    }



    return $return;

  } // php_headers





  // check for WP version in meta tags

  function wp_header_meta() {

    $return = array();



    if (!class_exists('WP_Http')) {

      require( ABSPATH . WPINC . '/class-http.php' );

    }



    $http = new WP_Http();

    $response = (array) $http->request(get_bloginfo('wpurl'));

    $html = $response['body'];



    if ($html) {

      $return['status'] = 10;

      // extract content in <head> tags

      $start = strpos($html, '<head');

      $len = strpos($html, 'head>', $start + strlen('<head'));

      $html = substr($html, $start, $len - $start + strlen('head>'));

      // find all Meta Tags

      preg_match_all('#<meta([^>]*)>#si', $html, $matches);

      $meta_tags = $matches[0];



      foreach ($meta_tags as $meta_tag) {

        if (stripos($meta_tag, 'generator') !== false &&

            stripos($meta_tag, get_bloginfo('version')) !== false) {

          $return['status'] = 0;

          break;

        }

      }

    } else {

      // error

      $return['status'] = 5;

    }



    return $return;

  } // wp_header_meta





  // compare WP Blog Url with WP Site Url

  function blog_site_url_check() {

    $return = array();



    $siteurl = get_bloginfo('siteurl');

    $wpurl = get_bloginfo('wpurl');



    if ($siteurl == $wpurl) {

      $return['status'] = 0;

    } else {

      $return['status'] = 10;

    }



    return $return;

  } // blog_site_url_check





  // brute force attack on password

  function dictionary_attack($password) {

    $dictionary = file(dirname(__FILE__)."/pass-dictionary.txt", FILE_IGNORE_NEW_LINES);



    if (in_array($password, $dictionary)) {

      return true;

    } else {

      return false;

    }

  } // dictionary_attack





  // check database password

  function db_password_check() {

    $return = array();

    $password = DB_PASSWORD;



    if (empty($password)) {

      $return['status'] = 0;

      $return['msg'] = 'password is empty';

    } elseif (self::dictionary_attack($password)) {

      $return['status'] = 0;

      $return['msg'] = 'password is a simple word from the dictionary';

    } elseif (strlen($password) < 6) {

      $return['status'] = 0;

      $return['msg'] = 'password length is only ' . strlen($password) . ' chars';

    } elseif (sizeof(count_chars($password, 1)) < 5) {

      $return['status'] = 0;

      $return['msg'] = 'password is too simple';

    } else {

      $return['status'] = 10;

      $return['msg'] = 'password is ok';

    }



    return $return;

  } // db_password_check





  // unique config keys check

  function salt_keys_check() {

    $return = array();

    $ok = true;

    $keys = array('AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY',

                  'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT');



    foreach ($keys as $key) {

      $constant = @constant($key);

      if (empty($constant) || trim($constant) == 'put your unique phrase here' || strlen($constant) < 50) {

        $bad_keys[] = $key;

        $ok = false;

      }

    } // foreach



    if ($ok == true) {

      $return['status'] = 10;

    } else {

      $return['status'] = 0;

      $return['msg'] = implode(', ', $bad_keys);

    }



    return $return;

  } // salt_keys_check





  function uploads_browsable() {

    $return = array();

    $upload_dir = wp_upload_dir();



    $args = array('method' => 'GET', 'timeout' => 5, 'redirection' => 0,

                  'httpversion' => 1.0, 'blocking' => true, 'headers' => array(), 'body' => null, 'cookies' => array());

    $response = wp_remote_get(rtrim($upload_dir['baseurl'], '/') . '/?nocache=' . rand(), $args);



    if (is_wp_error($response)) {

      $return['status'] = 5;

      $return['msg'] = $upload_dir['baseurl'] . '/';

    } elseif ($response['response']['code'] == '200' && stripos($response['body'], 'index') !== false) {

      $return['status'] = 0;

      $return['msg'] = $upload_dir['baseurl'] . '/';

    } else {

      $return['status'] = 10;

    }



    return $return;

  }
    
}


class SGAntiVirus {
    
    public static $country_list = array(
        "" => "",   // 
        "AF" => "Afghanistan",   // Afghanistan
        "AL" => "Albania",   // Albania
        "DZ" => "Algeria",   // Algeria
        "AS" => "American Samoa",   // American Samoa
        "AD" => "Andorra",   // Andorra 
        "AO" => "Angola",   // Angola
        "AI" => "Anguilla",   // Anguilla
        "AQ" => "Antarctica",   // Antarctica
        "AG" => "Antigua and Barbuda",   // Antigua and Barbuda
        "AR" => "Argentina",   // Argentina
        "AM" => "Armenia",   // Armenia
        "AW" => "Aruba",   // Aruba 
        "AU" => "Australia",   // Australia 
        "AT" => "Austria",   // Austria
        "AZ" => "Azerbaijan",   // Azerbaijan
        "BS" => "Bahamas",   // Bahamas
        "BH" => "Bahrain",   // Bahrain 
        "BD" => "Bangladesh",   // Bangladesh
        "BB" => "Barbados",   // Barbados 
        "BY" => "Belarus",   // Belarus 
        "BE" => "Belgium",   // Belgium
        "BZ" => "Belize",   // Belize
        "BJ" => "Benin",   // Benin
        "BM" => "Bermuda",   // Bermuda
        "BT" => "Bhutan",   // Bhutan
        "BO" => "Bolivia",   // Bolivia
        "BA" => "Bosnia and Herzegovina",   // Bosnia and Herzegovina
        "BW" => "Botswana",   // Botswana
        "BV" => "Bouvet Island",   // Bouvet Island
        "BR" => "Brazil",   // Brazil
        "IO" => "British Indian Ocean Territory",   // British Indian Ocean Territory
        "VG" => "British Virgin Islands",   // British Virgin Islands,
        "BN" => "Brunei Darussalam",   // Brunei Darussalam
        "BG" => "Bulgaria",   // Bulgaria
        "BF" => "Burkina Faso",   // Burkina Faso
        "BI" => "Burundi",   // Burundi
        "KH" => "Cambodia",   // Cambodia 
        "CM" => "Cameroon",   // Cameroon
        "CA" => "Canada",   // Canada 
        "CV" => "Cape Verde",   // Cape Verde
        "KY" => "Cayman Islands",   // Cayman Islands
        "CF" => "Central African Republic",   // Central African Republic
        "TD" => "Chad",   // Chad
        "CL" => "Chile",   // Chile
        "CN" => "China",   // China
        "CX" => "Christmas Island",   // Christmas Island
        "CC" => "Cocos (Keeling Islands)",   // Cocos (Keeling Islands)
        "CO" => "Colombia",   // Colombia
        "KM" => "Comoros",   // Comoros
        "CG" => "Congo",   // Congo 
        "CK" => "Cook Islands",   // Cook Islands
        "CR" => "Costa Rica",   // Costa Rica 
        "HR" => "Croatia (Hrvatska)",   // Croatia (Hrvatska
        "CY" => "Cyprus",   // Cyprus
        "CZ" => "Czech Republic",   // Czech Republic
        "CG" => "Democratic Republic of Congo",   // Democratic Republic of Congo,
        "DK" => "Denmark",   // Denmark
        "DJ" => "Djibouti",   // Djibouti
        "DM" => "Dominica",   // Dominica
        "DO" => "Dominican Republic",   // Dominican Republic
        "TP" => "East Timor",   // East Timor
        "EC" => "Ecuador",   // Ecuador
        "EG" => "Egypt",   // Egypt 
        "SV" => "El Salvador",   // El Salvador 
        "GQ" => "Equatorial Guinea",   // Equatorial Guinea
        "ER" => "Eritrea",   // Eritrea 
        "EE" => "Estonia",   // Estonia 
        "ET" => "Ethiopia",   // Ethiopia
        "FK" => "Falkland Islands (Malvinas)",   // Falkland Islands (Malvinas)
        "FO" => "Faroe Islands",   // Faroe Islands 
        "FM" => "Federated States of Micronesia",   // Federated States of Micronesia,
        "FJ" => "Fiji",   // Fiji
        "FI" => "Finland",   // Finland
        "FR" => "France",   // France
        "GF" => "French Guiana",   // French Guiana
        "PF" => "French Polynesia",   // French Polynesia
        "TF" => "French Southern Territories",   // French Southern Territories
        "GA" => "Gabon",   // Gabon
        "GM" => "Gambia",   // Gambia
        "GE" => "Georgia",   // Georgia
        "DE" => "Germany",   // Germany
        "GH" => "Ghana",   // Ghana
        "GI" => "Gibraltar",   // Gibraltar
        "GR" => "Greece",   // Greece
        "GL" => "Greenland",   // Greenland
        "GD" => "Grenada",   // Grenada 
        "GP" => "Guadeloupe",   // Guadeloupe
        "GU" => "Guam",   // Guam 
        "GT" => "Guatemala",   // Guatemala
        "GN" => "Guinea",   // Guinea
        "GW" => "Guinea-Bissau",   // Guinea-Bissau
        "GY" => "Guyana",   // Guyana
        "HT" => "Haiti",   // Haiti
        "HM" => "Heard and McDonald Islands",   // Heard and McDonald Islands
        "HN" => "Honduras",   // Honduras
        "HK" => "Hong Kong",   // Hong Kong
        "HU" => "Hungary",   // Hungary
        "IS" => "Iceland",   // Iceland
        "IN" => "India",   // India
        "ID" => "Indonesia",   // Indonesia
        "IR" => "Iran",   // Iran
        "IQ" => "Iraq",   // Iraq
        "IE" => "Ireland",   // Ireland
        "IL" => "Israel",   // Israel
        "IT" => "Italy",   // Italy
        "CI" => "Ivory Coast",   // Ivory Coast,
        "JM" => "Jamaica",   // Jamaica
        "JP" => "Japan",   // Japan 
        "JO" => "Jordan",   // Jordan 
        "KZ" => "Kazakhstan",   // Kazakhstan
        "KE" => "Kenya",   // Kenya 
        "KI" => "Kiribati",   // Kiribati 
        "KW" => "Kuwait",   // Kuwait
        "KG" => "Kuwait",   // Kyrgyzstan
        "LA" => "Laos",   // Laos
        "LV" => "Latvia",   // Latvia
        "LB" => "Lebanon",   // Lebanon
        "LS" => "Lesotho",   // Lesotho
        "LR" => "Liberia",   // Liberia 
        "LY" => "Libya",   // Libya
        "LI" => "Liechtenstein",   // Liechtenstein
        "LT" => "Lithuania",   // Lithuania
        "LU" => "Luxembourg",   // Luxembourg 
        "MO" => "Macau",   // Macau
        "MK" => "Macedonia",   // Macedonia
        "MG" => "Madagascar",   // Madagascar
        "MW" => "Malawi",   // Malawi
        "MY" => "Malaysia",   // Malaysia
        "MV" => "Maldives",   // Maldives
        "ML" => "Mali",   // Mali
        "MT" => "Malta",   // Malta
        "MH" => "Marshall Islands",   // Marshall Islands
        "MQ" => "Martinique",   // Martinique
        "MR" => "Mauritania",   // Mauritania
        "MU" => "Mauritius",   // Mauritius
        "YT" => "Mayotte",   // Mayotte
        "MX" => "Mexico",   // Mexico
        "MD" => "Moldova",   // Moldova
        "MC" => "Monaco",   // Monaco
        "MN" => "Mongolia",   // Mongolia
        "MS" => "Montserrat",   // Montserrat
        "MA" => "Morocco",   // Morocco
        "MZ" => "Mozambique",   // Mozambique
        "MM" => "Myanmar",   // Myanmar
        "NA" => "Namibia",   // Namibia
        "NR" => "Nauru",   // Nauru
        "NP" => "Nepal",   // Nepal
        "NL" => "Netherlands",   // Netherlands
        "AN" => "Netherlands Antilles",   // Netherlands Antilles
        "NC" => "New Caledonia",   // New Caledonia
        "NZ" => "New Zealand",   // New Zealand
        "NI" => "Nicaragua",   // Nicaragua
        "NE" => "Nicaragua",   // Niger
        "NG" => "Nigeria",   // Nigeria
        "NU" => "Niue",   // Niue
        "NF" => "Norfolk Island",   // Norfolk Island
        "KP" => "Korea (North)",   // Korea (North)
        "MP" => "Northern Mariana Islands",   // Northern Mariana Islands
        "NO" => "Norway",   // Norway
        "OM" => "Oman",   // Oman
        "PK" => "Pakistan",   // Pakistan
        "PW" => "Palau",   // Palau
        "PA" => "Panama",   // Panama
        "PG" => "Papua New Guinea",   // Papua New Guinea
        "PY" => "Paraguay",   // Paraguay
        "PE" => "Peru",   // Peru
        "PH" => "Philippines",   // Philippines
        "PN" => "Pitcairn",   // Pitcairn
        "PL" => "Poland",   // Poland
        "PT" => "Portugal",   // Portugal
        "PR" => "Puerto Rico",   // Puerto Rico
        "QA" => "Qatar",   // Qatar
        "RE" => "Reunion",   // Reunion
        "RO" => "Romania",   // Romania
        "RU" => "Russian Federation",   // Russian Federation
        "RW" => "Rwanda",   // Rwanda
        "SH" => "Saint Helena and Dependencies",   // Saint Helena and Dependencies,
        "KN" => "Saint Kitts and Nevis",   // Saint Kitts and Nevis
        "LC" => "Saint Lucia",   // Saint Lucia
        "VC" => "Saint Vincent and The Grenadines",   // Saint Vincent and The Grenadines
        "VC" => "Saint Vincent and the Grenadines",   // Saint Vincent and the Grenadines,
        "WS" => "Samoa",   // Samoa
        "SM" => "San Marino",   // San Marino
        "ST" => "Sao Tome and Principe",   // Sao Tome and Principe 
        "SA" => "Saudi Arabia",   // Saudi Arabia
        "SN" => "Senegal",   // Senegal
        "SC" => "Seychelles",   // Seychelles
        "SL" => "Sierra Leone",   // Sierra Leone
        "SG" => "Singapore",   // Singapore
        "SK" => "Slovak Republic",   // Slovak Republic
        "SI" => "Slovenia",   // Slovenia
        "SB" => "Solomon Islands",   // Solomon Islands
        "SO" => "Somalia",   // Somalia
        "ZA" => "South Africa",   // South Africa
        "GS" => "S. Georgia and S. Sandwich Isls.",   // S. Georgia and S. Sandwich Isls.
        "KR" => "South Korea",   // South Korea,
        "ES" => "Spain",   // Spain
        "LK" => "Sri Lanka",   // Sri Lanka
        "SR" => "Suriname",   // Suriname
        "SJ" => "Svalbard and Jan Mayen Islands",   // Svalbard and Jan Mayen Islands
        "SZ" => "Swaziland",   // Swaziland
        "SE" => "Sweden",   // Sweden
        "CH" => "Switzerland",   // Switzerland
        "SY" => "Syria",   // Syria
        "TW" => "Taiwan",   // Taiwan
        "TJ" => "Tajikistan",   // Tajikistan
        "TZ" => "Tanzania",   // Tanzania
        "TH" => "Thailand",   // Thailand
        "TG" => "Togo",   // Togo
        "TK" => "Tokelau",   // Tokelau
        "TO" => "Tonga",   // Tonga
        "TT" => "Trinidad and Tobago",   // Trinidad and Tobago
        "TN" => "Tunisia",   // Tunisia
        "TR" => "Turkey",   // Turkey
        "TM" => "Turkmenistan",   // Turkmenistan
        "TC" => "Turks and Caicos Islands",   // Turks and Caicos Islands
        "TV" => "Tuvalu",   // Tuvalu
        "UG" => "Uganda",   // Uganda
        "UA" => "Ukraine",   // Ukraine
        "AE" => "United Arab Emirates",   // United Arab Emirates
        "UK" => "United Kingdom",   // United Kingdom
        "US" => "United States",   // United States
        "UM" => "US Minor Outlying Islands",   // US Minor Outlying Islands
        "UY" => "Uruguay",   // Uruguay
        "VI" => "US Virgin Islands",   // US Virgin Islands,
        "UZ" => "Uzbekistan",   // Uzbekistan
        "VU" => "Vanuatu",   // Vanuatu
        "VA" => "Vatican City State (Holy See)",   // Vatican City State (Holy See)
        "VE" => "Venezuela",   // Venezuela
        "VN" => "Viet Nam",   // Viet Nam
        "WF" => "Wallis and Futuna Islands",   // Wallis and Futuna Islands
        "EH" => "Western Sahara",   // Western Sahara
        "YE" => "Yemen",   // Yemen
        "ZM" => "Zambia",   // Zambia
        "ZW" => "Zimbabwe",   // Zimbabwe
        "CU" => "Cuba",   // Cuba,
        "IR" => "Iran",   // Iran,
    );
    
    public static function InstalltandAloneVersion()
    {
        $file_antivirus = dirname(dirname(dirname(dirname(__FILE__)))).DIRSEP.'webanalyze'.DIRSEP.'antivirus.php';
        $folder_webanalyze = dirname($file_antivirus);
        
        // Create folder
        if (!file_exists($folder_webanalyze))
        {
            mkdir($folder_webanalyze);
        }
        
        // Copy antivirus
        $status = copy(dirname(__FILE__).DIRSEP.'antivirus.php', $file_antivirus);
        if ($status === false)
        {
            chmod ( $folder_webanalyze , 0777 ); 
            $status = copy(dirname(__FILE__).DIRSEP.'antivirus.php', $file_antivirus);
        }
        
        // Create config
        $params = plgwpavp_GetExtraParams(array('access_key'));
        $fp = fopen($folder_webanalyze.DIRSEP.'antivirus_config.php', 'w');
        $config_txt = '<?php
define("ACCESS_KEY", "'.$params['access_key'].'");
?>';
        fwrite($fp, $config_txt);
        fclose($fp);
        
        // Create empty index.html
        $fp = fopen($folder_webanalyze.DIRSEP.'index.html', 'w');
        fwrite($fp, '<html><body bgcolor="#FFFFFF"></body></html>');
        fclose($fp);
    }
    
    public static function CheckStandAloneVersion()
    {
        $file_antivirus = dirname(dirname(dirname(dirname(__FILE__)))).DIRSEP.'webanalyze'.DIRSEP.'antivirus.php';
        $folder_webanalyze = dirname($file_antivirus);
        $file_antivirus_config = $folder_webanalyze.DIRSEP.'antivirus_config.php';
        
        if (file_exists($file_antivirus) && file_exists($file_antivirus_config)) return true;
        else return false;
    }
    
    public static function toDateInterval($seconds) 
    {
        return date_create('@' . (($now = time()) + $seconds))->diff(date_create('@' . $now));
    }
    
    public static function OrganizeSessionData($sessions, $limit = 5)
    {
        if (count($sessions) > $limit)
        {
            $a = array();
            foreach ($sessions as $ip => $row)
            {
                $a[$row['time']] = $ip;
            }
            
            ksort($a);
            $a = array_slice($a, -$limit);
            
            foreach ($a as $time => $ip)
            {
                unset($sessions[$ip]);
            }
        }
        
        return $sessions;
    }
    
    
    public static function CalculateSessionMD5($data)
    {
        $IPs = array();
        if (count($data))
        {
            foreach ($data as $ip => $session_data)
            {
                $IPs[] = $ip;
            }
            
            sort($IPs);
            $a = implode(",", $IPs);
            
            return md5($a);
        }
        else return false;
    }
    
    
    public static function RecognizeCountryCode($ip)
    {
		$end = "\015\012";
		$timeout = 5;
		$server = "whois.ripe.net";
		$port = 43;
		
		$ip_range_from = 0;
		$ip_range_till = 0;
		$ip_country = '';
		
		$sock = fsockopen($server, $port, $errno, $errstr, $timeout);
		
		
		if(!$sock)
		{
			//echo "Connection failed!";
			return false;
		}
		else
		{
			$t = 0;
			
			fputs($sock, $ip.$end);
			
			while ($buff = fgets($sock,1024))
			{
				if ( substr($buff,0,8) == 'inetnum:')
				{
					$inetnum = trim(str_replace("inetnum:", "", $buff));
					$inetnum_a = explode("-", $inetnum);
					$inetnum_a[0] = trim($inetnum_a[0]);
					$inetnum_a[1] = trim($inetnum_a[1]);
				
					$tmp = explode(".", $inetnum_a[0]);
					$ip_num_1 = $tmp[0]*256*256*256 + $tmp[1]*256*256 + $tmp[2]*256 + $tmp[3];
					if ($ip_num_1 == 0) break;
					$ip_range_from = $ip_num_1;
					
					$tmp = explode(".", $inetnum_a[1]);
					$ip_num_2 = $tmp[0]*256*256*256 + $tmp[1]*256*256 + $tmp[2]*256 + $tmp[3];
					if ($ip_num_2 == 0) break;
					$ip_range_till = $ip_num_2;
				}
				
				if ( substr($buff,0,8) == 'country:')
				{
					$country = trim(str_replace("country:", "", $buff));
					if ($country == '') break;
					$ip_country = strtoupper($country);
					break;
				}
			}
			
			fclose($sock);
			

			if ($ip_range_from == 0 || $ip_range_till == 0 || $ip_country == '') return false;
			
			return $ip_country;
		
		}
		
		return false;
    }
    
    
	public static function CreateFile($file, $content)
    {
        $fp = fopen($file, 'w');
        fwrite($fp, $content);
        fclose($fp);
    }
	
	public static function DownloadRemoteFile($link, $file)
	{
		$dst = fopen($file, 'w');
		$ch = curl_init();
		 curl_setopt($ch, CURLOPT_URL, $link );
		 curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)");
		 //curl_setopt($ch, CURLOPT_HEADER, true);
		 curl_setopt($ch, CURLOPT_TIMEOUT, 3600);
		 curl_setopt($ch, CURLOPT_TIMEOUT_MS, 3600000);
		 curl_setopt($ch, CURLOPT_FILE, $dst);
		 //!dont need curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
         //curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FAILONERROR, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // 10 sec
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 10000); // 10 sec
		curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
		//*** maybe need */curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

		 $a = curl_exec($ch);
		 curl_close($ch);
         if ($a === false) return false;
         else return true;
	}
    
    
	public static function DownloadFromWordpress_Link($link)
	{
	    return self::DownloadRemoteFile($link, dirname(__FILE__).DIRSEP.'tmp'.DIRSEP.'update.zip');
	}
    
	public static function DownloadFromWordpress($version)
	{
		$dst = fopen(dirname(__FILE__).DIRSEP.'tmp'.DIRSEP.'update.zip', 'w');
		$ch = curl_init();
		 curl_setopt($ch, CURLOPT_URL, 'https://downloads.wordpress.org/plugin/wp-antivirus-site-protection.'.$version.'.zip' );
		 curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)");
		 //curl_setopt($ch, CURLOPT_HEADER, true);
		 curl_setopt($ch, CURLOPT_TIMEOUT, 3600);
		 curl_setopt($ch, CURLOPT_TIMEOUT_MS, 3600000);
		 curl_setopt($ch, CURLOPT_FILE, $dst);
		 //!dont need curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
         //curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FAILONERROR, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // 10 sec
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, 10000); // 10 sec
		curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
		//*** maybe need */curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

		 $a = curl_exec($ch);
		 curl_close($ch);
         if ($a === false) return false;
         else return true;
	}
	
	public static function ShowFilesForAnalyze($files_array = array())
	{
		$files = array();
		
		if (count($files_array['main']))
		{
			foreach ($files_array['main'] as $k => $filename)
			{
				$files[$filename] = $filename;	
			}
		}
		if (count($files_array['heuristic']))
		{
			foreach ($files_array['heuristic'] as $k => $filename)
			{
				$files[$filename] = $filename;	
			}
		}
		
		sort($files);
		
		echo '<pre>';
		print_r($files);
		echo '</pre>';
		
		echo '<br><br>';
		
		if (count($files))
		{
			foreach ($files as $file)
			{
				$file_full_path = ABSPATH.'/'.$file;
				echo $file_full_path.' Filesize: '.filesize($file_full_path).' bytes<br><br>';
				$handle = fopen($file_full_path, "r");
				$content =  fread($handle, filesize($file_full_path));
				echo 'Content: <pre>'.$content.'</pre>';
				fclose($handle);
				
				echo '<br><br><hr><br><br>';
			}
		}
		
		
	}
	
	public static function SendFilesForAnalyze($files_array = array(), $email_from = 'dontreply@siteguarding.com')
	{
		if (trim($email_from) == '') $email_from = 'dontreply@siteguarding.com';
	
		$result = false;
		$files = array();
		
		if (count($files_array['main']))
		{
			foreach ($files_array['main'] as $k => $filename)
			{
				$files[$filename] = $filename;	
			}
		}
		if (count($files_array['heuristic']))
		{
			foreach ($files_array['heuristic'] as $k => $filename)
			{
				$files[$filename] = $filename;	
			}
		}
		sort($files);
		
		
		if (count($files))
		{
            // Zip files
            $zip_file = 'check_'.md5(ABSPATH).'.zip';
            $zip_file_url = get_site_url().'/wp-content/plugins/wp-antivirus-site-protection/tmp/'.$zip_file;
            $zip_file = dirname(__FILE__).DIRSEP.'tmp'.DIRSEP.$zip_file;
            $status = SGAntiVirus_module::ZipFilesList($zip_file, $files);
            if ($status === false) return false;
            
			$md5_list = array();
			
			$attachments = $files;
			$message_files = '';
			foreach ($attachments as $k => $v)
			{
				$attachments[$k] = ABSPATH.DIRSEP.$v;
				$message_files .= $v."<br>";
				$md5_list[] = array(
					'File' => $v,
					'md5' => strtoupper(md5_file(ABSPATH.'/'.$v))
				);
			}

			// To send HTML mail, the Content-type header must be set
			$headers  = 'MIME-Version: 1.0' . "\r\n";
			$headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";
			
			// Additional headers
			$headers .= "From: ".get_site_url()." <".$email_from.">" . "\r\n";
			
			// Mail it
			$mailto = 'review@siteguarding.com';
			$subject = 'Antivirus Files Review ('.get_site_url().')';
			$body_message = 'Files for review. Domain: '.get_site_url()."<br>";
			$body_message .= 'Platform: '.SGAntiVirus_module::$antivirus_platform."<br>";
			$body_message .= 'Zip URL: '.$zip_file_url."<br>";
			$body_message .= 'Antivirus Version: '.SGAntiVirus_module::$antivirus_version."<br>";
			$body_message .= "<br><br>Files:<br><br>".$message_files;
			$body_message .= "<br><br>MD5:<br><pre>".print_r($md5_list, true)."</pre>";

			if (function_exists('wp_mail') === false) 
			{
				require_once(ABSPATH.'wp-includes'.DIRSEP.'pluggable.php');
			}

			//$result = wp_mail($mailto, $subject, $body_message, $headers, $attachments);
			$result = wp_mail($mailto, $subject, $body_message, $headers);
		}
		
		return $result;
	}
	
	
	public static function QuarantineFiles($files = array())
	{
		$fp = fopen(dirname(__FILE__).'/tmp/quarantine.log', 'a');
		
		$result = true;
		
		$quarantine_path = dirname(__FILE__).'/tmp/';
		
		//print_r($files);
		if (count($files))
		{
			foreach ($files as $file)
			{
				if (file_exists(ABSPATH.'/'.$file))
				{
					$f_from = ABSPATH.'/'.$file;
					$f_to = $quarantine_path.md5($file).'.tmp';
					
					//echo ABSPATH.'/'.$file."<br>";
					
					$a = date("Y-m-d H:i:s")." File ".$file."\n";
					fwrite($fp, $a);
					
					// Move to quarantine
					if (copy($f_from, $f_to) === false) 
					{
						$result = false;
						
						$a = date("Y-m-d H:i:s")." File is not moved to quarantine ".$file."\n";
						fwrite($fp, $a);
					}
					else {
						$a = date("Y-m-d H:i:s")." Moved to quarantine as ".$f_to."\n";
						fwrite($fp, $a);
					}
					
					// Delete from the server
					if (unlink($f_from) === false)
					{
						$result = false;
						
						$a = date("Y-m-d H:i:s")." File is not deleted ".$file."\n";
						fwrite($fp, $a);
					}
					else {
						$a = date("Y-m-d H:i:s")." File deleted ".$file."\n";
						fwrite($fp, $a);
					}
				}
			}
		}
		
		fclose($fp);
		
		return $result;
	}



	public static function page_ConfirmRegistration()
	{
		?>
		<script>
		function form_ConfirmRegistration(form)
		{
			if ( jQuery('#registered').is(':checked') ) return true;
			else {
				alert('Confirmation is not checked.');	
				return false;
			}
		}
		</script>
        <div class="registration_box">
		<form method="post" action="admin.php?page=plgavp_Antivirus" onsubmit="return form_ConfirmRegistration(this);">
		
			<h3 class="apv_header">Registration</h3>
			
			<p>Click "Confirm Registration" button to complete registration process. Your website will be automatically registered on <a href="http://www.siteguarding.com">www.SiteGuarding.com</a>.<br></p>
			
			<p>Already registered? Go to <a href="admin.php?page=plgavp_Antivirus_settings_page">Antivirus Settings</a> page and enter your Access Key.</p>
		
			<table id="settings_page">

			<tr class="line_4">
			<th scope="row">Domain</th>
			<td>
	            <input disabled type="text" name="domain" id="domain" value="<?php echo get_site_url(); ?>" class="regular-text reg_input_box">
			</td>
			</tr>
			    
			<tr class="line_4">
			<th scope="row">Email</th>
			<td>
	            <input type="text" name="email" id="email" value="<?php echo get_option( 'admin_email' ); ?>" class="regular-text reg_input_box">
			</td>
			</tr>
            
			<tr class="line_4">
			<th scope="row"></th>
			<td>
	            <span class="msg_alert">Email address must be valid. You will get your registration access key and reports by email.</span>  
			</td>
			</tr>
			
			<tr class="line_4">
			<th scope="row">Confirmation</th>
			<td>
	            <input name="registered" type="checkbox" id="registered" value="1"> I confirm to register my website on <a href="http://www.siteguarding.com">www.SiteGuarding.com</a>
			</td>
			</tr>
			
			</table>
			
		<?php
		wp_nonce_field( 'name_254f4bd3ea8d' );
		?>			
		<p class="submit startscanner">
		  <input type="submit" name="submit" id="submit" class="button button-primary" value="Confirm Registration">
		</p>
		
		<input type="hidden" name="page" value="plgavp_Antivirus"/>
		<input type="hidden" name="action" value="ConfirmRegistration"/>
		</form>
        </div>
		
		<?php self::HelpBlock(); ?>
			
		<?php
	}	
	
	
public static function PrintIconMessage($data)
{
    $rand_id = "id_".rand(1,10000).'_'.rand(1,10000);
    if ($data['type'] == '' || $data['type'] == 'alert') {$type_message = 'negative'; $icon = 'warning sign';}
    if ($data['type'] == 'ok') {$type_message = 'green'; $icon = 'checkmark box';}
    if ($data['type'] == 'info') {$type_message = 'yellow'; $icon = 'info';}
    ?>
    <div class="ui icon <?php echo $type_message; ?> message">
        <i class="<?php echo $icon; ?> icon"></i>
        <div class="msg_block_row">
            <?php
            if ($data['button_text'] != '' || $data['help_text'] != '') {
            ?>
            <div class="msg_block_txt">
                <?php
                if ($data['header'] != '') {
                ?>
                <div class="header"><?php echo $data['header']; ?></div>
                <?php
                }
                ?>
                <?php
                if ($data['message'] != '') {
                ?>
                <p><?php echo $data['message']; ?></p>
                <?php
                }
                ?>
            </div>
            <div class="msg_block_btn">
                <?php
                if ($data['help_text'] != '') {
                ?>
                <a class="link_info edit_post" href="javascript:;" onclick="InfoBlock('<?php echo $rand_id; ?>');"><i class="help circle icon"></i></a>
                <?php
                }
                ?>
                <?php
                if ($data['button_text'] != '') {
                    if (!isset($data['button_url_target']) || $data['button_url_target'] == true) $new_window = 'target="_blank"';
                    else $new_window = '';
                ?>
                <a class="mini ui green button" <?php echo $new_window; ?> href="<?php echo $data['button_url']; ?>"><?php echo $data['button_text']; ?></a>
                <?php
                }
                ?>
            </div>
                <?php
                if ($data['help_text'] != '') {
                ?>
                    <div style="clear: both;"></div>
                    <div id="<?php echo $rand_id; ?>" style="display: none;">
                        <div class="ui divider"></div>
                        <p><?php echo $data['help_text']; ?></p>
                    </div>
                <?php
                }
                ?>
            <?php
            } else {
            ?>
                <?php
                if ($data['header'] != '') {
                ?>
                <div class="header"><?php echo $data['header']; ?></div>
                <?php
                }
                ?>
                <?php
                if ($data['message'] != '') {
                ?>
                <p><?php echo $data['message']; ?></p>
                <?php
                }
                ?>
            <?php
            }
            ?>
        </div> 
    </div>
    <?php
}


    
	public static function page_PreScan($params)
	{
	    $avp_params = plgwpavp_GetExtraParams();
        
	    // Hide alert
        if (isset($_GET['hide_alert']) && intval($_GET['hide_alert']) == 1)
        {
            $data['hide_alert_till'] = date("Y-m-d", mktime(0, 0, 0, date("m")  , date("d")+31, date("Y")));
            plgwpavp_SetExtraParams($data);
        }
        
	    $avp_params = plgwpavp_GetExtraParams();
       
	    // Check for extra security plugins
		if (!defined('ABSPATH') || strlen(ABSPATH) < 8) 
		{
			$scan_path = dirname(__FILE__);
			$scan_path = str_replace(DIRSEP.'wp-content'.DIRSEP.'plugins'.DIRSEP.'wp-antivirus-site-protection', DIRSEP, $scan_path);
    		//echo TEST;
		}
        else $scan_path = ABSPATH;
        
        $tmp_htaccess = $scan_path.DIRSEP.'wp-content'.DIRSEP.'plugins'.DIRSEP.'.htaccess';
        if (file_exists($tmp_htaccess)) unlink($tmp_htaccess);
        
        $tmp_htaccess = $scan_path.DIRSEP.'wp-content'.DIRSEP.'.htaccess';
        if (file_exists($tmp_htaccess)) unlink($tmp_htaccess);
        
        
        
        $tab_id = intval($_GET['tab']);
        $tab_array = array(0 => '', 1 => '', 2 => '', 3 => '', 4 => '', 5 => '' );
        $tab_array[$tab_id] = 'active ';
       ?>
<script>
function InfoBlock(id)
{
    jQuery("#"+id).toggle();
}
</script>
<div class="ui grid max-box">
<?php 
if ($params['membership'] != 'pro') {
?>
<div class="row">
	<a target="_blank" href="https://www.siteguarding.com/en/protect-your-website">
	<img src="<?php echo plugins_url('images/rek3.png', __FILE__); ?>" />
	</a>
    
	<a target="_blank" style="margin:0 10px" href="https://www.siteguarding.com/en/website-extensions">
	<img src="<?php echo plugins_url('images/rek1.png', __FILE__); ?>" />
	</a>
    
	<a target="_blank" href="https://www.siteguarding.com/en/secure-web-hosting">
	<img src="<?php echo plugins_url('images/rek4.png', __FILE__); ?>" />
	</a>Remove these ads?&nbsp;&nbsp;&nbsp;<a href="https://www.siteguarding.com/en/buy-service/antivirus-site-protection">Upgrade to PRO version</a>
    
</div>
<?php 
}
?>
<div class="row">


<div class="ui top attached tabular menu">
        <a href="admin.php?page=plgavp_Antivirus&tab=0" class="<?php echo $tab_array[0]; ?> item"><i class="desktop icon"></i> Status</a>
        <a href="admin.php?page=plgavp_Antivirus&tab=1" class="<?php echo $tab_array[1]; ?> item"><i class="crosshairs icon"></i> Antivirus Scanner</a>
        <a href="admin.php?page=plgavp_Antivirus&tab=2" class="<?php echo $tab_array[2]; ?> item"><i class="doctor icon"></i> WP Security Tests</a>
        <a href="admin.php?page=plgavp_Antivirus&tab=3" class="<?php echo $tab_array[3]; ?> item"><i class="file text outline icon"></i> Reports</a>
        <a href="admin.php?page=plgavp_Antivirus&tab=4" class="<?php echo $tab_array[4]; ?> item"><i class="comments outline icon"></i> Help</a>
</div>
<div class="ui bottom attached segment">
<?php
if ($tab_id == 0)
{
    ?>
    <h3 class="ui header">Security Status for <?php echo get_site_url(); ?> <a class="mini ui green button" href="admin.php?page=plgavp_Antivirus&tab=0&action=AVP_RefreshStatus"><i class="refresh icon"></i>Refresh</a></h3>
    
    <?php
    switch ($params['membership'])
    {
        case 'trial':
            $message_data = array(
                'type' => 'info',
                'header' => 'You have: Trial version (ver. '.SGAntiVirus_module::$antivirus_version.')',
                'message' => 'Available Scans: '.$params['scans'].'<br>Valid till: '.$params['exp_date'],
                'button_text' => 'Upgrade',
                'button_url' => 'https://www.siteguarding.com/en/buy-service/antivirus-site-protection?domain='.urlencode( get_site_url() ),
                'help_text' => ''
            );
            break;
            
        case 'pro':
            $message_data = array(
                'type' => 'ok',
                'header' => 'You have: Pro version (ver. '.SGAntiVirus_module::$antivirus_version.')',
                'message' => 'Available Scans: '.$params['scans'].'<br>Valid till: '.$params['exp_date'],
                'button_text' => '',
                'button_url' => '',
                'help_text' => ''
            );
            break;
            
        case 'free':
            $message_data = array(
                'type' => 'alert',
                'header' => 'You have: Free version (ver. '.SGAntiVirus_module::$antivirus_version.')',
                'message' => 'Available Scans: '.$params['scans'],
                'button_text' => 'Upgrade',
                'button_url' => 'https://www.siteguarding.com/en/buy-service/antivirus-site-protection?domain='.urlencode( get_site_url() ),
                'help_text' => ''
            );
            break;
    }
    self::PrintIconMessage($message_data);
    
    
    
    if (count($params['reports']) > 0) 
    {
        if ($params['last_scan_files_counters']['main'] == 0 && $params['last_scan_files_counters']['heuristic'] == 0) 
        {
            $message_data = array(
                'type' => 'ok',
                'header' => 'Website is clean',
                'message' => 'We didn\'t detect any problems and viruses on your website.',
                'button_text' => '',
                'button_url' => '',
                'help_text' => ''
            );
        }
        
        if ($params['filemonitoring']['status'] == 0) 
        {
            $extra_clean_text = '';
            $link_clean = 'https://www.siteguarding.com/en/services/malware-removal-service';
            $button_text = 'Clean Website';
        }
        else {
            $extra_clean_text = "You have subscription with SiteGuarding.com and can request free cleaning. Please send us a ticket to request the cleaning service.";
            $link_clean = 'https://www.siteguarding.com/en/contacts';
            $button_text = 'Send Request';
        }
        
        if ($params['last_scan_files_counters']['main'] > 0)
        {
            $message_data = array(
                'type' => 'alert',
                'header' => 'Website is infected',
                'message' => 'We have detected virus / unsafe files on your website.',
                'button_text' => $button_text,
                'button_url' => $link_clean,
                'help_text' => $extra_clean_text
            );
        }
        else if ($params['last_scan_files_counters']['heuristic'] > 0) 
        {
            $message_data = array(
                'type' => 'alert',
                'header' => 'Website has unsafe/infected files',
                'message' => 'We have detected virus / unsafe files on your website.',
                'button_text' => $button_text,
                'button_url' => $link_clean,
                'help_text' => $extra_clean_text
            );
        }
    }
    else {
        $message_data = array(
            'type' => 'info',
            'header' => 'Website never analyzed before',
            'message' => 'Please go to Antivirus section and scan your website.',
            'button_text' => 'Scan Website',
            'button_url' => 'admin.php?page=plgavp_Antivirus&tab=1',
            'button_url_target' => false,
            'help_text' => ''
        );
    }
    self::PrintIconMessage($message_data);
    
    
    
    if ($params['blacklist']['google'] != 'ok')
    {
        $message_data = array(
            'type' => 'alert',
            'header' => 'Google blacklist status',
            'message' => 'Website is blacklisted. Reason '.$params['blacklist']['google'],
            'button_text' => 'Clean Website',
            'button_url' => 'https://www.siteguarding.com/en/services/malware-removal-service',
            'help_text' => ''
        );
    }
    else {
        $message_data = array(
            'type' => 'ok',
            'header' => 'Google blacklist status',
            'message' => 'Your website is not blacklisted in Google.',
            'button_text' => '',
            'button_url' => '',
            'help_text' => ''
        );
    }
    self::PrintIconMessage($message_data); 
    
    
    echo '<h3 class="ui header">Latest login sessions (Your IP: '.$_SERVER['REMOTE_ADDR'].')</h3>';
    $params['last_logged'] = (array)json_decode($params['last_logged'], true);
    $list_html = '<table class="ui celled table"><thead><tr><th>Date</th><th>Username</th><th>IP</th><th>Country</th></tr></thead><tbody>';
    foreach ($params['last_logged']['sessions'] as $ip => $row)
    {
        $list_html .= '<tr><td>'.date("Y-m-d H:i:s", $row['time']).'</td><td>'.$row['user'].'</td><td>'.$ip.'</td><td>'.SGAntiVirus::$country_list[ $row['country'] ].'</td></tr>';
    }
    $list_html .= '</tbody></table>';
    
    echo $list_html;
    
    
    
    echo '<h3 class="ui header">Other important security tests and options</h3>';
    
    $tmp_date = intval(strtotime($params['latest_WP_scan_date']));
    if (time() - $tmp_date > 10 * 24 * 60 * 60)
    {
        if ($tmp_date == 0) $message = "Test your WordPress for security issues.";
        else $message = "You passed WordPress Security tests more than 10 days ago.";
        
        $message_data = array(
            'type' => 'alert',
            'header' => 'WordPress Security Tests',
            'message' => $message,
            'button_text' => 'Check',
            'button_url' => 'admin.php?page=plgavp_Antivirus&tab=2',
            'help_text' => '',
            'button_url_target' => false
        );
        self::PrintIconMessage($message_data); 
    }


    
    if ($params['filemonitoring']['status'] == 0)
    {
        $message_data = array(
            'type' => 'alert',
            'header' => 'Files Change Monitoring',
            'message' => 'You don\'t have subscription for this service. Learn more how it works.',
            'button_text' => 'Subsribe',
            'button_url' => 'https://www.siteguarding.com/en/protect-your-website',
            'help_text' => '<p>One of the services provided by us is the day-to-day scanning and checking Your website for malware installation and changes in the files.</p><p>If hacker upload any file, remove or inject malware codes into the website\'s files. We will easy detect it and fix the issue.</p>'
        );
    }
    else {
        $message_data = array(
            'type' => 'ok',
            'header' => 'Files Change Monitoring',
            'message' => 'Your subscription is '.$params['filemonitoring']['plan'].' ['.$params['filemonitoring']['exp_date'].']',
            'button_text' => '',
            'button_url' => '',
            'help_text' => '<p>One of the services provided by us is the day-to-day scanning and checking Your website for malware installation and changes in the files.</p><p>If hacker upload any file, remove or inject malware codes into the website\'s files. We will easy detect it and fix the issue.</p>'
        );
    }
    self::PrintIconMessage($message_data); 
    
    
    if (!SGAntiVirus_module::CheckFirewall())
    {
        $message_data = array(
            'type' => 'alert',
            'header' => 'Website Firewall',
            'message' => 'Firewall is not installed. We don\'t filter the traffic of your website.',
            'button_text' => 'Subsribe',
            'button_url' => 'https://www.siteguarding.com/en/protect-your-website',
            'help_text' => 'A website firewall is an appliance, standalone plugin that applies a set of rules to an HTTP conversation. Generally, these rules cover common attacks such as cross-site scripting (XSS), backdoor requests and SQL injection. By customizing the rules to your application, many attacks can be identified and blocked.'
        );
    }
    else {
        $message_data = array(
            'type' => 'ok',
            'header' => 'Website Firewall',
            'message' => 'Firewall is installed',
            'button_text' => '',
            'button_url' => '',
            'help_text' => 'A website firewall is an appliance, standalone plugin that applies a set of rules to an HTTP conversation. Generally, these rules cover common attacks such as cross-site scripting (XSS), backdoor requests and SQL injection. By customizing the rules to your application, many attacks can be identified and blocked.'
        );
    }
    self::PrintIconMessage($message_data);
    

    if (intval($avp_params['protect_login_page']) == 0)
    {
        $message_data = array(
            'type' => 'alert',
            'header' => 'Bruteforce Protection',
            'message' => 'You don\'t have bruteforce protection on login page.',
            'button_text' => 'Activate',
            'button_url' => 'admin.php?page=plgavp_Antivirus_settings_page',
            'button_url_target' => false,
            'help_text' => 'Brute-force attack is the most common attack, used against Web applications. The purpose of this attack is to gain access to user\'s accounts by repeated attempts to guess the password of the user or group of users.'
        );
    }
    else {
        $message_data = array(
            'type' => 'ok',
            'header' => 'Bruteforce Protection',
            'message' => 'Protection is active. Login page is protected.',
            'button_text' => 'Learn More',
            'button_url' => 'https://www.siteguarding.com/en/bruteforce-attack',
            'help_text' => 'Brute-force attack is the most common attack, used against Web applications. The purpose of this attack is to gain access to user\'s accounts by repeated attempts to guess the password of the user or group of users.'
        );
    }
    self::PrintIconMessage($message_data);
    
    
    
    if ( !isset($params['filemonitoring']['remote_backup_status']) || $params['filemonitoring']['remote_backup_status'] == 0)
    {
        $message_data = array(
            'type' => 'alert',
            'header' => 'Website Backup service',
            'message' => 'You don\'t have subscription for this service. Learn more how it works.',
            'button_text' => 'Subsribe',
            'button_url' => 'https://www.siteguarding.com/en/importance-of-website-backup',
            'help_text' => '<p>It is extremely important to have your website backed up regularly. The website backup means that you can have a similar copy of your content and data with you. You can keep it safe. Whatever happens to your website, the data will be available to you, and you can use it later.</p>'
        );
    }
    else {
        $message_data = array(
            'type' => 'ok',
            'header' => 'Website Backup service',
            'message' => 'Your subscription is '.$params['filemonitoring']['plan'].' ['.$params['filemonitoring']['exp_date'].']',
            'button_text' => '',
            'button_url' => '',
            'help_text' => '<p>It is extremely important to have your website backed up regularly. The website backup means that you can have a similar copy of your content and data with you. You can keep it safe. Whatever happens to your website, the data will be available to you, and you can use it later.</p>'
        );
    }
    self::PrintIconMessage($message_data); 
    
    
    
    
    if (!SGAntiVirus_module::CheckGEOProtectionInstallation()) 
    {
        $action = 'install-plugin';
        $slug = 'wp-geo-website-protection';
        $install_url = wp_nonce_url(
            add_query_arg(
                array(
                    'action' => $action,
                    'plugin' => $slug
                ),
                admin_url( 'update.php' )
            ),
            $action.'_'.$slug
        );
        
        $message_data = array(
            'type' => 'info',
            'header' => 'GEO Website Protection',
            'message' => 'Security plugin GEO Website Protection is not installed on your website.',
            'button_text' => 'Install',
            'button_url' => $install_url,
            'help_text' => 'WP GEO Website Protection is the security plugin to limit access from unwanted counties or IP addresses.<br>You can easy filter front-end visitors and visitors who wants to login to Wordpress backend. Detailed Logs and Statistics. <a href="https://wordpress.org/plugins/wp-geo-website-protection/" target="_blank">Click to see the details</a>'
        );
    }
    else {
        if (isset($params['extensions']['wp-geo-website-protection'])) $geo_ext_txt = '<br>Please use license key <b>'.$params['extensions']['wp-geo-website-protection'].'</b> to active PRO version.';
        else $geo_ext_txt = '';
        
        $message_data = array(
            'type' => 'ok',
            'header' => 'GEO Website Protection',
            'message' => 'Security plugin GEO Website Protection is installed on your website.'.$geo_ext_txt,
            'button_text' => 'Configure',
            'button_url' => 'admin.php?page=plgsggeo_protection',
            'help_text' => 'WP GEO Website Protection is the security plugin to limit access from unwanted counties or IP addresses.<br>You can easy filter front-end visitors and visitors who wants to login to Wordpress backend. Detailed Logs and Statistics. <a href="https://wordpress.org/plugins/wp-geo-website-protection/" target="_blank">Click to see the details</a>'
        );
    }
    self::PrintIconMessage($message_data);
    
    
    echo '<p class="mini">Status Timestamp: '.date("Y-m-d H:i:s", $params['cache_license_info_time']).'</p>';
}




if ($tab_id == 1)
{
    ?>
    <p>To start the scan process click "Start Scanner" button.</p>

    <p>Scanner will automatically collect and analyze the files of your website. The scanning process can take up to 10 mins (it depends of speed of your server and amount of the files to analyze). The copy of the report we will send by email for your records.</p>
    
    <form method="post" action="admin.php?page=plgavp_Antivirus&tab=1">
	<?php
	   wp_nonce_field( 'name_254f4bd3ea8d' );
	?>			
	<p class="submit startscanner">
	  <input type="submit" name="submit" id="submit" class="button button-primary" value="Start Scanner"><br />
      Scanner will check all the files for this website and all the folders.
	</p>
	
	<input name="allow_scan" type="hidden" id="allow_scan" value="1">
	<input type="hidden" name="page" value="plgavp_Antivirus"/>
	<input type="hidden" name="action" value="StartScan"/>
	</form>
    

    <script>
    function ShowLoader()
    {
        jQuery(".ajax_button").hide();
        jQuery(".scanner_ajax_loader").show(); 
        
        jQuery.post(
            ajaxurl, 
            {
                'action': 'plgavp_ajax_scan_sql'
            }, 
            function(response){
                document.location.href = 'admin.php?page=plgavp_Antivirus&tab=3';
            }
        );  
    }
    </script>		
	<p class="submit startscanner">
	  <input type="submit" name="submit" id="submit" class="button button-primary ajax_button" value="Check Database" onclick="ShowLoader()">
      <img class="scanner_ajax_loader" width="48" height="48" style="display: none;" src="<?php echo plugins_url('images/ajax_loader.svg', __FILE__); ?>" />
      <br />
      Scanner will check all posts and pages in database to detect unwanted links, iframes, javascript codes.
	</p>
	

    
    <p class="msg_alert"><b>Please note:</b> Some other security plugins can block Antivirus scanning process. Disable them or <a href="https://www.siteguarding.com/en/contacts" target="_blank">contact SiteGuarding.com support</a> for more information.</p>
    <p><b>Found suspicious file on your website?</b> Analyze it for free with our online tool antivirus. <a target="_blank" href="https://www.siteguarding.com/en/website-antivirus">Click here</a></p>
    
    <p>&nbsp;</p>
    
    <h3 class="apv_header">Extra Options</h3>
    
    <?php
    if (!SGAntiVirus_module::CheckGEOProtectionInstallation()) 
    {
        $action = 'install-plugin';
        $slug = 'wp-geo-website-protection';
        $install_url = wp_nonce_url(
            add_query_arg(
                array(
                    'action' => $action,
                    'plugin' => $slug
                ),
                admin_url( 'update.php' )
            ),
            $action.'_'.$slug
        );
        
        $message_data = array(
            'type' => 'info',
            'header' => 'GEO Website Protection',
            'message' => 'Security plugin GEO Website Protection is not installed on your website.',
            'button_text' => 'Install',
            'button_url' => $install_url,
            'help_text' => 'WP GEO Website Protection is the security plugin to limit access from unwanted counties or IP addresses.<br>You can easy filter front-end visitors and visitors who wants to login to Wordpress backend. Detailed Logs and Statistics. <a href="https://wordpress.org/plugins/wp-geo-website-protection/" target="_blank">Click to see the details</a>'
        );
    }
    else {
        if (isset($params['extensions']['wp-geo-website-protection'])) $geo_ext_txt = '<br>Please use license key <b>'.$params['extensions']['wp-geo-website-protection'].'</b> to active PRO version.';
        else $geo_ext_txt = '';
        
        $message_data = array(
            'type' => 'ok',
            'header' => 'GEO Website Protection',
            'message' => 'Security plugin GEO Website Protection is installed on your website.'.$geo_ext_txt,
            'button_text' => 'Configure',
            'button_url' => 'admin.php?page=plgsggeo_protection',
            'help_text' => 'WP GEO Website Protection is the security plugin to limit access from unwanted counties or IP addresses.<br>You can easy filter front-end visitors and visitors who wants to login to Wordpress backend. Detailed Logs and Statistics. <a href="https://wordpress.org/plugins/wp-geo-website-protection/" target="_blank">Click to see the details</a>'
        );
    }
    
    self::PrintIconMessage($message_data);
    
    ?>
    
    <div class="ui message">
        <div class="header">
        Website Cleaning Services
        </div>
        <p>Your website got hacked and blacklisted by Google? This is really bad, you are going to lose your visitors. We will help you to clean your website and remove from all blacklists.</p>
        
        <?php
        
        if ($params['filemonitoring']['status'] == 0) 
        {
            $extra_clean_text = '';
            $link_clean = 'https://www.siteguarding.com/en/services/malware-removal-service';
            $button_clean_text = 'Clean My Website';
        }
        else {
            $extra_clean_text = "You have subscription with SiteGuarding.com and can request free cleaning. Please send us a ticket to request the cleaning service.";
            $link_clean = 'https://www.siteguarding.com/en/contacts';
            $button_clean_text = 'Send Cleaning Request';
        }
        ?>
        
        <?php echo $extra_clean_text; ?>
		<p><a href="<?php echo $link_clean; ?>" target="_blank" class="ui green button"><?php echo $button_clean_text; ?></a></p>
    </div>
    
    <div class="ui message">
        <div class="header">
        Send the Files for Analyze
        </div>
        <p>Found suspicious files on your website? Send us request for free analyze. Our security experts will review your files and explain what to do.</p>
        
		<form method="post" action="admin.php?page=plgavp_Antivirus&tab=1">
		<?php
		if ($params['membership'] == 'pro') 
		{
			?>
			<input type="submit" name="submit" id="submit" class="ui green button" value="Send Files">
			<?php
		} else {
			?>
			<input type="button" class="ui green button" value="Send Files" onclick="javascript:alert('Available in PRO version only. Please Upgrade to PRO version.');">
			<?php
		}
		?>	
		
		<?php
		wp_nonce_field( 'name_254f4bd3ea8d' );
		?>
		<input type="hidden" name="page" value="plgavp_Antivirus"/>
		<input type="hidden" name="action" value="SendFilesForAnalyze"/>
		</form>
    </div>
    
    <div class="ui message">
        <div class="header">
        Quarantine
        </div>
        <p>Remove viruses from your website with one click.<br><span class="msg_alert">Please note: Hackers can inject malware codes inside of the normal/core files. We advice to send request to SiteGuarding.com for file review and analyze. If you quarantine core files, the website will stop to work.</span></p>
        
		<form method="post" action="admin.php?page=plgavp_Antivirus&tab=1">
		<?php
		if ($params['membership'] == 'pro') 
		{
			?>
			<input type="submit" name="submit" id="submit" class="ui red button" value="Quarantine Malware" onclick="return confirm('Before use this feature, please make sure that you have sent the files for analyze and got reply from SiteGuarding.com\nMove files to quarantine?')">
			<?php
		} else {
			?>
			<input type="button" class="ui red button" value="Quarantine Malware" onclick="javascript:alert('Available in PRO version only. Please Upgrade to PRO version.');">
			<?php
		}
		?>	
		
		<?php
		wp_nonce_field( 'name_254f4bd3ea8d' );
		?>
		<input type="hidden" name="page" value="plgavp_Antivirus"/>
		<input type="hidden" name="action" value="QuarantineFiles"/>
		</form>
    </div>
    
    <?php
}




if ($tab_id == 2)
{
    $action = trim($_POST['action']);
    if ($action == 'StartWPTests')
    {
        ?>
        <script type="text/javascript">
        window.setTimeout(function(){ document.location.reload(true); }, 20000);
        </script>
        <p style="text-align: center;">
            <img width="120" height="120" src="<?php echo plugins_url('images/ajax_loader.svg', __FILE__); ?>" />
            <br /><br />
            Please wait, it will take approximately 30 seconds.
        </p>
        <iframe src="admin.php?page=plgavp_Antivirus&tab=2&action=StartWPTests_iframe" style="height:1px;width:1px;"></iframe>
        <?php

    }
    else {
        
    ?>
        <h3 class="ui header">Security Audit for WordPress</h3>
    
        <p><b>Please read!</b> These tests only serve as suggestions! Although they cover years of best practices getting all test green will not guarantee your site will not get hacked. Likewise, getting them all red doesn't mean you'll certainly get hacked. Please read each test's detailed information to see if it represents a real security issue for your site. Suggestions and test results apply to public, production sites, not local, development ones. </p>
        <p>If you need an in-depth security analysis and protection, please learn more about our security packages <a href="https://www.siteguarding.com/en/protect-your-website" class="mini ui green button" target="_blank">Show Packages</a></p>
        <p>&nbsp;</p>
        <p>To run the tests click "Run Tests" button.</p>
        <p>Plugin will automatically collect information about your website, users, plugins, php configurations and much more. The scanning process can take up to 1 minute (it depends of speed of your server). You will be automatically redirected to the page with the results.</p>
        <form method="post" action="admin.php?page=plgavp_Antivirus&tab=2">
    	<?php
    	   wp_nonce_field( 'name_254f4bd3ea8d' );
    	?>			
    	<p class="submit startscanner">
    	  <input type="submit" name="submit" id="submit" class="button button-primary" value="Run Tests">
    	</p>
    	
    	<input type="hidden" name="page" value="plgavp_Antivirus"/>
    	<input type="hidden" name="action" value="StartWPTests"/>
    	</form>
        
        <?php
        if ($params['latest_WP_scan_date'] == '') $latest_WP_scan_date = '';
        else $latest_WP_scan_date = " [Latest analyze date: ".$params['latest_WP_scan_date']."]";
        ?>
        
        <h3 class="ui header">Test Results<?php echo $latest_WP_scan_date; ?></h3>
        <?php
        $stats = array();
        $stats['total_plugins'] = count(get_plugins());
        $stats['total_active_plugins'] = count(get_option('active_plugins'));
        $stats['total_disabled_plugins'] = $stats['total_plugins'] - $stats['total_active_plugins'];
        
        $allusers_info = count_users();
        $stats['total_admin_users'] = $allusers_info['avail_roles']['administrator'];
        
        if ($stats['total_admin_users'] > 2 || $stats['total_disabled_plugins'] > 2) 
        {
            if ($stats['total_admin_users'] > 2) 
            {
                $message_data = array(
                    'type' => 'alert',
                    'header' => '',
                    'message' => 'We have detected '.$stats['total_admin_users'].' accounts with administrator level (<a href="users.php?role=administrator" target="_blank">View site admins</a>). If you see any fake accounts or old accounts (freelancers, developers, etc) remove them or change the password. It\'s the most simple and common way to hack your website. <a href="https://www.siteguarding.com/en/old-or-fake-administrator-accounts" target="_blank">Learn more</a>',
                    'button_text' => '',
                    'button_url' => '',
                    'help_text' => ''
                );
                self::PrintIconMessage($message_data);
            }
            
            if ($stats['total_disabled_plugins'] > 2) 
            {
                $message_data = array(
                    'type' => 'alert',
                    'header' => '',
                    'message' => 'We have detected '.$stats['total_plugins'].' installed plugins and '.$stats['total_disabled_plugins'].' plugins are disabled</b></span> (<a href="plugins.php?plugin_status=inactive" target="_blank">View plugins</a>). The files of not active plugins still on the server and hackers can use the files or bugs of these plugins. <a href="https://www.siteguarding.com/en/disabled-plugins-and-extensions" target="_blank">Learn more</a>',
                    'button_text' => '',
                    'button_url' => '',
                    'help_text' => ''
                );
                self::PrintIconMessage($message_data);
            }
        }

        
        $security_tests = SGAntiVirus_WP_tests::$security_tests;

        foreach ($security_tests as $k => $row)
        {
            if (isset($params[$k]))
            {
                $wp_test_info = json_decode($params[$k], true);
                switch ($wp_test_info['status'])
                {
                    case 0:
                        $status_txt = 'msg_bad';
                        break;
                        
                    case 5:
                        $status_txt = 'msg_warning';
                        break;
                        
                    case 10:
                        $status_txt = 'msg_ok';
                        break;
                }
            }
            else $status_txt = '';
            
            switch($status_txt)
            {
                case 'msg_ok':
                    $msg_type = 'ok';
                    $msg_txt = $row['msg_ok'];
                    $msg_button_text = '';
                    $msg_button_url = '';
                    break;
                    
                case 'msg_bad':
                    $msg_type = 'alert';
                    $msg_txt = $row['msg_bad'];
                    $msg_button_text = 'Fix It';
                    $msg_button_url = 'https://www.siteguarding.com/en/services/malware-removal-service';
                    break;
                    
    
                case 'msg_warning':
                    $msg_type = 'info';
                    $msg_txt = $row['msg_warning'];
                    $msg_button_text = '';
                    $msg_button_url = '';
                    break;
                    
                default:
                    $msg_type = 'info';
                    $msg_txt = 'Never tested before';
                    $msg_button_text = '';
                    $msg_button_url = '';
            }
            
            if (isset($wp_test_info['msg']))
            {
                $msg_txt = str_replace("%s", $wp_test_info['msg'], $msg_txt);
            }
            
            if (isset($row['help'])) $msg_help = $row['help'];
            else $msg_help = '';
    
            $message_data = array(
                'type' => $msg_type,
                'header' => '<p><b>'.$row['title'].'</b></p>',
                'message' => $msg_txt,
                'button_text' => $msg_button_text,
                'button_url' => $msg_button_url,
                'button_url_target' => true,
                'help_text' => $msg_help
            );
            self::PrintIconMessage($message_data);
            
        }
    
    
    }
}


if ($tab_id == 3)
{
    ?>
    <h3 class="ui header">Latest Reports</h3>
    <?php
    if (isset($params['latest_scan_date']))
    {
        ?>
        <h4 class="ui header">Dababase (SQL) scan report</h4>
        <?php
        
                // Show report
                echo '<p>Latest scan was '.$params['latest_scan_date'].'</p>';
                
                $params['results'] = (array)json_decode($params['results'], true);

                
                if (intval($_GET['showdetailed']) == 0)
                {
                    /**
                     * Show simple
                     */
                    $results = AVP_SEO_SG_Protection::PrepareResults($params['results']);

                    echo '<h3>Bad words (<a href="admin.php?page=plgavp_Antivirus&tab=3&showdetailed=1">show details</a>)</h3>';
                    if (count($results['WORDS']))
                    {
                        echo '<table class="ui selectable celled table small">';
                        echo '<thead><tr><th>Words</th></thead>';
                        foreach ($results['WORDS'] as $word)
                        {
                            echo '<tr>';
                            echo '<td>'.$word.'</td>';
                            echo '</tr>';
                        }
                        echo '</table>';
                    }
                    else echo '<p>No bad words detected.</p>';
                    
                    echo "<hr>";
                    
                    echo '<h3>Detected links (<a href="admin.php?page=plgavp_Antivirus&tab=3&showdetailed=1">show details</a>)</h3>';
                    if (count($results['A']))
                    {
                        echo '<table class="ui selectable celled table small">';
                        echo '<thead><tr><th>Links</th><th>Text in links</th></tr></thead>';
                        foreach ($results['A'] as $link => $txt)
                        {
                            echo '<tr>';
                            echo '<td>'.$link.'</td><td>'.$txt.'</td>';
                            echo '</tr>';
                        }
                        echo '</table>';
                    }
                    else echo '<p>No strange links detected.</p>';
                    
                    echo "<hr>";
                    
                    echo '<h3>Detected iframes (<a href="admin.php?page=plgavp_Antivirus&tab=3&showdetailed=1">show details</a>)</h3>';
                    if (count($results['IFRAME']))
                    {
                        echo '<table class="ui selectable celled table small">';
                        echo '<thead><tr><th>Links</th></thead>';
                        foreach ($results['IFRAME'] as $link)
                        {
                            echo '<tr>';
                            echo '<td>'.$link.'</td>';
                            echo '</tr>';
                        }
                        echo '</table>';
                    }
                    else echo '<p>No iframes detected.</p>';
                    
                    echo "<hr>";
                    
                    echo '<h3>Detected JavaScripts (<a href="admin.php?page=plgavp_Antivirus&tab=3&showdetailed=1">show details</a>)</h3>';
                    if (count($results['SCRIPT']))
                    {
                        echo '<table class="ui selectable celled table small">';
                        echo '<thead><tr><th>JavaScripts Link or codes</th></thead>';
                        foreach ($results['SCRIPT'] as $link)
                        {
                            echo '<tr>';
                            echo '<td>'.$link.'</td>';
                            echo '</tr>';
                        }
                        echo '</table>';
                    }
                    else echo '<p>No iframes detected.</p>';
                }
                else {
                    /**
                     * Show detailed
                     */
                    $post_ids = array();
                    $post_titles = array();
                    if (count($params['results']['posts']['WORDS']))
                    {
                        foreach ($params['results']['posts']['WORDS'] as $post_id => $post_arr)
                        {
                            $post_ids[ $post_id ] = $post_id;
                        }
                    }
                    if (count($params['results']['posts']['A']))
                    {
                        foreach ($params['results']['posts']['A'] as $post_id => $post_arr)
                        {
                            $post_ids[ $post_id ] = $post_id;
                        }
                    }
                    if (count($params['results']['posts']['IFRAME']))
                    {
                        foreach ($params['results']['posts']['IFRAME'] as $post_id => $post_arr)
                        {
                            $post_ids[ $post_id ] = $post_id;
                        }
                    }
                    if (count($params['results']['posts']['SCRIPT']))
                    {
                        foreach ($params['results']['posts']['SCRIPT'] as $post_id => $post_arr)
                        {
                            $post_ids[ $post_id ] = $post_id;
                        }
                    }
                    $post_titles = AVP_SEO_SG_Protection::GetPostTitles_by_IDs($post_ids);
                    
                    echo '<h3>Detailed by post (<a href="admin.php?page=plgavp_Antivirus&tab=3&showdetailed=0">show simple</a>)</h3>'; 
                    if (count($params['results']['posts']['WORDS']))
                    {
                        foreach ($params['results']['posts']['WORDS'] as $post_id => $post_arr)
                        {
                            if (count($post_arr))
                            {
                                $edit_link = 'post.php?post='.$post_id.'&action=edit';
                                echo '<table class="ui selectable celled table small">';
                                echo '<thead><tr><th><b>Bad words in post ID: '.$post_id.'</b> ('.$post_titles[$post_id]/*SEO_SG_Protection::GetPostTitle_by_ID($post_id)*/.') <a href="'.$edit_link.'" target="_blank" class="edit_post"><i class="write icon"></i> edit</a></th></tr></thead>';
                                foreach ($post_arr as $word)
                                {
                                    echo '<tr>';
                                    echo '<td>'.$word.'</td>';
                                    echo '</tr>';
                                }
                                echo '</table>';
                            }
                        }
                    }
                    if (count($params['results']['posts']['A']))
                    {
                        foreach ($params['results']['posts']['A'] as $post_id => $post_arr)
                        {
                            if (count($post_arr))
                            {
                                $edit_link = 'post.php?post='.$post_id.'&action=edit';
                                echo '<table class="ui selectable celled table small">';
                                echo '<thead><tr><th class="ten wide"><b>Links in post ID: '.$post_id.'</b> ('.$post_titles[$post_id]/*SEO_SG_Protection::GetPostTitle_by_ID($post_id)*/.') <a href="'.$edit_link.'" target="_blank" class="edit_post"><i class="write icon"></i> edit</a></th><th class="six wide">Text in links</th></tr></thead>';
                                foreach ($post_arr as $link_data)
                                {
                                    foreach ($link_data as $link => $txt)
                                    {
                                        echo '<tr>';
                                        echo '<td>'.$link.'</td><td>'.$txt.'</td>';
                                        echo '</tr>';
                                    }
                                }
                                echo '</table>';
                            }
                        }
                    }
                    //else echo '<p>No strange links detected.</p>';
//print_r($params['results']['posts']['IFRAME']);
                    if (count($params['results']['posts']['IFRAME']))
                    {
                        foreach ($params['results']['posts']['IFRAME'] as $post_id => $post_arr)
                        {
                            if (count($post_arr))
                            {
                                $edit_link = 'post.php?post='.$post_id.'&action=edit';
                                echo '<table class="ui selectable celled table small">';
                                echo '<thead><tr><th><b>Iframes in post ID: '.$post_id.'</b> ('.$post_titles[$post_id]/*SEO_SG_Protection::GetPostTitle_by_ID($post_id)*/.') <a href="'.$edit_link.'" target="_blank" class="edit_post"><i class="write icon"></i> edit</a></th></tr></thead>';
                                foreach ($post_arr as $link)
                                {
                                    echo '<tr>';
                                    echo '<td>'.$link.'</td>';
                                    echo '</tr>';
                                }
                                echo '</table>';
                            }
                        }
                    }
                    //else echo '<p>No strange links detected.</p>';
//print_r($params['results']['posts']['SCRIPT']);exit;
                    if (count($params['results']['posts']['SCRIPT']))
                    {
                        foreach ($params['results']['posts']['SCRIPT'] as $post_id => $post_arr)
                        {
                            if (count($post_arr))
                            {
                                $edit_link = 'post.php?post='.$post_id.'&action=edit';
                                echo '<table class="ui selectable celled table small">';
                                echo '<thead><tr><th><b>JavaScript in post ID: '.$post_id.'</b> ('.$post_titles[$post_id]/*SEO_SG_Protection::GetPostTitle_by_ID($post_id)*/.') <a href="'.$edit_link.'" target="_blank" class="edit_post"><i class="write icon"></i> edit</a></th></tr></thead>';
                                foreach ($post_arr as $js_link => $js_code)
                                {
                                    if ($js_code == '') $js_code = $js_link;
                                    echo '<tr>';
                                    echo '<td>'.$js_code.'</td>';
                                    echo '</tr>';
                                }
                                echo '</table>';
                            }
                        }
                    }
                    //else echo '<p>No strange links detected.</p>';
                }
                
    }
    
    echo "<hr>";
    
    ?>
        
        <h3 class="ui header">Latest File Reports</h3>
    <?php
    $reports = $params['reports'];
    //print_r($params);
    if (count($reports)) {
    	?>
    	<p>
    	<?php
    		foreach ($reports as $report_info) {
    	?>
    			<a href="<?php echo $report_info['report_link']; ?>" target="_blank">Click to view report for <?php echo $report_info['domain']; ?>. Date: <?php echo $report_info['date']; ?></a><br />
    	<?php
    		}
    	?>
    	</p>
    	<?php
    } else {
    ?>
    	<p>You don't have any available report yet. Please scan your website.</p>
    <?php
    }
    ?>
    
    <h3 class="ui header">Latest File Scan Results</h3>
			<?php
			if ($params['membership'] == 'free') 
			{
                $message_data = array(
                    'type' => 'info',
                    'header' => 'Version Limits',
                    'message' => 'Quarantine & Malware Removal feature is disabled. Available in PRO version only.',
                    'button_text' => 'Upgrade',
                    'button_url' => 'https://www.siteguarding.com/en/buy-service/antivirus-site-protection?domain='.urlencode( get_site_url() ),
                    'help_text' => ''
                );
                self::PrintIconMessage($message_data);
			}
			?>

			<?php
			if ( $params['last_scan_files_counters']['main'] == 0 || $params['last_scan_files_counters']['heuristic'] == 0 )
			{
				echo '<p>No files for review.</p>';
			}
			if (count($params['last_scan_files']['main']))
			{
				// Check files
				foreach ($params['last_scan_files']['main'] as $k => $tmp_file)
				{
					if (!file_exists(ABSPATH.'/'.$tmp_file)) unset($params['last_scan_files']['main'][$k]);
				}
				
				if (count($params['last_scan_files']['main']) > 0)
				{
					?>
					<div class="avp_latestfiles_block">
					<h4>Action is required</h4>
					
					<?php
					foreach ($params['last_scan_files']['main'] as $tmp_file)
					{
						echo '<p>'.$tmp_file.'</p>';
					}
					?>
	
					<br />
					
					<div class="divTable">
					<div class="divRow">
					<div class="divCell">
					<form method="post" action="admin.php?page=plgavp_Antivirus">
					<?php
					if ($params['membership'] == 'pro') 
					{
						?>
						<input type="submit" name="submit" id="submit" class="ui green button" value="Send Files to SiteGuarding.com">
						<?php
					} else {
						?>
						<input type="button" class="ui green button" value="Send Files to SiteGuarding.com" onclick="javascript:alert('Available in PRO version only. Please Upgrade to PRO version.');">
						<?php
					}
					?>	
					
					<?php
					wp_nonce_field( 'name_254f4bd3ea8d' );
					?>
					<input type="hidden" name="page" value="plgavp_Antivirus"/>
					<input type="hidden" name="action" value="SendFilesForAnalyze"/>
					</form>
					</div>
					
					<div class="divCell">&nbsp;</div>

					<div class="divCell">
					<form method="post" action="admin.php?page=plgavp_Antivirus">
					<?php
					if ($params['membership'] == 'pro') 
					{
						?>
						<input type="submit" name="submit" id="submit" class="ui red button" value="Quarantine & Remove malware" onclick="return confirm('Before use this feature, please make sure that you have sent the files for analyze and got reply from SiteGuarding.com\nMove files to quarantine?')">
						<?php
					} else {
						?>
						<input type="button" class="ui red button" value="Quarantine & Remove malware" onclick="javascript:alert('Available in PRO version only. Please Upgrade to PRO version.');">
						<?php
					}
					?>	
					
					<?php
					wp_nonce_field( 'name_254f4bd3ea8d' );
					?>
					<input type="hidden" name="page" value="plgavp_Antivirus"/>
					<input type="hidden" name="action" value="QuarantineFiles"/>
					</form>
					
					</div></div></div>
					<p>* Please note: Hackers can inject malware codes inside of the normal files. If you delete these files, website can stop to work or will be not stable. We advice to send request to SiteGuarding.com for file review and analyze.</p> 
					
					</div>
					<?php
				}

			}
			
			
			if (count($params['last_scan_files']['heuristic']))
			{
				// Check files
				foreach ($params['last_scan_files']['heuristic'] as $k => $tmp_file)
				{
					if (!file_exists(ABSPATH.'/'.$tmp_file)) unset($params['last_scan_files']['heuristic'][$k]);
				}
				
				if (count($params['last_scan_files']['heuristic']) > 0)
				{
					?>
					<div class="avp_latestfiles_block">
					<h4>Review is required
    					<?php
    					if ($params['whitelist_filters_enabled'] == 1)
    					{
    						?>
    						<span class="label_red">White list is enabled</span>
    						<?php
    					}
    					?>
                    </h4>
					<?php
					foreach ($params['last_scan_files']['heuristic'] as $tmp_file)
					{
						echo '<p>'.$tmp_file.'</p>';
					}
					
					?>
					<br />

					
					
					<div class="divTable">
					<div class="divRow">
					<div class="divCell">
					
					<form method="post" action="admin.php?page=plgavp_Antivirus">
					<?php
					wp_nonce_field( 'name_254f4bd3ea8d' );
					
					if ($params['membership'] == 'pro') 
					{
						?>
						<input type="submit" name="submit" id="submit" class="ui green button" value="Send Files to SiteGuarding.com">
						<?php
					} else {
						?>
						<input type="button" class="ui green button" value="Send Files to SiteGuarding.com" onclick="javascript:alert('Available in PRO version only. Please Upgrade to PRO version.');">
						<?php
					}
					?>	
					
					<input type="hidden" name="page" value="plgavp_Antivirus"/>
					<input type="hidden" name="action" value="SendFilesForAnalyze"/>
					</form>
					
					</div>
					
					<div class="divCell">&nbsp;</div>

					<div class="divCell">
					<form method="post" action="admin.php?page=plgavp_Antivirus">
					<?php
					if ($params['membership'] == 'pro') 
					{
						?>
						<input type="submit" name="submit" id="submit" class="ui red button" value="Quarantine & Remove malware" onclick="return confirm('Before use this feature, please make sure that you have sent the files for analyze and got reply from SiteGuarding.com\nMove files to quarantine?')">
						<?php
					} else {
						?>
						<input type="button" class="ui red button" value="Quarantine & Remove malware" onclick="javascript:alert('Available in PRO version only. Please Upgrade to PRO version.');">
						<?php
					}
					?>	
					
					<?php
					wp_nonce_field( 'name_254f4bd3ea8d' );
					?>
					<input type="hidden" name="page" value="plgavp_Antivirus"/>
					<input type="hidden" name="action" value="QuarantineFiles"/>
					<input type="hidden" name="filelist" value="heuristic"/>
					</form>
					
					</div></div></div>
					<p>* Please note: Hackers can inject malware codes inside of the normal files. If you delete these files, website can stop to work or will be not stable. We advice to send request to SiteGuarding.com for file review and analyze.</p> 
					
					
					</div>
					<?php
				}
			}
			
		
			?>
    <?php    
}


if ($tab_id == 4)
{

		?>
        <h3 class="ui header">Support</h3>
		<p>
        For any questions and support please use LiveChat or this <a href="https://www.siteguarding.com/en/contacts" rel="nofollow" target="_blank" title="SiteGuarding.com - Website Security. Professional security services against hacker activity. Daily website file scanning and file changes monitoring. Malware detecting and removal.">contact form</a>
        </p>
        <p>
        or contact us in Live Chat
        </p>
        <p>
                <a href="http://www.siteguarding.com/livechat/index.html" target="_blank">
                <img src="<?php echo plugins_url('images/livechat.png', __FILE__); ?>"/>
                </a>
        </p>
		<p>
		For malware removal services please <a target="_blank" href="https://www.siteguarding.com/en/services/malware-removal-service">click here</a>.
		</p>
		<p>
		For more information and details about Antivirus Site Protection please <a target="_blank" href="https://www.siteguarding.com/en/antivirus-site-protection">click here</a>.<br /><br />
		</p>




        <h3 class="howitworks">Do you need clean and protected website? Please learn how it works.</h3>
        <p class="howitworks"><a href="https://www.siteguarding.com/en/protect-your-website" target="_blank">Our security packages</a> cover all your needs. Focus on your business and leave security to us.</p>
    
        <p class="center">
        
        <iframe src="https://player.vimeo.com/video/140200465" width="100%" height="430" frameborder="0" webkitallowfullscreen="" mozallowfullscreen="" allowfullscreen=""></iframe>
        
        </p>

	<form class="howitworks" method="post" action="https://www.siteguarding.com/en/protect-your-website">
		<p class="submit startscanner">
		  <input type="submit" name="submit" class="ui green button" value="Protect My Website">
		</p>
	</form>
    
    <p>
    <a href="https://www.siteguarding.com/" target="_blank">SiteGuarding.com</a> - Website Security. Professional security services against hacker activity.<br />
    </p>
    <?php
}
?>

</div>
       
    
</div>
</div>	




<?php /*
if ($params['membership'] != 'pro') {
?>
<div class="divCell divCellReka">
	<div class="RekaBlock">
		<a href="https://www.siteguarding.com/en/website-extensions">
		<img class="effect7" src="<?php echo plugins_url('images/rek1.png', __FILE__); ?>" />
		</a>
	</div>
	
	<div class="RekaBlock">
		<a href="http://www.safetybis.com/">
		<img class="effect7" src="<?php echo plugins_url('images/rek2.png', __FILE__); ?>" />
		</a>
	</div>
	
	<div class="RekaBlock">
		<a href="https://www.siteguarding.com/en/prices">
		<img class="effect7" src="<?php echo plugins_url('images/rek3.png', __FILE__); ?>" />
		</a>
	</div>
	
	<div class="RekaBlock">
		<a href="https://www.siteguarding.com/en/sitecheck">
		<img class="effect7" src="<?php echo plugins_url('images/rek4.png', __FILE__); ?>" />
		</a>
	</div>
	
	<div class="RekaBlock">
		<a href="https://www.siteguarding.com/en/buy-service/antivirus-site-protection">
		<img class="effect7" src="<?php echo plugins_url('images/rek5.png', __FILE__); ?>" />
		</a>
	</div>
	
	<div class="RekaBlock">
		Remove these ads?<br />
		<a href="https://www.siteguarding.com/en/buy-service/antivirus-site-protection">Upgrade to PRO version</a>
	</div>
	
</div>
<?php
} */
?>

		
		<?php
        
        
               
        self::HelpBlock();      

	}
	
	
	
	public static function ScanProgress($session_id = '', $wp_path = '/', $params = array(), $avp_license_info = array())
	{
		$domain = get_site_url();
		$session_report_key = md5($domain.'-'.rand(1,1000).'-'.time());
		?>
		
        <script>
            jQuery(document).ready(function(){
            	
            	var refreshIntervalId;
            	
         		<?php
               	$ajax_url = plugins_url('/ajax.php', __FILE__);
               	?>
               	var link = "<?php echo $ajax_url; ?>";

				jQuery.post(link, {
					    action: "StartScan_AJAX",
					    scan_path: "<?php echo base64_encode($wp_path); ?>",
						session_id: "<?php echo $session_id; ?>",
						access_key: "<?php echo $params['access_key']; ?>",
						session_report_key: "<?php echo $session_report_key; ?>",
						do_evristic: "<?php echo $params['do_evristic']; ?>",
						domain: "<?php echo get_site_url(); ?>",
						email: "<?php echo get_option( 'admin_email' ); ?>",
						membership: "<?php echo $avp_license_info['membership']; ?>"
					},
					function(data){
						
                       if (data!='') ShowReportText(data);
						
						/*if (data=='') 
						{
							alert('Your server lost connection. You will be redirected to SiteGuarding.com to view your report.');
							document.location.href = 'https://www.siteguarding.com/antivirus/viewreport?report_id=<?php echo $session_report_key; ?>';
							return;
						}
						
						ShowReportText(data);*/
					}
				);
				
				
				function GetProgress()
				{
	         		<?php
	               	$ajax_url = plugins_url('/ajax.php', __FILE__);
	               	?>
	               	var link = "<?php echo $ajax_url; ?>";
	
					jQuery.post(link, {
						    action: "GetScanProgress_AJAX",
							session_id: "<?php echo $session_id; ?>"
						},
						function(data){
						    var tmp_data = data.split('|');
						    jQuery("#progress_bar_txt").html(tmp_data[0]+'% - '+tmp_data[1]);
						    jQuery("#progress_bar_process").css('width', parseInt(tmp_data[0])+'%');
						    if (parseInt(tmp_data[2]) == 1)
						    {
						    	// Try to load report directly from SiteGuarding.com
						    	TryToGetReport();
						    }
						}
					);	
				}
				
				
				function TryToGetReport()
				{
	         		<?php
	               	$ajax_url = plugins_url('/ajax.php', __FILE__);
	               	?>
	               	var link = "<?php echo $ajax_url; ?>";
	
					jQuery.post(link, {
						    action: "GetScanReport_AJAX",
							session_report_key: "<?php echo $session_report_key; ?>"
						},
						function(data){
							if (data == '') return;
							ShowReportText(data);
						}
					);
				}
				
				function ShowReportText(data)
				{
					jQuery("#progress_bar_process").css('width', '100%');
					jQuery("#progress_bar").hide();
					
					clearInterval(refreshIntervalId);
					
                    jQuery("#report_area").html(data);
                    jQuery("#back_bttn").show();
                    jQuery("#help_block").show();
                    jQuery("#rek_block").hide();	
                    jQuery(".avp_reviewreport_block").hide();	
				}
				
				refreshIntervalId =  setInterval(GetProgress, 3000);
				
            });
        </script>
        
        <p class="msg_box msg_info avp_reviewreport_block">If the scanning process takes too long. Get the results using the link<br /><a href="https://www.siteguarding.com/antivirus/viewreport?report_id=<?php echo $session_report_key; ?>" target="_blank">https://www.siteguarding.com/antivirus/viewreport?report_id=<?php echo $session_report_key; ?></a></p>
        
        <div id="progress_bar"><div id="progress_bar_process"></div><div id="progress_bar_txt">Scanning process started...</div></div>
        
        <div id="report_area"></div>
        
        <div id="help_block" style="display: none;">
		
		<a href="http://www.siteguarding.com" target="_blank">SiteGuarding.com</a> - Website Security. Professional security services against hacker activity.
		
		</div>
        
        <a id="back_bttn" style="display: none;" class="button button-primary" href="admin.php?page=plgavp_Antivirus">Back</a>
        
        <div id="rek_block">
			<a href="https://www.siteguarding.com" target="_blank">
				<img class="effect7" src="<?php echo plugins_url('images/rek_scan.jpg', __FILE__); ?>">
			</a>
		</div>


		
		<?php
	}
	
	


	public static function GetLicenseInfo($domain, $access_key)
	{
	    $cache_license_info = plgwpavp_GetExtraParams();

        if (isset($cache_license_info['cache_license_info']) && isset($cache_license_info['cache_license_info_time']))
        {
            if (time() - intval($cache_license_info['cache_license_info_time']) < 60 * 60)      // 60 mins
            {
                $cache_license_info = (array)json_decode($cache_license_info['cache_license_info'], true);
                if ($cache_license_info === false) plgwpavp_UpdateSQLStructure();
                return $cache_license_info;
            }
        }
        
        if (!isset($cache_license_info['cache_license_info']) && isset($cache_license_info['cache_license_info_time']))
        {
            plgwpavp_UpdateSQLStructure();
        }
        
		$link = SITEGUARDING_SERVER.'?action=licenseinfo&type=json&data=';
		
	    $data = array(
			'domain' => $domain,
			'access_key' => $access_key,
            'product_type' => 'wp'
		);
	    $link .= base64_encode(json_encode($data));
	    //$msg = file_get_contents($link);
		/*include_once(dirname(__FILE__).'/HttpClient.class.php');
		$HTTPClient = new HTTPClient();
		
		$msg = $HTTPClient->get($link);
	    
	    $msg = trim($msg);
        
        if ($msg == '') 
        {
            $link = str_replace("http://", "https://", $link);
            $msg = $HTTPClient->get($link);
        }
	    if ($msg == '') return false;*/
        include_once(dirname(__FILE__).'/EasyRequest.min.php');
        
        $client = EasyRequest::create($link);
        $client->send();
        $msg = $client->getResponseBody();
    
	    $msg = trim($msg);
        
        if ($msg == '') 
        {
            $link = str_replace("http://", "https://", $link);
            $client = EasyRequest::create($link);
            $client->send();
            $msg = $client->getResponseBody();
        }
	    if ($msg == '') return false;
	    
	    $cache_license_info = (array)json_decode($msg, true);
        $data = array(
            'cache_license_info' => json_encode($cache_license_info),
            'cache_license_info_time' => time()
        );
        plgwpavp_SetExtraParams($data);

	    return $cache_license_info;
	}
    
	public static function GetUpdateInfo($domain, $access_key, $version = '')
	{
		$link = SITEGUARDING_SERVER.'?action=updateinfo';
		
	    $data = array(
			'domain' => $domain,
			'access_key' => $access_key,
			'version' => $version,
            'product_type' => 'wp'
		);
        $post_data['data'] = base64_encode(json_encode($data));
        
        //echo print_r($data, true); echo $link;exit;
	    //$msg = file_get_contents($link);
		/*include_once(dirname(__FILE__).'/HttpClient.class.php');
		$HTTPClient = new HTTPClient();
		
		$msg = $HTTPClient->post($link, $post_data);
	    
	    $msg = trim($msg);
        
        if ($msg == '') 
        {
            $link = str_replace("http://", "https://", $link);
            $msg = $HTTPClient->post($link, $post_data);
        }
	    if ($msg == '') return false;*/
        include_once(dirname(__FILE__).'/EasyRequest.min.php');
        
        $client = EasyRequest::create('POST', $link, array(
            'form_params' => $post_data)
        );
        $client->send();
        $msg = $client->getResponseBody();
		
	    $msg = trim($msg);
        
        if ($msg == '') 
        {
            $link = str_replace("http://", "https://", $link);
            $client = EasyRequest::create('POST', $link, array(
                'form_params' => $post_data)
            );
            $client->send();
            $msg = $client->getResponseBody();
        }
	    if ($msg == '') return false;
	    
	    return (array)json_decode($msg, true);
	}
	
	
	public static function GetProgressInfo($domain, $access_key)
	{
		$link = SITEGUARDING_SERVER.'?action=progressinfo&type=json&data=';
		
	    $data = array(
			'domain' => $domain,
			'access_key' => $access_key
		);
	    $link .= base64_encode(json_encode($data));
	    //$msg = file_get_contents($link);
		/*include_once(dirname(__FILE__).'/HttpClient.class.php');
		$HTTPClient = new HTTPClient();
		
		$msg = $HTTPClient->get($link);
	    
	    $msg = trim($msg);
        if ($msg == '') 
        {
            $link = str_replace("http://", "https://", $link);
            $msg = $HTTPClient->get($link);
        }
	    if ($msg == '') return false;*/
        include_once(dirname(__FILE__).'/EasyRequest.min.php');
        
        $client = EasyRequest::create($link);
        $client->send();
        $msg = $client->getResponseBody();
    
	    $msg = trim($msg);
        
        if ($msg == '') 
        {
            $link = str_replace("http://", "https://", $link);
            $client = EasyRequest::create($link);
            $client->send();
            $msg = $client->getResponseBody();
        }
	    if ($msg == '') return false;
	    
	    return (array)json_decode($msg, true);
	}

	
	public static function sendRegistration($domain, $email, $access_key = '', $errors = '')
	{
		// Send data
	    $link = SITEGUARDING_SERVER.'?action=register&type=json&data=';
	    
	    $data = array(
			'domain' => $domain,
			'email' => $email,
			'access_key' => $access_key,
			'errors' => $errors
		);
	    $link .= base64_encode(json_encode($data));
	    //$msg = trim(file_get_contents($link));
		/*include_once(dirname(__FILE__).'/HttpClient.class.php');
		$HTTPClient = new HTTPClient();
		
		$msg = $HTTPClient->get($link);
	    
	    if ($msg == '') 
        {
            $lic_info = self::GetLicenseInfo($domain, $access_key);
            if (!is_array($lic_info))
            {
                $link = str_replace("http://", "https://", $link);
                $msg = $HTTPClient->get($link);
            }
        }
	    if ($msg == '') return true;
	    else return $msg;*/
        include_once(dirname(__FILE__).'/EasyRequest.min.php');
        
        $client = EasyRequest::create($link);
        $client->send();
        $msg = $client->getResponseBody();
    
	    $msg = trim($msg);
        
	    if ($msg == '') 
        {
            $lic_info = self::GetLicenseInfo($domain, $access_key);
            if (!is_array($lic_info))
            {
                $link = str_replace("http://", "https://", $link);
                $client = EasyRequest::create($link);
                $client->send();
                $msg = $client->getResponseBody();
            }
        }
	    if ($msg == '') return true;
	    else return $msg;
	}

	
	public static function checkServerSettings($return_error_names = false)
	{
		$error_name = array();
		$error = 0;
		
		// Check tmp folder is writable
		if (!is_writable(dirname(__FILE__).'/tmp/'))
		{
			chmod ( dirname(__FILE__).'/tmp/' , 0777 ); 
			if (!is_writable(dirname(__FILE__).'/tmp/'))
			{
				$error = 1;
				$error_name[] = 'tmp is not writable';
				self::ShowMessage('Folder '.dirname(__FILE__).'/tmp/'.' is not writable.');
				
				?>
				Please change folder <?php echo dirname(__FILE__).'/tmp/'; ?>permission to 777 to make it writable.
				<?php
			}
		}
		
		
		// Check ssh
		/*if ( !function_exists('exec') ) 
		{
		    if (!class_exists('ZipArchive'))
		    {
				$error = 1;
				$error_name[] = 'exec & ZipArchive';
				self::ShowMessage('ZipArchive class is not installed on your server.');
				
				?>
				Please ask your hoster support to install or enable PHP ZipArchive class for your server. More information about ZipArchive class please read here <a href="http://bd1.php.net/manual/en/class.ziparchive.php" target="_blank">http://bd1.php.net/manual/en/class.ziparchive.php</a>
				<?php
			}
		}*/
		
		
		// Check CURL
		if ( !function_exists('curl_init') ) 
		{
			$error = 1;
			$error_name[] = 'CURL';
			self::ShowMessage('CURL is not installed on your server.');
			
			?>
			Please ask your hoster support to install or enable CURL for your server.
			<?php
		}
		
		
		if ($return_error_names) return json_encode($error_name);
		if ($error == 1) return false;
		else return true;
	}
	
	public static function ShowMessage($txt)
	{
		echo '<div id="setting-error-settings_updated" class="updated settings-error"><p><strong>'.$txt.'</strong></p></div>';
	}
    
    
	public static function CheckWPLogin_file()
	{
	    if (!defined('DIRSEP'))
        {
    	    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') define('DIRSEP', '\\');
    		else define('DIRSEP', '/');
        }
        
		if (!defined('ABSPATH') || strlen(ABSPATH) < 8) 
		{
			$scan_path = dirname(__FILE__);
			$scan_path = str_replace(DIRSEP.'wp-content'.DIRSEP.'plugins'.DIRSEP.'wp-antivirus-site-protection', DIRSEP, $scan_path);
    		//echo TEST;
		}
        else $scan_path = ABSPATH;
        
        $filename = $scan_path.DIRSEP.'wp-config.php';
        $handle = fopen($filename, "r");
        if ($handle === false) return false;
        $contents = fread($handle, filesize($filename));
        if ($contents === false) return false;
        fclose($handle);
        
        if (stripos($contents, '6DBB86C229DE-START') === false)     // Not found
        {
            self::PatchWPLogin_file();
        }
    }
    
	public static function PatchWPLogin_file($action = true)   // true - insert, false - remove
	{
	    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') define('DIRSEP', '\\');
		else define('DIRSEP', '/');
        
		$file = dirname(__FILE__).DIRSEP."sgantivirus.login.php";

        $integration_code = '<?php /* Siteguarding Block 6DBB86C229DE-START */if (substr($_SERVER["SCRIPT_FILENAME"], -12) == "wp-login.php") if(file_exists("'.$file.'"))include_once("'.$file.'");/* Siteguarding Block 6DBB86C229DE-END */?>';
        
        // Insert code
		if (!defined('ABSPATH') || strlen(ABSPATH) < 8) 
		{
			$scan_path = dirname(__FILE__);
			$scan_path = str_replace(DIRSEP.'wp-content'.DIRSEP.'plugins'.DIRSEP.'wp-antivirus-site-protection', DIRSEP, $scan_path);
    		//echo TEST;
		}
        else $scan_path = ABSPATH;
        
        $filename = $scan_path.DIRSEP.'wp-config.php';
        $handle = fopen($filename, "r");
        if ($handle === false) return false;
        $contents = fread($handle, filesize($filename));
        if ($contents === false) return false;
        fclose($handle);
        
        $pos_code = stripos($contents, '6DBB86C229DE');
        
        if ($action === false)
        {
            // Remove block
            $contents = str_replace($integration_code, "", $contents);
        }
        else {
            // Insert block
            if ( $pos_code !== false/* && $pos_code == 0*/)
            {
                // Skip double code injection
                return true;
            }
            else {
                // Insert
                $contents = $integration_code.$contents;
            }
        }
        
        $handle = fopen($filename, 'w');
        if ($handle === false) 
        {
            // 2nd try , change file permssion to 666
            $status = chmod($filename, 0666);
            if ($status === false) return false;
            
            $handle = fopen($filename, 'w');
            if ($handle === false) return false;
        }
        
        $status = fwrite($handle, $contents);
        if ($status === false) return false;
        fclose($handle);

        
        return true;
	}
    
	
	
	public static function HelpBlock()
	{
		?>

		<p>
		For more information and details about Antivirus Site Protection please <a target="_blank" href="https://www.siteguarding.com/en/antivirus-site-protection">click here</a>.<br /><br />
		<a href="http://www.siteguarding.com/livechat/index.html" target="_blank">
			<img src="<?php echo plugins_url('images/livechat.png', __FILE__); ?>"/>
		</a><br />
		For any questions and support please use LiveChat or this <a href="https://www.siteguarding.com/en/contacts" rel="nofollow" target="_blank" title="SiteGuarding.com - Website Security. Professional security services against hacker activity. Daily website file scanning and file changes monitoring. Malware detecting and removal.">contact form</a>.<br>
		<br>
		<a href="https://www.siteguarding.com/" target="_blank">SiteGuarding.com</a> - Website Security. Professional security services against hacker activity.<br />
		</p>
		<?php
	}
	
}



class AVP_SEO_SG_Protection
{
    public static $search_words = array(
        0 => 'document.write(',
    	6 => 'document.createElement(',
    	20 => 'display:none',
    	21 => 'poker',
    	22 => 'casino',
    	48=> 'hacked',
    	49=> 'cialis ',
    	52=> 'viagra '
    );
    
    
    public static function PrepareResults($results)
    {
        $a = array(
            'WORDS' => array(),
            'A' => array(),
            'IFRAME' => array(),
            'SCRIPT' => array()
        );
        
        //return $results;
        
        if (count($results['posts']['WORDS']))
        {
            foreach ($results['posts']['WORDS'] as $post_id => $post_arr)
            {
                foreach ($post_arr as $word)
                {
                    $a['WORDS'][$word] = $word;
                }
            }
        }
        
        if (count($results['posts']['A']))
        {
            foreach ($results['posts']['A'] as $posts)
            {
                if (count($posts))
                {
                    foreach ($posts as $post_id => $post_arr)
                    {
                        if (count($post_arr))
                        {
                            foreach ($post_arr as $post_link => $post_txt)
                            {
                                $a['A'][$post_link] = $post_txt;
                            }
                        }
                    }
                }
            }
        }
        
        
        if (count($results['posts']['IFRAME']))
        {
            foreach ($results['posts']['IFRAME'] as $posts)
            {
                if (count($posts))
                {
                    foreach ($posts as $post_id => $post_link)
                    {
                        $a['IFRAME'][$post_link] = $post_link;
                    }
                }
            }
        }
        
        //print_r($results['posts']['IFRAME']);exit;
        if (count($results['posts']['SCRIPT']))
        {
            foreach ($results['posts']['SCRIPT'] as $post_id => $post_arr)
            {
                foreach ($post_arr as $js_link => $js_code)
                {
                    if (strpos($js_link, "javascript code") !== false) $a['SCRIPT'][md5($js_code)] = $js_code;
                    else $a['SCRIPT'][md5($js_link)] = $js_link;
                }
            }
        }
        
        //echo '0000'.$post_link;exit;
        //print_r($a); exit;
        
        ksort($a['A']);
        ksort($a['SCRIPT']);
        sort($a['IFRAME']);
        return $a;
        
    }


    public static function GetPostTitle_by_ID($post_id)
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'posts';
        
        $rows = $wpdb->get_results( 
        	"
        	SELECT post_title
        	FROM ".$table_name."
            WHERE ID = ".$post_id."
            LIMIT 1;
        	"
        );
        
        if (count($rows)) return $rows[0]->post_title;
        else return false;
    }
    
    public static function GetPostTitles_by_IDs($post_ids = array())
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'posts';
        
        $rows = $wpdb->get_results( 
        	"
        	SELECT ID, post_title
        	FROM ".$table_name."
            WHERE ID IN (".implode(",", $post_ids).")
        	"
        );
        
        if (count($rows)) 
        {
            $a = array();
            foreach ($rows as $row)
            {
                $a[$row->ID] = $row->post_title;
            }
            return $a;
        }
        else return false;
    }
    
    public static function MakeAnalyze()
    {
        error_reporting(0);
        ignore_user_abort(true);
        set_time_limit(0);
        ini_set('memory_limit', '512M');
        
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'posts';
        
        $rows = $wpdb->get_results( 
        	"
        	SELECT ID, post_content AS val_data
        	FROM ".$table_name."
        	"
        );
        
        $a = array();
        if (count($rows))
        {
            include_once(dirname(__FILE__).DIRSEP.'simple_html_dom.php');
            
            $domain = self::PrepareDomain(get_site_url());
            
            $a['total_scanned'] = count($rows);
            
            foreach ($rows as $row)
            {
                //$post_content = $row->val_data;
				$post_content = "<html><body>".$row->val_data."</body></html>";
                
                foreach (self::$search_words as $find_block)
                {
                    if (stripos($post_content, $find_block) !== false)
                    {
                        $a['posts']['WORDS'][$row->ID][] = $find_block;
                    }
                }
                
                $html = str_get_html($post_content);
                
                if ($html !== false)
                {
                    $tmp_a = array();
                    
                    // Tag A
                    foreach($html->find('a') as $e) 
                    {
                        $link = strtolower(trim($e->href));
                        if (strpos($link, $domain) !== false) continue;     // Skip own links
                        if (strpos($link, "mailto:") !== false) continue;
                        if (strpos($link, "callto:") !== false) continue;
                        if ( $link[0] == '?' || $link[0] == '/' ) continue;
                        if ( $link[0] != 'h' && $link[1] != 't' && $link[2] != 't' && $link[3] != 'p' ) continue;
                        
                        //$tmp_s = $link.' <span class="color_light_grey">[Txt: '.strip_tags($e->outertext).']</span>';

                        /*$tmp_data = array(
                            'l' => $link,
                            't' => strip_tags($e->outertext)
                        );
                        $tmp_a[$link] = $tmp_data;*/
                        $tmp_a[$link] = strip_tags($e->outertext);
                        
                        $a['posts']['A'][$row->ID][] = $tmp_a;
                    }
                    
                    
                    
                    // Tag IFRAME
                    foreach($html->find('iframe') as $e) 
                    {
                        $link = strtolower(trim($e->src));
                        if (strpos($link, $domain) !== false) continue;     // Skip own links
                        if ( $link[0] == '?' || $link[0] == '/' ) continue;
                        if ( $link[0] != 'h' && $link[1] != 't' && $link[2] != 't' && $link[3] != 'p' ) continue;
                        
                        /*$tmp_data = array(
                            'l' => $link,
                            't' => 'iframe'
                        );
                        $tmp_a[$link] = $tmp_data;*/
                        
                        $a['posts']['IFRAME'][$row->ID][] = $link;
                    }
                    
                    
                    
                	// Tag SCRIPT
                	foreach($html->find('script') as $e)
                	{
                	    if (isset($e->src)) 
                        {
                            $link = strtolower(trim($e->src));
                        
                            if (strpos($link, $domain) !== false) continue;     // Skip own links
                            if ( $link[0] == '?' || $link[0] == '/' ) continue;
                            if ( $link[0] != 'h' && $link[1] != 't' && $link[2] != 't' && $link[3] != 'p' ) continue;
                            
                            $t = '';
                        }
                        else  {
                            $link = 'javascript code '.rand(1, 1000);
                            $t = $e->innertext;
                        }
                        
                        /*$tmp_data = array(
                            'l' => $link,
                            't' => $t
                        );*/
                        $tmp_a[$link] = $t;
                        
                        $a['posts']['SCRIPT'][$row->ID] = $tmp_a;
                    }
                    
                }
                
                unset($html);
            }
            
        }
        
        // save results
        $data = array(
            'progress_status' => 0,
            'results' => json_encode($a),
            'latest_scan_date' => date("Y-m-d H:i:s")
        );
        self::Set_Params($data);
    }
    
    
    
    public static function Get_Params($vars = array())
    {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'plgwpavp_config';
        
        $ppbv_table = $wpdb->get_results("SHOW TABLES LIKE '".$table_name."'" , ARRAY_N);
        if(!isset($ppbv_table[0])) return false;
        
        if (count($vars) == 0)
        {
            $rows = $wpdb->get_results( 
            	"
            	SELECT *
            	FROM ".$table_name."
            	"
            );
        }
        else {
            foreach ($vars as $k => $v) $vars[$k] = "'".$v."'";
            
            $rows = $wpdb->get_results( 
            	"
            	SELECT * 
            	FROM ".$table_name."
                WHERE var_name IN (".implode(',',$vars).")
            	"
            );
        }
        
        $a = array();
        if (count($rows))
        {
            foreach ( $rows as $row ) 
            {
            	$a[trim($row->var_name)] = trim($row->var_value);
            }
        }
    
        return $a;
    }
    
    
    public static function Set_Params($data = array())
    {
		global $wpdb;
		$table_name = $wpdb->prefix . 'plgwpavp_config';
    
        if (count($data) == 0) return;   
        
        foreach ($data as $k => $v)
        {
            $tmp = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . $table_name . ' WHERE var_name = %s LIMIT 1;', $k ) );
            
            if ($tmp == 0)
            {
                // Insert    
                $wpdb->insert( $table_name, array( 'var_name' => $k, 'var_value' => $v ) ); 
            }
            else {
                // Update
                $data = array('var_value'=>$v);
                $where = array('var_name' => $k);
                $wpdb->update( $table_name, $data, $where );
            }
        } 
    }


	public static function PrepareDomain($domain)
	{
	    $host_info = parse_url($domain);
	    if ($host_info == NULL) return false;
	    $domain = $host_info['host'];
	    if ($domain[0] == "w" && $domain[1] == "w" && $domain[2] == "w" && $domain[3] == ".") $domain = str_replace("www.", "", $domain);
	    //$domain = str_replace("www.", "", $domain);
	    
	    return $domain;
	}

}

/* Dont remove this code: SiteGuarding_Block_6C33B41CEC02 */
?>