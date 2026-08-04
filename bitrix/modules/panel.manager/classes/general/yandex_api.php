<?php
/**
 * yandex API class
 */
class YandexApi
{
	public $arReviews = array();
//	private $access_token = "rUwYsmxyzzj5lZ9zxiH1SSf4n8pwc7";
	private $access_token = "rUwYsmxyzzj5lZ9zxiH1SSf4n8pwc7";
	private $auth_key = "e9c44c20-ac95-4c0e-b8e4-79e2eaa85d2e";
	public $arError = array();
	/**
     * Конструктор инициализирует необходимые параметры
     *
     */
    public function  __construct()
    {
		$this->arReviews = array();
	}

    /**
     * функция получает отзывы по id модели на яндексе
     * @sort - string. Тип сортировки отзывов. grade — сортировка по оценке пользователем модели;date — сортировка по дате написания отзыва;rank — сортировка по полезности отзыва.
	 * @how - string. Направление сортировки. desc — сортировка по убыванию; asc — сортировка по возрастанию.
	 * @grade - integer. Фильтрация отзывов по оценке пользователями модели. Возможные значения: 2,1,0,-1,-2.
	 * @max_comments - integer. Количество комментариев в выходных данных.
	 * @page - integer. Номер страницы результатов, которую необходимо показать в ответе.
	 * @count - integer. Количество выводимых результатов на странице ответа. Максимальное возможное значение: 30.
     */
    public function get_reviews_model($yandex_model_id, $sort = "date", $how = "desc", $max_comments = 0, $page = 1, $count = 30){
		$url = "https://api.content.market.yandex.ru/v1/model/{$yandex_model_id}/opinion.json?sort={$sort}&how={$how}&max_comments={$max_comments}&page={$page}&count={$count}";
		$headers = array(
			"Host: api.content.market.yandex.ru",
			"Accept: */*",
			"Authorization: {$this->auth_key}"
		);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		$result = curl_exec($ch);
//		prent($result);
		curl_close($ch);
		$result_arr = $this->json2AssocArray($result);
		if($result_arr["errors"])
			$this->arError = array_merge($this->arError, $result_arr["errors"]);
//		prent($result_arr);
		$total = $result_arr["modelOpinions"]["total"];
		$page = $result_arr["modelOpinions"]["page"];
//		prent($page);
		if(isset($result_arr["modelOpinions"]["opinion"]) && count($result_arr["modelOpinions"]["opinion"]) > 0){
			$this->arReviews = array_merge($this->arReviews, $result_arr["modelOpinions"]["opinion"]);
		}

		if($total > $page * $count){
			$page++;
			$this->get_reviews_model($yandex_model_id, $sort, $how, $max_comments, $page, $count);
		}
		return $this->arReviews;
	}
//	public static function getError(){
//		return self::$arError;
//	}
	/**
     * функция получает список регионов
     */
    public function get_region(){
		$url = "https://api.partner.market.yandex.ru/v2/models.json";
		$headers = array(
			"Host: api.partner.market.yandex.ru",
			"Accept: */*",
		//	"Authorization: {$this->auth_key}"
		);
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		$result = curl_exec($ch);
//		prent($result);
		curl_close($ch);
		$result_arr = $this->json2AssocArray($result);
		
		return $result_arr;
	}
	public function json2AssocArray($json = ""){
		return json_decode($json, true);
	}
	
	
}

