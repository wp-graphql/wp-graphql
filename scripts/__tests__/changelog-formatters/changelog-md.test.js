const { getNewContributors, formatChangelog } = require('../../changelog-formatters/changelog-md');

describe('getNewContributors', () => {
  test('returns empty array when no new contributors', () => {
    const changesets = [
      { pr: 123, summary: 'feat: New feature' },
      { pr: 124, summary: 'fix: Bug fix' }
    ];

    expect(getNewContributors(changesets)).toEqual([]);
  });

  test('identifies new contributors correctly', () => {
    const changesets = [
      {
        pr: 123,
        summary: 'feat: New feature',
        contributorUsername: 'user1',
        newContributor: true
      },
      {
        pr: 124,
        summary: 'fix: Bug fix',
        contributorUsername: 'user2',
        newContributor: false
      },
      {
        pr: 125,
        summary: 'docs: Update docs',
        contributorUsername: 'user3',
        newContributor: true
      }
    ];

    const result = getNewContributors(changesets);

    expect(result).toHaveLength(2);
    expect(result).toContainEqual({ username: 'user1', pr: 123 });
    expect(result).toContainEqual({ username: 'user3', pr: 125 });
    expect(result).not.toContainEqual({ username: 'user2', pr: 124 });
  });
});

describe('formatChangelog with new contributors', () => {
  test('includes new contributors section when present', () => {
    const changesets = [
      {
        pr: 123,
        summary: 'feat: New feature',
        contributorUsername: 'newuser1',
        newContributor: true
      },
      {
        pr: 124,
        summary: 'fix: Bug fix',
        contributorUsername: 'returninguser',
        newContributor: false
      }
    ];

    const result = formatChangelog(changesets, { version: '1.0.0' });

    expect(result).toContain('### New Contributors');
    expect(result).toContain('@newuser1 made their first contribution');
    expect(result).toContain('[#123](https://github.com/wp-graphql/wp-graphql/pull/123)');
    expect(result).not.toContain('@returninguser');
  });

  test('omits new contributors section when none present', () => {
    const changesets = [
      {
        pr: 123,
        summary: 'feat: New feature',
        contributorUsername: 'user1',
        newContributor: false
      }
    ];

    const result = formatChangelog(changesets, { version: '1.0.0' });

    expect(result).not.toContain('### New Contributors');
  });
});