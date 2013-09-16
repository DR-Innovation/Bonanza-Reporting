<?php
namespace bonanza\reporting\xml\chaos;
class InsertXMLGenerator extends \bonanza\reporting\xml\BaseXMLGenerator {
	
	const CHANNELID = 88;
	const DATETIME_FORMAT = 'c';//'Y-m-d G:i:s';
	protected static $PRIORITIZED_FORMAT_IDS = array(8, 23);
	
	public static function generateXML($object) {
		assert($object instanceof \CHAOS\Portal\Client\Data\Object);
		$metadata = $object->get_metadata(self::DKA2_METADATASCHEMAGUID);
		if($metadata == null) {
			printf("\tThe correct metadataschema was not associated with the object.\n");
			return null;
		}
		
		$result = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><CMS_CLICK xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="CMS_CLICKSTAT_CV_v1.1.xsd" />');
		/*
		 * <xs:element name="RAPID" type="xs:string" minOccurs="0"/> 
		 * <xs:element name="CHANNELID" type="xs:integer"/>
		 * <xs:element name="MEDIAID" type="xs:string"/>
		 * <xs:element name="TRANSACTIONTYPE" type="transactionType"/> 
		 * <xs:element name="NAME" type="xs:string" minOccurs="0"/>
		 * <xs:element name="DESCRIPTION" type="xs:string" minOccurs="0"/>
		 * <xs:element name="FILENAME" type="xs:string"/>
		 * <xs:element name="PATH" type="xs:string"/>
		 * <xs:element name="STREAMDURATION" type="xs:integer"/> 
		 * <xs:element name="URL" type="xs:string"/>
		 * <xs:element name="ITEMID" type="xs:integer" minOccurs="0"/>
		 * <xs:element name="PRODUCTIONNUMBER" type="productionNumberType" minOccurs="0"/> 
		 * <xs:element name="MOBSMPTEUMID" type="xs:string" minOccurs="0"/>
		 * <xs:element name="MUSAID" type="xs:integer" minOccurs="0"/> 
		 * <xs:element name="PUBLISHINGTIMESTART" type="xs:dateTime"/>
		 * <xs:element name="PUBLISHINGTIMEEND" type="xs:dateTime" minOccurs="0"/>	  
		 * <xs:element name="AGREEMENTID" type="xs:integer" minOccurs="0"/>
		 * <xs:element name="ORIGINALTITLE" type="xs:string" minOccurs="0"/>
		 * <xs:element name="SENTFIRSTTIME" type="xs:dateTime" minOccurs="0"/>
		 **/
		
		$result->addChild('CHANNELID', self::CHANNELID);
		$result->addChild('MEDIAID', $object->GUID);
		$result->addChild('TRANSACTIONTYPE', 'I');
		$result->addChild('NAME', htmlspecialchars(strval($metadata->Title)));
		$result->addChild('DESCRIPTION', htmlspecialchars(strval($metadata->Description)));
		
		$file = self::extractFileURL($object);
		if($file == null) {
			error_log("No files of a known type were associated with the object.");
			return null;
		}
			
		$filePathInfo = pathinfo(strval($file->URL));
		$result->addChild('FILENAME', $filePathInfo['basename']);
		$result->addChild('PATH', $filePathInfo['dirname']);
		
		foreach($metadata->Metafield as $metafield) {
			if($metafield->Key == "Duration") {
				$result->addChild('STREAMDURATION', intval($metafield->Value));
				break;
			}
		}
		
		$result->addChild('URL', sprintf("http://www.danskkulturarv.dk/chaos_post/%s/", $object->GUID));
		
		$result->addChild('ITEMID', intval($metadata->ExternalIdentifier));
		
		foreach($metadata->Metafield as $metafield) {
			if($metafield->Key == "ProductionId") {
				$productionId = strval($metafield->Value);
				$productionNumberMatchs = array();
				if(preg_match('/[0-9]{11}/', $productionId, $productionNumberMatchs) === 1) {
					$result->addChild('PRODUCTIONNUMBER', $productionNumberMatchs[0]);
					break;
				}
			}
		}
		
		$result->addChild('PUBLISHINGTIMESTART', date(self::DATETIME_FORMAT, self::extractEarliestPublishDate($object)));
		$latestPublishDate = self::extractLatestPublishDate($object);
		if($latestPublishDate != null) {
			$result->addChild('PUBLISHINGTIMEEND',  date(self::DATETIME_FORMAT, $latestPublishDate));
		}
		
		return $result;
	}
	
	protected static function extractFileURL($object, $prioritizedFormatIDs = null) {
		if($prioritizedFormatIDs == null) {
			// Default value.
			$prioritizedFormatIDs = self::$PRIORITIZED_FORMAT_IDS;
		}
		foreach($prioritizedFormatIDs as $formatId) {
			foreach($object->Files as $file) {
				if($file->FormatID == $formatId) {
					return $file;
				}
			}
		}
		// No hope ..
		error_log("Couldn't find a file URL for the object " . $object->GUID);
		foreach($object->Files as $file) {
			error_log(sprintf("Skipping file of type #%u (%s)", $file->FormatID, $file->URL));
		}
		return null;
	}
	
	protected static function extractEarliestPublishDate($object) {
		$result = null;
		foreach($object->AccessPoints as $accesspoint) {
			if($result == null || $result > $accesspoint->StartDate) {
				$result = $accesspoint->StartDate;
			}
		}
		return $result;
	}
	
	protected static function extractLatestPublishDate($object) {
		$result = null;
		foreach($object->AccessPoints as $accesspoint) {
			if($accesspoint->EndDate == null) {
				return null;
			}
			if($result == null || $result < $accesspoint->EndDate) {
				$result = $accesspoint->EndDate;
			}
		}
		return $result;
	}
}