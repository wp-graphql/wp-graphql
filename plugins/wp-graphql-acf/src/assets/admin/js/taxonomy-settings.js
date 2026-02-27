$j = jQuery.noConflict();

$j(document).ready(function () {
	new acf.Model({
		id: 'graphqlPostTypeSettings',
		events: {
			'blur .acf_singular_label': 'onChangeSingularLabel',
			'blur .acf_plural_label': 'onChangePluralLabel',
		},
		// when the singular label of thetaxonomy is changed
		// update the graphql_single_name field, if it doesn't have a value
		onChangeSingularLabel(e, $el) {
			const label = $el.val();
			const sanitized = acf.strCamelCase(acf.strSanitize(label));
			this.updateValue('#acf_taxonomy-graphql_single_name', sanitized);
		},
		// when the plural label of the taxonomy is changed
		// update the graphql_plural_name field, if it doesn't have a value
		onChangePluralLabel(e, $el) {
			const label = $el.val();
			const sanitized = acf.strCamelCase(acf.strSanitize(label));
			this.updateValue('#acf_taxonomy-graphql_plural_name', sanitized);
		},
		updateValue(fieldId, value) {
			const currentValue = $j(fieldId).val();

			// if there's already a value, do nothing
			if ('' !== currentValue) {
				return;
			}

			// set the value
			$j(fieldId).val(value);
		},
	});
});
