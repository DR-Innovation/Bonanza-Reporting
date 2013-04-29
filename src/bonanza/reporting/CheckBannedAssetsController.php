<?php

namespace bonanza\reporting;

class CheckBannedAssetsController {
	
	const CLIENT_GUID = '3a63ad95-2544-463b-b6bc-f3f6018facc7';
	
	protected $_chaos;
	
	protected $_options;
	
	protected $_bannedProductionIDs;
	
	public static function main($arguments = array()) {
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
		
		// Reuse the case sensitive autoloader.
		require_once('CaseSensitiveAutoload.php');
		
		// Register this autoloader.
		spl_autoload_extensions(".php");
		spl_autoload_register("CaseSensitiveAutoload");
		
		// Require the timed lib to time actions.
		require_once('timed.php');
		timed(); // Tick tack, time is ticking.
		
		$controller = new CheckBannedAssetsController($options);
		$controller->start();
	}
	
	/**
	 * 
	 * @param string[string] $options
	 * @param \bonanza\Reporter $reporter Used to report the stuff.
	 */
	function __construct($options) {
		$this->_options = $options;
		
		$CHAOS_URL = $options['CHAOS_URL'];
		echo "Connecting to $CHAOS_URL\n";
		$this->_chaos = new \CHAOS\SessionRefreshingPortalClient(null, $CHAOS_URL, self::CLIENT_GUID);
		
		if(key_exists('CHAOS_EMAIL', $_SERVER) && key_exists('CHAOS_PASSWORD', $_SERVER)) {
			printf("Authenticating session: ");
			$email = $_SERVER['CHAOS_EMAIL'];
			$password = $_SERVER['CHAOS_PASSWORD'];
			$response = $this->_chaos->EmailPassword()->Login($email, $password);
			if($response->WasSuccess() && $response->EmailPassword()->WasSuccess()) {
				printf("Success.\n");
			} else {
				printf("Failed ...\n");
			}
		} else {
			die("Woups .. the CHAOS_EMAIL or CHAOS_PASSWORD environment variable has to be set.");
		}
		
		$datafile = $options['banned-datafile'];
		if(!is_file($datafile)) {
			throw new \InvalidArgumentException("The datafile of banned productions IDs was not found, relative to ".getcwd());
		}
		$this->_bannedProductionIDs = file_get_contents($datafile);
		$this->_bannedProductionIDs = explode("\n", $this->_bannedProductionIDs);
		// Remove any whitespace chars before and after each ID.
		$this->_bannedProductionIDs = array_map('trim', $this->_bannedProductionIDs);
		// Remove any empty lines.
		$this->_bannedProductionIDs = array_filter($this->_bannedProductionIDs);
		
		if(array_key_exists('accesspoint', $options)) {
			$this->_accessPointGUID = $options['accesspoint'];
		} else {
			throw new \InvalidArgumentException("No accesspoint supplied.");
		}
	}
	
	function start() {
		foreach($this->_bannedProductionIDs as $productionID) {
			printf("Checking if asset with production ID %s is published at accesspoint %s.\n", $productionID, $this->_accessPointGUID);
			// $query = sprintf('DKA-ExternalIdentifier:"%s"', $productionID);
			$query = sprintf('m5906a41b-feae-48db-bfb7-714b3e105396_da_all:"%s" OR m00000000-0000-0000-0000-000063c30000_da_all:"%s"', $productionID, $productionID);
			$response = $this->_chaos->Object()->Get($query, null, $this->_accessPointGUID, 0, 100, false, false, false, true);
			if($response->MCM()->TotalCount() > 0) {
				$object = $response->MCM()->Results();
				$object = $object[0];
				printf("!!! It looks like the asset with production-id %s is published as object # %s.\n", $productionID, $object->GUID);
				// Unpublish!
				foreach($response->MCM()->Results() as $object) {
					foreach($object->AccessPoints as $accesspoint) {
						printf("Unpublishing Object #%s from accesspoint #%s.\n", $object->GUID, $accesspoint->AccessPointGUID);
						$response = $this->_chaos->Object()->SetPublishSettings($object->GUID, $accesspoint->AccessPointGUID);
						if(!$response->WasSuccess()) {
							throw new \RuntimeException("Error unpublishing object: ".$response->Error()->Message());
						} else if(!$response->MCM()->WasSuccess()) {
							throw new \RuntimeException("[MCM] Error unpublishing object: ".$response->MCM()->Error()->Message());
						}
					}
				}
			}
		}
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
}
CheckBannedAssetsController::main($_SERVER['argv']);