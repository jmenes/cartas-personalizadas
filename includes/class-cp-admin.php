<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Admin {

	public function __construct() {
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'add_custom_fields' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_custom_fields' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
	}

	public function admin_scripts( $hook ) {
		global $post;
		if ( ( 'post.php' === $hook || 'post-new.php' === $hook ) && $post && 'product' === $post->post_type ) {
			wp_enqueue_script( 'jquery-ui-sortable' );
		}
	}

	public function add_custom_fields() {
		echo '<div class="options_group">';

		// Checkbox: Es Carta Personalizada
		woocommerce_wp_checkbox( array(
			'id'            => '_cp_is_letter',
			'label'         => __( 'Es Carta Personalizada', 'cartas-personalizadas' ),
			'description'   => __( 'Marcar si este producto es una carta personalizada que requiere formulario.', 'cartas-personalizadas' ),
		) );

		// Select: Formato de Entrega
		woocommerce_wp_select( array(
			'id'            => '_cp_delivery_format',
			'label'         => __( 'Formato de Entrega', 'cartas-personalizadas' ),
			'options'       => array(
				'physical' => __( 'Físico (Impresión - PDF sin imagen de fondo)', 'cartas-personalizadas' ),
				'digital'  => __( 'Digital (Descarga - PDF con imagen de fondo completa)', 'cartas-personalizadas' ),
			),
			'description'   => __( 'Si seleccionas Digital, recuerda marcar la casilla "Virtual" en los ajustes superiores de WooCommerce para desactivar el envío.', 'cartas-personalizadas' ),
			'desc_tip'      => true,
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

		echo '<p class="form-field _cp_templates_field" style="padding-left: 162px;">';
		echo '<label style="margin-left: -162px; float: left; width: 150px; font-weight: 600;">' . __( 'Plantillas PDF (Elementos)', 'cartas-personalizadas' ) . '</label>';
		
		// Selector to ADD a template
		echo '<span style="display: flex; gap: 8px; max-width: 50%; margin-bottom: 12px;">';
		echo '<select id="cp-add-template-select" class="wc-enhanced-select" style="flex-grow: 1;">';
		echo '<option value="">' . __( 'Seleccionar plantilla para añadir...', 'cartas-personalizadas' ) . '</option>';
		foreach ( $templates as $template ) {
			echo '<option value="' . esc_attr( $template->ID ) . '">' . esc_html( $template->post_title ) . '</option>';
		}
		echo '</select>';
		echo '<button type="button" id="cp-add-template-btn" class="button button-secondary">' . __( 'Añadir', 'cartas-personalizadas' ) . '</button>';
		echo '</span>';

		// Draggable list of selected templates
		echo '<div id="cp-templates-list" style="max-width: 50%; border: 1px solid #ccc; background: #fafafa; border-radius: 4px; padding: 5px; min-height: 50px;">';
		if ( empty( $selected_templates ) ) {
			echo '<p class="cp-no-templates-msg" style="color: #666; font-style: italic; margin: 10px; text-align: center;">' . __( 'No hay plantillas añadidas. Selecciona una arriba y pulsa Añadir.', 'cartas-personalizadas' ) . '</p>';
		} else {
			foreach ( $selected_templates as $selected_id ) {
				$template_post = get_post( $selected_id );
				if ( ! $template_post ) continue;
				
				echo '<div class="cp-template-item" style="display: flex; align-items: center; background: #fff; border: 1px solid #ddd; border-radius: 3px; padding: 8px 12px; margin-bottom: 5px; cursor: move;">';
				echo '<span class="cp-sort-handle" style="color: #999; margin-right: 10px; font-size: 16px;">☰</span>';
				echo '<span style="flex-grow: 1; font-weight: 600;">' . esc_html( $template_post->post_title ) . '</span>';
				echo '<input type="hidden" name="_cp_templates[]" value="' . esc_attr( $selected_id ) . '">';
				echo '<button type="button" class="cp-remove-template-btn button-link button-link-delete" style="color: #a00; font-size: 18px; line-height: 1; padding: 0 5px; text-decoration: none;">&times;</button>';
				echo '</div>';
			}
		}
		echo '</div>';
		
		echo '<span class="description" style="display: block; margin-top: 6px;">' . __( 'Arrastra las plantillas para definir el orden en que se presentarán y descargarán.', 'cartas-personalizadas' ) . '</span>';
		echo '</p>';

		// Style and script for sorting and dynamic row management
		?>
		<style type="text/css">
			.ui-state-highlight {
				background: #fdf5d9;
				border: 1px dashed #e6db55;
				height: 38px;
				margin-bottom: 5px;
				border-radius: 3px;
			}
		</style>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			// Initialize sortable
			if ($.isFunction($.fn.sortable)) {
				$('#cp-templates-list').sortable({
					axis: 'y',
					handle: '.cp-sort-handle',
					placeholder: 'ui-state-highlight',
					forcePlaceholderSize: true
				});
			}

			// Add template
			$('#cp-add-template-btn').on('click', function(e) {
				e.preventDefault();
				var $select = $('#cp-add-template-select');
				var id = $select.val();
				var title = $select.find('option:selected').text();

				if (!id) {
					return;
				}

				// Prevent duplicates
				if ($('#cp-templates-list input[value="' + id + '"]').length) {
					alert('<?php echo esc_js( __( 'Esta plantilla ya está añadida.', 'cartas-personalizadas' ) ); ?>');
					return;
				}

				// Remove "no templates" message
				$('.cp-no-templates-msg').remove();

				var itemHtml = '<div class="cp-template-item" style="display: flex; align-items: center; background: #fff; border: 1px solid #ddd; border-radius: 3px; padding: 8px 12px; margin-bottom: 5px; cursor: move;">' +
					'<span class="cp-sort-handle" style="color: #999; margin-right: 10px; font-size: 16px;">☰</span>' +
					'<span style="flex-grow: 1; font-weight: 600;">' + title + '</span>' +
					'<input type="hidden" name="_cp_templates[]" value="' + id + '">' +
					'<button type="button" class="cp-remove-template-btn button-link button-link-delete" style="color: #a00; font-size: 18px; line-height: 1; padding: 0 5px; text-decoration: none;">&times;</button>' +
					'</div>';

				$('#cp-templates-list').append(itemHtml);
				
				// Reset select (and trigger WooCommerce enhanced select update)
				$select.val('').trigger('change');
			});

			// Remove template
			$(document).on('click', '.cp-remove-template-btn', function(e) {
				e.preventDefault();
				$(this).closest('.cp-template-item').remove();

				if ($('#cp-templates-list .cp-template-item').length === 0) {
					var msg = '<p class="cp-no-templates-msg" style="color: #666; font-style: italic; margin: 10px; text-align: center;"><?php echo esc_js( __( 'No hay plantillas añadidas. Selecciona una arriba y pulsa Añadir.', 'cartas-personalizadas' ) ); ?></p>';
					$('#cp-templates-list').append(msg);
				}
			});
		});
		</script>
		<?php

		echo '</div>';
	}

	public function save_custom_fields( $post_id ) {
		// Save Checkbox
		$is_letter = isset( $_POST['_cp_is_letter'] ) ? 'yes' : 'no';
		update_post_meta( $post_id, '_cp_is_letter', $is_letter );

		// Save Delivery Format
		$delivery_format = isset( $_POST['_cp_delivery_format'] ) ? sanitize_text_field( $_POST['_cp_delivery_format'] ) : 'physical';
		update_post_meta( $post_id, '_cp_delivery_format', $delivery_format );

		// Save Templates Array
		if ( isset( $_POST['_cp_templates'] ) && is_array( $_POST['_cp_templates'] ) ) {
			$templates = array_map( 'sanitize_text_field', $_POST['_cp_templates'] );
			update_post_meta( $post_id, '_cp_templates', $templates );
		} else {
			delete_post_meta( $post_id, '_cp_templates' );
		}
	}
}
