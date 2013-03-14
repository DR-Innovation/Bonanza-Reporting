<?php
namespace bonanza\reporting;
class Reporter {
	
	protected $pastPublishedObjects;
	protected $currentlyPublishedObjects = array();
	protected $stateFolderPath;
	
	public function loadStatefolder($stateFolderPath) {
		$insertedStateFolderPath = $stateFolderPath . DIRECTORY_SEPARATOR . 'inserted';
		$deletedStateFolderPath = $stateFolderPath . DIRECTORY_SEPARATOR . 'deleted';
		
		$cwd = getcwd();
		if(!is_dir($stateFolderPath)) {
			throw new \RuntimeException("The state folder ($stateFolderPath) relative to ($cwd) has to be a real folder on the system.");
		} else if(!is_dir($insertedStateFolderPath)) {
			throw new \RuntimeException("The inserted state folder ($insertedStateFolderPath) relative to ($cwd) has to be a real folder on the system.");
		} else if(!is_dir($deletedStateFolderPath)) {
			throw new \RuntimeException("The deleted state folder ($deletedStateFolderPath) relative to ($cwd) has to be a real folder on the system.");
		}
		
		$this->pastPublishedObjects = array();
		if ($handle = opendir($insertedStateFolderPath)) {
		    // This is the correct way to loop over the directory.
		    while (false !== ($entry = readdir($handle))) {
		    	if($entry !== '.' && $entry !== '..') {
		    		if(preg_match('/([0-F]{8}-[0-F]{4}-[0-F]{4}-[0-F]{4}-[0-F]{12})\.xml/i', $entry, $entry_matches)) {
		    			$uuid = $entry_matches[1];
		    			$this->pastPublishedObjects[$uuid] = realpath($insertedStateFolderPath . DIRECTORY_SEPARATOR . $entry);
		    		}
		    	}
		    }
		    closedir($handle);
		}
	}
	
	public function __construct($stateFolderPath) {
		$this->stateFolderPath = $stateFolderPath;
		$this->loadStatefolder($this->stateFolderPath);
	}
	
	protected function isObjectPublished($object) {
		// TODO Consider using some server time, if this is a future feature of the service.
		$now = new \DateTime();
		foreach($object->AccessPoints as $accessPoint) {
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
	
	public function processObject($objectGUID, $object) {
		// Check if it's published somewhere.
		if(self::isObjectPublished($object)) {
			$this->currentlyPublishedObjects[$objectGUID] = $object;
		} else {
			// Do nothing, this will be present in the state folder if it has to be unpublished.
		}
	}
	
	public function diff() {
		$currentlyPublishedObjectGUIDs = array_keys($this->currentlyPublishedObjects);
		$pastPublishedObjectsGUIDs = array_keys($this->pastPublishedObjects);
		return array(
			'unpublished' => array_diff($pastPublishedObjectsGUIDs, $currentlyPublishedObjectGUIDs),
			'published' => array_diff($currentlyPublishedObjectGUIDs, $pastPublishedObjectsGUIDs),
		);
	}
	
	public function generateInsertXML($objectGUID) {
		$object = $this->currentlyPublishedObjects[$objectGUID];
		return InsertXMLGenerator::generate($object);
	}
	
	public function generateDeleteXML($objectGUID) {
		$objectPath = $this->pastPublishedObjects[$objectGUID];
		$xml = simplexml_load_file($objectPath);
		return DeleteXMLGenerator::generate($xml);
	}
}