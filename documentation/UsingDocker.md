# Using Docker
Docker can be used to run tests or a local application instance in an isolated environment. It can also take care of most
of the set up and configuration tasks performed by a developer.   

1. Verify [Docker CE](https://www.docker.com/community-edition) 17.09.0+ is installed:
   ```
   sudo docker --version
   ```
   
1. Verify [Docker Compose](https://docs.docker.com/compose/install/) is installed:
   ```
   sudo docker-compose --version
   ```
1. (Optional, but handy) How to use Docker without having to type, `sudo`.   
   * https://docs.docker.com/install/linux/linux-postinstall/#manage-docker-as-a-non-root-user


## Running Wordpress + wp-graphql
1. Start a local instance of WordPress. This will run the instance in the foreground:
   ```
   ./bin/run-docker-local-app.sh
   ```
1. Visit http://127.0.0.1:8000.

## Using PHPStorm/IntelliJ+XDebug (OS X and Linux)

1. Make sure PHPStorm/IntelliJ is listenting on port 9000 for incoming XDebug connections from the WP container (for more info on remote XDebug debugging, visit https://xdebug.org/docs/remote):
   ![alt text](img/intellij-php-debug-config.png)
   
1. Create a PHP server mapping. This tells the debugger how to map a file path in the container to a file path on the host OS.
   ![alt text](img/intellij-php-servers.png)

1. Create a PHP Debug run configuration.
   ![alt text](img/intellij-php-debug-run-config.png) 

1. Run WordPress+the plugin with XDebug enabled. Here's an example:
   ```
   ./bin/run-docker-local-app-xdebug.sh
   ```

1. Start the debugger:
   ![alt text](img/intellij-php-start-debug.png)
   
1. Now when you visit http://127.0.0.1:8000 you can use the debugger.           


## Using MySQL clients to connect to MySQL containers
1. Run the application with desired sites. Here's an example:
   ```
   ./bin/run-docker-local-app.sh
   ```

1. List the MySQL containers that are running and their MySQL port mappings. These ports will change each time the app is run:  
   ```
   ./bin/list-mysql-containers.sh
   ```
   
   You should see output like the following:
   ```
   aa38d8d7eff1        mariadb:10.2.24-bionic          "docker-entrypoint.s…"   14 seconds ago      Up 13 seconds       0.0.0.0:32772->3306/tcp   docker_mysql_test_1
   ```
   
1. Configure your MySQL client to connect to `localhost` and the appropriate ***host*** port. For example, to connect
   to the MySQL container shown above, have the MySQL client connect with this configuration:
   * IP/Hostname: `localhost`
   * Port: `32772`
   * Database: `wpgraphql_test`
   * User: `root`
   * Password: `testing`

   
## Running tests with Docker

### For developers
You'll need two terminal windows for this. The first window is to start the Docker containers needed for running tests. The
second window is where you'll log into one of the running Docker containers (which will have OS dependencies already installed) and run 
your tests as you make code changes.

1. In the first terminal window, start up a pristine Docker testing environment by running this command:
   ```
   ./bin/run-docker-test-environment.sh
   ```
   This step will take several minutes the first time it's run because it needs to install OS dependencies. This work will
   be cached so you won't have to wait as long the next time you run it. You are ready to go to the next step when you
   see output similar to the following:
   ```
   wpgraphql.test_1  | [Tue Oct 30 15:04:33.917067 2018] [core:notice] [pid 1] AH00094: Command line: 'apache2 -D FOREGROUND'
   
   ```
1. In the second terminal window, access the Docker container shell from which you can run tests:
   ```
   ./bin/run-docker-test-environment-shell.sh
   ```
   You should eventually see a prompt like this:
   ```
   root@cd8e4375eb6f:/tmp/wordpress/wp-content/plugins/wp-graphql
   ```   
1. Now you are ready to work in your IDE and test your changes by running any of the following commands in the second
terminal window):
   ```
   vendor/bin/codecept run wpunit --env docker
   vendor/bin/codecept run functional --env docker
   vendor/bin/codecept run acceptance --env docker
   vendor/bin/codecept run tests/wpunit/NodesTest.php --env docker
   vendor/bin/codecept run tests/wpunit/NodesTest.php:testPluginNodeQuery --env docker
   ```
**Notes:**

* If you make a change that requires `composer install` to be rerun, shutdown the testing environment and restart it to 
automatically rerun the `composer install` in the testing environment.
* Leave the container shell (the second terminal window) by typing `exit`.
* Shutdown the testing environment (the first terminal window) by typing `Ctrl + c` 
* Docker artifacts will *usually* be cleaned up automatically when the script completes. In case it doesn't do the job,
try these solutions:
   * Run this command: `docker system prune`
   * https://docs.docker.com/config/pruning/#prune-containers


### For CI tools (e.g. Travis)

* Run the tests in pristine Docker environments by running any of these commands: 
   ```
   ./bin/run-docker-tests.sh 'wpunit'
   ./bin/run-docker-tests.sh 'functional'
   ./bin/run-docker-tests.sh 'acceptance'
   ```

* Run the tests in pristine Docker environments with different configurations. Here are some examples: 
   ```
   env PHP_VERSION='7.1' ./bin/run-docker-tests.sh 'wpunit'
   env PHP_VERSION='7.1' COVERAGE='true' ./bin/run-docker-tests.sh 'functional'
   ```
If `COVERAGE='true'` is set, results will appear in `docker-output/`.


**Notes:**
* Code coverage for `functional` and `acceptance` tests is only supported for PHP 7.X. 
  

## Updating WP Docker software versions

Make sure the `docker/docker-compose*.yml` files refer to the most recent and specific version of the official WordPress Docker and MySQL compatible images.

Please avoid using the `latest` Docker tag. Once Docker caches a Docker image for a given tag onto your machine, it won't automatically
check for updates. Using an actual version number ensures Docker image caches are updated at the right time.

List of software versions to check:
* Travis config `.travis.yml`
* Test base Dockerfile (`Dockerfile.test-base`)
   * XDebug
   * Official WordPress/PHP Docker image
   * PHP Composer

* XDebug Dockerfile (`Dockerfile.xdebug`)
   * XDebug
