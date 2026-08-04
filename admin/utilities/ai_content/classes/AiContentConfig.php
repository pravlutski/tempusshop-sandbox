<?php

class AiContentConfig
{
	const OPTION_MODULE = 'panel.manager';
	const OPTION_KEY = 'ai_content_openai_key';
	const OPTION_MODEL = 'ai_content_openai_model';

	public static function get(): array
	{
		$apiKey = '';
		$model = 'gpt-4.1';

		$path = dirname(__DIR__) . '/credentials/openai.php';
		if (is_file($path)) {
			$config = include $path;
			if (is_array($config)) {
				$apiKey = (string)($config['api_key'] ?? '');
				$model = (string)($config['model'] ?? $model);
			}
		}

		if ($apiKey === '' && class_exists('COption')) {
			$apiKey = (string)COption::GetOptionString(self::OPTION_MODULE, self::OPTION_KEY, '');
			$model = (string)COption::GetOptionString(self::OPTION_MODULE, self::OPTION_MODEL, $model);
		}

		if ($apiKey === '') {
			throw new RuntimeException('Не задан OpenAI api_key (credentials/openai.php или настройки install)');
		}

		return [
			'api_key' => $apiKey,
			'model' => $model ?: 'gpt-4.1',
		];
	}

	public static function save(string $apiKey, string $model = 'gpt-4.1'): void
	{
		if (!class_exists('COption')) {
			throw new RuntimeException('COption unavailable');
		}
		COption::SetOptionString(self::OPTION_MODULE, self::OPTION_KEY, trim($apiKey));
		COption::SetOptionString(self::OPTION_MODULE, self::OPTION_MODEL, trim($model) ?: 'gpt-4.1');
	}

	public static function hasKey(): bool
	{
		try {
			self::get();
			return true;
		} catch (Throwable $e) {
			return false;
		}
	}

	/** Property codes we ask the model to fill when possible */
	public static function researchPropCodes(): array
	{
		return [
			'TYPE',
			'MECHANISM',
			'FACE',
			'BACKLIGHT',
			'MATERIAL',
			'FEATURES',
			'CASE',
			'GLASS',
			'CALENDAR',
			'WR',
			'COLOR',
			'DIAL_COLOR',
			'DIAMETER',
			'HEIGHT',
			'THICKNESS',
			'WARRANTY',
			'VENDORCODES',
		];
	}
}
