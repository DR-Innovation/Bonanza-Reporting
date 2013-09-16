<?php
namespace bonanza\reporting\modes;
use bonanza\reporting\BonanzaReportingUtility;
class RestoreMode extends BaseMode {
	
	const UUID_PATTERN = '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/';
	
	protected static $EXPECTED_HEADER = array(
		"PRODUCTION_NO",
		"CHANNEL_ID",
		"START_TIME",
		"FIRST_PUB",
		"PUB_DESCRIPTION",
		"PUB_SOURCE_URL",
		"PUB_TITLE",
		"LAST_CHANGED_DATE",
		"ON_DEMAND_FILENAME",
		"PUB_SOURCE_URL_UPPER",
		"ON_DEMAND_RESOURCE_PATH_UPPER",
		"PUBLIC_URL"
	);
	
	public function start() {
		echo "Starting to restore state form the datafile.\n";
		$this->loadDatabaseDump();
		
		echo "Restoring from " . count($this->_dbdump) . " entries.\n";
		$xml_generator = new \bonanza\reporting\xml\dbdump\InsertXMLGenerator();
		foreach($this->_dbdump as $dbdump_row) {
			if(strstr($dbdump_row['PUB_SOURCE_URL'], 'dka/') !== false) {
				$dbdump_row['CHAOS_GUID'] = strtolower(substr($dbdump_row['PUB_SOURCE_URL'], 4));
			} else {
				echo 'Skipping an object with a strange PUB_SOURCE_URL: ' . print_r($dbdump_row, true);
			}
			if(preg_match(self::UUID_PATTERN, $dbdump_row['CHAOS_GUID']) == 0) {
				echo 'Skipping an object with a strange GUID: ' . $dbdump_row['CHAOS_GUID'];
				continue;
			}
			// Generate and validate the XML.
			$insert_xml = $xml_generator->generate($dbdump_row);
			// Save the XML to the as-is state folder.
			BonanzaReportingUtility::saveXMLToFile($insert_xml, 'as-is', $dbdump_row['CHAOS_GUID'] . '.xml');
		}
	} 
	
	protected function sanityCheckOptions() {
		if(!array_key_exists('dbdump', $this->_options)) {
			throw new \RuntimeException("You have to specify a 'dbdump' CSV-file to restore from.");
		}
		
		$this->_options['dbdump'] = realpath($this->_options['dbdump']);
		if($this->_options['dbdump'] === false || !is_readable($this->_options['dbdump'])) {
			throw new \RuntimeException("You have to specify a readable 'dbdump' CSV-file to restore from.");
		}
	}
	
	/**
	 * The legacy datadump to restore from.
	 * @var string[][]
	 */
	protected $_dbdump;
	
	protected function loadDatabaseDump() {
		$result = file_get_contents($this->_options['dbdump']);
		$result = explode("\n", $result);

		$header = explode("\t", $result[0]);
		foreach($header as &$header_cell) {
			$header_cell = trim($header_cell, '"');
		}
		
		// Skip the first row.
		if($header !== self::$EXPECTED_HEADER) {
			error_log('The dbdump is in an unexpected format.');
			exit;
		}

		// Shift the header off the top of the stack.
		array_shift($result);
		
		$this->_dbdump = array();
		foreach($result as $row) {
			$row_exploded = explode("\t", $row);
			if(count($row_exploded) == 1) {
				continue; // Skip an empty line.
			}
			$row = array();
			$c = 0;
			foreach($row_exploded as $cell) {
				if(substr($cell, 0, 1) == '"' && substr($cell, -1, 1) == '"') {
					$cell = substr($cell, 1, strlen($cell)-2);
				}
				$row[self::$EXPECTED_HEADER[$c++]] = $cell;
			}
			$this->_dbdump[] = $row;
		}
	}
	
}