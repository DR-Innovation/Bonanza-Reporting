<?php
namespace bonanza\reporting;
class DeleteXMLGenerator extends BaseXMLGenerator {
	
	public static function generateXML($insertedXML) {
		if(!$insertedXML instanceof \SimpleXMLElement) {
			throw new \RuntimeException('generateXML was called with something that was not a SimpleXMLElement.');
		}
		$insertedXML->TRANSACTIONTYPE = "D";
		$insertedXML->PUBLISHINGTIMEEND = date('Y-m-d G:i:s');
		return $insertedXML;
	}
	
}