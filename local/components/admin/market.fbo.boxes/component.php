<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

// Включаем вывод ошибок для отладки
error_reporting(E_ALL);
ini_set('display_errors', 1);


// Создаем экземпляр компонента и запускаем
$component = new FBOBoxes($this->__component);
$component->executeComponent();
?>