from selenium import webdriver
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from sys import argv
from selenium.common.exceptions import TimeoutException
from selenium.webdriver.common.action_chains import ActionChains
from pprint import pprint as pp
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.chrome.options import Options
import json
import os
import traceback
import time
import logging
from datetime import datetime


path_main = '/home/bitrix/logs/ms/'
path_parser_html = path_main + 'parser/'
match_page = {
    'stockReport': 'https://online.moysklad.ru/app/#stockReport?reportType=GOODS&upToDateFilter=29.08.2024%2023:59:00|'
}
# python3 /var/www/bitrix/data/www/tempusshop.ru/local/cron/parser/ms/clicker.py cabinet=msk2 username=api@chronos password=VvrmVqzKtF7B pages=stockReport,

        
def log_error(error_msg, exc_info=False):
    """
    Логирование ошибок с деталями исключения
    """
    if exc_info:
        error_msg += f"\n{traceback.format_exc()}"
    
    logging.error(error_msg)
    # Дополнительно сохраняем критичные ошибки в отдельный файл
    with open('/var/www/bitrix_logs/ms/errors.log', 'a', encoding='utf-8') as f:
        f.write(f"{datetime.now()} - ERROR - {error_msg}\n")
        
def parse_argv():
    ar = {}

    for param in argv[1:]:
        print(param)
        
        p = param.split("=")
        
        if "," in p[1]:
            ar[p[0]] = p[1].split(",")
        else:
            ar[p[0]] = p[1]
        
    
    return ar
    
    
param = parse_argv()

f = open(path_main + param['cabinet'] + '.json')

settings = json.load(f)
#print(settings)

#options = webdriver.ChromeOptions()
#options.add_argument("--headless")
#options.add_argument("--no-sandbox")
#options.add_argument("--ignore-ssl-errors=true")
#options.add_argument("--ignore-certificate-errors")    
#options.binary_location="/usr/bin/chromedriver"

"""
options = Options()
options.binary_location = '/usr/bin/chromium-browser'
options.add_argument("--headless=new")
options.add_argument("--no-sandbox")
options.add_argument("--ignore-ssl-errors=true")
options.add_argument("--ignore-certificate-errors")
options.add_argument("--disable-dev-shm-usage")  # Критически важно!
options.add_argument("--disable-gpu")
options.add_argument("--window-size=1920,1080")
#options.add_argument("--remote-debugging-port=9222")
options.add_argument("--disable-extensions")
options.add_argument("--disable-setuid-sandbox")
options.add_argument("--disable-software-rasterizer")
options.add_argument("--disable-notifications")
options.add_argument("--disable-infobars")
options.add_argument("--disable-browser-side-navigation")
options.add_argument("--disable-features=VizDisplayCompositor")
options.add_argument("--disable-logging")
"""
options = Options()
options.add_argument("--headless=new")
options.add_argument("--no-sandbox")
options.add_argument("--disable-dev-shm-usage")
options.add_argument("--window-size=1920,1080")

service = Service(
    executable_path='/usr/bin/chromedriver',
    service_args=['--verbose'],
    log_path='/var/www/bitrix_logs/ms/chromedriver.log'
)

try:
    #driver = webdriver.Firefox(executable_path='/usr/local/bin/geckodriver', service_log_path=path_main + 'geckodriver.log', options=opts)
    #driver = webdriver.Chrome('/usr/bin/chromedriver',options=options)
    driver = webdriver.Chrome(service=service, options=options)
except Exception as e:
    log_error(f"Ошибка подключения драйвера {str(e)}", exc_info=True)
    raise

driver.get("https://online.moysklad.ru/doLogon")

username = driver.find_element(By.NAME, 'j_username')
password = driver.find_element(By.NAME, 'j_password')
# ставим логин/пароль
username.send_keys(settings['USERNAME'])
password.send_keys(settings['PASSWORD'])
# авторизуемся
driver.find_element(By.NAME, 'submitButton').click()


def is_visible(driver, locator, timeout=10):
    try:
        WebDriverWait(driver, timeout).until(EC.visibility_of_element_located((By.CLASS_NAME, locator)))
        return True
    except TimeoutException:
        log_error(f"is_visible", exc_info=True)
        return False

def wait_while_class_destroy(driver, total_wait=30, key=""):
    
    elem = driver.find_elements("xpath", "//table[@class='b-content-table cellTableRefreshing']")
    elem2 = driver.find_elements("xpath", "//table[@class='cellTableRefreshing']")
    
    if key in ['TURNOVER_FLOW','TURNOVER','GROUP','CUSTOMERS','OPT','VED','ACTIVE_CLIENT','STOCK_REPORT',]:
        print('wait_while_class_destroy', key, len(elem), len(elem2), 'total_wait', total_wait)
    
    if isinstance(elem, list) and len(elem) == 0 and isinstance(elem2, list) and len(elem2) == 0:
        print('wait_while_class_destroy return True', key)
        return True
    else:
        total_wait -= 3
        time.sleep(3)
        if total_wait > 0:
            return wait_while_class_destroy(driver, total_wait, key)
        else:
            print('wait_while_class_destroy return False', key)
            return False
    #return False
    
    """
    try:
        #elem = driver.find_element_by_xpath('//*[contains(@class, "class_name")]')
        elem = driver.find_elements("xpath", "//table[@class='b-content-table cellTableRefreshing']")
        elem2 = driver.find_elements("xpath", "//table[@class='cellTableRefreshing']")
        

    finally:
        if key in ['TURNOVER_FLOW','TURNOVER','GROUP','CUSTOMERS','OPT','VED','ACTIVE_CLIENT','STOCK_REPORT',]:
            print('wait_while_class_destroy', key, len(elem), len(elem2), 'total_wait', total_wait)
        if isinstance(elem, list) and len(elem) == 0 and isinstance(elem2, list) and len(elem2) == 0:
            return True
        else:
            total_wait -= 1
            time.sleep(1)
            if total_wait > 1: wait_while_class_destroy(driver, total_wait, key)
        
    return False
    """
        
def saveFile(driver, param):
    #element = driver.find_element_by_css_selector('#site') 
    element = driver.find_element(By.CSS_SELECTOR, '#site')

    # Retrieve the innerHTML of the element
    inner_html = element.get_attribute('innerHTML')
    
    file_html = path_parser_html + param["UNIQUE_ID"] + '.txt'
    try:
        html = inner_html.encode('utf-8', 'ignore').decode('utf-8')
        if html is None:
            print('is none', param)
        with open(file_html, 'w', encoding='utf-8') as file:
            file.write(inner_html.encode('utf-8', 'ignore').decode('utf-8'))
            
        res = True
    except Exception as e:
        log_error(f"saveFile {str(e)}", exc_info=True)
        with open(file_html, 'w', encoding='utf-8') as file:
            file.write(inner_html.encode('ascii', 'ignore').decode('ascii'))
        
        res = False
    return res
        
def saveResult(iterator, file_result, param):
    try:
        with open(file_result, 'w', encoding='utf-8') as file:
            file.write(json.dumps(param))
    except Exception as e:
        log_error(f"saveResult {str(e)}", exc_info=True)
        
    
# проходим по страницам каждый в новом табе. может быстрее получится
#for key, page in settings['PAGES'].items():
#    try:
#        driver.execute_script("window.open('" + page["URL"] + "', '" + page["UNIQUE_ID"] + "');")
#    except Exception as e:
#        print('traceback', traceback.format_exc())
        
for key, page in settings['PAGES'].items():
    try:
        # Открываем страницу в новой вкладке
        driver.execute_script("window.open('about:blank', '" + page["UNIQUE_ID"] + "');")
        driver.switch_to.window(page["UNIQUE_ID"])
        driver.get(page["URL"])
        
        # Вместо открытия новой вкладки новое окно
        #driver.execute_script("window.open('{}', '_blank');".format(page["URL"]))
        #WebDriverWait(driver, 60).until(
        #    lambda d: d.execute_script("return document.readyState") == "complete"
        #)
    except Exception as e:
        log_error(f"Error opening tab for {key} {str(e)}", exc_info=True)
        #print(f'Error opening tab for {key}:', traceback.format_exc())
        
# после открытия всех табов проходим по ним
save_page = 0
fail_page = 0
all_page = 0

# после открытия всех табов проходим по ним
save_page = 0
fail_page = 0
all_page = 0
for key, page in settings['PAGES'].items():
    try:
        print('start ', key)
        driver.switch_to.window(page["UNIQUE_ID"])
        all_page += 1
        
        await_class = page["AWAIT_CLASS"] if 'AWAIT_CLASS' in page else 'floating-footer'
        
        elem = WebDriverWait(driver, 50).until(
            EC.presence_of_element_located((By.CLASS_NAME, await_class)) #This is a dummy element
        )
        # time.sleep(3)

        if saveFile(driver, {'UNIQUE_ID': page["UNIQUE_ID"]}):
            save_page += 1
        else:
            fail_page += 1
        
        saveResult(all_page, settings['FILE_RESULT'], {"status": "in_proccess", "save_page": save_page, "fail_page": fail_page})
        
        if 'ADDITIONAL_PAGE' in page and len(page['ADDITIONAL_PAGE']) > 0:
            """if is_visible(driver, 'lognex-popup-panel', 5) == True:
                #close_popup_button = driver.find_element_by_css_selector('.close-icon-dark')
                close_popup_button = driver.find_element(By.CSS_SELECTOR, '.close-icon-dark')
                close_popup_button.click()
                #ActionChains(driver).move_to_element(close_popup_button).click().perform()
            """
            if is_visible(driver, 'dynamic-filter-panel', 5) == False:
                #filter_button = driver.find_element_by_css_selector('.filter-button')
                filter_button = driver.find_element(By.CSS_SELECTOR, '.filter-button')
                ActionChains(driver).move_to_element(filter_button).click().perform()
                #filter_button.click()
                # time.sleep(1)
            
            #el = driver.find_element_by_tag_name('body')
            el = driver.find_element(By.TAG_NAME, 'body')
            el.screenshot('/home/bitrix/logs/ms/parser/img/' + page["UNIQUE_ID"] + '.png')
            
            #list_of_all_elements = driver.find_elements("xpath", "//table[@class='jscalendar-periodpanel-inner']//tbody//tr//input[@class='gwt-TextBox gwt-DateBox']")
            list_of_all_elements = driver.find_elements("xpath", "//div[@class='dynamic-filter-panel']//input[@class='gwt-TextBox gwt-DateBox']")

            #submit_button = driver.find_element_by_css_selector('.find')
            submit_button = driver.find_element(By.CSS_SELECTOR, '.find')
            """
            if key == 'RETURN_SUPPLIERS':
                el2 = driver.find_element_by_tag_name('body')
                el2.screenshot('/home/bitrix/logs/ms/' + key + '_asdasd.png')
                file_html = path_parser_html + '___________.txt'
                inner_html = el2.get_attribute('innerHTML')
                with open(file_html, 'w', encoding='utf-8') as file:
                    file.write(inner_html.encode('utf-8', 'ignore').decode('utf-8')) 
            """
            for add_page in page['ADDITIONAL_PAGE']:
                if 'SKIP' in add_page:
                    continue
                
                all_page += 1
                
                if 'DATE_FROM' in add_page['FILTER']:
                    date_from = list_of_all_elements[0]
                    date_from.clear()
                    date_from.send_keys(add_page['FILTER']['DATE_FROM'])

                if 'DATE_TO' in add_page['FILTER']:
                    date_to = list_of_all_elements[1]
                    date_to.clear()
                    date_to.send_keys(add_page['FILTER']['DATE_TO'])

                #submit_button.click()
                ActionChains(driver).move_to_element(submit_button).click().perform()
                time.sleep(1)
                
                #el2 = driver.find_element_by_tag_name('body')
                el2 = driver.find_element(By.TAG_NAME, 'body')
                el2.screenshot('/home/bitrix/logs/ms/parser/img/' + key + '_' + add_page['DATE'] + '.png')
                
                #if is_visible(driver, 'b-transparent-fixed-panel'):
                #if is_visible(driver, 'cellTableRefreshing') == False:
                #if is_visible(driver, 'cellTableHeader'):
                #r = wait_while_class_destroy(driver, 10, key)
                #print('wait_while_class_destroy res', key, r)
                if wait_while_class_destroy(driver, 20, key) == True:
                    #el2 = driver.find_element_by_tag_name('body')
                    el2 = driver.find_element(By.TAG_NAME, 'body')
                    el2.screenshot('/home/bitrix/logs/ms/parser/img/' + key + '_' + add_page['DATE'] + '_success.png')
                    if saveFile(driver, {'UNIQUE_ID': add_page["UNIQUE_ID"]}):
                        save_page += 1
                    else:
                        fail_page += 1
                else:
                    #el2 = driver.find_element_by_tag_name('body')
                    el2 = driver.find_element(By.TAG_NAME, 'body')
                    el2.screenshot('/home/bitrix/logs/ms/parser/img/' + key + '_' + add_page['DATE'] + '_error.png')
                    #saveFile(driver, {'UNIQUE_ID': add_page["UNIQUE_ID"] + '_error'})
                    fail_page += 1
                    
                saveResult(all_page, settings['FILE_RESULT'], {"status": "in_proccess", "save_page": save_page, "fail_page": fail_page})
    except Exception as e:
        #pp(['traceback page', key, traceback.format_exc()])
        log_error(f"traceback page {key} {str(e)}", exc_info=True)
        fail_page += 1

saveResult(all_page, settings['FILE_RESULT'], {"status": "end", "save_page": save_page, "fail_page": fail_page})

# проходим по страницам  в одном окне
"""
for page in settings['PAGES']:
    try:
        # driver.get(match_page[page])
        print('url - ', page["URL"])
        driver.get(page["URL"])
        driver.refresh()
        elem = WebDriverWait(driver, 30).until(
            EC.presence_of_element_located((By.CLASS_NAME, "floating-footer")) #This is a dummy element
        )
        page_source = driver.page_source
        #file_html = path_parser_html + page["unique_id"] + '.txt'
        #print('----------------', file_html, 'page_source', driver)
        #fileToWrite = open(file_html, "w")
        #fileToWrite.write(page_source)
        #fileToWrite.close()
        
        #yourstring = page_source.encode('ascii', 'ignore').decode('ascii')
        page_source = page_source.encode('ascii', 'ignore').decode('ascii')
        file_html = path_parser_html + page["UNIQUE_ID"] + '.txt'
        print('----------------', page)
        fileToWrite = open(file_html, "w")
        fileToWrite.write(page_source)
        fileToWrite.close()
        
        #driver.execute_script("document.getElementById('site').innerHTML='';")
        
        #driver.get('https://online.moysklad.ru/app')
    except:
        print('traceback', traceback.format_exc())
    finally:
        # сохраняем html
        #page_source = driver.page_source
        #driver.get('https://online.moysklad.ru/app')
        
        #elem = WebDriverWait(driver, 30).until(
        #    EC.presence_of_element_located((By.CLASS_NAME, "l-fixed-width-page")) #This is a dummy element
        #)
        pass
"""

driver.close()
driver.quit()