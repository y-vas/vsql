import os
from jict import jict

jct = jict('xamp.yaml')
dir = jct['dir']

if not os.path.exists( dir ):
    os.system(f'git clone https://github.com/y-vas/coreui-free-laravel-admin-template.git {dir}')

env = jict(f'{dir}.env.example')

env['DB_CONNECTION']='sqlite'
env['DB_HOST']=''
env['DB_PORT']=''
env['DB_DATABASE']=f'{dir}database/database.sqlite'
env['DB_USERNAME']=''
env['DB_PASSWORD']=''

env.save(f'{dir}.env')
print(env)
exit()

os.system( f'sudo apt-get install composer -y' )
os.system( f'sudo apt-get update' )
os.system( f'sudo apt-get install php-xml -y' )
os.system( f'sudo apt-get install php-mbstring' )
os.system( f'sudo apt-get install php -y' )
os.system( f'composer update -d {dir}' )
# os.system( f'npm install {dir}' )
os.system( f'touch {dir}database/database.sqlite' )

# in your app directory
# # generate laravel APP_KEY
# $ php artisan key:generate
# # run database migration and seed
# $ php artisan migrate:refresh --seed
# # generate mixing
# $ npm run dev
# # and repeat generate mixing
# $ npm run dev
