<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if (!empty($_POST['selectedValue'])) {
  $_SESSION["CABINET"] = $_POST['selectedValue'];
  echo "Кабинет успешно изменен";
} else {
  echo "Пустой запрос";
}
