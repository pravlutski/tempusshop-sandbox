<?php

class AiContentAccess
{
	/**
	 * AccessValidator queries admin_utilities_* and crashes if tables are missing.
	 * On sandbox we ensure tables first; admins always pass.
	 */
	public static function guard(): void
	{
		global $USER;

		if ($USER && $USER->IsAdmin()) {
			return;
		}

		try {
			AccessValidator::checkIfAllowed();
		} catch (Throwable $e) {
			if ($USER && $USER->IsAdmin()) {
				return;
			}
			die('<div class="alert alert-danger">Доступ запрещен или таблицы прав утилит недоступны. Админ: откройте /admin/utilities/ai_content/install.php</div>');
		}
	}
}
