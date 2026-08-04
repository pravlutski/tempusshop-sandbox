<?require_once($_SERVER['DOCUMENT_ROOT']. "/bitrix/modules/main/include/prolog_before.php");
use Bitrix\Main\Loader;
if(!Loader::includeModule('iblock') || !Loader::includeModule('panel.manager'))return;
global $DB;

$action = trim(htmlspecialchars($_REQUEST["action"]));
switch($action){
	case "get-list": 
		$res = ["status" => "error"];
		if(isset($_REQUEST["product_id"]) && is_array($_REQUEST["product_id"]) && count($_REQUEST["product_id"]) > 0){
			$arFilter = Array(
				"IBLOCK_ID"	=> 16,
				"ID" => $_REQUEST["product_id"],
			);
			$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID", "PROPERTY_AEN"));

			$ar = [];
			
			while($ob = $rs->GetNextElement()){
				$arFields = $ob->GetFields();
				
				$ar[] = [
					"ID" => $arFields["ID"],
					"BARCODE" => $arFields["PROPERTY_AEN_VALUE"],
				];
			}
			
			$res = array(
				'status' => "ok",
				'data' => $ar,
			);
		}

		$GLOBALS['APPLICATION']->RestartBuffer();
		echo json_encode($res, JSON_UNESCAPED_UNICODE);

		header('Content-Type: application/json;charset=UTF-8');
		die();
		break;
	case "save": 
		$form = urldecode($_REQUEST["form"]);
		$objProduct = new CPanelProduct;
		$objUtils = new CPanelUtils;
		
		$strSql = "SELECT el.ID as ID, pr.PROPERTY_123 as ARTICLE 
			FROM 
				b_iblock_element el 
			LEFT JOIN 
				b_iblock_element_prop_s16 pr 
			ON el.ID=pr.IBLOCK_ELEMENT_ID 
			WHERE 
				el.IBLOCK_ID = '16' AND pr.PROPERTY_123 <> ''";
			
		$arResult = array();	
		$arResult["SORT_ENABLE"] = true;
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);

		while ($row = $results->Fetch()){
			if(strlen($row["ARTICLE"]) > 0){
				$arResult["ARTICLE_BX"][$row["ID"]] = $row["ARTICLE"];
			}
		}
		$arLog = "";
		foreach($_REQUEST["barcode"] as $product_id => $bc){

			$article = $arResult["ARTICLE_BX"][$product_id];
			$article = htmlentities($article);
			$article = str_replace("&nbsp;", "", $article);

			$barcode = trim($bc);
			$barcode = htmlentities($barcode);
			$barcode = str_replace("&nbsp;", "", $barcode);
			
			
			if( empty($article) or empty($barcode) ) {
				//$arLog .= "<p style='color:red;'>{$article} - {$barcode} . Заполнены не все поля.</p>";
			}elseif( !( $objProduct->findArticle( $article ) ) ){
				$arLog .= "<span class='label label-danger' style='display: block;'>{$article} - {$barcode} . Такой артикул не существует на сайте.</span>";
			}elseif( $objUtils->checkArtBarcode($article, $barcode) ){
				$arLog .= "<span class='label label-danger' style='display: block;'>{$article} - {$barcode} . Такой ШК установлен для другого товара, обратитесь к руководителю</span>";
			}elseif( $objUtils->addAltBarcode($article, $barcode) ){
				$arLog .= "<p style='color:green;'>{$article} - {$barcode} добавлен</p>";
			}else{
				$arLog .= "<span class='label label-danger' style='display: block;'>{$article} - {$barcode} ошибка</span>";
			}
		}
		
		$res = array(
			'status' => "ok",
			'data' => $arLog,
		);
		
		$GLOBALS['APPLICATION']->RestartBuffer();
		echo json_encode($res, JSON_UNESCAPED_UNICODE);

		header('Content-Type: application/json;charset=UTF-8');
		die();
		break;
	default:
		break;
}
?>