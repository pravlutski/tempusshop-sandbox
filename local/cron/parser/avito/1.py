import undetected_chromedriver as uc
from selenium.webdriver.common.by import By
import time
import json
import requests

# 1. СТАРЫЕ КУКИ (для начальной авторизации)
OLD_COOKIES = [
    {
        'name': 'user-cached-data',
        'value': '{"avatar":{"url":"https://40.img.avito.st/image/1/1.Na97ira1j0ZNLRtAHboW2QUrm0bFIRtATS2bRA.zUK2SNPVhcfXst37A5rY9z098_6tIRzNUKfHfA2VUow"},"email":"sales@tempusshop.ru","employerType":null,"hasAdditionalServices":true,"hasAvitoPro":true,"hasComfortableDeal":false,"hasDashboard":true,"hasDashboardBeduin":true,"hasDashboardExposed":false,"hasJobChatBotPackagesAccess":true,"hasJobChatBotsAccess":true,"hasMarketAnalysis":true,"hasNdProfSearch":false,"hasProWelcomeOnboarding":true,"hasShop":true,"hasShopSubscription":true,"hasSmbStats":true,"hasTariff":true,"hasUserGeo20Access":true,"hasVirtualDealRoom":false,"hasWordstat":true,"hashedId":"62b5f4c69f78dc0e4346727c5a569303","id":225573656,"isAdvertisingAgency":false,"isAiAssistantEnabled":false,"isComtransDealer":false,"isEmployee":false,"isEmployer":false,"isLegal":false,"isOneStopPlace":true,"isShowLoans":false,"loyaltyPreferences":{"layoutType":"united","title":"Уровень сервиса","uri":"/reputation"},"name":"Watch Original","passport":{"isFeatureEnabled":true,"isProfileSwitchAvailable":true},"showCallback":false,"showManagerCallbackLink":true,"tariffInfo":{"balanceLeft":11883.2,"bonusesLeft":0,"hasBalance":true,"hasBonuses":false,"isActive":true,"isVisible":true,"name":"Тариф","url":"/tariff/cpa/profile"}}',
        'domain': '.avito.ru'
    },
    {
        'name': '_ym_uid',
        'value': '1769872825726980164',
        'domain': '.avito.ru'
    },
    {
        'name': 'tmr_lvid',
        'value': '6cd4bbb68f87475bc5cb17049b632994',
        'domain': '.avito.ru'
    }
]

def get_fresh_cookies_with_selenium():
    """Используем Selenium для получения свежих кук"""
    print("🚀 Запускаем браузер для получения свежих кук...")
    
    # Настройка браузера
    options = uc.ChromeOptions()
    options.add_argument('--no-sandbox')
    options.add_argument('--disable-dev-shm-usage')
    # options.add_argument('--headless')  # РАСКОММЕНТИРУЙ для сервера
    
    driver = uc.Chrome(options=options, version_main=142)
    
    try:
        # 1. Открываем Avito
        print("1. Открываем Avito...")
        driver.get("https://www.avito.ru")
        time.sleep(3)
        
        # 2. Добавляем старые куки для авторизации
        print("2. Добавляем куки авторизации...")
        for cookie in OLD_COOKIES:
            try:
                driver.add_cookie(cookie)
                print(f"   ✓ {cookie['name']}")
            except Exception as e:
                print(f"   ✗ {cookie['name']}: {e}")
        
        # 3. Обновляем страницу (чтобы куки применились)
        print("3. Обновляем страницу...")
        driver.refresh()
        time.sleep(5)
        
        # 4. Проверяем, авторизованы ли мы
        print("4. Проверяем авторизацию...")
        try:
            profile_link = driver.find_element(By.XPATH, "//a[@href='/profile']")
            print("   ✅ Видим профиль - авторизация успешна!")
        except:
            print("   ⚠️  Не видим явных признаков авторизации")
        
        # 5. Получаем ВСЕ свежие куки
        print("5. Получаем свежие куки...")
        fresh_cookies = driver.get_cookies()
        
        # Сохраняем куки в файл
        with open('fresh_avito_cookies.json', 'w') as f:
            json.dump(fresh_cookies, f, indent=2)
        
        print(f"   ✅ Получено {len(fresh_cookies)} кук")
        print("   💾 Куки сохранены в fresh_avito_cookies.json")
        
        # 6. Пробуем сразу получить заказы через браузер
        print("\n6. Тестируем API через браузер...")
        driver.get("https://www.avito.ru/web/1/orders?page=1&limit=20")
        time.sleep(3)
        
        # Проверяем ответ
        page_content = driver.page_source
        
        if 'orders' in page_content:
            print("   ✅ API отвечает через браузер!")
            
            # Сохраняем ответ
            import re
            json_match = re.search(r'(\{.*\})', page_content, re.DOTALL)
            if json_match:
                try:
                    data = json.loads(json_match.group(1))
                    orders_count = len(data.get('orders', []))
                    print(f"   📊 Получено заказов: {orders_count}")
                    
                    with open('browser_orders.json', 'w', encoding='utf-8') as f:
                        json.dump(data, f, ensure_ascii=False, indent=2)
                except:
                    pass
        
        return fresh_cookies
        
    except Exception as e:
        print(f"❌ Ошибка в Selenium: {e}")
        return None
    finally:
        driver.quit()
        print("\nБраузер закрыт")

def use_fresh_cookies_for_api(fresh_cookies):
    """Используем свежие куки для API запросов"""
    print("\n🔄 Используем свежие куки для API...")
    
    # Создаем сессию requests
    session = requests.Session()
    
    # Устанавливаем заголовки
    session.headers.update({
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'Accept': 'application/json',
        'Referer': 'https://www.avito.ru/profile'
    })
    
    # Добавляем куки в сессию
    for cookie in fresh_cookies:
        session.cookies.set(
            cookie['name'],
            cookie['value'],
            domain=cookie.get('domain', '.avito.ru'),
            path=cookie.get('path', '/')
        )
    
    # Пробуем получить заказы
    url = "https://www.avito.ru/web/1/orders?page=1&limit=20"
    
    try:
        response = session.get(url, timeout=30)
        
        if response.status_code == 200:
            data = response.json()
            orders_count = len(data.get('orders', []))
            print(f"✅ API РАБОТАЕТ! Заказов: {orders_count}")
            
            # Сохраняем результат
            with open('api_orders.json', 'w', encoding='utf-8') as f:
                json.dump(data, f, ensure_ascii=False, indent=2)
            
            return True
        elif response.status_code == 429:
            print("❌ Всё равно 429! IP всё ещё заблокирован.")
            print("   Нужно сменить IP через прокси или подождать.")
            return False
        else:
            print(f"❌ Ошибка {response.status_code}")
            return False
            
    except Exception as e:
        print(f"❌ Ошибка запроса: {e}")
        return False

def main():
    print("=" * 60)
    print("Avito Cookie Refresh + API Parser")
    print("=" * 60)
    
    # 1. Получаем свежие куки через Selenium
    fresh_cookies = get_fresh_cookies_with_selenium()
    
    if not fresh_cookies:
        print("\n❌ Не удалось получить свежие куки")
        return
    
    # 2. Пробуем использовать их для API
    print("\n" + "=" * 40)
    print("Тестируем свежие куки...")
    
    success = use_fresh_cookies_for_api(fresh_cookies)
    
    if success:
        print("\n🎉 ВСЁ РАБОТАЕТ! Теперь можно парсить все страницы:")
        print("""
# Код для парсинга всех страниц
import requests
import json

with open('fresh_avito_cookies.json', 'r') as f:
    cookies = json.load(f)

session = requests.Session()
for cookie in cookies:
    session.cookies.set(cookie['name'], cookie['value'])

for page in range(1, 6):
    url = f'https://www.avito.ru/web/1/orders?page={page}&limit=20'
    response = session.get(url)
    if response.status_code == 200:
        data = response.json()
        with open(f'orders_page_{page}.json', 'w') as f:
            json.dump(data, f, ensure_ascii=False, indent=2)
        print(f'Страница {page}: {len(data.get("orders", []))} заказов')
        """)
    else:
        print("\n⚠️  API не работает даже со свежими куками.")
        print("Вероятно, IP твоего сервера полностью заблокирован.")
        print("\nРешение: использовать прокси.")
        print("Добавь в настройки Selenium:")
        print("""
options.add_argument('--proxy-server=http://username:password@proxy_ip:port')
        """)

if __name__ == "__main__":
    main()