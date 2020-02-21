from time import sleep
from core import Unit as Core
import sys

class Unit( Core ):

    def register(self):
        sleep(.5 )
        # self.clk('cliRolCli')
        self.clk('cliRolEst')
        self.fake('cliNom','first_name')
        self.fake('cliCognoms','last_name')
        self.fake('cliTelf1','phone_number')
        self.fake('cliNick','safe_email')
        self.fake('cliContrasenya','md5')
        # self.fake('nomEmp','word')
        self.fake('nomEst','word')
        self.clk('cliCondicions')
        self.clk('create')
        sleep(.5)

    def login(self):
        sleep(.5)
        self.clk('login-rm')
        self.set('logUsuari','USER_EMAIL')
        self.set('logClau','USER_PASS')
        self.clk('log')
        sleep(.5)

    def dadadesestabliment(self):
        sleep(.5)
        self.fake('forAdreca','address')
        self.fake('forCP','postcode')
        self.fake('forPoblacio','city')
        self.fake('forProvincia','state')
        self.sel('forComunitatAutonoma',1)
        self.fake('forWeb','url')
        self.clk('finish')
        sleep(.5)

    def areaprivada(self):
        self.re( 'es/privado' )
        self.clk( 'profile' )
        sleep( 0.5 )

test = Unit()
for i,x in enumerate(sys.argv):
    if i ==0: continue
    eval(f'test.{x}()')
