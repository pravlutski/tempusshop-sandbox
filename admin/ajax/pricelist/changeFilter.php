<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if (!empty($_POST['selectedValue'])) {
  $_SESSION["showmode"] = $_POST['selectedValue'];
  echo "Фильтр успешно применен";
} else {
  echo "Пустой запрос";
}
