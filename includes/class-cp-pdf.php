<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once CP_PLUGIN_PATH . 'lib/fpdf.php';

class CP_PDF {

	public function __construct() {
		// Ensure upload directory exists
		$upload_dir = wp_upload_dir();
		$cp_dir = $upload_dir['basedir'] . '/cp_orders';
		if ( ! file_exists( $cp_dir ) ) {
			wp_mkdir_p( $cp_dir );
		}
	}

	public function generate_preview( $data ) {
		error_log( 'CP_PDF: generate_preview called' );
		$pdf = $this->create_pdf( $data, true );
		
		// Output as Base64 for preview
		$content = $pdf->Output( 'S' );
		error_log( 'CP_PDF: PDF output generated' );
		return 'data:application/pdf;base64,' . base64_encode( $content );
	}

	public function generate_final( $order_id, $item_data, $index = 0 ) {
		$pdf = $this->create_pdf( $item_data, false );
		
		$upload_dir = wp_upload_dir();
		$suffix = $index > 0 ? '-' . $index : '';
		$filename = 'letter-' . $order_id . '-' . uniqid() . $suffix . '.pdf';
		$path = $upload_dir['basedir'] . '/cp_orders/' . $filename;
		$url = $upload_dir['baseurl'] . '/cp_orders/' . $filename;
		
		$pdf->Output( 'F', $path );
		
		return array(
			'path' => $path,
			'url'  => $url
		);
	}

	private function create_pdf( $data, $is_preview = false ) {
		// Get Template ID to fetch settings before instantiating PDF
		$template_id = isset( $data['template_id'] ) ? $data['template_id'] : 0;
		$orientation = 'P';
		$size = 'A4';
		
		if ( $template_id ) {
			$orientation_meta = get_post_meta( $template_id, '_cp_page_orientation', true );
			if ( $orientation_meta ) $orientation = $orientation_meta;
			
			$size_meta = get_post_meta( $template_id, '_cp_page_size', true );
			if ( $size_meta ) $size = $size_meta;
		}

		$pdf = new FPDF_Rotated( $orientation, 'mm', $size );
		$pdf->AddPage();
		
		// Add Custom Fonts
		$pdf->AddFont( 'LibreBaskerville', '', 'libre-baskerville-v24-latin_latin-ext-regular.php' );
		$pdf->AddFont( 'LibreBaskerville', 'I', 'libre-baskerville-v24-latin_latin-ext-italic.php' );
		$pdf->AddFont( 'LibreBaskerville', 'B', 'libre-baskerville-v24-latin_latin-ext-700.php' );
		// FPDF requires 'BI' (Bold-Italic) if it tries to use it. Falling back to Bold.
		$pdf->AddFont( 'LibreBaskerville', 'BI', 'libre-baskerville-v24-latin_latin-ext-700.php' );
		
		// Map size to mm dimensions for background scaling
		$dim_w = 210;
		$dim_h = 297;
		if ( $size === 'A5' ) {
			$dim_w = 148;
			$dim_h = 210;
		} elseif ( $size === 'Letter' ) {
			$dim_w = 216;
			$dim_h = 279;
		}
		
		// Swap dimensions if Landscape
		if ( $orientation === 'L' ) {
			$temp = $dim_w;
			$dim_w = $dim_h;
			$dim_h = $temp;
		}
		
		// Prepare Blocks Data
		$blocks_config = array();
		
		if ( $template_id ) {
			$upload_dir = wp_upload_dir();

			// Background Image: Only loaded for preview or for digital delivery
			$delivery_format = isset( $data['delivery_format'] ) ? $data['delivery_format'] : 'physical';
			$background_image = get_post_meta( $template_id, '_cp_background_image', true );
			if ( $background_image && ( $is_preview || $delivery_format === 'digital' ) ) {
				$local_bg = '';
				
				// Try to resolve the local system path if it is a local image under wp-content
				$wp_content_pos = strpos( $background_image, '/wp-content/' );
				if ( $wp_content_pos !== false ) {
					$relative_path = substr( $background_image, $wp_content_pos );
					if ( defined( 'WP_CONTENT_DIR' ) ) {
						$local_bg = WP_CONTENT_DIR . substr( $relative_path, 11 ); // remove '/wp-content' (11 chars)
					} else {
						$local_bg = ABSPATH . ltrim( $relative_path, '/' );
					}
				}
				
				if ( empty( $local_bg ) || ! file_exists( $local_bg ) ) {
					$local_bg = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $background_image );
				}
				
				if ( ! empty( $local_bg ) && file_exists( $local_bg ) ) {
					$pdf->Image( $local_bg, 0, 0, $dim_w, $dim_h );
				} elseif ( ini_get('allow_url_fopen') ) {
					$pdf->Image( $background_image, 0, 0, $dim_w, $dim_h );
				}
			}

			// Models Configuration
			$models_data = get_post_meta( $template_id, '_cp_models', true );
			if ( ! is_array( $models_data ) ) $models_data = json_decode( $models_data, true ) ?: array();
			
			$model_id = isset( $data['model_id'] ) ? intval( $data['model_id'] ) : 0;
			
			// Watermark (painted between background and text)
			if ( $is_preview ) {
				$wm_color = isset( $models_data[$model_id]['watermark_color'] ) ? $models_data[$model_id]['watermark_color'] : '#d7d7d7';
				$this->add_watermark( $pdf, $dim_w, $dim_h, $wm_color );
			}
			
			// Layout Config
			$sep_meta = get_post_meta( $template_id, '_cp_element_separation', true );
			$separation = ( $sep_meta !== '' ) ? floatval( $sep_meta ) : 10; // Default 10mm
			
			$margin_left = get_post_meta( $template_id, '_cp_margin_left', true );
			$margin_left = ( $margin_left !== '' ) ? floatval( $margin_left ) : 20;

			$margin_right = get_post_meta( $template_id, '_cp_margin_right', true );
			$margin_right = ( $margin_right !== '' ) ? floatval( $margin_right ) : 20;
			
			$margin_top = get_post_meta( $template_id, '_cp_margin_top', true );
			$margin_top = ( $margin_top !== '' ) ? floatval( $margin_top ) : 40;
			
			if ( isset( $models_data[$model_id]['blocks'] ) ) {
				$blocks_config = $models_data[$model_id]['blocks'];
			} else {
				// Fallback to legacy
				$blocks_json = get_post_meta( $template_id, '_cp_template_blocks', true );
				if ( ! is_array( $blocks_json ) && ! empty( $blocks_json ) ) {
					$blocks_config = json_decode( $blocks_json, true );
				} elseif ( is_array( $blocks_json ) ) {
					$blocks_config = $blocks_json;
				}
			}

			// Get model specific typography settings
			$font_size = isset( $models_data[$model_id]['font_size'] ) && is_numeric( $models_data[$model_id]['font_size'] ) ? floatval( $models_data[$model_id]['font_size'] ) : 12;
			$line_height = isset( $models_data[$model_id]['line_height'] ) && is_numeric( $models_data[$model_id]['line_height'] ) ? floatval( $models_data[$model_id]['line_height'] ) : 8;
			$text_align = isset( $models_data[$model_id]['text_align'] ) ? $models_data[$model_id]['text_align'] : 'L';

		} else {
			$separation = 10;
			$margin_left = 20;
			$margin_right = 20;
			$margin_top = 40;
			$font_size = 12;
			$line_height = 8;
			$text_align = 'L';
		}

		$current_x = $margin_left;
		$current_y = $margin_top;
		$content_width = $dim_w - $margin_left - $margin_right;

		$pdf->SetFont( 'LibreBaskerville', '', $font_size );
		$pdf->SetXY( $current_x, $current_y );

		$user_blocks = isset( $data['blocks'] ) && is_array( $data['blocks'] ) ? $data['blocks'] : array();

		if ( ! empty( $blocks_config ) && is_array( $blocks_config ) ) {
			foreach ( $blocks_config as $b_idx => $block ) {
				$type = isset( $block['type'] ) ? $block['type'] : 'fixed';
				$text_to_print = '';
				
				if ( $type === 'input' ) {
					$text_to_print = isset( $user_blocks[$b_idx] ) && is_string( $user_blocks[$b_idx] ) ? $user_blocks[$b_idx] : '';
				} elseif ( $type === 'fixed' ) {
					$base_text = isset( $block['base_text'] ) ? $block['base_text'] : '';
					$text_to_print = $base_text;
					
					// Replace variables
					$user_vars = isset( $data['variables'] ) && is_array( $data['variables'] ) ? $data['variables'] : array();
					$variables_config = isset( $models_data[$model_id]['variables'] ) && is_array( $models_data[$model_id]['variables'] ) ? $models_data[$model_id]['variables'] : array();
					
					// Fallback to block variables if model variables are empty
					if ( empty( $variables_config ) && isset( $block['variables'] ) && is_array( $block['variables'] ) ) {
						$variables_config = $block['variables'];
						if ( isset( $user_blocks[$b_idx] ) && is_array( $user_blocks[$b_idx] ) ) {
							$user_vars = array_merge( $user_vars, $user_blocks[$b_idx] );
						}
					}
					
					foreach ( $variables_config as $var ) {
						$tag = isset( $var['tag'] ) ? $var['tag'] : '';
						if ( $tag ) {
							$val = isset( $user_vars[$tag] ) ? $user_vars[$tag] : '';
							$text_to_print = str_replace( $tag, $val, $text_to_print );
						}
					}
				}
				if ( ! empty( trim( $text_to_print ) ) ) {
					$pos_type = isset( $block['position_type'] ) ? $block['position_type'] : 'sequential';
					$w_val = isset( $block['width'] ) && is_numeric( $block['width'] ) && $block['width'] > 0 ? floatval( $block['width'] ) : $content_width;
					
					if ( $pos_type === 'absolute' ) {
						$p_x = isset( $block['pos_x'] ) && is_numeric( $block['pos_x'] ) ? floatval( $block['pos_x'] ) : $current_x;
						$p_y = isset( $block['pos_y'] ) && is_numeric( $block['pos_y'] ) ? floatval( $block['pos_y'] ) : $current_y;
						$pdf->SetXY( $p_x, $p_y );
						$pdf->MultiCell( $w_val, $line_height, utf8_decode( $text_to_print ), 0, $text_align );
						// When absolute positioning, we might optionally not advance $current_y
						// but usually it's safer to not advance it or advance it relatively.
						if ( $p_y >= $current_y ) {
							$current_y = $pdf->GetY() + $separation; 
						}
						$current_x = $p_x;
					} else {
						$pdf->SetXY( $current_x, $current_y );
						$pdf->MultiCell( $w_val, $line_height, utf8_decode( $text_to_print ), 0, $text_align );
						$current_y = $pdf->GetY() + $separation; // Update for next blocks
					}
				}
			}
		} else {
			// Legacy fallback for old orders
			$name = isset( $data['name'] ) ? $data['name'] : 'Destinatario';
			
			// Greeting
			$greeting_tpl = get_post_meta( $template_id, '_cp_greeting_template', true );
			if ( !$greeting_tpl ) $greeting_tpl = 'Querido/a {name},';
			$greeting_text = str_replace( '{name}', $name, $greeting_tpl );
			$pdf->SetXY( $margin_left, $current_y );
			$pdf->MultiCell( $content_width, 10, utf8_decode( $greeting_text ) );
			$current_y = $pdf->GetY() + $separation;
			
			// Body
			$content = isset( $data['content'] ) ? $data['content'] : '';
			$pdf->SetXY( $margin_left, $current_y );
			$pdf->MultiCell( $content_width, $line_height, utf8_decode( $content ) );
			$current_y = $pdf->GetY() + $separation;
			
			// Closing
			$closing_tpl = get_post_meta( $template_id, '_cp_closing_template', true );
			if ( !$closing_tpl ) $closing_tpl = 'Atentamente, {name}';
			$closing_text = str_replace( '{name}', $name, $closing_tpl );
			$pdf->SetXY( $margin_left, $current_y );
			$pdf->MultiCell( $content_width, 10, utf8_decode( $closing_text ) );
		}
		
		return $pdf;
	}

	private function add_watermark( $pdf, $dim_w = 210, $dim_h = 297, $color = '#d7d7d7' ) {
		$rgb = $this->hex2rgb( $color );
		$pdf->SetFont( 'LibreBaskerville', 'B', 15 );
		$pdf->SetTextColor( $rgb[0], $rgb[1], $rgb[2] ); // Custom watermark color
		
		// Create a diagonal pattern 
		$spacing_x = 55;
		$spacing_y = 55;
		
		for ( $y = 0; $y < $dim_h + 100; $y += $spacing_y ) {
			for ( $x = 0; $x < $dim_w + 100; $x += $spacing_x ) {
				// We offset x or y slightly on odd rows to interlace the pattern
				$offset = ( ( $y / $spacing_y ) % 2 == 1 ) ? ( $spacing_x / 2 ) : 0;
				$pdf->RotatedText( $x - 50 + $offset, $y - 50, 'Muestra', 45 );
			}
		}
		
		$pdf->SetTextColor( 0, 0, 0 ); // Reset
	}

	private function hex2rgb( $hex ) {
		$hex = str_replace( "#", "", $hex );
		if ( strlen( $hex ) == 3 ) {
			$r = hexdec( substr( $hex, 0, 1 ) . substr( $hex, 0, 1 ) );
			$g = hexdec( substr( $hex, 1, 1 ) . substr( $hex, 1, 1 ) );
			$b = hexdec( substr( $hex, 2, 1 ) . substr( $hex, 2, 1 ) );
		} else {
			$r = hexdec( substr( $hex, 0, 2 ) );
			$g = hexdec( substr( $hex, 2, 2 ) );
			$b = hexdec( substr( $hex, 4, 2 ) );
		}
		return array( $r, $g, $b );
	}
}

// Extend FPDF to add RotatedText method
class FPDF_Rotated extends FPDF {
	function RotatedText($x, $y, $txt, $angle) {
		//Text rotated around its origin
		$this->Rotate($angle,$x,$y);
		$this->Text($x,$y,$txt);
		$this->Rotate(0);
	}

	var $angle=0;

	function Rotate($angle,$x=-1,$y=-1) {
		if($x==-1)
			$x=$this->x;
		if($y==-1)
			$y=$this->y;
		if($this->angle!=0)
			$this->_out('Q');
		$this->angle=$angle;
		if($angle!=0) {
			$angle*=M_PI/180;
			$c=cos($angle);
			$s=sin($angle);
			$cx=$x*$this->k;
			$cy=($this->h-$y)*$this->k;
			$this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm',$c,$s,-$s,$c,$cx,$cy,-$cx,-$cy));
		}
	}

	function _endpage() {
		if($this->angle!=0) {
			$this->angle=0;
			$this->_out('Q');
		}
		parent::_endpage();
	}
}

// Monkey patch or use the extended class. 
// Since I instantiated FPDF directly in create_pdf, I should change it to FPDF_Rotated if I want rotation.
// Let's fix create_pdf to use FPDF_Rotated.
