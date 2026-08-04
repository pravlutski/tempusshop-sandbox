<?php

require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
	$profiles_list = $_POST["profiles_list"];
	$csv_file = $_FILES["csv_file"];

	$multipleProfileCreator = new MultipleProfileCreator($profiles_list, $csv_file);
	$multipleProfileCreator->createMultipleProfiles();
} else {
	echo "Данные формы не были переданы";
}

class MultipleProfileCreator
{
	private $profiles_list;
	private $csv_file;

	public function __construct($profiles_list, $csv_file)
	{
		$this->profiles_list = $profiles_list;
		$this->csv_file = $csv_file;
	}

	public function createMultipleProfiles(): void
	{
		global $DB;

		if (!empty($this->csv_file['tmp_name'])) {
			// Обработка CSV-файла
			$profiles = $this->processCSVFile();
		} else {
			// Обработка списка профилей из поля textarea
			$profiles = explode("\n", $this->profiles_list);
		}

		foreach ($profiles as $profile) {
			// Разбиваем строку по символу ";"
			$profile_parts = explode(";", $profile, 3); // Ограничение разбиения до 3 элементов
			$article = trim($profile_parts[0]);
			$markup = trim($profile_parts[1]);
			$price_id = trim($profile_parts[2]);

			// Проверка наличия профиля в базе данных
			$checkDuplicateQuery = "SELECT `article`, `price_id` FROM `ci_custom_analiz_price` WHERE `article` = '" . $DB->ForSql($article) . "' AND `price_id` = '" . $DB->ForSql($price_id) . "'";
			$checkDuplicateResult = $DB->Query($checkDuplicateQuery);

			if ($checkDuplicateRow = $checkDuplicateResult->Fetch()) {
				// Удаление существующего профиля
				$deleteQuery = "DELETE FROM `ci_custom_analiz_price` WHERE `article` = '" . $DB->ForSql($article) . "' AND `price_id` = '" . $DB->ForSql($price_id) . "'";
				$deleteResult = $DB->Query($deleteQuery);

				if ($deleteResult === false) {
					echo "Ошибка при удалении существующего профиля с артикулом: " . $article . " и типом цены: " . $price_id . "<br>";
					continue;
				}

				echo "Существующий профиль с артикулом: " . $article . " и типом цены: " . $price_id . " был удален. Создается новый профиль.<br>";
			}

			// Вставка данных в базу данных
			$strSql = "INSERT INTO `ci_custom_analiz_price` (`article`, `markup`, `price_id`, `created_at`, `updated_at`)
                       VALUES ('" . $DB->ForSql($article) . "', '" . $DB->ForSql($markup) . "', '" . $DB->ForSql($price_id) . "', NOW(), NOW())";
			$result = $DB->Query($strSql);

			if ($result === false) {
				echo "Ошибка при создании профиля: " . $article . " с типом цены: " . $price_id . "<br>";
			} else {
				echo "Профиль с артикулом: " . $article . " и типом цены: " . $price_id . " успешно создан.<br>";
			}
		}

		echo "Профили успешно созданы";
		header("Location: https://tempusshop.ru/admin/modules/custom_analiz_price/");
	}

	private function processCSVFile(): array
	{
		$profiles = [];

		if (($handle = fopen($this->csv_file['tmp_name'], "r")) !== FALSE) {
			while (($data = fgetcsv($handle, 1000, ";")) !== FALSE) {
				$profiles[] = implode(";", $data);
			}
			fclose($handle);
		}

		return $profiles;
	}
}