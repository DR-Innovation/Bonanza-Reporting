<?php
namespace bonanza\reporting\xml;
abstract class BaseXMLGenerator {

	const CHANNELID = 88;
	const DATETIME_FORMAT = 'c';//'Y-m-d G:i:s';
	
	const SCHEMA = "../../../schemas/CMS_CLICKSTAT_CV_v1.1.xsd";
	const DKA2_METADATASCHEMAGUID = "5906a41b-feae-48db-bfb7-714b3e105396";
	
	protected static $schema;
	
	protected static function getSchema() {
		if(self::$schema == null) {
			$schemaPath = realpath(__DIR__ . self::SCHEMA);
			self::$schema = $schemaPath;
		}
		return self::$schema;
	}
	
	/**
	 * Generates XML from an object.
	 * @param unknown $reference Some reference to the object from which the XML should be generated.
	 * @throws \Exception If the subclass didn't implement the method.
	 * @return \SimpleXMLElement A simple XML document.
	 */
	protected static function generateXML($reference) {
		throw new \Exception("The static generateXML method has not been implemented by the extending class.");
	}
	
	public static function generate($reference) {
		$clazz = get_called_class();
		$xml = $clazz::generateXML($reference);
		// Check against schema.
		$schema = self::getSchema();
		return $xml;
	}
}