<?php
namespace bonanza\reporting\modes;
class CommitMode extends BaseMode {
	
	protected $_ftp;
	
	const FILE_TRANSFER_RETRIES = 3;
	
	public function start() {
		echo "Starting to commit the difference state to the FTP.\n";
		$difference = $this->getDifferenceObjects();
		echo "Committing " . count($difference) . " XML files.\n";
		\bonanza\reporting\BonanzaReportingUtility::progress_reset(count($difference), "Uploading");
		$this->ensureFTPConnection();
		// Go throug all files and transfer them one by one, removing the deleted files from the as-is folder.
		$objects_processed = 0;
		foreach($difference as $GUID => $path) {
			$success = false; // For now ..
			$tries = 0;
			do {
				if($tries > 0) {
					echo "Warning: File transfer of $path failed, ensuring FTP connection and retrying.";
					usleep(500000); // Wait 500 ms
					$this->ensureFTPConnection();
				}
				$success = $this->transferFile($path);
				$tries++;
			} while(!$success && $tries < self::FILE_TRANSFER_RETRIES);
			
			$xml = simplexml_load_file($path);
			if($success) {
				$asIsPath = $this->_options['state-folder'] . DIRECTORY_SEPARATOR . 'as-is' . DIRECTORY_SEPARATOR . $GUID . '.xml';
				if(strval($xml->TRANSACTIONTYPE) == "D") {
					// Delete it from the as-is state folder.
					$success = unlink($asIsPath);
					if(!$success) {
						throw new \RuntimeException("Tried to delete $asIsPath, but failed.");
					}
				} elseif(strval($xml->TRANSACTIONTYPE) == "I") {
					// Add it to the as-is state folder.
					$success = copy($path, $asIsPath);
					if(!$success) {
						throw new \RuntimeException("Tried to copy $path to $asIsPath, but failed.");
					}
				}
				// Delete it from the difference state folder.
				$success = unlink($path);
				if(!$success) {
					throw new \RuntimeException("Tried to delete $path, but failed.");
				}
			} else {
				throw new \RuntimeException("Couldn't transfer $path to the FTP after " . self::FILE_TRANSFER_RETRIES . " retries.");
			}
			$objects_processed++;
			\bonanza\reporting\BonanzaReportingUtility::progress_update($objects_processed);
		}
	}
	
	protected function sanityCheckOptions() {
		if(!array_key_exists('ftp-hostname', $this->_options)) {
			throw new \RuntimeException("The ftp-hostname option needs to be set.");
		}
		if(!array_key_exists('ftp-port', $this->_options)) {
			$this->_options['ftp-port'] = 21; // Defaults to port 21
		}
		if(!array_key_exists('ftp-username', $this->_options)) {
			throw new \RuntimeException("The ftp-username option needs to be set.");
		}
		if(!array_key_exists('ftp-password', $this->_options)) {
			throw new \RuntimeException("The ftp-password option needs to be set.");
		}
		if(!array_key_exists('ftp-folder', $this->_options)) {
			throw new \RuntimeException("The ftp-folder option needs to be set.");
		}
	}

	public function getDifferenceObjects() {
		$differenceFolder = $this->_options['state-folder'] . DIRECTORY_SEPARATOR . 'difference';
		$differenceObjects = array();
		if ($handle = opendir($differenceFolder)) {
			// This is the correct way to loop over the directory.
			while (false !== ($entry = readdir($handle))) {
				if($entry !== '.' && $entry !== '..') {
					if(preg_match('/([0-F]{8}-[0-F]{4}-[0-F]{4}-[0-F]{4}-[0-F]{12})\.xml/i', $entry, $entry_matches)) {
						$uuid = $entry_matches[1];
						$differenceObjects[strtolower($uuid)] = realpath($differenceFolder . DIRECTORY_SEPARATOR . $entry);
					}
				}
			}
			closedir($handle);
		}
		return $differenceObjects;
	}

	protected function ensureFTPConnection() {
		if($this->_ftp == null) {
			$hostname = strval($this->_options['ftp-hostname']);
			$port = intval($this->_options['ftp-port']);
			$this->_ftp = ftp_connect($hostname, $port);
			if($this->_ftp === false) {
				throw new \RuntimeException("Couldn't create the FTP resource: $hostname:$port.");
			} else {
				$success = ftp_login($this->_ftp, $this->_options['ftp-username'], $this->_options['ftp-password']);
				if(!$success) {
					throw new \RuntimeException("FTP Username / password didn't match.");
				}
				$success = ftp_pasv($this->_ftp, true);
				if(!$success) {
					throw new \RuntimeException("Couldn't turn the passive FTP mode on.");
				}
			}
		} else {
			$response = @ftp_nlist($this->_ftp, ".");
			if($response === false) {
				$this->_ftp = null;
				echo "The FTP seemed to have timed out, reconnecting ...\n";
				// Reconnect ...
				$this->ensureFTPConnection();
			}
		}
		return $this->_ftp;
	}
	
	protected function transferFile($path) {
		$working_directory = ftp_pwd($this->_ftp);
		$ftp_folder = strval($this->_options['ftp-folder']);
		// Finding a standardized representation of the directories.
		$working_directory = '/' . trim($working_directory, './');
		$ftp_folder = '/' . trim($ftp_folder, './');
		
		if($working_directory !== $ftp_folder) {
			echo "Chaning directory from '$working_directory' to '$ftp_folder'.\n";
			$success = ftp_chdir($this->_ftp, $ftp_folder);
			if(!$success) {
				throw new \RuntimeException("Couldn't change directory to $ftp_folder");
			}
		}
		// We have the correct remote directory.
		return ftp_put($this->_ftp, pathinfo($path, PATHINFO_BASENAME), $path, FTP_ASCII);
	}
}
