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
}
