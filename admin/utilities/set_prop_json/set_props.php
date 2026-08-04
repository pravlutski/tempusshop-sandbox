<?require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');?>
<?php
// Отвечаем только на Ajax
if ($_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') {return;}
global $USER;
CModule::IncludeModule("main");
CModule::IncludeModule("iblock");
CModule::IncludeModule('panel.manager');

$arGroups = $USER->GetUserGroupArray();

if (!$USER->IsAdmin() && !in_array(7, $arGroups) && !in_array(6, $arGroups)) 
{
    $APPLICATION->AuthForm(GetMessage("PERMISION_DENIED"));
    return ;
}



set_time_limit(3600);
$objContent = new CPanelContent;
$objUtils = new CPanelUtils;
$objProduct = new CPanelProduct;
$objBrand = new CPanelBrand;

$arBrand = $objBrand->getList();

foreach($arBrand as $k => $v) {

	$arClearStr[] = mb_strtoupper($v["name"]);
	//альтернативные бренды
	if(strlen($v["alt_name"]) > 0){
		$tmp = explode("|", $v["alt_name"]);
		foreach($tmp as $key => &$name){
			$name = trim($name);
			if(strlen($name) > 0){
				$arAltBrand[] = array(
					'id' => $v['id'],
					'name' => mb_strtoupper($name, "UTF-8"),
					'regular' => $v['regular'],
				);

				$arClearStr[] = mb_strtoupper($name, "UTF-8");
			}
		}
		unset($name);
	}
}
if(is_array($arAltBrand) && count($arAltBrand) > 0) $arBrand = array_merge($arAltBrand, $arBrand);
//prent($arBrand);
//$arResult["PROPS"] = $objContent->getProps();

$tmp = $objContent->getProps();
$tmp = sort_nested_arrays($tmp, $args = array('sort2' => 'asc', 'sort' => 'asc'));
foreach($tmp as $arItem) $arResult["PROPS"][$arItem["id"]] = $arItem;

global $USER;
$arGroups = $USER->GetUserGroupArray();

if (!$USER->IsAdmin() && !in_array(7, $arGroups))
{
    $APPLICATION->AuthForm(GetMessage("PERMISION_DENIED"));
    return ;
}
//prent($_POST);

parse_str($_POST['data'], $data);


$arList = explode("\r\n", $data["list_articles"]);
$arList = array_diff($arList, array(''));

$limit = 3;
$count = ceil(count($arList) / $limit);
$step = 1;

$arList = array_slice($arList, $_POST["offset"] * $limit, 3);


// Получаем от клиента номер итерации
$offset = $_POST['offset'];

// Проверяем, все ли строки обработаны
$offset = $offset + $step;
if ($offset >= $count) {
	$sucsess = 1;
} else {
	$sucsess = round($offset / $count, 2);
}

//AddMessage2Log($_POST);

//list_articles

if ($_POST["action"] == "run"){

	if($data["prop"] && isset($arResult["PROPS"][$data["prop"]])){




		$arArticles = array();
		foreach($arList as $key => &$article){
			$article = mb_strtoupper($article);
			$art = $article;

			foreach($arBrand as $brand){
				if(strripos($article, $brand["name"]) !== false){
					$arClearStr = array();

					$arClearStr[] = mb_strtoupper($brand["name"]);
					//альтернативные бренды
					if(strlen($brand["alt_name"]) > 0){
						$tmp = explode("|", $brand["alt_name"]);
						foreach($tmp as $key => &$name){
							$name = trim($name);
							if(strlen($name) > 0){
								$arClearStr[] = mb_strtoupper($name, "UTF-8");
							}
						}
						unset($name);
					}
					$article = str_replace($arClearStr, '', $article);

					$article = trim($article);
					//prent($article);
					if(strlen($brand["regular"]) > 2){
						preg_match($brand["regular"], $article, $matches);
						$matches = array_diff($matches, array(''));
						$matches = array_unique($matches);

						if($matches && count($matches) == 1 && strlen($matches[0]) > 0)
							$article = $matches[0];
					}

					$article = str_replace(array("  "), array(" "), $article);
					$article = trim($article);

					//если пятый символ -, то менять на J. если девятого символа нет - добавлять Y
					//if($brand_name == "Q&Q"){
					if($brand["id"] == 16){
						if($article[4] == "-") $article[4] = "J";
						if(strlen($article) == 8) $article[8] = "Y";
					}

					//для романсана 22 удаляем пробелы
					if($brand["id"] == 22){
						$article = str_replace(" ", "", $article);
					}

					//если поставщик 3. Денис (supplier_id = 39) и бренд Восток (brand_id = 38)

					if($brand["id"] == 38){
						$tmp = trim(array_pop(explode(" ", $article)));
						//$tmp = intval($tmp);
						//if($tmp > 0){
						if(strlen($tmp) > 0){
							$article = $tmp;

						}
					}

					//RA-KV0006Y10B
					//if($brand_name == "Orient"){
					if($brand["id"] == 2){
						if($article[2] == "-"){
							$article = substr($article, 0, 10);
						}else{
							$article = substr($article, 0, 9);
						}
					}elseif($brand["id"] == 14)
						$article = $article;
					elseif(strpos($article, " "))
						$article = strstr($article, " ", true);

					if($brand["id"] == 26 && $article[2] == "/"){
						$article = substr($article, 3);
					}

					//если Tissot то удаляем точку после буквы T
					if($brand["id"] == 20 && $article[0] == "T" && $article[1] == "."){
						$article = substr_replace($article, '', 1, 1);
					}
					break;
				}
			}

			$alt_art = $objUtils->getArtnumber($article);

			if($alt_art) $arArticles[] = $alt_art; else $arArticles[] = $article;
		}
		unset($article);

		$prop = $arResult["PROPS"][$data["prop"]];
		$PROPERTY_CODE = $data["prop"];

		foreach($arArticles as $key => $article){
			$ID = CPanelProduct::findArticle($article);
			if(!$ID){

			}
			if($ID){

				if($data["MULTIPLE"] == "Y" && $data["INSERT_TO"] == "Y"){
					$arFilterEl = Array("IBLOCK_ID" => CProSet::IB_CATALOG, "ID" => $ID);
					$resEl = CIBlockElement::GetList(Array(), $arFilterEl, false, false, array("ID", "PROPERTY_{$data["prop"]}"));

					$oldList = array();
					while($obEl = $resEl->getNext()){
						$oldList = array_keys($obEl["~PROPERTY_" . mb_strtoupper($data["prop"]) . "_VALUE"]);
					}
					foreach($data["PROPS"][$data["prop"]] as $k => $v)
						if(!in_array($v, $oldList))
							$oldList[] = $v;

					$arValues = $oldList;
				}else{
					$arValues = $data["PROPS"][$data["prop"]];
				}

				//prent($arValues);
				$PROP_CODE = $data["prop"];
				CIBlockElement::SetPropertyValueCode($ID, $PROP_CODE, $arValues);
				\Bitrix\Iblock\PropertyIndex\Manager::updateElementIndex(CProSet::IB_CATALOG, $ID);

				$log .= "<p style='color:green'>{$ID} - {$article} установлен</p>";


			}else{
				$log .= "<p style='color:red;'>{$article} - не найден ID товара</p>";
			}

		}

	}else{
		$log .= "<p style='color:red'>Выберите свойство</p>";
	}

}
// Можно передавать в скрипт разный action и в соответствии с ним выполнять разные действия.
//$action = $_POST['action'];
//if (empty($action)) {return;}

//$data = $_POST['data'];
//if (empty($data)) return;

//


// И возвращаем клиенту данные (номер итерации и сообщение об окончании работы скрипта)
$arReq['offset'] = $offset;
$arReq['sucsess'] = $sucsess;
$arReq['log'] = $log;
echo json_encode($arReq);
