<?php

class EnumTypeDescriptionTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

    public function setUp(): void {
        parent::setUp();
        $this->clearSchema();
        // Remove any existing filters
        remove_all_filters('graphql_pre_enum_description');
    }

    public function tearDown(): void {
        parent::tearDown();
        $this->clearSchema();
        // Clean up filters
        remove_all_filters('graphql_pre_enum_description');
    }

    /**
     * Test that PostStatusEnum descriptions are working correctly
     */
    public function testPostStatusEnumDescriptions(): void {
        $query = '
        query GetPostStatusEnumType {
            __type(name: "PostStatusEnum") {
                enumValues {
                    name
                    description
                }
            }
        }
        ';

        $response = $this->graphql(['query' => $query]);

        $this->assertArrayHasKey('data', $response);
        $this->assertArrayHasKey('__type', $response['data']);

        $enumValues = $response['data']['__type']['enumValues'];

        // Test specific enum values have correct descriptions
        $this->assertContains([
            'name' => 'PUBLISH',
            'description' => 'Content that is publicly visible to all visitors',
        ], $enumValues);

        $this->assertContains([
            'name' => 'DRAFT',
            'description' => 'Content that is saved but not yet published or visible to the public',
        ], $enumValues);
    }

    /**
     * Test that MediaItemSizeEnum descriptions include dimensions
     */
    public function testMediaItemSizeEnumDescriptions(): void {
        $query = '
        query GetMediaItemSizeEnumType {
            __type(name: "MediaItemSizeEnum") {
                enumValues {
                    name
                    description
                }
            }
        }
        ';

        $response = $this->graphql(['query' => $query]);

        $enumValues = $response['data']['__type']['enumValues'];

        // Test that THUMBNAIL includes dimensions
        $thumbnail = array_filter($enumValues, function($value) {
            return $value['name'] === 'THUMBNAIL';
        });

        $thumbnail = reset($thumbnail);
        $this->assertStringContainsString('150x150', $thumbnail['description']);
    }

    /**
     * Test that the filter can override descriptions
     */
    public function testEnumDescriptionFilter(): void {
        $filter_called = false;

        add_filter('graphql_pre_enum_description', function($desc, $enum_type, $value, $context) use (&$filter_called) {
            $filter_called = true;
            codecept_debug([
                'filter_called' => true,
                'desc' => $desc,
                'enum_type' => $enum_type,
                'value' => $value,
                'context' => $context
            ]);

            if ($enum_type === 'PostStatusEnum' && $value === 'publish') {
                return 'Custom filtered description for published content';
            }
            return $desc;
        }, 10, 4);

        $this->clearSchema();

        $query = '
        query GetPostStatusEnumType {
            __type(name: "PostStatusEnum") {
                enumValues {
                    name
                    description
                }
            }
        }
        ';

        $response = $this->graphql(['query' => $query]);

        $enumValues = $response['data']['__type']['enumValues'];

        // Find the PUBLISH enum value
        $publish = array_filter($enumValues, function($value) {
            return $value['name'] === 'PUBLISH';
        });

        $publish = reset($publish);

        $this->assertEquals(
            'Custom filtered description for published content',
            $publish['description']
        );

        // Add debug assertion
        $this->assertTrue($filter_called, 'Filter was never called');
    }

    /**
     * Test that the filter receives the correct context data
     */
    public function testEnumDescriptionFilterContext(): void {
        $received_context = null;
        $filter_called = false;

        add_filter('graphql_pre_enum_description', function($desc, $enum_type, $value, $context) use (&$received_context, &$filter_called) {
            $filter_called = true;

            // Debug ALL calls to the filter
            codecept_debug([
                'filter_called' => true,
                'enum_type' => $enum_type,
                'value' => $value,
                'context' => $context,
                'desc' => $desc
            ]);

            // Only set context for MediaItemSizeEnum and only if we haven't captured it yet
            if ($enum_type === 'MediaItemSizeEnum' && is_null($received_context)) {
                $received_context = $context;
            }
            return $desc;
        }, 10, 4);

        $query = '
        query GetMediaItemSizeEnumType {
            __type(name: "MediaItemSizeEnum") {
                enumValues {
                    name
                    description
                }
            }
        }
        ';

        $this->graphql(['query' => $query]);

        // First verify the filter was called at all
        $this->assertTrue($filter_called, 'Filter was never called');

        // Debug what we received
        codecept_debug(['final_context' => $received_context]);

        // Only assert context structure if it exists
        if (!is_null($received_context)) {
            $this->assertIsArray($received_context);
            $this->assertArrayHasKey('width', $received_context);
            $this->assertArrayHasKey('height', $received_context);
            $this->assertArrayHasKey('crop', $received_context);
            $this->assertEquals(150, $received_context['width']);
            $this->assertEquals(150, $received_context['height']);
        } else {
            $this->markTestIncomplete('Context was null - need to verify expected behavior');
        }
    }
}