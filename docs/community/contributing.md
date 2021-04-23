---
uri: "/docs/contributing/"
title: "Contributing"
---

This document will be most useful for developers that want to contribute to WPGraphQL and want to run the docker container locally as well as utilize xdebug for debugging and tracing.

In order to continue, you should follow steps to setup Docker running on your machine.

### Build the WordPress Site

The `app` docker image starts a running WordPress site with the local wp-graphql directory installed and activated. Local changes to the source code is immediately reflects in the app.

First step, clone the source for wp-graphql from github.
<pre class="wp-block-code"><code>git clone git@github.com:wp-graphql/wp-graphql.git</code></pre>

Build the plugin and dependencies:
<pre class="wp-block-code"><code>composer install</code></pre>

Or if you don&#8217;t have composer installed or prefer building it in a docker instance:
<pre class="wp-block-code"><code>docker run -v $PWD:/app composer --ignore-platform-reqs install</code></pre>

Build the app and testing docker images:
<pre class="wp-block-code"><code>composer build-app
composer build-test</code></pre>

In one terminal window, start the WordPress app:
<pre class="wp-block-code"><code>composer run-app</code></pre>

In your web browser, open the site, <a href="http://localhost:8091" target=_blank>http://localhost:8091</a>.  And the WP admin at <a href="http://localhost:8091/wp-admin" target=_blank>http://localhost:8091/wp-admin</a>. Username is 'admin'. Password is 'password'.

### Using XDebug

#### Local WordPress Site With XDebug

Use the environment variable USING_XDEBUG to start the docker image and WordPress with xdebug configured to use port 9003 to communicated with your IDE.
<pre class="wp-block-code"><code>
export USING_XDEBUG=1
composer run-app</code></pre>

You should see output in the terminal like the following examples that indicate xdebug is indeed enabled and running in the app:
<pre class="wp-block-code"><code>
app_1      | Enabling XDebug 3
app_1      | [01-Apr-2021 04:43:53 UTC] Xdebug: [Step Debug] Could not connect to debugging client. Tried: host.docker.internal:9003 (through xdebug.client_host/xdebug.client_port) :-(
</code></pre>

Start your IDE, like VSCode. Enable xdebug and set breakpoints. Load pages in your browsers and you should experience the IDE pausing the page load and showing the breakpoint.

#### Using XDebug with unit Tests

See the testing page on running the unit test suite.  These instructions show how to enable xdebug for those unit tests and allow debugging in an IDE.

Use the environment variable USING_XDEBUG to run tests with xdebug configured to use port 9003 to communicated with your IDE.

<pre class="wp-block-code"><code>export USING_XDEBUG=1
composer run-tests</code></pre>

Use the environment variable SUITES to specify individual test files for quicker runs.

#### Configure VSCode IDE Launch File

Create or add the following configuration to your .vscode/launch.json in the root directory. Restart VSCode. Start the debug listener before running the app or testing images.

If you have WordPress core files in a directory for local development, you can add the location to the `pathMappings` for debug step through.
<pre class="wp-block-code"><code>{
    "version": "0.2.0",
    "configurations": [
        {
            "name": "Listen for Xdebug",
            "type": "php",
            "request": "launch",
            "port": 9003,
            "xdebugSettings": {
                "max_children": 128,
                "max_data": 1024,
                "max_depth": 3,
                "show_hidden": 1
            },
            "pathMappings": {
                "/var/www/html/wp-content/plugins/wp-graphql": "${workspaceFolder}",
                "/var/www/html": "${workspaceFolder}/wordpress",
            }
        }
    ]
}</code></pre>
