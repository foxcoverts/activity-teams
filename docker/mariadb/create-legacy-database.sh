#!/usr/bin/env bash

# Creates the climbing_legacy database on first MariaDB volume init (Sail).
# For an existing volume, run: php artisan legacy:load-dump

/usr/bin/mariadb --user=root --password="$MYSQL_ROOT_PASSWORD" <<-EOSQL
    CREATE DATABASE IF NOT EXISTS climbing_legacy;
EOSQL

if [ -n "$MYSQL_USER" ]; then
/usr/bin/mariadb --user=root --password="$MYSQL_ROOT_PASSWORD" <<-EOSQL
    GRANT ALL PRIVILEGES ON \`climbing_legacy\`.* TO '$MYSQL_USER'@'%';
EOSQL
fi
