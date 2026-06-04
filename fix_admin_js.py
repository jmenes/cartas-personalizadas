import re

with open('assets/js/admin.js', 'r') as f:
    content = f.read()

# 1. Update modelsData.push to include variables
content = content.replace("description: '',\n                blocks: []", "description: '',\n                variables: [],\n                blocks: []")

# 2. Update newBlock to NOT include variables
content = content.replace("base_text: '',\n                variables: []", "base_text: ''")

# 3. Add variable button and remove variable
old_handlers = """        // Add Variable
        $blocksContainer.on('click', '.cp-add-variable-btn', function () {
            var $block = $(this).closest('.cp-block-item');
            var $tbody = $block.find('.cp-b-variables-table tbody');
            renderVariable($tbody, {});
            updateBlockDataFromDOM($block);
            serializeModels();
        });"""
new_handlers = """        // Add Model Variable
        $('#cp-add-model-variable-btn').on('click', function () {
            if (activeModelIndex < 0) return;
            var $tbody = $('.cp-m-variables-table tbody');
            renderVariable($tbody, {});
            updateModelVariablesFromDOM();
            serializeModels();
        });"""
content = content.replace(old_handlers, new_handlers)

old_remove = """        // Remove Variable
        $blocksContainer.on('click', '.cp-remove-variable', function () {
            var $block = $(this).closest('.cp-block-item');
            $(this).closest('tr').remove();
            updateBlockDataFromDOM($block);
            serializeModels();
        });"""
new_remove = """        // Remove Model Variable
        $modelEditor.on('click', '.cp-remove-variable', function () {
            $(this).closest('tr').remove();
            updateModelVariablesFromDOM();
            serializeModels();
        });"""
content = content.replace(old_remove, new_remove)

# 4. updateBlockDataFromDOM remove variables
old_update_block = """            } else {
                blockData.base_text = $block.find('.cp-b-base-text').val();
                blockData.variables = [];
                $block.find('.cp-b-variables-table tbody tr').each(function () {
                    var $v = $(this);
                    blockData.variables.push({
                        tag: $v.find('.cp-v-tag').val(),
                        label: $v.find('.cp-v-label').val(),
                        desc: $v.find('.cp-v-desc').val(),
                        type: $v.find('.cp-v-type').val(),
                        max_chars: $v.find('.cp-v-max').val()
                    });
                });
            }"""
new_update_block = """            } else {
                blockData.base_text = $block.find('.cp-b-base-text').val();
            }"""
if old_update_block in content:
    content = content.replace(old_update_block, new_update_block)

# Add updateModelVariablesFromDOM
update_model_vars = """
        function updateModelVariablesFromDOM() {
            if (activeModelIndex < 0) return;
            var variables = [];
            $('.cp-m-variables-table tbody tr').each(function () {
                var $v = $(this);
                variables.push({
                    tag: $v.find('.cp-v-tag').val(),
                    label: $v.find('.cp-v-label').val(),
                    desc: $v.find('.cp-v-desc').val(),
                    type: $v.find('.cp-v-type').val(),
                    max_chars: $v.find('.cp-v-max').val()
                });
            });
            modelsData[activeModelIndex].variables = variables;
        }
"""
if "updateModelVariablesFromDOM()" not in content:
    content = content.replace("function serializeModels() {", update_model_vars + "\n        function serializeModels() {")

# Auto-serialize update
old_auto = """        // Auto-serialize on change/input
        $blocksContainer.on('change input keyup', 'input, select, textarea', function () {
            if ($(this).hasClass('cp-block-type')) return;
            var $block = $(this).closest('.cp-block-item');
            updateBlockDataFromDOM($block);
            serializeModels();
        });"""
new_auto = """        // Auto-serialize on change/input
        $modelEditor.on('change input keyup', 'input, select, textarea', function () {
            if ($(this).hasClass('cp-block-type')) return;
            var $block = $(this).closest('.cp-block-item');
            if ($block.length > 0) {
                updateBlockDataFromDOM($block);
            } else if ($(this).closest('.cp-m-variables-table').length > 0) {
                updateModelVariablesFromDOM();
            }
            serializeModels();
        });"""
if old_auto in content:
    content = content.replace(old_auto, new_auto)

# loadModelEditor
old_load = """                $modelName.val(m.name || '');
                $modelDesc.val(m.description || '');

                $blocksContainer.empty();"""
new_load = """                $modelName.val(m.name || '');
                $modelDesc.val(m.description || '');

                var $tbody = $('.cp-m-variables-table tbody');
                $tbody.empty();
                if (Array.isArray(m.variables)) {
                    m.variables.forEach(function (v) {
                        renderVariable($tbody, v);
                    });
                } else {
                    m.variables = [];
                }

                $blocksContainer.empty();"""
if old_load in content:
    content = content.replace(old_load, new_load)

# renderBlock remove var mapping
old_renderBlock = """            if (data.type === 'fixed' && Array.isArray(data.variables)) {
                var $tbody = $block.find('.cp-b-variables-table tbody');
                data.variables.forEach(function (v) {
                    renderVariable($tbody, v);
                });
            }

            var type = data.type || 'fixed';"""
new_renderBlock = """            var type = data.type || 'fixed';"""
if old_renderBlock in content:
    content = content.replace(old_renderBlock, new_renderBlock)


with open('assets/js/admin.js', 'w') as f:
    f.write(content)

print('Updated admin.js')
