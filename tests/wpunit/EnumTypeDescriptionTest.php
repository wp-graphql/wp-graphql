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

        add_filter('graphql_pre_enum_value_description', function($desc, $value_desc, $value_name, $enum_type) use (&$filter_called) {
            $filter_called = true;
            codecept_debug([
                'filter_called' => true,
                'desc' => $desc,
                'enum_type' => $enum_type,
                'value_name' => $value_name,
                'value_desc' => $value_desc
            ]);

            if ($enum_type === 'PostStatusEnum' && $value_name === 'PUBLISH') {
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
}