<?php
// Run with: wp eval-file (container path). Creates the [ext-matrix] Color attribute and wires V / V1 / V2.
$v = (int) getenv( 'EXTMX_V' ); $v1 = (int) getenv( 'EXTMX_V1' ); $v2 = (int) getenv( 'EXTMX_V2' );
$attr_id = wc_attribute_taxonomy_id_by_name( 'extmx-color' );
if ( ! $attr_id ) {
	$attr_id = wc_create_attribute( [ 'name' => '[ext-matrix] Color', 'slug' => 'extmx-color', 'type' => 'select', 'order_by' => 'menu_order', 'has_archives' => false ] );
}
$tax = 'pa_extmx-color';
register_taxonomy( $tax, [ 'product' ], [ 'hierarchical' => false ] );
$red  = term_exists( 'extmx-red', $tax ) ?: wp_insert_term( '[ext-matrix] Red', $tax, [ 'slug' => 'extmx-red' ] );
$blue = term_exists( 'extmx-blue', $tax ) ?: wp_insert_term( '[ext-matrix] Blue', $tax, [ 'slug' => 'extmx-blue' ] );
$a = new WC_Product_Attribute();
$a->set_id( $attr_id ); $a->set_name( $tax ); $a->set_options( [ (int) $red['term_id'], (int) $blue['term_id'] ] ); $a->set_visible( true ); $a->set_variation( true );
$p = wc_get_product( $v ); $p->set_attributes( [ $a ] ); $p->save();
wp_set_object_terms( $v, [ (int) $red['term_id'], (int) $blue['term_id'] ], $tax );
foreach ( [ $v1 => 'extmx-red', $v2 => 'extmx-blue' ] as $vid => $slug ) { $x = wc_get_product( $vid ); $x->set_attributes( [ $tax => $slug ] ); $x->save(); }
WC_Product_Variable::sync( $v );
echo "ATTR=$attr_id\nRED={$red['term_id']}\nBLUE={$blue['term_id']}\n";
