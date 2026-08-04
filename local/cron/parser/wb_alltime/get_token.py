import time
from seleniumbase import Driver

URL = "https://www.wildberries.ru/"
COOKIE_NAME = "x_wbaas_token"

def get_and_save_token():
    driver = Driver(uc=True, headless=True, driver_version="keep")
    
    try:
        driver.open(URL)
        for attempt in range(3):
            cookies_data = driver.execute_cdp_cmd("Network.getAllCookies", {})
            
            for cookie in cookies_data.get("cookies", []):
                if cookie.get("name") == COOKIE_NAME:
                    token = cookie.get("value")
                    
                    with open("/var/www/bitrix/data/www/tempusshop.ru/local/cron/parser/wb_alltime/token.txt", "w", encoding="utf-8") as f:
                        f.write(token)
                    
                    print(f"SUCCESS: Токен получен и сохранен: {token[:20]}...")
                    return token
            
            time.sleep(1)        
        
        print("ERROR: Токен не найден (время вышло)")
        
    finally:
        driver.quit()

if __name__ == "__main__":
    get_and_save_token()