#!/usr/bin/env bash

## Flush permalinks
npm run wp-env run cli -- wp rewrite structure /%postname%/ --hard
npm run wp-env run tests-cli -- wp rewrite structure /%postname%/ --hard

npm run wp-env run cli -- wp rewrite flush --hard
npm run wp-env run tests-cli  -- wp rewrite flush --hard

# Get install Path
cd $(wp-env install-path)

# Reload Apache flag
RELOAD=false

# Install pdo on tests-cli
if [[ $(docker compose exec -T -u root tests-cli php -m | grep pdo_mysql) != "pdo_mysql" ]]; then
	echo "Installing: pdo_mysql Extension on tests-cli."
	docker compose exec -T -u root tests-cli docker-php-ext-install pdo_mysql

	if [[ $(docker compose exec -T -u root tests-cli php -m | grep pdo_mysql) == "pdo_mysql" ]]; then
		echo "pdo_mysql Extension on tests-cli: Installed."
	else
		echo "pdo_mysql Extension on tests-cli: Failed."
	fi
else
	echo "pdo_mysql Extension on tests-cli: Already installed."
fi
