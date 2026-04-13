# WPGraphQL IDE

> A next-gen query editor for [WPGraphQL](https://github.com/wp-graphql/wp-graphql) 🚀

https://github.com/josephfusco/wpgraphql-ide/assets/6676674/88e128f0-a682-42ec-84d8-ff8474d7e683

## Install

See [releases](https://github.com/josephfusco/wpgraphql-ide/releases) to download, or [download from packagist.org](https://packagist.org/packages/josephfusco/wpgraphql-ide) via composer.

## Usage

When this plugin is active, a new settings tab "IDE Settings" will appear in the WPGraphQL settings screen.

  ![WPGraphQL IDE Settings tab showing the admin bar link behavior and Show legacy editor settings](https://github.com/wp-graphql/wpgraphql-ide/assets/6676674/59236b4c-0019-40a8-ae9b-a1228997f30c)

## Breaking Changes Policy

We are committed to maintaining a stable and reliable interface for users of our WordPress plugin. To ensure a seamless experience, we adhere to the following policy regarding breaking changes:

### Policy Statement

1. **Access Functions**: We will not introduce any intentional breaking changes to access functions specifically created to interface with the plugin. These functions are the primary means for interacting with our plugin and will remain consistent across updates.

2. **Public Redux Stores**: We commit to maintaining the public Redux stores using `@wordpress/data` without any breaking changes. This ensures your integrations with our plugin's state management remain stable and predictable.

3. **Internal Refactoring**: Refactoring internal functions and variables not related to the above does not constitute a breaking change. These modifications aim to enhance the plugin's internal structure and performance without impacting the intentionally public API.

4. **Semantic Versioning**: If a breaking change is necessary, we will adhere to [Semantic Versioning](https://semver.org/) (SemVer). This means any breaking changes will result in an increment of the major version number, clearly signaling the change.

### Access Functions Documentation

Please use the designated access functions to interface with our plugin. These functions are designed to provide stable interaction points and are detailed in [ACCESS_FUNCTIONS.md](ACCESS_FUNCTIONS.md).

By following this policy, we aim to build trust and reliability, ensuring that your integrations remain functional and stable with each new release.

### Custom Hooks Documentation

See [ACTIONS_AND_FILTERS.md](ACTIONS_AND_FILTERS.md).

## Privacy Policy

WPGraphQL IDE uses [Appsero](https://appsero.com) SDK to collect some telemetry data upon user's confirmation. This helps us to troubleshoot problems faster & make product improvements.

Appsero SDK **does not gather any data by default.** The SDK only starts gathering basic telemetry data **when a user allows it via the admin notice**. We collect the data to ensure a great user experience for all our users.

Integrating Appsero SDK **DOES NOT IMMEDIATELY** start gathering data, **without confirmation from users in any case.**

Learn more about how [Appsero collects and uses this data](https://appsero.com/privacy-policy/).

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md)