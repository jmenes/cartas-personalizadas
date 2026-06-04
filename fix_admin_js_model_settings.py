import re

with open('assets/js/admin.js', 'r') as f:
    content = f.read()

# Variables new model defaults
old_defaults = """                name: 'Nuevo Modelo',
                description: '',
                variables: [],
                blocks: []"""
new_defaults = """                name: 'Nuevo Modelo',
                description: '',
                font_size: '12',
                line_height: '8',
                variables: [],
                blocks: []"""
content = content.replace(old_defaults, new_defaults)

# Listeners for new inputs
old_listeners = """        $modelDesc.on('input', function () {
            if (activeModelIndex >= 0) {
                modelsData[activeModelIndex].description = $(this).val();
                serializeModels();
            }
        });"""
new_listeners = """        $modelDesc.on('input', function () {
            if (activeModelIndex >= 0) {
                modelsData[activeModelIndex].description = $(this).val();
                serializeModels();
            }
        });

        $('#cp-model-font-size').on('input', function () {
            if (activeModelIndex >= 0) {
                modelsData[activeModelIndex].font_size = $(this).val();
                serializeModels();
            }
        });

        $('#cp-model-line-height').on('input', function () {
            if (activeModelIndex >= 0) {
                modelsData[activeModelIndex].line_height = $(this).val();
                serializeModels();
            }
        });"""
content = content.replace(old_listeners, new_listeners)

# Update loadModelEditor
old_load = """                $modelName.val(m.name || '');
                $modelDesc.val(m.description || '');"""
new_load = """                $modelName.val(m.name || '');
                $modelDesc.val(m.description || '');
                $('#cp-model-font-size').val(m.font_size || '12');
                $('#cp-model-line-height').val(m.line_height || '8');"""
content = content.replace(old_load, new_load)


with open('assets/js/admin.js', 'w') as f:
    f.write(content)

print('Updated admin.js')
