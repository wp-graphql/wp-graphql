---
uri: "/docs/testing/"
title: "Testing"
---

This document will be most useful for developers that want to contribute to WPGraphQL and want to run tests locally.

In order to run tests, you must [clone the plugin from Github](https://github.com/wp-graphql/wp-graphql). Downloading from Composer or Packagist will not include the dev dependencies needed to run tests.

## Testing Locally with Codeception

Running tests locally with Codeception is the fastest way to run tests, but it requires some initial setup on your local machine.

### Pre-requisites

- Command line access
- PHP 7.3+ installed and running on your machine
- MySQL installed and running on your machine
- Composer

### Install WordPress Test Environment

WPGraphQL includes a script to install a local test environment.

Running this script will install WordPress to your machines `tmp` directory, and will add WPGraphQL as a plugin and activate it.

> **NOTE:** Because this installs to a tmp directory, if you restart your computer, this will go away so you would need to run this script again to setup your test environment again.

Run the following command, replacing the variables with data for your local Database

```shell
bin/install-test-env.sh $db_name $db_user $db_pass $db_host latest true
```

For example:

```shell
bin/install-test-env.sh wptests root password 127.0.0.1 latest true
```

This should output similar to the following:

```shell
+ install_wp
+ '[' -d /tmp/wordpress/ ']'
+ mkdir -p /tmp/wordpress/
+ [[ latest == \n\i\g\h\t\l\y ]]
+ [[ latest == \t\r\u\n\k ]]
+ '[' latest == latest ']'
+ local ARCHIVE_NAME=latest
+ download https://wordpress.org/latest.tar.gz /tmp/wordpress.tar.gz
++ which curl
+ '[' /usr/bin/curl ']'
+ curl -s https://wordpress.org/latest.tar.gz
+ tar --strip-components=1 -zxmf /tmp/wordpress.tar.gz -C /tmp/wordpress/
+ download https://raw.github.com/markoheijnen/wp-mysqli/master/db.php /tmp/wordpress//wp-content/db.php
++ which curl
+ '[' /usr/bin/curl ']'
+ curl -s https://raw.github.com/markoheijnen/wp-mysqli/master/db.php
+ install_test_suite
++ uname -s
+ [[ Darwin == \D\a\r\w\i\n ]]
+ local 'ioption=-i .bak'
+ '[' '!' -d /tmp/wordpress-tests-lib ']'
+ mkdir -p /tmp/wordpress-tests-lib
+ svn co --quiet https://develop.svn.wordpress.org/tags/5.5.3/tests/phpunit/includes/ /tmp/wordpress-tests-lib/includes
+ svn co --quiet https://develop.svn.wordpress.org/tags/5.5.3/tests/phpunit/data/ /tmp/wordpress-tests-lib/data
+ '[' '!' -f wp-tests-config.php ']'
+ download https://develop.svn.wordpress.org/tags/5.5.3/wp-tests-config-sample.php /tmp/wordpress-tests-lib/wp-tests-config.php
++ which curl
+ '[' /usr/bin/curl ']'
+ curl -s https://develop.svn.wordpress.org/tags/5.5.3/wp-tests-config-sample.php
++ echo /tmp/wordpress/
++ sed 's:/\+$::'
+ WP_CORE_DIR=/tmp/wordpress/
+ sed -i .bak 's:dirname( __FILE__ ) . '\''/src/'\'':'\''/tmp/wordpress//'\'':' /tmp/wordpress-tests-lib/wp-tests-config.php
+ sed -i .bak s/youremptytestdbnamehere/wptests/ /tmp/wordpress-tests-lib/wp-tests-config.php
+ sed -i .bak s/yourusernamehere/root/ /tmp/wordpress-tests-lib/wp-tests-config.php
+ sed -i .bak s/yourpasswordhere/password/ /tmp/wordpress-tests-lib/wp-tests-config.php
+ sed -i .bak 's|localhost|127.0.0.1|' /tmp/wordpress-tests-lib/wp-tests-config.php
+ wait_for_database_connection
+ set +ex
+ install_db
+ '[' true = true ']'
+ return 0
+ configure_wordpress
+ cd /tmp/wordpress/
+ wp config create --dbname=wptests --dbuser=root --dbpass=password --dbhost=127.0.0.1 --skip-check --force=true
Success: Generated 'wp-config.php' file.
+ wp core install --url=wpgraphql.test '--title=WPGraphQL Tests' --admin_user=admin --admin_password=password --admin_email=admin@wpgraphql.test --skip-email
WordPress is already installed.
+ wp rewrite structure /%year%/%monthnum%/%postname%/
Success: Rewrite structure set.
Success: Rewrite rules flushed.
+ activate_plugin
+ '[' '!' -d /tmp/wordpress//wp-content/plugins/wp-graphql ']'
+ ln -s /Users/your-machine/path/to/wp-graphql /tmp/wordpress//wp-content/plugins/wp-graphql
+ cd /tmp/wordpress/
+ wp plugin activate wp-graphql
Warning: Plugin 'wp-graphql' is already active.
Success: Plugin already activated.
+ wp rewrite flush
Success: Rewrite rules flushed.
+ wp db export /Users/your-machine/path/to/wp-graphql/tests/_data/dump.sql
Success: Exported to '/Users/your-machine/path/to/wp-graphql/tests/_data/dump.sql'.
```

### Configure Test Suites

Within the `/tests/` directory of WPGraphQL are the Codeception `.yml` configuration files.

To run tests locally, copy the `.yml` file for the test suite you want to run and rename it without the `.dist`.

For example:

- copy: `wpunit.suite.dist.yml` to `wpunit.suite.yml`

Then update the details of the `.yml` file to point to your local database by changing the fields:

- dbName
- dbHost
- dbUser
- dbPassword

The other fields should be able to remain the same, but update as necessary.

### Install Composer Dependencies

Run the following script from the root of the WPGraphQL plugin:

```shell
composer install
```

This installs the dev dependencies needed to run the tests.

### Run the tests

To run the tests, run the following commands (you can use `control + c` to exit):

```shell
vendor/bin/codecept run wpunit
```

This will run *all* of the tests of the `wpunit` suite.

To run an individual test file, you can specify the file like so:

```shell
vendor/bin/codecept run tests/wpunit/AccessFunctionsTest.php
```

Or you can specify one specific test like so:

```shell
vendor/bin/codecept run tests/wpunit/AccessFunctionsTest.php:testCustomScalarCanBeUsedInSchema
```

The tests should start running and you should see something similar to the following:

![Screenshot of Codeception tests running in the terminal.](./images/testing-codeception-screenshot.png)

## Testing with Docker

Testing in docker is slower than testing locally directly with Codeception, but it allows you to test in a consistent environment and not have to worry about a local environment issue getting in the way of running the tests. Also allows testing with different versions of PHP and/or WordPress quickly.

### Pre-Requisites

In order to run tests with Docker, you should have Docker running on your machine.

### Setup the Docker Environment

Build and start the Docker testing environment by running this command:

```shell
composer build-test
```

This step will take several minutes the first time it's run because it needs to install OS dependencies. This work will be cached so you won't have to wait as long the next time you run it. You are ready to go to the next step to run the full test suite in the docker container.

Build the environment with specific version of PHP:

```shell
PHP_VERSION=8.1 composer build-test
```

```shell
PHP_VERSION=7.4 composer build-test
```

Build the environment with specific version of WordPress:

```shell
WP_VERSION=6.1 composer build-test
```

```shell
WP_VERSION=5.9 composer build-test
```

Or both

```shell
PHP_VERSION=8.1 WP_VERSION=6.0 composer build-test
```

#### Run the full test suite

```shell
composer run-test
```

#### Run specific tests in testing environment

Use the environment variable SUITES to specify individual files or lists of files to test. This is useful when working on one test file and wanting to limit test execution to the single file or test. Also see the contributing documentation on enabling xdebug in the testing docker environment.

By default the SUITES variable is set to all test types; acceptance,functional,wpunit.  See the .env file in the source root directory.

To run just the acceptance test suite, use the following:

```shell
SUITES=acceptance composer run-test
```

To run just the functional test suite, use the following:

```shell
SUITES=functional composer run-test
```

To run just the wpunit test suite, use the following:

```shell
SUITES=wpunit composer run-test
```

Or a combination of more than one:

```shell
SUITES=acceptance,functional composer run-test
```

To run a specific test file, use something like the following:

```shell
SUITES=functional:BasicPostListCept composer run-test
```

To run a specific test within a test suite file:

```shell
SUITES=wpunit:FiltersTest.php:testFilterGraphqlRequestResults composer run-test
```

You can also specify the PHP and/or WordPress versions to run the tests in those environments. Environment must have been built previously using instructions above.

```shell
PHP_VERSION=8.1 WP_VERSION=6.0 SUITES=acceptance composer run-test
```

**Notes:**

-   If you make a change that requires `composer install` to be rerun, run composer build-app again.

-   Leave the container shell by typing `exit`.

-   Docker artifacts will *usually* be cleaned up automatically when the script completes. In case it doesn't do the job, try these solutions:
    - Run this command: `docker system prune`
    - https://docs.docker.com/config/pruning/#prune-containers

#### Advanced Testing Within Docker Shell

Log into the docker shell prompt:

```shell
docker-compose run --entrypoint bash -- testing
```

Specify the PHP and/or WordPress versions to use that environment. Environment must have been built previously using instructions above.

```shell
PHP_VERSION=8.1 WP_VERSION=6.0 docker-compose run --entrypoint bash -- testing
```

Run the setup script, which also runs the test suite.  This needs to be run at least once after logging into the docker bash shell prompt. If you log out, the settings are not saved and must be re-run after opening the docker shell prompt.

```shell
/usr/local/bin/testing-entrypoint.sh
```

If you want to skip the tests but initiate and setup the docker environment, use the following:

```shell
SUITES= /usr/local/bin/testing-entrypoint.sh
```

After running the initial entry point, change to the plugin diectory:

```shell
cd wp-content/plugins/wp-graphql
```

Run tests like this:

```shell
vendor/bin/codecept run -c codeception.dist.yml wpunit
```

```shell
vendor/bin/codecept run -c codeception.dist.yml acceptance
```

```shell
vendor/bin/codecept run -c codeception.dist.yml functional:BasicPostListCept
```
