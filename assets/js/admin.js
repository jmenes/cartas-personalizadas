jQuery(document).ready(function ($) {
    $('.cp_upload_btn').click(function (e) {
        e.preventDefault();
        var target = $(this).data('target');
        var image = wp.media({
            title: 'Subir Imagen',
            multiple: false
        }).open()
            .on('select', function (e) {
                var uploaded_image = image.state().get('selection').first();
                var image_url = uploaded_image.toJSON().url;
                $(target).val(image_url);
            });
    });

    // --- Template Models & Blocks Logic ---
    var $modelsInput = $('#cp_models_input');
    var $modelSelector = $('#cp-model-selector');
    var $addModelBtn = $('#cp-add-model-btn');
    var $removeModelBtn = $('#cp-remove-model-btn');
    var $modelEditor = $('#cp-model-editor');
    var $modelName = $('#cp-model-name');
    var $modelDesc = $('#cp-model-description');

    var $blocksContainer = $('#cp-blocks-container');
    var $addBlockBtn = $('#cp-add-block-btn');

    if ($modelsInput.length > 0) {
        var modelsData = [];
        var activeModelIndex = -1;

        // Load initial data
        try {
            var val = $modelsInput.val();
            if (val) modelsData = JSON.parse(val);
        } catch (e) { console.error('Error parsing models JSON'); }

        if (!Array.isArray(modelsData)) {
            modelsData = [];
        }

        renderModelSelector();

        // Model selector change
        $modelSelector.on('change', function () {
            var idx = parseInt($(this).val(), 10);
            loadModelEditor(idx);
        });

        // Add Model
        $addModelBtn.on('click', function () {
            var newId = 'model_' + Math.random().toString(36).substr(2, 9);
            modelsData.push({
                id: newId,
                name: 'Nuevo Modelo',
                description: '',
                font_size: '12',
                line_height: '8',
                text_align: 'L',
                watermark_color: '#d7d7d7',
                variables: [],
                blocks: []
            });
            renderModelSelector();
            $modelSelector.val(modelsData.length - 1).trigger('change');
            serializeModels();
        });

        // Remove Model
        $removeModelBtn.on('click', function () {
            if (activeModelIndex >= 0 && confirm('¿Eliminar este modelo?')) {
                modelsData.splice(activeModelIndex, 1);
                renderModelSelector();
                serializeModels();
            }
        });

        // Update Model Name/Desc
        $modelName.on('input', function () {
            if (activeModelIndex >= 0) {
                modelsData[activeModelIndex].name = $(this).val();
                $modelSelector.find('option:selected').text($(this).val());
                serializeModels();
            }
        });

        $modelDesc.on('input', function () {
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
        });

        $('#cp-model-text-align').on('change', function () {
            if (activeModelIndex >= 0) {
                modelsData[activeModelIndex].text_align = $(this).val();
                serializeModels();
            }
        });

        $('#cp-model-watermark-color').on('input', function () {
            if (activeModelIndex >= 0) {
                modelsData[activeModelIndex].watermark_color = $(this).val();
                serializeModels();
            }
        });

        // Add New Block
        $addBlockBtn.on('click', function () {
            if (activeModelIndex < 0) return;
            var newBlock = {
                type: 'fixed',
                position_type: 'sequential',
                pos_x: '',
                pos_y: '',
                width: '',
                label: '',
                desc: '',
                field_type: 'text',
                max_chars: '',
                base_text: ''
            };
            modelsData[activeModelIndex].blocks.push(newBlock);
            renderBlock(newBlock);
            updateBlockIndices();
            serializeModels();
        });

        // Remove Block
        $blocksContainer.on('click', '.cp-remove-block', function () {
            if (confirm('¿Seguro que quieres eliminar este bloque?')) {
                var $blockItem = $(this).closest('.cp-block-item');
                var bIndex = $blockItem.index();
                modelsData[activeModelIndex].blocks.splice(bIndex, 1);
                $blockItem.remove();
                updateBlockIndices();
                serializeModels();
            }
        });

        // Change Block Type
        $blocksContainer.on('change', '.cp-block-type', function () {
            var $block = $(this).closest('.cp-block-item');
            if ($(this).val() === 'input') {
                $block.find('.cp-block-input-settings').show();
                $block.find('.cp-block-fixed-settings').hide();
            } else {
                $block.find('.cp-block-input-settings').hide();
                $block.find('.cp-block-fixed-settings').show();
            }
            updateBlockDataFromDOM($block);
            serializeModels();
        });

        // Add Model Variable
        $modelEditor.on('click', '.cp-add-model-variable-btn', function () {
            if (activeModelIndex < 0) return;
            var $tbody = $('.cp-m-variables-table tbody');
            renderVariable($tbody, {});
            updateModelVariablesFromDOM();
            serializeModels();
        });

        // Position Type Toggle
        $blocksContainer.on('change', '.cp-b-position-type', function () {
            var $block = $(this).closest('.cp-block-item');
            if ($(this).val() === 'absolute') {
                $block.find('.cp-block-absolute-settings').show();
            } else {
                $block.find('.cp-block-absolute-settings').hide();
            }
            updateBlockDataFromDOM($block);
            serializeModels();
        });

        // Block Tabs Toggle
        $blocksContainer.on('click', '.nav-tab', function (e) {
            e.preventDefault();
            var $block = $(this).closest('.cp-block-item');
            var tabName = $(this).data('tab');

            // Manage active tab state
            $block.find('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');

            // Show correct panel
            $block.find('.cp-block-panel').hide();
            $block.find('.cp-panel-' + tabName).show();
        });

        // Remove Model Variable
        $modelEditor.on('click', '.cp-remove-variable', function () {
            $(this).closest('tr').remove();
            updateModelVariablesFromDOM();
            serializeModels();
        });

        // Auto-serialize on change/input
        $modelEditor.on('change input keyup', 'input, select, textarea', function () {
            if ($(this).hasClass('cp-block-type')) return;
            var $block = $(this).closest('.cp-block-item');
            if ($block.length > 0) {
                updateBlockDataFromDOM($block);
            } else if ($(this).closest('.cp-m-variables-table').length > 0) {
                updateModelVariablesFromDOM();
            }
            serializeModels();
        });

        $('form#post').on('submit', function () {
            serializeModels();
        });

        function renderModelSelector() {
            $modelSelector.empty();
            if (modelsData.length === 0) {
                $modelSelector.append('<option value="-1">-- Ningún modelo --</option>');
                loadModelEditor(-1);
            } else {
                modelsData.forEach(function (m, idx) {
                    $modelSelector.append('<option value="' + idx + '">' + (m.name || 'Modelo ' + (idx + 1)) + '</option>');
                });
                if (activeModelIndex >= 0 && activeModelIndex < modelsData.length) {
                    $modelSelector.val(activeModelIndex);
                    loadModelEditor(activeModelIndex);
                } else {
                    $modelSelector.val(0);
                    loadModelEditor(0);
                }
            }
        }

        function loadModelEditor(index) {
            activeModelIndex = index;
            if (index < 0) {
                $modelEditor.hide();
                $removeModelBtn.hide();
            } else {
                $modelEditor.show();
                $removeModelBtn.show();
                var m = modelsData[index];
                $modelName.val(m.name || '');
                $modelDesc.val(m.description || '');
                $('#cp-model-font-size').val(m.font_size || '12');
                $('#cp-model-line-height').val(m.line_height || '8');
                $('#cp-model-text-align').val(m.text_align || 'L');
                $('#cp-model-watermark-color').val(m.watermark_color || '#d7d7d7');

                var $tbody = $('.cp-m-variables-table tbody');
                $tbody.empty();

                // Migrate old block variables to model variables
                if (!Array.isArray(m.variables) || m.variables.length === 0) {
                    m.variables = [];
                    if (Array.isArray(m.blocks)) {
                        m.blocks.forEach(function (b) {
                            if (b.type === 'fixed' && Array.isArray(b.variables)) {
                                b.variables.forEach(function (varObj) {
                                    var exists = m.variables.find(function (v) { return v.tag === varObj.tag; });
                                    if (!exists) m.variables.push(varObj);
                                });
                            }
                        });
                    }
                }

                if (Array.isArray(m.variables)) {
                    m.variables.forEach(function (v) {
                        renderVariable($tbody, v);
                    });
                } else {
                    m.variables = [];
                }

                $blocksContainer.empty();
                if (Array.isArray(m.blocks)) {
                    m.blocks.forEach(function (b) {
                        renderBlock(b);
                    });
                } else {
                    m.blocks = [];
                }
                updateBlockIndices();
            }
        }

        function updateBlockDataFromDOM($block) {
            if (activeModelIndex < 0) return;
            var bIndex = $block.index();
            var type = $block.find('.cp-block-type').val();
            var position_type = $block.find('.cp-b-position-type').val();

            var blockData = {
                type: type,
                position_type: position_type,
                pos_x: $block.find('.cp-b-pos-x').val(),
                pos_y: $block.find('.cp-b-pos-y').val(),
                width: $block.find('.cp-b-width').val()
            };
            if (type === 'input') {
                blockData.label = $block.find('.cp-b-label').val();
                blockData.desc = $block.find('.cp-b-desc').val();
                blockData.field_type = $block.find('.cp-b-field-type').val();
                blockData.max_chars = $block.find('.cp-b-max').val();
            } else {
                blockData.base_text = $block.find('.cp-b-base-text').val();
            }
            modelsData[activeModelIndex].blocks[bIndex] = blockData;
        }


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

        function serializeModels() {
            $modelsInput.val(JSON.stringify(modelsData));
        }

        function renderBlock(data) {
            var template = $.trim($('#tmpl-cp-block').html());
            var $block = $(template);

            // Set values
            $block.find('.cp-block-type').val(data.type || 'fixed');
            $block.find('.cp-b-position-type').val(data.position_type || 'sequential');
            $block.find('.cp-b-pos-x').val(data.pos_x || '');
            $block.find('.cp-b-pos-y').val(data.pos_y || '');
            $block.find('.cp-b-width').val(data.width || '');

            $block.find('.cp-b-label').val(data.label || '');
            $block.find('.cp-b-desc').val(data.desc || '');
            $block.find('.cp-b-field-type').val(data.field_type || 'text');
            $block.find('.cp-b-max').val(data.max_chars || '');
            $block.find('.cp-b-base-text').val(data.base_text || '');

            var type = data.type || 'fixed';
            if (type === 'input') {
                $block.find('.cp-block-input-settings').show();
                $block.find('.cp-block-fixed-settings').hide();
            } else {
                $block.find('.cp-block-input-settings').hide();
                $block.find('.cp-block-fixed-settings').show();
            }

            var posType = data.position_type || 'sequential';
            if (posType === 'absolute') {
                $block.find('.cp-block-absolute-settings').show();
            } else {
                $block.find('.cp-block-absolute-settings').hide();
            }

            $blocksContainer.append($block);
        }

        function renderVariable($tbody, data) {
            var template = $.trim($('#tmpl-cp-variable').html());
            var $tr = $(template);

            $tr.find('.cp-v-tag').val(data.tag || '');
            $tr.find('.cp-v-label').val(data.label || '');
            $tr.find('.cp-v-desc').val(data.desc || '');
            $tr.find('.cp-v-type').val(data.type || 'text');
            $tr.find('.cp-v-max').val(data.max_chars || '');

            $tbody.append($tr);
        }

        function updateBlockIndices() {
            $blocksContainer.find('.cp-block-item').each(function (index) {
                $(this).find('.block-index-display').text(index + 1);
            });
        }
    }

});
