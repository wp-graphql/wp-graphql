<?php
// Creates the woo group's products with WooCommerce's PHP API and prints their IDs as KEY=VALUE
// lines. Run through fixtures/woo.sh (bin/lib.sh's wp_php), not directly.
//
// - P2: a simple product the others point at.
// - P1: its description renders a post-title block and a [product_page] shortcode for P2.
// - P3: its description runs a [products] loop for P2, then a post-title block.
// - V: a variable product (Red / Blue variations V1 and V2), with P2 as an upsell.

$simple = static function ( string $name, string $slug, string $price, string $description = '' ): int {
	$product = new WC_Product_Simple();
	$product->set_name( $name );
	$product->set_slug( $slug );
	$product->set_regular_price( $price );
	$product->set_description( $description );
	$product->set_status( 'publish' );
	return $product->save();
};

$p2 = $simple( '[ext-matrix] Probe Target P2', 'extmx-probe-target-p2', '20' );
$p1 = $simple( '[ext-matrix] Shortcode Host P1', 'extmx-shortcode-host-p1', '10', "<!-- wp:post-title {\"level\":2} /-->\n[product_page id=\"$p2\"]" );
$p3 = $simple( '[ext-matrix] Loop Host P3', 'extmx-loop-host-p3', '30', "[products ids=\"$p2\"]\n<!-- wp:post-title {\"level\":2} /-->" );

// A global attribute with two terms.
$attribute_id = wc_attribute_taxonomy_id_by_name( 'extmx-color' );
if ( ! $attribute_id ) {
	$attribute_id = wc_create_attribute( [ 'name' => '[ext-matrix] Color', 'slug' => 'extmx-color', 'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => false ] );
}
$taxonomy = 'pa_extmx-color';
register_taxonomy( $taxonomy, [ 'product' ], [ 'hierarchical' => false ] );
$red  = term_exists( 'extmx-red', $taxonomy ) ?: wp_insert_term( '[ext-matrix] Red', $taxonomy, [ 'slug' => 'extmx-red' ] );
$blue = term_exists( 'extmx-blue', $taxonomy ) ?: wp_insert_term( '[ext-matrix] Blue', $taxonomy, [ 'slug' => 'extmx-blue' ] );

$attribute = new WC_Product_Attribute();
$attribute->set_id( $attribute_id );
$attribute->set_name( $taxonomy );
$attribute->set_options( [ (int) $red['term_id'], (int) $blue['term_id'] ] );
$attribute->set_visible( true );
$attribute->set_variation( true );

$variable = new WC_Product_Variable();
$variable->set_name( '[ext-matrix] Variable V' );
$variable->set_slug( 'extmx-variable-v' );
$variable->set_description( '<!-- wp:post-title {"level":2} /-->' );
$variable->set_status( 'publish' );
$variable->set_attributes( [ $attribute ] );
$variable->set_upsell_ids( [ $p2 ] );
$v = $variable->save();
wp_set_object_terms( $v, [ (int) $red['term_id'], (int) $blue['term_id'] ], $taxonomy );

$variation_ids = [];
foreach ( [ 'extmx-red' => '11', 'extmx-blue' => '12' ] as $term_slug => $price ) {
	$variation = new WC_Product_Variation();
	$variation->set_parent_id( $v );
	$variation->set_attributes( [ $taxonomy => $term_slug ] );
	$variation->set_regular_price( $price );
	$variation->set_status( 'publish' );
	$variation_ids[] = $variation->save();
}
WC_Product_Variable::sync( $v );
wc_delete_product_transients( $v );

printf( "P2=%d\nP1=%d\nP3=%d\nATTR=%d\nV=%d\nV1=%d\nV2=%d\n", $p2, $p1, $p3, $attribute_id, $v, $variation_ids[0], $variation_ids[1] );
