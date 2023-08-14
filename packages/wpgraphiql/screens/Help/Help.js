import { Card, Row, Col, Divider, Button } from "antd";
import styled from "styled-components";

const StyledWrapper = styled.div`
  padding: 24px;
  overflow-y: scroll;
`;

/**
 * Docs
 * - Getting Started
 * - Beginner Guides
 * - Using WPGraphQL
 * - Advanced Concepts
 * Developer Reference
 * - Recipes
 * - Actions
 * - Filters
 * - Functions
 * Extensions
 * Blog
 */
const Help = () => {
  return (
    <StyledWrapper>
      <h2>Help</h2>
      <p>
        On this page you will find resources to help you understand WPGraphQL,
        how to use GraphQL with WordPress, how to customize it and make it work
        for you, and how to get plugged into the community.
      </p>
      <Divider />
      <h3>Documentation</h3>
      <p>Below are helpful links to the official WPGraphQL documentation.</p>
      <Row gutter={[16, 16]}>
        <Col xs={24} sm={24} md={12} lg={12} xl={6}>
          <Card
            style={{ height: "100%" }}
            title="Getting Started"
            actions={[
              <a
                href="https://www.wpgraphql.com/docs/introduction/"
                target="_blank"
              >
                <Button type="primary">Get Started with WPGraphQL</Button>
              </a>,
            ]}
          >
            <p>
              In the Getting Started are resources to learn about GraphQL,
              WordPress, how they work together, and more.
            </p>
          </Card>
        </Col>
        <Col xs={24} sm={24} md={12} lg={12} xl={6}>
          <Card
            style={{ height: "100%" }}
            title="Beginner Guides"
            actions={[
              <a
                target="_blank"
                href="https://www.wpgraphql.com/docs/intro-to-graphql/"
              >
                <Button type="primary">Beginner Guides</Button>
              </a>,
            ]}
          >
            <p>
              The Beginner guides go over specific topics such as GraphQL,
              WordPress, tools and techniques to interact with GraphQL APIs and
              more.
            </p>
          </Card>
        </Col>
        <Col xs={24} sm={24} md={12} lg={12} xl={6}>
          <Card
            style={{ height: "100%" }}
            title="Using WPGraphQL"
            actions={[
              <a
                href="https://www.wpgraphql.com/docs/posts-and-pages/"
                target="_blank"
              >
                <Button type="primary">Using WPGraphQL</Button>
              </a>,
            ]}
          >
            <p>
              This section covers how WPGraphQL exposes WordPress data to the
              Graph, and shows how you can interact with this data using
              GraphQL.
            </p>
          </Card>
        </Col>
        <Col xs={24} sm={24} md={12} lg={12} xl={6}>
          <Card
            style={{ height: "100%" }}
            title="Advanced Concepts"
            actions={[
              <a
                href="https://www.wpgraphql.com/docs/wpgraphql-concepts/"
                target="_blank"
              >
                <Button type="primary">Advanced Concepts</Button>
              </a>,
            ]}
          >
            <p>
              Learn about concepts such as "connections", "edges", "nodes",
              "what is an application data graph?" and more{" "}
            </p>
          </Card>
        </Col>
      </Row>
      <Divider />
      <h3>Developer Reference</h3>
      <p>
        Below are helpful links to the WPGraphQL developer reference. These
        links will be most helpful to developers looking to customize WPGraphQL{" "}
      </p>
      <Row gutter={[16, 16]}>
        <Col xs={24} sm={24} md={12} lg={12} xl={6}>
          <Card
            style={{ height: "100%" }}
            title="Recipes"
            actions={[
              <a href="https://www.wpgraphql.com/recipes" target="_blank">
                <Button type="primary">Recipes</Button>
              </a>,
            ]}
          >
            <p>
              Here you will find snippets of code you can use to customize
              WPGraphQL. Most snippets are PHP and intended to be included in
              your theme or plugin.
            </p>
          </Card>
        </Col>
        <Col xs={24} sm={24} md={12} lg={12} xl={6}>
          <Card
            style={{ height: "100%" }}
            title="Actions"
            actions={[
              <a href="https://www.wpgraphql.com/actions" target="_blank">
                <Button type="primary">Actions</Button>
              </a>,
            ]}
          >
            <p>
              Here you will find an index of the WordPress "actions" that are
              used in the WPGraphQL codebase. Actions can be used to customize
              behaviors.
            </p>
          </Card>
        </Col>
        <Col xs={24} sm={24} md={12} lg={12} xl={6}>
          <Card
            style={{ height: "100%" }}
            title="Filters"
            actions={[
              <a href="https://www.wpgraphql.com/filters" target="_blank">
                <Button type="primary">Filters</Button>
              </a>,
            ]}
          >
            <p>
              Here you will find an index of the WordPress "filters" that are
              used in the WPGraphQL codebase. Filters are used to customize the
              Schema and more.
            </p>
          </Card>
        </Col>
        <Col xs={24} sm={24} md={12} lg={12} xl={6}>
          <Card
            style={{ height: "100%" }}
            title="Functions"
            actions={[
              <a href="https://www.wpgraphql.com/functions" target="_blank">
                <Button type="primary">Functions</Button>
              </a>,
            ]}
          >
            <p>
              Here you will find functions that can be used to customize the
              WPGraphQL Schema. Learn how to register GraphQL "fields", "types",
              and more.
            </p>
          </Card>
        </Col>
      </Row>
      <Divider />
      <h3>Community</h3>

      <Row gutter={[16, 16]}>
        <Col xs={24} sm={24} md={24} lg={8} xl={8}>
          <Card
            style={{ height: "100%" }}
            title="Blog"
            actions={[
              <a href="https://www.wpgraphql.com/Blog" target="_blank">
                <Button type="primary">Read the Blog</Button>
              </a>,
            ]}
          >
            <p>
              Keep up to date with the latest news and updates from the
              WPGraphQL team.
            </p>
          </Card>
        </Col>
        <Col xs={24} sm={24} md={24} lg={8} xl={8}>
          <Card
            style={{ height: "100%" }}
            title="Extensions"
            actions={[
              <a href="https://www.wpgraphql.com/Extensions" target="_blank">
                <Button type="primary">View Extensions</Button>
              </a>,
            ]}
          >
            <p>
              Browse the list of extensions that are available to extend
              WPGraphQL to work with other popular WordPress plugins.
            </p>
          </Card>
        </Col>
        <Col xs={24} sm={24} md={24} lg={8} xl={8}>
          <Card
            style={{ height: "100%" }}
            title="Join us in Slack"
            actions={[
              <a
                href="https://join.slack.com/t/wp-graphql/shared_invite/zt-3vloo60z-PpJV2PFIwEathWDOxCTTLA"
                target="_blank"
              >
                <Button type="primary">Join us in Slack</Button>
              </a>,
            ]}
          >
            <p>
              There are more than 2,000 people in the WPGraphQL Slack community
              asking questions and helping each other. Join us today!
            </p>
          </Card>
        </Col>
      </Row>
    </StyledWrapper>
  );
};

export default Help;
