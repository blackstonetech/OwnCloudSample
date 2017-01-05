docker run --name mysql -v $PWD/db/conf.d:/etc/mysql/conf.d -v $PWD/db/initdb.d:/docker-entrypoint-initdb.d -v $PWD/db/data:/var/lib/mysql -e MYSQL_ROOT_PASSWORD=changeme -d mysql


docker run --name mediawiki --link mysql:mysql -p 8080:80 -v $PWD/config/LocalSettings.php:/var/www/html/LocalSettings.php -v $PWD/images:/var/www/html/images -v $PWD/extensions:/var/www/html/extensions -v $PWD/logs:/var/log/mediawiki -d synctree/mediawiki

### Resources
https://www.mediawiki.org/wiki/Manual:How_to_debug#Setting_up_a_debug_log_file

