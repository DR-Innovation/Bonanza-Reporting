<?php
namespace bonanza\reporting\xml\chaos;
use bonanza\reporting\xml\BaseXMLGenerator;
class DeleteXMLGenerator extends \bonanza\reporting\xml\BaseXMLGenerator {
	
	public static function generateXML($insertedXML) {
		if(!$insertedXML instanceof \SimpleXMLElement) {
			throw new \RuntimeException('generateXML was called with something that was not a SimpleXMLElement.');
		}
		$insertedXML->TRANSACTIONTYPE = "D";
		$insertedXML->PUBLISHINGTIMEEND = date(BaseXMLGenerator::DATETIME_FORMAT);
		return $insertedXML;
	}
	
}