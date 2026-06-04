import re

with open('includes/class-cp-templates.php', 'r') as f:
    content = f.read()

# Replace styles
old_style = "<style>"
			.cp-row { margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
			.cp-row label { display: inline-block; width: 150px; font-weight: bold; vertical-align: top; }
			.cp-row input[type="number"] { width: 70px; }
			.cp-row input[type="text"] { width: 300px; }
			.cp-sub-row { margin-top: 5px; margin-left: 155px; }
		</style>"""
new_style = """<style>
			.cp-admin-wrap { max-width: 1000px; margin-top: 20px; }
			.cp-panel-section { background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin-bottom: 25px; border-radius: 4px; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
			.cp-panel-section h3 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 12px; margin-bottom: 20px; font-size: 1.25em; color: #1d2327; }
			.cp-row { margin-bottom: 15px; }
			.cp-row label { display: inline-block; width: 180px; font-weight: 600; vertical-align: middle; color: #2271b1; }
			.cp-row input[type="number"] { width: 80px; }
			.cp-row input[type="text"], .cp-row select { width: 100%; max-width: 300px; }
			.cp-row input[type="color"] { width: 50px; height: 32px; padding: 0; border: 1px solid #8c8f94; border-radius: 4px; cursor: pointer; vertical-align: middle; }
			.cp-sub-row { margin-top: 5px; margin-left: 185px; color: #646970; font-size: 0.9em; font-style: italic; }
			
			.cp-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; }
			.cp-grid-2 .cp-row label { width: 140px; }
			.cp-grid-2 .cp-sub-row { margin-left: 145px; }
			
			.cp-models-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; background: #f0f0f1; padding: 15px; border-radius: 4px; border: 1px solid #ccd0d4; }
			.cp-models-header-actions { display: flex; gap: 10px; align-items: center; }
			.cp-m-variables-table th { background: #f6f7f7; font-weight: 600; }
			
			.cp-inner-panel { border: 1px solid #e2e4e7; padding: 20px; background: #fff; margin-bottom: 20px; border-radius: 3px; box-shadow: 0 1px 2px rgba(0,0,0,.02); }
			.cp-inner-panel h4 { margin-top: 0; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #ddd; font-size: 1.1em; color: #1d2327; }
			
			.cp-block-item { border: 1px solid #ccd0d4; padding: 0; margin-bottom: 20px; background: #fff; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
			.cp-block-item h4 { margin: 0; padding: 12px 15px; background: #f6f7f7; border-bottom: 1px solid #ccd0d4; font-size: 1.05em; display: flex; justify-content: space-between; align-items: center; }
			.cp-block-item-body { padding: 15px; }
		</style>
		<div class="cp-admin-wrap">
			<div class="cp-panel-section">
				<h3><?php _e( 'Ajustes Generales del Documento', 'cartas-personalizadas' ); ?></h3>"""
content = content.replace(old_style, new_style)

# Wrap models starting
old_models_start = """<hr style="margin: 20px 0;">
		<h3><?php _e('Modelos de la Plantilla', 'cartas-personalizadas'); ?></h3>
		<p class="description"><?php _e('Define uno o más modelos (opciones) a elegir por el cliente. Cada modelo tiene su propia estructura de bloques.', 'cartas-personalizadas'); ?></p>"""
new_models_start = """</div> <!-- End Panel Section -->
			<div class="cp-panel-section">
				<h3><?php _e('Modelos de la Plantilla', 'cartas-personalizadas'); ?></h3>
				<p class="description" style="margin-bottom:20px;"><?php _e('Define los diferentes "Modelos" disponibles. Cada modelo puede tener un nombre, una configuración tipográfica diferente, y sus propios bloques de texto y variables.', 'cartas-personalizadas'); ?></p>"""
content = content.replace(old_models_start, new_models_start)

# Rewrite models header
old_models_header = """<div class="cp-models-tabs" style="margin-bottom: 20px;">
				<select id="cp-model-selector" style="font-size: 16px; padding: 5px; min-width: 200px;">
					<!-- Options added via JS -->
				</select>
				<button type="button" class="button" id="cp-add-model-btn"><?php _e('+ Añadir Modelo', 'cartas-personalizadas'); ?></button>
				<button type="button" class="button button-link-delete" id="cp-remove-model-btn" style="color: #a00;"><?php _e('Eliminar Modelo', 'cartas-personalizadas'); ?></button>
			</div>"""
new_models_header = """<div class="cp-models-header">
				<div>
					<label style="font-weight:600; margin-right:10px;"><?php _e('Modelo Activo:', 'cartas-personalizadas'); ?></label>
					<select id="cp-model-selector" style="font-size: 16px; padding: 5px; min-width: 250px;">
						<!-- Options added via JS -->
					</select>
				</div>
				<div class="cp-models-header-actions">
					<button type="button" class="button button-primary" id="cp-add-model-btn"><?php _e('+ Añadir Modelo', 'cartas-personalizadas'); ?></button>
					<button type="button" class="button button-link-delete" id="cp-remove-model-btn" style="color: #a00;"><?php _e('Eliminar', 'cartas-personalizadas'); ?></button>
				</div>
			</div>"""
content = content.replace(old_models_header, new_models_header)

# Editor inside
old_editor_start = """<div id="cp-model-editor" style="display:none; border: 1px solid #ccd0d4; padding: 15px; background: #fff;">
				<div class="cp-row">
					<label><?php _e('Nombre del Modelo', 'cartas-personalizadas'); ?></label>
					<input type="text" id="cp-model-name" style="width: 300px;">
				</div>
				<div class="cp-row">
					<label><?php _e('Descripción', 'cartas-personalizadas'); ?></label>
					<input type="text" id="cp-model-description" style="width: 100%; max-width: 600px;">
					<span class="description" style="display:block; margin-left: 155px; margin-top:5px;"><?php _e('Opcional. Se mostrará al cliente debajo del nombre.', 'cartas-personalizadas'); ?></span>
				</div>
				<div class="cp-row">
					<label><?php _e('Tamaño Tipografía', 'cartas-personalizadas'); ?></label>
					<input type="number" id="cp-model-font-size" style="width: 100px;" placeholder="12">
					<span class="description" style="margin-left:5px;"><?php _e('Por defecto: 12', 'cartas-personalizadas'); ?></span>
				</div>
				<div class="cp-row">
					<label><?php _e('Interlineado', 'cartas-personalizadas'); ?></label>
					<input type="number" id="cp-model-line-height" style="width: 100px;" placeholder="8">
					<span class="description" style="margin-left:5px;"><?php _e('Por defecto: 8', 'cartas-personalizadas'); ?></span>
				</div>
				<div class="cp-row">
					<label><?php _e('Alineación de Texto', 'cartas-personalizadas'); ?></label>
					<select id="cp-model-text-align" style="width: 150px;">
						<option value="L"><?php _e('Izquierda', 'cartas-personalizadas'); ?></option>
						<option value="C"><?php _e('Centrado', 'cartas-personalizadas'); ?></option>
						<option value="R"><?php _e('Derecha', 'cartas-personalizadas'); ?></option>
						<option value="J"><?php _e('Justificado', 'cartas-personalizadas'); ?></option>
					</select>
				</div>
				<div class="cp-row">
					<label><?php _e('Color de Marca de Agua', 'cartas-personalizadas'); ?></label>
					<input type="color" id="cp-model-watermark-color" value="#d7d7d7" style="width: 50px;">
					<span class="description" style="margin-left:5px;"><?php _e('Por defecto: gris claro (#d7d7d7)', 'cartas-personalizadas'); ?></span>
				</div>"""
new_editor_start = """<div id="cp-model-editor" style="display:none; background: #fafafa; border: 1px solid #ccd0d4; padding: 20px; border-radius: 4px;">
				<div class="cp-inner-panel">
					<h4><?php _e('Datos Básicos', 'cartas-personalizadas'); ?></h4>
					<div class="cp-row">
						<label><?php _e('Nombre', 'cartas-personalizadas'); ?></label>
						<input type="text" id="cp-model-name" placeholder="Ej: Para Niños...">
					</div>
					<div class="cp-row" style="margin-bottom:0;">
						<label><?php _e('Descripción', 'cartas-personalizadas'); ?></label>
						<input type="text" id="cp-model-description" style="max-width: 500px;" placeholder="Se mostrará en la tienda">
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
								<span class="description" style="margin-left:8px;">pt</span>
							</div>
						</div>
						<div class="cp-row" style="margin-bottom:0;">
							<label><?php _e('Interlineado', 'cartas-personalizadas'); ?></label>
							<div style="display:inline-flex; align-items:center;">
								<input type="number" id="cp-model-line-height" style="width:70px;" placeholder="8">
								<span class="description" style="margin-left:8px;">mm</span>
							</div>
						</div>
					</div>
					<div class="cp-inner-panel">
						<h4><?php _e('Marca de Agua (Previsualización)', 'cartas-personalizadas'); ?></h4>
						<div class="cp-row" style="margin-bottom:0;">
							<label><?php _e('Color del Texto', 'cartas-personalizadas'); ?></label>
							<div style="display:inline-flex; align-items:center;">
								<input type="color" id="cp-model-watermark-color" value="#d7d7d7">
								<span class="description" style="margin-left:8px;">(#d7d7d7)</span>
							</div>
						</div>
					</div>
				</div>"""
content = content.replace(old_editor_start, new_editor_start)

# Editor parts
old_parts = """<hr>
				<h4><?php _e('Variables del Modelo', 'cartas-personalizadas'); ?></h4>
				<p class="description"><?php _e('Define aquí los marcadores (ej. {nombre}) que los clientes rellenarán. Estos marcadores pueden usarse en cualquier bloque fijo de este modelo.', 'cartas-personalizadas'); ?></p>"""
new_parts = """<div class="cp-inner-panel">
					<h4><?php _e('Variables Globales del Modelo', 'cartas-personalizadas'); ?></h4>
					<p class="description" style="margin-bottom:15px;"><?php _e('Todos los marcadores definidos aquí (ej. {nombre}) podrán utilizarse en cualquier bloque fijo. Se preguntarán una única vez al principio del formulario.', 'cartas-personalizadas'); ?></p>"""
content = content.replace(old_parts, new_parts)

old_vars_end = """</table>
				<p><button type="button" class="button cp-add-model-variable-btn">+ Añadir Variable</button></p>

				<hr>
				<h4><?php _e('Estructura de Bloques del Modelo', 'cartas-personalizadas'); ?></h4>
				<div id="cp-blocks-container"></div>
				
				<p>
					<button type="button" class="button button-primary" id="cp-add-block-btn"><?php _e('+ Añadir Bloque', 'cartas-personalizadas'); ?></button>
				</p>
			</div>
		</div>

		<!-- JS Template for a Block -->"""
new_vars_end = """</table>
					<p style="margin-top:15px; margin-bottom:0;"><button type="button" class="button" id="" class="cp-add-model-variable-btn" onclick="jQuery('.cp-add-model-variable-btn').click()">+ Añadir Variable</button></p>
				</div> <!-- End Inner Panel -->

				<div class="cp-inner-panel">
					<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #ddd;">
						<h4 style="margin:0; border:none; padding:0;"><?php _e('Párrafos y Bloques del Documento', 'cartas-personalizadas'); ?></h4>
						<button type="button" class="button button-primary" id="cp-add-block-btn"><?php _e('+ Añadir Bloque', 'cartas-personalizadas'); ?></button>
					</div>
					
					<div id="cp-blocks-container"></div>
				</div> <!-- End Inner Panel -->
				
			</div> <!-- End active model editor -->
			</div> <!-- End Panel Section -->
		</div> <!-- End Admin Wrap -->

		<!-- JS Template for a Block -->"""
content = content.replace(old_vars_end, new_vars_end)

# Template for Block JS
old_block_js = """<div class="cp-block-item" style="border: 1px solid #ccd0d4; padding: 15px; margin-bottom: 15px; background: #fafafa; border-radius: 4px;">
				<div style="float: right;">
					<button type="button" class="button button-link-delete cp-remove-block"><?php _e('Eliminar', 'cartas-personalizadas'); ?></button>
				</div>
				<h4>Bloque #<span class="block-index-display"></span></h4>"""
new_block_js = """<div class="cp-block-item">
				<h4>
					<span>Bloque #<span class="block-index-display"></span></span>
					<button type="button" class="button button-small button-link-delete cp-remove-block" style="color:#a00;"><?php _e('Eliminar', 'cartas-personalizadas'); ?></button>
				</h4>
				<div class="cp-block-item-body">"""
content = content.replace(old_block_js, new_block_js)


old_end_js = """</div>
					</div>
				</div>
			</div>
		</script>"""
new_end_js = """</div>
					</div>
				</div>
				</div> <!-- End Body -->
			</div>
		</script>"""
content = content.replace(old_end_js, new_end_js)


with open('includes/class-cp-templates.php', 'w') as f:
    f.write(content)

print("UI improvements applied successfully.")
