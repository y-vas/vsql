import os
from jict import jict


exit()
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

os.system( f'sudo apt-get install composer -y' )
os.system( f'sudo apt-get update' )
os.system( f'sudo apt-get install php-xml -y' )
os.system( f'sudo apt-get install php-mbstring' )
os.system( f'sudo apt-get install php -y' )
os.system( f'sudo apt-get install php-sqlite3 -y' )
os.system( f'composer update -d {dir}' )
os.system( f'cd {dir} && composer require doctrine/dbal' )
os.system( f'cd {dir} && npm install' )
os.system( f'touch {dir}database/database.sqlite' )

# in your app directory
# # generate laravel APP_KEY
os.system( f'cd {dir} && php artisan key:generate' )
# # run database migration and seed
os.system( f'cd {dir} && php artisan migrate:refresh --seed' )
# # generate mixing
os.system( f'cd {dir} && npm run dev' )
# # and repeat generate mixing
os.system( f'cd {dir} && npm run dev' )
os.system( f'cd {dir} && php artisan serve' )
