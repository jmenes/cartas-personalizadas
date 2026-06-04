<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CP_Templates {

	public function __construct() {
		add_action( 'init', array( $this, 'register_cpt' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_meta_boxes' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	public function register_cpt() {
		register_post_type( 'cp_template', array(
			'labels' => array(
				'name'               => __( 'Plantillas de Cartas', 'cartas-personalizadas' ),
				'singular_name'      => __( 'Plantilla', 'cartas-personalizadas' ),
				'add_new'            => __( 'Añadir Nueva', 'cartas-personalizadas' ),
				'add_new_item'       => __( 'Añadir Nueva Plantilla', 'cartas-personalizadas' ),
				'edit_item'          => __( 'Editar Plantilla', 'cartas-personalizadas' ),
				'new_item'           => __( 'Nueva Plantilla', 'cartas-personalizadas' ),
				'view_item'          => __( 'Ver Plantilla', 'cartas-personalizadas' ),
				'search_items'       => __( 'Buscar Plantillas', 'cartas-personalizadas' ),
				'not_found'          => __( 'No se encontraron plantillas', 'cartas-personalizadas' ),
				'not_found_in_trash' => __( 'No se encontraron plantillas en la papelera', 'cartas-personalizadas' ),
			),
			'public'      => false,
			'show_ui'     => true,
			'supports'    => array( 'title' ),
			'menu_icon'   => 'dashicons-media-document',
		) );
	}

	public function add_meta_boxes() {
		add_meta_box(
			'cp_template_config',
			__( 'Configuración de la Plantilla', 'cartas-personalizadas' ),
			array( $this, 'render_meta_box' ),
			'cp_template',
			'normal',
			'high'
		);
	}

	public function render_meta_box( $post ) {
		wp_nonce_field( 'cp_save_template_meta', 'cp_template_nonce' );

		$background_image = get_post_meta( $post->ID, '_cp_background_image', true );
		
		// Templates
		// Layout Config
		$element_separation = get_post_meta( $post->ID, '_cp_element_separation', true );
		if ( $element_separation === '' ) $element_separation = '10';

		// Models Data (JSON)
		$models_data = get_post_meta( $post->ID, '_cp_models', true );
		if ( empty( $models_data ) ) {
			// Migrate legacy blocks if exists
			$blocks_data = get_post_meta( $post->ID, '_cp_template_blocks', true );
			if ( ! empty( $blocks_data ) ) {
				$models_data = array(
					array(
						'id' => uniqid('model_'),
						'name' => 'Modelo Principal',
						'description' => '',
						'blocks' => is_array($blocks_data) ? $blocks_data : json_decode(wp_unslash($blocks_data), true)
					)
				);
				$models_data = wp_json_encode( $models_data );
			} else {
				$models_data = '[]';
			}
		} else if ( is_array( $models_data ) ) {
			$models_data = wp_json_encode( $models_data );
		}
		
		// Page Settings
		$page_size = get_post_meta( $post->ID, '_cp_page_size', true );
		if ( empty( $page_size ) ) $page_size = 'A4';
		
		$page_orientation = get_post_meta( $post->ID, '_cp_page_orientation', true );
		if ( empty( $page_orientation ) ) $page_orientation = 'P';
		?>
		<style>
			.cp-admin-wrap { max-width: 1000px; margin-top: 20px; }
			.cp-panel-section { background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin-bottom: 25px; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
			.cp-panel-section h3 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 12px; margin-bottom: 20px; font-size: 1.2em; color: #1d2327; }
			.cp-row { margin-bottom: 15px; }
			.cp-row label { display: inline-block; width: 180px; font-weight: 600; vertical-align: middle; color: #2271b1; }
			.cp-row input[type="number"] { width: 80px; }
			.cp-row input[type="text"], .cp-row select { width: 100%; max-width: 300px; }
			.cp-row input[type="color"] { width: 50px; height: 32px; padding: 0; border: 1px solid #8c8f94; border-radius: 4px; cursor: pointer; vertical-align: middle; }
			.cp-sub-row { margin-top: 5px; margin-left: 185px; color: #646970; font-size: 0.9em; font-style: italic; }
			
			.cp-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
			.cp-grid-2 .cp-row label { width: 140px; }
			.cp-grid-2 .cp-sub-row { margin-left: 145px; }
			.cp-inner-panel { border: 1px solid #e2e4e7; padding: 15px; background: #fff; border-radius: 3px; }
			.cp-inner-panel h4 { margin-top: 0; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #ddd; font-size: 1.05em; color: #1d2327; }
			
			.cp-models-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; background: #f0f0f1; padding: 15px; border-radius: 4px; border: 1px solid #ccd0d4; }
			.cp-m-variables-table th { background: #f6f7f7; font-weight: 600; }
			.cp-block-item { border: 1px solid #ccd0d4; padding: 0; margin-bottom: 20px; background: #fff; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
			.cp-block-item h4 { margin: 0; padding: 12px 15px; background: #f6f7f7; border-bottom: 1px solid #ccd0d4; font-size: 1.05em; display: flex; justify-content: space-between; align-items: center; }
			.cp-block-item-body { padding: 15px; }
		</style>
		
		<div class="cp-admin-wrap">

		<div class="cp-panel-section">
			<h3><?php _e( 'Opciones Generales del Documento PDF', 'cartas-personalizadas' ); ?></h3>
		
		<div class="cp-row">
			<label><?php _e( 'Tamaño de Papel', 'cartas-personalizadas' ); ?></label>
			<select name="cp_page_size">
				<option value="A4" <?php selected( $page_size, 'A4' ); ?>>A4 (210 x 297 mm)</option>
				<option value="A5" <?php selected( $page_size, 'A5' ); ?>>A5 (148 x 210 mm)</option>
				<option value="Letter" <?php selected( $page_size, 'Letter' ); ?>>Carta (216 x 279 mm)</option>
			</select>
		</div>

		<div class="cp-row">
			<label><?php _e( 'Orientación', 'cartas-personalizadas' ); ?></label>
			<select name="cp_page_orientation">
				<option value="P" <?php selected( $page_orientation, 'P' ); ?>><?php _e( 'Vertical (Portrait)', 'cartas-personalizadas' ); ?></option>
				<option value="L" <?php selected( $page_orientation, 'L' ); ?>><?php _e( 'Apaisado (Landscape)', 'cartas-personalizadas' ); ?></option>
			</select>
		</div>

		<div class="cp-row">
			<label><?php _e( 'Imagen de Fondo', 'cartas-personalizadas' ); ?></label>
			<input type="text" name="cp_background_image" id="cp_background_image" value="<?php echo esc_attr( $background_image ); ?>" class="regular-text" />
			<button type="button" class="button cp_upload_btn" data-target="#cp_background_image"><?php _e( 'Subir Imagen', 'cartas-personalizadas' ); ?></button>
			<div class="cp-sub-row">
				<span class="description"><?php _e( 'Imagen de fondo a tamaño completo (A4: 210 x 297 mm).', 'cartas-personalizadas' ); ?></span>
			</div>
		</div>

		<div class="cp-row">
			<label><?php _e( 'Separación Elementos', 'cartas-personalizadas' ); ?></label>
			<input type="number" name="cp_element_separation" value="<?php echo esc_attr( $element_separation ); ?>" step="1" placeholder="10" />
			<span class="description"><?php _e( 'mm. Espacio vertical entre los bloques al imprimir.', 'cartas-personalizadas' ); ?></span>
		</div>

		</div>

		<div class="cp-panel-section">
			<h3><?php _e('Modelos de la Plantilla', 'cartas-personalizadas'); ?></h3>
			<p class="description" style="margin-bottom:20px;"><?php _e('Define uno o más modelos (opciones) a elegir por el cliente. Cada modelo tiene su propia estructura de bloques.', 'cartas-personalizadas'); ?></p>

		<div id="cp-models-app">
			<input type="hidden" name="cp_models" id="cp_models_input" value="<?php echo esc_attr( $models_data ); ?>">
			
			<div class="cp-models-header">
				<div>
					<label style="font-weight:600; margin-right:10px;"><?php _e('Modelo Activo:', 'cartas-personalizadas'); ?></label>
					<select id="cp-model-selector" style="font-size: 16px; padding: 5px; min-width: 200px;">
						<!-- Options added via JS -->
					</select>
				</div>
				<div>
					<button type="button" class="button button-primary" id="cp-add-model-btn"><?php _e('+ Añadir Modelo', 'cartas-personalizadas'); ?></button>
					<button type="button" class="button button-link-delete" id="cp-remove-model-btn" style="color: #a00;"><?php _e('Eliminar', 'cartas-personalizadas'); ?></button>
				</div>
			</div>

			<div id="cp-model-editor" style="display:none; background: #fafafa; border: 1px solid #ccd0d4; padding: 20px; border-radius: 4px;">
				<div class="cp-inner-panel">
					<h4><?php _e('Datos Básicos', 'cartas-personalizadas'); ?></h4>
					<div class="cp-row">
						<label><?php _e('Nombre del Modelo', 'cartas-personalizadas'); ?></label>
						<input type="text" id="cp-model-name" placeholder="Ej: Para Niños...">
					</div>
					<div class="cp-row" style="margin-bottom:0; border:none; padding-bottom:0;">
						<label><?php _e('Descripción', 'cartas-personalizadas'); ?></label>
						<input type="text" id="cp-model-description" style="width: 100%; max-width: 500px;" placeholder="Se mostrará al cliente en la web">
						<span class="description" style="display:block; margin-left: 185px; margin-top:5px; color:#666; font-size:0.9em; font-style:italic;"><?php _e('Opcional. Texto explicativo que aparecerá debajo del modelo elegido.', 'cartas-personalizadas'); ?></span>
					</div>
				</div>
				<div class="cp-grid-2">
					<div class="cp-inner-panel">
						<h4><?php _e('Tipografía y Diseño', 'cartas-personalizadas'); ?></h4>
						<div class="cp-row">
							<label><?php _e('Alineación', 'cartas-personalizadas'); ?></label>
							<select id="cp-model-text-align" style="max-width: 150px;">
								<option value="L"><?php _e('Izquierda', 'cartas-personalizadas'); ?></option>
								<option value="C"><?php _e('Centrada', 'cartas-personalizadas'); ?></option>
								<option value="R"><?php _e('Derecha', 'cartas-personalizadas'); ?></option>
								<option value="J"><?php _e('Justificada', 'cartas-personalizadas'); ?></option>
							</select>
						</div>
						<div class="cp-row">
							<label><?php _e('Tamaño de Letra', 'cartas-personalizadas'); ?></label>
							<div style="display:inline-flex; align-items:center;">
								<input type="number" id="cp-model-font-size" style="width:70px;" placeholder="12">
								<span class="description" style="margin-left:8px;">pt (Por defecto: 12)</span>
							</div>
						</div>
						<div class="cp-row" style="margin-bottom:0; border:none; padding-bottom:0;">
							<label><?php _e('Interlineado', 'cartas-personalizadas'); ?></label>
							<div style="display:inline-flex; align-items:center;">
								<input type="number" id="cp-model-line-height" style="width:70px;" placeholder="8">
								<span class="description" style="margin-left:8px;">mm (Por defecto: 8)</span>
							</div>
						</div>
					</div>
					<div class="cp-inner-panel">
						<h4><?php _e('Marca de Agua (Previsualización)', 'cartas-personalizadas'); ?></h4>
						<p class="description" style="margin-top:0; margin-bottom: 20px;">Color del texto "Muestra" para este documento.</p>
						<div class="cp-row" style="margin-bottom:0; border:none; padding-bottom:0;">
							<label><?php _e('Color del Texto', 'cartas-personalizadas'); ?></label>
							<div style="display:inline-flex; align-items:center;">
								<input type="color" id="cp-model-watermark-color" value="#d7d7d7">
								<span class="description" style="margin-left:8px;">(#d7d7d7)</span>
							</div>
						</div>
					</div>
				</div>
				
				<div class="cp-inner-panel">
					<h4><?php _e('Variables Globales del Modelo', 'cartas-personalizadas'); ?></h4>
					<p class="description" style="margin-top:0; margin-bottom:15px;"><?php _e('Define aquí los marcadores (ej. {nombre}). Estos podrán usarse en cualquier bloque fijo insertándolos durante la redacción.', 'cartas-personalizadas'); ?></p>
					<table class="widefat striped cp-m-variables-table">
						<thead>
							<tr>
								<th>Marcador</th>
								<th>Etiqueta (Frontend)</th>
								<th>Descripción</th>
								<th>Tipo Campo</th>
								<th>Máx. Caracts.</th>
								<th></th>
							</tr>
						</thead>
						<tbody></tbody>
					</table>
					<p style="margin-bottom:0;"><button type="button" class="button cp-add-model-variable-btn"><?php _e('+ Añadir Nueva Variable', 'cartas-personalizadas'); ?></button></p>
				</div>

				<div class="cp-inner-panel">
					<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #ddd;">
						<h4 style="margin:0; border:none; padding:0;"><?php _e('Estructura de Bloques del Modelo', 'cartas-personalizadas'); ?></h4>
						<button type="button" class="button button-primary" id="cp-add-block-btn"><?php _e('+ Añadir Bloque', 'cartas-personalizadas'); ?></button>
					</div>
					<div id="cp-blocks-container"></div>
				</div>
				
			</div> <!-- END: Model Editor -->
		</div> <!-- END: Panel Section for Models -->
		</div> <!-- END: Admin Wrap -->

		<!-- JS Template for a Block -->
		<script type="text/template" id="tmpl-cp-block">
			<div class="cp-block-item">
				<h4>
					<span>Bloque #<span class="block-index-display"></span></span>
					<button type="button" class="button button-small button-link-delete cp-remove-block" style="color:#a00;"><?php _e('Eliminar', 'cartas-personalizadas'); ?></button>
				</h4>
				<div class="cp-block-item-body">
				
				<div class="cp-row" style="margin-bottom: 20px;">
					<label style="font-weight:600; vertical-align:middle; width:150px;">Tipo de Bloque</label>
					<select class="cp-block-type" style="width:300px;">
						<option value="fixed">Texto Fijo con Marcadores</option>
						<option value="input">Entrada Libre del Cliente</option>
					</select>
				</div>

				<!-- Navigation Tabs -->
				<h2 class="nav-tab-wrapper cp-block-tabs" style="border-bottom: 1px solid #ccc; margin-bottom: 15px; padding-bottom: 0;">
					<a href="#" class="nav-tab nav-tab-active cp-tab-content" data-tab="content">Contenido</a>
					<a href="#" class="nav-tab cp-tab-pdf" data-tab="pdf">Opciones de PDF</a>
				</h2>

				<!-- Settings Wrapper -->
				<div class="cp-block-tab-panels">
					<!-- TAB 1: Content -->
					<div class="cp-panel-content cp-block-panel">
						<!-- Section for Input Type -->
						<div class="cp-block-input-settings" style="display:none; padding-left: 20px; border-left: 3px solid #007cba;">
							<div class="cp-row">
								<label>Etiqueta (Label)</label>
								<input type="text" class="cp-b-label" placeholder="Ej: Escribe un mensaje...">
							</div>
							<div class="cp-row">
								<label>Descripción</label>
								<input type="text" class="cp-b-desc" placeholder="Se mostrará bajo la etiqueta">
							</div>
							<div class="cp-row">
								<label>Tipo de Campo</label>
								<select class="cp-b-field-type">
									<option value="text">Texto Corto (1 línea)</option>
									<option value="textarea">Párrafo (Varias líneas)</option>
								</select>
							</div>
							<div class="cp-row">
								<label>Máx. Caracteres</label>
								<input type="number" class="cp-b-max" placeholder="Opcional">
							</div>
						</div>

						<!-- Section for Fixed Type -->
						<div class="cp-block-fixed-settings" style="display:none; padding-left: 20px; border-left: 3px solid #2271b1;">
							<div class="cp-row" style="margin-bottom: 20px;">
								<label style="display:block; width:auto; text-align:left; margin-bottom:5px;">Texto Base</label>
								<textarea class="cp-b-base-text" rows="4" style="width:100%;" placeholder="Ej: Querido {nombre}, sabemos que vives en {ciudad}"></textarea>
								<span class="description">Usa llaves {marcador} para definir variables que pediremos al cliente. (Define las variables en la sección "Variables del Modelo" de arriba).</span>
							</div>
						</div>
					</div>

					<!-- TAB 2: PDF RENDER OPTIONS -->
					<div class="cp-panel-pdf cp-block-panel" style="display:none; padding-left: 20px; border-left: 3px solid #666;">
						<div class="cp-row">
							<label>Posicionamiento</label>
							<select class="cp-b-position-type">
								<option value="sequential">A continuación (Secuencial)</option>
								<option value="absolute">Ajuste personalizado (X, Y)</option>
							</select>
						</div>
						<div class="cp-block-absolute-settings" style="display:none;">
							<div class="cp-row">
								<label>Posición X (mm)</label>
								<input type="number" step="0.1" class="cp-b-pos-x" placeholder="0">
							</div>
							<div class="cp-row">
								<label>Posición Y (mm)</label>
								<input type="number" step="0.1" class="cp-b-pos-y" placeholder="0">
							</div>
						</div>
						<div class="cp-row">
							<label>Ancho Máximo (mm)</label>
							<input type="number" step="0.1" class="cp-b-width" placeholder="Por defecto">
							<span class="description" style="margin-left:5px;">0 o vacío para que ocupe todo el ancho disponible.</span>
						</div>
					</div>
				</div> <!-- End Tab Panels -->
				</div> <!-- End Block Body -->
			</div> <!-- End cp-block-item -->
		</script>

		<!-- JS Template for a Variable Row -->
		<script type="text/template" id="tmpl-cp-variable">
			<tr>
				<td><input type="text" class="cp-v-tag" placeholder="{nombre}" style="width:100%;"></td>
				<td><input type="text" class="cp-v-label" placeholder="Nombre... " style="width:100%;"></td>
				<td><input type="text" class="cp-v-desc" placeholder="Opcional..." style="width:100%;"></td>
				<td>
					<select class="cp-v-type" style="width:100%;">
						<option value="text">Texto Corto</option>
						<option value="textarea">Párrafo</option>
					</select>
				</td>
				<td><input type="number" class="cp-v-max" style="width:100%;"></td>
				<td><button type="button" class="button button-link-delete cp-remove-variable">&times;</button></td>
			</tr>
		</script>
		<?php
	}

	public function save_meta_boxes( $post_id ) {
		if ( ! isset( $_POST['cp_template_nonce'] ) || ! wp_verify_nonce( $_POST['cp_template_nonce'], 'cp_save_template_meta' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$fields = array(
			'cp_background_image',
			'cp_element_separation',
			'cp_page_size', 'cp_page_orientation',
		);

		foreach ( $fields as $field ) {
			if ( isset( $_POST[ $field ] ) ) {
				update_post_meta( $post_id, '_' . $field, sanitize_text_field( $_POST[ $field ] ) );
			}
		}

		// Save Models JSON properly
		if ( isset( $_POST['cp_models'] ) ) {
			$models_json = stripslashes( $_POST['cp_models'] );
			$models_data = json_decode( $models_json, true );
			if ( is_array( $models_data ) ) {
				update_post_meta( $post_id, '_cp_models', $models_data );
				
				// Optional backwards compatibility: save the first model's blocks to the old meta key
				if ( ! empty( $models_data[0]['blocks'] ) ) {
					update_post_meta( $post_id, '_cp_template_blocks', $models_data[0]['blocks'] );
				}
			}
		}
	}

	public function enqueue_admin_scripts( $hook ) {
		global $post;
		if ( 'post.php' != $hook && 'post-new.php' != $hook ) {
			return;
		}
		if ( 'cp_template' != $post->post_type ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script( 'cp-admin-script', CP_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), '1.0.0', true );
	}
}
