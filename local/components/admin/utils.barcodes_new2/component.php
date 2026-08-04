<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

// Подключаем класс компонента
$componentClass = 'BarcodeManagerComponent';
if (!class_exists($componentClass)) {
    require_once __DIR__ . '/class.php';
}

// Инициализируем и выполняем компонент
$component = new $componentClass($this);
$component->executeComponent();
?>