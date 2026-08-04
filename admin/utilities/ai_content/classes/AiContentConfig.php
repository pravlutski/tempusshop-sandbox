<?php

class AiContentConfig
{
	const OPTION_MODULE = 'panel.manager';
	const OPTION_KEY = 'ai_content_openai_key';
	const OPTION_MODEL = 'ai_content_openai_model';
	const OPTION_PROXY = 'ai_content_openai_proxy';
	const OPTION_PROXY_TYPE = 'ai_content_openai_proxy_type';

	public static function get(): array
	{
		$apiKey = '';
		$model = 'gpt-4.1';
		$proxy = '';
		$proxyType = 'http';

		$path = dirname(__DIR__) . '/credentials/openai.php';
		if (is_file($path)) {
			$config = include $path;
			if (is_array($config)) {
				$apiKey = (string)($config['api_key'] ?? '');
				$model = (string)($config['model'] ?? $model);
				$proxy = (string)($config['proxy'] ?? '');
				$proxyType = (string)($config['proxy_type'] ?? $proxyType);
			}
		}

		if (class_exists('COption')) {
			if ($apiKey === '') {
				$apiKey = (string)COption::GetOptionString(self::OPTION_MODULE, self::OPTION_KEY, '');
			}
			$model = (string)COption::GetOptionString(self::OPTION_MODULE, self::OPTION_MODEL, $model) ?: $model;
			$dbProxy = (string)COption::GetOptionString(self::OPTION_MODULE, self::OPTION_PROXY, '');
			if ($dbProxy !== '') {
				$proxy = $dbProxy;
			}
			$dbProxyType = (string)COption::GetOptionString(self::OPTION_MODULE, self::OPTION_PROXY_TYPE, '');
			if ($dbProxyType !== '') {
				$proxyType = $dbProxyType;
			}
		}

		if ($apiKey === '') {
			throw new RuntimeException('Не задан OpenAI api_key (credentials/openai.php или настройки install)');
		}

		return [
			'api_key' => $apiKey,
			'model' => $model ?: 'gpt-4.1',
			'proxy' => trim($proxy),
			'proxy_type' => self::normalizeProxyType($proxyType),
		];
	}

	public static function save(
		string $apiKey,
		string $model = 'gpt-4.1',
		?string $proxy = null,
		?string $proxyType = null
	): void {
		if (!class_exists('COption')) {
			throw new RuntimeException('COption unavailable');
		}
		if (trim($apiKey) !== '') {
			COption::SetOptionString(self::OPTION_MODULE, self::OPTION_KEY, trim($apiKey));
		}
		COption::SetOptionString(self::OPTION_MODULE, self::OPTION_MODEL, trim($model) ?: 'gpt-4.1');
		if ($proxy !== null) {
			COption::SetOptionString(self::OPTION_MODULE, self::OPTION_PROXY, trim($proxy));
		}
		if ($proxyType !== null) {
			COption::SetOptionString(self::OPTION_MODULE, self::OPTION_PROXY_TYPE, self::normalizeProxyType($proxyType));
		}
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

	public static function getProxyRaw(): string
	{
		if (class_exists('COption')) {
			$v = (string)COption::GetOptionString(self::OPTION_MODULE, self::OPTION_PROXY, '');
			if ($v !== '') {
				return $v;
			}
		}
		$path = dirname(__DIR__) . '/credentials/openai.php';
		if (is_file($path)) {
			$config = include $path;
			if (is_array($config) && !empty($config['proxy'])) {
				return (string)$config['proxy'];
			}
		}
		return '';
	}

	public static function getProxyTypeRaw(): string
	{
		$type = 'http';
		if (class_exists('COption')) {
			$type = (string)COption::GetOptionString(self::OPTION_MODULE, self::OPTION_PROXY_TYPE, 'http') ?: 'http';
		}
		return self::normalizeProxyType($type);
	}

	public static function normalizeProxyType(string $type): string
	{
		$type = strtolower(trim($type));
		$allowed = ['http', 'https', 'socks5', 'socks5h'];
		return in_array($type, $allowed, true) ? $type : 'http';
	}

	/**
	 * Parse proxy string into curl options.
	 * Supported:
	 * - host:port
	 * - host:port:user:pass
	 * - http://user:pass@host:port
	 * - socks5://user:pass@host:port
	 */
	public static function parseProxy(string $proxy, string $proxyType = 'http'): ?array
	{
		$proxy = trim($proxy);
		if ($proxy === '') {
			return null;
		}

		$typeMap = [
			'http' => defined('CURLPROXY_HTTP') ? CURLPROXY_HTTP : 0,
			'https' => defined('CURLPROXY_HTTPS') ? CURLPROXY_HTTPS : (defined('CURLPROXY_HTTP') ? CURLPROXY_HTTP : 0),
			'socks5' => defined('CURLPROXY_SOCKS5') ? CURLPROXY_SOCKS5 : 5,
			'socks5h' => defined('CURLPROXY_SOCKS5_HOSTNAME') ? CURLPROXY_SOCKS5_HOSTNAME : (defined('CURLPROXY_SOCKS5') ? CURLPROXY_SOCKS5 : 5),
		];

		if (preg_match('#^(https?|socks5h?|socks4a?)://#i', $proxy, $m)) {
			$scheme = strtolower($m[1]);
			if ($scheme === 'socks5' || $scheme === 'socks5h') {
				$proxyType = $scheme;
			} elseif ($scheme === 'https') {
				$proxyType = 'https';
			} else {
				$proxyType = 'http';
			}
			$parts = parse_url($proxy);
			if (empty($parts['host']) || empty($parts['port'])) {
				return null;
			}
			$hostPort = $parts['host'] . ':' . $parts['port'];
			$userpwd = null;
			if (isset($parts['user'])) {
				$userpwd = rawurldecode($parts['user']);
				if (isset($parts['pass'])) {
					$userpwd .= ':' . rawurldecode($parts['pass']);
				}
			}
			return [
				'hostport' => $hostPort,
				'userpwd' => $userpwd,
				'type' => $typeMap[$proxyType] ?? $typeMap['http'],
				'type_name' => $proxyType,
			];
		}

		$chunks = explode(':', $proxy);
		if (count($chunks) === 2) {
			return [
				'hostport' => $chunks[0] . ':' . $chunks[1],
				'userpwd' => null,
				'type' => $typeMap[$proxyType] ?? $typeMap['http'],
				'type_name' => $proxyType,
			];
		}
		if (count($chunks) === 4) {
			return [
				'hostport' => $chunks[0] . ':' . $chunks[1],
				'userpwd' => $chunks[2] . ':' . $chunks[3],
				'type' => $typeMap[$proxyType] ?? $typeMap['http'],
				'type_name' => $proxyType,
			];
		}

		return null;
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
