<?php
/**
 * Search Form template override for Rabindra Medical Hall Child Theme.
 * Corrects label-for association and adds explicit autocomplete="off".
 *
 * @package Rabindra_Medical_Hall_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$astra_search_input_placeholder = isset( $args['input_placeholder'] ) ? $args['input_placeholder'] : ( function_exists( 'astra_default_strings' ) ? astra_default_strings( 'string-search-input-placeholder', false ) : __( 'Search &hellip;', 'rabindra-medical-hall' ) );
$astra_search_show_input_submit = isset( $args['show_input_submit'] ) ? $args['show_input_submit'] : true;
$astra_search_data_attrs        = isset( $args['data_attributes'] ) ? $args['data_attributes'] : '';
$astra_search_input_value       = isset( $args['input_value'] ) ? $args['input_value'] : '';

$search_id = 'search-field';
if ( did_action( 'astra_sticky_header_markup' ) > 0 ) {
	$search_id .= '-sticky';
}
?>
<form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label for="<?php echo esc_attr( $search_id ); ?>" class="screen-reader-text"><?php echo esc_html__( 'Search for:', 'rabindra-medical-hall' ); ?></label>
	<input
		type="search"
		id="<?php echo esc_attr( $search_id ); ?>"
		class="search-field"
		autocomplete="off"
		<?php echo $astra_search_data_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		placeholder="<?php echo esc_attr( $astra_search_input_placeholder ); ?>"
		value="<?php echo esc_attr( $astra_search_input_value ); ?>"
		name="s">
	<?php if ( class_exists( 'Astra_Icons' ) && Astra_Icons::is_svg_icons() ) { ?>
		<button class="search-submit ast-search-submit" aria-label="<?php echo esc_attr__( 'Search Submit', 'rabindra-medical-hall' ); ?>">
			<span hidden><?php echo esc_html__( 'Search', 'rabindra-medical-hall' ); ?></span>
			<i><?php Astra_Icons::get_icons( 'search', true ); ?></i>
		</button>
	<?php } ?>
	<?php if ( $astra_search_show_input_submit ) { ?>
		<input type="submit" class="search-submit" value="<?php echo esc_attr__( 'Search', 'rabindra-medical-hall' ); ?>">
	<?php } ?>
</form>
