<?php
namespace bonanza\reporting\xml\dbdump;
class InsertXMLGenerator extends \bonanza\reporting\xml\BaseXMLGenerator {
	
	protected static $PRIORITIZED_FORMAT_IDS = array(2, 1);
	
	protected static function generateXML($dbdump_row) {
		
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
		
		$result->addChild('CHANNELID', $dbdump_row['CHANNEL_ID']);
		$result->addChild('MEDIAID', $dbdump_row['CHAOS_GUID']);
		$result->addChild('TRANSACTIONTYPE', 'I');
		if($dbdump_row['PUB_TITLE']) {
			$result->addChild('NAME', htmlspecialchars($dbdump_row['PUB_TITLE']));
		}
		if($dbdump_row['PUB_DESCRIPTION']) {
			$result->addChild('DESCRIPTION', htmlspecialchars($dbdump_row['PUB_DESCRIPTION']));
		}
		
		$result->addChild('FILENAME', $dbdump_row['ON_DEMAND_FILENAME']);
		$result->addChild('PATH', $dbdump_row['ON_DEMAND_RESOURCE_PATH_UPPER']);
		
		// Unknown from datadump! This might be a problem ...
		$result->addChild('STREAMDURATION', 0);
		
		$result->addChild('URL', $dbdump_row['PUBLIC_URL']);

		if(strlen($dbdump_row['PRODUCTION_NO']) == 9) {
			$dbdump_row['PRODUCTION_NO'] = '00' . $dbdump_row['PRODUCTION_NO'];
		}
		$productionNumberMatchs = array();
		if(preg_match('/[0-9]{11}/', $dbdump_row['PRODUCTION_NO'], $productionNumberMatchs) === 1) {
			$result->addChild('PRODUCTIONNUMBER', $productionNumberMatchs[0]);
		}
		
		$startTime = strtotime($dbdump_row['START_TIME']);
		$result->addChild('PUBLISHINGTIMESTART', date(self::DATETIME_FORMAT, $startTime));
		
		return $result;
	}
}