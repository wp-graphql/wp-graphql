<?php

/**
 * Tests for Date Picker, Date Time Picker and Time Picker fields nested in
 * repeater and flexible content rows.
 *
 * Stored values must resolve directly without round-tripping through PHP strtotime()
 * or field return formats, avoiding Unix Epoch (1970) or date part corruption.
 *
 * @see https://github.com/wp-graphql/wp-graphql/issues/4524
 * @see https://github.com/wp-graphql/wp-graphql/issues/4564
 */
class NestedDateTimeFieldsTest extends \Tests\WPGraphQL\Acf\WPUnit\WPGraphQLAcfTestCase {

	public function setUp(): void {
		parent::setUp();

		if ( ! defined( 'ACF_PRO' ) ) {
			$this->markTestSkipped( 'ACF Pro is not active so this test will not run.' );
		}
	}

	public function tearDown(): void {
		parent::tearDown();
	}

	/**
	 * Register an ACF repeater field.
	 *
	 * @param string $name       The name of the repeater.
	 * @param array  $sub_fields The sub fields of the repeater.
	 *
	 * @return string The key of the repeater.
	 */
	protected function register_repeater( string $name, array $sub_fields ): string {
		return $this->register_acf_field(
			[
				'key'                => uniqid( 'field_nested_repeater_', true ),
				'name'               => $name,
				'type'               => 'repeater',
				'graphql_field_name' => \WPGraphQL\Utils\Utils::format_field_name( $name ),
				'sub_fields'         => $sub_fields,
			]
		);
	}

	/**
	 * Execute GraphQL query against published post.
	 *
	 * @param string $fragment The selection of fields on AcfTestGroup.
	 *
	 * @return array
	 */
	protected function query_post( string $fragment ): array {
		return $this->graphql(
			[
				'query'     => '
				query GetPost( $id: ID! ) {
				  post( id: $id, idType: DATABASE_ID ) {
				    acfTestGroup {
				      ' . $fragment . '
				    }
				  }
				}
				',
				'variables' => [
					'id' => $this->published_post->ID,
				],
			]
		);
	}

	public function testDateTimePickerInRepeaterResolvesFarFutureDateWithoutEpoch(): void {
		$repeater_key = $this->register_repeater(
			'repeater_with_dt_picker',
			[
				[
					'key'                => 'field_nested_dt_picker',
					'name'               => 'event_datetime',
					'type'               => 'date_time_picker',
					'show_in_graphql'    => 1,
					'graphql_field_name' => 'eventDatetime',
					'return_format'      => 'd/m/Y g:i a',
				],
			]
		);

		update_field(
			$repeater_key,
			[
				[ 'field_nested_dt_picker' => '2026-11-25 14:30:00' ],
				[ 'field_nested_dt_picker' => '2026-10-05 09:15:22' ],
			],
			$this->published_post->ID
		);

		$actual = $this->query_post( 'repeaterWithDtPicker { eventDatetime }' );

		codecept_debug( $actual );

		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'post.acfTestGroup.repeaterWithDtPicker.0.eventDatetime', '2026-11-25T14:30:00+00:00' ),
				$this->expectedField( 'post.acfTestGroup.repeaterWithDtPicker.1.eventDatetime', '2026-10-05T09:15:22+00:00' ),
			]
		);
	}

	public function testDatePickerInRepeaterResolvesFarFutureDateWithoutEpoch(): void {
		$repeater_key = $this->register_repeater(
			'repeater_with_d_picker',
			[
				[
					'key'                => 'field_nested_d_picker',
					'name'               => 'event_date',
					'type'               => 'date_picker',
					'show_in_graphql'    => 1,
					'graphql_field_name' => 'eventDate',
					'return_format'      => 'd/m/Y',
				],
			]
		);

		update_field(
			$repeater_key,
			[
				[ 'field_nested_d_picker' => '20261125' ],
				[ 'field_nested_d_picker' => '20260511' ],
			],
			$this->published_post->ID
		);

		$actual = $this->query_post( 'repeaterWithDPicker { eventDate }' );

		codecept_debug( $actual );

		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'post.acfTestGroup.repeaterWithDPicker.0.eventDate', '2026-11-25T00:00:00+00:00' ),
				$this->expectedField( 'post.acfTestGroup.repeaterWithDPicker.1.eventDate', '2026-05-11T00:00:00+00:00' ),
			]
		);
	}

	public function testTimePickerInRepeaterResolvesFormattedTime(): void {
		$repeater_key = $this->register_repeater(
			'repeater_with_t_picker',
			[
				[
					'key'                => 'field_nested_t_picker',
					'name'               => 'event_time',
					'type'               => 'time_picker',
					'show_in_graphql'    => 1,
					'graphql_field_name' => 'eventTime',
					'return_format'      => 'g:i a',
				],
			]
		);

		update_field(
			$repeater_key,
			[
				[ 'field_nested_t_picker' => '14:30:00' ],
				[ 'field_nested_t_picker' => '09:15:00' ],
			],
			$this->published_post->ID
		);

		$actual = $this->query_post( 'repeaterWithTPicker { eventTime }' );

		codecept_debug( $actual );

		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'post.acfTestGroup.repeaterWithTPicker.0.eventTime', '2:30 pm' ),
				$this->expectedField( 'post.acfTestGroup.repeaterWithTPicker.1.eventTime', '9:15 am' ),
			]
		);
	}

	public function testDateTimePickerInFlexibleContentResolves(): void {
		$this->register_acf_field(
			[
				'key'                => 'field_nested_dt_flex',
				'name'               => 'nested_dt_flex',
				'type'               => 'flexible_content',
				'graphql_field_name' => 'nestedDtFlex',
				'layouts'            => [
					'layout_event' => [
						'key'        => 'layout_event',
						'name'       => 'event_layout',
						'label'      => 'Event Layout',
						'display'    => 'block',
						'sub_fields' => [
							[
								'key'                => 'field_nested_flex_dt',
								'name'               => 'meeting_time',
								'type'               => 'date_time_picker',
								'show_in_graphql'    => 1,
								'graphql_field_name' => 'meetingTime',
								'return_format'      => 'd/m/Y g:i a',
								'parent_layout'      => 'layout_event',
							],
						],
					],
				],
			]
		);

		update_field(
			'field_nested_dt_flex',
			[
				[
					'acf_fc_layout'        => 'event_layout',
					'field_nested_flex_dt' => '2026-11-25 14:30:00',
				],
			],
			$this->published_post->ID
		);

		$actual = $this->query_post( 'nestedDtFlex { __typename ... on AcfTestGroupNestedDtFlexEventLayoutLayout { meetingTime } }' );

		codecept_debug( $actual );

		self::assertQuerySuccessful(
			$actual,
			[
				$this->expectedField( 'post.acfTestGroup.nestedDtFlex.0.meetingTime', '2026-11-25T14:30:00+00:00' ),
			]
		);
	}
}
