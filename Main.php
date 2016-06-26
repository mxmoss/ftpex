<?php

//This function moves a remote FTP site to the local file system
//Afterward, the file will be removed from the FTP server
function ftp_moveFrom($conn_id, $aFile){
	try {
		error_log("Moving file $aFile from ftp site");
		
		$remoteSizeInBytes = ftp_size($conn_id, $aFile);
		
		// rename the file before download
		$tmpFileName = uniqid ( rand (), true ) . '.tmp';
		error_log("Renaming $aFile to $tmpFileName");
		if (!ftp_rename ( $conn_id, $aFile, $tmpFileName )) {
			throw new Exception("Cannot rename $aFile to $tmpFileName");
		}
		
		// download using tempfilename
		if (!ftp_get ( $conn_id, $tmpFileName, $tmpFileName, FTP_BINARY )) {
			throw new Exception("Cannot download tmp file");
		}
				
		// rename back to original name
		if (!rename ( $tmpFileName, $aFile )){
			throw new Exception("Cannot rename tmp file back to $aFile");
		}
		
		$localSizeInBytes = filesize($aFile);
		if ($remoteSizeInBytes > -1 && $remoteSizeInBytes != $localSizeInBytes){
			throw new Exception("Local file size does not match");
		}
		
		//clean up temp files
		if(file_exists ($tmpFileName)){
			unlink($tmpFileName );
		}
		if (ftp_size($conn_id, $tmpFileName)>-1){
			ftp_delete ( $conn_id, $tmpFileName );
		}
		error_log("Success moving file $aFile from ftp site");
	} catch ( Exception $e ) {
		error_log("Error: $e->getMessage ()");
	}
}

//This function moves a file from the local file system to an FTP server.
//Afterward, the file will be removed from the local system
function ftp_moveTo($conn_id, $aFile){
	try {
		error_log("Moving file $aFile to ftp site");
		
		$localSizeInBytes = filesize($aFile);
		
		// rename the file before download
		$tmpFileName = uniqid ( rand (), true ) . '.tmp';
		error_log("Renaming $aFile to $tmpFileName");
		if (!rename( $aFile, $tmpFileName )){
			throw new Exception("Cannot rename $aFile to $tmpFileName");
		}
		
		// put on ftp site using tempfilename
		if (!ftp_put ( $conn_id, $tmpFileName, $tmpFileName, FTP_BINARY )) {
			throw new Exception("Cannot upload tmp file");
		}
		// rename back to original name
		if (!ftp_rename($conn_id, $tmpFileName, $aFile)){
			throw new Exception("Cannot rename tmp file back to $aFile");
		}

		$remoteSizeInBytes = ftp_size($conn_id, $aFile);
		if ($remoteSizeInBytes > -1 && $remoteSizeInBytes != $localSizeInBytes){
			throw new Exception("Remote file size does not match");
		}
		
		//clean up temp files
		if(file_exists ($tmpFileName)){
			unlink($tmpFileName );
		}
		if (ftp_size($conn_id, $tmpFileName)>-1){
			ftp_delete ( $conn_id, $tmpFileName );
		}
		error_log("Success moving file $aFile to ftp site");
	} catch ( Exception $e ) {
		error_log("Error: $e->getMessage ()");
	}

}

// Wrapper to handle moving files to or from an FTP site
function ftpExchange($ftpSite, $ftpUID, $ftpPWD, $ftpAction, $ftpFilemask, $subDir) {
	// set up basic connection
	$conn_id = ftp_connect ( $ftpSite ) or die ( "Couldn't connect to $ftp_server" );
	
	// login with username and password
	$login_result = ftp_login ( $conn_id, $ftpUID, $ftpPWD );
	if ((! $conn_id) || (! $login_result)) {
		echo "FTP connection has failed!\r\n";
		echo "Attempted to connect to $ftpSite for user $ftpUID\r\n";
		exit ();
	} else {
		echo "Connected to $ftpSite, for user $ftpUID\r\n";
	}
	
	//Synchronize directories
	if (ftp_chdir($conn_id, '/'.$subDir)){
		chdir($subDir);
		
		$i = 0;
		$msg = $ftpAction.'ing ';
		if ($ftpAction == 'upload'){
			$file_list = glob($ftpFilemask);
		} else {
			$file_list = ftp_nlist ( $conn_id, $ftpFilemask );
		}
		$file_count = count($file_list);
		if ($file_list){
			foreach($file_list as $aFile) {
				echo $msg,$aFile,"\r\n";
				if ($ftpAction == 'upload'){
					ftp_moveTo($conn_id, $aFile);
				} else {
					ftp_moveFrom( $conn_id, $aFile);
				}
				$i++;
			}
		}
		chdir('..');
		if ($file_count <> $i){
			echo "ERROR: List of input files differs from the count of actual files.";
		}
	}
	
	// close the FTP stream
	ftp_close ( $conn_id );
	echo "Disconnected\r\n";
}

function Main(){
	error_reporting(E_ALL); 
	ini_set('log_errors','1'); 
	ini_set('display_errors','1');
	
	$ftpSite = 'mysite';
	$ftpUID = 'myusername';
	$ftpPWD = 'mypassword';
	
	//Move these files to /pub on FTP server  
	//*.gpg, *.pdf  (case sensitive)
	$SubDir = 'pub';
	$FileMask = 'bar*.*';
	ftpExchange($ftpSite, $ftpUID, $ftpPWD, 'upload', $FileMask, $SubDir);
	
	//Retrieve incoming files from FTP server
	//*.gpg
	$FileMask = 'foo*.*';
	$SubDir = 'incoming';
	ftpExchange($ftpSite, $ftpUID, $ftpPWD, 'download', $FileMask, $SubDir);
}

Main();

?>
