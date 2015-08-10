# -*- coding: utf-8 -*-

from lib.user import login
from lib.logout import logout
from lib.utils import create_webdriver  # Changed
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

import time

success = True
wd = create_webdriver()


def is_alert_present(wd):
    try:
        wd.switch_to_alert().text
        return True
    except:
        return False

try:
    login(wd)
    wd.find_element_by_link_text("Mapbender Demo Map").click()
    wd.find_element_by_xpath("//label[contains(text(), 'POI')]").click()
    element = WebDriverWait(wd, 10).until(
        EC.visibility_of_element_located((By.CSS_SELECTOR, "div.popup"))
    )
    wd.find_element_by_tag_name("svg").click()
    time.sleep(2)
    subtitle = wd.find_element_by_css_selector("span.popupSubTitle")
    coordinates = subtitle.get_attribute('innerHTML') 
    #return python variable to browser
    '''wd.execute_script("""var content = arguments[0];console.log(content);""", content)'''
    if len(coordinates) > 0:
        wd.execute_script("console.log('success');")
        time.sleep(2)
    else:
        wd.execute_script("console.log('NoSuccess');")
        success = False

    #logout(wd)

except Exception as e:  # Changed ff
    raise e
    wd.quit()
    #raise e
finally:
    wd.quit()
    if not success:
        raise Exception("Test failed.")

