<?php
class CPanelUtils{
	public $LAST_ERROR;
	function __construct(){

	}
	function parse_sales( $post, $rows ){
		$post = explode("\n", $post);
		if( is_array($rows) and count($rows) > 0 ){
			if( is_array($post) and count($post) > 0 ){
				$arr = array();
				foreach( $rows as $key => $row_number ) $rows[$key] = (int)$row_number;
				foreach( $post as $sale ){
					$sale = explode("\t", $sale);
					foreach( $rows as $row_number ){
						if( isset($sale[$row_number]) )
							$new_row[] = $sale[$row_number];
						else return false;
					}
					$arr[] = $new_row;
					unset($new_row);
				}
				return $arr;
			} return false;
		} else return false;
	}

	/* artnumbers methods */
	function getAltAnList(){
		global $DB;
		$arr = array();
		$strSql = "SELECT * FROM ci_catalog_artnumbers";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arr[$row["artnumber"]][] = $row["alternative"];
		}
		return $arr;
	}

	function getAltAnListFull(){
		global $DB;
		$arr = array();
		$strSql = "SELECT * FROM ci_catalog_artnumbers";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arr[$row["artnumber"]][] = [
				'alternative' => $row["alternative"],
				'user_id' => $row['user_id'],
				'mode' => $row['mode'],
				'timestamp' => $row['date'],
			];
		}
		return $arr;
	}

	function addAltAn($an, $alt, $mode = 'auto'){
		global $DB;
		global $USER;
		if($an == $alt) return false;
		if( !$this->checkAltRule($an, $alt) ){
			$in = array(
				"artnumber" => "'".addslashes($an)."'",
				"alternative" => "'".addslashes($alt)."'",
				"user_id" => "'".$USER->GetId()."'",
				"mode" => "'".$mode."'",
				"date" => "'".date('Y-m-d G:i:s')."'",
			);
			$DB->Insert("ci_catalog_artnumbers", $in, $err_mess.__LINE__);

			//обновляем свойство "Альтернативные артикулы" у товара
			$this->updateProp($an);
			return true;
		} else return false;
	}
	function rmAltAn( $an, $altAn ){
		global $DB;
		$DB->Query("DELETE FROM ci_catalog_artnumbers WHERE artnumber = '".$an."' AND alternative = '".$altAn."'", false, $err_mess.__LINE__);

		//обновляем свойство "Альтернативные артикулы" у товара
		$this->updateProp($an);
	}
	function checkAltRule( $an, $alt ){
		global $DB;
		if( empty( $an ) or empty( $alt ) )
			return false;
		//$strSql = "SELECT * FROM ci_catalog_artnumbers WHERE artnumber = '".$an."' AND alternative = '".$alt."'";
		$strSql = "SELECT * FROM ci_catalog_artnumbers WHERE (artnumber = '".$alt."' OR alternative = '".$alt."')";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			return true;
		}
		return false;
	}
	function getArtnumber( $alt ){
		global $DB;
		if( empty( $alt ) )
			return false;
		$strSql = "SELECT * FROM ci_catalog_artnumbers WHERE alternative = '".$alt."' COLLATE utf8mb3_unicode_ci";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			return $row["artnumber"];
		}
		return false;
	}

	function getArtnumberAll(){
		global $DB;

		$ar = array();
		$strSql = "SELECT * FROM ci_catalog_artnumbers";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$ar[$row["alternative"]] = $row["artnumber"];
		}
		return $ar;
	}

	function getAltArtnumber( $artnumber ){
		global $DB;
		if( empty( $artnumber ) )
			return false;
		$strSql = "SELECT * FROM ci_catalog_artnumbers WHERE artnumber = '".$artnumber."'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		$ar = array();
		while ($row = $results->Fetch()){
			$ar[] = $row["alternative"];
		}
		return $ar;
	}

	function updateProp( $an ){
		global $DB;
		$objProduct = new CPanelProduct;

		$product_id = $objProduct->findArticle( $an );
		$arAlt = $this->getAltArtnumber( $an );

		if($product_id > 0){
			$str = implode(", ", $arAlt);
			//prent($product_id);
			CIBlockElement::SetPropertyValueCode($product_id, "vendorcodes", $str);
			return true;
		}

		return false;

	}

	/* artBarcodes methods */
	function BCgetAltList(){
		global $DB;
		$arr = array();
		$strSql = "SELECT * FROM ci_catalog_barcode";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arr[$row["ARTICLE"]][] = $row;
		}
		return $arr;
	}

	// проверка существования альтернативы для артикула
	function checkArtBarcode($article, $barcode){
		global $DB;
		if(!$article || !$barcode)
			return false;
		//$strSql = "SELECT * FROM ci_catalog_barcode WHERE ARTICLE = '".$article."' AND BARCODE = '".$barcode."'";
		$strSql = "SELECT * FROM ci_catalog_barcode WHERE BARCODE = '".$barcode."'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			if($row["ARTICLE"] == $article){
				$this->LAST_ERROR = "Этот ШК '{$row["ARTICLE"]}' уже добавлен к товару";
			}else{
				$this->LAST_ERROR = "ШК установлен для '{$row["ARTICLE"]}'";
			}
			return true;
		}
		return false;
	}

	//добавление альтернативного ШК
	function addAltBarcode($article, $barcode, $product_id = false){
		global $DB;
		if($article == $barcode) return false;
		if(!$this->checkArtBarcode($article, $barcode)){
			$in = array(
				"ARTICLE" => "'".addslashes($article)."'",
				"BARCODE" => "'".addslashes($barcode)."'",
			);

			if(!$product_id){
				$objProduct = new CPanelProduct;
				$product_id = $objProduct->findArticle($article);
			}
			if(!$product_id){
				$this->LAST_ERROR = "Не найден ID товара";
				return false;
			}
			$in["PRODUCT_ID"] = "'".addslashes($product_id)."'";

			$DB->Insert("ci_catalog_barcode", $in, $err_mess.__LINE__);

			$this->updatePropBarcode($product_id);
			return true;
		}else return false;
	}

	// удаляем альтернативу баркода
	function rmAltBarcode($article, $barcode){
		global $DB;
		if(!$article || !$barcode) return false;

		$strSql = "SELECT PRODUCT_ID FROM ci_catalog_barcode WHERE ARTICLE = '".$article."' AND BARCODE = '".$barcode."'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			$DB->Query("DELETE FROM ci_catalog_barcode WHERE ARTICLE = '".$article."' AND BARCODE = '".$barcode."'", false, $err_mess.__LINE__);
			$this->updatePropBarcode($row["PRODUCT_ID"]);
		}

	}
	function rmAltBarcodeID($ID){
		global $DB;
		if(!$ID) return false;

		$strSql = "SELECT PRODUCT_ID FROM ci_catalog_barcode WHERE ID = '".$ID."'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			$DB->Query("DELETE FROM ci_catalog_barcode WHERE ID = '".$ID."'", false, $err_mess.__LINE__);
			$this->updatePropBarcode($row["PRODUCT_ID"]);
		}


	}

	function updatePropBarcode($productID = 0){
		global $DB;
		$arBarcode = [];
		$strSql = "SELECT BARCODE FROM ci_catalog_barcode WHERE PRODUCT_ID = '".$productID."'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arBarcode[] = $row["BARCODE"];
		}

		$str = implode(", ", $arBarcode);
		CIBlockElement::SetPropertyValueCode($productID, "barcodes", $str);
		return true;

	}
}
