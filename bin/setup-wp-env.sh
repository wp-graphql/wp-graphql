#!/usr/bin/env bash

## Flush permalinks
npm run wp-env run cli -- wp rewrite structure /%postname%/ --hard
npm run wp-env run tests-cli -- wp rewrite structure /%postname%/ --hard

npm run wp-env run cli -- wp rewrite flush --hard
npm run wp-env run tests-cli  -- wp rewrite flush --hard

# Enable HTTP Authorization header passthrough for Apache
# This is needed for Application Passwords and other auth methods
# See: https://github.com/wp-graphql/wp-graphql/pull/3448
for container in cli tests-cli; do
	npm run wp-env run $container -- wp eval '
$htaccess_file = ABSPATH . ".htaccess";
if ( ! file_exists( $htaccess_file ) ) {
    echo ".htaccess file not found, skipping Authorization header setup\n";
    exit;
}
$htaccess_content = file_get_contents( $htaccess_file );
$auth_rules = "# BEGIN WPGraphQL Authorization
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteCond %{HTTP:Authorization} ^(.*)
RewriteRule ^(.*) - [E=HTTP_AUTHORIZATION:%1]
</IfModule>
# END WPGraphQL Authorization

";
if ( strpos( $htaccess_content, "WPGraphQL Authorization" ) === false ) {
    file_put_contents( $htaccess_file, $auth_rules . $htaccess_content );
    echo "Added HTTP Authorization rules to .htaccess\n";
} else {
    echo "HTTP Authorization rules already present\n";
}
'
done

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
