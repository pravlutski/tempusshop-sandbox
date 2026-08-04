<?php
class CPanelDiscount{
	function addCard( $arr, $sum ){
		global $DB;
		global $USER;
		$in = array(
			"code" => "'".addslashes($arr["code"])."'", 
			"fullname" => "'".addslashes($arr["fullname"])."'", 
			"birthday" => "'".addslashes($arr["birthday"])."'", 
			"phone" => "'".addslashes($arr["phone"])."'", 
			"email" => "'".addslashes($arr["email"])."'", 
			"start_level" => "'".addslashes($arr["start_level"])."'", 
			"by_whom" => "'".$USER->getID()."'", 
		);
		$ID = $DB->Insert("ci_discount_cards", $in, $err_mess.__LINE__);
		$this->addSale( $ID, $sum );
	}
	function getCards(){
		global $DB;
		$arr = array();
		$strSql = "SELECT * FROM ci_discount_cards ORDER BY id DESC";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arr[] = $row;
		}
		return $arr;
	}
	function getCard( $id ){
		global $DB;
		$id = (int)$id;
		$strSql = "SELECT * FROM ci_discount_cards WHERE id = '{$id}' LIMIT 1";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			return $row;
		}
		return false;
	}
	function addSale( $card_id, $sum ){
		global $DB;
		global $USER;
		$discount = addslashes($this->getDiscount($card_id));
		if( $discount !== false ){
			$card_id = addslashes($card_id);
			if( $discount > 0 )
				$sum = $sum - ($sum / 100 * $discount);

			$sum = (int) $sum;
			$in = array(
				"amount" => "'".addslashes($sum)."'", 
				"discount" => "'".addslashes($discount)."'", 
				"datetime" => "NOW()", 
				"user_id" => "'".$USER->getID()."'", 
				"card_id" => "'".addslashes($card_id)."'", 
			);
			$ID = $DB->Insert("ci_discount_sales", $in, $err_mess.__LINE__);
			if($ID > 0) return true;
		}
		return false;
	}
	function getID( $code ){
		global $DB;
		$code = addslashes($code);
		if(strlen($code) <= 0) return;
		$strSql = "SELECT id FROM ci_discount_cards WHERE code = '{$code}' LIMIT 1";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			return $row["id"];
		}
		return false;
	}
	function getIDbyPhone( $phone ){
		global $DB;
		$phone = addslashes($phone);
		$strSql = "SELECT id FROM ci_discount_cards WHERE phone = '{$phone}' LIMIT 1";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			return $row["id"];
		}
		return false;
	}
	function getDiscount( $id ){
		global $DB;
		$strSql = "SELECT start_level FROM ci_discount_cards WHERE id = '{$id}' LIMIT 1";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			$discount = 0;
			$level = $row["start_level"];
			switch( $level ){
				case 1: $discount = 5; break;
				case 2: $discount = 10; break;
			}

			$sum = $this->getAmount( $id );

			if( $sum >= 150 and $sum< 1000 )
				$discount_bysale = 5;
			elseif( $sum >= 1000 and $sum < 1500 )
				$discount_bysale = 7;
			elseif( $sum >= 1500 and $sum < 2000 )
				$discount_bysale = 8;
			elseif( $sum >= 2000 )
				$discount_bysale = 10;
			if( isset($discount_bysale) ){
				if( $discount_bysale > $discount )
					return $discount_bysale;
				else return $discount;
			} else return $discount;
		}
        return false;
	}
	function getAmount( $id ){
		global $DB;
		$sum = 0;
		$strSql = "SELECT amount FROM ci_discount_sales WHERE card_id = '{$id}' and active = 'Y'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$sum += $row["amount"];
		}
		return $sum;
	}
	function getSales( $card_id ){
		global $DB;
		$arr = array();
		$card_id = (int)$card_id;
		$strSql = "SELECT * FROM ci_discount_sales WHERE card_id = '{$card_id}' ORDER BY id DESC";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arr[] = $row;
		}
		return $arr;
	}
	function cancel_sale( $id ){
		global $DB;
		$id = (int)$id;
		if($id <= 0) return;
		if($this->isDiscountSales($id)){
			$DB->Update("ci_discount_sales", array("active" => "'N'"), "WHERE id='".$id."'", $err_mess.__LINE__);
			return true;
		}
		return false;
	}
	function restore_sale( $id, $user_id ){
		global $DB;
		$id = (int)$id;
		if($id <= 0) return;
		if($this->isDiscountSales($id)){
			$DB->Update("ci_discount_sales", array("active" => "'Y'"), "WHERE id='".$id."'", $err_mess.__LINE__);
			return true;
		}
		return false;
	}
	function isDiscountSales( $id ){
		global $DB;
		$id = (int)$id;
		if($id <= 0) return;
		$strSql = "SELECT id FROM ci_discount_sales WHERE id = '{$id}'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			return true;
		}
		return false;
	}
	function getIDbySale( $sale_id ){
		global $DB;
		$sale_id = (int)$sale_id;
		$strSql = "SELECT card_id FROM ci_discount_sales WHERE id = '{$sale_id}' LIMIT 1";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			return $row["card_id"];
		}
		return false;
	}
	function toLog( $text, $user_id ){
		global $DB;
		$in = array(
			'event'  => "'".$text."'",
			'user_id'=> "'".$user_id."'"
		);
		$DB->Insert("ci_discount_log", $in, $err_mess.__LINE__);
	}
	function getLog(){
		global $DB;
		$arr = array();
		$strSql = "SELECT * FROM ci_discount_log ORDER BY id DESC";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arr[] = $row;
		}
		return $arr;
	}
	function edit( $id, $fields ){
		global $DB;
		$id = (int)$id;
		if($this->getCard($id)){
			$err = $this->validateCard($id, $fields);
			if(count($err) == 0){
				$in = array(
					'fullname'  => "'".addslashes($fields["fullname"])."'",
					'birthday'=> "'".addslashes($fields["birthday"])."'",
					'phone'=> "'".addslashes($fields["phone"])."'",
					'email'=> "'".addslashes($fields["email"])."'",
				);
				if(isset($fields['start_level']) && in_array($fields['start_level'], array("0", "1", "2")))
					$in["start_level"] = $fields['start_level'];
				$DB->Update("ci_discount_cards", $in, "WHERE id='".$id."'", $err_mess.__LINE__);
				return true;
			}else{
				return $err;
			}
		}
		return false;
	}
	function validateCard($id, $fields){
		global $DB;
		$arError = array();
		$strSql = "SELECT id FROM ci_discount_cards WHERE id <> '{$id}' AND phone = '{$fields["phone"]}'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			$arError[] = "Телефон {$fields["phone"]} занят другим пользователем";
		}
/*		
		$strSql = "SELECT id FROM ci_discount_cards WHERE id <> '{$id}' AND email = '{$fields["email"]}'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			$arError[] = "Email {$fields["email"]} занят другим пользователем";
		}*/
		return $arError;
	}
	function find( $str ){
		global $DB;
		$str = addslashes( $str );
		$arr = array();
		$strSql = "SELECT * FROM ci_discount_cards WHERE ( locate('{$str}', code) > 0 ) or ( locate('{$str}', phone) > 0 ) or ( locate('{$str}', fullname) > 0 )";
		//$strSql = "SELECT * FROM ci_discount_cards WHERE ( locate('{$str}', fullname) > 0 )";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arr[] = $row;
		}
		return $arr;
	}
	function remove( $id ){
		global $DB;
		$id = (int)$id;
		if( $this->getCard( $id ) ){
			$DB->Query("DELETE FROM ci_discount_cards WHERE id = '".$id."'", false, $err_mess.__LINE__);
			$DB->Query("DELETE FROM ci_discount_sales WHERE card_id = '".$id."'", false, $err_mess.__LINE__);
			return true;
		}
		return false;
	}
/*	function isDiscountCard( $id ){
		global $DB;
		$id = (int)$id;
		if($id <= 0) return;
		$strSql = "SELECT id FROM ci_discount_cards WHERE id = '{$id}'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			return true;
		}
		return false;
	}*/
	
}