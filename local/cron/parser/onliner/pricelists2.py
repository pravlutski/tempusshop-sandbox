import os
import time
import uuid
import requests
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.common.action_chains import ActionChains

# Конфигурация
LOGIN_URL = "https://b2b.onliner.by/login"
PRICELIST_URL = "https://b2b.onliner.by/pricelists"
DOWNLOAD_URL = "https://b2b.onliner.by/shop/competitors_prices"
DOWNLOAD_DIR = "/home/bitrix/ext_www/tempusshop.ru/upload"
DOWNLOAD_FILENAME = os.path.join(DOWNLOAD_DIR, "onliner_competitors_prices.csv.gz")

# Учетные данные
USERNAME = "tempus.by"
PASSWORD = "mg6q6Fk954"

def setup_driver():
    chrome_options = Options()
    
    # Для отладки можно временно отключить headless режим
    chrome_options.add_argument("--headless=new")
    chrome_options.add_argument("--no-sandbox")
    chrome_options.add_argument("--disable-dev-shm-usage")
    chrome_options.add_argument("--window-size=1280,1024")
    
    # Уникальный профиль для каждого запуска
    user_data_dir = f"/tmp/chrome_{uuid.uuid4().hex}"
    os.makedirs(user_data_dir, exist_ok=True)
    chrome_options.add_argument(f"--user-data-dir={user_data_dir}")
    
    service = Service(executable_path='/usr/bin/chromedriver')
    driver = webdriver.Chrome(service=service, options=chrome_options)
    
    return driver, user_data_dir

def login_and_get_cookies():
    driver, temp_dir = None, None
    try:
        driver, temp_dir = setup_driver()
        
        print("Открываем страницу входа...")
        driver.get(LOGIN_URL)
        
        # Ждем и заполняем форму
        WebDriverWait(driver, 30).until(
            EC.presence_of_element_located((By.CSS_SELECTOR, "#login-form")))
        
        email = driver.find_element(By.NAME, "email")
        email.clear()
        email.send_keys(USERNAME)
        
        password = driver.find_element(By.NAME, "password")
        password.clear()
        password.send_keys(PASSWORD)
        driver.save_screenshot('/var/www/bitrix_data/tempusshop.ru/upload/onliner_pre_auth.png')
        driver.find_element(By.CSS_SELECTOR, '#login-form button').click()
        
        # Проверяем успешный вход
        #WebDriverWait(driver, 30).until(
        #    EC.presence_of_element_located((By.CSS_SELECTOR, "body.logged-in")))
        
        print("Авторизация успешна, получаем cookies...")
        driver.save_screenshot('/var/www/bitrix_data/tempusshop.ru/upload/onliner_auth.png')
        # Переходим на страницу прайс-листа
        try:
            driver.get(PRICELIST_URL)
            driver.find_element(By.CSS_SELECTOR, '#competitors_prices a').click()
        except Exception as e:
            pass
        #WebDriverWait(driver, 30).until(
        #    EC.presence_of_element_located((By.CSS_SELECTOR, "#competitors_prices")))
        #generate_btn = WebDriverWait(driver, 30).until(
        #    EC.element_to_be_clickable((By.CSS_SELECTOR, '#competitors_prices a')))
        #generate_btn.click()
        #generate_button.screenshot('/var/www/bitrix_data/tempusshop.ru/upload/asdasd.png')
        driver.save_screenshot('/var/www/bitrix_data/tempusshop.ru/upload/asdasd.png')
        #driver.find_element(By.CSS_SELECTOR, '#login-form button').click()
        #generate_button.screenshot('/var/www/bitrix/data/www/asdasd.png')
        #ActionChains(driver).move_to_element(generate_button).click().perform()
        print("Ожидание 30 секунд для генерации прайс-листа...")
        time.sleep(30)
    
        cookies = {c['name']: c['value'] for c in driver.get_cookies()}
        return cookies
        
    finally:
        if driver:
            driver.quit()
        if temp_dir and os.path.exists(temp_dir):
            os.system(f"rm -rf {temp_dir}")

def download_with_requests(cookies):
  
    session = requests.Session()
    session.cookies.update(cookies)
    session.headers.update({
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
    })
    
    #print("Генерируем прайс-лист...")
    #response = session.get(PRICELIST_URL)
    #response.raise_for_status()
    
    #print("Инициируем генерацию файла...")
    #generate_url = "https://b2b.onliner.by/shop/competitors_prices/generate"  # Возможно нужно уточнить URL
    #response = session.post(generate_url)
    #response.raise_for_status()
    
    #print("Ожидаем 60 секунд для генерации...")
    #time.sleep(60)
    
    print("Скачиваем файл...")
    response = session.get(DOWNLOAD_URL, stream=True)
    response.raise_for_status()
    
    os.makedirs(DOWNLOAD_DIR, exist_ok=True)
    temp_filename = DOWNLOAD_FILENAME + ".tmp"
    
    with open(temp_filename, 'wb') as f:
        for chunk in response.iter_content(chunk_size=8192):
            if chunk:
                f.write(chunk)
    
    os.rename(temp_filename, DOWNLOAD_FILENAME)
    print(f"Файл успешно сохранен: {DOWNLOAD_FILENAME}")

def main():
    try:
        # Получаем cookies через Selenium
        cookies = login_and_get_cookies()
        
        # Скачиваем файл через requests
        download_with_requests(cookies)
        
        print("Процесс завершен успешно!")
    except Exception as e:
        print(f"Ошибка: {str(e)}")
        raise

if __name__ == "__main__":
    main()