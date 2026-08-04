<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempus.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

die;

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require("/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/panel/db.php");
set_time_limit(0);
ini_set('max_execution_time', 0);
ini_set('memory_limit', '4048M');

use Bitrix\Main\Application,
	Bitrix\Main\Loader;
	use Bitrix\Iblock\SectionTable;

class UpdatePS{
	public function __construct(){
		$this->db = new OLDDB;
		$this->CurDB = new DBPanel();
		;
    $this->iblockId = 12;
    $this->mathProps['BY'] = [
      492 => 1,
      493 => 2,
      494 => 3
    ];
    $this->mathProps['RU'] = [
      512 => 4,
      514 => 5,
    ];
		$this->mathDC = [
		    2104 => '5%',
		    2105 => '10%',
		    2106 => '15%',
		    2107 => '20%',
		    2108 => '25%',
		    2109 => '30%',
		    2110 => '35%',
		    2111 => '40%',
		    2112 => '45%',
		    2113 => '50%',
		    2114 => '55%',
		    2115 => '60%',
		    2116 => '65%'
		];
    $this->logFile = '/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/sites/logs/SP/lastlog.txt';
		$this->loadModules();
	}

	private function loadModules(){
		Loader::includeModule("main");
		Loader::includeModule("iblock");
  	Loader::IncludeModule("catalog");
  }

	public function updateAll(){
		$timeStart = date('Y.m.d G:i:s');
		$arWhere[] = [
		  'column' => 'code',
		  'operator' => '=',
		  'value' => 'PriceStockExchange'
		];
		$addArray = [
		  'status' => 'PROCESS',
		  'status_text' => 'Инициирован обмен',
		  'percent' => 0,
		  'time_start' => $timeStart,
		];
		$this->CurDB->update('sites_agents', $addArray, $arWhere);

		file_put_contents($this->logFile, "START".date('Y-m-d H:i:s'). PHP_EOL);
    $this->itemsBack = $this->getAll();
		//$this->itemsBack = $this->getById(87632);
		$addArray = [
			'status_text' => 'Обработка товаров из бек-офиса',
			'percent' => 50,
		];
		$this->CurDB->update('sites_agents', $addArray, $arWhere);

  	$xmlIds = array_keys($this->itemsBack);
    $this->itemsSite = $this->getCurrent($xmlIds);

    if (!empty($this->itemsBack) && !empty($this->itemsSite)) {
      $this->update();
    } else {
      file_put_contents($this->logFile, "Пустые массивы. Проверить скрипт." . PHP_EOL, FILE_APPEND);
    }

		$timeEnd = date('Y.m.d G:i:s');
		$addArray = [
		  'status' => 'COMPLETED',
		  'status_text' => 'Обмен завршен',
		  'percent' => 100,
		  'time_end' => $timeEnd,
		];
		$this->CurDB->update('sites_agents', $addArray, $arWhere);

  }

	public function sectionUpdate(){
		$this->exclude = $this->getExcludeSection();
		if (!empty($this->exclude)) {
			$this->deactiveSection();
		}

	}

	public function deactiveSection() {
			foreach ($this->exclude['RU'] as $key => $value) {
		    $arSelect = ["ID", "NAME"];
		    $arFilter = [
		        "IBLOCK_ID" => 51,
		        "NAME" => $value,
		    ];

		    $result = CIBlockElement::GetList([], $arFilter, false, false, $arSelect);
		    while ($el = $result->GetNext()) {
		       CIBlockElement::SetPropertyValuesEx($el['ID'], 51, array('ACTIVE_ADD_RU' => 281));
		    }
		}


		$arSelect = ["ID", "NAME"];
		$arFilter = [
		    "IBLOCK_ID" => 51,
		    "!NAME" => $this->exclude['RU'],
		];

		$result = CIBlockElement::GetList([], $arFilter, false, false, $arSelect);
		while ($el = $result->GetNext()) {
		    CIBlockElement::SetPropertyValuesEx($el['ID'], 51, array('ACTIVE_ADD_RU' => 280));
		}


		foreach ($this->exclude['BY'] as $key => $value2) {
			$arSelect = ["ID", "NAME"];
			$arFilter = [
					"IBLOCK_ID" => 51,
					"NAME" => $value2,
			];

			$result = CIBlockElement::GetList([], $arFilter, false, false, $arSelect);
			while ($el = $result->GetNext()) {
						CIBlockElement::SetPropertyValuesEx($el['ID'], 51, array('ACTIVE_ADD_BY' => 283));
				}
		}


		$arSelect = ["ID", "NAME"];
		$arFilter = [
				"IBLOCK_ID" => 29,
				"!NAME" => $this->exclude['BY'],
		];

		$result = CIBlockElement::GetList([], $arFilter, false, false, $arSelect);
		while ($el = $result->GetNext()) {
				CIBlockElement::SetPropertyValuesEx($el['ID'], 51, array('ACTIVE_ADD_BY' => 282));
		}
	}

	public function getExcludeSection()	{
		$result = $this->CurDB->query("SELECT * FROM sites_brand_exclude");
		$rows = $this->CurDB->fetchAll($result);
		foreach ($rows as $row) {
		  $arraySection[$row['site']][$row['brand_id']] = $row['name'];
		}
		unset($result);
		unset($rows);
		return $arraySection;
	}

  public function update() {
    file_put_contents($this->logFile, "Начало обновление элементов." . PHP_EOL, FILE_APPEND);
    file_put_contents($this->logFile, "-------------" . PHP_EOL, FILE_APPEND);
    foreach ($this->itemsSite as $key => $item) {

      file_put_contents($this->logFile, "Обновление элемента " . $item['PROPERTY_CML2_ARTICLE_VALUE'] . PHP_EOL, FILE_APPEND);

      if (isset($this->itemsBack[$item['XML_ID']])) {
        if (trim($this->itemsBack[$item['XML_ID']]['ARTICLE']) == trim($item['PROPERTY_CML2_ARTICLE_VALUE'])) {
          //чекаем изменения
					//DP_DISCOUNT
					if (empty($this->itemsBack[$item['XML_ID']]['DC_SALE']) || intval($this->itemsBack[$item['XML_ID']]['DC_SALE']) == 0 ) {
						CIBlockElement::SetPropertyValuesEx($item['ID'], 12, array('DP_DISCOUNT' => array()));
						CIBlockElement::SetPropertyValuesEx($item['ID'], 12, array('DP_DISCOUNT' => false));
						file_put_contents($this->logFile, "Обновление DP_DISCOUNT: Значение отчищенно" . PHP_EOL, FILE_APPEND);
					} else if (intval($item['PROPERTY_DP_DISCOUNT_VALUE']) != intval($this->mathDC[$this->itemsBack[$item['XML_ID']]['DC_SALE']])) {

							try {

								if(isset($this->mathDC[$this->itemsBack[$item['XML_ID']]['DC_SALE']])) {


										$propertyEnums = CIBlockPropertyEnum::GetList(
												array(),
												array(
														"IBLOCK_ID" => 12,
														"CODE" => "DP_DISCOUNT",
														"VALUE" => $this->mathDC[$this->itemsBack[$item['XML_ID']]['DC_SALE']]
												)
										);
										if ($enum = $propertyEnums->Fetch()) {
												$enumId = $enum["ID"];
										  	CIBlockElement::SetPropertyValuesEx($item['ID'], 12, array('DP_DISCOUNT' => $enumId));
												$newPriceRu = $this->itemsBack[$item['XML_ID']]['PRICE_RU'] / (1 - (intval($this->mathDC[$this->itemsBack[$item['XML_ID']]['DC_SALE']]) / 100 ));
												$newPriceBy = $this->itemsBack[$item['XML_ID']]['PRICE_BY'] / (1 - (intval($this->mathDC[$this->itemsBack[$item['XML_ID']]['DC_SALE']]) / 100 ));
												$this->itemsBack[$item['XML_ID']]['PRICE_RU'] = $newPriceRu;
												$this->itemsBack[$item['XML_ID']]['PRICE_BY'] = $newPriceBy;
												file_put_contents($this->logFile, "Обновление DP_DISCOUNT: ".$item['PROPERTY_DP_DISCOUNT_VALUE']." -> " .$this->mathDC[$this->itemsBack[$item['XML_ID']]['DC_SALE']]. PHP_EOL, FILE_APPEND);
										}


								}

							} catch (Exception $e) {
									file_put_contents($this->logFile, "Обновление DP_DISCOUNT: Ошибка DP_DISCOUNT." . $e->getMessage() . PHP_EOL, FILE_APPEND);
							}

					} else {
						file_put_contents($this->logFile, "Обновление DP_DISCOUNT: сво-во не изменилось, обновление не требуется" . PHP_EOL, FILE_APPEND);
						$newPriceRu = $this->itemsBack[$item['XML_ID']]['PRICE_RU'] / (1 - (intval($this->mathDC[$this->itemsBack[$item['XML_ID']]['DC_SALE']]) / 100 ));
						$newPriceBy = $this->itemsBack[$item['XML_ID']]['PRICE_BY'] / (1 - (intval($this->mathDC[$this->itemsBack[$item['XML_ID']]['DC_SALE']]) / 100 ));
						$this->itemsBack[$item['XML_ID']]['PRICE_RU'] = $newPriceRu;
						$this->itemsBack[$item['XML_ID']]['PRICE_BY'] = $newPriceBy;
					}
          //цена ру
           // if (trim($item['CATALOG_PRICE_1']) != trim($this->itemsBack[$item['XML_ID']]['PRICE_RU'])) {
            if (intval($this->itemsBack[$item['XML_ID']]['PRICE_RU']) != 0) {
              try {
                  $PriceExists = CPrice::GetList(
                      array(),
                      array(
                          "PRODUCT_ID" => $item['ID'],
                          "CATALOG_GROUP_ID" => 1,
													"CURRENCY" => 'RUB'
                      )
                  )->Fetch();

                  if ($PriceExists && !empty($PriceExists['ID'])) {
                      CPrice::Update(
                          $PriceExists['ID'],
                          [
                              'PRICE' => $this->itemsBack[$item['XML_ID']]['PRICE_RU'],
                              'CURRENCY' => 'RUB'
                          ]
                      );
                      file_put_contents($this->logFile, "Обновление цены RU:".$item['CATALOG_PRICE_1']." -> " .$this->itemsBack[$item['XML_ID']]['PRICE_RU']. PHP_EOL, FILE_APPEND);

                  } else {
                      CPrice::Add([
                          'PRODUCT_ID' => $item['ID'],
                          'CATALOG_GROUP_ID' => 1,
                          'PRICE' => $this->itemsBack[$item['XML_ID']]['PRICE_RU'],
                          'CURRENCY' => 'RUB'
                      ]);
                      file_put_contents($this->logFile, "Обновление цены RU:".$item['CATALOG_PRICE_1']." -> " .$this->itemsBack[$item['XML_ID']]['PRICE_RU']. PHP_EOL, FILE_APPEND);

                  }
                } catch (Exception $e) {
                    file_put_contents($this->logFile, "Обновление цены RU: Ошибка обновления цены." . $e->getMessage() . PHP_EOL, FILE_APPEND);
                }
            } else {
              file_put_contents($this->logFile, "Обновление цены RU: цена в беклфисе равна 0 или отсутствует." . PHP_EOL, FILE_APPEND);
            }
          // } else {
          //   file_put_contents($this->logFile, "Обновление цены RU: цена не изменилось, обновление не требуется (".$this->itemsBack[$item['XML_ID']]['PRICE_RU']." = " .$item['CATALOG_PRICE_1']. ")" . PHP_EOL, FILE_APPEND);
          // }
          //цена by
          // if (trim($item['CATALOG_PRICE_2']) != trim($this->itemsBack[$item['XML_ID']]['PRICE_BY'])) {
            if (intval($this->itemsBack[$item['XML_ID']]['PRICE_BY']) != 0) {
              try {
                  $PriceExists = CPrice::GetList(
                      array(),
                      array(
                          "PRODUCT_ID" => $item['ID'],
                          "CATALOG_GROUP_ID" => 2,
													"CURRENCY" => 'BYN'
                      )
                  )->Fetch();

                  if ($PriceExists && !empty($PriceExists['ID'])) {
                      CPrice::Update(
                          $PriceExists['ID'],
                          [
                              'PRICE' => $this->itemsBack[$item['XML_ID']]['PRICE_BY'],
                              'CURRENCY' => 'BYN'
                          ]
                      );
                      file_put_contents($this->logFile, "Обновление цены BY:".$item['CATALOG_PRICE_2']." -> " .$this->itemsBack[$item['XML_ID']]['PRICE_BY']. PHP_EOL, FILE_APPEND);
                  } else {
                      CPrice::Add([
                          'PRODUCT_ID' => $item['ID'],
                          'CATALOG_GROUP_ID' => 2,
                          'PRICE' => $this->itemsBack[$item['XML_ID']]['PRICE_BY'],
                          'CURRENCY' => 'BYN'
                      ]);
                      file_put_contents($this->logFile, "Обновление цены BY:".$item['CATALOG_PRICE_2']." -> " .$this->itemsBack[$item['XML_ID']]['PRICE_BY']. PHP_EOL, FILE_APPEND);
                  }

                } catch (Exception $e) {
                    file_put_contents($this->logFile, "Обновление цены BY: Ошибка обновления цены." . $e->getMessage() . PHP_EOL, FILE_APPEND);
                }
            } else {
              file_put_contents($this->logFile, "Обновление цены BY: цена в беклфисе равна 0 или отсутствует." . PHP_EOL, FILE_APPEND);
            }
          // } else {
          //   file_put_contents($this->logFile, "Обновление цены BY: цена не изменилось, обновление не требуется (".$this->itemsBack[$item['XML_ID']]['PRICE_BY']." = " .$item['CATALOG_PRICE_2']. ")" . PHP_EOL, FILE_APPEND);
          // }
          //остаток
          if (intval($item['CATALOG_QUANTITY']) != intval($this->itemsBack[$item['XML_ID']]['CATALOG_QUANTITY'])) {

              try {

                if (CCatalogProduct::GetByID($item['ID'])) {
                    CCatalogProduct::Update($item['ID'], ['QUANTITY' => $this->itemsBack[$item['XML_ID']]['CATALOG_QUANTITY']]);
                    file_put_contents($this->logFile, "Обновление остатка:".$item['CATALOG_QUANTITY']." -> " .$this->itemsBack[$item['XML_ID']]['CATALOG_QUANTITY']. PHP_EOL, FILE_APPEND);
                } else {
                    CCatalogProduct::Add(['ID' => $item['ID'], 'QUANTITY' => $this->itemsBack[$item['XML_ID']]['CATALOG_QUANTITY']]);
                    file_put_contents($this->logFile, "Обновление остатка:".$item['CATALOG_QUANTITY']." -> " .$this->itemsBack[$item['XML_ID']]['CATALOG_QUANTITY']. PHP_EOL, FILE_APPEND);
                }

              } catch (Exception $e) {
                  file_put_contents($this->logFile, "Обновление остатка: Ошибка Обновление остатка." . $e->getMessage() . PHP_EOL, FILE_APPEND);
              }

          } else {
            file_put_contents($this->logFile, "Обновление остатка: остаток не изменился, обновление не требуется (".$this->itemsBack[$item['XML_ID']]['CATALOG_QUANTITY']." = " .$item['CATALOG_QUANTITY']. ")" . PHP_EOL, FILE_APPEND);
          }
          //доступность ру
          if (intval($item['PROPERTY_AVAILABILITY_RU_ENUM_ID']) != intval($this->mathProps['RU'][$this->itemsBack[$item['XML_ID']]['AV_RU']])) {

              try {

                if(isset($this->mathProps['RU'][$this->itemsBack[$item['XML_ID']]['AV_RU']])) {
                    CIBlockElement::SetPropertyValuesEx($item['ID'], 12, array('AVAILABILITY_RU' => $this->mathProps['RU'][$this->itemsBack[$item['XML_ID']]['AV_RU']]));
                      file_put_contents($this->logFile, "Обновление наличия RU: ".$item['PROPERTY_AVAILABILITY_RU_ENUM_ID']." -> " .$this->mathProps['RU'][$this->itemsBack[$item['XML_ID']]['AV_RU']]. PHP_EOL, FILE_APPEND);
                }


              } catch (Exception $e) {
                  file_put_contents($this->logFile, "Обновление наличия RU: Ошибка Обновление наличия." . $e->getMessage() . PHP_EOL, FILE_APPEND);
              }

          } else {
            file_put_contents($this->logFile, "Обновление наличия RU: сво-во не изменилось, обновление не требуется" . PHP_EOL, FILE_APPEND);
          }
          //доступность бай
          if (intval($item['AVAILABILITY_BY_ENUM_ID']) != intval($this->mathProps['BY'][$this->itemsBack[$item['XML_ID']]['AV_BY']])) {
              try {

                if(isset($this->mathProps['BY'][$this->itemsBack[$item['XML_ID']]['AV_BY']])) {
                    CIBlockElement::SetPropertyValuesEx($item['ID'], 12, array('AVAILABILITY_BY' => $this->mathProps['BY'][$this->itemsBack[$item['XML_ID']]['AV_BY']]));
                    file_put_contents($this->logFile, "Обновление наличия BY: ".$item['PROPERTY_AVAILABILITY_BY_ENUM_ID']." -> " .$this->mathProps['BY'][$this->itemsBack[$item['XML_ID']]['AV_BY']]. PHP_EOL, FILE_APPEND);
                }


              } catch (Exception $e) {
                  file_put_contents($this->logFile, "Обновление наличия BY: Ошибка Обновление наличия." . $e->getMessage() . PHP_EOL, FILE_APPEND);
              }

          } else {
            file_put_contents($this->logFile, "Обновление наличия BY: сво-во не изменилось, обновление не требуется" . PHP_EOL, FILE_APPEND);
          }
					//DELIVERY_JSON
          if ($item['PROPERTY_DELIVERY_JSON_VALUE'] != $this->itemsBack[$item['XML_ID']]['DELIVERY_JSON']) {
              try {
                    CIBlockElement::SetPropertyValuesEx($item['ID'], 12, array('DELIVERY_JSON' => $this->itemsBack[$item['XML_ID']]['DELIVERY_JSON']));
                    file_put_contents($this->logFile, "Обновление DELIVERY_JSON: ".$item['PROPERTY_DELIVERY_JSON_VALUE']." -> " .$this->itemsBack[$item['XML_ID']]['DELIVERY_JSON']. PHP_EOL, FILE_APPEND);
              } catch (Exception $e) {
                  file_put_contents($this->logFile, "Обновление DELIVERY_JSON: Ошибка Обновление наличия." . $e->getMessage() . PHP_EOL, FILE_APPEND);
              }

          } else {
            file_put_contents($this->logFile, "Обновление DELIVERY_JSON: сво-во не изменилось, обновление не требуется" . PHP_EOL, FILE_APPEND);
          }
        } else {
          file_put_contents($this->logFile, "Дизметч по артикулу: Артикул[BACK] " .$this->itemsBack[$item['XML_ID']]['ARTICLE']. " / Артикул[SITE] " . $item['PROPERTY_CML2_ARTICLE_VALUE'] . PHP_EOL, FILE_APPEND);
          file_put_contents($this->logFile, "Обновление элемента остановленно!" . PHP_EOL, FILE_APPEND);
        }
      } else {
        file_put_contents($this->logFile, "Элемент не найден в массиве из бекофиса!" . PHP_EOL, FILE_APPEND);
      }
      file_put_contents($this->logFile, "-------------" . PHP_EOL, FILE_APPEND);
    }
  }


  public function getCurrent($ids) {
    $rows = [];

    $arSelect = Array(
       "ID",
       "XML_ID",
       "NAME",
       "ACTIVE",
       "PROPERTY_CML2_ARTICLE",
       "PROPERTY_AVAILABILITY_BY",
       "PROPERTY_AVAILABILITY_RU",
			 "PROPERTY_DP_DISCOUNT",
			 "PROPERTY_DELIVERY_JSON",
       "CATALOG_QUANTITY",
       "CATALOG_GROUP_1", // Цена ru
       "CATALOG_GROUP_2"  // Цена by
   );

		$arFilter = Array(
			"IBLOCK_ID"	=> $this->iblockId,
      'XML_ID' => $ids,
		);

    $result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
    while ($el = $result->GetNext()){

      $rows[] = $el;

    }
    return $rows;
  }

  public function getAll() {
    $DB = new OLDDB;
    $results = $this->db->query("SELECT
        p.ID as PRODUCT_ID,
        p.NAME as PRODUCT_NAME,
        c.QUANTITY as CATALOG_QUANTITY,
        sp.PROPERTY_123 as ARTICLE,
        MAX(CASE WHEN pp.CATALOG_GROUP_ID = 2 THEN pp.PRICE ELSE NULL END) as PRICE_BY,
        MAX(CASE WHEN pp.CATALOG_GROUP_ID = 5 THEN pp.PRICE ELSE NULL END) as PRICE_RU,
        sp.PROPERTY_267 as AV_BY,
				sp.PROPERTY_3110 as DELIVERY_JSON,
        sp.PROPERTY_282 as AV_RU,
				sp.PROPERTY_3087 as DC_SALE
    FROM
        b_iblock_element p
    LEFT JOIN
        b_catalog_product c ON c.ID = p.ID
    LEFT JOIN
        b_catalog_price pp ON pp.PRODUCT_ID = p.ID AND pp.CATALOG_GROUP_ID IN (2, 5)
    LEFT JOIN
        b_iblock_element_prop_s16 sp ON sp.IBLOCK_ELEMENT_ID = p.ID
    WHERE
        sp.PROPERTY_2844 = '1943'
    GROUP BY
        p.ID, p.NAME, c.QUANTITY");

    $rows = $this->db->fetchAll($results);
    $arResult = [];
    foreach ($rows as $item) {
        $arResult[$item['PRODUCT_ID']] = $item;
    }
    return $arResult;
  }

  public function getById($productId = 87632) {
    $DB = new OLDDB;
    $results = $this->db->query("SELECT
        p.ID as PRODUCT_ID,
        p.NAME as PRODUCT_NAME,
        c.QUANTITY as CATALOG_QUANTITY,
        sp.PROPERTY_123 as ARTICLE,
        MAX(CASE WHEN pp.CATALOG_GROUP_ID = 2 THEN pp.PRICE ELSE NULL END) as PRICE_BY,
        MAX(CASE WHEN pp.CATALOG_GROUP_ID = 5 THEN pp.PRICE ELSE NULL END) as PRICE_RU,
        sp.PROPERTY_267 as AV_BY,
        sp.PROPERTY_282 as AV_RU,
				sp.PROPERTY_3110 as DELIVERY_JSON,
				sp.PROPERTY_3087 as DC_SALE
    FROM
        b_iblock_element p
    LEFT JOIN
        b_catalog_product c ON c.ID = p.ID
    LEFT JOIN
        b_catalog_price pp ON pp.PRODUCT_ID = p.ID AND pp.CATALOG_GROUP_ID IN (2, 5)
    LEFT JOIN
        b_iblock_element_prop_s16 sp ON sp.IBLOCK_ELEMENT_ID = p.ID
    WHERE
        p.ID = ".intval($productId)."
    GROUP BY
        p.ID, p.NAME, c.QUANTITY");

    $rows = $this->db->fetchAll($results);
    $arResult = [];
    foreach ($rows as $item) {
        $arResult[$item['PRODUCT_ID']] = $item;
    }
    return $arResult;
  }

}

(new UpdatePS())->updateAll();
(new UpdatePS())->sectionUpdate();
?>
