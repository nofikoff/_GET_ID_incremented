#!/bin/bash
# Runs once, on the first start of an empty data volume. Shell rather than .sql because the
# grantee is the application user from DB_USERNAME, which plain SQL cannot read.
set -euo pipefail

MYSQL_PWD="${MYSQL_ROOT_PASSWORD}" mysql --protocol=socket -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \`getid_test\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON \`getid_test\`.* TO '${MYSQL_USER}'@'%';
SQL
