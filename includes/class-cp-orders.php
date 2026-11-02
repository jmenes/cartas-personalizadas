<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Orders {

	public function __construct() {
		// Generate PDF when order is paid or processing
		add_action( 'woocommerce_payment_complete', array( $this, 'generate_order_pdfs' ) );
		add_action( 'woocommerce_order_status_processing', array( $this, 'generate_order_pdfs' ) );

		// Add download link to Admin Order Items
		add_action( 'woocommerce_after_order_itemmeta', array( $this, 'add_admin_download_link' ), 10, 3 );

		// Add download link to Customer Order Items on My Account and Thank You page
		add_action( 'woocommerce_order_item_meta_end', array( $this, 'add_customer_download_links' ), 10, 3 );

		// Attach PDF to Customer Completed Order Email
		add_filter( 'woocommerce_email_attachments', array( $this, 'attach_pdf_to_customer_email' ), 10, 3 );

		// Hide internal PDF meta from order item meta display
		add_filter( 'woocommerce_order_item_get_formatted_meta_data', array( $this, 'hide_internal_order_item_meta' ), 10, 2 );

		// Admin action for manual PDF regeneration
		add_action( 'admin_post_cp_regenerate_order_pdfs', array( $this, 'handle_manual_pdf_regeneration' ) );
		add_action( 'admin_notices', array( $this, 'display_admin_regeneration_notice' ) );

		// WooCommerce Order Action dropdown
		add_filter( 'woocommerce_order_actions', array( $this, 'add_order_actions' ) );
		add_action( 'woocommerce_order_action_cp_regenerate_order_pdfs', array( $this, 'process_order_action_regeneration' ) );

		// Secure PDF download endpoints with access control
		add_action( 'admin_post_cp_download_pdf', array( $this, 'handle_secure_pdf_download' ) );
		add_action( 'admin_post_nopriv_cp_download_pdf', array( $this, 'handle_secure_pdf_download' ) );
		add_action( 'init', array( $this, 'handle_secure_pdf_download' ) );
	}

	public function generate_order_pdfs( $order_id, $force = false ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$pdf_generator = new CP_PDF();

		foreach ( $order->get_items() as $item_id => $item ) {
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}

			$is_letter = $product->get_meta( '_cp_is_letter' );
			if ( 'yes' !== $is_letter ) {
				continue;
			}

			// Check if PDF already generated and not forcing regeneration
			$existing_pdf = $item->get_meta( '_cp_pdf_url' );
			if ( ! empty( $existing_pdf ) && ! $force ) {
				continue;
			}

			// Get custom data
			$personalizations = $item->get_meta( '_cp_personalizations' );

			if ( empty( $personalizations ) || ! is_array( $personalizations ) ) {
				// Fallback to legacy single item
				$name = $item->get_meta( '_cp_name' );
				$content = $item->get_meta( '_cp_content' );
				if ( empty( $name ) || empty( $content ) ) {
					continue;
				}
				$personalizations = array(
					array(
						'name' => $name,
						'content' => $content,
						'template_id' => $product->get_meta( '_cp_template' ),
						'template_name' => 'Carta'
					)
				);
			}

			$generated_urls = array();
			$generated_paths = array();

			foreach ( $personalizations as $index => $data ) {
				$doc_title = isset( $data['template_name'] ) && ! empty( $data['template_name'] ) ? $data['template_name'] : sprintf( __( 'Documento %d', 'cartas-personalizadas' ), $index + 1 );
				
				$delivery_format = ! empty( $data['delivery_format'] ) ? $data['delivery_format'] : $product->get_meta( '_cp_delivery_format' );
				if ( empty( $delivery_format ) ) {
					$delivery_format = 'physical';
				}

				if ( 'digital' === $delivery_format ) {
					// Digital delivery: Single PDF with background, without watermark
					$data_digital = $data;
					$data_digital['include_background'] = true;
					$result = $pdf_generator->generate_final( $order_id, $data_digital, $index, 'digital' );

					if ( $result && isset( $result['url'] ) ) {
						$generated_urls[] = array(
							'name'   => $doc_title,
							'url'    => $result['url'],
							'type'   => 'digital',
							'format' => 'digital',
						);
						$generated_paths[] = $result['path'];
					}
				} else {
					// Physical delivery: Generate BOTH versions
					// 1. Text only (for printing on pre-printed stationery)
					$data_text = $data;
					$data_text['include_background'] = false;
					$result_text = $pdf_generator->generate_final( $order_id, $data_text, $index, 'texto' );

					if ( $result_text && isset( $result_text['url'] ) ) {
						$generated_urls[] = array(
							'name'   => $doc_title . ' (' . __( 'solo texto', 'cartas-personalizadas' ) . ')',
							'url'    => $result_text['url'],
							'type'   => 'text',
							'format' => 'physical',
						);
						$generated_paths[] = $result_text['path'];
					}

					// 2. Complete with background (without watermark)
					$data_full = $data;
					$data_full['include_background'] = true;
					$result_full = $pdf_generator->generate_final( $order_id, $data_full, $index, 'completo' );

					if ( $result_full && isset( $result_full['url'] ) ) {
						$generated_urls[] = array(
							'name'   => $doc_title . ' (' . __( 'completo con fondo', 'cartas-personalizadas' ) . ')',
							'url'    => $result_full['url'],
							'type'   => 'full',
							'format' => 'physical',
						);
						$generated_paths[] = $result_full['path'];
					}
				}
			}

			if ( ! empty( $generated_urls ) ) {
				$item->update_meta_data( '_cp_pdf_urls', $generated_urls );
				$item->update_meta_data( '_cp_pdf_paths', $generated_paths );
				// Also save first one to legacy meta for backwards compatibility
				$item->update_meta_data( '_cp_pdf_url', $generated_urls[0]['url'] );
				$item->save();
			}
		}
	}

	public function add_admin_download_link( $item_id, $item, $product ) {
		if ( ! is_admin() ) {
			return;
		}

		// Ensure we only process product line items (ignore shipping, fees, etc.)
		if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
			return;
		}

		$personalizations = $item->get_meta( '_cp_personalizations' );
		$is_letter = ( $product && is_a( $product, 'WC_Product' ) ) ? $product->get_meta( '_cp_is_letter' ) : '';

		// If this product is not a custom letter, do not output anything
		if ( empty( $personalizations ) && empty( $item->get_meta( '_cp_name' ) ) && 'yes' !== $is_letter ) {
			return;
		}

		$order_id = $item->get_order_id();
		$pdf_urls = $item->get_meta( '_cp_pdf_urls' );

		// 1. Botones de descarga de PDF si existen
		if ( ! empty( $pdf_urls ) && is_array( $pdf_urls ) ) {
			echo '<div class="cp-admin-pdf-downloads" style="margin-top: 8px;">';
			foreach ( $pdf_urls as $doc_idx => $doc ) {
				$doc_type = isset( $doc['type'] ) ? $doc['type'] : '';
				$btn_class = ( 'full' === $doc_type ) ? 'button button-small button-secondary' : 'button button-small button-primary';

				if ( 'full' === $doc_type ) {
					$icon_html = '<span class="dashicons dashicons-format-image" style="vertical-align: middle; font-size: 16px; margin-right: 3px;"></span>';
				} else {
					$icon_html = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" style="vertical-align: text-bottom; margin-right: 4px; display: inline-block; fill: currentColor;" aria-hidden="true" focusable="false"><path d="M6 2c-.55 0-1 .45-1 1v14c0 .55.45 1 1 1h8c.55 0 1-.45 1-1V7l-5-5H6zm7 6h-3V3.5L13.5 8H13z"/></svg>';
				}

				$doc_name = isset( $doc['name'] ) ? $doc['name'] : '';
				// Ensure sentence case on document suffixes
				$doc_name = str_ireplace( array( '(Solo Texto)', '(Solo texto)' ), '(' . __( 'solo texto', 'cartas-personalizadas' ) . ')', $doc_name );
				$doc_name = str_ireplace( array( '(Completo con Fondo)', '(Completo con fondo)' ), '(' . __( 'completo con fondo', 'cartas-personalizadas' ) . ')', $doc_name );

				$download_url = $this->get_download_url( $order_id, $item_id, $doc_idx );

				echo '<a href="' . esc_url( $download_url ) . '" class="' . esc_attr( $btn_class ) . '" target="_blank" style="margin-right: 5px; margin-bottom: 5px;">' . $icon_html . sprintf( __( 'Descargar %s', 'cartas-personalizadas' ), esc_html( $doc_name ) ) . '</a>';
			}
			echo '</div>';
		} else {
			$pdf_url = $item->get_meta( '_cp_pdf_url' );
			if ( $pdf_url ) {
				$download_url = $this->get_download_url( $order_id, $item_id, 0 );
				$icon_html = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 20 20" style="vertical-align: text-bottom; margin-right: 4px; display: inline-block; fill: currentColor;" aria-hidden="true" focusable="false"><path d="M6 2c-.55 0-1 .45-1 1v14c0 .55.45 1 1 1h8c.55 0 1-.45 1-1V7l-5-5H6zm7 6h-3V3.5L13.5 8H13z"/></svg>';
				echo '<p style="margin-top: 8px;"><a href="' . esc_url( $download_url ) . '" class="button button-small button-primary" target="_blank">' . $icon_html . __( 'Descargar carta', 'cartas-personalizadas' ) . '</a></p>';
			} else {
				echo '<div style="margin-top: 8px; color: #b32d2e; font-weight: 500;"><span class="dashicons dashicons-warning" style="vertical-align: middle;"></span> ' . __( 'Los PDFs aún no se han generado o hubo un problema al crearlos.', 'cartas-personalizadas' ) . '</div>';
			}
		}

		// 2. Botón para Regenerar / Forzar creación de PDFs usando los datos almacenados
		if ( $order_id && ( ! empty( $personalizations ) || $item->get_meta( '_cp_name' ) ) ) {
			$regen_url = wp_nonce_url( admin_url( 'admin-post.php?action=cp_regenerate_order_pdfs&order_id=' . $order_id ), 'cp_regenerate_order_pdfs_' . $order_id );
			echo '<div style="margin-top: 6px; margin-bottom: 6px;">';
			echo '<a href="' . esc_url( $regen_url ) . '" class="button button-small button-secondary" onclick="return confirm(\'' . esc_js( __( '¿Deseas volver a generar los archivos PDF de este pedido?', 'cartas-personalizadas' ) ) . '\');"><span class="dashicons dashicons-update" style="vertical-align: middle; font-size: 15px;"></span> ' . __( 'Regenerar PDFs con los datos guardados', 'cartas-personalizadas' ) . '</a>';
			echo '</div>';
		}

		// 3. Vista de datos recuperables de personalización
		if ( ! empty( $personalizations ) && is_array( $personalizations ) ) {
			echo '<details class="cp-order-personalization-details" style="margin-top: 8px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 8px 12px; max-width: 650px;">';
			echo '<summary style="font-weight: 600; cursor: pointer; color: #2271b1; outline: none; user-select: none;"><span class="dashicons dashicons-clipboard" style="vertical-align: middle; font-size: 16px;"></span> ' . __( 'Ver datos de personalización del cliente', 'cartas-personalizadas' ) . '</summary>';
			echo '<div style="margin-top: 10px; font-size: 0.9em; line-height: 1.5;">';

			foreach ( $personalizations as $p_idx => $p_data ) {
				$t_title = isset( $p_data['template_name'] ) ? $p_data['template_name'] : sprintf( __( 'Documento %d', 'cartas-personalizadas' ), $p_idx + 1 );
				$m_title = ! empty( $p_data['model_name'] ) ? $p_data['model_name'] : ( isset( $p_data['model_id'] ) ? sprintf( __( 'Modelo %d', 'cartas-personalizadas' ), $p_data['model_id'] + 1 ) : '' );
				$d_format = isset( $p_data['delivery_format'] ) && $p_data['delivery_format'] === 'digital' ? __( 'Digital', 'cartas-personalizadas' ) : __( 'Físico', 'cartas-personalizadas' );

				echo '<div style="background: #f9f9f9; border: 1px solid #e2e4e7; border-radius: 3px; padding: 8px 10px; margin-bottom: 8px;">';
				echo '<div style="font-weight: bold; color: #1d2327; margin-bottom: 4px;">' . esc_html( $t_title );
				if ( $m_title ) {
					echo ' <span style="font-weight: normal; color: #646970;">(' . esc_html( $m_title ) . ')</span>';
				}
				echo ' <span style="float: right; font-size: 0.85em; background: #e5e5e5; padding: 1px 6px; border-radius: 2px;">' . esc_html( $d_format ) . '</span>';
				echo '</div>';

				// Variables / Marcadores
				if ( ! empty( $p_data['variables'] ) && is_array( $p_data['variables'] ) ) {
					echo '<div style="margin-top: 6px;"><strong>' . __( 'Variables / Marcadores:', 'cartas-personalizadas' ) . '</strong>';
					echo '<ul style="margin: 4px 0 6px 15px; list-style: disc;">';
					foreach ( $p_data['variables'] as $var_tag => $var_val ) {
						echo '<li><code style="background: #eee; padding: 1px 4px; border-radius: 2px;">' . esc_html( $var_tag ) . '</code>: <strong>' . esc_html( $var_val ) . '</strong></li>';
					}
					echo '</ul></div>';
				}

				// Bloques de texto libre
				if ( ! empty( $p_data['blocks'] ) && is_array( $p_data['blocks'] ) ) {
					echo '<div style="margin-top: 6px;"><strong>' . __( 'Bloques de texto introducidos:', 'cartas-personalizadas' ) . '</strong>';
					echo '<ul style="margin: 4px 0 0 15px; list-style: disc;">';
					foreach ( $p_data['blocks'] as $b_key => $b_val ) {
						if ( is_string( $b_val ) && trim( $b_val ) !== '' ) {
							echo '<li><em>' . sprintf( __( 'Bloque %s:', 'cartas-personalizadas' ), esc_html( $b_key ) ) . '</em> ' . nl2br( esc_html( $b_val ) ) . '</li>';
						} elseif ( is_array( $b_val ) ) {
							foreach ( $b_val as $sub_k => $sub_v ) {
								echo '<li><code>' . esc_html( $sub_k ) . '</code>: ' . nl2br( esc_html( $sub_v ) ) . '</li>';
							}
						}
					}
					echo '</ul></div>';
				}

				echo '</div>';
			}

			echo '</div>';
			echo '</details>';
		} elseif ( $item->get_meta( '_cp_name' ) ) {
			// Legacy display
			echo '<details class="cp-order-personalization-details" style="margin-top: 8px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; padding: 8px 12px; max-width: 650px;">';
			echo '<summary style="font-weight: 600; cursor: pointer; color: #2271b1;"><span class="dashicons dashicons-clipboard" style="vertical-align: middle;"></span> ' . __( 'Ver datos de personalización del cliente', 'cartas-personalizadas' ) . '</summary>';
			echo '<div style="margin-top: 8px; font-size: 0.9em;">';
			echo '<p><strong>' . __( 'Nombre:', 'cartas-personalizadas' ) . '</strong> ' . esc_html( $item->get_meta( '_cp_name' ) ) . '</p>';
			echo '<p><strong>' . __( 'Contenido:', 'cartas-personalizadas' ) . '</strong> ' . nl2br( esc_html( $item->get_meta( '_cp_content' ) ) ) . '</p>';
			echo '</div>';
			echo '</details>';
		}
	}

	public function handle_manual_pdf_regeneration() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'Permisos insuficientes.', 'cartas-personalizadas' ) );
		}

		$order_id = isset( $_GET['order_id'] ) ? intval( $_GET['order_id'] ) : 0;
		check_admin_referer( 'cp_regenerate_order_pdfs_' . $order_id );

		if ( $order_id ) {
			$this->generate_order_pdfs( $order_id, true );
		}

		wp_safe_redirect( add_query_arg( array(
			'post'           => $order_id,
			'action'         => 'edit',
			'cp_regenerated' => '1',
		), admin_url( 'post.php' ) ) );
		exit;
	}

	public function add_order_actions( $actions ) {
		$actions['cp_regenerate_order_pdfs'] = __( 'Regenerar PDFs de Carta Personalizada', 'cartas-personalizadas' );
		return $actions;
	}

	public function process_order_action_regeneration( $order ) {
		if ( $order && is_a( $order, 'WC_Order' ) ) {
			$this->generate_order_pdfs( $order->get_id(), true );
			$order->add_order_note( __( 'PDFs de carta personalizada regenerados manualmente por el administrador.', 'cartas-personalizadas' ) );
		}
	}

	public function display_admin_regeneration_notice() {
		if ( isset( $_GET['cp_regenerated'] ) && '1' === $_GET['cp_regenerated'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . __( 'Los archivos PDF del pedido se han regenerado correctamente a partir de los datos guardados.', 'cartas-personalizadas' ) . '</p></div>';
		}
	}

	public function add_customer_download_links( $item_id, $item, $product ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}

		$personalizations = $item->get_meta( '_cp_personalizations' );
		$is_digital = false;
		if ( is_array( $personalizations ) ) {
			foreach ( $personalizations as $data ) {
				if ( isset( $data['delivery_format'] ) && $data['delivery_format'] === 'digital' ) {
					$is_digital = true;
					break;
				}
			}
		}

		// Fallback check on product meta
		if ( ! $is_digital && $product ) {
			$delivery_format = $product->get_meta( '_cp_delivery_format' );
			if ( 'digital' === $delivery_format ) {
				$is_digital = true;
			}
		}

		if ( ! $is_digital ) {
			return;
		}

		$order_id = $item->get_order_id();
		$order = wc_get_order( $order_id );
		$order_key = $order ? $order->get_order_key() : '';

		$pdf_urls = $item->get_meta( '_cp_pdf_urls' );
		
		if ( ! empty( $pdf_urls ) && is_array( $pdf_urls ) ) {
			echo '<div class="cp-customer-downloads" style="margin-top: 10px;">';
			foreach ( $pdf_urls as $doc_idx => $doc ) {
				// Safety: Never show physical format files to customer
				if ( isset( $doc['format'] ) && 'physical' === $doc['format'] ) {
					continue;
				}
				$doc_name = isset( $doc['name'] ) ? $doc['name'] : '';
				$doc_name = str_ireplace( array( '(Solo Texto)', '(Solo texto)' ), '(' . __( 'solo texto', 'cartas-personalizadas' ) . ')', $doc_name );
				$doc_name = str_ireplace( array( '(Completo con Fondo)', '(Completo con fondo)' ), '(' . __( 'completo con fondo', 'cartas-personalizadas' ) . ')', $doc_name );

				$download_url = $this->get_download_url( $order_id, $item_id, $doc_idx, $order_key );

				echo '<p><a href="' . esc_url( $download_url ) . '" class="button alt button-small cp-download-btn" target="_blank" style="display: inline-block; background-color: #2271b1; color: #fff; padding: 6px 12px; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 0.9em; margin-bottom: 5px;">' . sprintf( __( 'Descargar %s', 'cartas-personalizadas' ), esc_html( $doc_name ) ) . '</a></p>';
			}
			echo '</div>';
		} else {
			// Legacy fallback
			$pdf_url = $item->get_meta( '_cp_pdf_url' );
			if ( $pdf_url ) {
				$download_url = $this->get_download_url( $order_id, $item_id, 0, $order_key );
				echo '<div class="cp-customer-downloads" style="margin-top: 10px;">';
				echo '<p><a href="' . esc_url( $download_url ) . '" class="button alt button-small cp-download-btn" target="_blank" style="display: inline-block; background-color: #2271b1; color: #fff; padding: 6px 12px; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 0.9em; margin-bottom: 5px;">' . __( 'Descargar carta', 'cartas-personalizadas' ) . '</a></p>';
				echo '</div>';
			}
		}
	}

	public function attach_pdf_to_customer_email( $attachments, $email_id, $order ) {
		if ( 'customer_completed_order' !== $email_id ) {
			return $attachments;
		}

		if ( ! is_a( $order, 'WC_Order' ) ) {
			return $attachments;
		}

		foreach ( $order->get_items() as $item_id => $item ) {
			$product = $item->get_product();
			
			$personalizations = $item->get_meta( '_cp_personalizations' );
			$is_digital = false;
			if ( is_array( $personalizations ) ) {
				foreach ( $personalizations as $data ) {
					if ( isset( $data['delivery_format'] ) && $data['delivery_format'] === 'digital' ) {
						$is_digital = true;
						break;
					}
				}
			}

			// Fallback check on product meta
			if ( ! $is_digital && $product ) {
				$delivery_format = $product->get_meta( '_cp_delivery_format' );
				if ( 'digital' === $delivery_format ) {
					$is_digital = true;
				}
			}

			if ( $is_digital ) {
				$pdf_urls = $item->get_meta( '_cp_pdf_urls' );
				$pdf_paths = $item->get_meta( '_cp_pdf_paths' );
				if ( ! empty( $pdf_paths ) && is_array( $pdf_paths ) ) {
					foreach ( $pdf_paths as $p_idx => $path ) {
						// Safety: Never attach physical format files to customer email
						if ( isset( $pdf_urls[$p_idx]['format'] ) && 'physical' === $pdf_urls[$p_idx]['format'] ) {
							continue;
						}
						if ( file_exists( $path ) ) {
							$attachments[] = $path;
						}
					}
				}
			}
		}

		return $attachments;
	}

	/**
	 * Hide internal PDF metadata from order items display (emails, checkout, thank you, admin order view)
	 */
	public function hide_internal_order_item_meta( $formatted_meta, $item ) {
		$hidden_keys = array(
			'_cp_pdf_url',
			'_cp_pdf_urls',
			'_cp_pdf_paths',
			'_cp_personalizations',
			'_cp_name',
			'_cp_content'
		);

		foreach ( $formatted_meta as $key => $meta ) {
			if ( in_array( $meta->key, $hidden_keys, true ) ) {
				unset( $formatted_meta[ $key ] );
			}
		}

		return $formatted_meta;
	}

	/**
	 * Build secure download URL for an order item PDF
	 */
	public function get_download_url( $order_id, $item_id, $doc_idx, $order_key = '' ) {
		$args = array(
			'action'   => 'cp_download_pdf',
			'order_id' => intval( $order_id ),
			'item_id'  => intval( $item_id ),
			'doc_idx'  => intval( $doc_idx ),
		);

		if ( ! empty( $order_key ) ) {
			$args['order_key'] = sanitize_text_field( $order_key );
		}

		if ( is_admin() || current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' ) || current_user_can( 'administrator' ) ) {
			$args['_wpnonce'] = wp_create_nonce( 'cp_download_' . $order_id . '_' . $doc_idx );
		}

		return add_query_arg( $args, admin_url( 'admin-post.php' ) );
	}

	/**
	 * Secure PDF download handler with authentication and permission checks
	 */
	public function handle_secure_pdf_download() {
		$is_cp_action = ( isset( $_GET['action'] ) && 'cp_download_pdf' === $_GET['action'] )
			|| ( isset( $_GET['cp_action'] ) && 'download_pdf' === $_GET['cp_action'] );

		if ( ! $is_cp_action ) {
			return;
		}

		$order_id = isset( $_GET['order_id'] ) ? intval( $_GET['order_id'] ) : 0;
		$item_id  = isset( $_GET['item_id'] ) ? intval( $_GET['item_id'] ) : 0;
		$doc_idx  = isset( $_GET['doc_idx'] ) ? intval( $_GET['doc_idx'] ) : 0;

		if ( ! $order_id ) {
			wp_die( esc_html__( 'Pedido no especificado.', 'cartas-personalizadas' ), esc_html__( 'Error de acceso', 'cartas-personalizadas' ), array( 'response' => 400 ) );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_die( esc_html__( 'Pedido no encontrado.', 'cartas-personalizadas' ), esc_html__( 'Error de acceso', 'cartas-personalizadas' ), array( 'response' => 404 ) );
		}

		$item = null;
		if ( $item_id ) {
			$item = $order->get_item( $item_id );
		}

		// Fallback: If item was not found by ID, locate the letter item in the order
		if ( ! $item || ! is_a( $item, 'WC_Order_Item_Product' ) ) {
			foreach ( $order->get_items() as $order_item ) {
				if ( is_a( $order_item, 'WC_Order_Item_Product' ) ) {
					$product = $order_item->get_product();
					if ( ( $product && 'yes' === $product->get_meta( '_cp_is_letter' ) ) || $order_item->get_meta( '_cp_pdf_urls' ) || $order_item->get_meta( '_cp_pdf_url' ) ) {
						$item = $order_item;
						break;
					}
				}
			}
		}

		if ( ! $item ) {
			wp_die( esc_html__( 'Elemento del pedido no encontrado.', 'cartas-personalizadas' ), esc_html__( 'Error de acceso', 'cartas-personalizadas' ), array( 'response' => 404 ) );
		}

		$pdf_paths = $item->get_meta( '_cp_pdf_paths' );
		$pdf_urls  = $item->get_meta( '_cp_pdf_urls' );

		// Fallback for legacy orders if _cp_pdf_paths is missing or empty
		if ( empty( $pdf_paths ) || ! is_array( $pdf_paths ) ) {
			$upload_dir = wp_upload_dir();
			if ( ! empty( $pdf_urls ) && is_array( $pdf_urls ) ) {
				$pdf_paths = array();
				foreach ( $pdf_urls as $doc ) {
					if ( isset( $doc['url'] ) ) {
						$pdf_paths[] = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $doc['url'] );
					}
				}
			} elseif ( $legacy_url = $item->get_meta( '_cp_pdf_url' ) ) {
				$pdf_paths = array( str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $legacy_url ) );
			}
		}

		if ( empty( $pdf_paths ) || ! isset( $pdf_paths[ $doc_idx ] ) ) {
			if ( ! empty( $pdf_paths ) && isset( $pdf_paths[0] ) ) {
				$doc_idx = 0;
			} else {
				wp_die( esc_html__( 'El archivo PDF solicitado no está disponible.', 'cartas-personalizadas' ), esc_html__( 'Archivo no encontrado', 'cartas-personalizadas' ), array( 'response' => 404 ) );
			}
		}

		$file_path = $pdf_paths[ $doc_idx ];

		// If file path does not exist on disk, attempt resolving from url
		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			$upload_dir = wp_upload_dir();
			if ( ! empty( $pdf_urls[ $doc_idx ]['url'] ) ) {
				$alt_path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $pdf_urls[ $doc_idx ]['url'] );
				if ( file_exists( $alt_path ) ) {
					$file_path = $alt_path;
				}
			}
		}

		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			wp_die( esc_html__( 'El archivo PDF no existe en el servidor.', 'cartas-personalizadas' ), esc_html__( 'Archivo no encontrado', 'cartas-personalizadas' ), array( 'response' => 404 ) );
		}

		$real_file = realpath( $file_path );
		if ( ! $real_file || ! file_exists( $real_file ) || 'pdf' !== strtolower( pathinfo( $real_file, PATHINFO_EXTENSION ) ) ) {
			wp_die( esc_html__( 'Archivo no válido.', 'cartas-personalizadas' ), esc_html__( 'Error de acceso', 'cartas-personalizadas' ), array( 'response' => 403 ) );
		}

		// Security Authorization:
		$is_admin = false;
		if ( is_user_logged_in() ) {
			if ( current_user_can( 'manage_woocommerce' ) 
				|| current_user_can( 'manage_options' ) 
				|| current_user_can( 'edit_shop_orders' ) 
				|| current_user_can( 'edit_others_shop_orders' ) 
				|| current_user_can( 'administrator' ) 
				|| is_super_admin() ) {
				$is_admin = true;
			}
		}

		// Also check valid admin nonce
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! $is_admin && ! empty( $nonce ) ) {
			if ( is_user_logged_in() && wp_verify_nonce( $nonce, 'cp_download_' . $order_id . '_' . $doc_idx ) ) {
				$is_admin = true;
			}
		}

		$doc_meta = isset( $pdf_urls[ $doc_idx ] ) ? $pdf_urls[ $doc_idx ] : array();
		$is_physical = ( isset( $doc_meta['format'] ) && 'physical' === $doc_meta['format'] );

		// Check product format if doc_meta doesn't specify
		if ( ! $is_physical && ( $product = $item->get_product() ) ) {
			if ( 'physical' === $product->get_meta( '_cp_delivery_format' ) ) {
				$is_physical = true;
			}
		}

		if ( $is_admin ) {
			// Administrator is authorized to download any document
		} else {
			// Non-admin can NEVER download physical letters
			if ( $is_physical ) {
				wp_die( esc_html__( 'No tienes permisos.', 'cartas-personalizadas' ), esc_html__( 'Acceso denegado', 'cartas-personalizadas' ), array( 'response' => 403 ) );
			}

			// For digital letters, verify customer ownership
			$authorized = false;
			$order_key  = isset( $_GET['order_key'] ) ? sanitize_text_field( wp_unslash( $_GET['order_key'] ) ) : '';

			// 1. Check order key (guest checkout / thank you page)
			if ( ! empty( $order_key ) && hash_equals( $order->get_order_key(), $order_key ) ) {
				$authorized = true;
			}

			// 2. Check logged-in user ID
			$current_user_id = get_current_user_id();
			if ( ! $authorized && $current_user_id && $order->get_customer_id() === $current_user_id ) {
				$authorized = true;
			}

			// 3. Check customer billing email
			if ( ! $authorized && $current_user_id ) {
				$user = wp_get_current_user();
				if ( $user && strtolower( $user->user_email ) === strtolower( $order->get_billing_email() ) ) {
					$authorized = true;
				}
			}

			if ( ! $authorized ) {
				wp_die( esc_html__( 'No tienes permisos para acceder a esta descarga.', 'cartas-personalizadas' ), esc_html__( 'Acceso denegado', 'cartas-personalizadas' ), array( 'response' => 403 ) );
			}

			// Verify order is not cancelled, refunded or failed
			if ( $order->has_status( array( 'cancelled', 'refunded', 'failed' ) ) ) {
				wp_die( esc_html__( 'El pedido ha sido cancelado o reembolsado.', 'cartas-personalizadas' ), esc_html__( 'Descarga no disponible', 'cartas-personalizadas' ), array( 'response' => 403 ) );
			}
		}

		// Clear output buffers
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		$filename = basename( $real_file );
		if ( isset( $doc_meta['name'] ) && ! empty( $doc_meta['name'] ) ) {
			$clean_name = sanitize_file_name( $doc_meta['name'] );
			if ( ! empty( $clean_name ) ) {
				$filename = $clean_name . '.pdf';
			}
		}

		// Disable compression that can corrupt binary output
		if ( function_exists( 'apache_setenv' ) ) {
			@apache_setenv( 'no-gzip', '1' );
		}
		@ini_set( 'zlib.output_compression', 'Off' );

		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: inline; filename="' . esc_attr( $filename ) . '"' );
		header( 'Content-Transfer-Encoding: binary' );
		header( 'Content-Length: ' . filesize( $real_file ) );
		header( 'Cache-Control: private, max-age=0, must-revalidate' );
		header( 'Pragma: public' );

		readfile( $real_file );
		exit;
	}
}
