<?php
/**
 * logger
 */
class CLog{

	public function  __construct(){
	}
    function add2log($in = array()) {
		global $USER;
		$in["text"] = trim($in["text"]);
//AddMessage2Log($in);
		//if(!in_array($in["event"], $this->getFields()) || strlen($in["text"]) <= 0) return false;
		// || strlen($in["text"]) <= 0
		if(!self::getFields()[$in["event"]]) return false;
		$in["user_id"] = $USER->getID();
		//if(!$in["site_id"]) $in["site_id"] = SITE_ID;
		self::add($in);
    }
	public static function getFields(){
		$arAccessEvent = array(
			"E" => "Обновление складов",
			"Y" => "Выгрузка яндекса",
			"O" => "Обновление онлайнера",
			"U" => "Обновление каталога",
			"C" => "Загрузка каталога онлайнера",
			"UI" => "Обновление цены у товара",
			"P" => "Парсер отзывов с яндекса",
			"PP" => "Парсер цен с яндекса",
			"PC" => "Парсер цен ceneo",
			"OR" => "Изменения в заказах",
			"DD" => "Обновление значений в таблице сроков поставки",
			"R" => "Разное",
			"S" => "Обновление СУПЕРЦЕНА",
			"YP" => "YPartner",
			"ER" => "Ошибки",
			"MC" => "Обмен с MoySklad",
			"WB" => "WB",
		);
		return $arAccessEvent;
	}
	private function add($arr){
		global $DB;
		if(is_array($arr["detail"])) $arr["detail"] = serialize($arr["detail"]);
		$in = array(
			"user_id" => "'".addslashes($arr["user_id"])."'",
			"event" => "'".addslashes($arr["event"])."'",
			"site_id" => "'".addslashes($arr["site_id"])."'",
			"text" => "'".addslashes($arr["text"])."'",
			"detail" => "'".addslashes($arr["detail"])."'"
		);
		
		$DB->Insert("ci_log", $in, $err_mess.__LINE__);
    }

	
}