<?php

const SEND_TEMPUS_IBLOCK_ID = 16;
const SEND_TEMPUS_CHUNK_SIZE = 800;

function sendTempusMainFields()
{
	return [
		'NAME' => 'NAME — Название',
		'CODE' => 'CODE — Символьный код',
		'XML_ID' => 'XML_ID — Внешний код',
		'ACTIVE' => 'ACTIVE — Активность',
		'SORT' => 'SORT — Сортировка',
		'PREVIEW_PICTURE' => 'PREVIEW_PICTURE — Картинка анонса',
		'PREVIEW_TEXT' => 'PREVIEW_TEXT — Описание для анонса',
		'DETAIL_PICTURE' => 'DETAIL_PICTURE — Детальная картинка',
		'DETAIL_TEXT' => 'DETAIL_TEXT — Детальное описание',
		'IBLOCK_SECTION_ID' => 'IBLOCK_SECTION_ID — Основной раздел',
		'SECTIONS' => 'SECTIONS — Разделы',
		'TAGS' => 'TAGS — Теги',
		'DATE_ACTIVE_FROM' => 'DATE_ACTIVE_FROM — Начало активности',
		'DATE_ACTIVE_TO' => 'DATE_ACTIVE_TO — Окончание активности',
	];
}

function sendTempusParseList($text)
{
	$text = trim((string)$text);
	if ($text === '') {
		return [];
	}
	$text = str_replace(["\r\n", "\r"], "\n", $text);
	$parts = preg_split('/[\n,;\t ]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
	$result = [];
	foreach ($parts as $part) {
		$part = trim($part);
		if ($part !== '') {
			$result[] = $part;
		}
	}
	return array_values(array_unique($result));
}

function sendTempusGetIblockProps($iblockId)
{
	$props = [];
	if (!CModule::IncludeModule('iblock')) {
		return $props;
	}
	$rs = CIBlockProperty::GetList(
		['NAME' => 'ASC', 'ID' => 'ASC'],
		['IBLOCK_ID' => (int)$iblockId, 'ACTIVE' => 'Y']
	);
	while ($prop = $rs->GetNext()) {
		$code = trim((string)$prop['CODE']);
		if ($code === '') {
			continue;
		}
		$props[$code] = [
			'CODE' => $code,
			'NAME' => (string)$prop['NAME'],
			'PROPERTY_TYPE' => (string)$prop['PROPERTY_TYPE'],
		];
	}
	return $props;
}

function sendTempusResolveIds($idsText, $articlesText, $iblockId)
{
	$rawIds = sendTempusParseList($idsText);
	$articles = sendTempusParseList($articlesText);

	$ids = [];
	foreach ($rawIds as $value) {
		if (ctype_digit((string)$value) && (int)$value > 0) {
			$ids[] = (int)$value;
		}
	}
	$ids = array_values(array_unique($ids));

	if ($ids) {
		return [
			'ids' => $ids,
			'notFound' => [],
			'source' => 'ID',
		];
	}

	if (!$articles) {
		return [
			'ids' => [],
			'notFound' => [],
			'source' => '',
		];
	}

	if (!CModule::IncludeModule('iblock')) {
		return [
			'ids' => [],
			'notFound' => $articles,
			'source' => 'ARTICLE',
		];
	}

	$foundByArticle = [];
	foreach (array_chunk($articles, 200) as $chunk) {
		$res = CIBlockElement::GetList(
			[],
			[
				'IBLOCK_ID' => (int)$iblockId,
				'PROPERTY_CML2_ARTICLE' => $chunk,
			],
			false,
			false,
			['ID', 'PROPERTY_CML2_ARTICLE']
		);
		while ($el = $res->Fetch()) {
			$article = mb_strtoupper(trim((string)$el['PROPERTY_CML2_ARTICLE_VALUE']));
			if ($article !== '') {
				$foundByArticle[$article] = (int)$el['ID'];
			}
		}
	}

	$ids = [];
	$notFound = [];
	foreach ($articles as $article) {
		$key = mb_strtoupper(trim($article));
		if (isset($foundByArticle[$key])) {
			$ids[] = $foundByArticle[$key];
		} else {
			$notFound[] = $article;
		}
	}

	return [
		'ids' => array_values(array_unique($ids)),
		'notFound' => $notFound,
		'source' => 'ARTICLE',
	];
}

function sendTempusFilterSelected($selected, $allowed)
{
	if (!is_array($selected)) {
		return [];
	}
	$result = [];
	foreach ($selected as $code) {
		$code = trim((string)$code);
		if ($code !== '' && isset($allowed[$code])) {
			$result[] = $code;
		}
	}
	return array_values(array_unique($result));
}
