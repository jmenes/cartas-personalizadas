<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Admin {

	public function __construct() {
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_custom_fields' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_custom_fields' ) );
	}

	public function add_custom_fields() {
		echo '<div class="options_group">';

		// Checkbox: Es Carta Personalizada
		woocommerce_wp_checkbox( array(
			'id'            => '_cp_is_letter',
			'label'         => __( 'Es Carta Personalizada', 'cartas-personalizadas' ),
			'description'   => __( 'Marcar si este producto es una carta personalizada que requiere formulario.', 'cartas-personalizadas' ),
		) );

		// Select: Plantilla PDF (Multiple)
		$templates = get_posts( array(
			'post_type'      => 'cp_template',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		) );

		// Get currently selected templates
		global $post;
		$selected_templates = get_post_meta( $post->ID, '_cp_templates', true );
		if ( ! is_array( $selected_templates ) ) {
			// Backwards compatibility
			$old_template = get_post_meta( $post->ID, '_cp_template', true );
			$selected_templates = $old_template ? array( $old_template ) : array();
		}

		echo '<p class="form-field _cp_templates_field">';
		echo '<label for="_cp_templates">' . __( 'Plantillas PDF (Elementos)', 'cartas-personalizadas' ) . '</label>';
		echo '<select id="_cp_templates" name="_cp_templates[]" class="wc-enhanced-select" multiple="multiple" style="width: 50%;" data-placeholder="' . __( 'Seleccionar Plantillas', 'cartas-personalizadas' ) . '">';
		
		foreach ( $templates as $template ) {
			$selected = in_array( $template->ID, $selected_templates ) ? 'selected="selected"' : '';
			echo '<option value="' . esc_attr( $template->ID ) . '" ' . $selected . '>' . esc_html( $template->post_title ) . '</option>';
		}
		
		echo '</select>';
		echo '<span class="description">' . __( 'Selecciona las plantillas PDF que compondrán este producto (ej. Carta, Sobre). El orden de selección importará.', 'cartas-personalizadas' ) . '</span>';
		echo '</p>';

		echo '</div>';
	}

	public function save_custom_fields( $post_id ) {
		// Save Checkbox
		$is_letter = isset( $_POST['_cp_is_letter'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, '_cp_is_letter', $is_letter );

		// Save Templates Array
		if ( isset( $_POST['_cp_templates'] ) && is_array( $_POST['_cp_templates'] ) ) {
			$templates = array_map( 'sanitize_text_field', $_POST['_cp_templates'] );
			update_post_meta( $post_id, '_cp_templates', $templates );
		} else {
			delete_post_meta( $post_id, '_cp_templates' );
		}
	}
}
