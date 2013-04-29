<?php
namespace bonanza\reporting;
class InsertXMLGenerator extends BaseXMLGenerator {
	
	const CHANNELID = 88;
	const DATETIME_FORMAT = 'c';//'Y-m-d G:i:s';
	protected static $PRIORITIZED_FORMAT_IDS = array(2, 1);
	
	public static function generateXML($object) {
		$metadata = self::getMetadata($object, self::DKA2_METADATASCHEMAGUID);
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
		$result->addChild('NAME', strval($metadata->Title));
		$result->addChild('DESCRIPTION', strval($metadata->Description));
		
		$file = self::extractFileURL($object);
		if($file == null) {
			printf("\tNo files of a known type were associated with the object.\n");
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
				if(strlen($productionId) == 11) {
					$result->addChild('PRODUCTIONNUMBER', $productionId);
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
		foreach($object->Files as $file) {
			printf("\tSkipping file of type #%u (%s)\n", $file->FormatID, $file->URL);
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