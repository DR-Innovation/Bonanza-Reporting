<?php
namespace bonanza\reporting;
abstract class BaseXMLGenerator {
	
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
	
	public abstract static function generateXML($reference);
	
	public static function generate($reference) {
		$clazz = get_called_class();
		$xml = $clazz::generateXML($reference);
		// Check against schema.
		$schema = self::getSchema();
		return $xml;
	}
	
	public static function getMetadata($object, $metadataSchemaGUID) {
		foreach($object->Metadatas as $metadata) {
			if($metadata->MetadataSchemaGUID == $metadataSchemaGUID) {
				return simplexml_load_string($metadata->MetadataXML);
			}
		}
		return null;
	}
}