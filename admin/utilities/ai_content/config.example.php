<?php
/**
 * Copy to credentials/openai.php and fill values.
 * Prefer saving via /admin/utilities/ai_content/install.php (Bitrix options).
 */
return [
	'api_key' => 'sk-proj-...',
	'model' => 'gpt-4.1',
	// Examples:
	// 'proxy' => '1.2.3.4:8000:user:pass',
	// 'proxy' => 'socks5://user:pass@1.2.3.4:1080',
	'proxy' => '',
	'proxy_type' => 'http', // http | https | socks5 | socks5h
];
