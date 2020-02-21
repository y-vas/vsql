from time import sleep
from faker import Faker
from selenium import webdriver
from faker import Faker
import configparser

config = configparser.ConfigParser()
config.read('../../.env',encoding='utf-8-sig')
cnf = config['ENVIROMENT_VARIABLES']
options = webdriver.ChromeOptions()
options.add_argument('--load-extension=path/to/the/extension')

class Unit():
    def __init__(self):
        self.host = cnf['HOST_NAME']
        self.page = f"http://{self.host}/es/registre"
        self.driver = webdriver.Chrome()
        self.driver.get( self.page )
        self.fk = Faker()

    def re(self,ru):
        self.driver.get( f'http://{self.host}/{ru}' )

    def go(self,id):
        elem = self.driver.find_element_by_xpath(f'//*[@id="{id}"]')
        self.driver.execute_script("return arguments[0].scrollIntoView();", elem)
        return elem

    def get(self,id):
        return self.driver.find_element_by_xpath(f'//*[@id="{id}"]')

    def clk(self,id):
        elem = self.get(id)
        try:
            elem.click()
        except:
            self.go(id).click()

    def set(self,id,what=0):
        self.get(id).send_keys(cnf[what])

    def sel(self,id,what):
        self.driver.execute_script(f"return arguments[0].selectedIndex = {what};", self.get(id))

    def fake(self,id,what):
        fake = self.fk
        try: val = eval(f'self.{what}')
        except: val = eval(f'fake.{what}()')
        self.get(id).send_keys(val)
        setattr(self, what,val)

    def fak(self,id,what):
        fake = self.fk
        val = eval(f'fake.{what}()')
        self.get(id).send_keys(val)
