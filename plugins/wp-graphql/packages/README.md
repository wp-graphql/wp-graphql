# Packages

This directory contains the JavaScript packages for WPGraphQL.

These packages are used to build the tooling that makes up the GraphiQL IDE in the admin.

## Running Locally

If you're working on the GraphiQL App locally, you can follow these steps to run GraphiQL in dev mode:

### Install Dependencies

From the root of the plugin directory, run the following command:

- `npm install`

### Run in Dev Mode

Running the app in dev mode allows for the changes to the code to be re-built immediately and reduces the time for the feedback loop. You can refresh the WP Admin as you're working and see your changes right away.

From the root of the plugin directory, run the following command:

- `npm run start`

### Build the app

Building the app allows for the app to be used without being in "dev" mode. It generates new assets in the build directory which are enqueued by the WordPress admin. You'll want to test your changes in a build to ensure the changes will work for users.

From the root of the plugin directory, run the following command:

- `npm run build`
