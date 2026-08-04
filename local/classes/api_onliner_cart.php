<?php
/**
 * api onliner class
 */ 
class OnlinerCart_API
{
	private $access_token;
	private $api_url_auth = "https://b2bapi.onliner.by/oauth/token";
	private $api_url = "https://cart.api.onliner.by";
	
    private $https = FALSE; //API работает через https
	
	private $client_id = "";
	private $client_secret = "";
	/**
     * Конструктор инициализирует CURL и необходимые параметры
     *
     * @param <type> $_client_login
     * @param <type> $_client_password
     */
    public function  __construct(){
		$this->client_id = COption::GetOptionString("panel.manager", "ONLINER_CART_CLIENT_ID");
		$this->client_secret = COption::GetOptionString("panel.manager", "ONLINER_CART_CLIENT_SECRET");
		
		$_client_id = $this->client_id;
		$_client_secret = $this->client_secret;
        //инициализируем CURL
        $this->curl = curl_init();

		curl_setopt($this->curl, CURLOPT_URL, $this->api_url_auth);
		curl_setopt($this->curl, CURLOPT_HTTPHEADER, array('Accept: application/json'));
		curl_setopt($this->curl, CURLOPT_USERPWD, "{$_client_id}:{$_client_secret}");
		curl_setopt($this->curl, CURLOPT_POST, 1);
		curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($this->curl, CURLOPT_POSTFIELDS, array('grant_type' => 'client_credentials'));

		$result = curl_exec($this->curl);

		$obj_access = json_decode($result);
		$access = $obj_access->{'access_token'};
		//при успешной авторизации получаем код доступа сессии
		$this->access_token = $access;

	}

	/*
	Список заказов магазина
	page	integer	Номер страницы
	limit	integer	Лимит записей на странице, по умолчанию - 50, максимально - 100
	status	string	Строковый код статуса
	include	string	Дополнительные группы данных о заказе (такие же, как в методе получения информации о заказе)
	*/
	public function getListOrder($page = 1, $limit = 50, $status = "", $include = ""){
		$access = $this->access_token;
		
		$process = curl_init($this->api_url . "/orders?page={$page}&limit={$limit}&status={$status}&include={$include}");
		curl_setopt(
			$process, 
			CURLOPT_HTTPHEADER, 
			array(
				"Accept: application/json; charset=utf-8", 
				"Content-Type: application/json", 
				"Authorization: Bearer $access"
			)
		);
		//curl_setopt($process, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
		$result = curl_exec($process);
		return $result;
	}
	
	/*
	GET /orders/{orderKey}
	
	Получение информации о заказе
	GET /orders/{orderKey}
	Параметры запроса
	Параметр	Тип	Описание
	include	string	Дополнительные группы данных о заказе
	Допустимые группы с дополнительной информацией:
	shop - Краткая информация о магазине
	positions - Информация о позициях и товарах заказа
	status_change_log - Информация об изменениях статусов заказа

	*/
	public function getOrder($order_id = "", $include = ""){
		if(strlen($order_id) <= 0) return false;
		$access = $this->access_token;
		$process = curl_init($this->api_url . "/orders/{$order_id}?include={$include}");
		curl_setopt(
			$process, 
			CURLOPT_HTTPHEADER, 
			array(
				"Accept: application/json", 
				"Content-Type: application/json", 
				"Authorization: Bearer $access"
			)
		);
		//curl_setopt($process, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
		$result = curl_exec($process);
		return $result;
	}

	public function setOrderStatus($order_id, $_data){
		if(strlen($order_id) <= 0) return false;
		$access = $this->access_token;
		$process = curl_init($this->api_url . "/orders/{$order_id}");
		curl_setopt(
			$process, 
			CURLOPT_HTTPHEADER, 
			array(
				"Accept: application/json", 
				"Content-Type: application/json", 
				"Authorization: Bearer $access"
			)
		);

		curl_setopt($process, CURLOPT_CUSTOMREQUEST, 'PATCH');
		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($process, CURLOPT_POSTFIELDS, $_data);
		$result = curl_exec($process);
		return $result;
	}
	
	/*
	Получить список доступных причин для отмены заказа магазином
	GET /resources/shop-cancel-reasons
	*/
	public function getCancelReasonsApi(){
		$access = $this->access_token;
		
		$process = curl_init($this->api_url . "/resources/shop-cancel-reasons");
		curl_setopt(
			$process, 
			CURLOPT_HTTPHEADER, 
			array(
				"Accept: application/json; charset=utf-8", 
				"Content-Type: application/json", 
				"Authorization: Bearer $access"
			)
		);
		//curl_setopt($process, CURLOPT_CUSTOMREQUEST, 'PUT');
		curl_setopt($process, CURLOPT_RETURNTRANSFER, TRUE);
		$result = curl_exec($process);
		return $result;
	}
	
	public function getCancelReasons($reason){
	    $ar = array(
			"1" => array(
				"id" => 1,
				"comment" => "Товара нет в наличии",
			),
			"8" => array(
				"id" => 8,
				"comment" => "Заказ продублирован",
			),
			"2" => array(
				"id" => 2,
				"comment" => "Покупателя не устроила стоимость товара",
			),
			"3" => array(
				"id" => 3,
				"comment" => "Покупателя не устроил срок доставки",
			),
			"4" => array(
				"id" => 4,
				"comment" => "Покупателя не устроила стоимость доставки",
			),
			"5" => array(
				"id" => 5,
				"comment" => "Покупатель отказался от заказа",
			),
			"6" => array( 
				"id" => 6,
				"comment" => "Не удалось связаться с покупателем",
			),
			"7" => array(
				"id" => 7,
				"comment" => "Иное",
			),
		);
		return $ar[$reason];
	}

	function add2log($log){
		$file_log = "/userscripts/logs/onliner/cart_" . date("Y-m-d") . ".txt";
		file_put_contents($file_log, $log, FILE_APPEND | LOCK_EX);
	}

}