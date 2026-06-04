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
	}

	public function generate_order_pdfs( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Avoid regenerating if already done (check a flag or if file exists)
		// For simplicity, we'll regenerate or check if meta exists
		
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

			// Check if PDF already generated
			$existing_pdf = $item->get_meta( '_cp_pdf_url' );
			if ( ! empty( $existing_pdf ) ) {
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

		$pdf_urls = $item->get_meta( '_cp_pdf_urls' );
		
		if ( ! empty( $pdf_urls ) && is_array( $pdf_urls ) ) {
			foreach ( $pdf_urls as $doc ) {
				echo '<p><a href="' . esc_url( $doc['url'] ) . '" class="button button-small" target="_blank">' . sprintf( __( 'Descargar %s PDF', 'cartas-personalizadas' ), esc_html( $doc['name'] ) ) . '</a></p>';
			}
		} else {
			// Legacy fallback
			$pdf_url = $item->get_meta( '_cp_pdf_url' );
			if ( $pdf_url ) {
				echo '<p><a href="' . esc_url( $pdf_url ) . '" class="button button-small" target="_blank">' . __( 'Descargar Carta PDF', 'cartas-personalizadas' ) . '</a></p>';
			}
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
}
