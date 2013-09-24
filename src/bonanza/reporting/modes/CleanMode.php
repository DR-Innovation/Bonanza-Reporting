<?php
namespace bonanza\reporting\modes;
use bonanza\reporting\BonanzaReportingUtility;
class CleanMode extends BaseMode {
	
	public function start() {
		echo "Cleaning the state folder! Warning: This will delete all uncommitted files in the state.\n";
		
		$files = array_merge(
			array(),
			glob($this->_options['state-folder'] . DIRECTORY_SEPARATOR . 'as-is' . DIRECTORY_SEPARATOR . '*'),
			glob($this->_options['state-folder'] . DIRECTORY_SEPARATOR . 'difference' . DIRECTORY_SEPARATOR . '*')
		);
		
		if(count($files) == 0) {
			echo "No files to delete.";
		} else {
			$f = 0;
			BonanzaReportingUtility::progress_reset(count($files));
			foreach($files as $file) {
				if(is_file($file)) {
					// Delete file
					unlink($file);
				}
				BonanzaReportingUtility::progress_update($f++);
			}
		}
	} 
	
	protected function sanityCheckOptions() {
	}
	
}