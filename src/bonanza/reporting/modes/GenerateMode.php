<?php
namespace bonanza\reporting\modes;
use bonanza\reporting\BonanzaReportingUtility;
class GenerateMode extends BaseMode {
	
	const CHAOS_OBJECT_TYPE_ID = 36;
	const CLIENT_GUID = '869bef07-0f6a-4461-b82e-5fe909b2ce80';
	const PAGE_SIZE = 100;
	static $DKA_FOLDER_IDS = array(444, 715);
	
	/**
	 * An client to the chaos service which has been authenticated.
	 * @var \CHAOS\Portal\Client\EnhancedPortalClient
	 */
	static $_chaos;
	
	public function __construct($options) {
		parent::__construct($options);
		self::$_chaos = new \CHAOS\Portal\Client\EnhancedPortalClient($options['chaos-url'], self::CLIENT_GUID);
		self::$_chaos->EmailPassword()->Login($options['chaos-email'], $options['chaos-password']);
	}
	
	public function start() {
		echo "Starting to generate the difference state from the current as-is state and the CHAOS service.\n";
		$published = $this->getPublishedObjects();
		echo "--- Done communicating with the CHAOS service ---\n";
		echo "CHAOS reports " . count($published) . " objects published.\n";
		$formerlyPublished = $this->getFormerlyPublishedObjects();
		echo "State as-is folder reports " . count($formerlyPublished) . " objects published.\n";
		
		// Calculate the difference.
		$newlyPublished = array_diff(array_keys($published), array_keys($formerlyPublished));
		$newlyUnpublished = array_diff(array_keys($formerlyPublished), array_keys($published));
		echo "This suggests " . count($newlyPublished) . " newly published and " . count($newlyUnpublished) . " newly unpublished objects.\n";
		
		// Create insert XMLs for the objects published.
		$insertXMLGenerator = new \bonanza\reporting\xml\chaos\InsertXMLGenerator();
		foreach($newlyPublished as $guid) {
			$object = $published[$guid];
			$xml = $insertXMLGenerator->generateXML($object);
			if($xml) {
				BonanzaReportingUtility::saveXMLToFile($xml, 'difference', $guid . '.xml');
			}
		}
		
		// Create delete XMLs for the objects unpublished.
		$deleteXMLGenerator = new \bonanza\reporting\xml\chaos\DeleteXMLGenerator();
		foreach($newlyUnpublished as $guid) {
			$objectReference = $formerlyPublished[$guid];
			$insertXML = simplexml_load_file($objectReference);
			$xml = $deleteXMLGenerator->generateXML($insertXML);
			if($xml) {
				BonanzaReportingUtility::saveXMLToFile($xml, 'difference', $guid . '.xml');
			}
		}
	}
	
	public function sanityCheckOptions() {
		if(!key_exists('chaos-email', $this->_options) || !key_exists('chaos-password', $this->_options)) {
			throw new \RuntimeException("You have to specify the CHAOS_EMAIL and CHAOS_PASSWORD environment variables.");
		}
		// Ensure that the state-folders difference folder is empty.
		$differenceFolder = $this->_options['state-folder'] . DIRECTORY_SEPARATOR . 'difference';
		if(!array_key_exists('debug', $this->_options) && !self::isFolderEmpty($differenceFolder)) {
			throw new \RuntimeException("The difference folder isn't empty - try committing first or empty the folder manually.");
		}
	}
	
	protected static function getQuery() {
		$folderQueries = array_map(function($folderId) {
			return "(FolderID:$folderId)";
		}, self::$DKA_FOLDER_IDS);
		return implode("+OR+", $folderQueries);
	}
	
	public static function isObjectPublished($object) {
		// TODO Consider using some server time, if this is a future feature of the service.
		$now = new \DateTime();
		foreach($object->AccessPoints as $accessPoint) {
			if($accessPoint->StartDate == null) {
				continue; // Skipping something which has no start date set.
			}
			$startDate = new \DateTime();
			$startDate->setTimestamp($accessPoint->StartDate);
			// Is now after the start date?
			if($startDate < $now) {
				// Is the end date not sat? I.e. is it at the end of our time?
				if($accessPoint->EndDate == null) {
					return true;
				} else {
					$endDate = new \DateTime();
					$endDate->setTimestamp($accessPoint->EndDate);
					// Are we still publishing this?
					if($now < $endDate) {
						return true;
					}
				}
			}
		}
		// None of the accesspoints was actively publishing the object.
		return false;
	}
	
	public function getPublishedObjects() {
		$publishedObjects = array();
		$query = self::getQuery();
		$pageIndex = 0;
		$totalPageCount = null;
		do {
			$response = self::$_chaos->Object()->Get($query, 'DateCreated+asc', null, $pageIndex, self::PAGE_SIZE, true, true, false, true);
			if($totalPageCount === null) {
				$totalPageCount = floor($response->MCM()->TotalCount() / self::PAGE_SIZE);
				\bonanza\reporting\BonanzaReportingUtility::progress_reset($totalPageCount, "Downloading objects");
			}
			foreach($response->MCM()->Results() as $object) {
				if(self::isObjectPublished($object)) {
					$publishedObjects[strtolower($object->GUID)] = new \CHAOS\Portal\Client\Data\Object($object);
				}
			}
			\bonanza\reporting\BonanzaReportingUtility::progress_update($pageIndex+1);
			// printf("Processed page #%u of %u\n", $pageIndex+1, floor($response->MCM()->TotalCount() / self::PAGE_SIZE));
			$pageIndex++;
		} while($pageIndex * self::PAGE_SIZE < $response->MCM()->TotalCount() && !array_key_exists('debug', $this->_options));
		return $publishedObjects;
	}
	
	public function getFormerlyPublishedObjects() {
		$asIsFolder = $this->_options['state-folder'] . DIRECTORY_SEPARATOR . 'as-is';
		$formerlyPublishedObjects = array();
		if ($handle = opendir($asIsFolder)) {
			// This is the correct way to loop over the directory.
			while (false !== ($entry = readdir($handle))) {
				if($entry !== '.' && $entry !== '..') {
					if(preg_match('/([0-F]{8}-[0-F]{4}-[0-F]{4}-[0-F]{4}-[0-F]{12})\.xml/i', $entry, $entry_matches)) {
						$uuid = $entry_matches[1];
						$formerlyPublishedObjects[strtolower($uuid)] = realpath($asIsFolder . DIRECTORY_SEPARATOR . $entry);
					}
				}
			}
			closedir($handle);
		}
		return $formerlyPublishedObjects;
	}
	
	public static function isFolderEmpty($path) {
		if ($handle = opendir($path)) {
			// This is the correct way to loop over the directory.
			while (false !== ($entry = readdir($handle))) {
				if($entry !== '.' && $entry !== '..') {
					return false;
				}
			}
			closedir($handle);
		}
		return true;
	}
}