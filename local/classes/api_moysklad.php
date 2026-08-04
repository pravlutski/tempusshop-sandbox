<?php
/**
 * api moysklad class
 */

/*
class MoyskladAPI
{
	private $access_token;
	private $api_url_auth = "https://api.moysklad.ru/api/remap/1.2/security/token";
	private $api_url = "https://api.moysklad.ru/api/remap/1.2";

    private $https = FALSE; //API работает через https

	private $client_id = "";
	private $client_secret = "";

	public $MSPosition = array();
	public $LAST_ERROR;

    public function  __construct($site){

		if($site == "s1"){

			$ms_login = "admin@tempusint";
			$ms_pass = "48539dd3a8";
			$ms_site = "s1";

		}elseif($site == "s2"){

			$ms_login = "admin@tempusby";
			$ms_pass = "107c779c77";
			$ms_site = "s2";

		}elseif($site == "s3"){

			$ms_login = "admin@tempuspl";
			$ms_pass = "07a21edaa6b9";
			$ms_site = "s3";

		}elseif($site == "s1_order"){

			$ms_login = "admin@tempusint";
			$ms_pass = "48539dd3a8";
			$ms_site = "s1_order";

		}else{
			die("no site");
		}

		$this->access_token = CProSet::getOption("MS_ACCESS_TOKEN_" . $ms_site);
//		$time_start = debug_microtime_float();
//prent($this->access_token,0,1);
		if(!$this->access_token){
			//инициализируем CURL
			$this->curl = curl_init();

			curl_setopt($this->curl, CURLOPT_URL, $this->api_url_auth);
			curl_setopt($this->curl, CURLOPT_HTTPHEADER, array('Accept: application/json;charset=utf-8'));
			curl_setopt($this->curl, CURLOPT_USERPWD, "{$ms_login}:{$ms_pass}");
			curl_setopt($this->curl, CURLOPT_POST, 1);
			curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($this->curl, CURLOPT_POSTFIELDS, array('grant_type' => 'client_credentials'));

			$result = curl_exec($this->curl);
			$info = curl_getinfo($this->curl);
	//$time_end = debug_microtime_float();
	//prent($result,0,1);prent($info,0,1);prent(curl_error($this->curl),0,1);
	//prent($time_end - $time_start,0,1);prent($result,0,1);
			$obj_access = json_decode($result);

			$this->access_token = $obj_access->{'access_token'};

			if($this->access_token){
				CProSet::setOption("MS_ACCESS_TOKEN_" . $ms_site, $this->access_token);
			}else{
				CProSet::setOption("MS_ACCESS_TOKEN_" . $ms_site, "");
			}
			//при успешной авторизации получаем код доступа сессии
		}

		//$this->access_token = $access;

	}


	public function getListOrder($page = 0, $search = ""){

		if($page == 0) $offset = 0; else $offset = $page * 1000;

		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}

		$limit = 1000;
		//$limit = 1;
		$process = curl_init($this->api_url . "/entity/customerorder?offset={$offset}&limit={$limit}&search={$search}&order=moment,desc");
		curl_setopt(
			$process,
			CURLOPT_HTTPHEADER,
			array(
				"Accept: application/json;charset=utf-8",
				"Authorization: Bearer $access"
			)
		);

		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);

		$result = curl_exec($process);

		$result = json_decode($result, true);

		return $result;
	}

	public function getPaymentTemplate($_data = array()){

		$access = $this->access_token;

		if(!$access || !$_data) return;

		$_data = array(
			"operations" => array(array("meta" => $_data))
		);
		$_data = json_encode($_data);

		$process = curl_init($this->api_url . "/entity/paymentin/new");
		curl_setopt(
			$process,
			CURLOPT_HTTPHEADER,
			array(
				"Content-Type: application/json",
				"Authorization: Bearer $access"
			)
		);
		curl_setopt($process, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($process, CURLOPT_POST, true);
		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($process, CURLOPT_POSTFIELDS, $_data);

		$result = curl_exec($process);

		$result = json_decode($result, true);

		return $result;
	}

	public function getPaymentOutTemplate($_data = array()){

		$access = $this->access_token;

		if(!$access || !$_data) return;

		$_data = array(
			"organization" => array(array("meta" => $_data))
		);
		$_data = json_encode($_data);
		prent($_data);
		$process = curl_init($this->api_url . "/entity/paymentout/new");
		curl_setopt(
			$process,
			CURLOPT_HTTPHEADER,
			array(
				"Content-Type: application/json",
				"Authorization: Bearer $access"
			)
		);
		curl_setopt($process, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($process, CURLOPT_POST, true);
		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($process, CURLOPT_POSTFIELDS, $_data);

		$result = curl_exec($process);

		$result = json_decode($result, true);

		return $result;
	}

	public function getCashInTemplate($_data = array()){

		$access = $this->access_token;

		if(!$access || !$_data) return;

		$_data = array(
			"operations" => array(array("meta" => $_data))
		);
		$_data = json_encode($_data);

		$process = curl_init($this->api_url . "/entity/cashin/new");
		curl_setopt(
			$process,
			CURLOPT_HTTPHEADER,
			array(
				"Content-Type: application/json",
				"Authorization: Bearer $access"
			)
		);
		curl_setopt($process, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($process, CURLOPT_POST, true);
		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($process, CURLOPT_POSTFIELDS, $_data);

		$result = curl_exec($process);

		$result = json_decode($result, true);

		return $result;
	}

	public function getCashOutTemplate($_data = array()){

		$access = $this->access_token;

		if(!$access || !$_data) return;

		$_data = array(
			"operations" => array(array("meta" => $_data))
		);
		$_data = json_encode($_data);

		$process = curl_init($this->api_url . "/entity/cashout/new");
		curl_setopt(
			$process,
			CURLOPT_HTTPHEADER,
			array(
				"Content-Type: application/json",
				"Authorization: Bearer $access"
			)
		);
		curl_setopt($process, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($process, CURLOPT_POST, true);
		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($process, CURLOPT_POSTFIELDS, $_data);

		$result = curl_exec($process);

		$result = json_decode($result, true);

		return $result;
	}


	public function setCash($_data = array()){

		$access = $this->access_token;

		if(!$access || !$_data) return;

		$_data = json_encode($_data);
		//prent($_data);
		//prent($_data);die;
		$process = curl_init($this->api_url . "/entity/cashin");
		curl_setopt(
			$process,
			CURLOPT_HTTPHEADER,
			array(
				"Content-Type: application/json",
				"Authorization: Bearer $access"
			)
		);
		curl_setopt($process, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($process, CURLOPT_POST, true);
		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($process, CURLOPT_POSTFIELDS, $_data);

		$result = curl_exec($process);

		$result = json_decode($result, true);
		//prent($result);die;
		return $result;
	}

	public function setCashOut($_data = array()){

		$access = $this->access_token;

		if(!$access || !$_data) return;

		$_data = json_encode($_data);
		//prent($_data);
		//prent($_data);die;
		$process = curl_init($this->api_url . "/entity/cashout");
		curl_setopt(
			$process,
			CURLOPT_HTTPHEADER,
			array(
				"Content-Type: application/json",
				"Authorization: Bearer $access"
			)
		);
		curl_setopt($process, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($process, CURLOPT_POST, true);
		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($process, CURLOPT_POSTFIELDS, $_data);

		$result = curl_exec($process);

		$result = json_decode($result, true);
		//prent($result);die;
		return $result;
	}

	public function setPayment($_data = array()){

		$access = $this->access_token;

		if(!$access || !$_data) return;

		$_data = json_encode($_data);

		$process = curl_init($this->api_url . "/entity/paymentin");
		curl_setopt(
			$process,
			CURLOPT_HTTPHEADER,
			array(
				"Content-Type: application/json",
				"Authorization: Bearer $access"
			)
		);
		curl_setopt($process, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($process, CURLOPT_POST, true);
		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($process, CURLOPT_POSTFIELDS, $_data);

		$result = curl_exec($process);

		$result = json_decode($result, true);

		return $result;
	}

	public function setPaymentOut($_data = array()){

		$access = $this->access_token;

		if(!$access || !$_data) return;

		$_data = json_encode($_data);

		$process = curl_init($this->api_url . "/entity/paymentout");
		curl_setopt(
			$process,
			CURLOPT_HTTPHEADER,
			array(
				"Content-Type: application/json",
				"Authorization: Bearer $access"
			)
		);
		curl_setopt($process, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($process, CURLOPT_POST, true);
		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($process, CURLOPT_POSTFIELDS, $_data);

		$result = curl_exec($process);

		$result = json_decode($result, true);

		return $result;
	}

	public function getListProject(){

		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}

		$process = curl_init($this->api_url . "/entity/project");
		curl_setopt(
			$process,
			CURLOPT_HTTPHEADER,
			array(
				"Accept: application/json;charset=utf-8",
				"Authorization: Bearer $access"
			)
		);

		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);

		$result = curl_exec($process);

		$result = json_decode($result, true);

		return $result;
	}

	public function getProject($id = ""){
		if(!$id) return;
		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}

		$process = curl_init($this->api_url . "/entity/project/{$id}");
		curl_setopt(
			$process,
			CURLOPT_HTTPHEADER,
			array(
				"Accept: application/json;charset=utf-8",
				"Authorization: Bearer $access"
			)
		);

		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);

		$result = curl_exec($process);

		$result = json_decode($result, true);

		return $result;
	}
	public function getListSupply($page = 0, $firstPage = false){

		if($page == 0) $offset = 0; else $offset = $page * 1000;

		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}

		$limit = 1000;
		//$limit = 1;updated
		//&order=moment,desc
		$process = curl_init($this->api_url . "/entity/supply?offset={$offset}&limit={$limit}&order=updated,desc");
		curl_setopt(
			$process,
			CURLOPT_HTTPHEADER,
			array(
				"Accept: application/json;charset=utf-8",
				"Authorization: Bearer $access"
			)
		);

		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);

		$result = curl_exec($process);

		$result = json_decode($result, true);

		if(isset($result["rows"])){
			foreach($result["rows"] as $key => $arItem){
				$this->MSPosition[$arItem["id"]] = $arItem;
			}
		}else{
			$info = curl_getinfo($process);
			CLog::add2log(array("event" => "MC", "text" => "Ошибка при обмене /entity/supply", "detail" => serialize(array("res" => $res, "info" => $info))));
		}

		if($firstPage === false && $result["meta"]["size"] > $result["meta"]["limit"] + $offset){
			$this->getListSupply($page + 1);
		}

	}

	public function getListSalesReturn($page = 0, $firstPage = false){

		if($page == 0) $offset = 0; else $offset = $page * 1000;

		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}

		$limit = 1000;
		//$limit = 1;updated
		//&order=moment,desc
		$process = curl_init($this->api_url . "/entity/salesreturn?offset={$offset}&limit={$limit}&order=updated,desc");
		curl_setopt(
			$process,
			CURLOPT_HTTPHEADER,
			array(
				"Accept: application/json;charset=utf-8",
				"Authorization: Bearer $access"
			)
		);

		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);

		$result = curl_exec($process);

		$result = json_decode($result, true);
		if(isset($result["rows"])){
			foreach($result["rows"] as $key => $arItem){
				$this->MSPosition[$arItem["id"]] = $arItem;
			}
		}else{
			$info = curl_getinfo($process);
			CLog::add2log(array("event" => "MC", "text" => "Ошибка при обмене /entity/salesreturn", "detail" => serialize(array("res" => $res, "info" => $info))));
		}

		if($firstPage === false && $result["meta"]["size"] > $result["meta"]["limit"] + $offset){
			$this->getListSalesReturn($page + 1);
		}

	}

	public function getListProfit($page = 0, $firstPage = false, $arFilter = array()){

		if($page == 0) $offset = 0; else $offset = $page * 1000;

		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}

		$limit = 1000;
		//$limit = 1;updated
		//&order=moment,desc
		$momentTo = date("Y-m-d");
		$momentFrom = date("Y-m-d", strtotime("-6 month"));

		if($arFilter["momentTo"]) $momentTo = $arFilter["momentTo"]; else $momentTo = date("Y-m-d");
		if($arFilter["momentFrom"]) $momentFrom = $arFilter["momentFrom"]; else $momentFrom = date("Y-m-d", strtotime("-6 month"));

		//prent($momentTo);prent($momentFrom);
		$process = curl_init($this->api_url . "/report/profit/byproduct?offset={$offset}&limit={$limit}&momentFrom={$momentFrom}&momentTo={$momentTo}&filter=counterparty=https://api.moysklad.ru/api/remap/1.2/entity/counterparty/c5ae8285-242d-11ec-0a80-0d7b0021192c");

		curl_setopt(
			$process,
			CURLOPT_HTTPHEADER,
			array(
				"Accept: application/json;charset=utf-8",
				"Authorization: Bearer $access"
			)
		);

		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);

		$res = curl_exec($process);

		$result = json_decode($res, true);

//$file_log = "/home/bitrix/getListProfit_" . date("Y-m-d") . ".txt";
//file_put_contents($file_log, serialize($result) . "\r\n", FILE_APPEND | LOCK_EX);
		if(isset($result["rows"])){
			foreach($result["rows"] as $key => $arItem){
				if($arItem["sellQuantity"] > 1 && $arItem["assortment"]["code"]){
					//$this->MSPosition[] = $arItem;
					$this->MSPosition[$arItem["assortment"]["code"]] = array(
						"XML_ID" => $arItem["assortment"]["code"],
						"ARTICLE" => $arItem["assortment"]["article"],
					);
				}

			}
		}else{
			$info = curl_getinfo($process);
			CLog::add2log(array("event" => "MC", "text" => "Ошибка при обмене /report/profit/byproduct", "detail" => serialize(array("res" => $res, "info" => $info))));
		}


		if($firstPage === false && $result["meta"]["size"] > $result["meta"]["limit"] + $offset){
			$this->getListProfit($page + 1);
		}

	}

	public function customRequest($url = ""){

		if(!$url) return false;

		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}

		$process = curl_init($url);
		curl_setopt(
			$process,
			CURLOPT_HTTPHEADER,
			array(
				"Accept: application/json;charset=utf-8",
				"Authorization: Bearer $access"
			)
		);

		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);

		$result = curl_exec($process);

		$result = json_decode($result, true);

		return $result;
	}

	public function getDemandTemplate($_data = array()){

		$access = $this->access_token;

		if(!$access || !$_data) return;

		$_data = array(
			"customerOrder" => array("meta" => $_data)
		);
		$_data = json_encode($_data);

		$process = curl_init($this->api_url . "/entity/demand/new");
		curl_setopt(
			$process,
			CURLOPT_HTTPHEADER,
			array(
				"Content-Type: application/json",
				"Authorization: Bearer $access"
			)
		);
		curl_setopt($process, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($process, CURLOPT_POST, true);
		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($process, CURLOPT_POSTFIELDS, $_data);

		$result = curl_exec($process);

		$result = json_decode($result, true);

		return $result;
	}

	//Массовое создание и обновление Отгрузок

	public function setDemand($_data = array()){

		$access = $this->access_token;

		if(!$access || !$_data) return;

		$_data = json_encode($_data);

		$process = curl_init($this->api_url . "/entity/demand");
		curl_setopt(
			$process,
			CURLOPT_HTTPHEADER,
			array(
				"Content-Type: application/json",
				"Authorization: Bearer $access"
			)
		);
		curl_setopt($process, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($process, CURLOPT_POST, true);
		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($process, CURLOPT_POSTFIELDS, $_data);

		$result = curl_exec($process);

		$result = json_decode($result, true);
		//prent($result);die;
		return $result;
	}

	public function getStock($page = 0, $arFilter = array()){

		if($page == 0) $offset = 0; else $offset = $page * 1000;

		$access = $this->access_token;

		$asd = (count($arFilter) > 0 ? implode("&", $arFilter) : "");
		prent($asd,0,1);die;
		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}

		$limit = 1000;

		$process = curl_init($this->api_url . "/report/stock/all?stockMode=positiveOnly&limit={$limit}&offset={$offset}&groupBy=product" . (count($arFilter) > 0 ? implode("&", $arFilter) : ""));
		curl_setopt(
			$process,
			CURLOPT_HTTPHEADER,
			array(
				"Accept: application/json;charset=utf-8",
				"Authorization: Bearer $access"
			)
		);

		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);

		$result = curl_exec($process);

		$result = json_decode($result, true);
		prent($result);die;
		foreach($result["rows"] as $key => $arItem){
			$this->MSPosition[$arItem["externalCode"]] = array(
				"XML_ID" => $arItem["externalCode"],
				"PRICE" => $arItem["price"],
				"COUNT" => $arItem["quantity"],
			);
		}

		if($result["meta"]["size"] > $result["meta"]["limit"] + $offset){
			$this->getStock($page + 1);
		}

	}

	function add2log($log){
		$file_log = "/userscripts/logs/onliner/cart_" . date("Y-m-d") . ".txt";
		file_put_contents($file_log, $log, FILE_APPEND | LOCK_EX);
	}

}*/
