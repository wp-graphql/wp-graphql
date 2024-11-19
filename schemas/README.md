# Schemas

This directory contains the JSON schemas for the various types of data that are used in the project.

## Purpose

The JSON schemas in this directory serve multiple purposes:
- **Validation**: They ensure that the JSON data used in the project adheres to a predefined structure, which helps maintain data integrity and consistency.
- **Autocompletion**: Code editors and other tools can use these schemas to provide autocompletion and validation features, making it easier for developers to write and maintain JSON data.

## Usage

### Extensions Page in WordPress Admin

The schemas are used by the Extensions page in the WordPress admin to validate the `extensions.json` file. This ensures that the extensions listed on the page meet the required criteria and are correctly formatted.

### GitHub Workflow

A GitHub Workflow is set up to validate the `src/Admin/Extensions/extensions.json` file whenever it changes. This workflow ensures that any pull requests (PRs) from the community to add or remove extensions continue to meet the criteria defined in the schema. This helps maintain the quality and consistency of the extensions listed in the project.

## How to Use the Schemas

1. **Validation**: Use tools like `ajv` or `jsonschema` to validate your JSON data against the schemas.
2. **Autocompletion**: Configure your code editor to use the schemas for autocompletion and validation. Most modern code editors, like VSCode, support JSON schema validation out of the box.

For more information on how to submit an extension, see [docs/submit-extension.md](../docs/submit-extension.md).