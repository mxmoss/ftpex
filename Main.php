<?php
require_once '\develop\php\ftp-php\src\Ftp.php';

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
		if (!ftp_get ( $conn_id, $tmpFileName, $tmpFileName, Ftp::BINARY )) {
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
		if (!ftp_put ( $conn_id, $tmpFileName, $tmpFileName, Ftp::BINARY )) {
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

// Sample using native php
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
	
	//Upload public distribution files
	if (ftp_chdir($conn_id, '/'.$subDir)){
		chdir($subDir);
		
		if ($ftpAction == 'upload'){
			// upload any local files
			foreach(glob($ftpFilemask) as $aFile) {
				echo 'Uploading ',$aFile,"\r\n";
				$upload = ftp_moveTo($conn_id, $aFile);
			}
		} else {
			// download any files on server
			$contents_on_server = ftp_nlist ( $conn_id, $ftpFilemask );
			if ($contents_on_server){
				foreach ( $contents_on_server as $aFile ) {
					echo 'Downloading ',$aFile,"\r\n";
					ftp_moveFrom( $conn_id, $aFile);
				}
			}
		}
		chdir('..');
	}
	
	// close the FTP stream
	ftp_close ( $conn_id );
	echo "Disconnected\r\n";
}

error_reporting(E_ALL); 
ini_set('log_errors','1'); 
ini_set('display_errors','1');

$ftpSite = 'mysite';
$ftpUID = 'myusername';
$ftpPWD = 'mypassword';

$SubDir = 'pub';
$FileMask = 'bar*.*';
ftpExchange($ftpSite, $ftpUID, $ftpPWD, 'upload', $FileMask, $SubDir);
$FileMask = 'foo*.*';
$SubDir = 'incoming';
ftpExchange($ftpSite, $ftpUID, $ftpPWD, 'download', $FileMask, $SubDir);

?>
