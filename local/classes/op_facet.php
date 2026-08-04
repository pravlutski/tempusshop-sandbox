<?php
namespace Bitrix\Iblock\PropertyIndex;
use Bitrix\Catalog;
use \Bitrix\Main\Data\Cache;
class opFacet extends Facet{
	public function queryCache(array $filter, array $facetTypes = array(), $facetId = 0){
		
		$connection = \Bitrix\Main\Application::getConnection();
		$sqlHelper = $connection->getSqlHelper();

		$facetFilter = $this->getFacetFilter($facetTypes);
		if (!$facetFilter)
		{
			return false;
		}

		if ($filter)
		{
			$filter["IBLOCK_ID"] = $this->iblockId;

			$element = new \CIBlockElement;
			$element->strField = "ID";
			$element->prepareSql(array("ID"), $filter, false, false);
			$elementFrom = $element->sFrom;
			$elementWhere = $element->sWhere;
		}
		else
		{
			$elementFrom = "";
			$elementWhere = "";
		}

		$facets = array();
		if ($facetId)
		{
			$facets[] = array(
				"where" => $this->getWhere($facetId),
				"facet" => array($facetId),
			);
		}
		else
		{
			foreach ($facetFilter as $facetId)
			{
				$where = $this->getWhere($facetId);

				$found = false;
				foreach ($facets as $i => $facetWhereAndFacets)
				{
					if ($facetWhereAndFacets["where"] == $where)
					{
						$facets[$i]["facet"][] = $facetId;
						$found = true;
						break;
					}
				}

				if (!$found)
				{
					$facets[] = array(
						"where" => $where,
						"facet" => array($facetId),
					);
				}
			}
		}

		$sqlUnion = array();
		foreach ($facets as $facetWhereAndFacets)
		{
			$where = $facetWhereAndFacets["where"];
			$facetFilter = $facetWhereAndFacets["facet"];

			$sqlSearch = array("1=1");

			if (empty($where))
			{//prent("0");
			
				$sqlUnionorig[] = "
					SELECT
						F.FACET_ID
						,F.VALUE
						,MIN(F.VALUE_NUM) MIN_VALUE_NUM
						,MAX(F.VALUE_NUM) MAX_VALUE_NUM
						".($connection instanceof \Bitrix\Main\DB\MysqlCommonConnection
							?",MAX(case when LOCATE('.', F.VALUE_NUM) > 0 then LENGTH(SUBSTRING_INDEX(F.VALUE_NUM, '.', -1)) else 0 end)"
							:",MAX(".$sqlHelper->getLengthFunction("ABS(F.VALUE_NUM) - FLOOR(ABS(F.VALUE_NUM))")."+1-".$sqlHelper->getLengthFunction("0.1").")"
						)." VALUE_FRAC_LEN
						,COUNT(DISTINCT F.ELEMENT_ID) ELEMENT_COUNT
					FROM
						".($elementFrom
								?$elementFrom."
							INNER JOIN ".$this->storage->getTableName()." F ON BE.ID = F.ELEMENT_ID"
								:$this->storage->getTableName()." F"
							)."
					WHERE
						F.SECTION_ID = ".$this->sectionId."
						and F.FACET_ID in (".implode(",", $facetFilter).")
						".$elementWhere."
					GROUP BY
						F.FACET_ID, F.VALUE
				";
				
				$sqlUnion[] = "
					SELECT
						F.FACET_ID
						,F.VALUE
						,MIN(F.VALUE_NUM) MIN_VALUE_NUM
						,MAX(F.VALUE_NUM) MAX_VALUE_NUM
						".($connection instanceof \Bitrix\Main\DB\MysqlCommonConnection
							?",MAX(case when LOCATE('.', F.VALUE_NUM) > 0 then LENGTH(SUBSTRING_INDEX(F.VALUE_NUM, '.', -1)) else 0 end)"
							:",MAX(".$sqlHelper->getLengthFunction("ABS(F.VALUE_NUM) - FLOOR(ABS(F.VALUE_NUM))")."+1-".$sqlHelper->getLengthFunction("0.1").")"
						)." VALUE_FRAC_LEN
						,COUNT(DISTINCT F.ELEMENT_ID) ELEMENT_COUNT
					FROM
						" . $this->storage->getTableName() . " F " . "
					WHERE
						F.SECTION_ID = ".$this->sectionId."
						and F.FACET_ID in (".implode(",", $facetFilter).")
					GROUP BY
						F.FACET_ID, F.VALUE 
				";
				
				continue;
			}
			elseif (count($where) == 1)
			{//prent("1");
				$fcJoin = "INNER JOIN ".$this->storage->getTableName()." FC on FC.ELEMENT_ID = BE.ID";
				foreach ($where as $facetWhere)
				{
					$sqlWhere = $this->whereToSql($facetWhere, "FC");
					if ($sqlWhere)
						$sqlSearch[] = $sqlWhere;
				}
			}
			elseif (count($where) <= 5)
			{//prent("5");
				$subJoin = "";
				$subWhere = "";
				$i = 0;
				foreach ($where as $facetWhere)
				{
					if ($i == 0)
						$subJoin .= "FROM ".$this->storage->getTableName()." FC$i\n";
					else
						$subJoin .= "INNER JOIN ".$this->storage->getTableName()." FC$i ON FC$i.ELEMENT_ID = FC0.ELEMENT_ID\n";

					$sqlWhere = $this->whereToSql($facetWhere, "FC$i");
					if ($sqlWhere)
					{
						if ($subWhere)
							$subWhere .= "\nAND ".$sqlWhere;
						else
							$subWhere .= $sqlWhere;
					}

					$i++;
				}
				$fcJoin = "
					INNER JOIN (
						SELECT FC0.ELEMENT_ID
						$subJoin
						WHERE
						$subWhere
					) FC on FC.ELEMENT_ID = BE.ID
				";
				//prent($fcJoin);
			}
			else
			{//prent("else");
				$condition = array();
				foreach ($where as $facetWhere)
				{
					$sqlWhere = $this->whereToSql($facetWhere, "FC0");
					if ($sqlWhere)
						$condition[] = $sqlWhere;
				}
				$fcJoin = "
						INNER JOIN (
							SELECT FC0.ELEMENT_ID
							FROM ".$this->storage->getTableName()." FC0
							WHERE FC0.SECTION_ID = ".$this->sectionId."
							AND (
							(".implode(")OR(", $condition).")
							)
						GROUP BY FC0.ELEMENT_ID
						HAVING count(DISTINCT FC0.FACET_ID) = ".count($condition)."
						) FC on FC.ELEMENT_ID = BE.ID
					";
			}

			$sqlUnion[] = "
				SELECT
					F.FACET_ID
					,F.VALUE
					,MIN(F.VALUE_NUM) MIN_VALUE_NUM
					,MAX(F.VALUE_NUM) MAX_VALUE_NUM
					".($connection instanceof \Bitrix\Main\DB\MysqlCommonConnection
						?",MAX(case when LOCATE('.', F.VALUE_NUM) > 0 then LENGTH(SUBSTRING_INDEX(F.VALUE_NUM, '.', -1)) else 0 end)"
						:",MAX(".$sqlHelper->getLengthFunction("ABS(F.VALUE_NUM) - FLOOR(ABS(F.VALUE_NUM))")."+1-".$sqlHelper->getLengthFunction("0.1").")"
					)." VALUE_FRAC_LEN
					,COUNT(DISTINCT F.ELEMENT_ID) ELEMENT_COUNT
				FROM
					".$this->storage->getTableName()." F
					INNER JOIN (
						SELECT BE.ID
						FROM
							".($elementFrom
								?$elementFrom
								:"b_iblock_element BE"
							)."
							".$fcJoin."
						WHERE ".implode(" AND ", $sqlSearch)."
						".$elementWhere."
					) E ON E.ID = F.ELEMENT_ID
				WHERE
					F.SECTION_ID = ".$this->sectionId."
					and F.FACET_ID in (".implode(",", $facetFilter).")
				GROUP BY
					F.FACET_ID, F.VALUE
			";
			//prent($sqlSearch);
			$sqlUnion2[] = "
				SELECT
					F.FACET_ID
					,F.VALUE
					,MIN(F.VALUE_NUM) MIN_VALUE_NUM
					,MAX(F.VALUE_NUM) MAX_VALUE_NUM
					".($connection instanceof \Bitrix\Main\DB\MysqlCommonConnection
						?",MAX(case when LOCATE('.', F.VALUE_NUM) > 0 then LENGTH(SUBSTRING_INDEX(F.VALUE_NUM, '.', -1)) else 0 end)"
						:",MAX(".$sqlHelper->getLengthFunction("ABS(F.VALUE_NUM) - FLOOR(ABS(F.VALUE_NUM))")."+1-".$sqlHelper->getLengthFunction("0.1").")"
					)." VALUE_FRAC_LEN
					,COUNT(DISTINCT F.ELEMENT_ID) ELEMENT_COUNT
				FROM
					".$this->storage->getTableName()." F 
					".(count($sqlSearch) > 1 ? "INNER JOIN (
						SELECT FC.ELEMENT_ID
						FROM 
							".$this->storage->getTableName()." FC
						WHERE ".implode(" AND ", $sqlSearch)."
					) E ON E.ELEMENT_ID = F.ELEMENT_ID" : "")."
				WHERE
					F.SECTION_ID = ".$this->sectionId."
					and F.FACET_ID in (".implode(",", $facetFilter).")
				GROUP BY
					F.FACET_ID, F.VALUE 
			";
			
//AddMessage2Log(implode("\nUNION ALL\n", $sqlUnion));
		}
		//AddMessage2Log(implode("\nUNION ALL\n", $sqlUnion));
		//prent(implode("\nUNION ALL\n", $sqlUnion2));
		//global $USER;
		$obCache = Cache::createInstance();
		$cache_id = md5(serialize($sqlUnion));
		$cache_path = "/op/smart.filter.facet/" . $this->sectionId;// . "/" . md5($sql);
		//prent($cache_id);
		if($obCache->InitCache(3600, $cache_id, $cache_path)) {
			$result = $obCache->GetVars();

			return $result;

		} else {
			$obCache->StartDataCache();
			
			//$ob = $this->query($filter, $facetTypes = array(), $facetId = 0);
			//$ob = $connection->query(implode("\nUNION ALL\n", $sqlUnion));
			$ob = $connection->query(implode("\nUNION\n", $sqlUnion));
			
			while ($rowData = $ob->fetch()){
				$result[] = $rowData;
			}
			
			$obCache->EndDataCache($result);
		}
		
		return $result;
	}
	
	protected function getFacetFilter(array $facetTypes)
	{
		// op 
		/*$ar = array(252,174,248,250,254,256,258,260,5484,262,264,266,268,270,5482,282,286,288,528,1896,5492,560,5496);
		if(SITE_ID == "s1") $ar[] = 168;
		if(SITE_ID == "s2") {$ar[] = 492;$ar[] = 534;}
		if(SITE_ID == "s3") $ar[] = 742;
		return $ar;*/
		global $DB;
		
		$strSql = "SELECT PROPERTY_ID FROM b_iblock_section_property WHERE IBLOCK_ID = '16' AND SMART_FILTER = 'Y' GROUP BY PROPERTY_ID";

		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
			
		while($row = $results->Fetch()){
			$arProp[$row["PROPERTY_ID"] * 2] = $row["PROPERTY_ID"] * 2;
		}
		
		if(SITE_ID == "s1") {unset($arProp[492],$arProp[534],$arProp[742],$arProp[740]);}
		if(SITE_ID == "s2") {unset($arProp[168],$arProp[742],$arProp[564],$arProp[740]);}
		if(SITE_ID == "s3") {unset($arProp[168],$arProp[492],$arProp[534],$arProp[564]);}
		
		$arProp = array_values($arProp);
		//prent($arProp);
		return $arProp;
	}
}
?>