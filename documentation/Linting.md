# Linting

As a helpful development tool, you can enable automatic linting before commiting.
1. Run `npm install`. 
2. Before development, run `composer install`
3. After you're done run `composer install --no-dev` to remove development dependencies

(Steps 2 and 3 will be removed once we have an automated build process. See https://github.com/wp-graphql/wp-graphql/issues/224)

Your changed files will now be linted via phpcs and your commit will fail with a list of errors if there are any.