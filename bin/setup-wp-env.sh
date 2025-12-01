#!/usr/bin/env bash

## Flush permalinks
npm run wp-env run cli -- wp rewrite structure /%postname%/ --hard
npm run wp-env run tests-cli -- wp rewrite structure /%postname%/ --hard

npm run wp-env run cli -- wp rewrite flush --hard
npm run wp-env run tests-cli  -- wp rewrite flush --hard

# Get install Path
cd $(wp-env install-path)


# Install pdo on tests-cli
if [[ $(docker compose exec -T -u root tests-cli php -m | grep pdo_mysql) != "pdo_mysql" ]]; then
	echo "Installing: pdo_mysql Extension on tests-cli."
	if docker compose exec -T -u root tests-cli docker-php-ext-install pdo_mysql; then
		if [[ $(docker compose exec -T -u root tests-cli php -m | grep pdo_mysql) == "pdo_mysql" ]]; then
			echo "pdo_mysql Extension on tests-cli: Installed."
		else
			echo "ERROR: pdo_mysql Extension on tests-cli: Installation command succeeded but extension not loaded." >&2
			exit 1
		fi
	else
		echo "ERROR: pdo_mysql Extension on tests-cli: Installation failed." >&2
		exit 1
	fi
else
	echo "pdo_mysql Extension on tests-cli: Already installed."
fi
