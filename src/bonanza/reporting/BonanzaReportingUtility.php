<?php

namespace bonanza\reporting;

class BonanzaReportingUtility {
	
	const CLIENT_GUID = '7257a149-4d54-4a8f-9364-501b341a0856';
	const OBJECTS_PROCESSED_WHEN_DEBUGGING = 10;
	const VALIDATION_SCHEMA = '../../../schemas/CMS_CLICKSTAT_CV_v1.1.xsd';
	
	/**
	 * The CHAOS client.
	 * @var \CHAOS\Portal\Client\PortalClient
	 */
	/*
	protected $_chaos;
	
	protected $_validationSchema;
	*/
	
	/**
	 * The reporter to use when parsing objects.
	 * @var \bonanza\Reporter
	 */
	/*
	protected $_reporter;
	*/
	
	public static $MODES = array(
		'clean' => '\bonanza\reporting\modes\CleanMode',
		'restore' => '\bonanza\reporting\modes\RestoreMode',
		'generate' => '\bonanza\reporting\modes\GenerateMode',
		'commit' => '\bonanza\reporting\modes\CommitMode'
	);
	
	public static $SERVER_TO_OPTIONS = array(
		'CHAOS_URL' => 'chaos-url',
		'CHAOS_EMAIL' => 'chaos-email',
		'CHAOS_PASSWORD' => 'chaos-password',
		'STATE_FOLDER' => 'state-folder',
		'FTP_HOSTNAME' => 'ftp-hostname',
		'FTP_PORT' => 'ftp-port',
		'FTP_USERNAME' => 'ftp-username',
		'FTP_PASSWORD' => 'ftp-password',
		'FTP_FOLDER' => 'ftp-folder',
		'DBDUMP' => 'dbdump'
	);
	
	protected static $_options;
	
	public static function printLogo() {
		echo "Bonanza Reporting v.0.2\n";
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
	
	public static function sanityCheckOptions(&$options) {
		
		if(!array_key_exists('mode', $options)) {
			error_log('Mode not specified or unrecognized.');
			exit -2;
		}
	
		if(!array_key_exists('state-folder',  $options)) {
			error_log('You have to provide a state-folder.');
			exit -3;
		}
		
		$options['state-folder'] = realpath($options['state-folder']);
		if($options['state-folder'] === false) {
			error_log('The state-folder provided is not a readable directory.');
			exit -4;
		}
	}
	
	/**
	 * Ensure that the state folder has as-is and difference
	 */
	protected static function ensureStateFolders($stateFolder) {
		self::ensureFolder($stateFolder);
		self::ensureFolder($stateFolder . DIRECTORY_SEPARATOR . 'as-is');
		self::ensureFolder($stateFolder . DIRECTORY_SEPARATOR . 'difference');
	}
	
	/**
	 * Checks for a directory's existance and creates it if its non-existant.
	 * @param string $path to the folder.
	 * @return boolean True on success.
	 */
	public static function ensureFolder($path) {
		if(!is_dir($path)) {
			$success = mkdir($path);
			if(!$success) {
				error_log("Couldn't create the $path folder.");
				return false;
			}
		} elseif(!is_writable($path)) {
			error_log("The directory isn't writable.");
			return false;
		}
		return true;
	}
	
	public static function main($arguments = array()) {
		self::printLogo();
		
		self::$_options = self::extractOptionsFromArguments($arguments);

		// Include the current projects src folder.
		set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__ . '/../../');
		
		// And anything given in the include path environment variable.
		if(key_exists('INCLUDE_PATH', $_SERVER)) {
			set_include_path(get_include_path() . PATH_SEPARATOR . $_SERVER['INCLUDE_PATH']);
		}
		
		// Update the options with environment variables - if not in options.
		foreach(self::$SERVER_TO_OPTIONS as $server_key => $option_key) {
			if(key_exists($server_key, $_SERVER) && !key_exists($option_key, self::$_options)) {
				self::$_options[$option_key] = strval($_SERVER[$server_key]);
			}
		}
		
		self::sanityCheckOptions(self::$_options);
		self::ensureStateFolders(self::$_options['state-folder']);

		// Turn the mode into an array of modes, split on ','
		self::$_options['mode'] = explode(',', self::$_options['mode']);
		
		// Reuse the case sensitive autoloader.
		require_once('CaseSensitiveAutoload.php');
		
		// Register this autoloader.
		spl_autoload_extensions(".php");
		spl_autoload_register("CaseSensitiveAutoload");
		
		// Require the timed lib to time actions.
		require_once('timed.php');
		timed(); // Tick tack, time is ticking.
		
		foreach(self::$_options['mode'] as $mode) {
			if(array_key_exists($mode, self::$MODES)) {
				echo "Starting the utility in '$mode' mode.\n";
				$mode_class = self::$MODES[$mode];
				$mode_instance = new $mode_class(self::$_options);
					
				$mode_instance->start();
			} else {
				echo "Skipping execution of in an unknown mode '$mode'.";
			}
		}
	}
	
	static function saveXMLToFile($xml, $path, $filename) {
		$dom = dom_import_simplexml($xml)->ownerDocument;
		/* @var $dom \DOMDocument */
		$dom->formatOutput = true;
		if(!$dom->schemaValidate(__DIR__ . DIRECTORY_SEPARATOR . self::VALIDATION_SCHEMA)) {
			echo $dom->saveXML();
			throw new \RuntimeException("Couldn't save XML which didn't match the validation schema.");
		}
		$xml = $dom->saveXML();
		
		$filename = self::$_options['state-folder'] . DIRECTORY_SEPARATOR . $path . DIRECTORY_SEPARATOR . $filename;
		
		$result = file_put_contents($filename, $xml);
		if($result === false) {
			throw new \RuntimeException("Couldn't save the XML file.");
		}
	}
	
	const PROGRESS_BAR_TITLE_CHAR = '_';
	const PROGRESS_BAR_TICK_CHAR = '=';
	
	protected static $_progress_total_count = null;
	protected static $_progress_bar_size = null;
	protected static $_progress_ticks = null;
	
	static function progress_reset($total_count = null, $progress_bar_text = 'Processing', $progress_bar_size = 60) {
		if($total_count !== null) {
			$progress_bar_title_chars = $progress_bar_size - strlen($progress_bar_text) - 2;
			for($i = 0; $i < floor($progress_bar_title_chars / 2); $i++) {
				echo self::PROGRESS_BAR_TITLE_CHAR;
			}
			echo " $progress_bar_text ";
			for($i = 0; $i < ceil($progress_bar_title_chars / 2); $i++) {
				echo self::PROGRESS_BAR_TITLE_CHAR;
			}
			echo "\n";
		} else {
			echo "\n";
		}
		self::$_progress_total_count = $total_count;
		self::$_progress_bar_size = $progress_bar_size;
		self::$_progress_ticks = 0;
	}
	
	static function progress_update($progress) {
		if(self::$_progress_total_count !== null && $progress <= self::$_progress_total_count) {
			$supposed_ticks = floor(self::$_progress_bar_size * $progress / self::$_progress_total_count);
			for(; self::$_progress_ticks < $supposed_ticks; self::$_progress_ticks++) {
				echo self::PROGRESS_BAR_TICK_CHAR;
			}
			if(self::$_progress_ticks == self::$_progress_bar_size) {
				echo "\n";
			}
		}
	}
}
BonanzaReportingUtility::main($_SERVER['argv']);