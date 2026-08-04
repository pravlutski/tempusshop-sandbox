<?php

use JetBrains\PhpStorm\NoReturn;

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

class ProfileCreator {
    private $article;
    private $markup;
    private $price_id;

	/**
	 * @param $article
	 * @param $markup
	 * @param $price_id
	 */
    public function __construct($article, $markup, $price_id) {
        $this->article = $article;
        $this->markup = $markup;
        $this->price_id = $price_id;
    }

    /**
     * @return void
     */
    public function createProfile(): void
    {
        global $DB;

        $strSql = "INSERT INTO `ci_custom_analiz_price` (`article`, `markup`, `price_id`, `created_at`, `updated_at`)
                   VALUES ('" . $DB->ForSql($this->article) . "', '" . $DB->ForSql($this->markup) . "', '" . $DB->ForSql($this->price_id) . "', now(), now())";
        $result = $DB->Query($strSql);
        if ($result !== false) {
            echo "Тег успешно создан";
            header("Location: https://tempusshop.ru/admin/modules/custom_analiz_price/");
            exit();
        } else {
            echo "Ошибка при создании тега";
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $article = $_POST["article"];
    $markup = $_POST["markup"];
    $price_id = $_POST["price_id"];

    $profileCreator = new ProfileCreator($article, $markup, $price_id);
    $profileCreator->createProfile();
} else {
    echo "Данные формы не были переданы";
}
