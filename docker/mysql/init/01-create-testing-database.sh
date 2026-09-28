#!/bin/sh
set -e

mysql --user=root --password="${MYSQL_ROOT_PASSWORD}" <<SQL
CREATE DATABASE IF NOT EXISTS \`ecommerce_testing\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON \`ecommerce_testing\`.* TO '${MYSQL_USER}'@'%';
FLUSH PRIVILEGES;
SQL
