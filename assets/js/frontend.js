jQuery(document).ready(function ($) {
    // Check if a central preview container exists (from [cp_preview] shortcode)
    var $centralContainer = $('#cp-preview-container');
    var $individualContainers = $('.cp-preview-container');
    if ($centralContainer.length > 0 && $individualContainers.length > 0) {
        // Clear placeholder in central container
        $centralContainer.empty();
        
        // Add class to form indicating we have a central preview container
        $('.cp-personalization-form').addClass('cp-has-central-preview');
        
        // Move all individual containers to the central container
        $individualContainers.each(function () {
            $centralContainer.append($(this));
            // Reset custom inline margins for central stacking
            $(this).css('margin-top', '0').css('margin-bottom', '25px');
        });
    }

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

        // Try to find the template-specific container, else fallback to the central container
        var $container = $('#cp-preview-container-' + index);
        if (!$container.length) {
            $container = $('#cp-preview-container');
            if (!$container.length) {
                $container = $('<div id="cp-preview-container" style="margin-top: 20px; border: 2px dashed #ccc; padding: 20px; text-align: center; background: #f9f9f9; min-height: 200px; display: flex; align-items: center; justify-content: center;"></div>');
                $('.cp-personalization-form').after($container);
            }
        }

        var $overlayBtn = $container.find('.cp-preview-overlay-btn');
        var originalOverlayText = $overlayBtn.length ? $overlayBtn.text() : '';
        if ($overlayBtn.length) {
            $overlayBtn.text('Cargando...').prop('disabled', true);
        }

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
                if ($overlayBtn.length) {
                    $overlayBtn.text(originalOverlayText).prop('disabled', false);
                }
                if (response.success) {
                    if (response.data.type === 'pdf') {
                        // Render PDF using PDF.js
                        renderPDF(response.data.preview_url, $container, index);
                    } else {
                        // Fallback for image
                        var html = '<img src="' + response.data.preview_url + '" style="width:100%; border:1px solid #ccc;">';
                        $container.html(html).addClass('cp-preview-active');
                    }
                } else {
                    $container.html('<div style="color: #ef4444; padding: 20px; font-weight: 500;">Error: ' + response.data.message + '</div>').addClass('cp-preview-active');
                }
            },
            error: function () {
                $btn.text(originalText).prop('disabled', false);
                if ($overlayBtn.length) {
                    $overlayBtn.text(originalOverlayText).prop('disabled', false);
                }
                $container.html('<div style="color: #ef4444; padding: 20px; font-weight: 500;">Error de conexión.</div>').addClass('cp-preview-active');
            }
        });
    }

    // Trigger on button click or clicking the placeholder container
    $(document).on('click', '.cp-preview-btn, .cp-preview-container', function (e) {
        // Prevent event bubbling if clicking elements inside container (like the active canvas)
        if ($(e.target).closest('canvas').length) {
            return;
        }

        var index;
        if ($(this).hasClass('cp-preview-container')) {
            if ($(this).hasClass('cp-preview-active')) {
                return; // Do nothing if it's already showing the PDF
            }
            index = $(this).data('index');
        } else {
            // It is the button
            index = $(this).data('index');
        }

        if (index !== undefined) {
            generatePreview(index);
        }
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

        // Update preview automatically if we are on the form page and the PDF has already been generated once
        var $container = $('#cp-preview-container-' + tIndex);
        if ($container.length && $container.hasClass('cp-preview-active')) {
            generatePreview(tIndex);
        }
    });

    // We no longer trigger generatePreview automatically on page load to save resources
    // and make the initial page load instant. The static background is displayed instead.

    function renderPDF(url, $container, index) {
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
                var canvasId = 'cp-pdf-canvas-' + index;
                var canvas = document.createElement('canvas');
                canvas.id = canvasId;

                // Remove any old canvas/controls first
                $container.find('canvas, .cp-zoom-controls').remove();
                
                // Append the canvas absolutely inside the container
                $container.append(canvas);

                // Add zoom controls
                var zoomHtml = '<div class="cp-zoom-controls">' +
                    '<button type="button" class="cp-zoom-btn cp-zoom-out" data-index="' + index + '" title="Alejar">−</button>' +
                    '<button type="button" class="cp-zoom-btn cp-zoom-reset" data-index="' + index + '" title="Restablecer">↺</button>' +
                    '<button type="button" class="cp-zoom-btn cp-zoom-in" data-index="' + index + '" title="Acercar">+</button>' +
                    '</div>';
                $container.append(zoomHtml);

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
                    // Add the active class to trigger smooth CSS transition
                    $container.addClass('cp-preview-active');
                });
            });
        }, function (reason) {
            // PDF loading error
            console.error(reason);
            $container.html('Error loading PDF preview.');
        });
    }

    // Zoom control state
    var cpZoomLevels = {};

    $(document).on('click', '.cp-zoom-btn', function (e) {
        e.preventDefault();
        e.stopPropagation(); // Prevent triggering other clicks on container
        
        console.log('Cartas: Zoom button clicked:', this.className);
        
        var $container = $(this).closest('.cp-preview-container');
        if (!$container.length) {
            console.error('Cartas Zoom: Container not found');
            return;
        }
        
        var index = $container.attr('data-index');
        if (index === undefined) {
            index = $container.data('index');
        }
        
        console.log('Cartas Zoom: Index:', index);
        
        var $canvas = $container.find('canvas');
        if (!$canvas.length) {
            console.error('Cartas Zoom: Canvas not found inside container');
            return;
        }
        
        if (cpZoomLevels[index] === undefined) {
            cpZoomLevels[index] = 1.0;
        }
        
        var oldZoom = cpZoomLevels[index];
        
        if ($(this).hasClass('cp-zoom-in')) {
            cpZoomLevels[index] = Math.min(cpZoomLevels[index] + 0.25, 2.5);
        } else if ($(this).hasClass('cp-zoom-out')) {
            cpZoomLevels[index] = Math.max(cpZoomLevels[index] - 0.25, 1.0);
        } else if ($(this).hasClass('cp-zoom-reset')) {
            cpZoomLevels[index] = 1.0;
        }
        
        console.log('Cartas Zoom: Level changed from', oldZoom, 'to', cpZoomLevels[index]);
        
        var targetWidth = (cpZoomLevels[index] * 100) + '%';
        
        if (cpZoomLevels[index] === 1.0) {
            $container.removeClass('cp-zoomed');
            $canvas.css({
                'width': '',
                'height': ''
            });
            $container.css('overflow', 'hidden');
        } else {
            $container.addClass('cp-zoomed');
            $container.css('overflow', 'auto');
            $canvas.css({
                'width': targetWidth,
                'height': 'auto'
            });
        }
    });
});
