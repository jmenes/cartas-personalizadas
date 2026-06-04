jQuery(document).ready(function ($) {
    function generatePreview(index) {
        var $block = $('.cp-template-block[data-index="' + index + '"]');
        if (!$block.length) return;

        var templateId = $block.data('template');
        var productId = $('#cp_product_id').val();
        var modelId = $block.find('.cp-model-selector:checked, input[type="hidden"].cp-model-selector').val() || 0;

        // Gather all inputs dynamically
        var blocksData = {};
        var variablesData = {};

        $block.find('.cp-dynamic-field:not(:disabled)').each(function () {
            var nameAttr = $(this).attr('name'); // e.g: cp_data[0][models][0][blocks][0] OR cp_data[0][models][0][variables][var_0]
            if (!nameAttr) return;

            var isModelVariable = nameAttr.match(/\[variables\]\[([^\]]+)\]/);
            if (isModelVariable) {
                variablesData[isModelVariable[1]] = $(this).val();
                return;
            }

            var bIndexMatch = nameAttr.match(/\[blocks\]\[(\d+)\]/);
            if (!bIndexMatch) return;
            var bIndex = bIndexMatch[1];

            var isVariable = nameAttr.match(/\[blocks\]\[\d+\]\[([^\]]+)\]/);

            if (isVariable) {
                var vTag = isVariable[1];
                if (typeof blocksData[bIndex] !== 'object') {
                    blocksData[bIndex] = {};
                }
                blocksData[bIndex][vTag] = $(this).val();
            } else {
                blocksData[bIndex] = $(this).val();
            }
        });

        // Convert obj to array to maintain sequential order strictly just in case
        var blocksArray = [];
        Object.keys(blocksData).sort().forEach(function (key) {
            blocksArray[key] = blocksData[key];
        });

        var $btn = $block.find('.cp-preview-btn');
        var originalText = $btn.text();
        $btn.text('Generando...').prop('disabled', true);

        // We use the central container for preview, but we could also append one inside the block.
        // For now, keeping the central shortcode container.
        var $container = $('#cp-preview-container');
        if (!$container.length) {
            // If shortcode not used, we can dynamically append a container
            $container = $('<div id="cp-preview-container" style="margin-top: 20px; border: 2px dashed #ccc; padding: 20px; text-align: center; background: #f9f9f9; min-height: 200px; display: flex; align-items: center; justify-content: center;"></div>');
            $('.cp-personalization-form').after($container);
        }

        $container.html('Cargando previsualización...').show();

        $.ajax({
            url: cp_ajax.url,
            type: 'POST',
            data: {
                action: 'cp_generate_preview',
                nonce: cp_ajax.nonce,
                blocks: blocksArray,
                variables: variablesData,
                product_id: productId,
                template_id: templateId,
                model_id: modelId
            },
            success: function (response) {
                $btn.text(originalText).prop('disabled', false);
                if (response.success) {
                    if (response.data.type === 'pdf') {
                        // Render PDF using PDF.js
                        renderPDF(response.data.preview_url, $container);
                    } else {
                        // Fallback for image
                        var html = '<img src="' + response.data.preview_url + '" style="width:100%; border:1px solid #ccc;">';
                        $container.html(html);
                    }
                } else {
                    $container.html('Error: ' + response.data.message);
                }
            },
            error: function () {
                $btn.text(originalText).prop('disabled', false);
                $container.html('Error de conexión.');
            }
        });
    }

    // Trigger on button click
    $(document).on('click', '.cp-preview-btn', function () {
        var index = $(this).data('index');
        generatePreview(index);
    });

    // Handle Model Selection change
    $(document).on('change', '.cp-model-selector', function () {
        var $block = $(this).closest('.cp-template-block');
        var tIndex = $(this).data('tindex');
        var mIdx = $(this).val();

        // Hide all model fields and disable their inputs to avoid validation errors
        $block.find('.cp-model-fields').hide();
        $block.find('.cp-model-fields .cp-dynamic-field').prop('disabled', true);

        // Show active model fields and enable inputs
        var $activeFields = $block.find('.cp-model-fields-' + tIndex + '-' + mIdx);
        $activeFields.show();
        $activeFields.find('.cp-dynamic-field').prop('disabled', false);

        // Update preview automatically if we are on the form page
        generatePreview(tIndex);
    });

    // Trigger on page load if there is at least one form
    if ($('.cp-template-block').length > 0) {
        var firstBlockIndex = $('.cp-template-block').first().data('index');
        if (firstBlockIndex !== undefined) {
            generatePreview(firstBlockIndex);
        }
    }

    function renderPDF(url, $container) {
        // Ensure pdfjsLib is available
        if (typeof pdfjsLib === 'undefined') {
            $container.html('Error: PDF library not loaded.');
            return;
        }

        pdfjsLib.GlobalWorkerOptions.workerSrc = cp_ajax.pdfWorkerUrl;

        var loadingTask = pdfjsLib.getDocument(url);
        loadingTask.promise.then(function (pdf) {
            // Fetch the first page
            pdf.getPage(1).then(function (page) {
                var scale = 1.5;
                var viewport = page.getViewport({ scale: scale });

                // Prepare canvas using PDF page dimensions
                var canvasId = 'cp-pdf-canvas';
                var canvas = document.createElement('canvas');
                canvas.id = canvasId;
                canvas.style.width = '100%';
                canvas.style.border = '1px solid #ccc';

                $container.html(canvas);

                var context = canvas.getContext('2d');
                canvas.height = viewport.height;
                canvas.width = viewport.width;

                // Render PDF page into canvas context
                var renderContext = {
                    canvasContext: context,
                    viewport: viewport
                };
                var renderTask = page.render(renderContext);
                renderTask.promise.then(function () {
                    console.log('Page rendered');
                });
            });
        }, function (reason) {
            // PDF loading error
            console.error(reason);
            $container.html('Error loading PDF preview.');
        });
    }
});
