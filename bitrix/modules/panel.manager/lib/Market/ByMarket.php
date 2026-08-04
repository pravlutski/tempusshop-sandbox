<?php
namespace Panel\Manager\Market;

class ByMarket extends AbstractMarket
{
    public function getCompetitorPrices(array $articles): array
    {
		$objPricelist = new \CPanelPricelist();
		$arTmp = $arArticleAlt = array();
		
		$articles = array_unique($articles);
		$res = $this->findElementsByArticles($articles);

		foreach ($res as $item) {
			$arTmp[$item["CML2_ARTICLE"]] = $item["MODEL_ONLINER"];
		}
		
		//сразу выбираем артикулы из свойства товара "Артикул Онлайнера" для подмены
		/*$res = \CIBlockElement::getList(
			array(),
			array(
				'IBLOCK_ID' => \CProSet::IB_CATALOG,
				"=PROPERTY_CML2_ARTICLE" => $articles,
				"!PROPERTY_MODEL_ONLINER" => false
			),
			false,
			false,
			array(
				"ID",
				"PROPERTY_CML2_ARTICLE",
				"PROPERTY_MODEL_ONLINER",
			)
		);
		while ($row = $res->getNext()) {
			$arTmp[$row["PROPERTY_CML2_ARTICLE_VALUE"]] = $row["PROPERTY_MODEL_ONLINER_VALUE"];
		}*/
		
		foreach($articles as $article){
			if($arTmp[$article]){
				if ( strripos($arTmp[$article], ' ') ){
					// $v = end( explode(' ', $v) );
					$arArticleAlt[] = end( explode(' ', $arTmp[$article]) );
				}else{
					$arArticleAlt[] = $arTmp[$article];
				}
			} else {
				$arArticleAlt[] = $article;
			}
		}
		// получаем цены онлайнера
		$tmp2 = $objPricelist->getOnlinerPriceByFilter(array("model" => $arArticleAlt), false);

		$price = array();
		foreach($tmp2 as $key => $arItem){
			if($name = array_search($arItem["name"], $arTmp)){
				$price[$name] = array(
					"name" => $name,
					"minPrice" => $arItem["minPrice"],
					"minPrice2" => $arItem["minPrice2"],
					"minPrice3" => $arItem["minPrice3"],
				);
			}else{
				$price[$arItem["name"]] = array(
					"name" => $arItem["name"],
					"minPrice" => $arItem["minPrice"],
					"minPrice2" => $arItem["minPrice2"],
					"minPrice3" => $arItem["minPrice3"],
				);
			}
		}

		// дополняем товарами из общей
        $objPricelist = new \CPanelPricelist();
		$price2 = $objPricelist->getCompetitorPriceByFilter($this->lowerMarketCode, ["article" => $articles]);

		$this->monitoring->applyBrandDiscounts($price2);

		$price2 = $this->prepareMinCompetitorPrices($price2);
		foreach ($price2 as $article => $item) {
			if ($price[$article]) {
				
				$p = [
					$price[$article]['minPrice'],
					$price[$article]['minPrice2'],
					$price[$article]['minPrice3'],
					$price2[$article]['minPrice'],
					$price2[$article]['minPrice2'],
					$price2[$article]['minPrice3'],
				];
				$p = array_values(
					array_unique(
						array_filter(
							array_map('floatval', $p),
							function($v) { return $v > 0; }
						)
					)
				);
				
				if ($p[0]) {
					$price[$article]['minPrice'] = $p[0];
				}
				if ($p[1]) {
					$price[$article]['minPrice2'] = $p[1];
				}
				if ($p[2]) {
					$price[$article]['minPrice3'] = $p[2];
				}
			} else {
				$price[$article] = $item;
			}
		}
		//prent($price);
		return $price;
    }
	
	public function findElementsByArticles(array $articles, $iblockId = 16) {
		if (empty($articles)) {
			return [];
		}
		
		$connection = \Bitrix\Main\Application::getConnection();
		$tempTableName = 'temp_articles_' . uniqid();
		
		$connection->query("CREATE TEMPORARY TABLE {$tempTableName} (article VARCHAR(255) PRIMARY KEY)");
		
		$chunks = array_chunk($articles, 500);
		foreach ($chunks as $chunk) {
			$values = [];
			foreach ($chunk as $article) {
				$values[] = "('" . $connection->getSqlHelper()->forSql($article) . "')";
			}
			$connection->query("INSERT INTO {$tempTableName} (article) VALUES " . implode(',', $values));
		}
		
		$sql = "
			SELECT BE.ID, FPS0.PROPERTY_123 as CML2_ARTICLE, FPS0.PROPERTY_255 as MODEL_ONLINER
			FROM b_iblock_element BE
			INNER JOIN b_iblock_element_prop_s16 FPS0 ON FPS0.IBLOCK_ELEMENT_ID = BE.ID
			INNER JOIN {$tempTableName} TA ON TA.article = FPS0.PROPERTY_123
			WHERE BE.IBLOCK_ID = {$iblockId}
			  AND BE.WF_STATUS_ID = 1 
			  AND BE.WF_PARENT_ELEMENT_ID IS NULL
			  AND FPS0.PROPERTY_255 IS NOT NULL AND FPS0.PROPERTY_255 != ''
		";
		
		$result = $connection->query($sql);
		$data = $result->fetchAll();
		
		$connection->query("DROP TEMPORARY TABLE {$tempTableName}");
		
		return $data;
	}
}