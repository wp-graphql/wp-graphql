$j = jQuery.noConflict();

$j(document).ready(function () {

	// Update the "graphql_field_name" when
	// the "name" field is updated.
	var graphqlFieldSettings = new acf.Model({
		actions: {
			'change_field_name': 'updateGraphqlFieldName',
			'render_field_settings': 'updateGraphqlFieldName',
		},
		updateGraphqlFieldName: function( $el ) {

			var field = acf.getInstance($el);
			var $name = field.$setting('name');
			var $nameField = acf.getInstance( $name );

			// when a field is emptied, we'll do nothing
			if ( '' === $nameField || undefined === $nameField ) {
				field.prop( 'graphql_field_name', '' );
				return;
			}

			if ( undefined === $nameField?.getValue() ) {
				field.prop( 'graphql_field_name', '' );
				return;
			}

			var name = $nameField?.getValue() ?? null;

			if ( '' === name ) {
				field.prop( 'graphql_field_name', '' );
				return;
			}

			var $graphqlFieldName = field?.$setting('graphql_field_name');
			var graphqlFieldName = acf?.getInstance($graphqlFieldName)?.getValue() ?? '';

			if ( graphqlFieldName === '' ) {
				var sanitizedGraphqlFieldName = acf.strCamelCase( acf.strSanitize(name) );
				field.prop( 'graphql_field_name', sanitizedGraphqlFieldName );
			}
		},
	})

	var GraphqlLocationManager = new acf.Model({
		id: 'graphqlLocationManager',
		wait: 'ready',
		events: {
			'render_field_settings': 'onChangeRemoveRule',
			'click .add-location-rule': 'onClickAddRule',
			'click .add-location-group': 'onClickAddGroup',
			'click .remove-location-rule': 'onClickRemoveRule',
			'change .refresh-location-rule': 'onChangeRemoveRule',
			'change .rule-groups .operator select': 'onChangeRemoveRule',
			'change .rule-groups .value select': 'onChangeRemoveRule',
		},
		requestPending: false,
		initialize: function () {


			// ACF with settings tabs
			var el = $j('#acf-field-group-options');

			// ACF with settings tabs
			if ( ! el.length ) {
				// ACF before settings tabs existed
				el = $j('#acf-field-group-locations');
			}


			this.$el = el;
			this.getGraphqlTypes();
		},

		onClickAddRule: function (e, $el) {
			this.getGraphqlTypes();
		},

		onClickRemoveRule: function (e, $el) {
			this.getGraphqlTypes();
		},

		onChangeRemoveRule: function (e, $el) {
			setTimeout(function () {
				GraphqlLocationManager.getGraphqlTypes();
			}, 500);

		},

		onClickAddGroup: function (e, $el) {
			this.getGraphqlTypes();
		},

		isRequestPending: function() {
			return this.requestPending;
		},

		startRequest: function () {
			this.requestPending = true;
		},

		finishRequest: function() {
			this.requestPending = false;
		},

		getGraphqlTypes: function () {
			getGraphqlTypesFromLocationRules();
		},
	});

	/**
	 * Set the visibility of the GraphQL Fields based on the `show_in_graphql`
	 * field state.
	 */
	function setGraphqlFieldVisibility() {

		var showInGraphQLCheckbox = $j('#acf_field_group-show_in_graphql');
		var graphqlFields = $j('#wpgraphql-acf-meta-box .acf-field');

		graphqlFields.each(function (i, el) {
			if ($j(this).attr('data-name') !== 'show_in_graphql') {
				if (!showInGraphQLCheckbox.is(':checked')) {
					$j(this).hide();
				} else {
					$j(this).show();
				}
			}
		});

		showInGraphQLCheckbox.on('change', function () {
			setGraphqlFieldVisibility();
			getGraphqlTypesFromLocationRules();
		})

	}

	function toggleFieldRequirement() {

		$j('#acf_field_group-show_in_graphql').on('change', function () {
			var graphqlFieldNameWrap = $j('.acf-field[data-name="graphql_field_name"]'),
				graphqlLabel = graphqlFieldNameWrap.find('label'),
				graphqlInput = $j('#acf_field_group-graphql_field_name');

			if ($j(this).is(':checked')) {

				// Add span.acf-required if necessary.
				if (graphqlFieldNameWrap.find('.acf-required').length === 0) {
					graphqlLabel.append('<span class="acf-required">*</span>');
				}

				// Toggle required attributes and visual features.
				graphqlFieldNameWrap.addClass('is-required');
				graphqlLabel.find('.acf-required').show();
				graphqlInput.attr('required', true);
			} else {
				graphqlFieldNameWrap.removeClass('is-required');
				graphqlLabel.find('.acf-required').hide();
				graphqlInput.attr('required', false);
			}

		});

	}

	/**
	 * Listen to state changes for checkboxes for Interfaces and the checkboxes for
	 * Possible Types of the interfaces
	 */
	function initInterfaceCheckboxes() {

		// Find all the checkboxes for Interface types
		$j('span[data-interface]').each(function (i, el) {

			// Get the interface name
			let interfaceName = $j(el).data('interface');

			// Get the checkbox for the interface
			let interfaceCheckbox = $j('input[value="' + interfaceName + '"]');

			// Find all checkboxes that implement the interface
			let possibleTypesCheckboxes = $j('span[data-implements="' + interfaceName + '"]').siblings('input[type="checkbox"]');

			// Prepend some space before to nest the Types beneath the Interface
			possibleTypesCheckboxes.before("&nbsp;&nbsp;");

			// Listen for changes on the Interface checkbox
			interfaceCheckbox.change(function () {
				possibleTypesCheckboxes.prop('checked', $j(this).is(":checked"));
			})

		});

	}

	/**
	 * JavaScript version of the PHP lcfirst
	 *
	 * @param str
	 * @returns {string}
	 */
	function lcfirst(str) {
		str += ''
		const f = str.charAt(0)
			.toLowerCase()
		return f + str.substr(1)
	}

	/**
	 * JavaScript version of the PHP ucwords
	 *
	 * @param str
	 * @returns {string}
	 */
	function ucwords(str) {
		return str.toLowerCase().replace(/\b[a-z]/g, function (letter) {
			return letter.toUpperCase();
		})
	}

	/**
	 * Based on the WPGraphQL format_field_name function
	 *
	 * See: https://github.com/wp-graphql/wp-graphql/blob/cc0b383259383898c3a1bebe65adf1140290b37e/src/Utils/Utils.php#L85-L100
	 */
	function formatFieldName(fieldName) {

		fieldName.replace('[^a-zA-Z0-9 -]', '-');
		fieldName = lcfirst(fieldName);
		fieldName = lcfirst(fieldName.split('-').join(' '));
		fieldName = ucwords(fieldName);
		fieldName = lcfirst(fieldName.split(' ').join(''));
		return fieldName;

	}

	/**
	 * Set the GraphQL Field Name value based on the Field Group Title
	 * if the graphql_field_name has not already been set.
	 */
	function setGraphqlFieldName() {
		var graphqlFieldNameField = $j('#acf_field_group-graphql_field_name');

		// support for v6+
		if ( $j('#title.acf-headerbar-title-field').exists() ) {
			var fieldGroupTitle = $j('#title.acf-headerbar-title-field');

		// versions of ACF < v6
		} else {
			var fieldGroupTitle = $j('#titlediv #title');
		}

		if ('' === graphqlFieldNameField.val()) {
			graphqlFieldNameField.val(formatFieldName(fieldGroupTitle.val()));
		}
		fieldGroupTitle.on('change', function () {
			setGraphqlFieldName();
		});
	}


	/**
	 * Determine whether users should be able to interact with the checkboxes
	 * to manually set the GraphQL Types for the ACF Field Group
	 */
	function graphqlMapTypesFromLocations() {
		var checkboxes = $j('.acf-field[data-name="graphql_types"] .acf-checkbox-list input[type="checkbox"]');
		var manualMapTypes = $j('#acf_field_group-map_graphql_types_from_location_rules');

		if (manualMapTypes.not(':checked')) {
			getGraphqlTypesFromLocationRules();
		}

		checkboxes.each(function (i, el) {
			if (manualMapTypes.is(':checked')) {
				$j(this).removeAttr("disabled");
			} else {
				$j(this).attr("disabled", true);
			}
		});
		manualMapTypes.on('change', function () {
			graphqlMapTypesFromLocations();
		});
	}

	// Function to collect all nested fields under 'acf_field_group[location]'
	function collectFieldGroupData() {

		const selectors = [
			'[name^="acf_field_group[location]"]',
			'[name^="acf_field_group[graphql_field_name]"]',
			'[name^="post_title"]',
			'[name^="acf_field_group[active]"]',
		];

		// Select the fields that start with either of the specific name patterns
		var fields = $j(selectors.join(','));

		// Serialize only these fields
		var serializedData = fields.serialize();

		return serializedData;
	}

	function getGraphqlTypesFromLocationRules() {
		var showInGraphQLCheckbox = $j('#acf_field_group-show_in_graphql');
		var form = $j('#post');
		var checkboxes = $j('.acf-field[data-name="graphql_types"] .acf-checkbox-list input[type="checkbox"]');
		var manualMapTypes = $j('#acf_field_group-map_graphql_types_from_location_rules');

		// If Manual Type selection is checked,
		// Don't attempt to get GraphQL Types from the location rules
		if (manualMapTypes.is(':checked')) {
			return;
		}

		if ( ! showInGraphQLCheckbox.is(':checked') ) {
			return;
		}

		if ( 'pending' !== form.attr('data-request-pending') ) {

			// Start the request
			form.attr('data-request-pending', 'pending' );

			// Make the request
			$j.post(ajaxurl, {
				action: 'get_acf_field_group_graphql_types',
				data: collectFieldGroupData(),
				nonce: wp_graphql_acf.nonce
			}, function (res) {
				var types = res && res['graphql_types'] ? res['graphql_types'] : [];

				checkboxes.each(function (i, el) {
					var checkbox = $j(this);
					var value = $j(this).val();
					checkbox.prop('checked', false);
					if (types && types.length) {
						if (-1 !== $j.inArray(value, types)) {
							checkbox.prop('checked', true);
						}
					}
					checkbox.trigger("change");
				})

				// Signal that the request is finished
				form.removeAttr('data-request-pending');

			});
		}

	};

	// Initialize the functionality to track the state of the Interface checkboxes.
	initInterfaceCheckboxes();
	toggleFieldRequirement();
	setGraphqlFieldVisibility();
	setGraphqlFieldName();
	graphqlMapTypesFromLocations();


});
