![Logo](./img/logo.png)

# WPGraphQL Documentation Site

[docs.wpgraphql.com](https://docs.wpgraphql.com) • <a href="https://wpgql-slack.herokuapp.com/" target="_blank">Join Slack</a>

This repository contains the content and Gatsby site for the [docs.wpgraphql.com](https://docs.wpgraphql.com) site.

[![Netlify Status](https://api.netlify.com/api/v1/badges/58a7d0f1-5002-42d6-a570-eff7468e1575/deploy-status)](https://app.netlify.com/sites/wpgraphql-docs/deploys)


## Gatsby
This site is built using [Gatsby](https://gatsbyjs.org).

## Contributing
The content of this site is all contained in .mdx (Markdown + JSX) files within the repository, so to contribute new content, help fix typos, etc, you can make a pull request to this repo. 

### Updating Content
If you need to update existing content, you can find the content of the site within the `/src/content` directory. There are sub-directories each containing relevant `.mdx` files. Edit the content of the page you would like to change, then open a Pull Request to this repo with your changes. 

### Creating New Pages
If you would like to create a _new_ page on the site, create a new `.mdx` page in the appropriate sub-directory of the `/src/content` directory. 

Add some *frontmatter* at the top of the file including at least a title and description. 

For example:

```
---
title: Name of new page
description: Very brief of the pages purpose
---
```

Then write the content of the page using Markdown and supported JSX components (see below)

#### Add the new page to the navigation
The navigation is controlled by the `/src/content/nav.yml` file. Add your page to the `nav.yml` file in a logical place within the hierarchy, and make sure to include both an `id` and `tite`. The `id` should match the file name. So if you created `new-page.mdx`, then the `id` should be `new-page`. The `title` should be the text that should show in the menu for users to navigate to the page.

### Running the site Locally
It's not necessary to run the site locally to contribute, but if you would like to get it up and running on your machine, below are instructions for running the WPGraphQL Docs site locally. 

- Make sure you have [node](https://nodejs.org/en/dow) installed on your machine
- Make sure you have Gatsby CLI installed. You can install with the following command: `npm i -g gatsby-cli`
- Clone this repo
- Navigate to the cloned repo directory. ex: `cd /path/to/docs.wpgraphql.com`
- Within that directory, create a file named: `.env.development`
  - [Create a Github Token](https://help.github.com/en/articles/creating-a-personal-access-token-for-the-command-line)
  - Add the token to `.env.development` file like so: `GITHUB_TOKEN=123456` (replacing 123456 with your token)
- Using the command line, run the command `npm install` to install dependencies
- Using the command line, run the command `gatsby develop` to start the site
