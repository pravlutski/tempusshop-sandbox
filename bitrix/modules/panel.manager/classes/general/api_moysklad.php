<?php
/*
Api moysklad class
 */

class MoyskladAPI
{
	private $access_token;
	private $api_url_auth = "https://api.moysklad.ru/api/remap/1.2/security/token";
	private $api_url = "https://api.moysklad.ru/api/remap/1.2";

	private $ms_login = '';
	private $ms_pass = '';
	private $ms_site = '';
	private $tryToRecreate = true;
	public $isResponseValid = false;


	private $https = FALSE; //API работает через https

	private $client_id = "";
	private $client_secret = "";

	public $MSPosition = array();
	public $LAST_ERROR;

    public function  __construct($site){
		$this->site_id = $site;
		if($site == "s1"){

			//$ms_login = "admin@tempusint";
			$ms_login = "bitrix@tempusint";
			//$ms_pass = "48539dd3a8";
			$ms_pass = "akkxbTO88yQR";
			$ms_site = "s1";
			$this->colPrice = "price";

		}elseif($site == "s2"){

			//$ms_login = "admin@tempusby";
			$ms_login = "bitrix@tempusby";
			//$ms_pass = "107c779c77";
			$ms_pass = "akkxbTO88yQR";
			$ms_site = "s2";
			$this->colPrice = "price";

		}elseif($site == "s3"){

			//$ms_login = "admin@tempuspl";
			$ms_login = "bitrix@tempuspl";
			//$ms_pass = "07a21edaa6b9";
			$ms_pass = "akkxbTO88yQR";
			$ms_site = "s3";
			$this->colPrice = "price";

		}elseif($site == "s1_order"){

			//$ms_login = "admin@tempusint";
			$ms_login = "bitrix@tempusint";
			//$ms_pass = "48539dd3a8";
			$ms_pass = "akkxbTO88yQR";
			$ms_site = "s1_order";
			$this->colPrice = "price";
		}elseif($site == "s1_opt"){

			$ms_login = "api@tempusws";
			$ms_pass = "qab3VnZXa";
			$ms_site = "s1_opt";


			$this->colPrice = "salePrice";
		}elseif($site == "msk"){

			$ms_login = "api@chronos";
			$ms_pass = "tempus2025TEMPUS!";
			$ms_site = "msk";

			$this->colPrice = "price";
			//$this->colPrice = "salePrice";
		}elseif($site == "msk2_asdasd"){

			$ms_login = "api@chronos";
			$ms_pass = "tempus2025TEMPUS!";
			$ms_site = "msk";

			$this->colPrice = "price";
			//$this->colPrice = "salePrice";
		}elseif($site == "s4"){

			$ms_login = "api@tempuskz";
			$ms_pass = "azsxdcfvgbhnjmk,l.";
			$ms_site = "s4";

			$this->colPrice = "price";
			//$this->colPrice = "salePrice";
		}elseif($site == "s1_nkz"){
			$ms_login = "bitrix@tempusint";
			$ms_pass = "akkxbTO88yQR";
			$ms_site = "s1";
			$this->colPrice = "price";
		}else{
			die("no site");
		}
		$this->ms_login = $ms_login;
		$this->ms_pass = $ms_pass;
		$this->ms_site = $ms_site;

		$this->Logger = new TsLogger("/MoyskladAPI/");
		$this->TsTriggers = new TsTriggers();

		$this->access_token = CProSet::getOption("MS_ACCESS_TOKEN_" . $ms_site);

		if( ! $this->access_token ){
			$this->updateToken();
		}
	}

	public function getAccessParams(){
		return [
			"username" => $this->ms_login,
			"password" => $this->ms_pass,
			"access_token" => $this->access_token,
		];
	}

        public function updateToken()
        {
                $this->Logger->log("LOG", "Получение токена {$this->site_id}");

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $this->api_url_auth);
                curl_setopt($ch, CURLOPT_HTTPHEADER,
                        [
                                'Accept: application/json;charset=utf-8',
                                'Accept-Encoding: gzip'
                        ]);
                curl_setopt($ch, CURLOPT_USERPWD, "{$this->ms_login}:{$this->ms_pass}");
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                curl_setopt($ch, CURLOPT_POSTFIELDS, array('grant_type' => 'client_credentials'));
                $result = curl_exec($ch);
                $info = curl_getinfo($ch);
                $obj_access = json_decode($result);

                $this->access_token = $obj_access->{'access_token'};
                if($this->access_token){
                        $this->Logger->log("LOG", "Получили токен {$this->site_id}");
                        CProSet::setOption("MS_ACCESS_TOKEN_" . $this->ms_site, $this->access_token);
                        $this->Logger->log("LOG", "Токен для {$this->site_id} получен.");
                        $this->TsTriggers->SetError(["Moysklad API: Токен для {$this->site_id} получен."]);
                        $this->TsTriggers->SetError($obj_access);
                        $this->TsTriggers->SendTriggerErrors();
                } else {
                        CProSet::setOption("MS_ACCESS_TOKEN_" . $this->ms_site, "");
                        $this->Logger->log("LOG", "Токен для {$this->site_id} получить не удалось." . print_r($result, true));
                        $this->TsTriggers->SetError(["Moysklad API: Токен для {$this->site_id} получить не удалось " . $result]);
                        $this->TsTriggers->SendTriggerErrors();
                }
        }

        public function isInvalidApiTokenError( $curl_result )
        {
                $result_arr = json_decode( $curl_result, true );

		if( ! $result_arr )
		{
			return false;
		}

	       	if( array_key_exists( "errors", $result_arr ) || ! empty( $result_arr['errors'] ) )
		{
			foreach( $result_arr['errors'] as $error )
			{
                                if( $error['code'] === 1056 )
				{
                                        return true;
                                }
                        }
                }
                return false;
        }



	public function getListOrder($page = 0, $search = "", $firstPage = false, $attempt = 0){

		if($page == 0) $offset = 0; else $offset = $page * 1000;

		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}

		$limit = 1000;

		$this->add2log(date("Y-m-d H:i:s") . " /entity/customerorder?offset={$offset}&limit={$limit}&search={$search}&order=moment,desc");

		$ch = curl_init($this->api_url . "/entity/customerorder?offset={$offset}&limit={$limit}&search={$search}&order=moment,desc");
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Accept: application/json;charset=utf-8",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

		//curl_setopt($ch, CURLOPT_INTERFACE, "93.125.48.252");
		$res = curl_exec($ch);

		$result = gzdecode($res);

		$result = json_decode($result, true);

		if(!$result || $result["errors"]){
			$info = curl_getinfo($ch);
			CLog::add2log(array("event" => "MC", "text" => "Ошибка при обмене /entity/customerorder?offset={$offset}&limit={$limit}&search={$search}&order=moment,desc", "detail" => serialize(array("res" => $res, "info" => $info, "error" => curl_error($ch)))));

			if($attempt < 3){
				sleep(1);
				$this->getListOrder($page, $search, $firstPage, $attempt + 1);
			}

		}

		foreach($result["rows"] as $key => $arItem){
			$this->MSPosition[$arItem["id"]] = $arItem;
		}

		if($firstPage === false && $result["meta"]["size"] > $result["meta"]["limit"] + $offset){
			sleep(2);
			$this->getListOrder($page + 1, $search, $firstPage);
		}

		//return $result;
	}

	public function getPaymentTemplate($_data = array(), $attempt = 0){

		$access = $this->access_token;

		if(!$access || !$_data) return;
		$this->add2log(date("Y-m-d H:i:s") . " /entity/paymentin/new");
		$_data = array(
			"operations" => array(array("meta" => $_data))
		);
		$_data = json_encode($_data);

		$ch = curl_init($this->api_url . "/entity/paymentin/new");
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Content-Type: application/json",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $_data);
		//curl_setopt($ch, CURLOPT_INTERFACE, "93.125.48.252");

		$res = curl_exec($ch);

		$result = gzdecode($res);

$result = json_decode($result, true);

		if(!$result || $result["errors"]){
			$info = curl_getinfo($ch);
			CLog::add2log(array("event" => "MC", "text" => "Ошибка при обмене /entity/paymentin/new", "detail" => serialize(array("res" => $res, "info" => $info, "error" => curl_error($ch)))));

			if($attempt < 3){
				sleep(1);
				$this->getPaymentTemplate($_data, $attempt + 1);
			}

		}

		return $result;
	}

	public function getPaymentOutTemplate($_data = array(), $attempt = 0){

		$access = $this->access_token;

		if(!$access || !$_data) return;

		$this->add2log(date("Y-m-d H:i:s") . " /entity/paymentout/new");
		$_data = array(
			"organization" => array(array("meta" => $_data))
		);
		$_data = json_encode($_data);
		prent($_data);
		$ch = curl_init($this->api_url . "/entity/paymentout/new");
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Content-Type: application/json",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $_data);
		//curl_setopt($ch, CURLOPT_INTERFACE, "93.125.48.252");

		$res = curl_exec($ch);

		$result = gzdecode($res);

$result = json_decode($result, true);

		if(!$result || $result["errors"]){
			$info = curl_getinfo($ch);
			CLog::add2log(array("event" => "MC", "text" => "Ошибка при обмене /entity/paymentout/new", "detail" => serialize(array("res" => $res, "info" => $info, "error" => curl_error($ch)))));

			if($attempt < 3){
				sleep(1);
				$this->getPaymentOutTemplate($_data, $attempt + 1);
			}

		}

		return $result;
	}

	public function getCashInTemplate($_data = array(), $attempt = 0){

		$access = $this->access_token;

		if(!$access || !$_data) return;
		$this->add2log(date("Y-m-d H:i:s") . " /entity/cashin/new");
		$data = json_encode(array('operations' => array(array('meta' => $_data))));

		$ch = curl_init($this->api_url . "/entity/cashin/new");
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Content-Type: application/json",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		//curl_setopt($ch, CURLOPT_INTERFACE, "93.125.48.252");

		$res = curl_exec($ch);

		$result = gzdecode($res);

		$result = json_decode($result, true);

		if(!$result || $result["errors"]){
			$info = curl_getinfo($ch);
			CLog::add2log(array("event" => "MC", "text" => "Ошибка при обмене /entity/cashin/new", "detail" => serialize(array("res" => $res, "info" => $info, "error" => curl_error($ch)))));

			if($attempt < 3){
				sleep(1);
				$this->getCashInTemplate($_data, $attempt + 1);
			}

		}

		return $result;
	}

	public function getCashOutTemplate($_data = array(), $attempt = 0){

		$access = $this->access_token;

		if(!$access || !$_data) return;
		$this->add2log(date("Y-m-d H:i:s") . " /entity/cashout/new");

		$_data = array(
			"operations" => array(array("meta" => $_data))
		);
		$_data = json_encode($_data);

		$ch = curl_init($this->api_url . "/entity/cashout/new");
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Content-Type: application/json",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $_data);
		//curl_setopt($ch, CURLOPT_INTERFACE, "93.125.48.252");

		$res = curl_exec($ch);

		$result = gzdecode($res);

$result = json_decode($result, true);

		if(!$result || $result["errors"]){
			$info = curl_getinfo($ch);
			CLog::add2log(array("event" => "MC", "text" => "Ошибка при обмене /entity/cashout/new", "detail" => serialize(array("res" => $res, "info" => $info, "error" => curl_error($ch)))));

			if($attempt < 3){
				sleep(1);
				$this->getCashOutTemplate($_data, $attempt + 1);
			}

		}

		return $result;
	}


	public function setCash($_data = array(), $attempt = 0){

		$access = $this->access_token;

		if(!$access || !$_data) return;
		$this->add2log(date("Y-m-d H:i:s") . " /entity/cashin");
		$data = json_encode($_data);
		//prent($_data);
		//prent($_data);die;

		$ch = curl_init($this->api_url . "/entity/cashin");
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Content-Type: application/json",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		//curl_setopt($ch, CURLOPT_INTERFACE, "93.125.48.252");

		$res = curl_exec($ch);
		$result = gzdecode($res);

		$result = json_decode($result, true);
		if(!$result || $result["errors"]){
			$info = curl_getinfo($ch);
			CLog::add2log(array("event" => "MC", "text" => "Ошибка при обмене /entity/cashin", "detail" => serialize(array("res" => $res, "info" => $info, "error" => curl_error($ch)))));

			if($attempt < 3){
				sleep(1);
				$this->setCash($_data, $attempt + 1);
			}

		}

		return $result;
	}

	function createCounterparty($userData){
		$access = $this->access_token;

		if(!$access) {return "Invalid access token";}

		$ch = curl_init($this->api_url . "/entity/counterparty");


		$data = json_encode($userData);

		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Content-Type: application/json",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

		$res = curl_exec($ch);
		print_r($res);
		print_r('###');

		$result = gzdecode($res);

		$result = json_decode($result, true);

		curl_close($ch);
		return $result;
	}

	public function setCashOut($_data = array(), $attempt = 0){

		$access = $this->access_token;

		if(!$access || !$_data) return;
		$this->add2log(date("Y-m-d H:i:s") . " /entity/cashout");

		$_data = json_encode($_data);
		//prent($_data);
		//prent($_data);die;
		$ch = curl_init($this->api_url . "/entity/cashout");
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Content-Type: application/json",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $_data);
		//curl_setopt($ch, CURLOPT_INTERFACE, "93.125.48.252");

		$res = curl_exec($ch);

		$result = gzdecode($res);

$result = json_decode($result, true);

		if(!$result || $result["errors"]){
			$info = curl_getinfo($ch);
			CLog::add2log(array("event" => "MC", "text" => "Ошибка при обмене /entity/cashout", "detail" => serialize(array("res" => $res, "info" => $info, "error" => curl_error($ch)))));

			if($attempt < 3){
				sleep(1);
				$this->setCashOut($_data, $attempt + 1);
			}

		}

		return $result;
	}

	public function setPayment($_data = array(), $attempt = 0){

		$access = $this->access_token;

		if(!$access || !$_data) return;
		$this->add2log(date("Y-m-d H:i:s") . " /entity/paymentin");

		$_data = json_encode($_data);

		$ch = curl_init($this->api_url . "/entity/paymentin");
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Content-Type: application/json",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $_data);
		//curl_setopt($ch, CURLOPT_INTERFACE, "93.125.48.252");

		$res = curl_exec($ch);

		$result = gzdecode($res);

$result = json_decode($result, true);

		if(!$result || $result["errors"]){
			$info = curl_getinfo($ch);
			CLog::add2log(array("event" => "MC", "text" => "Ошибка при обмене /entity/paymentin", "detail" => serialize(array("res" => $res, "info" => $info, "error" => curl_error($ch)))));

			if($attempt < 3){
				sleep(1);
				$this->setPayment($_data, $attempt + 1);
			}

		}

		return $result;
	}

	public function setPaymentOut($_data = array(), $attempt = 0){

		$access = $this->access_token;

		if(!$access || !$_data) return;
		$this->add2log(date("Y-m-d H:i:s") . " /entity/paymentout");

		$_data = json_encode($_data);

		$ch = curl_init($this->api_url . "/entity/paymentout");
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Content-Type: application/json",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $_data);
		//curl_setopt($ch, CURLOPT_INTERFACE, "93.125.48.252");

		$res = curl_exec($ch);

		$result = gzdecode($res);

$result = json_decode($result, true);

		if(!$result || $result["errors"]){
			$info = curl_getinfo($ch);
			CLog::add2log(array("event" => "MC", "text" => "Ошибка при обмене /entity/paymentout", "detail" => serialize(array("res" => $res, "info" => $info, "error" => curl_error($ch)))));

			if($attempt < 3){
				sleep(1);
				$this->setPaymentOut($_data, $attempt + 1);
			}

		}

		return $result;
	}

	public function getListProject($attempt = 0){

		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}
		$this->add2log(date("Y-m-d H:i:s") . " /entity/project");

		$ch = curl_init($this->api_url . "/entity/project");
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Accept: application/json;charset=utf-8",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		//curl_setopt($ch, CURLOPT_INTERFACE, "93.125.48.252");

		$res = curl_exec($ch);

		$result = gzdecode($res);

$result = json_decode($result, true);

		if(!$result || $result["errors"]){
			$info = curl_getinfo($ch);
			CLog::add2log(array("event" => "MC", "text" => "Ошибка при обмене /entity/project", "detail" => serialize(array("res" => $res, "info" => $info, "error" => curl_error($ch)))));

			if($attempt < 3){
				sleep(1);
				$this->getListProject($attempt + 1);
			}

		}

		return $result;
	}

	public function getProject($id = "", $attempt = 0){
		if(!$id) return;
		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}
		$this->add2log(date("Y-m-d H:i:s") . " /entity/project/{$id}");

		$ch = curl_init($this->api_url . "/entity/project/{$id}");
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Accept: application/json;charset=utf-8",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		//curl_setopt($ch, CURLOPT_INTERFACE, "93.125.48.252");

		$res = curl_exec($ch);

		$result = gzdecode($res);

$result = json_decode($result, true);

		if(!$result || $result["errors"]){
			$info = curl_getinfo($ch);
			CLog::add2log(array("event" => "MC", "text" => "Ошибка при обмене /entity/project/{$id}", "detail" => serialize(array("res" => $res, "info" => $info, "error" => curl_error($ch)))));

			if($attempt < 3){
				sleep(1);
				$this->getListProject($id, $attempt + 1);
			}

		}

		return $result;
	}
	public function getListSupply($page = 0, $firstPage = false, $attempt = 0){

		if($page == 0) $offset = 0; else $offset = $page * 1000;

		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}
		$this->add2log(date("Y-m-d H:i:s") . " /entity/supply?offset={$offset}&limit={$limit}&order=updated,desc");

		$limit = 1000;
		//$limit = 1;updated
		//&order=moment,desc
		$ch = curl_init($this->api_url . "/entity/supply?offset={$offset}&limit={$limit}&order=updated,desc");
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Accept: application/json;charset=utf-8",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_TIMEOUT, 600);
		//curl_setopt($ch, CURLOPT_INTERFACE, "93.125.48.252");

		$res = curl_exec($ch);

		$result = gzdecode($res);

$result = json_decode($result, true);

		if(!$result || $result["errors"]){
			$info = curl_getinfo($ch);
			CLog::add2log(array("event" => "MC", "text" => "Ошибка при обмене /entity/supply", "detail" => serialize(array("res" => $res, "info" => $info, "error" => curl_error($ch)))));

			if($attempt < 3){
				sleep(1);
				$this->getListSupply($page, $firstPage, $attempt + 1);
			}

		}

		foreach($result["rows"] as $key => $arItem){
			$this->MSPosition[$arItem["id"]] = $arItem;
		}

		if($firstPage === false && $result["meta"]["size"] > $result["meta"]["limit"] + $offset){
			$this->getListSupply($page + 1);
		}

	}

	public function getSalesReturnCustom($expand = false){
		// if($page == 0) $offset = 0; else $offset = $page * 1000;
		$access = $this->access_token;
		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}

		$limit = 100;
		$urlParams = '';
		if( $expand != false){
			$urlParams = '?expand=' . $expand . '&' . 'limit=100';
		}

		// $ch = curl_init($this->api_url . "/entity/salesreturn?offset={$offset}&limit={$limit}&order=updated,desc");
		$ch = curl_init($this->api_url . "/entity/salesreturn" . $urlParams);
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Accept: application/json;charset=utf-8",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		$res = curl_exec($ch);
		$result = gzdecode($res);
		$result = json_decode($result, true);

		return $result['rows'];
	}

	public function getListSalesReturn($page = 0, $firstPage = false, $attempt = 0){

		if($page == 0) $offset = 0; else $offset = $page * 1000;

		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}
		$this->add2log(date("Y-m-d H:i:s") . " /entity/salesreturn?offset={$offset}&limit={$limit}&order=updated,desc");

		$limit = 1000;
		//$limit = 1;updated
		//&order=moment,desc
		$ch = curl_init($this->api_url . "/entity/salesreturn?offset={$offset}&limit={$limit}&order=updated,desc");
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Accept: application/json;charset=utf-8",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_TIMEOUT, 600);
		//curl_setopt($ch, CURLOPT_INTERFACE, "93.125.48.252");

		$res = curl_exec($ch);

		$result = gzdecode($res);

		$result = json_decode($result, true);

		if(!$result || $result["errors"]){
			$info = curl_getinfo($ch);
			CLog::add2log(array("event" => "MC", "text" => "Ошибка при обмене /entity/salesreturn?offset={$offset}&limit={$limit}&order=updated,desc", "detail" => serialize(array("res" => $res, "info" => $info, "error" => curl_error($ch)))));

			if($attempt < 3){
				sleep(1);
				$this->getListSalesReturn($page, $firstPage, $attempt + 1);
			}

		}

		foreach($result["rows"] as $key => $arItem){
			$this->MSPosition[$arItem["id"]] = $arItem;
		}

		if($firstPage === false && $result["meta"]["size"] > $result["meta"]["limit"] + $offset){
			$this->getListSalesReturn($page + 1);
		}

	}

	public function getListMove($page = 0, $firstPage = false, $attempt = 0){

		if($page == 0) $offset = 0; else $offset = $page * 1000;

		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}
		$this->add2log(date("Y-m-d H:i:s") . " /entity/move?offset={$offset}&limit={$limit}&order=updated,desc");

		$limit = 200;
		//$limit = 1;updated
		//&order=moment,desc
		$ch = curl_init($this->api_url . "/entity/move?offset={$offset}&limit={$limit}&order=updated,desc");
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Accept: application/json;charset=utf-8",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_TIMEOUT, 600);
		//curl_setopt($ch, CURLOPT_INTERFACE, "93.125.48.252");

		$res = curl_exec($ch);

		$result = gzdecode($res);

		$result = json_decode($result, true);

		if(!$result || $result["errors"]){
			$info = curl_getinfo($ch);
			CLog::add2log(array("event" => "MC", "text" => "Ошибка при обмене /entity/move?offset={$offset}&limit={$limit}&order=updated,desc", "detail" => serialize(array("res" => $res, "info" => $info, "error" => curl_error($ch)))));

			if($attempt < 3){
				sleep(1);
				$this->getListMove($page, $firstPage, $attempt + 1);
			}

		}

		foreach($result["rows"] as $key => $arItem){
			$this->MSPosition[$arItem["id"]] = $arItem;
		}

		if($firstPage === false && $result["meta"]["size"] > $result["meta"]["limit"] + $offset){
			$this->getListMove($page + 1);
		}

	}

	public function getListProfit($page = 0, $firstPage = false, $arFilter = array(), $attempt = 0){

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

		$this->add2log(date("Y-m-d H:i:s") . " /report/profit/byproduct?offset={$offset}&limit={$limit}&momentFrom={$momentFrom}&momentTo={$momentTo}&filter=counterparty");
		//prent($momentTo);prent($momentFrom);
		$ch = curl_init($this->api_url . "/report/profit/byproduct?offset={$offset}&limit={$limit}&momentFrom={$momentFrom}&momentTo={$momentTo}");
//&filter=counterparty=https://api.moysklad.ru/api/remap/1.2/entity/counterparty/c5ae8285-242d-11ec-0a80-0d7b0021192c
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Accept: application/json;charset=utf-8",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		//curl_setopt($ch, CURLOPT_INTERFACE, "93.125.48.252");

		$res = curl_exec($ch);

		$result = gzdecode($res);

$result = json_decode($result, true);

		if(!$result || $result["errors"]){
			$info = curl_getinfo($ch);
			CLog::add2log(array("event" => "MC", "text" => "Ошибка при обмене /report/profit/byproduct?offset={$offset}&limit={$limit}&momentFrom={$momentFrom}&momentTo={$momentTo}&filter=counterparty", "detail" => serialize(array("res" => $res, "info" => $info, "error" => curl_error($ch)))));

			if($attempt < 3){
				sleep(1);
				$this->getListProfit($page, $firstPage, $arFilter, $attempt + 1);
			}

		}

		foreach($result["rows"] as $key => $arItem){
			if($arItem["sellQuantity"] > 4 && $arItem["assortment"]["code"]){
				//$this->MSPosition[] = $arItem;
				$this->MSPosition[$arItem["assortment"]["code"]] = array(
					"XML_ID" => $arItem["assortment"]["code"],
					"ARTICLE" => $arItem["assortment"]["article"],
				);
			}

		}


		if($firstPage === false && $result["meta"]["size"] > $result["meta"]["limit"] + $offset){
			$this->getListProfit($page + 1, $firstPage, $arFilter);
		}

	}

	public function getListProfitByAgent($page = 0, $firstPage = false, $arFilter = array(), $attempt = 0){

		if($page == 0) $offset = 0; else $offset = $page * 1000;

		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}

		$limit = 1000;
		//$limit = 1;updated
		//&order=moment,desc
		$momentTo = date("Y-m-d");
		$momentFrom = date("Y-m-d", strtotime("-30 day"));

		if($arFilter["momentTo"]) $momentTo = $arFilter["momentTo"]; else $momentTo = date("Y-m-d");
		if($arFilter["momentFrom"]) $momentFrom = $arFilter["momentFrom"]; else $momentFrom = date("Y-m-d", strtotime("-30 day"));

		$this->add2log(date("Y-m-d H:i:s") . " /report/profit/byproduct?offset={$offset}&limit={$limit}&momentFrom={$momentFrom}&momentTo={$momentTo}&filter=counterparty");

		$url = $this->api_url . "/report/profit/byproduct?offset={$offset}&limit={$limit}&momentFrom={$momentFrom}&momentTo={$momentTo}";
		if ( !empty($arFilter['agent']) ){
			$url .= '&filter=counterparty=https://api.moysklad.ru/api/remap/1.2/entity/counterparty/' . $arFilter['agent'];
		}
		$ch = curl_init( $url );
//&filter=counterparty=https://api.moysklad.ru/api/remap/1.2/entity/counterparty/c5ae8285-242d-11ec-0a80-0d7b0021192c
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Accept: application/json;charset=utf-8",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_TIMEOUT, 300);
		//curl_setopt($ch, CURLOPT_INTERFACE, "93.125.48.252");

		$res = curl_exec($ch);
		$result = gzdecode($res);
		// var_dump($result);

		$result = json_decode($result, true);

		if(!$result || $result["errors"]){
			$info = curl_getinfo($ch);
			CLog::add2log(array("event" => "MC", "text" => "Ошибка при обмене /report/profit/byproduct?offset={$offset}&limit={$limit}&momentFrom={$momentFrom}&momentTo={$momentTo}&filter=counterparty", "detail" => serialize(array("res" => $res, "info" => $info, "error" => curl_error($ch)))));

			if($attempt < 3){
				sleep(1);
				$this->getListProfitByAgent($page, $firstPage, $arFilter, $attempt + 1);
			}
		}

		foreach($result["rows"] as $key => $arItem){
				if ( $arItem["sellQuantity"] <= 1 ) continue;
				$this->MSPosition[$arItem["assortment"]["code"]] = array(
					"SELLSUM" => $arItem["sellSum"],// Не переименовал ключ, чтобы не править еще тонну скриптов
					"ARTICLE" => $arItem["assortment"]["article"],
					"COUNT" => $arItem["sellQuantity"],
					"PROFIT" => $arItem["profit"],
				);
				// $this->MSPosition[$arItem["assortment"]["code"]] = $arItem;
		}


		if($firstPage === false && $result["meta"]["size"] > $result["meta"]["limit"] + $offset){
			$this->getListProfitByAgent($page + 1, $firstPage, $arFilter);
		}

	}

	public function getListProfitByAgentCustom($page = 0, $firstPage = false, $arFilter = array(), $attempt = 0){

		if($page == 0) $offset = 0; else $offset = $page * 1000;

		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}

		$limit = 1000;
		//$limit = 1;updated
		//&order=moment,desc
		$momentTo = date("Y-m-d");
		$momentFrom = date("Y-m-d", strtotime("-30 day"));

		if($arFilter["momentTo"]) $momentTo = $arFilter["momentTo"]; else $momentTo = date("Y-m-d");
		if($arFilter["momentFrom"]) $momentFrom = $arFilter["momentFrom"]; else $momentFrom = date("Y-m-d", strtotime("-30 day"));

		$this->add2log(date("Y-m-d H:i:s") . " /report/profit/byproduct?offset={$offset}&limit={$limit}&momentFrom={$momentFrom}&momentTo={$momentTo}&filter=counterparty");

		$url = $this->api_url . "/report/profit/byproduct?offset={$offset}&limit={$limit}&momentFrom={$momentFrom}&momentTo={$momentTo}";
		if ( !empty($arFilter['agent']) ){
			$url .= '&filter=counterparty=https://api.moysklad.ru/api/remap/1.2/entity/counterparty/' . $arFilter['agent'];
		}
		$ch = curl_init( $url );
//&filter=counterparty=https://api.moysklad.ru/api/remap/1.2/entity/counterparty/c5ae8285-242d-11ec-0a80-0d7b0021192c
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Accept: application/json;charset=utf-8",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_TIMEOUT, 300);
		//curl_setopt($ch, CURLOPT_INTERFACE, "93.125.48.252");

		$res = curl_exec($ch);
		$result = gzdecode($res);
		// var_dump($result);

		$result = json_decode($result, true);

		if(!$result || $result["errors"]){
			$info = curl_getinfo($ch);
			CLog::add2log(array("event" => "MC", "text" => "Ошибка при обмене /report/profit/byproduct?offset={$offset}&limit={$limit}&momentFrom={$momentFrom}&momentTo={$momentTo}&filter=counterparty", "detail" => serialize(array("res" => $res, "info" => $info, "error" => curl_error($ch)))));

			if($attempt < 3){
				sleep(1);
				$this->getListProfitByAgentCustom($page, $firstPage, $arFilter, $attempt + 1);
			}
		}

		foreach($result["rows"] as $key => $arItem){
				// if ( $arItem["sellQuantity"] <= 1 ) continue;
				$this->MSPosition[$arItem["assortment"]["code"]] = array(
					"SELLSUM" => $arItem["sellSum"],// Не переименовал ключ, чтобы не править еще тонну скриптов
					"ARTICLE" => $arItem["assortment"]["article"],
					"COUNT" => $arItem["sellQuantity"],
					"PROFIT" => $arItem["profit"],
				);
				// $this->MSPosition[$arItem["assortment"]["code"]] = $arItem;
		}


		if($firstPage === false && $result["meta"]["size"] > $result["meta"]["limit"] + $offset){
			$this->getListProfitByAgentCustom($page + 1, $firstPage, $arFilter);
		}

	}

	public function getListProfitCustom($page = 0, $firstPage = false, $arFilter = array(), $attempt = 0){

		if($page == 0) $offset = 0; else $offset = $page * 1000;

		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}

		$limit = 1000;
		$momentTo = date("Y-m-d");
		$momentFrom = date("Y-m-d", strtotime("-6 month"));

		if($arFilter["momentTo"]) $momentTo = $arFilter["momentTo"]; else $momentTo = date("Y-m-d");
		if($arFilter["momentFrom"]) $momentFrom = $arFilter["momentFrom"]; else $momentFrom = date("Y-m-d", strtotime("-6 month"));

		$ch = curl_init($this->api_url . "/report/profit/byproduct?offset={$offset}&limit={$limit}&momentFrom={$momentFrom}&momentTo={$momentTo}");
//&filter=counterparty=https://api.moysklad.ru/api/remap/1.2/entity/counterparty/c5ae8285-242d-11ec-0a80-0d7b0021192c
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Accept: application/json;charset=utf-8",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		$res = curl_exec($ch);
		$result = gzdecode($res);
		$result = json_decode($result, true);
		return $result;

	}



	public function getListProfitNew($arFilter = array()){

		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}

		$limit = 1000;
		//$limit = 1;updated
		//&order=moment,desc
		$momentTo = date("Y-m-d");
		$momentFrom = date("Y-m-d", strtotime("-6 month"));

		if($arFilter["momentTo"]) $momentTo = $arFilter["momentTo"]; else $momentTo = date("Y-m-d");
		if($arFilter["momentFrom"]) $momentFrom = $arFilter["momentFrom"]; else $momentFrom = date("Y-m-d", strtotime("-6 month"));

		$send = true;
		$page = 0;
		do {

			if($page == 0) $offset = 0; else $offset = $page * 1000;

			$ch = curl_init($this->api_url . "/report/profit/byproduct?offset={$offset}&limit={$limit}&momentFrom={$momentFrom}&momentTo={$momentTo}");

			curl_setopt(
				$ch,
				CURLOPT_HTTPHEADER,
				array(
					"Accept: application/json;charset=utf-8",
					'Accept-Encoding: gzip',
					"Authorization: Bearer $access"
				)
			);

			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

			$res = curl_exec($ch);

			$result = gzdecode($res);

			$result = json_decode($result, true);

			if (empty($result["rows"])) {
				$send = false;
			} else {
				if (!empty($arData)) {
					$arData = array_merge($arData, $result["rows"]);
				} else {
					$arData = $result["rows"];
				}
			}
			// || $page > 10
			// if($result["meta"]["size"] < $result["meta"]["limit"] + $offset){
			// 	$send = false;
			// }

            $page++;
						sleep(1);
		} while ($send);

		return $arData;
	}

	public function getListProfitChannel($arFilter = array()){

		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}

		$limit = 1000;
		$momentTo = date("Y-m-d");
		$momentFrom = date("Y-m-d", strtotime("-6 month"));

		if($arFilter["momentTo"]) $momentTo = $arFilter["momentTo"]; else $momentTo = date("Y-m-d");
		if($arFilter["momentFrom"]) $momentFrom = $arFilter["momentFrom"]; else $momentFrom = date("Y-m-d", strtotime("-6 month"));

		$send = true;
		$page = 0;
		do {

			if($page == 0) $offset = 0; else $offset = $page * 1000;

			$url = "offset={$offset}&limit={$limit}&momentFrom=".urlencode($momentFrom)."&momentTo=".urlencode($momentTo)."";

			$ch = curl_init($this->api_url . "/report/profit/bysaleschannel?" . $url);

			curl_setopt(
				$ch,
				CURLOPT_HTTPHEADER,
				array(
					"Accept: application/json;charset=utf-8",
					'Accept-Encoding: gzip',
					"Authorization: Bearer $access"
				)
			);

			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

			$res = curl_exec($ch);

			$result = gzdecode($res);

$result = json_decode($result, true);

			if (!empty($arData)) {
				$arData = array_merge($arData, $result["rows"]);
			} else {
				$arData = $result["rows"];
			}
			// || $page > 10
			if($result["meta"]["size"] < $result["meta"]["limit"] + $offset){
				$send = false;
			}

            $page++;

		} while ($send);

		return $arData;
	}

	public function getListProfitDay($arFilter = array()){

		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}

		$limit = 1000;
		$momentTo = date("Y-m-d");
		$momentFrom = date("Y-m-d", strtotime("-6 month"));

		if($arFilter["momentTo"]) $momentTo = $arFilter["momentTo"]; else $momentTo = date("Y-m-d");
		if($arFilter["momentFrom"]) $momentFrom = $arFilter["momentFrom"]; else $momentFrom = date("Y-m-d", strtotime("-6 month"));

		$send = true;
		$page = 0;
		do {

			if($page == 0) $offset = 0; else $offset = $page * 1000;
			if (isset($arFilter['salesChannel'])) {
				$url = "offset={$offset}&limit={$limit}&momentFrom=".urlencode($momentFrom)."&momentTo=".urlencode($momentTo)."&filter=salesChannel=".$arFilter['salesChannel']."";
			}else {
				$url = "offset={$offset}&limit={$limit}&momentFrom=".urlencode($momentFrom)."&momentTo=".urlencode($momentTo)."";
			}

			$ch = curl_init($this->api_url . "/report/profit/byproduct?" . $url);

			curl_setopt(
				$ch,
				CURLOPT_HTTPHEADER,
				array(
					"Accept: application/json;charset=utf-8",
					'Accept-Encoding: gzip',
					"Authorization: Bearer $access"
				)
			);

			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

			$res = curl_exec($ch);

			$result = gzdecode($res);

			$result = json_decode($result, true);
			if (!empty($arData)) {
				$arData = array_merge($arData, $result["rows"]);
			} else {
				$arData = $result["rows"];
			}
			// || $page > 10
			if($result["meta"]["size"] < $result["meta"]["limit"] + $offset){
				$send = false;
			}

            $page++;

		} while ($send);

		return $arData;
	}
	public function getDemandTemplate($_data = array(), $attempt = 0){
		$start = debug_microtime_float();
		$access = $this->access_token;

		if(!$access || !$_data) return;

		$arLog = [
			"date" => date("Y-m-d H:i:s"),
			"data" => $_data,
			"attempt" => $attempt,
			"url" => "/entity/demand/new",
			"method" => "getDemandTemplate",
		];
		$this->add2log($arLog);

		$_data = array(
			"customerOrder" => array("meta" => $_data)
		);
		$_data = json_encode($_data);

		$ch = curl_init($this->api_url . "/entity/demand/new");
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Content-Type: application/json",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $_data);
		//curl_setopt($ch, CURLOPT_INTERFACE, "93.125.48.252");
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
		curl_setopt($ch, CURLOPT_TIMEOUT, 2);

		$res = curl_exec($ch);

		$result = gzdecode($res);

		$arLog = [
			"date" => date("Y-m-d H:i:s"),
			"data" => $_data,
			"attempt" => $attempt,
			"url" => "/entity/demand/new",
			"method" => "getDemandTemplate end",
			"time" => debug_microtime_float() - $start,
			//"response" => $result,
		];
		$this->add2log($arLog);

		$result = json_decode($result, true);

		if(!$result){
			$info = curl_getinfo($ch);
			CLog::add2log(array("event" => "MC", "text" => "Ошибка при обмене /entity/demand/new (пустое значение)", "detail" => serialize(array("res" => $res, "info" => $info, "error" => curl_error($ch)))));

			if($attempt < 3){
				sleep(1);
				$this->getDemandTemplate($_data, $attempt + 1);
			}

		} else if($result["errors"]){
			$info = curl_getinfo($ch);
			CLog::add2log(array("event" => "MC", "text" => "Ошибка при обмене /entity/demand/new (ошибка)", "detail" => serialize(array("res" => $res, "info" => $info, "error" => curl_error($ch)))));

			if($attempt < 3){
				sleep(1);
				$this->getDemandTemplate($_data, $attempt + 1);
			}

		}

		return $result;
	}

	public function getDemandShipmentTemplate($_data = array(), $attempt = 0){
		$start = debug_microtime_float();
		$access = $this->access_token;

		if(!$access || !$_data) return;

		$arLog = [
			"date" => date("Y-m-d H:i:s"),
			"data" => $_data,
			"attempt" => $attempt,
			"url" => "/entity/demand/new",
			"method" => "getDemandShipmentTemplate",
		];
		$this->add2log($arLog);

		$_data = array(
			"demand" => array("meta" => $_data)
		);
		$_data = json_encode($_data);

		$ch = curl_init($this->api_url . "/entity/demand/new");
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Content-Type: application/json",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $_data);
		//curl_setopt($ch, CURLOPT_INTERFACE, "93.125.48.252");
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
		curl_setopt($ch, CURLOPT_TIMEOUT, 2);

		$res = curl_exec($ch);

		$result = gzdecode($res);

		$arLog = [
			"date" => date("Y-m-d H:i:s"),
			"data" => $_data,
			"attempt" => $attempt,
			"url" => "/entity/demand/new",
			"method" => "getDemandShipmentTemplate end",
			"time" => debug_microtime_float() - $start,
			//"response" => $result,
		];
		$this->add2log($arLog);

		$result = json_decode($result, true);

		if(!$result){
			$info = curl_getinfo($ch);
			CLog::add2log(array("event" => "MC", "text" => "Ошибка при обмене /entity/demand/new (пустое значение)", "detail" => serialize(array("res" => $res, "info" => $info, "error" => curl_error($ch)))));

			if($attempt < 3){
				sleep(1);
				$this->getDemandShipmentTemplate($_data, $attempt + 1);
			}

		} else if($result["errors"]){
			$info = curl_getinfo($ch);
			CLog::add2log(array("event" => "MC", "text" => "Ошибка при обмене /entity/demand/new (ошибка)", "detail" => serialize(array("res" => $res, "info" => $info, "error" => curl_error($ch)))));

			if($attempt < 3){
				sleep(1);
				$this->getDemandShipmentTemplate($_data, $attempt + 1);
			}

		}

		return $result;
	}

	//Массовое создание и обновление Отгрузок

	public function setDemand($_data = array(), $attempt = 0){
		$start = debug_microtime_float();
		$access = $this->access_token;

		if(!$access || !$_data) return;

		$arLog = [
			"date" => date("Y-m-d H:i:s"),
			"data" => $_data,
			"attempt" => $attempt,
			"method" => "setDemand",
		];
		$this->add2log($arLog);

		$data = json_encode($_data);

		$ch = curl_init($this->api_url . "/entity/demand");
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Content-Type: application/json",
				"Accept-Encoding: gzip",
				"Authorization: Bearer $access"
			)
		);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		//curl_setopt($ch, CURLOPT_INTERFACE, "93.125.48.252");

		$res = curl_exec($ch);

		$result = gzdecode($res);

		$arLog = [
			"date" => date("Y-m-d H:i:s"),
			"data" => $_data,
			"attempt" => $attempt,
			"method" => "setDemand end",
			"time" => debug_microtime_float() - $start,
			"response" => $result,
		];
		$this->add2log($arLog);

		$result = json_decode($result, true);
		if(!$result){
				$info = curl_getinfo($ch);
				CLog::add2log(array("event" => "MC", "text" => "Ошибка при обмене /entity/demand (".$res.")", "detail" => serialize(array("res" => $resJson, "info" => $info, "error" => curl_error($ch)))));

			if($attempt < 3){
				sleep(1);
				$this->setDemand($_data, $attempt + 1);
			}
		}

		return $result ?? json_decode($res, 1);
	}

	public function getStock($page = 0, $filter = "", $attempt = 0){
		$start = debug_microtime_float();
		$this->isResponseValid = false;
		if($page == 0) $offset = 0; else $offset = $page * 1000;

		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}

        $store_id = '';
        if ($filter != '') {
            $store_id = explode('filter=store=https://api.moysklad.ru/api/remap/1.2/entity/store/',$filter);
            $store_id = explode( ';',$store_id[1] )[0];
        }
		$limit = 1000;

		$arLog = [
			"date" => date("Y-m-d H:i:s"),
			"url" => "/report/stock/all?limit={$limit}&offset={$offset}",
			"attempt" => $attempt,
			"method" => "getStock",
			"SCRIPT_FILENAME" => $_SERVER["SCRIPT_FILENAME"],
		];
		$this->add2log($arLog);

		$ch = curl_init($this->api_url . "/report/stock/all?limit={$limit}&offset={$offset}" . (strlen($filter) > 0 ? "&" . $filter : ""));

		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
		curl_setopt($ch, CURLOPT_TIMEOUT, 300);
		//curl_setopt($ch, CURLOPT_INTERFACE, "93.125.48.252");
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POST, 0);

		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Accept: application/json;charset=utf-8",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);

		$res = curl_exec($ch);
		$info = curl_getinfo($ch);
		//prent(curl_error($ch),0,1);

		$result = gzdecode($res);

		$arLog = [
			"date" => date("Y-m-d H:i:s"),
			"url" => "/report/stock/all?limit={$limit}&offset={$offset}",
			"attempt" => $attempt,
			"method" => "getStock",
			"time" => debug_microtime_float() - $start,
		];
		$this->add2log($arLog);

		if( $info['http_code'] == 401 || ( $this->isInvalidApiTokenError( $result ) && $this->tryToRecreate ) )
		{
			$this->tryToRecreate = false;
			$this->isResponseValid = false;
			$this->updateToken();
			return $this->getStock( $page, $filter, $attempt );
		}

		$result = json_decode($result, true);

		if(!$result || $result["errors"]){
			$info = curl_getinfo($ch);
			CLog::add2log(array("event" => "MC", "text" => "Ошибка при обмене /report/stock/all?stockMode=positiveOnly&limit={$limit}&offset={$offset}", "detail" => serialize(array("res" => $res, "info" => $info, "error" => curl_error($ch)))));

			if($attempt < 3){
				sleep(3);
				$this->getStock($page, $filter, $attempt + 1);
			}

		}

		if($this->colPrice == "salePrice"){
			file_put_contents("/home/bitrix/logs/salePrice.txt", print_r($result, true), FILE_APPEND);
		}
		if ( !empty($result['meta']) && !empty($result['context']) ){
			$this->isResponseValid = true;
		}
		foreach($result["rows"] as $key => $arItem){
			$this->MSPosition[$arItem["externalCode"]] = array(
				"XML_ID" => $arItem["externalCode"],
				"PRICE" => $arItem[$this->colPrice],
				"COUNT" => $arItem["quantity"],
				"stockDays" => $arItem["stockDays"],
				"stock" => $arItem["stock"],
				"name" => $arItem["name"],
                "store_id" => $store_id
			);
		}

		if($result["meta"]["size"] > $result["meta"]["limit"] + $offset){
			$this->getStock($page + 1, $filter);
		}

	}

	public function getProduct($page = 0, $filter = "", $attempt = 0){
		$start = debug_microtime_float();
		if($page == 0) $offset = 0; else $offset = $page * 1000;

		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}

		$limit = 1000;
		$this->add2log(date("Y-m-d H:i:s") . " /entity/product/?limit={$limit}&offset={$offset}");

		$ch = curl_init($this->api_url . "/entity/product/?limit={$limit}&offset={$offset}" . (strlen($filter) > 0 ? "&" . $filter : ""));

		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
		//curl_setopt($ch, CURLOPT_INTERFACE, "93.125.48.252");
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POST, 0);

		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Accept: application/json;charset=utf-8",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);

		$res = curl_exec($ch);

		$info = curl_getinfo($ch);

		$result = gzdecode($res);

$result = json_decode($result, true);

		if(!$result || $result["errors"]){
			$info = curl_getinfo($ch);
			CLog::add2log(array("event" => "MC", "text" => "Ошибка при обмене /entity/product/?limit={$limit}&offset={$offset}", "detail" => serialize(array("res" => $res, "info" => $info, "error" => curl_error($ch)))));

			if($attempt < 3){
				sleep(1);
				$this->getProduct($page, $filter, $attempt + 1);
			}

		}

		foreach($result["rows"] as $key => $arItem){
			$this->MSPosition[$arItem["externalCode"]] = $arItem;
		}

		if($result["meta"]["size"] > $result["meta"]["limit"] + $offset){
			$this->getProduct($page + 1, $filter);
		}

	}

	public function getRetailDemand($page = 0, $firstPage = false, $attempt = 0){
		$start = debug_microtime_float();
		if($page == 0) $offset = 0; else $offset = $page * 100;

		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}

		$limit = 100;
		$this->add2log(date("Y-m-d H:i:s") . " /entity/retaildemand?limit={$limit}&offset={$offset}&order=updated,desc");

		$ch = curl_init($this->api_url . "/entity/retaildemand?limit={$limit}&offset={$offset}&order=updated,desc");

		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
		curl_setopt($ch, CURLOPT_TIMEOUT, 600);
		//curl_setopt($ch, CURLOPT_INTERFACE, "93.125.48.252");
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POST, 0);

		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Accept: application/json;charset=utf-8",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);

		$res = curl_exec($ch);

		$info = curl_getinfo($ch);
		//prent(curl_error($ch),0,1);

		$result = gzdecode($res);

$result = json_decode($result, true);

		if(!$result || $result["errors"]){
			$info = curl_getinfo($ch);
			CLog::add2log(array("event" => "MC", "text" => "Ошибка при обмене /entity/retaildemand?limit={$limit}&offset={$offset}&order=updated,desc", "detail" => serialize(array("res" => $res, "info" => $info, "error" => curl_error($ch)))));

			if($attempt < 3){
				sleep(1);
				$this->getRetailDemand($page, $firstPage, $attempt + 1);
			}

		}

		foreach($result["rows"] as $key => $arItem){
			$this->MSPosition[$arItem["externalCode"]] = $arItem;
		}

		if($firstPage === false && $result["meta"]["size"] > $result["meta"]["limit"] + $offset){
			$this->getRetailDemand($page + 1, $filter);
		}

	}
	public function getRetailPositions($order_id, $attempt = 0){

		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}

		$this->add2log(date("Y-m-d H:i:s") . " /entity/retaildemand/{$order_id}/positions");
		$ch = curl_init($this->api_url . "/entity/retaildemand/{$order_id}/positions");

		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
		curl_setopt($ch, CURLOPT_TIMEOUT, 600);
		//curl_setopt($ch, CURLOPT_INTERFACE, "93.125.48.252");
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POST, 0);

		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Accept: application/json;charset=utf-8",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);

		$res = curl_exec($ch);

		$result = gzdecode($res);

$result = json_decode($result, true);

		if(!$result || $result["errors"]){
			$info = curl_getinfo($ch);
			CLog::add2log(array("event" => "MC", "text" => "Ошибка при обмене /entity/counterparty/{$id}", "detail" => serialize(array("res" => $res, "info" => $info, "error" => curl_error($ch)))));

			if($attempt < 3){
				sleep(1);
				$this->getRetailPositions($order_id, $attempt + 1);
			}

		}

		return $result["rows"];
	}

	//Массовое создание и обновление Возврат покупателя

	public function setSalesReturn($_data = array(), $attempt = 0){
		$start = debug_microtime_float();
		$access = $this->access_token;

		if(!$access || !$_data) return;
		$this->add2log(date("Y-m-d H:i:s") . " /entity/salesreturn");
		$arLog = [
			"date" => date("Y-m-d H:i:s"),
			"data" => $_data,
			"attempt" => $attempt,
			"method" => "setSalesReturn",
		];
		$this->add2log($arLog);

		$_data = json_encode($_data);

		$ch = curl_init($this->api_url . "/entity/salesreturn");
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Content-Type: application/json",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $_data);
		//curl_setopt($ch, CURLOPT_INTERFACE, "93.125.48.252");

		$res = curl_exec($ch);

		$result = gzdecode($res);

		$arLog = [
			"date" => date("Y-m-d H:i:s"),
			"data" => $_data,
			"attempt" => $attempt,
			"method" => "setSalesReturn",
			"time" => debug_microtime_float() - $start,
			//"response" => $result,
		];
		$this->add2log($arLog);

		$result = json_decode($result, true);

		if(!$result || $result["errors"]){
			$info = curl_getinfo($ch);
			CLog::add2log(array("event" => "MC", "text" => "Ошибка при обмене /entity/salesreturn", "detail" => serialize(array("res" => $res, "info" => $info, "error" => curl_error($ch)))));

			if($attempt < 3){
				sleep(1);
				$this->setSalesReturn($_data, $attempt + 1);
			}

		}

		return $result;
	}

	public function getRetailSalesReturn($page = 0, $firstPage = false, $attempt = 0){

		if($page == 0) $offset = 0; else $offset = $page * 50;

		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}

		$limit = 50;

		$this->add2log(date("Y-m-d H:i:s") . " /entity/retailsalesreturn?limit={$limit}&offset={$offset}&order=updated,des");
		$ch = curl_init($this->api_url . "/entity/retailsalesreturn?limit={$limit}&offset={$offset}&order=updated,desc");

		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
		curl_setopt($ch, CURLOPT_TIMEOUT, 300);
		//curl_setopt($ch, CURLOPT_INTERFACE, "93.125.48.252");
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POST, 0);

		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Accept: application/json;charset=utf-8",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);

		$res = curl_exec($ch);

		$info = curl_getinfo($ch);
		//prent(curl_error($ch),0,1);

		$result = gzdecode($res);

$result = json_decode($result, true);

		if(!$result || $result["errors"]){
			$info = curl_getinfo($ch);
			CLog::add2log(array("event" => "MC", "text" => "Ошибка при обмене /entity/retaildemand?limit={$limit}&offset={$offset}&order=updated,desc", "detail" => serialize(array("res" => $res, "info" => $info, "error" => curl_error($ch)))));

			if($attempt < 3){
				sleep(1);
				$this->getRetailSalesReturn($page, $firstPage, $attempt + 1);
			}

		}

		foreach($result["rows"] as $key => $arItem){
			$this->MSPosition[$arItem["externalCode"]] = $arItem;
		}

		if($firstPage === false && $result["meta"]["size"] > $result["meta"]["limit"] + $offset){
			$this->getRetailSalesReturn($page + 1, $filter);
		}

	}

	//Шаблон Возврата покупателя на основе
	public function getSalesReturnTemplate($_data = array(), $attempt = 0){
		$start = debug_microtime_float();
		$access = $this->access_token;

		if(!$access || !$_data) return;

		$arLog = [
			"date" => date("Y-m-d H:i:s"),
			"data" => $_data,
			"attempt" => $attempt,
			"method" => "getSalesReturnTemplate",
		];
		$this->add2log($arLog);

		$_data = array(
			"demand" => array("meta" => $_data)
		);
		$_data = json_encode($_data);

		$ch = curl_init($this->api_url . "/entity/salesreturn/new");
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Content-Type: application/json",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $_data);
		//curl_setopt($ch, CURLOPT_INTERFACE, "93.125.48.252");

		$res = curl_exec($ch);

		$result = gzdecode($res);

		$arLog = [
			"date" => date("Y-m-d H:i:s"),
			"data" => $_data,
			"attempt" => $attempt,
			"method" => "getSalesReturnTemplate",
			"time" => debug_microtime_float() - $start,
			//"response" => $result,
		];
		$this->add2log($arLog);

		$result = json_decode($result, true);

		if(!$result || $result["errors"]){
			$info = curl_getinfo($ch);
			CLog::add2log(array("event" => "MC", "text" => "Ошибка при обмене /entity/salesreturn/new", "detail" => serialize(array("res" => $res, "info" => $info, "error" => curl_error($ch)))));

			if($attempt < 3){
				sleep(1);
				$this->getSalesReturnTemplate($_data, $attempt + 1);
			}

		}

		return $result;
	}

	public function searchAgent($name){

		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}


		$ch = curl_init($this->api_url . "/entity/counterparty?filter=name={$name}");

		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
		curl_setopt($ch, CURLOPT_TIMEOUT, 300);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POST, 0);

		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Accept: application/json;charset=utf-8",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);

		$res = curl_exec($ch);

		$result = gzdecode($res);

		$result = json_decode($result, true);

		if (!empty($result)) {
			$result = $result['rows'];
		}

		return $result;

	}

	public function searchOrder($name){

		$access = $this->access_token;
		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}


		$ch = curl_init($this->api_url . "/entity/customerorder?filter=name={$name}");

		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
		curl_setopt($ch, CURLOPT_TIMEOUT, 300);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POST, 0);

		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Accept: application/json;charset=utf-8",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);

		$res = curl_exec($ch);

		$result = gzdecode($res);

		$result = json_decode($result, true);
		if (!empty($result)) {
			$result = $result['rows'];
		}

		return $result;

	}

	public function getDefault(){

		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}


		$ch = curl_init($this->api_url . "/context/usersettings");

		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
		curl_setopt($ch, CURLOPT_TIMEOUT, 300);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POST, 0);

		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Accept: application/json;charset=utf-8",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);

		$res = curl_exec($ch);

		$result = gzdecode($res);

		$result = json_decode($result, true);
		//
		// if (!empty($result)) {
		// 	$result = $result['rows'];
		// }

		return $result;

	}

	public function getAgent($id, $attempt = 0){

		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}

		$limit = 5;

		$this->add2log(date("Y-m-d H:i:s") . " /entity/counterparty/{$id}");
		$ch = curl_init($this->api_url . "/entity/counterparty/{$id}");

		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
		curl_setopt($ch, CURLOPT_TIMEOUT, 300);
		//curl_setopt($ch, CURLOPT_INTERFACE, "93.125.48.252");
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POST, 0);

		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Accept: application/json;charset=utf-8",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);

		$res = curl_exec($ch);

		$result = gzdecode($res);

		$result = json_decode($result, true);

		if(!$result || $result["errors"]){
			$info = curl_getinfo($ch);
			CLog::add2log(array("event" => "MC", "text" => "Ошибка при обмене /entity/counterparty/{$id}", "detail" => serialize(array("res" => $res, "info" => $info, "error" => curl_error($ch)))));

			if($attempt < 3){
				sleep(1);
				$this->getAgent($id, $attempt + 1);
			}

		}

		return $result;

	}

	public function getOrganization($id = "", $attempt = 0){

		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}


		$this->add2log(date("Y-m-d H:i:s") . " /entity/organization/{$id}");
		$ch = curl_init($this->api_url . "/entity/organization/{$id}");

		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
		//curl_setopt($ch, CURLOPT_INTERFACE, "93.125.48.252");
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POST, 0);

		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Accept: application/json;charset=utf-8",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);

		$res = curl_exec($ch);

		$result = gzdecode($res);

$result = json_decode($result, true);

		if(!$result || $result["errors"]){
			$info = curl_getinfo($ch);
			CLog::add2log(array("event" => "MC", "text" => "Ошибка при обмене /entity/organization/{$id}", "detail" => serialize(array("res" => $res, "info" => $info, "error" => curl_error($ch)))));

			if($attempt < 3){
				sleep(1);
				$this->getOrganization($id, $attempt + 1);
			}

		}

		return $result;

	}

	public function getInvoiceOrg($id = "", $attempt = 0){

		$access = $this->access_token;
		if(!$id) {$this->LAST_ERROR = "empty organization"; return false;}
		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}


		$this->add2log(date("Y-m-d H:i:s") . " /entity/organization/{$id}/accounts");
		$ch = curl_init($this->api_url . "/entity/organization/{$id}/accounts");

		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
		//curl_setopt($ch, CURLOPT_INTERFACE, "93.125.48.252");
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POST, 0);

		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Accept: application/json;charset=utf-8",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);

		$res = curl_exec($ch);

		$result = gzdecode($res);

$result = json_decode($result, true);

		if(!$result || $result["errors"]){
			$info = curl_getinfo($ch);
			CLog::add2log(array("event" => "MC", "text" => "Ошибка при обмене /entity/organization/{$id}/accounts", "detail" => serialize(array("res" => $res, "info" => $info, "error" => curl_error($ch)))));

			if($attempt < 3){
				sleep(1);
				$this->getInvoiceOrg($id, $attempt + 1);
			}

		}

		return $result;

	}

	public function getProductID($id, $attempt = 0){

		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}

		$limit = 5;

		$this->add2log(date("Y-m-d H:i:s") . " /entity/product/{$id}");
		$ch = curl_init($this->api_url . "/entity/product/{$id}");

		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
		curl_setopt($ch, CURLOPT_TIMEOUT, 300);
		//curl_setopt($ch, CURLOPT_INTERFACE, "93.125.48.252");
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POST, 0);

		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Accept: application/json;charset=utf-8",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);

		$res = curl_exec($ch);

		$result = gzdecode($res);

$result = json_decode($result, true);

		if(!$result || $result["errors"]){
			$info = curl_getinfo($ch);
			CLog::add2log(array("event" => "MC", "text" => "Ошибка при обмене /entity/product/{$id}", "detail" => serialize(array("res" => $res, "info" => $info, "error" => curl_error($ch)))));

			if($attempt < 3){
				sleep(1);
				$this->getProductID($id, $attempt + 1);
			}

		}

		return $result;

	}

	public function getListDemand($firstPage = true, $limitTimeStamp = false){

		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}

		$limit = 1000;

		$send = true;
		$page = 0;
		do {

			if($page == 0) $offset = 0; else $offset = $page * 1000;

			$url = "offset={$offset}&limit={$limit}&order=updated,desc";

			$ch = curl_init($this->api_url . "/entity/demand?" . $url);

			curl_setopt(
				$ch,
				CURLOPT_HTTPHEADER,
				array(
					"Accept: application/json;charset=utf-8",
					'Accept-Encoding: gzip',
					"Authorization: Bearer $access"
				)
			);

			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

			$res = curl_exec($ch);

			$result = gzdecode($res);

			$result = json_decode($result, true);

			if (!empty($arData)) {
				$arData = array_merge($arData, $result["rows"]);
			} else {
				$arData = $result["rows"];
			}

			if($firstPage === true || $result["meta"]["size"] < $result["meta"]["limit"] + $offset){
				$send = false;
			}

			if ($firstPage === false && $limitTimeStamp && $result["rows"][0] && $result["rows"][0]['updated']) {
				$timestamp = strtotime($result["rows"][0]['updated']);
				if ($limitTimeStamp > $timestamp) {
					$send = false;
				}
			}

            $page++;

		} while ($send);

		return $arData;
	}

	public function customRequest($url = "", $attempt = 0, $timeout = 600, $customerRequest = ''){
		$start = debug_microtime_float();
		if(!$url) return false;

		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}

		$arLog = [
			"date" => date("Y-m-d H:i:s"),
			"url" => $url,
			"method" => "customRequest",
		];
		$this->add2log($arLog);

		$ch = curl_init($url);
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Accept: application/json;charset=utf-8",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);

		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		//curl_setopt($ch, CURLOPT_INTERFACE, "93.125.48.252");
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POST, 0);

		$res = curl_exec($ch);

		$result = gzdecode($res);

		$arLog = [
			"date" => date("Y-m-d H:i:s"),
			"url" => $url,
			"method" => "customRequest",
			"time" => debug_microtime_float() - $start,
			//"response" => $result,
		];
		$this->add2log($arLog);

		$result = json_decode($result, true);
		if(!$result || $result["errors"]){
			$info = curl_getinfo($ch);
			CLog::add2log(array("event" => "MC", "text" => "Ошибка при обмене customRequest {$url}", "detail" => serialize(array("res" => $res, "info" => $info, "error" => curl_error($ch)))));

			if($attempt < 3){
				sleep(1);
				$this->customRequest($url, $attempt + 1);
			}
		}

		return $result;
	}

	public function deleteDemand($ms_id, $attempt = 0, $timeout = 600,){
		$start = debug_microtime_float();
		if(!$ms_id) return false;
		$url = "https://api.moysklad.ru/api/remap/1.2/entity/demand/{$ms_id}";
		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}

		$arLog = [
			"date" => date("Y-m-d H:i:s"),
			"url" => $url,
			"method" => "customRequest",
		];
		$this->add2log($arLog);

		$ch = curl_init($url);
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Accept: application/json;charset=utf-8",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);

		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
		//curl_setopt($ch, CURLOPT_INTERFACE, "93.125.48.252");
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_POST, 0);

		$res = curl_exec($ch);

		$result = gzdecode($res);

		$arLog = [
			"date" => date("Y-m-d H:i:s"),
			"url" => $url,
			"method" => "customRequest",
			"time" => debug_microtime_float() - $start,
			//"response" => $result,
		];
		$this->add2log($arLog);

		$result = json_decode($result, true);
		if(!$result || $result["errors"]){
			$info = curl_getinfo($ch);
			CLog::add2log(array("event" => "MC", "text" => "Ошибка при обмене customRequest {$url}", "detail" => serialize(array("res" => $res, "info" => $info, "error" => curl_error($ch)))));

			if($attempt < 3){
				sleep(1);
				$this->customRequest($url, $attempt + 1);
			}
		}

		return $result;
	}

	/**
	 * @param string $action
	 * @param string $method
	 * @param array  $data
	 * @param array  $header
	 *
	 * @return bool|string
	 * @throws \Exception
	 */
	public function send($action, $method = 'GET', $data = [], $header = [], $all = false, $getParams = array(), $items = []){

		if(!$this->access_token) {$this->LAST_ERROR = "Invalid access token"; return false;}

		$headers = array(
			'AUTHORIZATION: Bearer ' . $this->access_token,
			'Accept-Encoding: gzip'
		);
		foreach ( $header as $key => $value )
		{
			$headers[] = $key . ': ' . $value;
		}

		if(strripos($action, "https") !== false){
			$url = $action;
		}else{
			$url = $this->api_url . $action . ($getParams ? "?" . http_build_query($getParams) : "");
		}

		$ch = curl_init( $url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, TRUE );
		curl_setopt( $ch, CURLOPT_USERAGENT, __CLASS__ );
		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $method );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );

		if ( $method === 'POST' || $method === 'PUT' )
		{
			if($data)
			$data = json_encode( $data );
			curl_setopt( $ch, CURLOPT_POST, TRUE );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
		}

		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, TRUE );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 600 );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 600 );

		$res = curl_exec($ch);

		$result = gzdecode($res);
		// var_dump($result);
		if ( $action == '/entity/supply' ){
			/*file_put_contents(
				'/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/general/sendSupplyCheckGZ.txt',
				print_r($res, true),
				FILE_APPEND
			);
			file_put_contents(
				'/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/general/sendSupplyCheckJSON.txt',
				print_r($result, true),
				FILE_APPEND
			);*/
		}

		$result = json_decode($result, true);

		$info = curl_getinfo($ch);
		$this->LAST_STATUS_CODE = $info["http_code"];
		if ( (is_null( $result ) || $result["errors"]) && $info["http_code"] != 200 )
		{
			$arLog = [
				"action" => $action,
				"method" => $method,
				"data" => $data,
				"header" => $header,
				"all" => $all,
				"res" => $res,
				"result" => $result,
				"info" => $info,
			];

			CLog::add2log(array("event" => "MC", "text" => "Ошибка при обмене send", "detail" => serialize($arLog)));
			//prent($result);
			return false;
			//throw new \Exception(serialize($result["errors"]));
		}

		//prent($result);
		/*$last_id = 0;
		if(count($result["data"]) > 0 && $result["data"][0]){
			foreach($result["data"] as $k => $arItem){
				$this->arItem[] = $arItem;
				$last_id = $arItem["id"];
			}
		}elseif($result["data"]){
			$this->arItem = $result["data"];
		}*/

		if($all === true){
			/*if(!$items){
			//	$items = $result["rows"];
			}else{

			}*/
			$items = array_merge($items, $result["rows"]);

			if(($result["meta"]["limit"] + $result["meta"]["offset"]) < $result["meta"]["size"]){
				$getParams["limit"] = $result["meta"]["limit"];
				$getParams["offset"] = $result["meta"]["offset"] + $result["meta"]["limit"];

				sleep(1);
				return $this->send($action, $method, $data, $header, $all, $getParams, $items);

			}else{
				return $items;
			}

			//prent($items); die;

		}else{
			return $result;
		}

		//return $result["rows"];
	}

	public function getHeaders(){
		return array(
			'AUTHORIZATION: Bearer ' . $this->access_token,
			'Accept-Encoding: gzip'
		);
	}

	public function multi_send($actions = []){
		$multi = curl_multi_init();
		$channels = array();

		foreach($actions as $arItem){

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $this->api_url . $arItem["URL"]);

			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE );
			curl_setopt($ch, CURLOPT_USERAGENT, __CLASS__ );
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $arItem["METHOD"]);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $arItem["HEADERS"]);

			curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
			curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );
			curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, TRUE );
			curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 600 );
			curl_setopt( $ch, CURLOPT_TIMEOUT, 600 );

			curl_multi_add_handle($multi, $ch);

			$channels[$arItem["UNIQUE_ID"]] = $ch;
		}

		$active = null;
		do {
			$mrc = curl_multi_exec($multi, $active);
		} while ($mrc == CURLM_CALL_MULTI_PERFORM);

		while ($active && $mrc == CURLM_OK) {
			if (curl_multi_select($multi) == -1) {
				continue;
			}

			do {
				//$info = curl_multi_info_read( $multi );
				//prent($info);
				$mrc = curl_multi_exec($multi, $active);
				//$result = gzdecode($mrc);

				//$result = json_decode($result, true);
				//prent($result);
			} while ($mrc == CURLM_CALL_MULTI_PERFORM);
		}

		$arRes = [];
		foreach ($channels as $uniq_id => $channel) {
			$info = curl_getinfo($channel);
			//prent($info);
			$res = curl_multi_getcontent($channel);
			$result = gzdecode($res);

			$result = json_decode($result, true);
			//prent($result);
			$arRes[$uniq_id] = [
				"info" => $info,
				"data" => $result,
			];
			curl_multi_remove_handle($multi, $channel);
		}

		curl_multi_close($multi);

		return $arRes;


		$running = null;
		$mh = curl_multi_init();

		$arCh = [];
		foreach($actions as $cabinet_id => $arData){

			foreach($arData["REQUESTS"] as $arItem){
				$md5 = md5($cabinet_id.$arItem["URL"]);
				$arCh[$md5] = curl_init();
				curl_setopt($arCh[$md5], CURLOPT_URL, $arItem["URL"]);
				curl_setopt($arCh[$md5], CURLOPT_RETURNTRANSFER, TRUE );
				curl_setopt($arCh[$md5], CURLOPT_USERAGENT, __CLASS__ );
				curl_setopt($arCh[$md5], CURLOPT_CUSTOMREQUEST, $arItem["METHOD"]);
				curl_setopt($arCh[$md5], CURLOPT_HTTPHEADER, $arData["HEADERS"]);

				curl_multi_add_handle($mh, $arCh[$md5]);
			}

		}
		prent($mh);
		/*do {
		   curl_multi_exec($mh, $running);
		   curl_multi_select($mh);
		} while ($running > 0);*/
		$prev_running = $running = null;
		do {
			curl_multi_exec( $mh, $running );

			if ( $running !== $prev_running ) {
				// получаю информацию о текущих соединениях
				prent($prev_running);
				$info = curl_multi_info_read( $mh );
				prent($info);

				if ( is_array( $info ) && ( $ch = $info['handle'] ) ) {

					// получаю содержимое загруженной страницы
					$content = curl_multi_getcontent( $ch );
					prent($content);
					// тут какая-то обработка текста страницы

					// пока пусть будет как и в оригинале - вывод в STDOUT
					echo $content;
				}


				// обновляю кешируемое число текущих активных соединений
				$prev_running = $running;
			}

		} while ( $running > 0 );

		/*$arRes = [];
		foreach(array_keys($arCh) as $ch){
			$arRes[$ch] = curl_multi_getcontent($arCh[$ch]);
			prent($arRes[$ch]);
			curl_multi_remove_handle($mh, $arCh[$ch]);
		}

		prent($arRes);*/
		return;
		$r2 = curl_multi_getcontent($ch2);
		curl_multi_remove_handle($mh, $ch1);
		curl_multi_remove_handle($mh, $ch2);
		curl_multi_close($mh);


		if(!$this->access_token) {$this->LAST_ERROR = "Invalid access token"; return false;}

		$headers = array(
			'AUTHORIZATION: Bearer ' . $this->access_token,
			'Accept-Encoding: gzip'
		);
		foreach ( $header as $key => $value )
		{
			$headers[] = $key . ': ' . $value;
		}

		if(strripos($action, "https") !== false){
			$url = $action;
		}else{
			$url = $this->api_url . $action . ($getParams ? "?" . http_build_query($getParams) : "");
		}

		$ch = curl_init( $url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, TRUE );
		curl_setopt( $ch, CURLOPT_USERAGENT, __CLASS__ );
		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $method );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );

		if ( $method === 'POST' || $method === 'PUT' )
		{
			if($data)
			$data = json_encode( $data );
			curl_setopt( $ch, CURLOPT_POST, TRUE );
			curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
		}

		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, TRUE );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 600 );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 600 );

		$res = curl_exec($ch);

		$result = gzdecode($res);

		$result = json_decode($result, true);

		$info = curl_getinfo($ch);
		$this->LAST_STATUS_CODE = $info["http_code"];
		if ( (is_null( $result ) || $result["errors"]) && $info["http_code"] != 200 )
		{

			$arLog = [
				"action" => $action,
				"method" => $method,
				"data" => $data,
				"header" => $header,
				"all" => $all,
				"res" => $res,
				"result" => $result,
				"info" => $info,
			];

			CLog::add2log(array("event" => "MC", "text" => "Ошибка при обмене send", "detail" => serialize($arLog)));
			//prent($result);
			return false;
			//throw new \Exception(serialize($result["errors"]));
		}

		//prent($result);
		/*$last_id = 0;
		if(count($result["data"]) > 0 && $result["data"][0]){
			foreach($result["data"] as $k => $arItem){
				$this->arItem[] = $arItem;
				$last_id = $arItem["id"];
			}
		}elseif($result["data"]){
			$this->arItem = $result["data"];
		}*/

		if($all === true){
			/*if(!$items){
			//	$items = $result["rows"];
			}else{

			}*/
			$items = array_merge($items, $result["rows"]);

			if(($result["meta"]["limit"] + $result["meta"]["offset"]) < $result["meta"]["size"]){
				$getParams["limit"] = $result["meta"]["limit"];
				$getParams["offset"] = $result["meta"]["offset"] + $result["meta"]["limit"];

				sleep(1);
				return $this->send($action, $method, $data, $header, $all, $getParams, $items);

			}else{
				return $items;
			}

			//prent($items); die;

		}else{
			return $result;
		}

		//return $result["rows"];
	}

	function add2log($log){
		if(is_array($log)){
			global $USER;
			if($USER){
				$log["USER_ID"] = $USER->getID();
			}

			if(!$log["SCRIPT_FILENAME"]){
				$log["SCRIPT_FILENAME"] = $_SERVER["SCRIPT_FILENAME"];
			}
		}
		$file_log = "/home/bitrix/logs/ms/MoyskladAPI/request_" . date("Y-m-d") . ".txt";
		file_put_contents($file_log, print_r($log, true) . "\r\n", FILE_APPEND | LOCK_EX);
	}

	//edit

	function getProducts() {
		global $DB;
		$this->db = $DB;
		$strSql = "SELECT * FROM ci_ms_directory_products WHERE site_id = '$this->site_id'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$splitGroup = explode("/", $row['group']);
			$arRes[] = [
					'product_id' => $row['product_id'],
					'product_name' => $row['product_name'],
					'product_collection' => $splitGroup[2],
					'product_brand' => $splitGroup[1],
					'country' => $row['country'],
					'supplier' => $row['supplier'],
					'item_number' => $row['item_number'],
					'code' => $row['code'],
					'vat' => $row['vat'],
					'external_code' => $row['external_code'],
					'EAN8' => $row['EAN8'],
					'EAN13' => $row['EAN13'],
					'Code128' => $row['Code128'],
					'GTIN' => $row['GTIN'],
					'UPC' => $row['UPC'],
				];
		}
		return $arRes;
	}

	function getCustomers() {
		global $DB;
		$this->db = $DB;
		$strSql = "SELECT * FROM ci_ms_directory_customers WHERE site_id = '$this->site_id'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arRes[] = [
					'customer_id' => $row['customer_id'],
					'customer_name' => $row['customer_name'],
					'group' => $row['group'],
					'adress' => $row['adress'],
					'code' => $row['code'],
					'external_code' => $row['external_code'],
					'type' => $row['type'],
					'TIN' => $row['TIN'],
				];
		}
		return $arRes;
	}


 	function getListDemandPur() {
		$demandsMS = self::getAllDemand();
		$demandsBD = self::getListDemandBd();
		foreach ($demandsBD as $key => $d) {
			if (!$demandsMS[$key]) {
				self::deleteFromListDemandBd($key);
				//print_r('deleted: '. $key . '' .PHP_EOL);
			}
		}
		$pos = array();
		$rDemands  = array();
		foreach ($demandsMS as $key => $demand) {
			if (!$demandsBD[$demand['demand_id']]) {
				//$agent = self::getAgentDemand($demand['agent']);
				$agent = $demand['agent'];
				$rDemands[] = [
						'demand_id' => $demand['demand_id'],
						'date' => $demand['date'],
						'customer_id' => $agent,
					  'warehouse_id' => $demand['warehouse_id']
				];

				$posTmp = self::getDemandPositions($demand['demand_id']);
				if (is_array($posTmp))
					$pos = array_merge($pos,$posTmp);

				$arDemand = [
				  "demand_id" => $demand['demand_id'],
				  "date" => $demand['date'],
				  "sets" => ['customer_id' => $agent, 'pos' => $posTmp, 'warehouse_id' => $demand['warehouse_id']],
					"update" => $demand['update']
				];
				self::addToListDemandBd($arDemand);
				unset($arDemand);
				unset($agent);
				unset($posTmp);
			} else {
				if ($demandsBD[$demand['demand_id']]['update'] != $demand['update']) {
							//$agent = self::getAgentDemand($demand['agent']);
							$agent = $demand['agent'];
							$rDemands[] = [
									'demand_id' => $demand['demand_id'],
									'date' => $demand['date'],
									'customer_id' => $agent,
							    'warehouse_id' => $demand['warehouse_id']
							];

							$posTmp = self::getDemandPositions($demand['demand_id']);
							if (is_array($posTmp))
								$pos = array_merge($pos,$posTmp);

								$arDemand = [
								  "demand_id" => $demand['demand_id'],
								  "date" => $demand['date'],
								  "sets" => ['customer_id' => $agent, 'pos' => $posTmp, 'warehouse_id' => $demand['warehouse_id']],
									"update" => $demand['update']
								];

							self::updateListDemandBd($arDemand);
							//print_r('CHANGE: '.$demand['demand_id']);
							unset($arDemand);
							unset($agent);
							unset($posTmp);
				}else {
						$posTmp = $demandsBD[$demand['demand_id']]['sets']['pos'];
						if (is_array($posTmp))
							$pos = array_merge($pos,$posTmp);
						$rDemands[] = [
								'demand_id' => $demand['demand_id'],
								'date' => $demand['date'],
								'customer_id' => $demandsBD[$demand['demand_id']]['sets']['customer_id'],
							  'warehouse_id' => $demand['warehouse_id']
						];
				}
			}
		}

		$arResult = ['demands' => $rDemands, 'pos' => $pos];
		return $arResult;
	}

	function GetCommissionReport($expand = false, $offset = 0){
		$access = $this->access_token;
		$date = strval(trim($date));

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}
		$limit = 100;
		$urlParams = '';
		if( $expand != false){
			$urlParams = '?expand=' . $expand . '&' . 'limit=' . $limit . '&offset=' . $offset;
		}

		$ch = curl_init($this->api_url . "/entity/commissionreportin" . $urlParams);
		//
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Accept: application/json;charset=utf-8",
				"Accept-Encoding: gzip",
				"Authorization: Bearer $access"
			)
		);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		$res = curl_exec($ch);
		$result = gzdecode($res);
		// return $result;

		$result_arr = json_decode($result, true);
		curl_close($ch);
		// if ( !empty($result_arr['rows']) && count($result_arr) == 100 ){
		// 	$result_arr = array_merge( $result_arr, $this->GetCommissionReport($expand, $offset + 100) );
		// }
		// foreach ($result["rows"] as $key => $value) {
		// 	$sku_id = explode('https://api.moysklad.ru/api/remap/1.2/entity/product/',$value['meta']['href']);
		// 	$sku_id = explode('?expand=supplier',$sku_id[1]);
		// 	$dateP = explode('+',$date);
		// 	$arResult[]=[
		// 		'date' => $dateP[0],
		// 		'product_id' => $sku_id[0],
		// 		'quantity' => $value['stock'],
		// 		'COGS' => $value['price'] / 100
		// 	];
		// }
		// if (!is_array($result_arr) || $result_arr= = null){
		//
		// }
		// return $result_arr["rows"] ?? [];
		return $result_arr['rows'];
	}

	function getStocksCount($date){
		$access = $this->access_token;
		$date = strval(trim($date));
		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}
		$arResult = array();
		$filter = '';
		if ($this->site_id == 'msk') {
			$tmpRes = array();
			$sklads = ['83c00532-0f74-11ee-0a80-143a0014a102','2bcc228b-173a-11ee-0a80-0fdd000ddd83','97706d75-5b6f-11ee-0a80-14cc002bb00d','e7c0d649-55ef-11ee-0a80-1186002ba09f'];
			foreach ($sklads as $key => $value) {
					// code...
				$filter='store=https://api.moysklad.ru/api/remap/1.2/entity/store/2bcc228b-173a-11ee-0a80-0fdd000ddd83';
				$ch = curl_init($this->api_url . "/report/stock/all?filter=stockMode=nonEmpty;quantityMode=all;moment={$date}+23:59:59;store=https://api.moysklad.ru/api/remap/1.2/entity/store/{$value};");
				//
				curl_setopt(
					$ch,
					CURLOPT_HTTPHEADER,
					array(
						"Accept: application/json;charset=utf-8",
						'Accept-Encoding: gzip',
						"Authorization: Bearer $access"
					)
				);

				curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
				$res = curl_exec($ch);
				$result = gzdecode($res);

$result = json_decode($result, true);
				curl_close($ch);

				foreach ($result["rows"] as $k => &$v) {
					$v['WH_ID'] = $value;
					$arResult[] = $v;
				}
			}
		} else if ($this->site_id == 's1_opt') {
			$tmpRes = array();
			$sklads = ['adaf74a2-4d5b-11ed-0a80-0d1b0025794a'];
			foreach ($sklads as $key => $value) {
					// code...
				$filter='store=https://api.moysklad.ru/api/remap/1.2/entity/store/2bcc228b-173a-11ee-0a80-0fdd000ddd83';
				$ch = curl_init($this->api_url . "/report/stock/all?filter=stockMode=nonEmpty;quantityMode=all;moment={$date}+23:59:59;store=https://api.moysklad.ru/api/remap/1.2/entity/store/{$value};");
				//
				curl_setopt(
					$ch,
					CURLOPT_HTTPHEADER,
					array(
						"Accept: application/json;charset=utf-8",
						'Accept-Encoding: gzip',
						"Authorization: Bearer $access"
					)
				);

				curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
				$res = curl_exec($ch);
				$result = gzdecode($res);

$result = json_decode($result, true);
				curl_close($ch);

				foreach ($result["rows"] as $k => &$v) {
					$v['WH_ID'] = $value;
					$arResult[] = $v;
				}
			}

		} else {
			$ch = curl_init($this->api_url . "/report/stock/all?filter=moment={$date}+23:59:59");
			//
			curl_setopt(
				$ch,
				CURLOPT_HTTPHEADER,
				array(
					"Accept: application/json;charset=utf-8",
					'Accept-Encoding: gzip',
					"Authorization: Bearer $access"
				)
			);

			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			$res = curl_exec($ch);
			$result = gzdecode($res);

			$result = json_decode($result, true);
			curl_close($ch);
			$arResult = $result["rows"];
			// foreach ($result["rows"] as $key => $value) {
			// 	$sku_id = explode('https://api.moysklad.ru/api/remap/1.2/entity/product/',$value['meta']['href']);
			// 	$sku_id = explode('?expand=supplier',$sku_id[1]);
			// 	$dateP = explode('+',$date);
			// 	$arResult[]=[
			// 		'date' => $dateP[0],
			// 		'product_id' => $sku_id[0],
			// 		'quantity' => $value['stock'],
			// 		'COGS' => $value['price'] / 100
			// 	];
			// }
		}
		return $arResult;
	}

	function getWarehouses(){
		$access = $this->access_token;
		$date = strval(trim($date));

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}

		$ch = curl_init($this->api_url . "/entity/store");
		//
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Accept: application/json;charset=utf-8",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		$res = curl_exec($ch);
		$result = gzdecode($res);

$result = json_decode($result, true);
		curl_close($ch);
		// foreach ($result["rows"] as $key => $value) {
		// 	$sku_id = explode('https://api.moysklad.ru/api/remap/1.2/entity/product/',$value['meta']['href']);
		// 	$sku_id = explode('?expand=supplier',$sku_id[1]);
		// 	$dateP = explode('+',$date);
		// 	$arResult[]=[
		// 		'date' => $dateP[0],
		// 		'product_id' => $sku_id[0],
		// 		'quantity' => $value['stock'],
		// 		'COGS' => $value['price'] / 100
		// 	];
		// }
		return $result["rows"];
	}

	function getSupply(){
		$access = $this->access_token;
		$date = strval(trim($date));

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}

		$ch = curl_init($this->api_url . "/entity/supply");
		//
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Accept: application/json;charset=utf-8",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		$res = curl_exec($ch);
		$result = gzdecode($res);

$result = json_decode($result, true);
		curl_close($ch);
		// foreach ($result["rows"] as $key => $value) {
		// 	$sku_id = explode('https://api.moysklad.ru/api/remap/1.2/entity/product/',$value['meta']['href']);
		// 	$sku_id = explode('?expand=supplier',$sku_id[1]);
		// 	$dateP = explode('+',$date);
		// 	$arResult[]=[
		// 		'date' => $dateP[0],
		// 		'product_id' => $sku_id[0],
		// 		'quantity' => $value['stock'],
		// 		'COGS' => $value['price'] / 100
		// 	];
		// }
		return $result["rows"];
	}

	function getDemandSolo($id){
		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}

		$ch = curl_init($this->api_url . "/entity/demand/" . $id);

		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Accept: application/json;charset=utf-8",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		$res = curl_exec($ch);
		$result = gzdecode($res);

		$result = json_decode($result, true);
		curl_close($ch);
		// foreach($result["rows"] as $k => $v){
		// 		$arRes[] = $v;
		// }
		return $result;
	}


	function ChangeMoment($id,$moment){
		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}

		$contract = array(
			'moment' => $moment
		);
		$data = json_encode($contract);
		$ch = curl_init($this->api_url . "/entity/demand/" . $id);
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Content-Type: application/json",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		//curl_setopt($ch, CURLOPT_INTERFACE, "93.125.48.252");

		$res = curl_exec($ch);

		$result = gzdecode($res);

		//$result = json_decode($result, true);

		curl_close($ch);
		// foreach($result["rows"] as $k => $v){
		// 		$arRes[] = $v;
		// }
		return $result;
	}

	function ChangeContract($id){
		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}

		$contract = array(
			'contract' => array( 'meta' => array(
				'href' => 'https://api.moysklad.ru/api/remap/1.2/entity/contract/319a1c55-063c-11ee-0a80-00350013e793',
				'metadataHref' => 'https://api.moysklad.ru/api/remap/1.2/entity/contract/metadata',
				'type' => 'contract'
				)
			)
		);
		$data = json_encode($contract);
		$ch = curl_init($this->api_url . "/entity/demand/" . $id);
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Content-Type: application/json",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		//curl_setopt($ch, CURLOPT_INTERFACE, "93.125.48.252");

		$res = curl_exec($ch);

		$result = gzdecode($res);

		$result = json_decode($result, true);

		curl_close($ch);
		// foreach($result["rows"] as $k => $v){
		// 		$arRes[] = $v;
		// }
		return $result;
	}

	function ChangeCounteragent($id, $agent){
		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}

		$arAgent = array(
			'agent' => array( 'meta' => array(
				'href' => 'https://api.moysklad.ru/api/remap/1.2/entity/counterparty/'.$agent,
				'type' => 'counterparty',
				'metadataType' => 'application/json',
				)
			)
		);
		$data = json_encode($arAgent);
		$ch = curl_init($this->api_url . "/entity/demand/" . $id);
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Content-Type: application/json",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		//curl_setopt($ch, CURLOPT_INTERFACE, "93.125.48.252");

		$res = curl_exec($ch);

		$result = gzdecode($res);

		$result = json_decode($result, true);

		curl_close($ch);
		// foreach($result["rows"] as $k => $v){
		// 		$arRes[] = $v;
		// }
		return $result;
	}

	function createOrder($orderdata){
		$access = $this->access_token;

		if(!$access) {return "Invalid access token";}

		$ch = curl_init($this->api_url . "/entity/customerorder/");


		$data = json_encode($orderdata);

		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Content-Type: application/json",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

		$res = curl_exec($ch);

		// file_put_contents(
		// 	'/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/general/createOrder.log',
		// 	print_r($orderData, true) . PHP_EOL,
		// 	FILE_APPEND
		// );
		// file_put_contents(
		// 	'/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/general/createOrder.log',
		// 	print_r($res, true) . PHP_EOL,
		// 	FILE_APPEND
		// );

		$result = gzdecode($res);

		$result = json_decode($result, true);

		curl_close($ch);
		return $result;
	}

	function updateOrder($orderdata,$id){
		$access = $this->access_token;

		if(!$access) {return "Invalid access token";}

		$ch = curl_init($this->api_url . "/entity/customerorder/{$id}");


		$data = json_encode($orderdata);

		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Content-Type: application/json",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

		$res = curl_exec($ch);


		$result = gzdecode($res);

		$result = json_decode($result, true);

		curl_close($ch);
		return $result;
	}

	function getCustomOrder($id){
		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}

		$ch = curl_init($this->api_url . "/entity/customerorder/" . $id);

		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Accept: application/json;charset=utf-8",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		$res = curl_exec($ch);
		$result = gzdecode($res);

		$result = json_decode($result, true);
		curl_close($ch);

		return $result['demands'];
	}

	function getOrderPosition($id){
		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}

		$ch = curl_init($this->api_url . "/entity/customerorder/" . $id ."/positions");

		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Accept: application/json;charset=utf-8",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		$res = curl_exec($ch);
		$result = gzdecode($res);

		$result = json_decode($result, true);
		curl_close($ch);

		return $result;
	}

	function getAllDemand(){
		$access = $this->access_token;
		$arDemand = array();

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}

		if ($this->site_id == 'msk') {
			$sklads = ['83c00532-0f74-11ee-0a80-143a0014a102','2bcc228b-173a-11ee-0a80-0fdd000ddd83','97706d75-5b6f-11ee-0a80-14cc002bb00d','e7c0d649-55ef-11ee-0a80-1186002ba09f'];

			foreach ($sklads as $key => $value) {
 			  $start = true;
 				$i = 0;
 				while ($start == true) {
 					$ch = curl_init($this->api_url . "/entity/demand?filter=store=https://api.moysklad.ru/api/remap/1.2/entity/store/{$value}&offset={$i}");
 					curl_setopt(
 						$ch,
 						CURLOPT_HTTPHEADER,
 						array(
 							"Accept: application/json;charset=utf-8",
							'Accept-Encoding: gzip',
 							"Authorization: Bearer $access"
 						)
 					);

 					curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
 					$res = curl_exec($ch);
 					$result = gzdecode($res);

					$result = json_decode($result, true);
 					curl_close($ch);
 					if (count($result["rows"]) > 0) {
 						foreach($result["rows"] as $k => $v){
 						 if ($v['applicable'] == '1') {
 							 $agent_id = explode('https://api.moysklad.ru/api/remap/1.2/entity/counterparty/',$v['agent']['meta']['href']);
 							 $arDemand[$v['id']] = [
 								 'demand_id' => $v['id'],
 								 'date' => $v['moment'],
 								 'agent' => $agent_id[1],
 								 'update' => $v['updated'],
 								 'warehouse_id' => $value,
 							 ];
 						 }
 						}
 						$i = $i + 1000;
 					} else {
 						$start = false;
 					}
 				}
 			}
 			$dates = array_column($arDemand, 'date');
 			array_multisort($dates, SORT_DESC, $arDemand);
	 } else if ($this->site_id == 's1_opt') {
		 $sklads = ['adaf74a2-4d5b-11ed-0a80-0d1b0025794a'];
		 foreach ($sklads as $key => $value) {
			  $start = true;
				$i = 0;
				while ($start == true) {
					$ch = curl_init($this->api_url . "/entity/demand?filter=store=https://api.moysklad.ru/api/remap/1.2/entity/store/{$value}&offset={$i}");
					curl_setopt(
						$ch,
						CURLOPT_HTTPHEADER,
						array(
							"Accept: application/json;charset=utf-8",
							'Accept-Encoding: gzip',
							"Authorization: Bearer $access"
						)
					);

					curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
					$res = curl_exec($ch);
					$result = gzdecode($res);

					$result = json_decode($result, true);
					curl_close($ch);
					if (count($result["rows"]) > 0) {
						foreach($result["rows"] as $k => $v){
						 if ($v['applicable'] == '1') {
							 $agent_id = explode('https://api.moysklad.ru/api/remap/1.2/entity/counterparty/',$v['agent']['meta']['href']);
							 $arDemand[$v['id']] = [
								 'demand_id' => $v['id'],
								 'date' => $v['moment'],
								 'agent' => $agent_id[1],
								 'update' => $v['updated'],
								 'warehouse_id' => $value,
							 ];
						 }
						}
						$i = $i + 1000;
					} else {
						$start = false;
					}
				}
			}
			$dates = array_column($arDemand, 'date');
			array_multisort($dates, SORT_DESC, $arDemand);
		} else {
			$ch = curl_init($this->api_url . "/entity/demand");

			curl_setopt(
				$ch,
				CURLOPT_HTTPHEADER,
				array(
					"Accept: application/json;charset=utf-8",
					'Accept-Encoding: gzip',
					"Authorization: Bearer $access"
				)
			);

			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			$res = curl_exec($ch);
			$result = gzdecode($res);

			$result = json_decode($result, true);
			curl_close($ch);
			foreach($result["rows"] as $k => $v){
				if ($v['applicable'] == '1') {
					$agent_id = explode('https://api.moysklad.ru/api/remap/1.2/entity/counterparty/',$v['agent']['meta']['href']);
					$arDemand[$v['id']] = [
						'demand_id' => $v['id'],
						'date' => $v['moment'],
						'agent' => $agent_id[1],
						'update' => $v['updated'],
					];
				}
			}
			$dates = array_column($arDemand, 'date');
			array_multisort($dates, SORT_DESC, $arDemand);

			}


		return $arDemand;
	}

	function getAgentDemand($url){
		$access = $this->access_token;
		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}

		$ch = curl_init($url);
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Accept: application/json;charset=utf-8",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		$res = curl_exec($ch);
		$result = gzdecode($res);

		$result = json_decode($result, true);
		curl_close($ch);

		$arAgent = ['name' => $result['name'], 'adress' => $result['legalAddress']];

		return $arAgent;
	}

	function getDemandPositions($id){
		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}

		$ch = curl_init($this->api_url . "/entity/demand/" . $id . "/positions");

		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			array(
				"Accept: application/json;charset=utf-8",
				'Accept-Encoding: gzip',
				"Authorization: Bearer $access"
			)
		);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		$res = curl_exec($ch);
		$result = gzdecode($res);

		$result = json_decode($result, true);
		curl_close($ch);

		foreach($result["rows"] as $k => $v){
			$sku_id = explode('https://api.moysklad.ru/api/remap/1.2/entity/product/',$v['assortment']['meta']['href']);
			if (!isset($sku_id[1])){
				$sku_id = explode('https://api.moysklad.ru/api/remap/1.2/entity/service/',$v['assortment']['meta']['href']);
			}
			$arPos[] = [
				// 'name' => $name,
				'demand_id' => $id,
				//'sku_id' => self::getPositionNameDemand($v['assortment']['meta']['href']),
				'product_id' => $sku_id[1],
				'quantity' => $v['quantity'],
				'price' => intval($v['price']) / 100,
				'discount' => $v['discount'],
				'vat' => $v['vat']
			];

		}

		return $arPos;
	}

	function getTurnoverOld(){
		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}
		$momentTo = date("Y-m-d");
		$momentFrom = date("Y-m-d", strtotime("-3 month"));
		$offset = 0;
		$check = true;
		while ($check == true) {

			$ch = curl_init($this->api_url . "/report/turnover/all?offset={$offset}&limit=1000&momentFrom={$momentFrom}&momentTo={$momentTo}");

			curl_setopt(
				$ch,
				CURLOPT_HTTPHEADER,
				array(
					"Accept: application/json;charset=utf-8",
					'Accept-Encoding: gzip',
					"Authorization: Bearer $access"
				)
			);

			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			$res = curl_exec($ch);
			$result = gzdecode($res);
			curl_close($ch);
			$result = json_decode($result, true);
			if (empty($result['rows']) || count($result['rows']) < 1000) {
				$check = false;
			} else {
				$offset = $offset + 1000;
			}

			if (!empty($result['rows'])) {
				foreach ($result['rows'] as $key => $value) {
					$aRresult[] = $value;
				}
			}

		}

		return $aRresult;
	}


	function getTurnover($mouth){
		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}
		$momentTo = date("Y-m-d");
		$momentFrom = date("Y-m-d", strtotime("-".$mouth." month"));
		$offset = 0;
		$check = true;
		while ($check == true) {

			$ch = curl_init($this->api_url . "/report/turnover/all?offset={$offset}&limit=1000&momentFrom={$momentFrom}&momentTo={$momentTo}");

			curl_setopt(
				$ch,
				CURLOPT_HTTPHEADER,
				array(
					"Accept: application/json;charset=utf-8",
					'Accept-Encoding: gzip',
					"Authorization: Bearer $access"
				)
			);

			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			$res = curl_exec($ch);
			$result = gzdecode($res);
			curl_close($ch);
			$result = json_decode($result, true);
			if (empty($result['rows']) || count($result['rows']) < 1000) {
				$check = false;
			} else {
				$offset = $offset + 1000;
			}

			if (!empty($result['rows'])) {
				foreach ($result['rows'] as $key => $value) {
					$aRresult[] = $value;
				}
			}

		}

		return $aRresult;
	}

	function getTurnoverWeekWB($week){
		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}
		$momentTo = date("Y-m-d");
		$momentFrom = date("Y-m-d", strtotime("-".$week." week"));
		// print_r($momentTo);
		// print_r('###');
		// print_r($momentFrom);
		// die();
		$offset = 0;
		$check = true;
		while ($check == true) {

			$ch = curl_init($this->api_url . "/report/turnover/all?offset={$offset}&limit=1000&momentFrom={$momentFrom}&momentTo={$momentTo}&filter=type=demand&filter=store=https://api.moysklad.ru/api/remap/1.2/entity/store/79ed7d71-0aa6-11ea-0a80-004200039aa4&filter=store=https://api.moysklad.ru/api/remap/1.2/entity/store/51538bd5-6cf3-11ef-0a80-10ba001db77c&filter=agent=https://api.moysklad.ru/api/remap/1.2/entity/counterparty/dd5b00b5-2a6e-11ec-0a80-019e000baf07");

			curl_setopt(
				$ch,
				CURLOPT_HTTPHEADER,
				array(
					"Accept: application/json;charset=utf-8",
					'Accept-Encoding: gzip',
					"Authorization: Bearer $access"
				)
			);

			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			$res = curl_exec($ch);
			$result = gzdecode($res);

			curl_close($ch);
			$result = json_decode($result, true);
			if (empty($result['rows']) || count($result['rows']) < 1000) {
				$check = false;
			} else {
				$offset = $offset + 1000;
			}

			if (!empty($result['rows'])) {
				foreach ($result['rows'] as $key => $value) {
					$aRresult[] = $value;
				}
			}

		}

		return $aRresult;
	}

	function getTurnoverWeek($week, $filter = '&filter=store=https://api.moysklad.ru/api/remap/1.2/entity/store/79ed7d71-0aa6-11ea-0a80-004200039aa4'){
		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}
		$momentTo = rawurlencode(date("Y-m-d 23:59:59"));
		$momentFrom = rawurlencode(date("Y-m-d 00:00:00", strtotime("-".$week." week")));
		// print_r($momentTo);
		// print_r('###');
		// print_r($momentFrom);
		// die();
		$offset = 0;
		$check = true;
		while ($check == true) {

			$ch = curl_init($this->api_url . "/report/turnover/all?offset={$offset}&limit=1000&momentFrom={$momentFrom}&momentTo={$momentTo}&filter=type=supply{$filter}");

			curl_setopt(
				$ch,
				CURLOPT_HTTPHEADER,
				array(
					"Accept: application/json;charset=utf-8",
					'Accept-Encoding: gzip',
					"Authorization: Bearer $access"
				)
			);

			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			$res = curl_exec($ch);
			$result = gzdecode($res);
			curl_close($ch);
			$result = json_decode($result, true);
			if (empty($result['rows']) || count($result['rows']) < 1000) {
				$check = false;
			} else {
				$offset = $offset + 1000;
			}

			if (!empty($result['rows'])) {
				foreach ($result['rows'] as $key => $value) {
					$aRresult[] = $value;
				}
			}

		}

		return $aRresult;
	}

	function getTurnoverDay($day, $filter = '&filter=store=https://api.moysklad.ru/api/remap/1.2/entity/store/79ed7d71-0aa6-11ea-0a80-004200039aa4'){
		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}
		$momentTo = rawurlencode(date("Y-m-d 23:59:59"));
		$momentFrom = rawurlencode(date("Y-m-d 00:00:00", strtotime("-".$day." day")));
		// print_r($momentTo);
		// print_r('###');
		// print_r($momentFrom);
		// die();
		$offset = 0;
		$check = true;
		while ($check == true) {
			// var_dump($filter);
			$ch = curl_init($this->api_url . "/report/turnover/all?offset={$offset}&limit=1000&momentFrom={$momentFrom}&momentTo={$momentTo}&filter=type=supply{$filter}");

			curl_setopt(
				$ch,
				CURLOPT_HTTPHEADER,
				array(
					"Accept: application/json;charset=utf-8",
					'Accept-Encoding: gzip',
					"Authorization: Bearer $access"
				)
			);

			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			$res = curl_exec($ch);
			$result = gzdecode($res);
			curl_close($ch);
			$result = json_decode($result, true);
			// var_dump($result);
			if (empty($result['rows']) || count($result['rows']) < 1000) {
				$check = false;
			} else {
				$offset = $offset + 1000;
			}

			if (!empty($result['rows'])) {
				foreach ($result['rows'] as $key => $value) {
					$aRresult[] = $value;
				}
			}

		}

		return $aRresult;
	}

	function getTurnoverDayNew($dayFrom, $dayTo, $filter = '&filter=store=https://api.moysklad.ru/api/remap/1.2/entity/store/79ed7d71-0aa6-11ea-0a80-004200039aa4'){
		$access = $this->access_token;
		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}
		$momentTo = rawurlencode(date("Y-m-d 00:00:00", strtotime("-{$dayTo} day")));
		$momentFrom = rawurlencode(date("Y-m-d 00:00:00", strtotime("-{$dayFrom} day")));
		$offset = 0;
		$check = true;
		while ($check == true) {
			// var_dump($filter);
			$ch = curl_init($this->api_url . "/report/turnover/all?offset={$offset}&limit=1000&momentFrom={$momentFrom}&momentTo={$momentTo}&filter=type=supply{$filter}");

			curl_setopt(
				$ch,
				CURLOPT_HTTPHEADER,
				array(
					"Accept: application/json;charset=utf-8",
					'Accept-Encoding: gzip',
					"Authorization: Bearer $access"
				)
			);

			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			$res = curl_exec($ch);

			$result = gzdecode($res);
			curl_close($ch);
			$result = json_decode($result, true);
			// var_dump($result);
			if (empty($result['rows']) || count($result['rows']) < 1000) {
				$check = false;
			} else {
				$offset = $offset + 1000;
			}

			if (!empty($result['rows'])) {
				foreach ($result['rows'] as $key => $value) {
					$aRresult[] = $value;
				}
			}

		}

		return $aRresult;
	}

	function getTurnoverByFullPeriod($mouthFrom,$mouthTo){
		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}
		$momentTo = date("Y-m-d", strtotime("-".$mouthFrom." month"));
		$momentFrom = date("Y-m-d", strtotime("-".$mouthTo." month"));
		// $filter = '&filter=agent=https://api.moysklad.ru/api/remap/1.2/entity/counterparty/' . $agent;
		$offset = 0;
		$check = true;
		while ($check == true) {

			$ch = curl_init($this->api_url . "/report/turnover/all?offset={$offset}&limit=1000&momentFrom={$momentFrom}&momentTo={$momentTo}");

			curl_setopt(
				$ch,
				CURLOPT_HTTPHEADER,
				array(
					"Accept: application/json;charset=utf-8",
					'Accept-Encoding: gzip',
					"Authorization: Bearer $access"
				)
			);

			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			$res = curl_exec($ch);
			$result = gzdecode($res);
			curl_close($ch);
			$result = json_decode($result, true);
			if (empty($result['rows']) || count($result['rows']) < 1000) {
				$check = false;
			} else {
				$offset = $offset + 1000;
			}

			if (!empty($result['rows'])) {
				foreach ($result['rows'] as $key => $value) {
					$aRresult[] = $value;
				}
			}

		}

		return $aRresult;
	}

	function getTurnoverByProduct($mouth, $productId){
		$access = $this->access_token;

		if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}
		$momentTo = date("Y-m-d");
		$momentFrom = date("Y-m-d", strtotime("-".$mouth." month"));
		$offset = 0;
		$check = true;
		$filter = '&filter=product=https://api.moysklad.ru/api/remap/1.2/entity/product/' . $productId;
		while ($check == true) {

			$ch = curl_init($this->api_url . "/report/turnover/all?offset={$offset}&limit=1000&momentFrom={$momentFrom}&momentTo={$momentTo}" . $filter);

			curl_setopt(
				$ch,
				CURLOPT_HTTPHEADER,
				array(
					"Accept: application/json;charset=utf-8",
					'Accept-Encoding: gzip',
					"Authorization: Bearer $access"
				)
			);

			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			$res = curl_exec($ch);
			$result = gzdecode($res);
			curl_close($ch);
			$result = json_decode($result, true);
			if (empty($result['rows']) || count($result['rows']) < 1000) {
				$check = false;
			} else {
				$offset = $offset + 1000;
			}

			if (!empty($result['rows'])) {
				foreach ($result['rows'] as $key => $value) {
					$aRresult[] = $value;
				}
			}

		}

		return $aRresult;
	}
	// function getPositionNameDemand($url){
	// 	$access = $this->access_token;
	// 	if(!$access) {$this->LAST_ERROR = "Invalid access token"; return false;}
	//
	// 	$ch = curl_init($url);
	// 	curl_setopt(
	// 		$ch,
	// 		CURLOPT_HTTPHEADER,
	// 		array(
	// 			"Accept: application/json;charset=utf-8",
	// 			"Authorization: Bearer $access"
	// 		)
	// 	);
	// 	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	// 	$res = curl_exec($ch);
	// 	$result = gzdecode($res);

//$result = json_decode($result, true);
	// 	curl_close($ch);
	//
	// 	$name = $result['name'];
	//
	// 	return $name;
	// }

	function getListDemandBd() {
		global $DB;
		$this->db = $DB;
		$strSql = "SELECT * FROM ci_bq_demands";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arRes[$row['demand_id']] = [
					"date" => $row['date'],
					"sets" => unserialize($row['sets']),
					"update" => $row['update']
				];
		}
		return $arRes;
	}
	function addToListDemandBd($arDemand) {
		global $DB;
		$in = array(
			"demand_id" => "'".$arDemand['demand_id']."'",
			"date" => "'".$arDemand['date']."'",
			"sets" => "'".serialize($arDemand['sets'])."'",
			"update" => "'".$arDemand['update']."'"
		);
		$this->db->Insert("ci_bq_demands", $in, $err_mess.__LINE__);
	}
	function deleteFromListDemandBd($id) {
		global $DB;
		$this->db = $DB;
		$strSql = "DELETE FROM ci_bq_demands WHERE demand_id='".$id."'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	}
	function updateListDemandBd($arDemand) {
		global $DB;
		$in = array(
			"demand_id" => "'".$arDemand['demand_id']."'",
			"date" => "'".$arDemand['date']."'",
			"sets" => "'".serialize($arDemand['sets'])."'",
			"update" => "'".$arDemand['update']."'"
		);
		$this->db->Update("ci_bq_demands", $in, "WHERE demand_id ='".$arDemand['demand_id']."'", $err_mess.__LINE__);
	}


}
