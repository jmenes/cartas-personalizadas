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
				// Generate individual PDF for each item
				$result = $pdf_generator->generate_final( $order_id, $data, $index );

				if ( $result && isset( $result['url'] ) ) {
					$generated_urls[] = array(
						'name' => isset( $data['template_name'] ) ? $data['template_name'] : 'Documento ' . ($index + 1),
						'url'  => $result['url']
					);
					$generated_paths[] = $result['path'];
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

		$order_id = $item->get_order_id();
		$pdf_urls = $item->get_meta( '_cp_pdf_urls' );
		$personalizations = $item->get_meta( '_cp_personalizations' );

		// 1. Botones de descarga de PDF si existen
		if ( ! empty( $pdf_urls ) && is_array( $pdf_urls ) ) {
			echo '<div class="cp-admin-pdf-downloads" style="margin-top: 8px;">';
			foreach ( $pdf_urls as $doc ) {
				echo '<a href="' . esc_url( $doc['url'] ) . '" class="button button-small button-primary" target="_blank" style="margin-right: 5px; margin-bottom: 5px;"><span class="dashicons dashicons-pdf" style="vertical-align: middle; font-size: 16px;"></span> ' . sprintf( __( 'Descargar %s PDF', 'cartas-personalizadas' ), esc_html( $doc['name'] ) ) . '</a>';
			}
			echo '</div>';
		} else {
			$pdf_url = $item->get_meta( '_cp_pdf_url' );
			if ( $pdf_url ) {
				echo '<p style="margin-top: 8px;"><a href="' . esc_url( $pdf_url ) . '" class="button button-small button-primary" target="_blank"><span class="dashicons dashicons-pdf" style="vertical-align: middle; font-size: 16px;"></span> ' . __( 'Descargar Carta PDF', 'cartas-personalizadas' ) . '</a></p>';
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

		$pdf_urls = $item->get_meta( '_cp_pdf_urls' );
		
		if ( ! empty( $pdf_urls ) && is_array( $pdf_urls ) ) {
			echo '<div class="cp-customer-downloads" style="margin-top: 10px;">';
			foreach ( $pdf_urls as $doc ) {
				echo '<p><a href="' . esc_url( $doc['url'] ) . '" class="button alt button-small cp-download-btn" target="_blank" style="display: inline-block; background-color: #2271b1; color: #fff; padding: 6px 12px; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 0.9em; margin-bottom: 5px;">' . sprintf( __( 'Descargar %s PDF', 'cartas-personalizadas' ), esc_html( $doc['name'] ) ) . '</a></p>';
			}
			echo '</div>';
		} else {
			// Legacy fallback
			$pdf_url = $item->get_meta( '_cp_pdf_url' );
			if ( $pdf_url ) {
				echo '<div class="cp-customer-downloads" style="margin-top: 10px;">';
				echo '<p><a href="' . esc_url( $pdf_url ) . '" class="button alt button-small cp-download-btn" target="_blank" style="display: inline-block; background-color: #2271b1; color: #fff; padding: 6px 12px; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 0.9em; margin-bottom: 5px;">' . __( 'Descargar Carta PDF', 'cartas-personalizadas' ) . '</a></p>';
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
				$pdf_paths = $item->get_meta( '_cp_pdf_paths' );
				if ( ! empty( $pdf_paths ) && is_array( $pdf_paths ) ) {
					foreach ( $pdf_paths as $path ) {
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
}
