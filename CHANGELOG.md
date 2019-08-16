# Change Log

## [v0.3.4](https://github.com/wp-graphql/wp-graphql/tree/v0.3.4) (2019-05-24)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.3.3...v0.3.4)

**Implemented enhancements:**

- Support multiple Schemas [\#336](https://github.com/wp-graphql/wp-graphql/issues/336)
- Admin Menu Queries [\#219](https://github.com/wp-graphql/wp-graphql/issues/219)
- \#796 - Add srcSet field to media items [\#858](https://github.com/wp-graphql/wp-graphql/pull/858) ([jasonbahl](https://github.com/jasonbahl))
- \#802 - Throw exception for invalid list\_of / non\_null Types [\#856](https://github.com/wp-graphql/wp-graphql/pull/856) ([jasonbahl](https://github.com/jasonbahl))

**Fixed bugs:**

- Cannot define list of non null strings as return type [\#849](https://github.com/wp-graphql/wp-graphql/issues/849)
- Cursor pagination not working with custom ordering [\#818](https://github.com/wp-graphql/wp-graphql/issues/818)
- v0.3.x menuItems connectedObject issue [\#810](https://github.com/wp-graphql/wp-graphql/issues/810)
- Bug/\#810 menu items connected object term issue [\#855](https://github.com/wp-graphql/wp-graphql/pull/855) ([jasonbahl](https://github.com/jasonbahl))
- \#839 - Fix typo with cssClasses [\#853](https://github.com/wp-graphql/wp-graphql/pull/853) ([jasonbahl](https://github.com/jasonbahl))
- \#849 - This ensures that list\_of and non\_null can be used with each other [\#850](https://github.com/wp-graphql/wp-graphql/pull/850) ([jasonbahl](https://github.com/jasonbahl))
- Make the post date field to conform to RFC3339 [\#821](https://github.com/wp-graphql/wp-graphql/pull/821) ([manzoorwanijk](https://github.com/manzoorwanijk))

**Closed issues:**

- Pagination not working for pageBy and taxQuery queries [\#861](https://github.com/wp-graphql/wp-graphql/issues/861)
- Docker tests are failing [\#859](https://github.com/wp-graphql/wp-graphql/issues/859)
- wp-graphql-jwt-authentication plugin doesn't work after upgrading from 0.2.3 -\> 0.3.3? [\#854](https://github.com/wp-graphql/wp-graphql/issues/854)
- Page where "in" query not returning any results [\#844](https://github.com/wp-graphql/wp-graphql/issues/844)
- Pass $source to edge resolver [\#840](https://github.com/wp-graphql/wp-graphql/issues/840)
- menuItem cssClasses is always null [\#839](https://github.com/wp-graphql/wp-graphql/issues/839)
- Hooking WooCommerce Categories to GraphQL  [\#835](https://github.com/wp-graphql/wp-graphql/issues/835)
- Query pages results in server error [\#833](https://github.com/wp-graphql/wp-graphql/issues/833)
- Error when creating comments on latest dev branch [\#827](https://github.com/wp-graphql/wp-graphql/issues/827)
- The post date field does not conform to RFC3339 [\#822](https://github.com/wp-graphql/wp-graphql/issues/822)
- Bug/Enhancement: Report references to missing types in debug mode [\#802](https://github.com/wp-graphql/wp-graphql/issues/802)
- Add src-set to attachments  [\#796](https://github.com/wp-graphql/wp-graphql/issues/796)
- 0.3.0 bug: Queries on 'posts' with category value return internal server error [\#788](https://github.com/wp-graphql/wp-graphql/issues/788)
- Tests for preflight OPTIONS requests [\#260](https://github.com/wp-graphql/wp-graphql/issues/260)
- Fieldname Audit [\#221](https://github.com/wp-graphql/wp-graphql/issues/221)
- Unit Tests for PostObjectsConnection Resolver [\#86](https://github.com/wp-graphql/wp-graphql/issues/86)

**Merged pull requests:**

- Fix orderby with post object cursors [\#864](https://github.com/wp-graphql/wp-graphql/pull/864) ([epeli](https://github.com/epeli))
- Issue-859/CI-6320 | Fixing Docker tests; Updating Docker software versions [\#860](https://github.com/wp-graphql/wp-graphql/pull/860) ([mngi-arogers](https://github.com/mngi-arogers))
- \#260 -  [\#857](https://github.com/wp-graphql/wp-graphql/pull/857) ([jasonbahl](https://github.com/jasonbahl))
- Revert "Adds clear\_schema" [\#852](https://github.com/wp-graphql/wp-graphql/pull/852) ([jasonbahl](https://github.com/jasonbahl))
- Adds clear\_schema [\#847](https://github.com/wp-graphql/wp-graphql/pull/847) ([kidunot89](https://github.com/kidunot89))
- Pass $source to edge resolver [\#841](https://github.com/wp-graphql/wp-graphql/pull/841) ([hsimah](https://github.com/hsimah))

## [v0.3.3](https://github.com/wp-graphql/wp-graphql/tree/v0.3.3) (2019-04-26)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.3.2...v0.3.3)

**Implemented enhancements:**

- \#827 - Add "success" field to comment mutations, so that unauthentica… [\#831](https://github.com/wp-graphql/wp-graphql/pull/831) ([jasonbahl](https://github.com/jasonbahl))

**Fixed bugs:**

- Static $allowed\_post\_types and $allowed\_taxonomies causing funky issues [\#815](https://github.com/wp-graphql/wp-graphql/issues/815)
- 🐛BUGFIX: TermObjectLoader funkiness [\#824](https://github.com/wp-graphql/wp-graphql/pull/824) ([jasonbahl](https://github.com/jasonbahl))
- 🐛 Bug/\#815 static allowed vars cause issues in tests [\#816](https://github.com/wp-graphql/wp-graphql/pull/816) ([jasonbahl](https://github.com/jasonbahl))
- 🐛 BUGFIX [\#812](https://github.com/wp-graphql/wp-graphql/pull/812) ([jasonbahl](https://github.com/jasonbahl))

**Closed issues:**

- register\_graphql\_field type change? [\#826](https://github.com/wp-graphql/wp-graphql/issues/826)
- Object Id returning null [\#825](https://github.com/wp-graphql/wp-graphql/issues/825)
- Custom taxonomies not returning correct data [\#823](https://github.com/wp-graphql/wp-graphql/issues/823)
- Bug: {$type}Id not working [\#813](https://github.com/wp-graphql/wp-graphql/issues/813)
- v0.3.x menu where clause not being respected [\#811](https://github.com/wp-graphql/wp-graphql/issues/811)
- Cannot query field update\_date on type Post [\#809](https://github.com/wp-graphql/wp-graphql/issues/809)
- How to add meta data the right way [\#795](https://github.com/wp-graphql/wp-graphql/issues/795)
- How to create a field that is actually its own document tree \(related custom post to current post\). [\#754](https://github.com/wp-graphql/wp-graphql/issues/754)
- ConnectionResolver doesn't work non-WP objects or arrays [\#517](https://github.com/wp-graphql/wp-graphql/issues/517)

**Merged pull requests:**

- Fix hideEmpty description [\#828](https://github.com/wp-graphql/wp-graphql/pull/828) ([epeli](https://github.com/epeli))
- CI-6319 | Updating PHP composer, Xdebug, docker-compose versions; updating XDebug README info [\#817](https://github.com/wp-graphql/wp-graphql/pull/817) ([mngi-arogers](https://github.com/mngi-arogers))
- Restore missing resolver [\#814](https://github.com/wp-graphql/wp-graphql/pull/814) ([benallfree](https://github.com/benallfree))

## [v0.3.2](https://github.com/wp-graphql/wp-graphql/tree/v0.3.2) (2019-04-18)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.3.01...v0.3.2)

**Fixed bugs:**

- v0.3.x breaks menus [\#805](https://github.com/wp-graphql/wp-graphql/issues/805)
- 🐛 \#805 - BUGFIX for `menuItems{ nodes { ... } }` breaking [\#806](https://github.com/wp-graphql/wp-graphql/pull/806) ([jasonbahl](https://github.com/jasonbahl))
- :bug: BUGFIX [\#804](https://github.com/wp-graphql/wp-graphql/pull/804) ([jasonbahl](https://github.com/jasonbahl))
- \#799 - hook "setup\_types" to init [\#800](https://github.com/wp-graphql/wp-graphql/pull/800) ([jasonbahl](https://github.com/jasonbahl))
- \#797 - TypeRegistry::get\_type\(\) returns php warning if Type doesn't exist [\#798](https://github.com/wp-graphql/wp-graphql/pull/798) ([jasonbahl](https://github.com/jasonbahl))

**Closed issues:**

- {$postType}By entry points are broken! [\#803](https://github.com/wp-graphql/wp-graphql/issues/803)
- Post Types should be configured to "show\_in\_graphql" even outside GQL requests [\#799](https://github.com/wp-graphql/wp-graphql/issues/799)
- get\_type should check `isset\(\)` instead of `null !==` [\#797](https://github.com/wp-graphql/wp-graphql/issues/797)
- The link to the upgrade guide in the release notes for 0.3.0 is wrong [\#784](https://github.com/wp-graphql/wp-graphql/issues/784)
- Users who haven't published any posts should be considered private instead of restricted [\#774](https://github.com/wp-graphql/wp-graphql/issues/774)
- Document upgrade guide for v0.3.0 [\#741](https://github.com/wp-graphql/wp-graphql/issues/741)

## [v0.3.01](https://github.com/wp-graphql/wp-graphql/tree/v0.3.01) (2019-04-08)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.3.0...v0.3.01)

**Closed issues:**

- 0.3.0 bug: "wp\_" Hard coded as table prefix, breaks sites on upgrade [\#785](https://github.com/wp-graphql/wp-graphql/issues/785)

**Merged pull requests:**

- Bug/\#785 post order filter [\#790](https://github.com/wp-graphql/wp-graphql/pull/790) ([jasonbahl](https://github.com/jasonbahl))
- Remove hard coded table prefix [\#787](https://github.com/wp-graphql/wp-graphql/pull/787) ([epeli](https://github.com/epeli))

## [v0.3.0](https://github.com/wp-graphql/wp-graphql/tree/v0.3.0) (2019-04-06)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.2.3...v0.3.0)

**Implemented enhancements:**

- Model Layer: UserRole [\#763](https://github.com/wp-graphql/wp-graphql/issues/763)
- Model Layer: PostTypeLabelDetails [\#762](https://github.com/wp-graphql/wp-graphql/issues/762)
- Model Layer: MediaSize [\#761](https://github.com/wp-graphql/wp-graphql/issues/761)
- Model Layer: MediaItemMeta [\#760](https://github.com/wp-graphql/wp-graphql/issues/760)
- Model Layer: MediaDetails [\#759](https://github.com/wp-graphql/wp-graphql/issues/759)
- Model Layer: Edit Lock [\#758](https://github.com/wp-graphql/wp-graphql/issues/758)
- Model Layer: CommentAuthor [\#757](https://github.com/wp-graphql/wp-graphql/issues/757)
- Model Layer: Avatar [\#756](https://github.com/wp-graphql/wp-graphql/issues/756)
- Model Cleanup Round 2 [\#699](https://github.com/wp-graphql/wp-graphql/issues/699)
- Model Layer: Taxonomy [\#698](https://github.com/wp-graphql/wp-graphql/issues/698)
- Model Layer: PostType [\#697](https://github.com/wp-graphql/wp-graphql/issues/697)
- Model Layer: Posts [\#684](https://github.com/wp-graphql/wp-graphql/issues/684)
- Model Layer: Menu Items [\#610](https://github.com/wp-graphql/wp-graphql/issues/610)
- Model Layer: Menus [\#609](https://github.com/wp-graphql/wp-graphql/issues/609)
- Model Layer: Plugin [\#607](https://github.com/wp-graphql/wp-graphql/issues/607)
- Model Layer: Theme [\#606](https://github.com/wp-graphql/wp-graphql/issues/606)
- Model Layer: Comments [\#605](https://github.com/wp-graphql/wp-graphql/issues/605)
- Model Layer: Users [\#604](https://github.com/wp-graphql/wp-graphql/issues/604)
- Model Layer: Terms [\#603](https://github.com/wp-graphql/wp-graphql/issues/603)
- Expose source on MediaItems with size arg [\#589](https://github.com/wp-graphql/wp-graphql/issues/589)
- Implement Deferred resolvers for connections [\#587](https://github.com/wp-graphql/wp-graphql/issues/587)
- Return a post's visibility in the response [\#375](https://github.com/wp-graphql/wp-graphql/issues/375)
- Add Revision support [\#326](https://github.com/wp-graphql/wp-graphql/issues/326)
- Introduce Model Layer [\#280](https://github.com/wp-graphql/wp-graphql/issues/280)

**Fixed bugs:**

- Model Layer: Should not be able to query "future" posts without proper caps [\#753](https://github.com/wp-graphql/wp-graphql/issues/753)
- fix registerUser mutation [\#742](https://github.com/wp-graphql/wp-graphql/issues/742)
- Auth / accessing private posts [\#486](https://github.com/wp-graphql/wp-graphql/issues/486)
- 🐛 BUGFIX - PHP Warning in DataSource.php [\#727](https://github.com/wp-graphql/wp-graphql/pull/727) ([jasonbahl](https://github.com/jasonbahl))

**Closed issues:**

- createComment mutation authorization checks [\#740](https://github.com/wp-graphql/wp-graphql/issues/740)
- Unapproved comments should not be publicly queryable [\#738](https://github.com/wp-graphql/wp-graphql/issues/738)
- Users should only be "public" if they have a published post [\#734](https://github.com/wp-graphql/wp-graphql/issues/734)
- Sticky Posts are repeated on every page [\#732](https://github.com/wp-graphql/wp-graphql/issues/732)
- Post order returned from cursors is not stable [\#729](https://github.com/wp-graphql/wp-graphql/issues/729)
- get\_registered\_nav\_menu\_locations throws php warnings [\#726](https://github.com/wp-graphql/wp-graphql/issues/726)
- Abstract out visibility fields to get attached to all modeled objects. [\#687](https://github.com/wp-graphql/wp-graphql/issues/687)
- Protect WP\_User-\>user\_pass from leaking [\#618](https://github.com/wp-graphql/wp-graphql/issues/618)
- WPGraphQL Tax Query and Meta Query plugins not compat with v0.1.0 [\#557](https://github.com/wp-graphql/wp-graphql/issues/557)
- Subcategories aren't resolved by DataSource::resolve\_term\_objects\_connection\(\) [\#518](https://github.com/wp-graphql/wp-graphql/issues/518)
- Consider adding capability checks to queries [\#245](https://github.com/wp-graphql/wp-graphql/issues/245)
- Filter the Query to allow for caching and using cached ASTs [\#112](https://github.com/wp-graphql/wp-graphql/issues/112)

**Merged pull requests:**

- CI-6318 | Updating Docker versions to WP 5.1.1 + PHP 7.3 [\#767](https://github.com/wp-graphql/wp-graphql/pull/767) ([mngi-arogers](https://github.com/mngi-arogers))
- Fix for Sticky posts being repeated on every page [\#733](https://github.com/wp-graphql/wp-graphql/pull/733) ([mwidmann](https://github.com/mwidmann))
- WordPress \(VIP\) CodeSniffs to test suite / CI [\#731](https://github.com/wp-graphql/wp-graphql/pull/731) ([renatonascalves](https://github.com/renatonascalves))
- Feature/model layer [\#695](https://github.com/wp-graphql/wp-graphql/pull/695) ([CodeProKid](https://github.com/CodeProKid))

## [v0.2.3](https://github.com/wp-graphql/wp-graphql/tree/v0.2.3) (2019-03-18)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.2.2...v0.2.3)

**Implemented enhancements:**

- Add "TimezoneEnum" Type [\#675](https://github.com/wp-graphql/wp-graphql/issues/675)
- ✨ Add TimezoneEnum Type [\#676](https://github.com/wp-graphql/wp-graphql/pull/676) ([jasonbahl](https://github.com/jasonbahl))

**Closed issues:**

- filters on postobject may not work as expected. global post object is not setup. [\#718](https://github.com/wp-graphql/wp-graphql/issues/718)
- graphql\_map\_input\_fields\_to\_wp\_query filter causes "Internal server error" if $post\_type argument is used. [\#717](https://github.com/wp-graphql/wp-graphql/issues/717)
- playground.wpgraphql.com not showing directives [\#712](https://github.com/wp-graphql/wp-graphql/issues/712)

**Merged pull requests:**

- Add arg filters for menu items [\#719](https://github.com/wp-graphql/wp-graphql/pull/719) ([epeli](https://github.com/epeli))
- MenuLocationEnum fix [\#716](https://github.com/wp-graphql/wp-graphql/pull/716) ([kidunot89](https://github.com/kidunot89))
- Setup postdata on field levels [\#714](https://github.com/wp-graphql/wp-graphql/pull/714) ([epeli](https://github.com/epeli))
- Sync Develop with Release v0.2.2 [\#711](https://github.com/wp-graphql/wp-graphql/pull/711) ([jasonbahl](https://github.com/jasonbahl))

## [v0.2.2](https://github.com/wp-graphql/wp-graphql/tree/v0.2.2) (2019-02-26)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.2.1...v0.2.2)

**Implemented enhancements:**

- Add "AvailableLocalesEnum" Type [\#674](https://github.com/wp-graphql/wp-graphql/issues/674)
- get\_connection\_arg methods are protected and not usable in custom code [\#668](https://github.com/wp-graphql/wp-graphql/issues/668)
- 👌 IMPROVE: make connection methods public [\#669](https://github.com/wp-graphql/wp-graphql/pull/669) ([jasonbahl](https://github.com/jasonbahl))

**Fixed bugs:**

- PHP Warnings in Request.php and Router.php [\#666](https://github.com/wp-graphql/wp-graphql/issues/666)

**Closed issues:**

- graphql\_request\_data filter is executed twice for GET requests [\#707](https://github.com/wp-graphql/wp-graphql/issues/707)
- \[Feature Request\] skip and orderBy arguments for pagination [\#702](https://github.com/wp-graphql/wp-graphql/issues/702)
- Querying custom posttypes by WP id isn't able to resolve post when graphql\_single\_name is uppercase \(like in documentation\) [\#691](https://github.com/wp-graphql/wp-graphql/issues/691)
- Allow pages queries to return flat results instead of parent-child nested results. [\#673](https://github.com/wp-graphql/wp-graphql/issues/673)
- Make more composer install friendly [\#649](https://github.com/wp-graphql/wp-graphql/issues/649)
- Run tests against PHP 7.3 [\#624](https://github.com/wp-graphql/wp-graphql/issues/624)
- Write test for querying term children [\#575](https://github.com/wp-graphql/wp-graphql/issues/575)

**Merged pull requests:**

- Do not execute graphql\_request\_data twice for GET requests [\#708](https://github.com/wp-graphql/wp-graphql/pull/708) ([epeli](https://github.com/epeli))
- Issue 624, CI-6146 | Running tests with WP 5.0.3 + PHP 7.3; Updating PHP composer [\#703](https://github.com/wp-graphql/wp-graphql/pull/703) ([mngi-arogers](https://github.com/mngi-arogers))
- apply lcfirst when generating custom post type id field [\#693](https://github.com/wp-graphql/wp-graphql/pull/693) ([mwidmann](https://github.com/mwidmann))
- Add Access-Control-Max-Age header to cache preflight responses. [\#682](https://github.com/wp-graphql/wp-graphql/pull/682) ([chriszarate](https://github.com/chriszarate))
-  Apply wp\_unslash to query \(GET\) variables to undo wp\_magic\_quotes [\#681](https://github.com/wp-graphql/wp-graphql/pull/681) ([chriszarate](https://github.com/chriszarate))
- Support more WP\_Query options [\#679](https://github.com/wp-graphql/wp-graphql/pull/679) ([craigmcnamara](https://github.com/craigmcnamara))
- add test for term children \#575 [\#671](https://github.com/wp-graphql/wp-graphql/pull/671) ([iimez](https://github.com/iimez))
- 🐛 FIX: This fixes a bug with some potentially undefined variables tha… [\#667](https://github.com/wp-graphql/wp-graphql/pull/667) ([jasonbahl](https://github.com/jasonbahl))
- Feature/media item source [\#660](https://github.com/wp-graphql/wp-graphql/pull/660) ([hsimah](https://github.com/hsimah))
- CI-6012 | Supporting WP 5.0.2; Adding Docker XDebug for Linux; Streamlining Docker logic [\#651](https://github.com/wp-graphql/wp-graphql/pull/651) ([mngi-arogers](https://github.com/mngi-arogers))
- added the .gitattributes file to test composer packaging and distribution [\#650](https://github.com/wp-graphql/wp-graphql/pull/650) ([mikelking](https://github.com/mikelking))

## [v0.2.1](https://github.com/wp-graphql/wp-graphql/tree/v0.2.1) (2019-01-25)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.2.0...v0.2.1)

**Implemented enhancements:**

- Update GraphQL PHP [\#656](https://github.com/wp-graphql/wp-graphql/pull/656) ([jasonbahl](https://github.com/jasonbahl))

**Fixed bugs:**

- 🐛 FIX: Fixes PHP warnings in the Request class [\#662](https://github.com/wp-graphql/wp-graphql/pull/662) ([jasonbahl](https://github.com/jasonbahl))
- Update GraphQL PHP [\#656](https://github.com/wp-graphql/wp-graphql/pull/656) ([jasonbahl](https://github.com/jasonbahl))

**Closed issues:**

- orderby error [\#659](https://github.com/wp-graphql/wp-graphql/issues/659)
- PHP Warnings for missing 2nd arg for `after\_execute\_actions` [\#657](https://github.com/wp-graphql/wp-graphql/issues/657)
- Query with multiple operations [\#653](https://github.com/wp-graphql/wp-graphql/issues/653)
- Upgrade underlying GraphQL-PHP library [\#623](https://github.com/wp-graphql/wp-graphql/issues/623)

## [v0.2.0](https://github.com/wp-graphql/wp-graphql/tree/v0.2.0) (2019-01-10)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.1.4...v0.2.0)

**Implemented enhancements:**

- Pass connection args down through context [\#636](https://github.com/wp-graphql/wp-graphql/issues/636)
- Feature/\#636 pass connection args through context [\#637](https://github.com/wp-graphql/wp-graphql/pull/637) ([jasonbahl](https://github.com/jasonbahl))

**Fixed bugs:**

- graphql\_request\_data filter is not executed for POST requests [\#509](https://github.com/wp-graphql/wp-graphql/issues/509)

**Closed issues:**

- In new Request class \(prerelease 0.2.0\), HTTP request data is not filtered [\#643](https://github.com/wp-graphql/wp-graphql/issues/643)
- Cursor pagination returns some duplicate data. [\#641](https://github.com/wp-graphql/wp-graphql/issues/641)
- Add filter to allow users to customise typeBy query args [\#638](https://github.com/wp-graphql/wp-graphql/issues/638)
- RootQuery 'By' uses invalid type for database ID [\#632](https://github.com/wp-graphql/wp-graphql/issues/632)
- Cannot query field [\#630](https://github.com/wp-graphql/wp-graphql/issues/630)
- Implement persisted queries [\#590](https://github.com/wp-graphql/wp-graphql/issues/590)

**Merged pull requests:**

- Typo in isset check for existing queryId. [\#646](https://github.com/wp-graphql/wp-graphql/pull/646) ([chriszarate](https://github.com/chriszarate))
- Provide graphql\_server\_config action to allow customization of ServerConfig. [\#645](https://github.com/wp-graphql/wp-graphql/pull/645) ([chriszarate](https://github.com/chriszarate))
- Pass the operation params to the executeRequest call \(fixes \#643\). [\#644](https://github.com/wp-graphql/wp-graphql/pull/644) ([chriszarate](https://github.com/chriszarate))
- \#611 update mutation config [\#634](https://github.com/wp-graphql/wp-graphql/pull/634) ([hsimah](https://github.com/hsimah))
- \#632 fix types [\#633](https://github.com/wp-graphql/wp-graphql/pull/633) ([hsimah](https://github.com/hsimah))
- Order menu items by post\_\_in instead of menu\_order. [\#631](https://github.com/wp-graphql/wp-graphql/pull/631) ([chriszarate](https://github.com/chriszarate))
- Create a Request class to consolidate WPGraphQL entrypoints. [\#621](https://github.com/wp-graphql/wp-graphql/pull/621) ([chriszarate](https://github.com/chriszarate))

## [v0.1.4](https://github.com/wp-graphql/wp-graphql/tree/v0.1.4) (2018-12-19)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.1.3...v0.1.4)

**Implemented enhancements:**

- Throw exceptions in register\_graphql\_\* methods when they're missing critical fields [\#619](https://github.com/wp-graphql/wp-graphql/issues/619)
- \#619 - Throw Better Exceptions in TypeRegistry [\#628](https://github.com/wp-graphql/wp-graphql/pull/628) ([jasonbahl](https://github.com/jasonbahl))

**Fixed bugs:**

- Register Custom Connections [\#612](https://github.com/wp-graphql/wp-graphql/issues/612)
- Excerpt is always of the first post [\#503](https://github.com/wp-graphql/wp-graphql/issues/503)

**Closed issues:**

- Fix logo in README [\#582](https://github.com/wp-graphql/wp-graphql/issues/582)
- Write test to make sure first post excerpt is not repeated throughout list of posts [\#576](https://github.com/wp-graphql/wp-graphql/issues/576)
- APIGen deploys are causing Travis to fail [\#569](https://github.com/wp-graphql/wp-graphql/issues/569)

**Merged pull requests:**

- fixes \#576 test for global post object being set correctly [\#625](https://github.com/wp-graphql/wp-graphql/pull/625) ([CodeProKid](https://github.com/CodeProKid))
- fixes \#569 remove ApiGen [\#622](https://github.com/wp-graphql/wp-graphql/pull/622) ([CodeProKid](https://github.com/CodeProKid))
- Ttypo in comments field description \(compatability =\> compatibility\) [\#617](https://github.com/wp-graphql/wp-graphql/pull/617) ([MoOx](https://github.com/MoOx))
- - remove revisions first attempt [\#616](https://github.com/wp-graphql/wp-graphql/pull/616) ([jasonbahl](https://github.com/jasonbahl))
- \#612 - move `graphql\_register\_types` to the end of TypeRegistry::init\(\) [\#613](https://github.com/wp-graphql/wp-graphql/pull/613) ([jasonbahl](https://github.com/jasonbahl))

## [v0.1.3](https://github.com/wp-graphql/wp-graphql/tree/v0.1.3) (2018-12-04)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.1.2...v0.1.3)

**Implemented enhancements:**

- Add user password reset mutation [\#493](https://github.com/wp-graphql/wp-graphql/issues/493)
- Add mutation to send password reset email [\#491](https://github.com/wp-graphql/wp-graphql/issues/491)
- \#326 - Add Revisions Support [\#588](https://github.com/wp-graphql/wp-graphql/pull/588) ([jasonbahl](https://github.com/jasonbahl))
- \#491 - Send Password Reset Mutation [\#586](https://github.com/wp-graphql/wp-graphql/pull/586) ([jasonbahl](https://github.com/jasonbahl))
- Feature/\#493 user password reset mutation [\#585](https://github.com/wp-graphql/wp-graphql/pull/585) ([jasonbahl](https://github.com/jasonbahl))

**Fixed bugs:**

- generalSettings.startOfWeek throwing an error [\#528](https://github.com/wp-graphql/wp-graphql/issues/528)
- Restore graphql\_input\_fields filter [\#591](https://github.com/wp-graphql/wp-graphql/pull/591) ([chriszarate](https://github.com/chriszarate))

**Closed issues:**

- WPInputObjectType::construct doesn't call prepare\_fields [\#578](https://github.com/wp-graphql/wp-graphql/issues/578)

**Merged pull requests:**

- Bug/\#598 term search doesn't include child terms in results [\#599](https://github.com/wp-graphql/wp-graphql/pull/599) ([jasonbahl](https://github.com/jasonbahl))
- Revert "Revert "\#326 - Add Revisions Support"" [\#596](https://github.com/wp-graphql/wp-graphql/pull/596) ([jasonbahl](https://github.com/jasonbahl))
- Revert "\#326 - Add Revisions Support" [\#595](https://github.com/wp-graphql/wp-graphql/pull/595) ([jasonbahl](https://github.com/jasonbahl))
- Davidwhiletrue bugfix/528 [\#584](https://github.com/wp-graphql/wp-graphql/pull/584) ([jasonbahl](https://github.com/jasonbahl))
- Feature/\#582 fix logo in readme [\#583](https://github.com/wp-graphql/wp-graphql/pull/583) ([jasonbahl](https://github.com/jasonbahl))

## [v0.1.2](https://github.com/wp-graphql/wp-graphql/tree/v0.1.2) (2018-11-23)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.1.1...v0.1.2)

**Implemented enhancements:**

- Add better Type registry support [\#327](https://github.com/wp-graphql/wp-graphql/issues/327)

**Fixed bugs:**

- Children field missing on TermObjects [\#573](https://github.com/wp-graphql/wp-graphql/issues/573)
- Pagination Bug whilst using `hasPreviousPage` returning true. [\#430](https://github.com/wp-graphql/wp-graphql/issues/430)

**Closed issues:**

- Feature Request: Orderby Rand [\#577](https://github.com/wp-graphql/wp-graphql/issues/577)
- Error Unknown type MenuLocation after updating to 0.1.1 [\#570](https://github.com/wp-graphql/wp-graphql/issues/570)
- Broke a couple tests during v0.1.0 release... [\#561](https://github.com/wp-graphql/wp-graphql/issues/561)
- Fix code coverage reports [\#462](https://github.com/wp-graphql/wp-graphql/issues/462)

**Merged pull requests:**

- CI-5884 | Docker test shell no longer changes files on host OS; misc Docker cleanup [\#580](https://github.com/wp-graphql/wp-graphql/pull/580) ([mngi-arogers](https://github.com/mngi-arogers))
- \#573 - adding the "children" connection to TermObjects [\#574](https://github.com/wp-graphql/wp-graphql/pull/574) ([jasonbahl](https://github.com/jasonbahl))
- local-app file upload limit size pushed to 64MB [\#572](https://github.com/wp-graphql/wp-graphql/pull/572) ([kidunot89](https://github.com/kidunot89))

## [v0.1.1](https://github.com/wp-graphql/wp-graphql/tree/v0.1.1) (2018-11-01)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.1.0...v0.1.1)

**Fixed bugs:**

- Backticks in field descriptions [\#514](https://github.com/wp-graphql/wp-graphql/issues/514)
- Menus not returning ChildItems [\#505](https://github.com/wp-graphql/wp-graphql/issues/505)

**Closed issues:**

- Query'ing a number of posts in a category falsely returns hasNextPage: true if number of posts queried is equal to total number of posts in category [\#533](https://github.com/wp-graphql/wp-graphql/issues/533)
- Menu item connection gets childItems only for the first menu [\#520](https://github.com/wp-graphql/wp-graphql/issues/520)

**Merged pull requests:**

- fix bug with missing use statement [\#566](https://github.com/wp-graphql/wp-graphql/pull/566) ([jasonbahl](https://github.com/jasonbahl))
- CI-5745-2 | Fixing PHP code to get the tests passing; Fixing some broken Docker configuration. [\#565](https://github.com/wp-graphql/wp-graphql/pull/565) ([mngi-arogers](https://github.com/mngi-arogers))
- CI-5745 [\#560](https://github.com/wp-graphql/wp-graphql/pull/560) ([mngi-arogers](https://github.com/mngi-arogers))

## [v0.1.0](https://github.com/wp-graphql/wp-graphql/tree/v0.1.0) (2018-10-30)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.0.34...v0.1.0)

**Closed issues:**

- error line 24  [\#550](https://github.com/wp-graphql/wp-graphql/issues/550)
- Indicate in the README how to run specific tests [\#545](https://github.com/wp-graphql/wp-graphql/issues/545)
- A couple of tests depend on having Hello Dolly plugin installed [\#543](https://github.com/wp-graphql/wp-graphql/issues/543)

**Merged pull requests:**

- \* \* added to debugging to make it visual [\#551](https://github.com/wp-graphql/wp-graphql/pull/551) ([moh1t](https://github.com/moh1t))
- Fixes \#545 [\#546](https://github.com/wp-graphql/wp-graphql/pull/546) ([davidatwhiletrue](https://github.com/davidatwhiletrue))
- Fixes \#543. [\#544](https://github.com/wp-graphql/wp-graphql/pull/544) ([davidatwhiletrue](https://github.com/davidatwhiletrue))
- Featured image and media details bug fix [\#542](https://github.com/wp-graphql/wp-graphql/pull/542) ([myleshyson](https://github.com/myleshyson))
- Correct issue \#533 [\#537](https://github.com/wp-graphql/wp-graphql/pull/537) ([KittoVaci](https://github.com/KittoVaci))
- Update UserMutation.php to improve error message [\#536](https://github.com/wp-graphql/wp-graphql/pull/536) ([jasoncarle](https://github.com/jasoncarle))
- Release/v0.0.34 [\#531](https://github.com/wp-graphql/wp-graphql/pull/531) ([shivamk01](https://github.com/shivamk01))
- Fix $post\_type in graphql\_map\_input\_fields\_to\_wp\_query [\#515](https://github.com/wp-graphql/wp-graphql/pull/515) ([epeli](https://github.com/epeli))

## [v0.0.34](https://github.com/wp-graphql/wp-graphql/tree/v0.0.34) (2018-09-27)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.0.33...v0.0.34)

**Implemented enhancements:**

- Support User Taxonomies [\#511](https://github.com/wp-graphql/wp-graphql/issues/511)
- Add suppress filter query arg to all term queries [\#283](https://github.com/wp-graphql/wp-graphql/issues/283)
- Create helper functions for extending queries and mutations [\#246](https://github.com/wp-graphql/wp-graphql/issues/246)
- User Registration Mutation [\#223](https://github.com/wp-graphql/wp-graphql/issues/223)

**Fixed bugs:**

- TermObjectConnectionResolver tries to make use of Post ID even if the source is not a Post [\#496](https://github.com/wp-graphql/wp-graphql/issues/496)
- Batch Requests don't pass data to do\_graphql\_request properly [\#495](https://github.com/wp-graphql/wp-graphql/issues/495)
- \#504 - Ensure post objects are resolved using DataSource::resolve\_pos… [\#506](https://github.com/wp-graphql/wp-graphql/pull/506) ([jasonbahl](https://github.com/jasonbahl))

**Closed issues:**

- How to add a Child WPObject Type on multiple post types [\#523](https://github.com/wp-graphql/wp-graphql/issues/523)
- Is PostObjectConnectionArgs tagIn not implemented or possibly misnamed? [\#507](https://github.com/wp-graphql/wp-graphql/issues/507)
- postBy behaves in an inconsistent way [\#504](https://github.com/wp-graphql/wp-graphql/issues/504)
- Assign a post to category while creating using mutation. [\#292](https://github.com/wp-graphql/wp-graphql/issues/292)

**Merged pull requests:**

- Issue 462 | Code coverage+Docker refactor [\#526](https://github.com/wp-graphql/wp-graphql/pull/526) ([mngi-arogers](https://github.com/mngi-arogers))
- Fix for bug: Menus not returning ChildItems \#505 [\#524](https://github.com/wp-graphql/wp-graphql/pull/524) ([ElisaMassafra](https://github.com/ElisaMassafra))
- ISSUE-507 map tagIn-\>tag\_\_in [\#508](https://github.com/wp-graphql/wp-graphql/pull/508) ([camsjams](https://github.com/camsjams))
- Fix error message for non-existent post [\#501](https://github.com/wp-graphql/wp-graphql/pull/501) ([chriszarate](https://github.com/chriszarate))
- \#495 & \#496 [\#497](https://github.com/wp-graphql/wp-graphql/pull/497) ([jasonbahl](https://github.com/jasonbahl))

## [v0.0.33](https://github.com/wp-graphql/wp-graphql/tree/v0.0.33) (2018-08-16)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.0.32...v0.0.33)

**Merged pull requests:**

- bug: settings not loaded in Schema properly [\#490](https://github.com/wp-graphql/wp-graphql/pull/490) ([jasonbahl](https://github.com/jasonbahl))
- Feature/\#223 add user registration mutation [\#483](https://github.com/wp-graphql/wp-graphql/pull/483) ([kellenmace](https://github.com/kellenmace))

## [v0.0.32](https://github.com/wp-graphql/wp-graphql/tree/v0.0.32) (2018-08-09)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.0.31...v0.0.32)

**Merged pull requests:**

- Bug/do graphql request context [\#487](https://github.com/wp-graphql/wp-graphql/pull/487) ([jasonbahl](https://github.com/jasonbahl))
- Improve speed of PostObjectConnectionQueriesTest [\#484](https://github.com/wp-graphql/wp-graphql/pull/484) ([chriszarate](https://github.com/chriszarate))

## [v0.0.31](https://github.com/wp-graphql/wp-graphql/tree/v0.0.31) (2018-08-01)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.0.30...v0.0.31)

**Fixed bugs:**

- Exception when adding the 'thumbnail' option in the 'supports' array in 'register\_post\_type' [\#475](https://github.com/wp-graphql/wp-graphql/issues/475)

**Closed issues:**

- comment author [\#478](https://github.com/wp-graphql/wp-graphql/issues/478)
- Excerpts not outputting content as expected [\#464](https://github.com/wp-graphql/wp-graphql/issues/464)

**Merged pull requests:**

- Release/v0.0.31 [\#480](https://github.com/wp-graphql/wp-graphql/pull/480) ([jasonbahl](https://github.com/jasonbahl))
- \#475: Re-arrange setup of Types \(post\_types, taxonomies, etc\) that are registered to show\_in\_graphql [\#477](https://github.com/wp-graphql/wp-graphql/pull/477) ([jasonbahl](https://github.com/jasonbahl))
- Bug/\#464 post excerpt outputting incorrectly [\#476](https://github.com/wp-graphql/wp-graphql/pull/476) ([jasonbahl](https://github.com/jasonbahl))

## [v0.0.30](https://github.com/wp-graphql/wp-graphql/tree/v0.0.30) (2018-07-23)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.0.29...v0.0.30)

**Implemented enhancements:**

- Add userRoles entry point [\#444](https://github.com/wp-graphql/wp-graphql/issues/444)
- Post Connection "where" args should allow multiple stati [\#332](https://github.com/wp-graphql/wp-graphql/issues/332)
- Add menu\_location, menus, and nav\_menu\_item support. [\#126](https://github.com/wp-graphql/wp-graphql/issues/126)
- Comment Mutations [\#8](https://github.com/wp-graphql/wp-graphql/issues/8)

**Closed issues:**

- Filters using `the\_title` cause fatal errors [\#470](https://github.com/wp-graphql/wp-graphql/issues/470)
- Bug: Querying sourceUrl for featuredImage sizes returns filename [\#468](https://github.com/wp-graphql/wp-graphql/issues/468)
- menu item type \(post, page, category\) [\#466](https://github.com/wp-graphql/wp-graphql/issues/466)
- Add WP-GraphQL to Open Collective [\#455](https://github.com/wp-graphql/wp-graphql/issues/455)
- Error on GraphQL endpoint \(Unexpected \<EOF\>\) [\#451](https://github.com/wp-graphql/wp-graphql/issues/451)
- How to handle file uploads? [\#449](https://github.com/wp-graphql/wp-graphql/issues/449)
- No JSON response for caught exceptions in `Router::process\_http\_request\(\)` [\#442](https://github.com/wp-graphql/wp-graphql/issues/442)
- Fix Orderby mapping for post\_\_in, post\_name\_\_in and post\_parent\_\_in [\#436](https://github.com/wp-graphql/wp-graphql/issues/436)
- Add WPUnionType [\#432](https://github.com/wp-graphql/wp-graphql/issues/432)
- Questions about Auth and Security [\#373](https://github.com/wp-graphql/wp-graphql/issues/373)

**Merged pull requests:**

- $id added to the the\_title filter call [\#471](https://github.com/wp-graphql/wp-graphql/pull/471) ([kidunot89](https://github.com/kidunot89))
- Bug/\#468 source url for featured image returns filename [\#469](https://github.com/wp-graphql/wp-graphql/pull/469) ([jasonbahl](https://github.com/jasonbahl))
- Issue\#8 comment mutations [\#465](https://github.com/wp-graphql/wp-graphql/pull/465) ([kidunot89](https://github.com/kidunot89))
- Feature/444 - Adding entry point for querying user roles [\#463](https://github.com/wp-graphql/wp-graphql/pull/463) ([CodeProKid](https://github.com/CodeProKid))
- Bug/after execute missing request [\#461](https://github.com/wp-graphql/wp-graphql/pull/461) ([jasonbahl](https://github.com/jasonbahl))
- Use WPUnionType for MenuItemObjectUnionType [\#460](https://github.com/wp-graphql/wp-graphql/pull/460) ([chriszarate](https://github.com/chriszarate))
- Bug when querying categories children [\#459](https://github.com/wp-graphql/wp-graphql/pull/459) ([ElisaMassafra](https://github.com/ElisaMassafra))
- Clean up WPObjectType constructor [\#457](https://github.com/wp-graphql/wp-graphql/pull/457) ([chriszarate](https://github.com/chriszarate))
- Add WPUnionType. [\#456](https://github.com/wp-graphql/wp-graphql/pull/456) ([chriszarate](https://github.com/chriszarate))
- Update comment about Docker login / logout [\#454](https://github.com/wp-graphql/wp-graphql/pull/454) ([jasonbahl](https://github.com/jasonbahl))
- Populate $request variable for POST requests [\#452](https://github.com/wp-graphql/wp-graphql/pull/452) ([natewoodbridge](https://github.com/natewoodbridge))
- Check for duplicate declaration of graphql\_init [\#450](https://github.com/wp-graphql/wp-graphql/pull/450) ([jmlallier](https://github.com/jmlallier))
- Add docker-compose file for local / user testing. [\#448](https://github.com/wp-graphql/wp-graphql/pull/448) ([chriszarate](https://github.com/chriszarate))
- Nav Menu support [\#446](https://github.com/wp-graphql/wp-graphql/pull/446) ([chriszarate](https://github.com/chriszarate))

## [v0.0.29](https://github.com/wp-graphql/wp-graphql/tree/v0.0.29) (2018-05-24)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.0.28...v0.0.29)

**Closed issues:**

- Should be able to remove post edit lock [\#440](https://github.com/wp-graphql/wp-graphql/issues/440)

**Merged pull requests:**

- fix bug with setting the parentId for postObjects [\#445](https://github.com/wp-graphql/wp-graphql/pull/445) ([jasonbahl](https://github.com/jasonbahl))
- No JSON Response when exceptions occur outside of do\_graphql\_request [\#443](https://github.com/wp-graphql/wp-graphql/pull/443) ([jasonbahl](https://github.com/jasonbahl))
- Feature/440 - Remove edit lock after mutation is done [\#441](https://github.com/wp-graphql/wp-graphql/pull/441) ([CodeProKid](https://github.com/CodeProKid))

## [v0.0.28](https://github.com/wp-graphql/wp-graphql/tree/v0.0.28) (2018-05-03)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.0.27...v0.0.28)

**Implemented enhancements:**

- Make access control headers a filterable list [\#425](https://github.com/wp-graphql/wp-graphql/issues/425)
- CLI for generating Schema Files [\#290](https://github.com/wp-graphql/wp-graphql/issues/290)
- Introduce new config options to Field definition \(auth, pre/post resolve, cache?\), etc [\#281](https://github.com/wp-graphql/wp-graphql/issues/281)
- Static GraphQL Schema Docs Plugin [\#57](https://github.com/wp-graphql/wp-graphql/issues/57)

**Fixed bugs:**

- Add dateGmt field to postObject Mutation [\#434](https://github.com/wp-graphql/wp-graphql/issues/434)
- GET Requests with field arguments don't work [\#361](https://github.com/wp-graphql/wp-graphql/issues/361)

**Closed issues:**

- Online schema/API docs [\#415](https://github.com/wp-graphql/wp-graphql/issues/415)
- `define\('WP\_DEBUG', true\);` breaks`/graphql` endpoint for GraphiQL [\#411](https://github.com/wp-graphql/wp-graphql/issues/411)
- WP\_Query pagination uses post\_date and post ID in comparison, which can exclude posts [\#406](https://github.com/wp-graphql/wp-graphql/issues/406)
- Error: DiscussionSettings fields must be an object with field names as keys or a function which returns such an object. [\#368](https://github.com/wp-graphql/wp-graphql/issues/368)
- tax query, meta query [\#342](https://github.com/wp-graphql/wp-graphql/issues/342)
- Create a Golden Ticket Template for the repo [\#198](https://github.com/wp-graphql/wp-graphql/issues/198)
- Add introspection tests. [\#131](https://github.com/wp-graphql/wp-graphql/issues/131)

**Merged pull requests:**

- Update comment for clarity. [\#439](https://github.com/wp-graphql/wp-graphql/pull/439) ([chriszarate](https://github.com/chriszarate))
- \#436 - fix orderby mapping of post\_\_in, post\_name\_\_in and post\_parent\_\_in [\#437](https://github.com/wp-graphql/wp-graphql/pull/437) ([jasonbahl](https://github.com/jasonbahl))
- Update README.md [\#433](https://github.com/wp-graphql/wp-graphql/pull/433) ([bahiirwa](https://github.com/bahiirwa))
- \[\#332\] Added support for stati parameter as list of enums [\#429](https://github.com/wp-graphql/wp-graphql/pull/429) ([EduardMaghakyan](https://github.com/EduardMaghakyan))
- \#425 - filter access control headers [\#426](https://github.com/wp-graphql/wp-graphql/pull/426) ([jasonbahl](https://github.com/jasonbahl))
- Feature/\#361 get request issues [\#424](https://github.com/wp-graphql/wp-graphql/pull/424) ([jasonbahl](https://github.com/jasonbahl))

## [v0.0.27](https://github.com/wp-graphql/wp-graphql/tree/v0.0.27) (2018-03-20)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.0.26...v0.0.27)

**Closed issues:**

- Input args need to be unique to the Type they belong to & pass $config to filters [\#416](https://github.com/wp-graphql/wp-graphql/issues/416)
- cannot access user info through userId [\#414](https://github.com/wp-graphql/wp-graphql/issues/414)
- Authentication with WPGraphQL from a NextJS app [\#412](https://github.com/wp-graphql/wp-graphql/issues/412)
- Bug: `Array to string conversion` in resolver for `term` query on `PostObject` [\#400](https://github.com/wp-graphql/wp-graphql/issues/400)
- Setup Mock server using WPGraphQL schema [\#229](https://github.com/wp-graphql/wp-graphql/issues/229)

**Merged pull requests:**

- \#416 - Update Connection Args  [\#418](https://github.com/wp-graphql/wp-graphql/pull/418) ([jasonbahl](https://github.com/jasonbahl))
- \#416 - pass more context to filters for input args [\#417](https://github.com/wp-graphql/wp-graphql/pull/417) ([jasonbahl](https://github.com/jasonbahl))
- Fix adjustments to WP\_Query pagination to use only post\_date. [\#409](https://github.com/wp-graphql/wp-graphql/pull/409) ([jasonbahl](https://github.com/jasonbahl))
- Working wpunit/acceptance/functional tests inside Docker and running on Travis. [\#408](https://github.com/wp-graphql/wp-graphql/pull/408) ([chriszarate](https://github.com/chriszarate))
- Working wpunit/acceptance/functional tests inside Docker. [\#404](https://github.com/wp-graphql/wp-graphql/pull/404) ([chriszarate](https://github.com/chriszarate))
- Fix for issue \#400 😺😺😺😺 [\#401](https://github.com/wp-graphql/wp-graphql/pull/401) ([hews](https://github.com/hews))
- Fix undefined vars in Router introduced in v0.0.25 [\#399](https://github.com/wp-graphql/wp-graphql/pull/399) ([hews](https://github.com/hews))

## [v0.0.26](https://github.com/wp-graphql/wp-graphql/tree/v0.0.26) (2018-02-14)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.0.25...v0.0.26)

**Implemented enhancements:**

- Set status of PostObjects at the end of a create mutation [\#394](https://github.com/wp-graphql/wp-graphql/issues/394)

**Closed issues:**

- Add context and info params to all "xxx\_mutation\_update\_additional\_data" hooks [\#393](https://github.com/wp-graphql/wp-graphql/issues/393)
- Setup initial Docs site using Gatsby.js [\#343](https://github.com/wp-graphql/wp-graphql/issues/343)

**Merged pull requests:**

- \#394 - Set Post Status Late on Create Mutations [\#396](https://github.com/wp-graphql/wp-graphql/pull/396) ([jasonbahl](https://github.com/jasonbahl))
- Set post status for Post Object Create mutations late, to allow for side-effects before final status is set [\#395](https://github.com/wp-graphql/wp-graphql/pull/395) ([jasonbahl](https://github.com/jasonbahl))
- Sync with latest release [\#391](https://github.com/wp-graphql/wp-graphql/pull/391) ([jasonbahl](https://github.com/jasonbahl))

## [v0.0.25](https://github.com/wp-graphql/wp-graphql/tree/v0.0.25) (2018-02-09)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.0.24...v0.0.25)

**Implemented enhancements:**

- Filter field definition in Schema Instrumentation [\#377](https://github.com/wp-graphql/wp-graphql/issues/377)
- Feature/\#381 - Selected Terms in TermObjectConnection queries [\#387](https://github.com/wp-graphql/wp-graphql/pull/387) ([jasonbahl](https://github.com/jasonbahl))
- \#360 - Query Batching [\#385](https://github.com/wp-graphql/wp-graphql/pull/385) ([jasonbahl](https://github.com/jasonbahl))

**Fixed bugs:**

- Activation and Deactivation hooks never fire [\#370](https://github.com/wp-graphql/wp-graphql/issues/370)
- \#370 - Flush permalinks properly [\#382](https://github.com/wp-graphql/wp-graphql/pull/382) ([jasonbahl](https://github.com/jasonbahl))

**Closed issues:**

- When defining a type during theme setup, error when referencing built-in WP\_GraphQL types within it [\#390](https://github.com/wp-graphql/wp-graphql/issues/390)
- Unselected subcategories get returned for post's categories [\#381](https://github.com/wp-graphql/wp-graphql/issues/381)
- Question on custom metabox support. [\#380](https://github.com/wp-graphql/wp-graphql/issues/380)
- Query for posts where hasPassword: false should not return password-protected posts [\#374](https://github.com/wp-graphql/wp-graphql/issues/374)
- Query batching support [\#360](https://github.com/wp-graphql/wp-graphql/issues/360)

**Merged pull requests:**

- fix: logo in README [\#386](https://github.com/wp-graphql/wp-graphql/pull/386) ([ginatrapani](https://github.com/ginatrapani))
- Feature/\#198 pull request template [\#384](https://github.com/wp-graphql/wp-graphql/pull/384) ([jasonbahl](https://github.com/jasonbahl))
- Create CODE\_OF\_CONDUCT.md [\#383](https://github.com/wp-graphql/wp-graphql/pull/383) ([jasonbahl](https://github.com/jasonbahl))
- Filter Field Definition in Schema Instrumentation [\#378](https://github.com/wp-graphql/wp-graphql/pull/378) ([jasonbahl](https://github.com/jasonbahl))
- fix: hasPassword type should be boolean [\#376](https://github.com/wp-graphql/wp-graphql/pull/376) ([ginatrapani](https://github.com/ginatrapani))
- WP-CLI Improvements [\#372](https://github.com/wp-graphql/wp-graphql/pull/372) ([renatonascalves](https://github.com/renatonascalves))

## [v0.0.24](https://github.com/wp-graphql/wp-graphql/tree/v0.0.24) (2018-01-18)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.0.23...v0.0.24)

**Implemented enhancements:**

- WPGraphQL doesn't play nice with WP-CLI Server [\#355](https://github.com/wp-graphql/wp-graphql/issues/355)
- Allow response headers to be filtered [\#349](https://github.com/wp-graphql/wp-graphql/issues/349)
- Post Object Mutations should allow setting/updating terms [\#333](https://github.com/wp-graphql/wp-graphql/issues/333)
- Core WP Options Mutations [\#298](https://github.com/wp-graphql/wp-graphql/issues/298)
- \#333 - Set term connections as nested input in PostObjectMutations [\#369](https://github.com/wp-graphql/wp-graphql/pull/369) ([jasonbahl](https://github.com/jasonbahl))

**Fixed bugs:**

- WPGraphQL doesn't play nice with WP-CLI Server [\#355](https://github.com/wp-graphql/wp-graphql/issues/355)

**Closed issues:**

- Wiki doc on extending the Post schema is incorrect [\#366](https://github.com/wp-graphql/wp-graphql/issues/366)
- Parsing issue with graphql-tools [\#364](https://github.com/wp-graphql/wp-graphql/issues/364)
- Ignore schema.graphql file [\#351](https://github.com/wp-graphql/wp-graphql/issues/351)
- 403 status issues [\#346](https://github.com/wp-graphql/wp-graphql/issues/346)
- Post mutations with dates not working [\#345](https://github.com/wp-graphql/wp-graphql/issues/345)
- How to query a custom field type image from a custom post type [\#344](https://github.com/wp-graphql/wp-graphql/issues/344)
- Adjust how term/post\_type connection fields are added to each Type [\#111](https://github.com/wp-graphql/wp-graphql/issues/111)
- Unit Test for Schema [\#104](https://github.com/wp-graphql/wp-graphql/issues/104)
- Custom Taxonomy Query Registration API [\#13](https://github.com/wp-graphql/wp-graphql/issues/13)

**Merged pull requests:**

- \#345 Post mutations with dates not working [\#363](https://github.com/wp-graphql/wp-graphql/pull/363) ([weshebert20](https://github.com/weshebert20))

## [v0.0.23](https://github.com/wp-graphql/wp-graphql/tree/v0.0.23) (2018-01-10)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.0.22...v0.0.23)

**Implemented enhancements:**

- Hook up Travis CI with Schema Linting  [\#190](https://github.com/wp-graphql/wp-graphql/issues/190)
- \#349 - Allow response headers to be filterable [\#350](https://github.com/wp-graphql/wp-graphql/pull/350) ([jasonbahl](https://github.com/jasonbahl))

**Fixed bugs:**

- User nicename should be a string, not an int [\#356](https://github.com/wp-graphql/wp-graphql/issues/356)
- fix User nicename type [\#357](https://github.com/wp-graphql/wp-graphql/pull/357) ([ginatrapani](https://github.com/ginatrapani))
- \#337 - Remove Ghost File [\#338](https://github.com/wp-graphql/wp-graphql/pull/338) ([jasonbahl](https://github.com/jasonbahl))

**Closed issues:**

- Remove Ghost `/wp-graphql` file in the repo [\#337](https://github.com/wp-graphql/wp-graphql/issues/337)
- Caching the vendor folder on travis is a bad idea. [\#334](https://github.com/wp-graphql/wp-graphql/issues/334)
- Query Doesnt display Private Posts or Drafts [\#325](https://github.com/wp-graphql/wp-graphql/issues/325)
-  Unexpected token when creating Post [\#324](https://github.com/wp-graphql/wp-graphql/issues/324)
- Query custom content types [\#322](https://github.com/wp-graphql/wp-graphql/issues/322)
- Connect to WooCommerce plugin [\#321](https://github.com/wp-graphql/wp-graphql/issues/321)
- Install plugin from WordPress market [\#320](https://github.com/wp-graphql/wp-graphql/issues/320)
- Wordpress crashes after activating plugin [\#315](https://github.com/wp-graphql/wp-graphql/issues/315)
- 403 Forbidden https://bariatricdemo.myshopify.com/api/graphql [\#308](https://github.com/wp-graphql/wp-graphql/issues/308)
- How can we add comment to an existing post using mutation? [\#307](https://github.com/wp-graphql/wp-graphql/issues/307)
- How to create query/mutation for custom table in WP [\#299](https://github.com/wp-graphql/wp-graphql/issues/299)

**Merged pull requests:**

- \#343 - Docs Site [\#354](https://github.com/wp-graphql/wp-graphql/pull/354) ([jasonbahl](https://github.com/jasonbahl))
- Feature/\#343 setup docs site [\#353](https://github.com/wp-graphql/wp-graphql/pull/353) ([jasonbahl](https://github.com/jasonbahl))
- \#351 - Ignore \(and remove\) static schema.graphql file [\#352](https://github.com/wp-graphql/wp-graphql/pull/352) ([jasonbahl](https://github.com/jasonbahl))
- CI: Do not cache Composer vendor folder [\#348](https://github.com/wp-graphql/wp-graphql/pull/348) ([renatonascalves](https://github.com/renatonascalves))
- Add TERM\_ORDER as TermsOrderby enum. [\#340](https://github.com/wp-graphql/wp-graphql/pull/340) ([chriszarate](https://github.com/chriszarate))
- Remove get\_the\_excerpt filter for image caption. [\#339](https://github.com/wp-graphql/wp-graphql/pull/339) ([chriszarate](https://github.com/chriszarate))
- Adding a bunch of Docs, and mkdocs config [\#323](https://github.com/wp-graphql/wp-graphql/pull/323) ([jasonbahl](https://github.com/jasonbahl))
- Remove Jason's local yml files and update README. [\#319](https://github.com/wp-graphql/wp-graphql/pull/319) ([hughdevore](https://github.com/hughdevore))
- Adding Codeception [\#318](https://github.com/wp-graphql/wp-graphql/pull/318) ([jasonbahl](https://github.com/jasonbahl))
- Adding Codeception [\#314](https://github.com/wp-graphql/wp-graphql/pull/314) ([jasonbahl](https://github.com/jasonbahl))
- Misc Unit Test Coverage updates [\#313](https://github.com/wp-graphql/wp-graphql/pull/313) ([jasonbahl](https://github.com/jasonbahl))
- Features/\#156 options mutations [\#312](https://github.com/wp-graphql/wp-graphql/pull/312) ([hughdevore](https://github.com/hughdevore))
- \#190 - Schema Linting [\#310](https://github.com/wp-graphql/wp-graphql/pull/310) ([jasonbahl](https://github.com/jasonbahl))
- Ability to sort by menu\_order [\#306](https://github.com/wp-graphql/wp-graphql/pull/306) ([indeox](https://github.com/indeox))

## [v0.0.22](https://github.com/wp-graphql/wp-graphql/tree/v0.0.22) (2017-11-10)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.0.21...v0.0.22)

**Implemented enhancements:**

- Core WP Options Support [\#220](https://github.com/wp-graphql/wp-graphql/issues/220)
- Implement DataLoader [\#55](https://github.com/wp-graphql/wp-graphql/issues/55)

**Fixed bugs:**

- \#296 - WooCommerce Conflict [\#297](https://github.com/wp-graphql/wp-graphql/pull/297) ([jasonbahl](https://github.com/jasonbahl))

**Closed issues:**

- Conflict with WooCommerce 3.2.3 [\#296](https://github.com/wp-graphql/wp-graphql/issues/296)
- \[v0.0.21\] Internal server error [\#295](https://github.com/wp-graphql/wp-graphql/issues/295)
- orderby meta value? [\#287](https://github.com/wp-graphql/wp-graphql/issues/287)
- Unit Tests for DataSource methods [\#91](https://github.com/wp-graphql/wp-graphql/issues/91)

**Merged pull requests:**

- Prepare for release [\#303](https://github.com/wp-graphql/wp-graphql/pull/303) ([jasonbahl](https://github.com/jasonbahl))
- Feature/91 tests for datasource methods [\#301](https://github.com/wp-graphql/wp-graphql/pull/301) ([CodeProKid](https://github.com/CodeProKid))

## [v0.0.21](https://github.com/wp-graphql/wp-graphql/tree/v0.0.21) (2017-11-03)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.0.20...v0.0.21)

**Implemented enhancements:**

- Query Logger [\#56](https://github.com/wp-graphql/wp-graphql/issues/56)
- Initial Implementation of DataLoader \(deferred resolvers\)  [\#293](https://github.com/wp-graphql/wp-graphql/pull/293) ([jasonbahl](https://github.com/jasonbahl))
- \#273 - capitalizing Types [\#291](https://github.com/wp-graphql/wp-graphql/pull/291) ([jasonbahl](https://github.com/jasonbahl))

**Fixed bugs:**

- `sprintf\(\)` not interpolating strings properly in some places [\#271](https://github.com/wp-graphql/wp-graphql/issues/271)
- Plugin Version should be String not Float [\#264](https://github.com/wp-graphql/wp-graphql/issues/264)
- Preflight OPTIONS requests are returning 500 status [\#256](https://github.com/wp-graphql/wp-graphql/issues/256)
- Comment author must not be equal to existing wordpress user [\#172](https://github.com/wp-graphql/wp-graphql/issues/172)

**Closed issues:**

- Cleanup data config file [\#284](https://github.com/wp-graphql/wp-graphql/issues/284)
- Capitalize Types [\#273](https://github.com/wp-graphql/wp-graphql/issues/273)
- Update GraphQL-PHP to v0.11.2 [\#272](https://github.com/wp-graphql/wp-graphql/issues/272)
- Querying Subcategories [\#269](https://github.com/wp-graphql/wp-graphql/issues/269)
- Find objects based on multiple custom fields [\#268](https://github.com/wp-graphql/wp-graphql/issues/268)
- No  was found with the ID: post [\#267](https://github.com/wp-graphql/wp-graphql/issues/267)
- No schema available in GraphiQL [\#266](https://github.com/wp-graphql/wp-graphql/issues/266)
- Add custom post types with custom fields [\#243](https://github.com/wp-graphql/wp-graphql/issues/243)

**Merged pull requests:**

- fixes \#284 by adjusting some formatting and replacing sprintf\(\) with … [\#286](https://github.com/wp-graphql/wp-graphql/pull/286) ([CodeProKid](https://github.com/CodeProKid))
- CI-4209a [\#282](https://github.com/wp-graphql/wp-graphql/pull/282) ([pjpak](https://github.com/pjpak))
- Update dev with master [\#279](https://github.com/wp-graphql/wp-graphql/pull/279) ([jasonbahl](https://github.com/jasonbahl))
- Features/\#156 - Adding Settings queries [\#277](https://github.com/wp-graphql/wp-graphql/pull/277) ([hughdevore](https://github.com/hughdevore))
- CI-4215 - Adding tests for sanitize\_input\_fields and get\_query\_args in PostObject Connection [\#276](https://github.com/wp-graphql/wp-graphql/pull/276) ([davidvexel](https://github.com/davidvexel))
- \#272 - Update to use GraphQL-PHP v0.11.2 [\#275](https://github.com/wp-graphql/wp-graphql/pull/275) ([jasonbahl](https://github.com/jasonbahl))
- Change plugin version to string [\#270](https://github.com/wp-graphql/wp-graphql/pull/270) ([gabriellacerda](https://github.com/gabriellacerda))

## [v0.0.20](https://github.com/wp-graphql/wp-graphql/tree/v0.0.20) (2017-10-11)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.0.19...v0.0.20)

**Implemented enhancements:**

- Add nodes to connections on the same level as edges [\#247](https://github.com/wp-graphql/wp-graphql/issues/247)
- allItems in addition to connections? [\#212](https://github.com/wp-graphql/wp-graphql/issues/212)
- Options Mutations [\#10](https://github.com/wp-graphql/wp-graphql/issues/10)
- Options Queries [\#9](https://github.com/wp-graphql/wp-graphql/issues/9)

**Fixed bugs:**

- Use instanceof instead of is\_a [\#238](https://github.com/wp-graphql/wp-graphql/issues/238)

**Closed issues:**

- Replace slackin link in readme [\#251](https://github.com/wp-graphql/wp-graphql/issues/251)

**Merged pull requests:**

- \#256 - Preflight Request Support [\#257](https://github.com/wp-graphql/wp-graphql/pull/257) ([jasonbahl](https://github.com/jasonbahl))
- fixes \#251 adds new slack link to the repo [\#253](https://github.com/wp-graphql/wp-graphql/pull/253) ([CodeProKid](https://github.com/CodeProKid))
- replaced is\_a func with instanceof [\#250](https://github.com/wp-graphql/wp-graphql/pull/250) ([luchianenco](https://github.com/luchianenco))

## [v0.0.19](https://github.com/wp-graphql/wp-graphql/tree/v0.0.19) (2017-10-10)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.0.18...v0.0.19)

**Implemented enhancements:**

- Use `the\_title` filter for retrieving a Post Object title field. [\#120](https://github.com/wp-graphql/wp-graphql/issues/120)

**Fixed bugs:**

- Typo in $wpdb-\>prepare [\#232](https://github.com/wp-graphql/wp-graphql/issues/232)

**Closed issues:**

- Typo on Readme.txt - Documentation [\#236](https://github.com/wp-graphql/wp-graphql/issues/236)
- Return 403 for anauthenticated requests [\#227](https://github.com/wp-graphql/wp-graphql/issues/227)
- Unit Tests for mediaItems [\#156](https://github.com/wp-graphql/wp-graphql/issues/156)

**Merged pull requests:**

- \#247 - Add nodes to connections [\#248](https://github.com/wp-graphql/wp-graphql/pull/248) ([jasonbahl](https://github.com/jasonbahl))
- Docs - Adding unit test and code coverage info to docs. [\#244](https://github.com/wp-graphql/wp-graphql/pull/244) ([hughdevore](https://github.com/hughdevore))
- \#156 Finishing mediaItem code coverage. [\#241](https://github.com/wp-graphql/wp-graphql/pull/241) ([hughdevore](https://github.com/hughdevore))
- fixes \#236 - typo in documentation [\#237](https://github.com/wp-graphql/wp-graphql/pull/237) ([tayhansenxo](https://github.com/tayhansenxo))
- Add post object field format arg [\#230](https://github.com/wp-graphql/wp-graphql/pull/230) ([chriszarate](https://github.com/chriszarate))

## [v0.0.18](https://github.com/wp-graphql/wp-graphql/tree/v0.0.18) (2017-09-25)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.0.17...v0.0.18)

**Implemented enhancements:**

- Unit tests fail with PHP 7.1 [\#205](https://github.com/wp-graphql/wp-graphql/issues/205)
- Feature: flush rewrite rules on activation and deactivation [\#196](https://github.com/wp-graphql/wp-graphql/issues/196)
- \#227 - Return 403 for unauthenticated requests [\#231](https://github.com/wp-graphql/wp-graphql/pull/231) ([jasonbahl](https://github.com/jasonbahl))

**Closed issues:**

- Setup Mock server using WPGraphQL schema [\#228](https://github.com/wp-graphql/wp-graphql/issues/228)
- Adjust hooks to provide support for Tracing [\#225](https://github.com/wp-graphql/wp-graphql/issues/225)
- Requires PHP \>5.5 - "Can't use method return value in write context" [\#217](https://github.com/wp-graphql/wp-graphql/issues/217)
- PostObjects of a hierarchical Post Type should have a "children" field [\#209](https://github.com/wp-graphql/wp-graphql/issues/209)
- Get Post Objects by more than just the ID [\#207](https://github.com/wp-graphql/wp-graphql/issues/207)
- An empty request to the endpoint results in a malformed error response. [\#200](https://github.com/wp-graphql/wp-graphql/issues/200)
- Unit Tests for UsersConnection Resolver [\#89](https://github.com/wp-graphql/wp-graphql/issues/89)
- Unit Tests for TermObjectsConnection Resolver [\#88](https://github.com/wp-graphql/wp-graphql/issues/88)
- Unit Tests for PostTypesConnectionsResolver [\#87](https://github.com/wp-graphql/wp-graphql/issues/87)
- Unit Tests for PluginsConnectionResolver [\#85](https://github.com/wp-graphql/wp-graphql/issues/85)
- Unit Tests for UserConnectionQueryArgsType [\#73](https://github.com/wp-graphql/wp-graphql/issues/73)
- Unit Tests for TermObjectQueryArgsType [\#70](https://github.com/wp-graphql/wp-graphql/issues/70)
- Unit Tests for PostObjectQueryArgsType [\#65](https://github.com/wp-graphql/wp-graphql/issues/65)
- Unit Tests for DateQueryType [\#63](https://github.com/wp-graphql/wp-graphql/issues/63)
- Attachment Mutations [\#17](https://github.com/wp-graphql/wp-graphql/issues/17)

**Merged pull requests:**

- \#232 - fix placeholder in wpdb-\>prepare [\#233](https://github.com/wp-graphql/wp-graphql/pull/233) ([jasonbahl](https://github.com/jasonbahl))
- \#225 - GraphQL Tracing Support [\#226](https://github.com/wp-graphql/wp-graphql/pull/226) ([jasonbahl](https://github.com/jasonbahl))
- \#217 Document the required PHP version [\#218](https://github.com/wp-graphql/wp-graphql/pull/218) ([jasonbahl](https://github.com/jasonbahl))
- Feature/\#205 php 7.1 issues [\#211](https://github.com/wp-graphql/wp-graphql/pull/211) ([jasonbahl](https://github.com/jasonbahl))
- \#209 children connection for post objects. [\#210](https://github.com/wp-graphql/wp-graphql/pull/210) ([jasonbahl](https://github.com/jasonbahl))
- \#207 - Get Post Objects by Global ID, Database ID, or URI. [\#208](https://github.com/wp-graphql/wp-graphql/pull/208) ([jasonbahl](https://github.com/jasonbahl))
- Inline docs [\#204](https://github.com/wp-graphql/wp-graphql/pull/204) ([0aveRyan](https://github.com/0aveRyan))
- Return a user friendly error on an empty query [\#202](https://github.com/wp-graphql/wp-graphql/pull/202) ([AramZS](https://github.com/AramZS))
- Images for Wiki [\#201](https://github.com/wp-graphql/wp-graphql/pull/201) ([0aveRyan](https://github.com/0aveRyan))
- Activation and deactivation hooks [\#199](https://github.com/wp-graphql/wp-graphql/pull/199) ([AramZS](https://github.com/AramZS))
- Pulling master back through [\#194](https://github.com/wp-graphql/wp-graphql/pull/194) ([jasonbahl](https://github.com/jasonbahl))
- User Mutations [\#191](https://github.com/wp-graphql/wp-graphql/pull/191) ([CodeProKid](https://github.com/CodeProKid))

## [v0.0.17](https://github.com/wp-graphql/wp-graphql/tree/v0.0.17) (2017-08-15)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.0.16...v0.0.17)

**Implemented enhancements:**

- Review fields. [\#139](https://github.com/wp-graphql/wp-graphql/issues/139)
- Hierarchical terms like categories should have child parent connections. [\#133](https://github.com/wp-graphql/wp-graphql/issues/133)
- Add filters for registered post types and registered taxonomy connections. [\#132](https://github.com/wp-graphql/wp-graphql/issues/132)
- GraphiQl Plugin [\#58](https://github.com/wp-graphql/wp-graphql/issues/58)

**Fixed bugs:**

- BUG: Comment Connection doesn't set `parent` arg properly [\#186](https://github.com/wp-graphql/wp-graphql/issues/186)
- Too many params passed to absint\(\) [\#183](https://github.com/wp-graphql/wp-graphql/issues/183)
- Comment Connetion Query Args: where: {orderby: ...} [\#167](https://github.com/wp-graphql/wp-graphql/issues/167)
- Asking for more than the max number of results breaks pageInfo [\#159](https://github.com/wp-graphql/wp-graphql/issues/159)

**Closed issues:**

- Orderby date [\#193](https://github.com/wp-graphql/wp-graphql/issues/193)
- Can we create a new post using mutation in WP-graphql endpoint? [\#175](https://github.com/wp-graphql/wp-graphql/issues/175)
- WP-GraphQl stops end-point when adding WP-GraphQl options to Custom Post Types. [\#173](https://github.com/wp-graphql/wp-graphql/issues/173)
- Set "approved" as default status for comment connection queries [\#171](https://github.com/wp-graphql/wp-graphql/issues/171)
- Router hooks in too late, causing extra query to run [\#164](https://github.com/wp-graphql/wp-graphql/issues/164)
- Return featured\_media\_array.sizes [\#161](https://github.com/wp-graphql/wp-graphql/issues/161)
- last returns greater than the maximum number of allowed results [\#158](https://github.com/wp-graphql/wp-graphql/issues/158)
- Abstract Object fields to be used for input/output [\#142](https://github.com/wp-graphql/wp-graphql/issues/142)
- Add ancestors to posts/terms [\#116](https://github.com/wp-graphql/wp-graphql/issues/116)
- Unit Tests for access-functions [\#92](https://github.com/wp-graphql/wp-graphql/issues/92)
- Unit Tests for TaxonomyEnumType [\#83](https://github.com/wp-graphql/wp-graphql/issues/83)
- Unit Tests for RelationEnumType [\#82](https://github.com/wp-graphql/wp-graphql/issues/82)
- Unit Tests for PostTypeEnumType [\#81](https://github.com/wp-graphql/wp-graphql/issues/81)
- Unit Tests for PostStatusEnum [\#80](https://github.com/wp-graphql/wp-graphql/issues/80)
- Unit Tests for MimeTypeEnum [\#79](https://github.com/wp-graphql/wp-graphql/issues/79)
- Unit Tests for WPObjectType [\#77](https://github.com/wp-graphql/wp-graphql/issues/77)
- Unit Tests for WPInputObjectType [\#76](https://github.com/wp-graphql/wp-graphql/issues/76)
- Unit Tests for WPEnumType [\#75](https://github.com/wp-graphql/wp-graphql/issues/75)
- Unit Tests for RootQueryType [\#68](https://github.com/wp-graphql/wp-graphql/issues/68)
- Document xdebug max\_nesting\_limit [\#54](https://github.com/wp-graphql/wp-graphql/issues/54)
- Set up wpgraphql.com [\#22](https://github.com/wp-graphql/wp-graphql/issues/22)
- User Mutations [\#19](https://github.com/wp-graphql/wp-graphql/issues/19)
- Custom Taxonomy Mutation Registration API [\#14](https://github.com/wp-graphql/wp-graphql/issues/14)
- Custom Post Type Mutation Registration API [\#12](https://github.com/wp-graphql/wp-graphql/issues/12)
- Term Mutations [\#6](https://github.com/wp-graphql/wp-graphql/issues/6)
- Post Mutations [\#4](https://github.com/wp-graphql/wp-graphql/issues/4)

**Merged pull requests:**

- Feature/\#17 Adding mediaItem mutations. [\#189](https://github.com/wp-graphql/wp-graphql/pull/189) ([hughdevore](https://github.com/hughdevore))
- \#186 - Fix default "parent" arg for comment connection resolver [\#187](https://github.com/wp-graphql/wp-graphql/pull/187) ([jasonbahl](https://github.com/jasonbahl))
- Term mutation, absint\(\) error [\#185](https://github.com/wp-graphql/wp-graphql/pull/185) ([jasonbahl](https://github.com/jasonbahl))

## [v0.0.16](https://github.com/wp-graphql/wp-graphql/tree/v0.0.16) (2017-06-23)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.0.15...v0.0.16)

**Closed issues:**

- Consistent translations & late escaping [\#152](https://github.com/wp-graphql/wp-graphql/issues/152)
- Support $\_GET requests per spec [\#145](https://github.com/wp-graphql/wp-graphql/issues/145)

## [v0.0.15](https://github.com/wp-graphql/wp-graphql/tree/v0.0.15) (2017-06-19)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.0.14...v0.0.15)

## [v0.0.14](https://github.com/wp-graphql/wp-graphql/tree/v0.0.14) (2017-06-16)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.0.13...v0.0.14)

**Closed issues:**

- Change postTags to tags [\#147](https://github.com/wp-graphql/wp-graphql/issues/147)
- Attachment Queries [\#16](https://github.com/wp-graphql/wp-graphql/issues/16)

## [v0.0.13](https://github.com/wp-graphql/wp-graphql/tree/v0.0.13) (2017-06-15)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.0.12...v0.0.13)

**Implemented enhancements:**

- Add "postTypeLabels" field to the PostTypeType [\#51](https://github.com/wp-graphql/wp-graphql/issues/51)

## [v0.0.12](https://github.com/wp-graphql/wp-graphql/tree/v0.0.12) (2017-05-26)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.0.11...v0.0.12)

**Implemented enhancements:**

- Consider making the terms Global ID based off of $taxonomy-\>name [\#134](https://github.com/wp-graphql/wp-graphql/issues/134)

**Closed issues:**

- Unit Tests for CommentsConnectionResolver [\#84](https://github.com/wp-graphql/wp-graphql/issues/84)
- Unit Tests for UserType [\#74](https://github.com/wp-graphql/wp-graphql/issues/74)
- Unit Tests for ThemeType [\#72](https://github.com/wp-graphql/wp-graphql/issues/72)
- Unit Tests for TermObjectType [\#71](https://github.com/wp-graphql/wp-graphql/issues/71)
- Unit Tests for TaxonomyType [\#69](https://github.com/wp-graphql/wp-graphql/issues/69)
- Unit Tests for PostTypeType [\#67](https://github.com/wp-graphql/wp-graphql/issues/67)
- Unit Tests for PostObjectType [\#66](https://github.com/wp-graphql/wp-graphql/issues/66)
- Unit Tests for PluginType [\#64](https://github.com/wp-graphql/wp-graphql/issues/64)
- Unit Tests for CommentType [\#62](https://github.com/wp-graphql/wp-graphql/issues/62)
- Unit Tests for AvatarType [\#60](https://github.com/wp-graphql/wp-graphql/issues/60)

## [v0.0.11](https://github.com/wp-graphql/wp-graphql/tree/v0.0.11) (2017-04-29)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.0.10...v0.0.11)

## [v0.0.10](https://github.com/wp-graphql/wp-graphql/tree/v0.0.10) (2017-04-29)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.0.9...v0.0.10)

## [v0.0.9](https://github.com/wp-graphql/wp-graphql/tree/v0.0.9) (2017-04-29)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.0.8...v0.0.9)

**Implemented enhancements:**

- Add parent union field to PostObjectType [\#50](https://github.com/wp-graphql/wp-graphql/issues/50)
- add "relatedPostObject" union field to the CommentType [\#48](https://github.com/wp-graphql/wp-graphql/issues/48)
- JWT Authentication [\#2](https://github.com/wp-graphql/wp-graphql/issues/2)

**Fixed bugs:**

- Relay Pagination Issues on ConnectionResolvers [\#95](https://github.com/wp-graphql/wp-graphql/issues/95)

**Closed issues:**

- Root queries for post and terms resolve to same type [\#102](https://github.com/wp-graphql/wp-graphql/issues/102)

## [v0.0.8](https://github.com/wp-graphql/wp-graphql/tree/v0.0.8) (2017-04-21)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.0.7...v0.0.8)

## [v0.0.7](https://github.com/wp-graphql/wp-graphql/tree/v0.0.7) (2017-04-11)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/0.0.6...v0.0.7)

**Implemented enhancements:**

- Add filter to "get\_node\_definitions" [\#93](https://github.com/wp-graphql/wp-graphql/issues/93)
- Add "taxonomies" connection field to the PostTypeType [\#52](https://github.com/wp-graphql/wp-graphql/issues/52)

## [0.0.6](https://github.com/wp-graphql/wp-graphql/tree/0.0.6) (2017-03-15)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.0.6...0.0.6)

## [v0.0.6](https://github.com/wp-graphql/wp-graphql/tree/v0.0.6) (2017-03-15)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/0.0.5...v0.0.6)

**Implemented enhancements:**

- Harden the HTTP response [\#47](https://github.com/wp-graphql/wp-graphql/issues/47)

**Closed issues:**

- Hey Jason! Great work so far! [\#99](https://github.com/wp-graphql/wp-graphql/issues/99)
- Update README to include instructions for running unit tests [\#49](https://github.com/wp-graphql/wp-graphql/issues/49)
- Make Readme Better [\#1](https://github.com/wp-graphql/wp-graphql/issues/1)

## [0.0.5](https://github.com/wp-graphql/wp-graphql/tree/0.0.5) (2017-02-25)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/0.0.3...0.0.5)

**Implemented enhancements:**

- Harden the mapping between GraphQL Input Fields and Query Args [\#59](https://github.com/wp-graphql/wp-graphql/issues/59)
- Create abstract class for types [\#46](https://github.com/wp-graphql/wp-graphql/issues/46)

**Closed issues:**

- Figure out what to do with clover.xml file [\#97](https://github.com/wp-graphql/wp-graphql/issues/97)
- Move type field definitions out of construct [\#45](https://github.com/wp-graphql/wp-graphql/issues/45)
- Decide on a new organizational structure for types [\#44](https://github.com/wp-graphql/wp-graphql/issues/44)
- User Queries [\#18](https://github.com/wp-graphql/wp-graphql/issues/18)
- Comment Queries [\#7](https://github.com/wp-graphql/wp-graphql/issues/7)
- Term Queries [\#5](https://github.com/wp-graphql/wp-graphql/issues/5)
- Post Queries [\#3](https://github.com/wp-graphql/wp-graphql/issues/3)

## [0.0.3](https://github.com/wp-graphql/wp-graphql/tree/0.0.3) (2017-01-16)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/0.0.2...0.0.3)

## [0.0.2](https://github.com/wp-graphql/wp-graphql/tree/0.0.2) (2017-01-12)
[Full Changelog](https://github.com/wp-graphql/wp-graphql/compare/v0.0.2...0.0.2)



\* *This Change Log was automatically generated by [github_changelog_generator](https://github.com/skywinder/Github-Changelog-Generator)*
