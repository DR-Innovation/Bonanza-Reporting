<?php

namespace bonanza\reporting;

class ReportingController {
	
	const CLIENT_GUID = '7257a149-4d54-4a8f-9364-501b341a0856';
	
	/**
	 * The CHAOS client.
	 * @var \CHAOS\Portal\Client\PortalClient
	 */
	protected $_chaos;
	
	protected $_options;
	/**
	 * The reporter to use when parsing objects.
	 * @var \bonanza\Reporter
	 */
	protected $_reporter;
	
	protected $_ftp;
	
	public static function main($arguments = array()) {
		self::printLogo();
		
		$options = self::extractOptionsFromArguments($arguments);
		
		if(key_exists('INCLUDE_PATH', $_SERVER)) {
			set_include_path(get_include_path() . PATH_SEPARATOR . $_SERVER['INCLUDE_PATH']);
		} else {
			die("You have to specify the INCLUDE_PATH environment variable.");
		}
		
		if(key_exists('CHAOS_URL', $_SERVER)) {
			$options['CHAOS_URL'] = $_SERVER['CHAOS_URL'];
		} else {
			die("Woups .. the CHAOS_URL environment variable has to be set.");
		}
		
		if(key_exists('CHAOS_EMAIL', $_SERVER) && key_exists('CHAOS_PASSWORD', $_SERVER)) {
			$options['email'] = $_SERVER['CHAOS_EMAIL'];
			$options['password'] = $_SERVER['CHAOS_PASSWORD'];
		} else {
			die("You have to specify the CHAOS_EMAIL and CHAOS_PASSWORD environment variables.");
		}
		
		// Reuse the case sensitive autoloader.
		require_once('CaseSensitiveAutoload.php');
		
		// Register this autoloader.
		spl_autoload_extensions(".php");
		spl_autoload_register("CaseSensitiveAutoload");
		
		// Require the timed lib to time actions.
		require_once('timed.php');
		timed(); // Tick tack, time is ticking.
		
		//$stateFolder = realpath($options['state-folder']);
		if($options['state-folder'] === false || !is_dir($options['state-folder'])) {
			var_dump($stateFolder);
			die('The state-folder provided ('.$options['state-folder'].') is not a readable directory.');
		} else {
			$options['state-folder'] = $stateFolder;
		}
		
		$reporter = new \bonanza\reporting\Reporter($options['state-folder']);
		/* @var $reporter Reporter */
		$controller = new ReportingController($options, $reporter);
		print("---------- Bonanza Reporting Controller constructed ----------\n");
		$controller->start();
	}
	
	public static function printLogo() {
		echo "Bonanza Reporting ...\n";
	}
	
	protected static function extractOptionsFromArguments($arguments) {
		$result = array();
		for($i = 0; $i < count($arguments); $i++) {
			if(strpos($arguments[$i], '--') === 0) {
				$equalsIndex = strpos($arguments[$i], '=');
				if($equalsIndex === false) {
					$name = substr($arguments[$i], 2);
					$result[$name] = true;
				} else {
					$name = substr($arguments[$i], 2, $equalsIndex-2);
					$value = substr($arguments[$i], $equalsIndex+1);
					if($value == 'true') {
						$result[$name] = true;
					} elseif($value == 'false') {
						$result[$name] = false;
					} else {
						$result[$name] = $value;
					}
				}
			}
		}
		return $result;
	}
	
	/**
	 * 
	 * @param string[string] $options
	 * @param \bonanza\Reporter $reporter Used to report the stuff.
	 */
	function __construct($options, $reporter) {
		$this->_options = $options;
		$this->_reporter = $reporter;
		
		$CHAOS_URL = $options['CHAOS_URL'];
		echo "Connecting to $CHAOS_URL\n";
		$this->_chaos = new \CHAOS\SessionRefreshingPortalClient(null, $CHAOS_URL, self::CLIENT_GUID);
		$this->_chaos->EmailPassword()->Login($options['email'], $options['password']);
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
			}
		}
		return $this->_ftp;
	}
	
	protected function saveXMLToFile($xml, $filename) {
		$dom = dom_import_simplexml($xml)->ownerDocument;
		$dom->formatOutput = true;
		$xml = $dom->saveXML();
		
		$filename = $this->_options['state-folder'] . DIRECTORY_SEPARATOR . $filename;
		
		$result = file_put_contents($filename, $xml);
		if($result === false) {
			throw new \RuntimeException("Couldn't save the XML file.");
		}
	}
	
	protected function removeFileIfExisting($filename) {
		$filename = $this->_options['state-folder'] . DIRECTORY_SEPARATOR . $filename;
		if(file_exists($filename)) {
			return unlink($filename);
		}
	}
	
	protected function emptyFTPFolder() {
		$this->ensureFTPConnection();
		$files = ftp_nlist($this->_ftp, $this->_options['ftp-folder']);
		foreach($files as $file) {
			$filepath = $this->_options['ftp-folder'] . DIRECTORY_SEPARATOR . $file;
			$success = ftp_delete($this->_ftp, $filepath);
			if($success === false) {
				echo "Deleting file $filepath on the FTP server failed.";
			}
		}
	}
	
	protected function cleanupFolder($folder) {
		echo "Cleaning up the $folder folder.";
		$folder = $this->_options['state-folder'] . DIRECTORY_SEPARATOR . $folder;
		if ($handle = opendir($folder)) {
			// This is the correct way to loop over the directory.
			while (false !== ($entry = readdir($handle))) {
				if($entry !== '.' && $entry !== '..') {
					echo "Deleting file $entry.";
					unlink($folder . DIRECTORY_SEPARATOR . $entry);
				}
			}
			closedir($handle);
		}
	}
	
	protected function isFTPFolderEmpty() {
		$this->ensureFTPConnection();
		$files = ftp_nlist($this->_ftp, $this->_options['ftp-folder']);
		if($files === false) {
			throw new \RuntimeException("The FTP folder specified as runtime argument didn't exist.");
		}
		return count($files) == 0;
	}
	
	protected function transferStateToFTP() {
		$this->uploadAllFilesInFolder($this->_options['state-folder'] . DIRECTORY_SEPARATOR . 'inserted');
		$this->uploadAllFilesInFolder($this->_options['state-folder'] . DIRECTORY_SEPARATOR . 'deleted');
		// transferStateToFTP
	}
	
	protected function uploadAllFilesInFolder($folder) {
		if ($handle = opendir($folder)) {
			// This is the correct way to loop over the directory.
			while (false !== ($entry = readdir($handle))) {
				if($entry !== '.' && $entry !== '..') {
					$this->uploadFileToFTP($folder . DIRECTORY_SEPARATOR . $entry);
				}
			}
			closedir($handle);
		}
	}
	
	protected function uploadFileToFTP($local_filepath) {
		echo "Transfering $local_filepath to the FTP server.\n";
		$filepathinfo = pathinfo($local_filepath);
		$remote_filepath = $this->_options['ftp-folder'] . DIRECTORY_SEPARATOR . $filepathinfo['basename'];
		$success = ftp_put($this->_ftp, $remote_filepath, $local_filepath, FTP_ASCII);
		if(!$success) {
			throw new \RuntimeException("Failed to upload $local_filepath to server.");
		}
		return true;
	}

	function start() {
		// Assure that the FTP folder is empty at this point in time.
		$FTPFolderIsEmpty = $this->isFTPFolderEmpty();
		if(!$FTPFolderIsEmpty) {
			printf("The FTP folder is not empty yet, skipping this run of the reporter.\n");
			exit;
		}
		
		$pageIndex = 0;
		$pageSize = 500;
		//$pageSize = 2;
		$objectIndex = 0;
		$debugging = array_key_exists('debug', $this->_options);
		do {
			$query = sprintf("(FolderTree:%u AND ObjectTypeID:%u)", intval($this->_options['folder-id']), intval($this->_options['object-type-id']));
			// TODO implement this without the use of an accesspoint guid when the service supports the includeAccessPointGUIDs
			$response = $this->_chaos->Object()->Get($query, null, null, $pageIndex, $pageSize, true, true, true, true);
			
			$totalCount = $response->MCM()->TotalCount();
			$totalPages = ceil($totalCount / $pageSize);
			
			foreach($response->MCM()->Results() as $object) {
				printf("Processing object # %u of %u objects.\n", $objectIndex, $totalCount);
				$this->_reporter->processObject($object->GUID, $object);
				$objectIndex++;
			}
			$pageIndex++;
		} while($pageIndex < $totalPages && !$debugging);
		
		// Computing difference.
		$difference = $this->_reporter->diff();
		
		$i = 0;
		foreach($difference['unpublished'] as $unpublishedObjectGUID) {
			printf("[%u/%u] Generating an XML file to delete the object with GUID = %s.\n", $i++, count($difference['unpublished']), $unpublishedObjectGUID);
			$deleteXML = $this->_reporter->generateDeleteXML($unpublishedObjectGUID);
			if($deleteXML == null) {
				printf("\tSkipping this, as metadata was not generated.\n");
				continue;
			}
			// Save this $deleteXML to a file in the deleted state folder.
			$this->saveXMLToFile($deleteXML, "deleted/$unpublishedObjectGUID.xml");
			// Delete this XML file from the inserted state folder.
			$this->removeFileIfExisting("inserted/$unpublishedObjectGUID.xml");
		}
		
		$i = 0;
		foreach($difference['published'] as $publishedObjectGUID) {
			printf("[%u/%u] Generating an XML file to insert the object with GUID = %s.\n", $i++, count($difference['published']), $publishedObjectGUID);
			$insertXML = $this->_reporter->generateInsertXML($publishedObjectGUID);
			if($insertXML == null) {
				printf("\tSkipping this, as metadata was not generated.\n");
				continue;
			}
			// Save this $insertedXML to a file in the inserted state folder.
			$this->saveXMLToFile($insertXML, "inserted/$publishedObjectGUID.xml");
			// Make sure this is not in the deleted state folder.
			$this->removeFileIfExisting("deleted/$publishedObjectGUID.xml");
		}
		
		try {
			// Transfer the state to the FTP server.
			$this->transferStateToFTP();
			// Remove all files from the deleted folder.
			$this->cleanupFolder('deleted');
		} catch(\Exception $e) {
			$this->emptyFTPFolder();
			echo $e->getTraceAsString();
		}
	}
}
ReportingController::main($_SERVER['argv']);