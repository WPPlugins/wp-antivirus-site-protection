<?php
class SGAntiVirus_scanner
{
    public static  $scanner_version = '3.5';
    public static  $debug = true;
    
	public static  $bool_list = array(0 => 'FALSE', 1 => 'TRUE');
	
	public static $SITEGUARDING_SERVER = 'http://www.siteguarding.com/ext/antivirus/index.php';
    
    var $antivirus_version = '';
    var $antivirus_platform = '';
    var $antivirus_cms = '';
    
    var $work_dir = '';
    var $tmp_dir = '';
    var $membership = '';
    var $scan_path = '';
    var $access_key = '';
    var $domain = '';
    var $email = '';
    var $session_report_key = '';
    
    var $exclude_folders_real = array();

    var $license_info = array();
    
    public static $max_filesize = 1024000;  // in bytes
    
    
    
    
	public function AntivirusFinished()
	{
	    if (self::$debug) self::DebugLog('line');
        
	    $reason = error_get_last();
        if (self::$debug) self::DebugLog(print_r($reason, true));
		if (self::$debug) self::DebugLog('PHP process has been terminated');
		
		$fp = fopen($this->tmp_dir.'flag_terminated.tmp', 'w');
		$a = date("Y-m-d H:i:s")." Terminated";
		fwrite($fp, $a);
		fclose($fp);
        
        if ($reason['type'] == 1) echo 'Error: '.$reason['message'].' File: '.$reason['message'].' Line: '.$reason['line'];	
	}


	
	public function AntivirusFileLock()
	{
	    $lockFile = $this->tmp_dir.'scan.lock';
		
		$lockFp = fopen($lockFile, 'w');
		
		flock($lockFp, LOCK_UN);
		unlink($lockFile);
	}
    


    
	public function scanner($check_session = true, $show_results = true)
	{
	    // Start scanning process
        error_reporting(0);
		ini_set('memory_limit', '256M');
        
	    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') define(DIRSEP, '\\');
		else define(DIRSEP, '/');
        
        
		// Skip the 2nd scan process
		$lockFile = $this->tmp_dir.'scan.lock';
		
		if (file_exists($lockFile) && (time() - filemtime($lockFile)) < 60*5)
		{
			$error_msg = 'Another Scanning Process in the memory. Exit.';
			if (self::$debug) self::DebugLog($error_msg);
			exit;
		}
		
		register_shutdown_function('self::AntivirusFileLock');
		
		$lockFp = fopen($lockFile, 'w');
        
        // Register any shutdown of the script
        register_shutdown_function('self::AntivirusFinished');
        
		
        
		$error_msg = 'Start Scan Process ver. '.$this->antivirus_version.' [scanner ver. '.self::$scanner_version.']';
		if (self::$debug) self::DebugLog($error_msg, true);
		
		
        
        // Load extra settings
		if (file_exists($this->work_dir.'settings.php'))
		{
			$error_msg = '=> Extra settings loaded';
			if (self::$debug) self::DebugLog($error_msg);
			
			require_once($this->work_dir.'settings.php');	
            
            if (count($avp_settings))
            {
                foreach ($avp_settings as $k => $v)
                {
                    $v_txt = $v;
                    if ($v === false) $v_txt = 'BOOL: false';
                    if ($v === true) $v_txt = 'BOOL: true';
                    
            		$error_msg = 'Setting Value: '.strtoupper($k).' = '.$v_txt;
            		if (self::$debug) self::DebugLog($error_msg);
                        
                    if (strtolower($v) == 'false') $v = false;
                    if (strtolower($v) == 'true') $v = true;
                    define(strtoupper($k), $v);
                }
            }
		}
        
        if ( trim($this->license_info['settings']) != '' )
        {
            if (self::$debug) self::DebugLog("Remote settings:\n".$this->license_info['settings']);
            
            // Parse the settings
            $remote_settings = explode("\n", trim($this->license_info['settings']));
            if (count($remote_settings))
            {
                foreach ($remote_settings as $v)
                {
                    $v = trim($v);
                    $v = explode(":", $v);
                    $v[0] = trim($v[0]);
                    $v[1] = trim($v[1]);
                    if (strtolower($v[1]) == 'false') $v[1] = false;
                    if (strtolower($v[1]) == 'true') $v[1] = true;
                    define(strtoupper($v[0]), $v[1]);
                }
            }
        }
        


		// Analyze of exclude folders
		if (file_exists($this->work_dir.'exclude_folders.php'))
		{
			$error_msg = '=> Exclude folders file loaded';
			if (self::$debug) self::DebugLog($error_msg);
			
			require_once($this->work_dir.'exclude_folders.php');	
		}
		
		
	
		$tmp_result = set_time_limit ( 7200 );
		
		$error_msg = 'Change Time limit: '.self::$bool_list[intval($tmp_result)].' , Value: '.ini_get('max_execution_time');
		if (self::$debug) self::DebugLog($error_msg);
		
		$error_msg = 'Current Memory limit: '.ini_get('memory_limit');
		if (self::$debug) self::DebugLog($error_msg);

		$error_msg = 'OS info: '.PHP_OS.' ('.php_uname().')';
		if (self::$debug) self::DebugLog($error_msg);
		
		$error_msg = 'PHP ver: '.PHP_VERSION;
		if (self::$debug) self::DebugLog($error_msg);
		
		unlink($this->tmp_dir.'flag_terminated.tmp');
        unlink($this->tmp_dir.'filelist_'.md5($this->access_key).'.txt');
		
		// Remove old version logs
		if (file_exists($this->tmp_dir.'filelist.txt')) unlink($this->tmp_dir.'filelist.txt');
		if (file_exists($this->tmp_dir.'debug.log')) unlink($this->tmp_dir.'debug.log');
		if (file_exists($this->tmp_dir.'pack.zip')) unlink($this->tmp_dir.'pack.zip');
		if (file_exists($this->tmp_dir.'pack.tar')) unlink($this->tmp_dir.'pack.tar');
		if (file_exists($this->tmp_dir.'report_excluded_folders.php')) unlink($this->tmp_dir.'report_excluded_folders.php');
        if (file_exists($this->tmp_dir.'report_excluded_files.php')) unlink($this->tmp_dir.'report_excluded_files.php');
        if (file_exists($this->tmp_dir.'antivirus_error.log')) unlink($this->tmp_dir.'antivirus_error.log');

		
	
        /*if (!class_exists("HTTPClient"))
        {
            include_once($this->work_dir.'HttpClient.class.php');
        }
		
		$HTTPClient = new HTTPClient();*/
		
	
		// Some Init data
		$membership = $this->membership;
		$scan_path = $this->scan_path; 
		$access_key = $this->access_key;
		$domain = $this->domain;
		$email = $this->email;
		$session_report_key = $this->session_report_key; 
		
			// Some logs
			$error_msg = 'Domain: '.$domain;
			if (self::$debug) self::DebugLog($error_msg);
			
			$error_msg = 'Scan path: '.$scan_path;
			if (self::$debug) self::DebugLog($error_msg);
			
			$error_msg = 'Session report key: '.$session_report_key;
			if (self::$debug) self::DebugLog($error_msg);
            
			$error_msg = 'Report URL: https://www.siteguarding.com/antivirus/viewreport?report_id='.$session_report_key;
			if (self::$debug) self::DebugLog($error_msg);
			
			$error_msg = 'TMP folder: '.$this->tmp_dir;
			if (self::$debug) self::DebugLog($error_msg);
			
			
		if (file_exists($scan_path.'.htaccess.siteguarding')) 
        {
            $error_msg = 'Found: .htaccess.siteguarding';
            if (self::$debug) self::DebugLog($error_msg);
            
            if (!rename( $scan_path.'.htaccess.siteguarding', $scan_path.'.htaccess'))
            {
                $error_msg = 'Cant rename: .htaccess.siteguarding -> .htaccess';
                if (self::$debug) self::DebugLog($error_msg);
            }
        }
            

		if (trim($domain) == '') {$error_msg = 'Domain is empty. Please contact SiteGuarding.com support.';echo $error_msg;if (self::$debug) self::DebugLog($error_msg);exit;}
		if (trim($session_report_key) == '') {$error_msg = 'Session key is empty. Please contact SiteGuarding.com support.';echo $error_msg;if (self::$debug) self::DebugLog($error_msg);exit;}
		if (trim($scan_path) == '') {$error_msg = 'Scan Path is empty. Please contact SiteGuarding.com support.';echo $error_msg;if (self::$debug) self::DebugLog($error_msg);exit;}
			
		
		//session_start();
		$current_task = 0;
		$total_tasks = 0;
		$total_tasks += 1;	// Analyze what way to use for packing
		$total_tasks += 1;	// Pack files
		$total_tasks += 1;	// Send files
		$total_tasks += 1;	// Get report

		
		/**
		 * Analyze what way to use for packing
		 */
	 	$ssh_flag = false;
		if ( function_exists('exec') ) 
		{
			// Pack files with ssh 
			$ssh_flag = true;
		}
        if (defined('SETTINGS_ONLY_ZIP') && SETTINGS_ONLY_ZIP) $ssh_flag = false;
		// Update progress
		$current_task += 1;
		self::UpdateProgressValue($current_task, $total_tasks, 'Initialization.');
		
        
        if (self::$debug) self::DebugLog('line');
        


        

        $files_list = array();
        if (defined('DEBUG_FILELIST') && DEBUG_FILELIST) self::DebugFile($this->work_dir, true);
        
        
		$error_msg = 'Collecting info about the files [METHOD 2]';
		if (self::$debug) self::DebugLog($error_msg);        
        
        
        $exclude_folders_real = array();
		if (count($exclude_folders))
		{
			foreach ($exclude_folders as $k => $ex_folder)
			{
				$ex_folder = $scan_path.trim($ex_folder);
				$exclude_folders_real[$k] = trim(str_replace(DIRSEP.DIRSEP, DIRSEP, $ex_folder));	
			}
		}
		else $exclude_folders_real = array(); 
        
            if (defined('SCAN_ALL_SITES') && SCAN_ALL_SITES == 1) 
            {
                // No action
            }
            else {
                // Exclude other sites from this scan
        		$error_msg = 'Check for other websites';
        		if (self::$debug) self::DebugLog($error_msg); 
                
                $dir_array = array();
                if ($currentDir = opendir($scan_path)) 
                {
                    while ($file = readdir($currentDir)) 
                    {
                        if (is_dir($scan_path.$file)) 
                        {
                            if ($file == '.' || $file == '..') continue;
                            $dir_array[] = $file;
                        }
                
                    }
                    closedir($currentDir);
                }
                sort($dir_array);
                
                $cms_dirs = array();
                
                foreach ($dir_array as $folder)
                {
                	if ( file_exists( $scan_path.DIRSEP.$folder.DIRSEP."configuration.php" ) || file_exists( $scan_path.DIRSEP.$folder.DIRSEP."wp-config.php" ) )
                	{
                		$cms_dirs[] = DIRSEP.$folder.DIRSEP;
                	}
                }
                
                if (count($cms_dirs))
                {
            		$error_msg = 'Found '.count($cms_dirs).' websites';
            		if (self::$debug) self::DebugLog($error_msg); 
                    
        			foreach ($cms_dirs as $k => $ex_folder)
        			{
        				$ex_folder = $scan_path.trim($ex_folder);
                        $ex_folder = trim(str_replace(DIRSEP.DIRSEP, DIRSEP, $ex_folder));
                        if (!in_array($ex_folder, $exclude_folders_real)) $exclude_folders_real[] = $ex_folder;
        			}
                }
            }  
             
                            // Remote exclude folders
                            if (defined('EXCLUDE_FOLDERS'))
                            {
                                $remote_excluded_folders = explode(",", EXCLUDE_FOLDERS);
                                if ( count($remote_excluded_folders))
                                {
                        			foreach ($remote_excluded_folders as $k => $ex_folder)
                        			{
                        				$ex_folder = $scan_path.trim($ex_folder);
                                        $ex_folder = trim(str_replace(DIRSEP.DIRSEP, DIRSEP, $ex_folder));
                                        if (!in_array($ex_folder, $exclude_folders_real)) $exclude_folders_real[] = $ex_folder;
                        			}
                                }
                            }
                            
                                    // Remote include folders
                                    if (defined('INCLUDE_FOLDERS'))
                                    {
                                        $remote_included_folders = explode(",", INCLUDE_FOLDERS);
                                        if ( count($remote_included_folders))
                                        {
                                			foreach ($remote_included_folders as $k => $in_folder)
                                			{
                                				$in_folder = $scan_path.trim($in_folder);
                                                $in_folder = trim(str_replace(DIRSEP.DIRSEP, DIRSEP, $in_folder));
                                                if (in_array($in_folder, $exclude_folders_real)) 
                                                {
                                                    unset( $exclude_folders_real[array_search($in_folder, $exclude_folders_real)] );
                                                }
                                			}
                                        }
                                    }
        
        // Check if exluded folders are on the server
        if (count($exclude_folders_real))
        {
            $exclude_folders_real_short = array();
            foreach($exclude_folders_real as $k => $v)
            {
                if (!file_exists($v)) unset($exclude_folders_real[$k]);
                else $exclude_folders_real_short[] = str_replace($scan_path, DIRSEP, $v);
            }
            
            if (defined('HIDE_EXCLUDE_ALERT') && HIDE_EXCLUDE_ALERT == 1) 
            {
                // Empty
            }
            else {
        		$file_report_excluded_folders = $this->tmp_dir.'report_excluded_folders.php';
        
        		$fp = fopen($file_report_excluded_folders, 'w');
        		$status = fwrite($fp, "<?php die(); ?>\n".json_encode($exclude_folders_real_short));
        		fclose($fp);
            }
        }
        

        $this->exclude_folders_real = $exclude_folders_real; 
        
        
		$error_msg = 'Excluded Folders: '.count($exclude_folders_real);
		if (self::$debug) self::DebugLog($error_msg);
		
		$error_msg = print_r($exclude_folders_real, true);
		if (self::$debug && count($exclude_folders_real) > 0) self::DebugLog($error_msg); 
        
        $dirList = array();
        $dirList[] = $scan_path;
        
        
 
                
        // Scan all dirs
        while (true) 
        {
            $dirList = array_merge(self::ScanFolder(array_shift($dirList), $files_list), $dirList);
            if (count($dirList) < 1) break;
        }
        
        
		$error_msg = 'Save collected file_list';
		if (self::$debug) self::DebugLog($error_msg);

		$collected_filelist = $this->tmp_dir.'filelist_'.md5($this->access_key).'.txt';


		$fp = fopen($collected_filelist, 'w');
		$status = fwrite($fp, implode("\n", $files_list));
		fclose($fp);
		if ($status === false)
		{
			$error_msg = 'Cant save information about the collected files '.$collected_filelist;
			if (self::$debug) self::DebugLog($error_msg);
			
			// Turn ZIP mode
			$ssh_flag = false;
		}
		
		$error_msg = 'Total files: '.count($files_list);
		if (self::$debug) self::DebugLog($error_msg);
        
        


        if (self::$debug) self::DebugLog('line');



	
		if ($ssh_flag)
		{
			// SSH way
				$error_msg = 'Start - Pack with SSH';
				if (self::$debug) self::DebugLog($error_msg);
				
			$cmd = 'cd '.$scan_path.''."\n".'tar -czf '.$this->tmp_dir.'pack_'.md5($this->access_key).'.tar -T '.$collected_filelist;

			$output = array();
			$result = exec($cmd, $output);
			
			if (file_exists($this->tmp_dir.'pack_'.md5($this->access_key).'.tar') === false) 
			{
				$ssh_flag = false;
				
				$error_msg = 'Change pack method from SSH to PHP (ZipArchive)';
				if (self::$debug) self::DebugLog($error_msg);
			}
		}
		
        
        
    	if (!$ssh_flag) 
    	{
    		// PHP way
    			$error_msg = 'Start - Pack with ZipArchive';
    			if (self::$debug) self::DebugLog($error_msg);
    		
    	    	$file_zip = $this->tmp_dir.'pack_'.md5($this->access_key).'.zip';

    	    	if (file_exists($file_zip)) unlink($file_zip);
    	    	$pack_dir = $scan_path;
                
                	
    	    if (class_exists('ZipArchive') && ( defined('DISABLE_ZIPARCHIVE') && DISABLE_ZIPARCHIVE === false ) )
    	    {
    	        // open archive
    	        $zip = new ZipArchive;
    	        if ($zip->open($file_zip, ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE) === TRUE) 
    	        {
    	            foreach ($files_list as $file_name) 
    	            {
    	            	$file_name = $this->scan_path.$file_name;
    	                if( strstr(realpath($file_name), "stark") == FALSE) 
    					{
    						$short_key = str_replace($scan_path, "", $file_name);
    	                	$s = $zip->addFile(realpath($file_name), $short_key);
    		                if (!$s) 
    		                {
    		                	$error_msg = 'Couldnt add file: '.$file_name; 
    		                	if (self::$debug) self::DebugLog($error_msg);
    		                }
    	            	}
    					
    				}
    	             // close and save archive
    	            $zip->close();
    	            
    	            //$result['msg'][] = 'Archive created successfully'; 
    	        }
    	        else {
    	        	$error_msg = 'Error: Couldnt open ZIP archive.';
    	        	//echo $error_msg;
    	            self::UpdateProgressValue($current_task, $total_tasks, $error_msg);
    				if (self::$debug) self::DebugLog($error_msg);
    				exit;
    	        }
    	
    	    }
    	    else {
    	    	$error_msg =  'Error: ZipArchive class is not exist.';
    	        if (self::$debug) self::DebugLog($error_msg);
    	    }
    	    
    		$error_msg = 'ZipArchive method - finished';
    		if (self::$debug) self::DebugLog($error_msg);
    		
    		// Check if zip file exists
    		if (!file_exists($file_zip))
    		{
    			$error_msg = 'Error: zip file is not exists. Use OwnZipClass';
    			if (self::$debug) self::DebugLog($error_msg);
    			
    			$error_msg = 'OwnZipClass method - started';
    			if (self::$debug) self::DebugLog($error_msg);
                
    				$zip = new Zip();
    				$zip->setZipFile($file_zip);
    	            foreach ($files_list as $file_name_short) 
    	            {
    	            	$file_name = trim($this->scan_path.$file_name_short);
						
						
							if (defined('DEBUG_ZIP_ADDFILES') && DEBUG_ZIP_ADDFILES === true)
							{
								$error_msg = 'Zip Add: '.$file_name;
								if (self::$debug) self::DebugLog($error_msg);
							}

    	            	$handle = fopen($file_name, "r");
    	            	if (filesize($file_name) > 0) $zip->addFile(fread($handle, filesize($file_name)), $file_name_short, filectime($file_name), NULL, TRUE, Zip::getFileExtAttr($file_name));
    	            	fclose($handle);
    	           	}
    	           	$zip->finalize();
               	
    			$error_msg = 'OwnZipClass method - finished';
    			if (self::$debug) self::DebugLog($error_msg);
                
                $ssh_flag = false; 
    		}
    		
    	}
        
        
        
        
        
		// Update progress
		$current_task += 1;
		self::UpdateProgressValue($current_task, $total_tasks, 'Collecting information about the files.');


		/**
		 * Send files to SG server
		 */
		if ($ssh_flag)
		{
	 		$archive_filename = $this->tmp_dir.'pack_'.md5($this->access_key).'.tar';
	 		$archive_format = 'tar';
		} else {
			$archive_filename = $this->tmp_dir.'pack_'.md5($this->access_key).'.zip';
			$archive_format = 'zip';
		}
		$error_msg = 'Pack file: '.$archive_filename;
		if (self::$debug) self::DebugLog($error_msg);

		
	 	
	 	// Check if pack file is exist
		if (file_exists($archive_filename) === false) 
		{
			$error_msg = 'Error: Pack file is not exist. Probably not enough space on the server.';
			if (self::$debug) self::DebugLog($error_msg);
			echo $error_msg;
			exit;
		}
        
		$tar_size = filesize($archive_filename);
		$error_msg = 'Pack file is '.round($tar_size/1024/1024, 2).'Mb';
		if (self::$debug) self::DebugLog($error_msg);
        
        
        
        if (self::$debug) self::DebugLog('line');
		
		

		
		
		$error_msg = 'Start - Send Packed files to SG server';
		if (self::$debug) self::DebugLog($error_msg);
		
        $archive_file_url = "/".str_replace($this->scan_path, "", $this->tmp_dir).'pack_'.md5($this->access_key).'.'.$archive_format;
        $archive_file_url = str_replace("\\", "/", $archive_file_url);
		$error_msg = 'Pack URL: '.$archive_file_url;
		if (self::$debug) self::DebugLog($error_msg);
		
		if ($tar_size < 32 * 1024 * 1024 || $membership == 'pro')
    	{
    		// Send file
    		$post_data = base64_encode(json_encode(array(
    				'domain' => $domain,
    				'access_key' => $access_key,
    				'email' => $email,
    				'session_report_key' => $session_report_key,
    				'archive_format' => $archive_format,
    				'archive_file_url' => $archive_file_url))
    		);
    
    		$flag_CallBack = false;
    		if (defined('CALLBACK_PACK_FILE') && CALLBACK_PACK_FILE )
    		{	// Callback option
    			$flag_CallBack = true;
    		}
    		else {
    			$result = self::UploadSingleFile($archive_filename, 'uploadfiles_ver2', $post_data);
    			if ($result === false)
    			{
    				$error_msg = 'Can not upload pack file for analyze';
    				if (self::$debug) self::DebugLog($error_msg);
    				
    				$flag_CallBack = true;
    			}
    			else {
    				$error_msg = 'Pack file sent for analyze - OK';
    				if (self::$debug) self::DebugLog($error_msg);
    				
    				$flag_CallBack = false;
    			}
    		}
    		
    		
    		// CallBack method
    		if ($flag_CallBack)
    		{
    			$error_msg = 'Start to use CallBack method';
    			if (self::$debug) self::DebugLog($error_msg);
    			
    			
    			$post_data = base64_encode(json_encode(array(
    					'domain' => $domain,
    					'access_key' => $access_key,
    					'email' => $email,
    					'session_report_key' => $session_report_key,
    					'archive_format' => $archive_format,
    					'archive_file_url' => $archive_file_url))
    			);
    			
    			$result = self::UploadSingleFile_Callback($post_data);
    			
    			if ($result === false)
    			{
    				$error_msg = 'CallBack method - failed';
    				if (self::$debug) self::DebugLog($error_msg);
    				
    				$error_msg = 'Can not upload pack file for analyze';
    				if (self::$debug) self::DebugLog($error_msg);
    				echo $error_msg;
    				exit;
    			}
    			else {
    				$error_msg = 'CallBack method - OK';
    				if (self::$debug) self::DebugLog($error_msg);
    			}
    			
    		}
    
    		
    	}
		else {
			$error_msg = 'Pack file is too big ('.$error_msg.'), please contact SiteGuarding.com support or upgrade to PRO version.';
			if (self::$debug) self::DebugLog($error_msg);
			echo $error_msg;
            $error_msg = 'Website is huge or hosting account has other sites. Free version has limits. Contact SiteGuarding.com support.';
            self::ErrorLog($error_msg);
			exit;
		}
		// Update progress
		$current_task += 1;
		self::UpdateProgressValue($current_task, $total_tasks, 'Analyzing the files. Preparing the report.');
		




        if (self::$debug) self::DebugLog('line');
        

		/**
		 * Check and Get report from SG server
		 */
		$error_msg = 'Start - Report generating';
		if (self::$debug) self::DebugLog($error_msg);
		
        $use_https_flag = false;
		for ($i = 1; $i <= 10*60; $i++)
		{
			sleep(5);

			/*$post_data = array(
				'data'=> base64_encode(json_encode(array(
					'domain' => $domain,
					'access_key' => $access_key,
					'session_report_key' => $session_report_key)))
			);*/
            
    		$post_data = base64_encode(json_encode(array(
    				'domain' => $domain,
    				'access_key' => $access_key,
    				'session_report_key' => $session_report_key)));
	
			if ($use_https_flag === false)
            {
        		$link = self::$SITEGUARDING_SERVER.'?action=getreport_ver2&data='.$post_data;
        		$result_json = file_get_contents($link);
                
                if ($result_json === false) $use_https_flag = true;
            }

			if ($use_https_flag === true) 
			{
        		$link = str_replace("http://www.", "https://www.", self::$SITEGUARDING_SERVER).'?action=getreport_ver2&data='.$post_data;
        		$result_json = file_get_contents($link);
    			if ($result_json === false) 
    			{
    				$error_msg = 'Report can not be generated. Please try again or contact support';
    				if (self::$debug) self::DebugLog($error_msg);
    				echo $error_msg;
    				exit;
    			}
            }

			
			$result_json = (array)json_decode($result_json, true);

			//if (self::$debug) self::DebugLog(print_r($result_json, true));
            
			if ($result_json['status'] == 'ready') 
			{
				echo $result_json['text'];
                
				// Update progress
				$current_task += 1;
				self::UpdateProgressValue($current_task, $total_tasks, 'Done. Sending your report.');
				
				$error_msg = 'Done. Sending your report by email';
				if (self::$debug) self::DebugLog($error_msg);
				
				// Send email to user with the report
				$email_result = self::SendEmail($email, $result_json['text']);
                
				if ($email_result) $error_msg = 'Report Sent - OK';
                else $error_msg = 'Report Sent - FAILED'; 
				if (self::$debug) self::DebugLog($error_msg);
				
				exit;	
			}
		}
		

		
		$error_msg = 'Finished [Report is not sent]'."\n";
		if (self::$debug) self::DebugLog($error_msg);
		 
    }
    
    
    
    
    
    function ScanFolder($path, &$files_list)
    {
        $dirList = array();
    
        if ($currentDir = opendir($path)) 
        {
            while ($file = readdir($currentDir)) 
            {
                if ( $file === '.' || $file === '..' || is_link($path) ) continue;
                $file = $path . '/' . $file;
    
                
                if (is_dir($file)) 
                {
                    $folder = $file.DIRSEP;
                    $folder = str_replace(DIRSEP.DIRSEP, DIRSEP, $folder);
                    
                    // Exclude folders
                    if (count($this->exclude_folders_real))
                    {
                 		if (in_array($folder, $this->exclude_folders_real)) 
        				{
        				    if (self::$debug) self::DebugLog('--- '.$folder);
                            continue;
        				}
        				else if (self::$debug) self::DebugLog('+++ '.$folder);
                    }
                    $dirList[] = $file;
                    if (defined('DEBUG_FILELIST') && DEBUG_FILELIST) self::DebugFile($file);
                }
                else {
                    
                    if (is_link($file))
                    {
                        self::DebugLog('Symbolic link: '.$file);
                        continue;
                    }
                    
					//if (strpos($file, '.php.') || strpos($file, '.phtml.') || strpos($file, '.php3.') || strpos($file, '.php4.') || strpos($file, '.php5.'))
					if (strpos($file, '.php') || strpos($file, '.phtml'))
					{
					    $file_size = filesize($file);
                        if ($file_size > self::$max_filesize)
                        {
                            self::DebugLog('Big file: '.str_replace($this->scan_path, "", $file).' ['.$file_size.' bytes]');
                            self::HugeFilesLog(str_replace($this->scan_path, "", $file));
                            continue;
                        }
                        
                        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                        switch ($ext)
                        {
                            case 'tar':
                            case '7z':
                            case 'exe':
                            case 'gz':
                            case 'gzip':
                            case 'pak':
                            case 'pkg':
                            case 'rar':
                            case 'tar-gz':
                            case 'tgz':
                            case 'zip':
                                self::DebugLog('Excluded ext: '.$file);
                                continue;
                        }
                        
                        if (strlen($this->scan_path) > 1) $file = str_replace($this->scan_path, "", $file);
        				if ($file[0] == "\\" || $file[0] == "/") $file[0] = "";
        				$file = trim($file);
        				$files_list[] = $file;
					}
					else {
                        // Check extension
                        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                        switch ($ext)
                        {
                            case 'inc':
                            case 'php':
                            case 'php4':
                            case 'php5':
                            case 'phtml':
                            case 'js':
                            case 'html':
                            case 'htm':
                            case 'cgi':
                            case 'pl':
                            case 'so':
                            case 'sh':
                            case 'htaccess':
                            
        					    $file_size = filesize($file);
                                if ($file_size > self::$max_filesize)
                                {
                                    self::DebugLog('Big file: '.str_replace($this->scan_path, "", $file).' ['.$file_size.' bytes]');
                                    self::HugeFilesLog(str_replace($this->scan_path, "", $file));
                                    continue;
                                }
                                
                                if (strlen($this->scan_path) > 1) $file = str_replace($this->scan_path, "", $file);
                				if ($file[0] == "\\" || $file[0] == "/") $file[0] = "";
                				$file = trim($file);
                				$files_list[] = $file;
                                break;
                        }
                    }
                    
                }
    
            }
            closedir($currentDir);

        }
    
        return $dirList;
    }

    
    
    
    
    
    function DebugFile($file, $clean_log_file = false)
    {
		if ($clean_log_file) $fp = fopen($this->tmp_dir.'debug_filelist.log', 'w');
		else {
            $file = str_replace($this->scan_path, "", $file);
    	    $fp = fopen($this->tmp_dir.'debug_filelist.log', 'a');
		}

		$a = $file."\n";
		fwrite($fp, $a);
		fclose($fp); 
    }
    
    
    
    
    
	function UpdateProgressValue($current_task, $total_tasks, $current_step_txt)
	{
		$i = round( 100*$current_task/$total_tasks, 2 );
		
		$a = array(
			'txt' => $current_step_txt,
			'progress' => $i
		);
		
		$filename = $this->tmp_dir."antivirus_last_action.log";
		$fp = fopen($filename, 'w');
		fwrite($fp, json_encode($a));
		fclose($fp);
		
		sleep(3);
	}
    
    
    
    function UploadSingleFile($file, $action, $post_data)
    {
        $status = self::UploadSingleFile_extension($file, $action, $post_data);
        if ($status === false) $status = self::UploadSingleFile_extension($file, $action, $post_data, true);
        
        return $status;
    }
    
    
    function UploadSingleFile_extension($file, $action, $post_data, $https_flag = false)
    {
    	$target_url = self::$SITEGUARDING_SERVER;
        if ($https_flag === true) $target_url = str_replace("http://www.", "https://www.", $target_url);
    	$target_url = $target_url.'?action='.$action;
        
    	$file_name_with_full_path = $file;
        
        if (class_exists('CurlFile'))
        {
    		$error_msg = 'CURLOPT: CurlFile';
    		if (self::$debug) self::DebugLog($error_msg);
            
        	$post = array(
        		'data' => $post_data,
        		'file_contents' => new CurlFile($file_name_with_full_path)
        	);
        }
        else {
    		$error_msg = 'CURLOPT: @file';
    		if (self::$debug) self::DebugLog($error_msg);
            
        	$post = array(
        		'data' => $post_data,
        		'file_contents' => '@'.$file_name_with_full_path
        	);
        }
        
    	
    		$error_msg = 'CURL CURLOPT_INFILE: '.$file_name_with_full_path;
    		if (self::$debug) self::DebugLog($error_msg);
    		$error_msg = 'CURL CURLOPT_INFILESIZE: '.filesize($file_name_with_full_path);
    		if (self::$debug) self::DebugLog($error_msg);
    		$error_msg = 'Use HTTPS: '.self::$bool_list[intval($https_flag)];
    		if (self::$debug) self::DebugLog($error_msg);
    		
    
     	$ch = curl_init();
    	curl_setopt($ch, CURLOPT_URL,$target_url);
    	curl_setopt($ch, CURLOPT_POST,1);
        curl_setopt($ch, CURLOPT_SAFE_UPLOAD,false);
    	curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    	curl_setopt($ch, CURLOPT_INFILE, $file_name_with_full_path);
    	curl_setopt($ch, CURLOPT_INFILESIZE, filesize($file_name_with_full_path));
    	$result=curl_exec ($ch);
    	
    		$info = curl_getinfo($ch);
    		$error_msg = 'CURL info - '.print_r($info, true);
    		if (self::$debug) self::DebugLog($error_msg);
    		
    	$curl_error = curl_error($ch);
    	curl_close ($ch);
    	
    	if ($info['size_upload'] < 10000/*filesize($file_name_with_full_path)*/)
    	{
    		$error_msg = 'CURL uploaded file wrong size: '.$info['size_upload'];
    		if (self::$debug) self::DebugLog($error_msg);
    		
    		return false;
    	}
    
    	if (!$result) 
    	{
    		$error_msg = 'CURL upload is failed - '.$curl_error;
    		if (self::$debug) self::DebugLog($error_msg);
    		
    		return false;
    	}
    	else return true;
    }
    
    
    
    function UploadSingleFile_Callback($post_data)
    {
        $status = UploadSingleFile_Callback_extension($post_data);
        if ($status === false) $status = UploadSingleFile_Callback_extension($post_data, true);
        
        return $status;
    }
    
    function UploadSingleFile_Callback_extension($post_data, $https_flag = false)
    {
		$error_msg = 'Use HTTPS: '.self::$bool_list[intval($https_flag)];
		if (self::$debug) self::DebugLog($error_msg);
        
    	$link = self::$SITEGUARDING_SERVER;
        if ($https_flag === true) $link = str_replace("http://www.", "https://www.", $link);
    	$link = $link.'?action=uploadfiles_callback';

    	$postdata = http_build_query(
    	    array(
    	        'data' => $post_data
    	    )
    	);
    	
    	$opts = array('http' =>
    	    array(
    	        'method'  => 'POST',
    	        'header'  => 'Content-type: application/x-www-form-urlencoded',
    	        'content' => $postdata
    	    )
    	);
    	
    	$context  = stream_context_create($opts);
    	
    	$result = file_get_contents($link, false, $context);
    	
    	$result = json_decode(trim($result), true);
    	
    	if (self::$debug) self::DebugLog(print_r($result, true));
    	
    	if ($result['status'] == 'ok') return true;
    	else return false;
    }
    
    
    
    function SendEmail($email, $result, $subject = '')
    {
    	$to  = $email; // note the comma
    	
    	// subject
    	if ($subject == '') $subject = 'AntiVirus Report ['.date("Y-m-d H:i:s").']';
    	
    	// message
        $body_message = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>SiteGuarding - Professional Web Security Services!</title>
</head>
<body bgcolor="#ECECEC" style="background-color:#ECECEC;">
<table cellpadding="0" cellspacing="0" width="100%" align="center" border="0" bgcolor="#ECECEC" style="background-color: #fff;">
  <tr>
    <td width="100%" align="center" bgcolor="#ECECEC" style="padding: 5px 30px 20px 30px;">
      <table width="750" border="0" align="center" cellpadding="0" cellspacing="0" bgcolor="#fff" style="background-color: #fff;">
        <tr>
          <td width="750" bgcolor="#fff"><table width="750" border="0" cellspacing="0" cellpadding="0" bgcolor="#fff" style="background-color: #fff;">
            <tr>
              <td width="350" height="60" bgcolor="#fff" style="padding: 5px; background-color: #fff;"><a href="http://www.siteguarding.com/" target="_blank"><img src="data:image/png;base64,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" alt="SiteGuarding - Protect your website from unathorized access, malware and other threat" height="60" border="0" style="display:block" /></a></td>
              <td width="400" height="60" align="right" bgcolor="#fff" style="background-color: #fff;">
              <table border="0" cellspacing="0" cellpadding="0" bgcolor="#fff" style="background-color: #fff;">
                <tr>
                  <td style="font-family:Arial, Helvetica, sans-serif; font-size:11px;"><a href="http://www.siteguarding.com/en/login" target="_blank" style="color:#656565; text-decoration: none;">Login</a></td>
                  <td width="15"></td>
                  <td width="1" bgcolor="#656565"></td>
                  <td width="15"></td>
                  <td style="font-family:Arial, Helvetica, sans-serif; font-size:11px;"><a href="http://www.siteguarding.com/en/prices" target="_blank" style="color:#656565; text-decoration: none;">Services</a></td>
                  <td width="15"></td>
                  <td width="1" bgcolor="#656565"></td>
                  <td width="15"></td>
                  <td style="font-family:Arial, Helvetica, sans-serif; font-size:11px;"><a href="http://www.siteguarding.com/en/what-to-do-if-your-website-has-been-hacked" target="_blank" style="color:#656565; text-decoration: none;">Security Tips</a></td>            
                  <td width="15"></td>
                  <td width="1" bgcolor="#656565"></td>
                  <td width="15"></td>
                  <td style="font-family:Arial, Helvetica, sans-serif;  font-size:11px;"><a href="http://www.siteguarding.com/en/contacts" target="_blank" style="color:#656565; text-decoration: none;">Contacts</a></td>
                  <td width="30"></td>
                </tr>
              </table>
              </td>
            </tr>
          </table></td>
        </tr>

        <tr>
          <td width="750" height="2" bgcolor="#D9D9D9"></td>
        </tr>
        <tr>
          <td width="750" bgcolor="#fff" ><table width="750" border="0" cellspacing="0" cellpadding="0" bgcolor="#fff" style="background-color:#fff;">
            <tr>
              <td width="750" height="30"></td>
            </tr>
            <tr>
              <td width="750">
                <table width="750" border="0" cellspacing="0" cellpadding="0" bgcolor="#fff" style="background-color:#fff;">
                <tr>
                  <td width="30"></td>
                  <td width="690" bgcolor="#fff" align="left" style="background-color:#fff; font-family:Arial, Helvetica, sans-serif; color:#000000; font-size:12px;">
                    {MESSAGE_CONTENT}
                    <br>
                    <b>URGENT SUPPORT</b><br>
                    Not sure in the report details? Need urgent help and support. Please contact us <a href="https://www.siteguarding.com/en/contacts" target="_blank">https://www.siteguarding.com/en/contacts</a>
                  </td>
                  <td width="30"></td>
                </tr>
              </table></td>
            </tr>
            <tr>
              <td width="750" height="15"></td>
            </tr>
            <tr>
              <td width="750" height="15"></td>
            </tr>
            <tr>
              <td width="750"><table width="750" border="0" cellspacing="0" cellpadding="0">
                <tr>
                  <td width="30"></td>
                  <td width="690" align="left" style="font-family:Arial, Helvetica, sans-serif; color:#000000; font-size:12px;"><strong>How can we help?</strong><br />
                    If you have any questions please dont hesitate to contact us. Our support team will be happy to answer your questions 24 hours a day, 7 days a week. You can contact us at <a href="mailto:support@siteguarding.com" style="color:#2C8D2C;"><strong>support@siteguarding.com</strong></a>.<br />
                    <br />
                    Thanks again for choosing SiteGuarding as your security partner!<br />
                    <br />
                    <span style="color:#2C8D2C;"><strong>SiteGuarding Team</strong></span><br />
                    <span style="font-family:Arial, Helvetica, sans-serif; color:#000; font-size:11px;"><strong>We will help you to protect your website from unauthorized access, malware and other threats.</strong></span></td>
                  <td width="30"></td>
                </tr>
              </table></td>
            </tr>
            <tr>
              <td width="750" height="30"></td>
            </tr>
          </table></td>
        </tr>
        <tr>
          <td width="750" height="2" bgcolor="#D9D9D9"></td>
        </tr>
      </table>
      <table width="750" border="0" cellspacing="0" cellpadding="0">
        <tr>
          <td width="750" height="10"></td>
        </tr>
        <tr>
          <td width="750" align="center"><table border="0" cellspacing="0" cellpadding="0">
            <tr>
              <td style="font-family:Arial, Helvetica, sans-serif; color:#ffffff; font-size:10px;"><a href="http://www.siteguarding.com/en/website-daily-scanning-and-analysis" target="_blank" style="color:#656565; text-decoration: none;">Website Daily Scanning</a></td>
              <td width="15"></td>
              <td width="1" bgcolor="#656565"></td>
              <td width="15"></td>
              <td style="font-family:Arial, Helvetica, sans-serif; color:#ffffff; font-size:10px;"><a href="http://www.siteguarding.com/en/malware-backdoor-removal" target="_blank" style="color:#656565; text-decoration: none;">Malware & Backdoor Removal</a></td>
              <td width="15"></td>
              <td width="1" bgcolor="#656565"></td>
              <td width="15"></td>
              <td style="font-family:Arial, Helvetica, sans-serif; color:#ffffff; font-size:10px;"><a href="http://www.siteguarding.com/en/update-scripts-on-your-website" target="_blank" style="color:#656565; text-decoration: none;">Security Analyze & Update</a></td>
              <td width="15"></td>
              <td width="1" bgcolor="#656565"></td>
              <td width="15"></td>
              <td style="font-family:Arial, Helvetica, sans-serif; color:#ffffff; font-size:10px;"><a href="http://www.siteguarding.com/en/website-development-and-promotion" target="_blank" style="color:#656565; text-decoration: none;">Website Development</a></td>
            </tr>
          </table></td>
        </tr>

        <tr>
          <td width="750" height="10"></td>
        </tr>
        <tr>
          <td width="750" align="center" style="font-family: Arial,Helvetica,sans-serif; font-size: 10px; color: #656565;">Add <a href="mailto:support@siteguarding.com" style="color:#656565">support@siteguarding.com</a> to the trusted senders list.</td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
';

    	$body_message = str_replace("{MESSAGE_CONTENT}", $result, $body_message);
    	
    	// To send HTML mail, the Content-type header must be set
    	$headers  = 'MIME-Version: 1.0' . "\r\n";
    	$headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";
    	
    	// Additional headers
    	$headers .= 'From: '. $to . "\r\n";
    	
    	// Mail it
    	return mail($to, $subject, $body_message, $headers);
    }
    
    
	public function DebugLog($txt, $clean_log_file = false)
	{
	    if ($txt == 'line') $txt = '-----------------------------------------------------------------------';
		if ($clean_log_file) $fp = fopen($this->tmp_dir.'debug_'.md5($this->access_key).'.log', 'w');
		else $fp = fopen($this->tmp_dir.'debug_'.md5($this->access_key).'.log', 'a');
		$a = date("Y-m-d H:i:s")." ".$txt."\n";
		fwrite($fp, $a);
		fclose($fp);
	}
    
	public function HugeFilesLog($txt)
	{
        $file = $this->tmp_dir.'report_excluded_files.php';
        if (!file_exists($file)) $txt = "<?php die(); ?>\n".$txt;
	    $fp = fopen($file, 'a');
		fwrite($fp, $txt."\n");
		fclose($fp);
	}
    
	public function ErrorLog($txt)
	{
	    $fp = fopen($this->tmp_dir.'antivirus_error.log', 'w');
		$a = date("Y-m-d H:i:s")." ".$txt."\n";
		fwrite($fp, $a);
		fclose($fp);
	}
}

/* Dont remove this code: SiteGuarding_Block_6C33B41CEC02 */
?>