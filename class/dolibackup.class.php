<?php
/**
 * @author   Simafri  https://simafri.com
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */



class Dolibackup
{
	/**
	* Add files and sub-directories in a folder to zip file.
	* @param string $folder
	* @param ZipArchive $zipFile
	* @param int $exclusiveLength Number of text to be exclusived from the file path.
	*/
	private static function folderToZip($folder, &$zipFile, $exclusiveLength) {
		global $dolibarr_main_url_root,$langs;
		$langs->load("dolibackup@dolibackup");
		$handle = opendir($folder);
		$install_name= str_replace("https://", "",$dolibarr_main_url_root);
	    $install_name= str_replace("http://", "",$install_name);
		if (!$handle){
	    	return $langs->trans("ErrorCompressingFolder",$folder)."\n";
	    }
		while (false !== $f = readdir($handle)) {
			if ($f != '.' && $f != '..' && $f != 'backup-'.$install_name.strftime("%Y%m%d")) {
				$filePath = "$folder/$f";
				// Remove prefix from file path before add to zip.
				$localPath = substr($filePath, $exclusiveLength);
				if (is_file($filePath)) {
					$ok = $zipFile->addFile($filePath, $localPath);

					if (! $ok){
	                	$errors .= $langs->trans("ErrorCompressingFile",$filePath)."\n";
	                }
				} elseif (is_dir($filePath)) {
					// Add sub-directory.
					$zipFile->addEmptyDir($localPath);
					$errors .= self::folderToZip($filePath, $zipFile, $exclusiveLength);
				}
			}
		}
		closedir($handle);
		if ($errors){
	    	return $errors;
	    }
	}

	/**
	* Zip a folder (include itself).
	* Usage:
	*   HZip::zipDir('/path/to/sourceDir', '/path/to/out.zip');
	*
	* @param string $sourcePath Path of directory to be zip.
	* @param string $outZipPath Path of output zip file.
	*/
	public static function zipDir($sourcePath, $outZipPath, $file_name='')
	{
		global $langs;
		$langs->load("dolibackup@dolibackup");
		$pathInfo = pathInfo($sourcePath);
		$parentPath = $pathInfo['dirname'];
		$dirName = $pathInfo['basename'];

		$z = new ZipArchive();
		$z->open($outZipPath, ZIPARCHIVE::CREATE);
		if (!is_file($sourcePath)){
			$z->addEmptyDir($dirName);
			$result .= self::folderToZip($sourcePath, $z, strlen("$parentPath/"));
		}
		else {
			$ok =  $z->addFile($sourcePath,$file_name);
			if (! $ok){
            	$result .= $langs->trans("ErrorCompressingFile",$sourcePath)."\n";
            }
		}
		
		$z->close();
		return $result;
	} 

	/*
	*	Checks the backups, check if the last execution of cron job lancer_dolibackup has made an error
	*   and change the next date run of the lancer_backup to now so it can be executed again without
	*   waiting 24 hours
	*/
	public function controle_dolibackup(){
		global $db,$user,$conf;
		$now = dol_now(); 
		$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."cronjob WHERE methodename = 'lancer_dolibackup'";

		$resql = $db->query($sql);
		$obj = $db->fetch_object($resql);
		require_once DOL_DOCUMENT_ROOT."/cron/class/cronjob.class.php";
		$cron = new Cronjob($db);
		$cron->fetch($obj->rowid);
		/*	In case the state of the cron job lancer_dolibackup hasn't updated after 
		*	the last execution, due to database instance expiry for example.
		*	Then we update and reprogram the cron job
		*/
		if (!$conf->global->DOLIBACKUP_EN_COURS && $cron->processing == 1){
			$cron->lastresult = $conf->global->RESULT_DOLIBACKUP;
			$cron->datelastresult = dol_now();
			$cron->processing = 0;
			$result = $cron->update($user); // This include begin/commit
			$cron->reprogram_jobs($user->login, $now);
		}
		/*
		* Here we check whether the last execution of lancer_backup has made an error
		* If so, change the next date of execution to now
		*/
		if ($cron->lastresult < 0){
			$cron->datenextrun = dol_now();
			$result = $cron->update($user);
		}

		return ($errormsg ? -1 : 0);

	}
	/*
	* Launch the backups
	*/
	public function lancer_dolibackup(){
		global $db, $conf,$dolibarr_main_db_name,$dolibarr_main_db_host,$dolibarr_main_db_user,$dolibarr_main_db_port,$dolibarr_main_db_pass,$dolibarr_main_document_root,$dolibarr_main_data_root,$dolibarr_main_url_root, $dolibarr_main_db_type,$langs;
		$langs->load("dolibackup@dolibackup");
		require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
		require_once DOL_DOCUMENT_ROOT.'/core/lib/functions.lib.php';
		// Set const DOLIBACKUP_EN_COURS to 1
		dolibarr_set_const($db, 'DOLIBACKUP_EN_COURS', '1', 'chaine', 0, '', $conf->entity);

		// remove https or http to install name
		$install_name= str_replace("https://", "",$dolibarr_main_url_root);
		$install_name= str_replace("http://", "",$install_name);
		//create a folder to contain the backup
    	$backup_folder =  DOL_DATA_ROOT.'/dolibackup/backup-'.$install_name.strftime("%Y%m%d");
    	dol_mkdir($backup_folder);
    	dol_include_once('/dolibackup/mysqldump-php/src/Ifsnop/Mysqldump/Mysqldump.php');

    	// Connect to the database using Mysqldump
    	$dump = new Ifsnop\Mysqldump\Mysqldump('mysql:host='.$dolibarr_main_db_host.';dbname='.$dolibarr_main_db_name, $dolibarr_main_db_user,$dolibarr_main_db_pass);
    	// Dump the database
    	try {
    		$dump->start($backup_folder.'/dump.sql');
    	} catch (Exception $e) {
   			$errormsg .= $langs->trans("ErrorDatabase",$e->getMessage()). "\n";
		}

		//COPY AND COMPRESSION OF DUMP FILE, DOLIBARR FILES, DOCUMENTS FOLDER AND SCRIPT FOLDER TO FOLDERS TO BE ARCHIVED

    	$file_zip = $backup_folder.'/'.$install_name.'-'.strftime("%Y%m%d").'.zip';
    	// Compression of the sql dump file
    	if (!$errormsg) $errormsg .= $this->zipDir($backup_folder.'/dump.sql', $file_zip, 'dump.sql');
		unlink($backup_folder.'/dump.sql');

		// Compression of dolibarr_main_document_root
    	if (!$errormsg) $errormsg .= $this->zipDir($dolibarr_main_document_root, $file_zip);
 		
 		// Compression of scripts folder
 		if (file_exists($dolibarr_main_document_root.'/../scripts') && !$errormsg){
 			$errormsg .= $this->zipDir($dolibarr_main_document_root.'/../scripts', $file_zip);
 		}
 		else if (file_exists($dolibarr_main_data_root.'/../scripts') && !$errormsg){
 			$errormsg .= $this->zipDir($dolibarr_main_data_root.'/../scripts', $file_zip);
 		}

    	// Compression of dolibarr_main_data_root
		if (!$errormsg) $errormsg .= $this->zipDir($dolibarr_main_data_root, $file_zip);
		
    	// FTP Credentials
	    $ftp_server=$conf->global->SERVEUR_FTP;
		$ftp_user_name=$conf->global->UTILISATEUR_FTP;
		$ftp_user_pass=$conf->global->MOT_DE_PASSE_FTP;
		$file = $file_zip;//tobe uploaded
		$remote_file = $install_name.'-'.strftime("%Y%m%d").'.zip';

		//Connect to the server
		if (!$errormsg){
			$conn_id = ftp_connect($ftp_server);
			if (!$conn_id) {
				$errormsg .= $langs->trans("FTPConnectionError")."\n";
			}
		}
		//Authenticate to the server
		if (!$errormsg){
			ftp_pasv($conn_id, true);
			$login_result = ftp_login($conn_id, $ftp_user_name, $ftp_user_pass);
			if (!$login_result) {
				$errormsg .=  $langs->trans("FTPAuthentificationError")."\n";
			}
		}
		// Transfer of the backup file
		if (!$errormsg){
			$result_ftp = ftp_put($conn_id, $remote_file, $file, FTP_BINARY);
			if (!$result_ftp) {
				$errormsg .= $langs->trans("FTPFileTransferError")."\n";
			}
		}

		// Delete backups if there is more than 15
		if (!$errormsg){
			// $contents will contain the names of all the files in the FTP directory
			$contents = ftp_nlist($conn_id, ".");

			//$contents_date will trim the names of the files to get the last part that contains the date of the backup so we can sort them
			$contents_date = array();
			foreach($contents as $content) 
	        {
	        	if (substr($content,-12,-4)){
	        		$contents_date [] = substr($content,-12,-4);
	        	}
	        	
	        }
			function date_sort($a, $b) {
			    return strtotime($a) - strtotime($b);
			}
			// sort $contents_date
			usort($contents_date, "date_sort");
			//Delete the backup of files if there is more than 15
			for ($i = 0; $i < count($contents_date) - 15 ; $i++){
				$content_to_delete = $install_name.'-'.$contents_date[$i].'.zip';
				if (in_array($content_to_delete, $contents)){
					$result_delete = ftp_delete($conn_id,$content_to_delete);
	            	if (!$result_delete) {
						$errormsg .= $langs->trans("FTPDeleteFilesError",$content_to_delete)."\n";
					}
				} 
			}
			
		}
		
		if ($conn_id){
			// close the connection
			ftp_close($conn_id);
		}
		// This is a precaution in case the database instance has expired, we instanciate a new one and update DOLIBACKUP_EN_COURS and RESULT_DOLIBACKUP
		$db = getDoliDBInstance($dolibarr_main_db_type, $dolibarr_main_db_host, $dolibarr_main_db_user, $dolibarr_main_db_pass,$dolibarr_main_db_name, $dolibarr_main_db_port);
		$set_dolibackup = dolibarr_del_const($db, 'DOLIBACKUP_EN_COURS');

		dolibarr_set_const($db, 'RESULT_DOLIBACKUP', ($errormsg ? -1 : 0), 'chaine', 0, '', $conf->entity);
		
		// Delete the local zip file
		unlink($file_zip);
        
		$this->error = $errormsg;

		// Send error messages to the addresses on $conf->global->EMAILS_ECHEC
		if ($errormsg){
			require_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
			$mailfile = new CMailFile(
	            $langs->trans("BackupError",strftime("%d/%m/%Y"),$install_name),
	            $conf->global->EMAILS_ECHEC,
	            'Backups Dolibackups <noreply@dolibackup.com>',
	           	$langs->trans("BackupErrorDescription").$errormsg,
	            array(),
	            array(),
	            array(),
	            '',
	            '',
	            0,
	            1
        	);
        	$mailfile->sendfile();
		}

		
		return ($errormsg ? -1 : 0);

	}
	
}


