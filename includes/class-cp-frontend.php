<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Frontend {
	
	private $has_rendered_form = false;

	public function __construct() {
		// Display form before add to cart button
		add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'display_personalization_form_action' ) );

		// Validate add to cart
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 10, 3 );

		// Add custom data to cart item
		add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 2 );

		// Display custom data in cart and checkout
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );

		// Add custom data to order line item
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_line_item_data' ), 10, 4 );
		
		// Enqueue scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// AJAX for preview
		add_action( 'wp_ajax_cp_generate_preview', array( $this, 'ajax_generate_preview' ) );
		add_action( 'wp_ajax_nopriv_cp_generate_preview', array( $this, 'ajax_generate_preview' ) );
		// Shortcode for preview
		add_shortcode( 'cp_preview', array( $this, 'render_preview_shortcode' ) );
		// Shortcode for form
		add_shortcode( 'cp_form', array( $this, 'render_form_shortcode' ) );
	}

	public function render_preview_shortcode() {
		return '<div id="cp-preview-container" style="margin-top: 20px; border: 2px dashed #ccc; padding: 20px; text-align: center; background: #f9f9f9; min-height: 200px; display: flex; align-items: center; justify-content: center;">' . 
			'<span style="color: #999;">' . __( 'La previsualización de tu carta aparecerá aquí.', 'cartas-personalizadas' ) . '</span>' .
			'</div>';
	}

	public function enqueue_scripts() {
		global $post;
		
		$should_enqueue = false;
		if ( is_product() ) {
			$should_enqueue = true;
		} elseif ( is_a( $post, 'WP_Post' ) && ( has_shortcode( $post->post_content, 'cp_form' ) || has_shortcode( $post->post_content, 'cp_preview' ) ) ) {
			$should_enqueue = true;
		}

		if ( $should_enqueue ) {
			wp_enqueue_script( 'pdfjs', CP_PLUGIN_URL . 'assets/js/pdfjs/pdf.min.js', array(), '3.11.174', true );
			wp_enqueue_script( 'pdfjs-worker', CP_PLUGIN_URL . 'assets/js/pdfjs/pdf.worker.min.js', array(), '3.11.174', true );
			
			wp_enqueue_script( 'cp-frontend', CP_PLUGIN_URL . 'assets/js/frontend.js', array( 'jquery', 'pdfjs' ), '1.0.0', true );
			wp_localize_script( 'cp-frontend', 'cp_ajax', array(
				'url' => admin_url( 'admin-ajax.php' ),
				'nonce' => wp_create_nonce( 'cp_preview_nonce' ),
				'pdfWorkerUrl' => CP_PLUGIN_URL . 'assets/js/pdfjs/pdf.worker.min.js'
			) );
			wp_enqueue_style( 'cp-frontend', CP_PLUGIN_URL . 'assets/css/frontend.css' );
		}
	}

	public function display_personalization_form_action() {
		echo $this->get_personalization_form_html();
	}

	public function render_form_shortcode( $atts ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return '';
		}

		$atts = shortcode_atts( array(
			'product_id' => 0,
		), $atts, 'cp_form' );

		$product_id = intval( $atts['product_id'] );
		if ( ! $product_id ) {
			global $product;
			if ( $product ) {
				$product_id = $product->get_id();
			}
		}

		if ( ! $product_id ) {
			return '<p>' . __( 'No se encontró un producto válido para mostrar el formulario de personalización.', 'cartas-personalizadas' ) . '</p>';
		}

		return $this->get_personalization_form_html( $product_id, true );
	}

	public function get_personalization_form_html( $product_id = 0, $is_shortcode = false ) {
		// Prevent double-rendering if both action and shortcode fire on the same page
		if ( $this->has_rendered_form ) {
			return '';
		}

		if ( ! $product_id ) {
			global $product;
			if ( ! $product ) {
				return '<p><strong>[Cartas]</strong> ' . __( 'Error: No se ha detectado el producto. Asegúrate de estar en la página de un producto o de usar [cp_form product_id="..."]', 'cartas-personalizadas' ) . '</p>';
			}
			$product_id = $product->get_id();
		}

		$is_letter = get_post_meta( $product_id, '_cp_is_letter', true );
		if ( 'yes' !== $is_letter ) {
			return '<p style="color: #999;"><strong>[Cartas]</strong> ' . __( 'Este producto no está marcado como "Carta Personalizable" en sus ajustes.', 'cartas-personalizadas' ) . '</p>';
		}

		$templates = get_post_meta( $product_id, '_cp_templates', true );
		if ( ! is_array( $templates ) || empty( $templates ) ) {
			// Fallback to single if array is empty (legacy)
			$single_template = get_post_meta( $product_id, '_cp_template', true );
			if ( $single_template ) {
				$templates = array( $single_template );
			} else {
				return '<p style="color: #999;"><strong>[Cartas]</strong> ' . __( 'No hay ninguna Plantilla asignada a este producto.', 'cartas-personalizadas' ) . '</p>';
			}
		}

		ob_start();

		echo '<div class="cp-personalization-form">';
		echo '<input type="hidden" id="cp_product_id" value="' . esc_attr( $product_id ) . '">';
		
		foreach ( $templates as $t_index => $template_id ) {
			$template_title = get_the_title( $template_id );
			
			echo '<div class="cp-template-block" data-index="' . $t_index . '" data-template="' . esc_attr( $template_id ) . '" style="margin-bottom: 20px;">';
			echo '<div style="font-size: 1.25em; font-weight: bold; margin-bottom: 15px; color: #444;">' . esc_html( $template_title ) . '</div>';
			
			echo '<div class="cp-template-layout-grid">';
			
			// Left side: Form fields
			echo '<div class="cp-template-form-side">';

			$models_data = get_post_meta( $template_id, '_cp_models', true );
			if ( ! is_array( $models_data ) ) {
				$models_data = json_decode( $models_data, true ) ?: array();
			}

			// Backwards compatibility for old templates
			if ( empty( $models_data ) || ! is_array( $models_data ) ) {
				$blocks_data = get_post_meta( $template_id, '_cp_template_blocks', true );
				if ( ! is_array( $blocks_data ) ) {
					$blocks_data = json_decode( $blocks_data, true );
				}
				$models_data = array(
					array(
						'id' => 'legacy',
						'name' => 'Opción 1',
						'description' => '',
						'blocks' => is_array($blocks_data) ? $blocks_data : array()
					)
				);
			}

			$has_multiple = count( $models_data ) > 1;

			if ( $has_multiple ) {
				echo '<div class="cp-model-selector-wrap" style="margin-bottom: 25px;">';
				echo '<fieldset style="border:none; padding:0; margin:0;"><legend style="font-weight:bold; margin-bottom:10px; font-size:1.1em;">' . __( 'Elige un modelo', 'cartas-personalizadas' ) . '</legend>';
				
				foreach ( $models_data as $m_idx => $model ) {
					$checked = ( $m_idx === 0 ) ? 'checked' : '';
					echo '<div class="cp-model-radio-item" style="margin-bottom: 15px; background: #fafafa; padding: 12px; border: 1px solid #ddd; border-radius: 4px;">';
					echo '<label style="display:flex; align-items: flex-start; font-weight:normal; cursor:pointer;">';
					echo '<input type="radio" class="cp-model-selector" name="cp_data[' . $t_index . '][model_id]" data-tindex="' . $t_index . '" value="' . esc_attr( $m_idx ) . '" ' . $checked . ' style="margin-right: 12px; margin-top: 4px;">';
					echo '<span style="display:block;">';
					echo '<strong style="display:block; font-size: 1.05em; margin-bottom: 2px;">' . esc_html( $model['name'] ) . '</strong>';
					if ( ! empty( $model['description'] ) ) {
						echo '<span style="display:block; color: #666; font-size: 0.9em; line-height: 1.3;">' . esc_html( $model['description'] ) . '</span>';
					}
					echo '</span>';
					echo '</label>';
					echo '</div>';
				}
				echo '</fieldset>';
				echo '</div>';
			} else {
				echo '<input type="hidden" class="cp-model-selector" name="cp_data[' . $t_index . '][model_id]" data-tindex="' . $t_index . '" value="0">';
			}

			foreach ( $models_data as $m_idx => $model ) {
				$blocks_data = isset( $model['blocks'] ) ? $model['blocks'] : array();
				$display_style = ( $m_idx === 0 ) ? 'block' : 'none';
				// Input disabled status: if not active model, disable to avoid HTML5 validation issues
				$disabled_attr = ( $m_idx === 0 ) ? '' : 'disabled';
				
				echo '<div class="cp-model-fields cp-model-fields-' . $t_index . '-' . $m_idx . '" data-tindex="' . $t_index . '" data-midx="' . $m_idx . '" style="display: ' . $display_style . ';">';

				$model_variables = isset( $model['variables'] ) ? $model['variables'] : array();
				
				if ( is_array( $model_variables ) && ! empty( $model_variables ) ) {
					foreach ( $model_variables as $v_index => $variable ) {
						$label = isset( $variable['label'] ) && !empty( $variable['label'] ) ? $variable['label'] : __( 'Variable', 'cartas-personalizadas' );
						$desc = isset( $variable['desc'] ) ? $variable['desc'] : '';
						$v_type = isset( $variable['type'] ) ? $variable['type'] : 'text';
						$tag = isset( $variable['tag'] ) ? $variable['tag'] : 'var_' . $v_index;
						$max = isset( $variable['max_chars'] ) && !empty( $variable['max_chars'] ) ? intval( $variable['max_chars'] ) : '';
						$max_attr = $max ? ' maxlength="' . $max . '"' : '';
						
						echo '<p class="form-row form-row-wide" style="width: 100%;">';
						echo '<label for="cp_var_' . $t_index . '_' . $m_idx . '_' . $v_index . '" style="display: block; margin-bottom: 5px;">' . esc_html( $label ) . ' <span class="required">*</span></label>';
						if ( ! empty( $desc ) ) {
							echo '<span style="display: block; color: #666; font-size: 0.9em; margin-bottom: 5px;">' . esc_html( $desc ) . '</span>';
						}
						
						if ( $v_type === 'textarea' ) {
							echo '<textarea class="input-text cp-dynamic-field cp-dynamic-variable" data-tag="' . esc_attr( $tag ) . '" name="cp_data[' . $t_index . '][models][' . $m_idx . '][variables][' . esc_attr( $tag ) . ']" id="cp_var_' . $t_index . '_' . $m_idx . '_' . $v_index . '" rows="2" style="width: 100%; box-sizing: border-box; font-size: 1.1em; padding: 8px; resize: none;" required ' . $max_attr . ' ' . $disabled_attr . '></textarea>';
						} else {
							echo '<input type="text" class="input-text cp-dynamic-field cp-dynamic-variable" data-tag="' . esc_attr( $tag ) . '" name="cp_data[' . $t_index . '][models][' . $m_idx . '][variables][' . esc_attr( $tag ) . ']" id="cp_var_' . $t_index . '_' . $m_idx . '_' . $v_index . '" style="width: 100%; box-sizing: border-box; font-size: 1.1em; padding: 8px;" required ' . $max_attr . ' ' . $disabled_attr . '>';
						}
						echo '</p>';
					}
				}

				if ( is_array( $blocks_data ) && ! empty( $blocks_data ) ) {
					foreach ( $blocks_data as $b_index => $block ) {
						$type = isset( $block['type'] ) ? $block['type'] : 'fixed';
						
						if ( $type === 'input' ) {
							$label = isset( $block['label'] ) && !empty( $block['label'] ) ? $block['label'] : __( 'Texto', 'cartas-personalizadas' );
							$field_type = isset( $block['field_type'] ) ? $block['field_type'] : 'text';
							$desc = isset( $block['desc'] ) ? $block['desc'] : '';
							$max = isset( $block['max_chars'] ) && !empty( $block['max_chars'] ) ? intval( $block['max_chars'] ) : '';
							$max_attr = $max ? ' maxlength="' . $max . '"' : '';
							
							echo '<p class="form-row form-row-wide" style="width: 100%;">';
							echo '<label for="cp_' . $t_index . '_' . $m_idx . '_' . $b_index . '" style="display: block; margin-bottom: 5px;">' . esc_html( $label ) . ' <span class="required">*</span></label>';
							if ( ! empty( $desc ) ) {
								echo '<span style="display: block; color: #666; font-size: 0.9em; margin-bottom: 5px;">' . esc_html( $desc ) . '</span>';
							}
							if ( $field_type === 'textarea' ) {
								echo '<textarea class="input-text cp-dynamic-field" name="cp_data[' . $t_index . '][models][' . $m_idx . '][blocks][' . $b_index . ']" id="cp_' . $t_index . '_' . $m_idx . '_' . $b_index . '" rows="4" style="width: 100%; box-sizing: border-box; font-size: 1.1em; padding: 8px; resize: none;" required ' . $max_attr . ' ' . $disabled_attr . '></textarea>';
							} else {
								echo '<input type="text" class="input-text cp-dynamic-field" name="cp_data[' . $t_index . '][models][' . $m_idx . '][blocks][' . $b_index . ']" id="cp_' . $t_index . '_' . $m_idx . '_' . $b_index . '" style="width: 100%; box-sizing: border-box; font-size: 1.1em; padding: 8px;" required ' . $max_attr . ' ' . $disabled_attr . '>';
							}
						}
					}
				} else {
					// Legacy Fallback if no blocks configured
					echo '<p class="form-row form-row-wide" style="width: 100%;">';
					echo '<label style="display: block; margin-bottom: 5px;">' . __( 'Nombre del Destinatario', 'cartas-personalizadas' ) . ' <span class="required">*</span></label>';
					echo '<input type="text" class="input-text cp-dynamic-field cp-dynamic-variable" data-tag="{name}" name="cp_data[' . $t_index . '][models][' . $m_idx . '][blocks][0][{name}]" style="width: 100%; box-sizing: border-box; font-size: 1.1em; padding: 8px;" required ' . $disabled_attr . '>';
					echo '</p>';
					echo '<p class="form-row form-row-wide" style="width: 100%;">';
					echo '<label style="display: block; margin-bottom: 5px;">' . __( 'Contenido', 'cartas-personalizadas' ) . ' <span class="required">*</span></label>';
					echo '<textarea class="input-text cp-dynamic-field" name="cp_data[' . $t_index . '][models][' . $m_idx . '][blocks][1]" rows="5" style="width: 100%; box-sizing: border-box; font-size: 1.1em; padding: 8px; resize: none;" required ' . $disabled_attr . '></textarea>';
					echo '</p>';
				}
				
				echo '</div>'; // close cp-model-fields
			}

			echo '<button type="button" class="button cp-preview-btn" data-index="' . $t_index . '">' . sprintf( __( 'Previsualizar %s', 'cartas-personalizadas' ), $template_title ) . '</button>';
			
			echo '</div>'; // close cp-template-form-side
			
			// Right side: Preview
			echo '<div class="cp-preview-container" id="cp-preview-container-' . $t_index . '" style="border: 2px dashed #ccc; padding: 15px; text-align: center; background: #fafafa; min-height: 150px; display: flex; align-items: center; justify-content: center; border-radius: 4px;">';
			echo '<span style="color: #999;">' . sprintf( __( 'La previsualización de %s aparecerá aquí.', 'cartas-personalizadas' ), esc_html( $template_title ) ) . '</span>';
			echo '</div>';
			
			echo '</div>'; // close cp-template-layout-grid
			echo '</div>'; // close cp-template-block
		}
		
		echo '</div>'; // close cp-personalization-form

		$this->has_rendered_form = true;

		return ob_get_clean();
	}

	public function validate_add_to_cart( $passed, $product_id, $quantity ) {
		$is_letter = get_post_meta( $product_id, '_cp_is_letter', true );
		if ( 'yes' === $is_letter ) {
			if ( empty( $_POST['cp_data'] ) || ! is_array( $_POST['cp_data'] ) ) {
				wc_add_notice( __( 'Por favor, rellena todos los campos de personalización.', 'cartas-personalizadas' ), 'error' );
				return false;
			}
			
			foreach ( $_POST['cp_data'] as $index => $data ) {
				$m_idx = isset($data['model_id']) ? intval($data['model_id']) : 0;
				if ( empty( $data['models'][$m_idx]['blocks'] ) && empty( $data['models'][$m_idx]['variables'] ) ) {
					wc_add_notice( __( 'Por favor, rellena todos los campos de personalización para el modelo seleccionado.', 'cartas-personalizadas' ), 'error' );
					return false;
				}
			}
		}
		return $passed;
	}

	public function add_cart_item_data( $cart_item_data, $product_id ) {
		if ( isset( $_POST['cp_data'] ) && is_array( $_POST['cp_data'] ) ) {
			$sanitized_data = array();
			
			// Re-fetch templates to associate template ID with data
			$templates = get_post_meta( $product_id, '_cp_templates', true );
			if ( empty( $templates ) && $old = get_post_meta( $product_id, '_cp_template', true ) ) {
				$templates = array( $old );
			}
			
			$delivery_format = get_post_meta( $product_id, '_cp_delivery_format', true );
			if ( empty( $delivery_format ) ) {
				$delivery_format = 'physical';
			}
			
			foreach ( $_POST['cp_data'] as $index => $data ) {
				$template_id = isset( $templates[$index] ) ? $templates[$index] : 0;
				$m_idx = isset($data['model_id']) ? intval($data['model_id']) : 0;
				
				// Fetch model name to save it
				$models_data = get_post_meta( $template_id, '_cp_models', true );
				if ( ! is_array( $models_data ) ) $models_data = json_decode( $models_data, true ) ?: array();
				
				$model_name = isset( $models_data[$m_idx]['name'] ) ? $models_data[$m_idx]['name'] : '';
				
				// Sanitize the dynamically structured blocks array
				$sanitized_blocks = array();
				if ( isset( $data['models'][$m_idx]['blocks'] ) && is_array( $data['models'][$m_idx]['blocks'] ) ) {
					foreach ( $data['models'][$m_idx]['blocks'] as $b_idx => $b_val ) {
						if ( is_array( $b_val ) ) {
							// Legacy fix block processing
							$s_vars = array();
							foreach ( $b_val as $tag => $val ) {
								$s_vars[ sanitize_text_field( $tag ) ] = sanitize_textarea_field( wp_unslash( $val ) );
							}
							$sanitized_blocks[ $b_idx ] = $s_vars;
						} else {
							// Direct input block
							$sanitized_blocks[ $b_idx ] = sanitize_textarea_field( wp_unslash( $b_val ) );
						}
					}
				}

				$sanitized_variables = array();
				if ( isset( $data['models'][$m_idx]['variables'] ) && is_array( $data['models'][$m_idx]['variables'] ) ) {
					foreach ( $data['models'][$m_idx]['variables'] as $tag => $val ) {
						$sanitized_variables[ sanitize_text_field( $tag ) ] = sanitize_textarea_field( wp_unslash( $val ) );
					}
				}

				$sanitized_data[] = array(
					'blocks'          => $sanitized_blocks,
					'variables'       => $sanitized_variables,
					'template_id'     => $template_id,
					'template_name'   => get_the_title( $template_id ),
					'model_id'        => $m_idx,
					'model_name'      => $model_name,
					'delivery_format' => $delivery_format
				);
			}
			$cart_item_data['cp_personalizations'] = $sanitized_data;
		}
		return $cart_item_data;
	}

	public function display_cart_item_data( $item_data, $cart_item ) {
		if ( isset( $cart_item['cp_personalizations'] ) && is_array( $cart_item['cp_personalizations'] ) ) {
			foreach ( $cart_item['cp_personalizations'] as $index => $data ) {
				$item_data[] = array(
					'key'     => sprintf( __( 'Plantilla: %s', 'cartas-personalizadas' ), $data['template_name'] ),
					'value'   => !empty($data['model_name']) ? sprintf( __( 'Modelo: %s', 'cartas-personalizadas' ), $data['model_name'] ) : __( 'Personalizada ✔', 'cartas-personalizadas' ),
				);
				
				if ( isset( $data['delivery_format'] ) ) {
					$format_label = ( $data['delivery_format'] === 'digital' ) ? __( 'Digital (Descarga PDF)', 'cartas-personalizadas' ) : __( 'Físico (Envío Postal)', 'cartas-personalizadas' );
					$item_data[] = array(
						'key'   => __( 'Formato', 'cartas-personalizadas' ),
						'value' => $format_label,
					);
				}
			}
		}
		return $item_data;
	}

	public function add_order_line_item_data( $item, $cart_item_key, $values, $order ) {
		if ( isset( $values['cp_personalizations'] ) ) {
			$item->add_meta_data( '_cp_personalizations', $values['cp_personalizations'] );
		}
	}

	public function ajax_generate_preview() {
		check_ajax_referer( 'cp_preview_nonce', 'nonce' );

		$blocks_data = isset( $_POST['blocks'] ) ? $_POST['blocks'] : array();
		$variables_data = isset( $_POST['variables'] ) ? $_POST['variables'] : array();
		$template_id = isset( $_POST['template_id'] ) ? intval( $_POST['template_id'] ) : 0;
		$product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
		$model_id = isset( $_POST['model_id'] ) ? intval( $_POST['model_id'] ) : 0;

		// Sanitize AJAX payload
		$sanitized_blocks = array();
		if ( is_array( $blocks_data ) ) {
			foreach ( $blocks_data as $b_idx => $b_val ) {
				if ( is_array( $b_val ) ) {
					$s_vars = array();
					foreach ( $b_val as $tag => $val ) {
						$s_vars[ sanitize_text_field( $tag ) ] = sanitize_textarea_field( wp_unslash( $val ) );
					}
					$sanitized_blocks[ $b_idx ] = $s_vars;
				} else {
					$sanitized_blocks[ $b_idx ] = sanitize_textarea_field( wp_unslash( $b_val ) );
				}
			}
		}

		$sanitized_variables = array();
		if ( is_array( $variables_data ) ) {
			foreach ( $variables_data as $tag => $val ) {
				$sanitized_variables[ sanitize_text_field( $tag ) ] = sanitize_textarea_field( wp_unslash( $val ) );
			}
		}

		// Use CP_PDF for preview (will be rendered via PDF.js)
		error_log( 'CP_Frontend: Generating preview for product ' . $product_id . ' with template ' . $template_id );
		
		try {
			$pdf = new CP_PDF();
			$preview_url = $pdf->generate_preview( array( 
				'blocks' => $sanitized_blocks,
				'variables' => $sanitized_variables,
				'template_id' => $template_id,
				'model_id' => $model_id
			) );
			error_log( 'CP_Frontend: Preview generated successfully' );
		} catch ( Exception $e ) {
			error_log( 'CP_Frontend: Error generating preview: ' . $e->getMessage() );
			wp_send_json_error( array( 'message' => 'Error: ' . $e->getMessage() ) );
			return;
		}

		if ( $preview_url ) {
			wp_send_json_success( array( 'preview_url' => $preview_url, 'type' => 'pdf' ) );
		} else {
			wp_send_json_error( array( 'message' => 'Error generating preview' ) );
		}
	}
}
