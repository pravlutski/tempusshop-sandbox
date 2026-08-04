#!/usr/bin/env python3
"""
Скачивание прайса конкурентов с b2b.onliner.by.

С июля 202X вход на B2B защищён Google reCAPTCHA v2 (invisible + image challenge).
Обычный клик кнопки «Войти» в Selenium/headless больше не проходит:
сервер отвечает «Неправильная капча», а автоматизация получает image-challenge.

Варианты авторизации (по приоритету):
1. ONLINER_COOKIE_FILE / ONLINER_COOKIES — готовая сессия из браузера
2. TWOCAPTCHA_API_KEY / RUCAPTCHA_API_KEY — логин через requests + решение капчи
"""

from __future__ import annotations

import gzip
import io
import json
import os
import sys
import time
from typing import Any

import requests

LOGIN_URL = "https://b2b.onliner.by/login"
PRICELIST_URL = "https://b2b.onliner.by/pricelists"
GENERATE_URL = "https://b2b.onliner.by/shop/competitors_prices"
DOWNLOAD_URL = "https://b2b.onliner.by/shop/competitors_prices"
ONLINER_COOKIE_FILE = "/var/www/bitrix/data/www/tempusshop.ru/local/cron/parser/onliner/b2b_onliner_by_cookies.json"

# Sitekey с кнопки входа (class="g-recaptcha" data-sitekey=...)
RECAPTCHA_SITEKEY = "6LdFf00UAAAAANqyxTliFuNdmOR-eLmMCwn9hEDO"
GZIP_MAGIC = b"\x1f\x8b"

DEFAULT_DOWNLOAD_DIR = os.environ.get(
    "ONLINER_DOWNLOAD_DIR",
    "/home/bitrix/ext_www/tempusshop.ru/upload",
)
DEFAULT_DOWNLOAD_FILENAME = os.path.join(
    DEFAULT_DOWNLOAD_DIR, "onliner_competitors_prices.csv.gz"
)

USER_AGENT = (
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
    "AppleWebKit/537.36 (KHTML, like Gecko) "
    "Chrome/148.0.0.0 Safari/537.36"
)

CAPTCHA_POLL_INTERVAL = 5
CAPTCHA_TIMEOUT = 180
# Первый клик/GET ставит задачу; готовый файл ~200KB, заглушка часто ~8KB.
GENERATE_INITIAL_WAIT = int(os.environ.get("ONLINER_GENERATE_WAIT", "30"))
DOWNLOAD_POLL_INTERVAL = int(os.environ.get("ONLINER_DOWNLOAD_POLL", "15"))
DOWNLOAD_TIMEOUT = int(os.environ.get("ONLINER_DOWNLOAD_TIMEOUT", "300"))
MIN_FILE_BYTES = int(os.environ.get("ONLINER_MIN_FILE_BYTES", "50000"))


class AuthError(RuntimeError):
    pass


def env(name: str, default: str | None = None) -> str | None:
    value = os.environ.get(name, default)
    if value is None:
        return None
    value = value.strip()
    return value or None


def build_session(cookies: dict[str, str] | None = None) -> requests.Session:
    session = requests.Session()
    session.headers.update(
        {
            "User-Agent": USER_AGENT,
            "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
            "Accept-Language": "ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7",
        }
    )
    if cookies:
        session.cookies.update(cookies)
    return session


def load_cookies_from_env() -> dict[str, str] | None:
    """Загрузить cookies из JSON-файла или JSON-строки."""
    cookie_file = ONLINER_COOKIE_FILE
    if cookie_file:
        with open(cookie_file, encoding="utf-8") as fh:
            data = json.load(fh)
        return normalize_cookies(data)

    raw = env("ONLINER_COOKIES")
    if raw:
        return normalize_cookies(json.loads(raw))
    return None


def normalize_cookies(data: Any) -> dict[str, str]:
    if isinstance(data, dict):
        # Selenium export: [{name, value, ...}, ...] or plain {name: value}
        if all(isinstance(v, str) for v in data.values()):
            return {str(k): str(v) for k, v in data.items()}
        raise AuthError("ONLINER_COOKIES dict must be name->value")
    if isinstance(data, list):
        out: dict[str, str] = {}
        for item in data:
            if not isinstance(item, dict) or "name" not in item or "value" not in item:
                raise AuthError("Cookie list items must have name/value")
            out[str(item["name"])] = str(item["value"])
        return out
    raise AuthError("Unsupported cookies format")


def extract_sitekey(html: str) -> str:
    marker = 'data-sitekey="'
    start = html.find(marker)
    if start == -1:
        return RECAPTCHA_SITEKEY
    start += len(marker)
    end = html.find('"', start)
    return html[start:end] if end != -1 else RECAPTCHA_SITEKEY


def captcha_api_base() -> tuple[str, str]:
    """
    Возвращает (api_key, base_url) для 2captcha-совместимого сервиса.
    """
    key = env("TWOCAPTCHA_API_KEY") or env("RUCAPTCHA_API_KEY") or env("CAPTCHA_API_KEY")
    if not key:
        raise AuthError(
            "Нужен ключ решателя капчи: TWOCAPTCHA_API_KEY / RUCAPTCHA_API_KEY, "
            "либо готовые cookies: ONLINER_COOKIE_FILE / ONLINER_COOKIES"
        )
    # rucaptcha и 2captcha имеют одинаковый API; по умолчанию rucaptcha
    host = env("CAPTCHA_API_HOST", "https://rucaptcha.com")
    if env("TWOCAPTCHA_API_KEY") and not env("RUCAPTCHA_API_KEY"):
        host = env("CAPTCHA_API_HOST", "https://2captcha.com")
    return key, host.rstrip("/")


def solve_recaptcha_v2(page_url: str, sitekey: str) -> str:
    api_key, host = captcha_api_base()
    print(f"Отправляем reCAPTCHA в решатель ({host})...")
    create = requests.post(
        f"{host}/in.php",
        data={
            "key": api_key,
            "method": "userrecaptcha",
            "googlekey": sitekey,
            "pageurl": page_url,
            "invisible": 1,
            "json": 1,
        },
        timeout=60,
    )
    create.raise_for_status()
    payload = create.json()
    if payload.get("status") != 1:
        raise AuthError(f"Ошибка создания задачи капчи: {payload}")

    request_id = payload["request"]
    deadline = time.time() + CAPTCHA_TIMEOUT
    while time.time() < deadline:
        time.sleep(CAPTCHA_POLL_INTERVAL)
        result = requests.get(
            f"{host}/res.php",
            params={
                "key": api_key,
                "action": "get",
                "id": request_id,
                "json": 1,
            },
            timeout=60,
        )
        result.raise_for_status()
        body = result.json()
        if body.get("status") == 1:
            token = body["request"]
            print(f"Капча решена, длина токена: {len(token)}")
            return token
        if body.get("request") != "CAPCHA_NOT_READY":
            raise AuthError(f"Ошибка решения капчи: {body}")
        print("Капча ещё решается...")

    raise AuthError("Таймаут ожидания решения капчи")


def is_logged_in(session: requests.Session) -> bool:
    resp = session.get(PRICELIST_URL, allow_redirects=False, timeout=60)
    if resp.status_code in (301, 302, 303, 307, 308):
        location = resp.headers.get("Location", "")
        return "/login" not in location
    if resp.status_code == 200:
        return "login-form" not in resp.text and "g-recaptcha" not in resp.text
    return False


def login_with_captcha(username: str, password: str) -> requests.Session:
    session = build_session()
    print("Открываем страницу входа...")
    page = session.get(LOGIN_URL, timeout=60)
    page.raise_for_status()

    if "Неправильная капча" in page.text:
        # на GET этого быть не должно; оставляем на всякий случай
        pass

    sitekey = extract_sitekey(page.text)
    token = solve_recaptcha_v2(LOGIN_URL, sitekey)

    print("Отправляем форму логина...")
    resp = session.post(
        LOGIN_URL,
        data={
            "email": username,
            "password": password,
            "g-recaptcha-response": token,
        },
        headers={
            "Referer": LOGIN_URL,
            "Origin": "https://b2b.onliner.by",
            "Content-Type": "application/x-www-form-urlencoded",
        },
        timeout=60,
        allow_redirects=True,
    )
    resp.raise_for_status()

    if "Неправильная капча" in resp.text:
        raise AuthError("Сервер отклонил токен капчи (Неправильная капча)")
    if "login-form" in resp.text and resp.url.rstrip("/").endswith("/login"):
        # Возможны неверный пароль / другие ошибки
        fail = ""
        if 'class="fail"' in resp.text:
            start = resp.text.find('class="fail"')
            snippet = resp.text[start : start + 200]
            fail = snippet
        raise AuthError(f"Логин не удался, остались на /login. {fail}")

    if not is_logged_in(session):
        raise AuthError("После POST логина сессия неактивна (редирект на /login)")

    print("Авторизация успешна")
    return session


def login_with_cookies(cookies: dict[str, str]) -> requests.Session:
    session = build_session(cookies)
    if not is_logged_in(session):
        raise AuthError("Переданные cookies недействительны или истекли")
    print("Авторизация по cookies успешна")
    return session


def _download_headers() -> dict[str, str]:
    # Не просим HTML и не включаем transport-gzip: нужен сырой .csv.gz.
    return {
        "Referer": PRICELIST_URL,
        "Accept": "*/*",
        "Accept-Encoding": "identity",
    }


def fetch_competitors_payload(session: requests.Session) -> tuple[bytes, dict[str, str]]:
    """Один GET /shop/competitors_prices → тело + заголовки."""
    resp = session.get(
        DOWNLOAD_URL,
        headers=_download_headers(),
        timeout=300,
        allow_redirects=True,
        stream=False,
    )
    resp.raise_for_status()
    # requests мог сам разжать Content-Encoding; identity выше это отключает,
    # но на всякий случай берём сырые байты из content.
    data = resp.content
    headers = {k: v for k, v in resp.headers.items()}
    return data, headers


def payload_looks_like_html(data: bytes) -> bool:
    head = data.lstrip()[:200].lower()
    return head.startswith(b"<!doctype") or head.startswith(b"<html")


def payload_is_ready(data: bytes) -> tuple[bool, str]:
    """Проверяем, что это полноценный gzip, а не заглушка ~8KB."""
    if not data:
        return False, "пустой ответ"
    if payload_looks_like_html(data):
        return False, "HTML вместо файла (сессия/ошибка)"
    if not data.startswith(GZIP_MAGIC):
        return False, f"нет gzip-сигнатуры (первые байты={data[:16]!r})"
    if len(data) < MIN_FILE_BYTES:
        return False, f"слишком маленький файл: {len(data)} bytes (< {MIN_FILE_BYTES})"
    # Целостность gzip
    try:
        with gzip.GzipFile(fileobj=io.BytesIO(data)) as gz:
            # читаем кусок — убеждаемся, что архив не обрезан
            chunk = gz.read(64 * 1024)
            if not chunk:
                return False, "gzip открылся, но внутри пусто"
    except OSError as exc:
        return False, f"битый/обрезанный gzip: {exc}"
    return True, f"ok gzip, {len(data)} bytes"


def trigger_generate(session: requests.Session) -> bytes | None:
    """
    Инициирует генерацию файла конкурентов.
    На UI это ссылка #competitors_prices a → /shop/competitors_prices.
    Если файл уже готов — возвращаем его сразу.
    """
    print("Открываем прайс-листы...")
    page = session.get(PRICELIST_URL, timeout=60)
    page.raise_for_status()
    if "login-form" in page.text:
        raise AuthError("Сессия потеряна на странице прайс-листов")

    print("Инициируем генерацию competitors_prices...")
    data, headers = fetch_competitors_payload(session)
    ctype = headers.get("Content-Type", "")
    disp = headers.get("Content-Disposition", "")
    print(
        f"generate bytes={len(data)} Content-Type={ctype!r} "
        f"Content-Disposition={disp!r}"
    )
    ready, reason = payload_is_ready(data)
    if ready:
        print(f"Файл уже готов после первого запроса ({reason})")
        return data
    print(f"Файл ещё не готов ({reason}), ждём генерацию...")
    return None


def download_file(session: requests.Session, dest: str, initial: bytes | None = None) -> None:
    """
    Скачивает полный .csv.gz, поллит пока размер/gzip не станут валидными.

    Ранее был баг: peek через iter_content(8192) + второй проход по тому же
    response часто сохранял только первый чанк (~8KB) вместо ~200KB.
    """
    os.makedirs(os.path.dirname(dest) or ".", exist_ok=True)

    if initial is not None:
        ready, reason = payload_is_ready(initial)
        if ready:
            _write_atomic(dest, initial)
            print(f"Файл сохранён: {dest} ({len(initial)} bytes) [{reason}]")
            return

    print(
        f"Ожидание генерации: initial_wait={GENERATE_INITIAL_WAIT}s, "
        f"poll={DOWNLOAD_POLL_INTERVAL}s, timeout={DOWNLOAD_TIMEOUT}s, "
        f"min_bytes={MIN_FILE_BYTES}"
    )
    time.sleep(GENERATE_INITIAL_WAIT)

    deadline = time.time() + DOWNLOAD_TIMEOUT
    last_reason = "не запускали"
    attempt = 0
    while time.time() < deadline:
        attempt += 1
        data, headers = fetch_competitors_payload(session)
        ctype = headers.get("Content-Type", "")
        ready, reason = payload_is_ready(data)
        last_reason = reason
        print(
            f"попытка {attempt}: bytes={len(data)} Content-Type={ctype!r} → {reason}"
        )
        if ready:
            _write_atomic(dest, data)
            print(f"Файл сохранён: {dest} ({len(data)} bytes)")
            return
        if payload_looks_like_html(data):
            raise AuthError(
                "Вместо файла получен HTML. Сессия невалидна или доступ запрещён."
            )
        time.sleep(DOWNLOAD_POLL_INTERVAL)

    raise AuthError(
        f"Не дождались полного файла за {DOWNLOAD_TIMEOUT}s. "
        f"Последняя причина: {last_reason}"
    )


def _write_atomic(dest: str, data: bytes) -> None:
    tmp = dest + ".tmp"
    with open(tmp, "wb") as fh:
        fh.write(data)
    os.replace(tmp, dest)


def dump_cookies(session: requests.Session, path: str | None = None) -> None:
    cookies = requests.utils.dict_from_cookiejar(session.cookies)
    text = json.dumps(cookies, ensure_ascii=False, indent=2)
    if path:
        with open(path, "w", encoding="utf-8") as fh:
            fh.write(text)
        print(f"Cookies сохранены в {path}")
    else:
        print(text)


def main() -> int:
    dest = env("ONLINER_DOWNLOAD_PATH", DEFAULT_DOWNLOAD_FILENAME) or DEFAULT_DOWNLOAD_FILENAME
    username = env("ONLINER_USERNAME", "tempus.by")
    password = env("ONLINER_PASSWORD")

    try:
        cookies = load_cookies_from_env()
        if cookies:
            session = login_with_cookies(cookies)
        else:
            if not password:
                raise AuthError(
                    "Задайте ONLINER_PASSWORD или ONLINER_COOKIE_FILE / ONLINER_COOKIES"
                )
            assert username is not None
            session = login_with_captcha(username, password)

        cookie_dump = env("ONLINER_DUMP_COOKIES")
        if cookie_dump:
            dump_cookies(session, cookie_dump)

        initial = trigger_generate(session)
        download_file(session, dest, initial=initial)
        print("Процесс завершен успешно!")
        return 0
    except Exception as exc:
        print(f"Ошибка: {exc}", file=sys.stderr)
        raise


if __name__ == "__main__":
    raise SystemExit(main())