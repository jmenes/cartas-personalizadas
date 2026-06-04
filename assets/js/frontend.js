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

        // Find all inputs across all models in this block
        var $allModelFields = $block.find('.cp-model-fields');
        var $allInputs = $allModelFields.find(':input');

        // Hide all, disable them, and remove required attribute to prevent browser validation errors
        $allModelFields.hide();
        $allInputs.prop('disabled', true).removeAttr('required');

        // Show active model fields, enable inputs, and restore required attribute
        var $activeFields = $block.find('.cp-model-fields-' + tIndex + '-' + mIdx);
        $activeFields.show();
        $activeFields.find(':input').prop('disabled', false).attr('required', 'required');

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

                // Remove any old canvas/controls/wrapper first
                $container.find('.cp-preview-scroll-wrapper, .cp-zoom-controls').remove();
                
                // Create scroll wrapper
                var $scrollWrapper = $('<div class="cp-preview-scroll-wrapper"></div>');
                
                // Append the canvas inside the wrapper
                $scrollWrapper.append(canvas);
                $container.append($scrollWrapper);

                // Add zoom controls directly to container so they remain floating
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
        
        var $scrollWrapper = $container.find('.cp-preview-scroll-wrapper');
        if (!$scrollWrapper.length) {
            console.error('Cartas Zoom: Scroll wrapper not found');
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
        
        // Calculate viewport and scroll metrics for center-anchored scaling
        var containerWidth = $scrollWrapper.width() || $container.width();
        var containerHeight = $scrollWrapper.height() || $container.height();
        
        var oldCanvasWidth = containerWidth * oldZoom;
        var oldCanvasHeight = containerHeight * oldZoom;
        
        var oldScrollLeft = $scrollWrapper.scrollLeft() || 0;
        var oldScrollTop = $scrollWrapper.scrollTop() || 0;
        
        var centerX = oldScrollLeft + containerWidth / 2;
        var centerY = oldScrollTop + containerHeight / 2;
        
        var ratioX = centerX / oldCanvasWidth;
        var ratioY = centerY / oldCanvasHeight;
        
        var targetWidth = (cpZoomLevels[index] * 100) + '%';
        
        if (cpZoomLevels[index] === 1.0) {
            $container.removeClass('cp-zoomed');
            $canvas.css({
                'width': '',
                'height': ''
            });
            $scrollWrapper.scrollLeft(0).scrollTop(0);
        } else {
            $container.addClass('cp-zoomed');
            $canvas.css({
                'width': targetWidth,
                'height': 'auto'
            });
            
            var newCanvasWidth = containerWidth * cpZoomLevels[index];
            var newCanvasHeight = containerHeight * cpZoomLevels[index];
            
            var newScrollLeft = (ratioX * newCanvasWidth) - (containerWidth / 2);
            var newScrollTop = (ratioY * newCanvasHeight) - (containerHeight / 2);
            
            $scrollWrapper.scrollLeft(newScrollLeft);
            $scrollWrapper.scrollTop(newScrollTop);
        }
    });

    // Panning (Drag-to-scroll) functionality
    var isDown = false;
    var startX, startY, scrollLeft, scrollTop;

    $(document).on('mousedown', '.cp-preview-scroll-wrapper', function (e) {
        var $container = $(this).closest('.cp-preview-container');
        if (!$container.hasClass('cp-zoomed')) return;
        
        isDown = true;
        $(this).addClass('cp-dragging');
        startX = e.pageX;
        startY = e.pageY;
        scrollLeft = $(this).scrollLeft();
        scrollTop = $(this).scrollTop();
    });

    $(document).on('mouseleave mouseup', '.cp-preview-scroll-wrapper', function () {
        isDown = false;
        $(this).removeClass('cp-dragging');
    });

    $(document).on('mousemove', '.cp-preview-scroll-wrapper', function (e) {
        if (!isDown) return;
        e.preventDefault();
        var walkX = (e.pageX - startX) * 1.2;
        var walkY = (e.pageY - startY) * 1.2;
        $(this).scrollLeft(scrollLeft - walkX);
        $(this).scrollTop(scrollTop - walkY);
    });

    // Touch support for panning on mobile devices
    $(document).on('touchstart', '.cp-preview-scroll-wrapper', function (e) {
        var $container = $(this).closest('.cp-preview-container');
        if (!$container.hasClass('cp-zoomed')) return;
        
        isDown = true;
        var touch = e.originalEvent.touches[0];
        startX = touch.pageX;
        startY = touch.pageY;
        scrollLeft = $(this).scrollLeft();
        scrollTop = $(this).scrollTop();
    });

    $(document).on('touchend touchcancel', '.cp-preview-scroll-wrapper', function () {
        isDown = false;
    });

    $(document).on('touchmove', '.cp-preview-scroll-wrapper', function (e) {
        if (!isDown) return;
        var touch = e.originalEvent.touches[0];
        var walkX = (touch.pageX - startX);
        var walkY = (touch.pageY - startY);
        $(this).scrollLeft(scrollLeft - walkX);
        $(this).scrollTop(scrollTop - walkY);
        e.preventDefault(); // Prevent page scrolling during preview pan
    });

    // Associate personalization inputs with the WooCommerce cart form to support page builders / Elementor
    // where the personalization form is rendered outside the actual WooCommerce cart form.
    function linkInputsToCartForm() {
        var $cartForm = $('form.cart');
        if ($cartForm.length) {
            var formId = $cartForm.attr('id');
            if (!formId) {
                formId = 'cp-cart-form';
                $cartForm.attr('id', formId);
            }
            // Link all inputs inside our form to the WooCommerce cart form
            $('.cp-personalization-form :input').attr('form', formId);
        }
    }

    // Run on load
    linkInputsToCartForm();

    // Initialize model selector states on load to ensure only the active model fields are enabled and required
    $('.cp-model-selector:checked, input[type="hidden"].cp-model-selector').trigger('change');

    // Fallback: Dynamically inject inputs on cart form submission if they are outside the form
    $(document).on('submit', 'form.cart', function () {
        var $form = $(this);
        $('.cp-personalization-form').find(':input:not(:disabled)').each(function () {
            if (!$.contains($form[0], this)) {
                // Skip unchecked radio buttons and checkboxes
                if ($(this).is('[type="radio"], [type="checkbox"]') && !$(this).is(':checked')) {
                    return;
                }

                var name = $(this).attr('name');
                if (name) {
                    // Remove any previously injected hidden input for this name to avoid duplicates
                    $form.find('input[type="hidden"][name="' + name + '"]').remove();
                    
                    var val = $(this).val();
                    $('<input>').attr({
                        type: 'hidden',
                        name: name,
                        value: val
                    }).appendTo($form);
                }
            }
        });
    });
});
