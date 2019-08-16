## Contribute to WPGraphQL

WPGraphQL welcomes community contributions, bug reports and other constructive feedback.

When contributing please ensure you follow the guidelines below so that we can keep on top of things.

## Getting Started

* __Do not report potential security vulnerabilities here. Email them privately to our security team at 
[info@wpgraphql.com](mailto:info@wpgraphql.com)__
* Before submitting a ticket, please be sure to replicate the behavior with no other plugins active and on a base theme like Twenty Seventeen.
* Submit a ticket for your issue, assuming one does not already exist.
  * Raise it on our [Issue Tracker](https://github.com/wp-graphql/wp-graphql/issues)
  * Clearly describe the issue including steps to reproduce the bug.
  * Make sure you fill in the earliest version that you know has the issue as well as the version of WordPress you're using.

## Making Changes

* Fork the repository on GitHub
* Make the changes to your forked repository
  * Ensure you stick to the [WordPress Coding Standards](https://codex.wordpress.org/WordPress_Coding_Standards)
* When committing, reference your issue (if present) and include a note about the fix
* If possible, and if applicable, please also add/update unit tests for your changes
* Push the changes to your fork and submit a pull request to the 'develop' branch of this repository

## Code Documentation

* We strive for full doc coverage and follow the standards set by phpDoc
* Please make sure that every function is documented so that when we update our API Documentation things don't go awry!
	* If you're adding/editing a function in a class, make sure to add `@access {private|public|protected}`
* Finally, please use tabs and not spaces.

At this point you're waiting on us to merge your pull request. We'll review all pull requests, and make suggestions and changes if necessary.

## Issue Triage
Below is an outline on the process we follow for issue triage. 

There are 4 Github Project Boards for the WPGraphQL organization:

- **1: Issue Intake** https://github.com/orgs/wp-graphql/projects/5
  - Each day this board is used to do initial "first touch" triage of issues. When new issues are opened on any of the WPGraphQL repos, they will be brought into this Project. 
  - Once open issues are in this project, time should be taken to read the issue and label it with appropriate labels. Some of the key labels would be:
    - **:rocket: Actionable**: This signifies that the issue has enough detail for someone to take action and create a Pull Request to resolve it.
    - **Needs Discussion**: This signifies that there is some ambiguity with the issue and more detail is needed before it can be actionable.
    - **Question**: This signifies that the issue was a question that needs answered, but isn't an actionable item that will lead to a pull request.
  - Along with labeling, related issues, if any, should also be tagged at this time. 
  
 - **2: Question Triage**: https://github.com/orgs/wp-graphql/projects/6
   - Each day this board is used to triage issues labeled as "Question"
   - If the question needs more information from the issue creator, it can be replied to, labeled with "Awaiting Response" and placed in the "Waiting for Response" Project column.
   - Once a question has been answered and the issue is closed, it can be closed and moved to the "Done" column.

- **3: Discussion Triage**: https://github.com/orgs/wp-graphql/projects/7
  - Each day this board is used to triage issues labeled as "Needs Discussion"
  - Once enough discussion has occurred and there are enough details to take action the issue should be labeled as "Actionable"
    - If the discussion deems that the Issue cannot/should not be addressed, the issue should be closed and moved to the "Done" column.
    
- **4: Actionable Issues**: https://github.com/orgs/wp-graphql/projects/1
  - Each day this board is used to dictate the priorities of the issues across the organization. 
  - Issues that are actionable will first be placed into the "Needs Prioritized" column
  - Issues will then be moved to the "Prioritized" Column in order of where they fall in priority. 
  - When an issue is being worked on, it should be moved from the "Prioritized" Column to the "In Progress" Column
    - When an issue is completed, it should be closed and moved to the "Done" column
    - If an issue couldn't be completed for whatever, but still needs to be, it should be moved out of the "In Progress" column and back into the top of "Prioritized" column.

  
> **NOTE:** This CONTRIBUTING.md file was forked from [Easy Digital Downloads](https://github.com/easydigitaldownloads/easy-digital-downloads/blob/master/CONTRIBUTING.md)
