<?php
include 'error_log.php';

//Class for exchanging DX files with an FTP server
class DX_Ftp {
	var $conn_id;
	var $ftpSite;
	var $ftpUID;
	var $ftpPWD;
	var $logfile; 
	
	function logIt($msg){
	    echo date("Y-m-d H:i:s ")."$msg\r\n";
		file_put_contents($this->logfile,  date("Y-m-d H:i:s ")."$msg\r\n", FILE_APPEND);
	}
	
	// This method moves a remote FTP site to the local file system
	// Afterward, the file will be removed from the FTP server
	function ftp_moveFrom($aFile) {
		try {
			$remoteSizeInBytes = ftp_size ( $this->conn_id, $aFile );
			
			// rename the file before download
			$tmpFileName = uniqid ( rand (), true ) . '.tmp';
			if (! ftp_rename ( $this->conn_id, $aFile, $tmpFileName )) {
				throw new Exception ( "Failed to rename $aFile to $tmpFileName" );
			}
			
			// download using tempfilename
			if (! ftp_get ( $this->conn_id, $tmpFileName, $tmpFileName, FTP_BINARY )) {
				throw new Exception ( "Failed to download file $aFile as temp file $tmpFileName" );
			}
			
			// rename back to original name
			if (! rename ( $tmpFileName, $aFile )) {
				throw new Exception ( "Failed to rename $tmpFileName back to $aFile" );
			}
			
			$localSizeInBytes = filesize ( $aFile );
			if ($remoteSizeInBytes > - 1 && $remoteSizeInBytes != $localSizeInBytes) {
				throw new Exception ( "Local file $aFile size does not match remote" );
			}
			
			// clean up temp files
			if (file_exists ( $tmpFileName )) {
				unlink ( $tmpFileName );
			}
			if (ftp_size ( $this->conn_id, $tmpFileName ) > - 1) {
				ftp_delete ( $this->conn_id, $tmpFileName );
			}
			error_log ( "Success moving file $aFile from ftp site" );
		} catch ( Exception $e ) {
			error_log ( "Error: MoveFrom $e->getMessage ()" );
		}
	}
	
	// This method moves a file from the local file system to an FTP server.
	// Afterward, the file will be removed from the local system
	function ftp_moveTo($aFile) {
		try {
			error_log ( "Moving file $aFile to ftp site" );
			
			$localSizeInBytes = filesize ( $aFile );
			
			// rename the file before download
			$tmpFileName = uniqid ( rand (), true ) . '.tmp';
			error_log ( "Renaming $aFile to $tmpFileName" );
			if (! rename ( $aFile, $tmpFileName )) {
				throw new Exception ( "Failed to rename $aFile to $tmpFileName" );
			}
			
			// put on ftp site using tempfilename
			if (! ftp_put ( $this->conn_id, $tmpFileName, $tmpFileName, FTP_BINARY )) {
				throw new Exception ( "Failed to upload file $aFile as temp file $tmpFileName" );
			}
			// rename back to original name
			if (! ftp_rename ( $this->conn_id, $tmpFileName, $aFile )) {
				throw new Exception ( "Failed to rename $tmpFileName back to $aFile" );
			}
			
			$remoteSizeInBytes = ftp_size ( $this->conn_id, $aFile );
			if ($remoteSizeInBytes > - 1 && $remoteSizeInBytes != $localSizeInBytes) {
				throw new Exception ( "Remote file $aFile size does not match local" );
			}
			
			// clean up temp files
			if (file_exists ( $tmpFileName )) {
				unlink ( $tmpFileName );
			}
			if (ftp_size ( $this->conn_id, $tmpFileName ) > - 1) {
				ftp_delete ( $this->conn_id, $tmpFileName );
			}
			error_log ( "Success moving file $aFile to ftp site" );
		} catch ( Exception $e ) {
			error_log ( "Error: MoveTo $e->getMessage ()" );
		}
	}
	
	// Method to handle moving files to or from an FTP site
	function ftpExchange($ftpMode, $ftpFilemask, $subDir) {
		// set up basic connection
		$this->conn_id = ftp_connect ( $this->ftpSite ) or die ( "Couldn't connect to $ftp_server" );
		
		// login with username and password
		$login_result = ftp_login ( $this->conn_id, $this->ftpUID, $this->ftpPWD );
		if ((! $this->conn_id) || (! $login_result)) {
			$this->logIt("Failed attempt to connect to $this->ftpSite, for user $this->ftpUID");
			exit ();
		} else {
			$this->logIt("Connected to $this->ftpSite for user $this->ftpUID");
		}
		
		// Synchronize directories
		$this->logIt("Change to $subDir");
		if (ftp_chdir ( $this->conn_id, $subDir )) {
			chdir ( $subDir );
			
			$i = 0;
			$msg = $ftpMode . 'ing ';
			if ($ftpMode == 'upload') {
				$file_list = glob ( $ftpFilemask );
			} else {
				$file_list = ftp_nlist ( $this->conn_id, $ftpFilemask );
			}
			$file_count = count ( $file_list );
			if ($file_list) {
				foreach ( $file_list as $aFile ) {
		            $this->logIt("$msg $aFile");
					if ($ftpMode == 'upload') {
						$this->ftp_moveTo($aFile);
					} else {
						$this->ftp_moveFrom ($aFile);
					}
					$i ++;
				}
			}
			chdir ( '..' );
			if ($file_count != $i) {
		        $this->logIt("ERROR: List of input files differs from the count of actual files." );
			}
		}
		
		// close the FTP stream
		ftp_close ( $this->conn_id );
		$this->logIt("Disconnected");
	}

}

//mainline logic
function Main() {
	$dx_ftp = new DX_Ftp;
	
  $ini_array = parse_ini_file("dxftp.ini");
	$dx_ftp->ftpSite = $ini_array["site"];
	$dx_ftp->ftpUID = $ini_array["UID"];
	$dx_ftp->ftpPWD = $ini_array["PWD"];
	$dx_ftp->logfile = __DIR__."\\".$ini_array["logfile"];
	$dx_ftp->logIt($dx_ftp->logfile);
	
	// Move these files to /pub on FTP server
	// eg: *.gpg, *.pdf (case sensitive)
	$FileMask = '*.gpg';
	$dx_ftp->ftpExchange ('upload', $FileMask, 'pub' );
	
	// Retrieve /incoming files from FTP server
	$FileMask = '*.gpg';
	$dx_ftp->ftpExchange ('download', $FileMask, 'incoming' );
}

Main ();

?>
