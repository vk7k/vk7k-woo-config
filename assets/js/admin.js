/**
 * WooConfig Admin Script - Enhanced Backend Management
 * Includes: Tab switching, Paste import, Batch generator, Quick mode, Desglosar
 */
(function($) {
    'use strict';

    var availableProducts = [];
    var currentFiles = [];

    $(document).ready(function() {
        initInlineRowEditing();
        initModalManagement();
        initDesglosarButtons();
    });

    /**
     * Inline row editing & deleting in order metabox
     */
    function initInlineRowEditing() {
        // Toggle inline edit mode
        $(document).on('click', '.wooconfig-btn-edit-row', function(e) {
            e.preventDefault();
            var $row = $(this).closest('.wooconfig-item-row');
            $row.find('.wooconfig-view-mode').hide();
            $row.find('.wooconfig-edit-mode').show();
            $row.find('.wooconfig-edit-dorsal').focus();
        });

        // Cancel inline edit
        $(document).on('click', '.wooconfig-btn-cancel-row', function(e) {
            e.preventDefault();
            var $row = $(this).closest('.wooconfig-item-row');
            $row.find('.wooconfig-edit-mode').hide();
            $row.find('.wooconfig-view-mode').show();
        });

        // Save inline edit
        $(document).on('click', '.wooconfig-btn-save-row', function(e) {
            e.preventDefault();
            var $btn    = $(this);
            var $row    = $btn.closest('.wooconfig-item-row');
            var itemId  = $row.data('item-id');
            var orderId = $row.data('order-id');
            var dorsal  = $row.find('.wooconfig-edit-dorsal').val();
            var name    = $row.find('.wooconfig-edit-name').val();
            var size    = $row.find('.wooconfig-edit-size').val();

            $btn.prop('disabled', true).text('Guardando...');

            $.ajax({
                url: wooconfig_admin.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'wooconfig_admin_update_item',
                    nonce: wooconfig_admin.nonce,
                    order_id: orderId,
                    item_id: itemId,
                    dorsal: dorsal,
                    name: name,
                    size: size
                },
                success: function(res) {
                    $btn.prop('disabled', false).text('💾 Guardar');
                    if (res.success) {
                        // Update view spans
                        $row.find('.wooconfig-edit-mode').hide();
                        $row.find('.wooconfig-view-mode').show();
                        $row.find('td:nth-child(3) .wooconfig-view-mode strong').text(dorsal || '-');
                        $row.find('td:nth-child(4) .wooconfig-view-mode strong').text((name || '-').toUpperCase());
                        $row.find('td:nth-child(5) .wooconfig-view-mode span').text(size || '-');
                    } else {
                        alert(res.data && res.data.message ? res.data.message : wooconfig_admin.i18n.error);
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('💾 Guardar');
                    alert(wooconfig_admin.i18n.error);
                }
            });
        });

        // Delete uniform row
        $(document).on('click', '.wooconfig-btn-delete-row', function(e) {
            e.preventDefault();
            if (!confirm(wooconfig_admin.i18n.confirm_delete)) {
                return;
            }

            var $row    = $(this).closest('.wooconfig-item-row');
            var itemId  = $row.data('item-id');
            var orderId = $row.data('order-id');

            $row.css('opacity', 0.5);

            $.ajax({
                url: wooconfig_admin.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'wooconfig_admin_delete_item',
                    nonce: wooconfig_admin.nonce,
                    order_id: orderId,
                    item_id: itemId
                },
                success: function(res) {
                    if (res.success) {
                        $row.fadeOut(300, function() {
                            $(this).remove();
                        });
                    } else {
                        $row.css('opacity', 1);
                        alert(res.data && res.data.message ? res.data.message : wooconfig_admin.i18n.error);
                    }
                },
                error: function() {
                    $row.css('opacity', 1);
                    alert(wooconfig_admin.i18n.error);
                }
            });
        });

        // Quick add single row to existing group
        $(document).on('click', '.wooconfig-btn-add-item-row', function(e) {
            e.preventDefault();
            var $btn     = $(this);
            var groupId  = $btn.data('group-id');
            var orderId  = $btn.data('order-id');
            var dorsal   = prompt('Número de dorsal (opcional):', '');
            if (dorsal === null) return;
            var name     = prompt('Nombre / Apellido / Alias en camiseta (opcional):', '');
            if (name === null) return;
            var size     = prompt('Talla (ej: M, L, 12, XL):', 'M');
            if (size === null || !size.trim()) return;

            $btn.prop('disabled', true).text('Añadiendo...');

            $.ajax({
                url: wooconfig_admin.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'wooconfig_admin_add_item',
                    nonce: wooconfig_admin.nonce,
                    order_id: orderId,
                    group_id: groupId,
                    dorsal: dorsal,
                    name: name,
                    size: size
                },
                success: function(res) {
                    $btn.prop('disabled', false).text('➕ Añadir Prenda');
                    if (res.success) {
                        window.location.reload();
                    } else {
                        alert(res.data && res.data.message ? res.data.message : wooconfig_admin.i18n.error);
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('➕ Añadir Prenda');
                    alert(wooconfig_admin.i18n.error);
                }
            });
        });

        // Group workshop preview live trigger
        $(document).on('click', '.wooconfig-btn-preview-group', function(e) {
            e.preventDefault();
            var $card      = $(this).closest('.wooconfig-group-card');
            var orderId    = $(this).data('order-id');
            var groupTitle = $(this).data('group-title') || 'Equipo';
            var designDesc = $card.find('p').first().text().trim();

            // Collect items from card table
            var items = [];
            $card.find('.wooconfig-item-row').each(function() {
                var $r = $(this);
                items.push({
                    product_name: $r.find('td:nth-child(2) strong').text().trim(),
                    dorsal: $r.find('.wooconfig-edit-dorsal').val() || $r.find('td:nth-child(3) strong').text().trim(),
                    name: $r.find('.wooconfig-edit-name').val() || $r.find('td:nth-child(4) strong').text().trim(),
                    size: $r.find('.wooconfig-edit-size').val() || $r.find('td:nth-child(5) span').text().trim(),
                    adicionales: $r.find('td:nth-child(6) span').text().trim()
                });
            });

            // Collect files
            var files = [];
            $card.find('img').each(function() {
                files.push({
                    file_url: $(this).attr('src'),
                    original_name: 'Imagen adjunta',
                    is_image: true
                });
            });

            var $btn = $(this);
            $btn.prop('disabled', true).text('Generando...');

            $.ajax({
                url: wooconfig_admin.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'wooconfig_admin_preview_sheet',
                    nonce: wooconfig_admin.nonce,
                    order_id: orderId,
                    group_title: groupTitle,
                    design_desc: designDesc,
                    files: JSON.stringify(files),
                    items: JSON.stringify(items)
                },
                success: function(res) {
                    $btn.prop('disabled', false).text('👁 Previsualizar Ficha');
                    if (res.success && res.data.preview_url) {
                        window.open(res.data.preview_url, '_blank');
                    } else {
                        alert(res.data && res.data.message ? res.data.message : wooconfig_admin.i18n.error);
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('👁 Previsualizar Ficha');
                    alert(wooconfig_admin.i18n.error);
                }
            });
        });
    }

    /**
     * Desglosar Buttons - Convert standard WooCommerce items
     */
    function initDesglosarButtons() {
        $(document).on('click', '.wooconfig-btn-desglosar', function(e) {
            e.preventDefault();
            var $btn      = $(this);
            var orderId   = $btn.data('order-id');
            var itemId    = $btn.data('item-id');
            var productId = $btn.data('product-id');
            var productName = $btn.data('product-name');
            var quantity  = parseInt($btn.data('quantity'), 10) || 5;

            // Open modal pre-populated for desglosar
            openModalForDesglosar(orderId, itemId, productId, productName, quantity);
        });

        // "Create from scratch" button
        $(document).on('click', '#wooconfig-btn-create-from-scratch', function(e) {
            e.preventDefault();
            var $backdrop = $('#wooconfig-admin-modal-backdrop');
            resetModal();
            $backdrop.css('display', 'flex');
            loadConfigurableProducts();
        });
    }

    function openModalForDesglosar(orderId, itemId, productId, productName, quantity) {
        var $backdrop = $('#wooconfig-admin-modal-backdrop');
        resetModal();

        // Set convert item ID
        $('#wooconfig-modal-convert-item-id').val(itemId);

        // Update title
        $('#wooconfig-modal-title').html('⚡ Configurar Tallas y Dorsales (' + quantity + ' prendas) - ' + productName);

        // Set group title
        $('#wooconfig-modal-group-title').val('Equipo: ' + productName);

        // Open modal
        $backdrop.css('display', 'flex');

        // Load products and pre-select
        loadConfigurableProducts(function() {
            $('#wooconfig-modal-product-select').val(productId).trigger('change');

            // Switch to manual tab and generate batch rows
            switchTab('manual');
            $('#wooconfig-batch-qty').val(quantity);
            generateBatchRows(quantity);
        });
    }

    /**
     * Modal Management for Adding Team/Items to Order
     */
    function initModalManagement() {
        var $backdrop = $('#wooconfig-admin-modal-backdrop');
        if (!$backdrop.length) return;

        // Open modal (from toolbar button)
        $('#wooconfig-btn-open-add-group').on('click', function(e) {
            e.preventDefault();
            resetModal();
            $backdrop.css('display', 'flex');
            loadConfigurableProducts();
            if ($('#wooconfig-modal-players-table tbody tr').length === 0) {
                for (var i = 0; i < 5; i++) {
                    addModalPlayerRow();
                }
            }
        });

        // Close modal
        $('#wooconfig-modal-close-btn, #wooconfig-modal-cancel-btn').on('click', function(e) {
            e.preventDefault();
            $backdrop.hide();
        });

        // ─── Tab Switching ───
        $(document).on('click', '.wooconfig-input-tab', function(e) {
            e.preventDefault();
            var tab = $(this).data('tab');
            switchTab(tab);
        });

        // ─── Tab A: Paste Parse ───
        $('#wooconfig-paste-parse-btn').on('click', function(e) {
            e.preventDefault();
            var rawText = $('#wooconfig-paste-area').val().trim();
            if (!rawText) {
                alert('Pega al menos una fila con datos.');
                return;
            }
            var parsed = parsePastedText(rawText);
            if (parsed.length === 0) {
                alert('No se pudo interpretar ninguna fila. Revisa el formato.');
                return;
            }

            // Clear existing rows and populate
            clearModalTable();
            var prodId = parseInt($('#wooconfig-modal-product-select').val(), 10);
            var prod   = getSelectedProduct(prodId);

            for (var i = 0; i < parsed.length; i++) {
                addModalPlayerRow(parsed[i].dorsal, parsed[i].name, parsed[i].size, parsed[i].adicionales);
            }

            var $result = $('#wooconfig-paste-result');
            $result.text('✓ ' + parsed.length + ' filas cargadas correctamente').show();
            setTimeout(function() { $result.fadeOut(); }, 4000);
            updateTableCount();
        });

        // ─── Tab B: Batch Generator ───
        $('#wooconfig-batch-generate-btn').on('click', function(e) {
            e.preventDefault();
            var qty = parseInt($('#wooconfig-batch-qty').val(), 10) || 10;
            if (qty < 1 || qty > 100) {
                alert('Ingresa una cantidad entre 1 y 100.');
                return;
            }
            generateBatchRows(qty);
        });

        // Add 1 row
        $('#wooconfig-modal-add-row-btn').on('click', function(e) {
            e.preventDefault();
            addModalPlayerRow();
            updateTableCount();
        });

        // Add 5 rows
        $('#wooconfig-modal-add-5rows-btn').on('click', function(e) {
            e.preventDefault();
            for (var i = 0; i < 5; i++) {
                addModalPlayerRow();
            }
            updateTableCount();
        });

        // Clear table
        $('#wooconfig-modal-clear-table-btn').on('click', function(e) {
            e.preventDefault();
            if (confirm('¿Limpiar toda la tabla? Esta acción no se puede deshacer.')) {
                clearModalTable();
                updateTableCount();
            }
        });

        // ─── Tab C: Quick Mode ───
        $('#wooconfig-quick-generate-btn').on('click', function(e) {
            e.preventDefault();
            var qty = parseInt($('#wooconfig-quick-qty').val(), 10) || 10;
            var defaultSize = $('#wooconfig-quick-default-size').val() || 'M';
            if (qty < 1 || qty > 100) {
                alert('Ingresa una cantidad entre 1 y 100.');
                return;
            }
            clearModalTable();
            for (var i = 1; i <= qty; i++) {
                addModalPlayerRow(String(i), '', defaultSize);
            }
            updateTableCount();

            // Show feedback
            alert('✓ ' + qty + ' prendas preparadas con talla ' + defaultSize + '. Recuerda adjuntar la planilla/foto en la sección de Archivos (paso 2).');
        });

        // Delete row in modal
        $(document).on('click', '.wooconfig-modal-remove-row-btn', function(e) {
            e.preventDefault();
            $(this).closest('tr').remove();
            renumberModalRows();
            updateTableCount();
        });

        // Product selection changed
        $('#wooconfig-modal-product-select').on('change', function() {
            var selectedId = parseInt($(this).val(), 10);
            var prod = getSelectedProduct(selectedId);
            if (prod) {
                if (!$('#wooconfig-modal-group-title').val()) {
                    $('#wooconfig-modal-group-title').val('Equipo: ' + prod.name);
                }
                updateModalRowsProductOptions(prod);
            }
        });

        // Media Library file uploader
        $('#wooconfig-modal-upload-files-btn').on('click', function(e) {
            e.preventDefault();
            var frame = wp.media({
                title: 'Seleccionar o Subir Archivos para el Taller',
                button: { text: 'Adjuntar a Confección' },
                multiple: true
            });

            frame.on('select', function() {
                var selection = frame.state().get('selection');
                selection.map(function(attachment) {
                    attachment = attachment.toJSON();
                    currentFiles.push({
                        file_url: attachment.url,
                        original_name: attachment.filename || attachment.title || 'Archivo',
                        is_image: attachment.type === 'image'
                    });
                });
                renderModalFiles();
            });

            frame.open();
        });

        // Delete uploaded file item
        $(document).on('click', '.wooconfig-modal-delete-file', function(e) {
            e.preventDefault();
            var idx = $(this).data('file-index');
            currentFiles.splice(idx, 1);
            renderModalFiles();
        });

        // Preview Draft Sheet from Modal
        $('#wooconfig-modal-preview-btn').on('click', function(e) {
            e.preventDefault();
            var orderId    = $('#wooconfig-btn-open-add-group').data('order-id') || $('#wooconfig-btn-create-from-scratch').data('order-id');
            var prodId     = parseInt($('#wooconfig-modal-product-select').val(), 10);
            var groupTitle = $('#wooconfig-modal-group-title').val() || 'Vista Previa';
            var designDesc = $('#wooconfig-modal-design-notes').val();
            var items      = collectModalPlayers();

            if (items.length === 0) {
                alert('Debes ingresar al menos 1 prenda para previsualizar.');
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true).text('Cargando...');

            $.ajax({
                url: wooconfig_admin.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'wooconfig_admin_preview_sheet',
                    nonce: wooconfig_admin.nonce,
                    order_id: orderId,
                    group_title: groupTitle,
                    design_desc: designDesc,
                    files: JSON.stringify(currentFiles),
                    items: JSON.stringify(items)
                },
                success: function(res) {
                    $btn.prop('disabled', false).text('👁 Previsualizar Ficha de Taller (Borrador)');
                    if (res.success && res.data.preview_url) {
                        window.open(res.data.preview_url, '_blank');
                    } else {
                        alert(res.data && res.data.message ? res.data.message : wooconfig_admin.i18n.error);
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('👁 Previsualizar Ficha de Taller (Borrador)');
                    alert(wooconfig_admin.i18n.error);
                }
            });
        });

        // Save Group and Uniforms to Order
        $('#wooconfig-modal-save-btn').on('click', function(e) {
            e.preventDefault();
            var orderId      = $('#wooconfig-btn-open-add-group').data('order-id') || $('#wooconfig-btn-create-from-scratch').data('order-id');
            var prodId       = parseInt($('#wooconfig-modal-product-select').val(), 10);
            var groupTitle   = $('#wooconfig-modal-group-title').val();
            var designNotes  = $('#wooconfig-modal-design-notes').val();
            var sponsorNotes = $('#wooconfig-modal-sponsor-notes').val();
            var items        = collectModalPlayers();
            var convertItemId = parseInt($('#wooconfig-modal-convert-item-id').val(), 10) || 0;
            var paymentMode  = $('input[name="wooconfig_modal_payment_mode"]:checked').val() || 'full';

            if (!prodId) {
                alert('Por favor selecciona un producto configurable.');
                return;
            }
            if (items.length === 0) {
                alert('Debes añadir al menos 1 prenda a la lista con su talla.');
                return;
            }

            var $btn = $(this);
            $btn.prop('disabled', true).text('Guardando en Pedido...');

            $.ajax({
                url: wooconfig_admin.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'wooconfig_admin_save_group',
                    nonce: wooconfig_admin.nonce,
                    order_id: orderId,
                    product_id: prodId,
                    group_title: groupTitle,
                    design_notes: designNotes,
                    sponsor_notes: sponsorNotes,
                    files: JSON.stringify(currentFiles),
                    items: JSON.stringify(items),
                    convert_item_id: convertItemId,
                    payment_mode: paymentMode
                },
                success: function(res) {
                    $btn.prop('disabled', false).text('Guardar y Añadir al Pedido');
                    if (res.success) {
                        alert(res.data && res.data.message ? res.data.message : wooconfig_admin.i18n.saved);
                        window.location.reload();
                    } else {
                        alert(res.data && res.data.message ? res.data.message : wooconfig_admin.i18n.error);
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('Guardar y Añadir al Pedido');
                    alert(wooconfig_admin.i18n.error);
                }
            });
        });
    }

    /* ═══════════════════════════════════════════════════
       TAB SWITCHING
       ═══════════════════════════════════════════════════ */

    function switchTab(tab) {
        // Update tab buttons
        $('.wooconfig-input-tab').removeClass('is-active');
        $('.wooconfig-input-tab[data-tab="' + tab + '"]').addClass('is-active');

        // Update panels
        $('.wooconfig-input-panel').removeClass('is-active');
        $('.wooconfig-input-panel[data-panel="' + tab + '"]').addClass('is-active');

        // Show/hide table wrapper based on tab
        if (tab === 'quick') {
            $('#wooconfig-modal-table-wrapper').hide();
        } else {
            $('#wooconfig-modal-table-wrapper').show();
        }
    }

    /* ═══════════════════════════════════════════════════
       PASTE PARSER (Excel / WhatsApp / CSV / TSV)
       ═══════════════════════════════════════════════════ */

    function parsePastedText(text) {
        var lines = text.split(/\n/);
        var results = [];

        // Known sizes for detection
        var knownSizes = ['XXXL', 'XXL', 'XL', 'L', 'M', 'S', 'XS', 'XXS',
                          '2', '4', '6', '8', '10', '12', '14', '16', '18', '20',
                          '2XL', '3XL', '4XL', '5XL'];
        var knownSizesLower = knownSizes.map(function(s) { return s.toLowerCase(); });

        // Known adicionales keywords
        var adicionalKeywords = {
            'manga larga': 'manga_larga',
            'manga_larga': 'manga_larga',
            'ml': 'manga_larga',
            'cuello polo': 'cuello_polo',
            'cuello piqué': 'cuello_polo',
            'cuello pique': 'cuello_polo',
            'piqué': 'cuello_polo',
            'pique': 'cuello_polo',
            'cuello_polo': 'cuello_polo'
        };

        for (var i = 0; i < lines.length; i++) {
            var line = lines[i].trim();
            if (!line) continue;

            // Skip header-like lines
            if (/^(#|dorsal|número|nombre|talla|size|jugador)/i.test(line)) continue;

            var columns = splitLine(line);
            if (columns.length === 0) continue;

            var parsed = classifyColumns(columns, knownSizesLower, adicionalKeywords);
            results.push(parsed);
        }

        return results;
    }

    function splitLine(line) {
        // Try tab first (Excel/Sheets copy)
        if (line.indexOf('\t') !== -1) {
            return line.split('\t').map(function(s) { return s.trim(); }).filter(Boolean);
        }
        // Try semicolon
        if (line.indexOf(';') !== -1) {
            return line.split(';').map(function(s) { return s.trim(); }).filter(Boolean);
        }
        // Try comma (but careful with names like "PÉREZ, JUAN")
        if (line.indexOf(',') !== -1 && (line.match(/,/g) || []).length >= 2) {
            return line.split(',').map(function(s) { return s.trim(); }).filter(Boolean);
        }
        // Try pipe
        if (line.indexOf('|') !== -1) {
            return line.split('|').map(function(s) { return s.trim(); }).filter(Boolean);
        }
        // Try dash with spaces
        if (line.indexOf(' - ') !== -1) {
            return line.split(' - ').map(function(s) { return s.trim(); }).filter(Boolean);
        }
        // Fallback: multiple spaces (2+)
        return line.split(/\s{2,}/).map(function(s) { return s.trim(); }).filter(Boolean);
    }

    function classifyColumns(columns, knownSizesLower, adicionalKeywords) {
        var result = { dorsal: '', name: '', size: 'M', adicionales: [] };
        var usedIndices = {};

        // Pass 1: Find size
        for (var i = 0; i < columns.length; i++) {
            var val = columns[i].trim();
            if (knownSizesLower.indexOf(val.toLowerCase()) !== -1) {
                result.size = val.toUpperCase();
                usedIndices[i] = true;
                break;
            }
        }

        // Pass 2: Find adicionales
        for (var j = 0; j < columns.length; j++) {
            if (usedIndices[j]) continue;
            var lower = columns[j].toLowerCase().trim();
            for (var keyword in adicionalKeywords) {
                if (lower.indexOf(keyword) !== -1) {
                    result.adicionales.push(adicionalKeywords[keyword]);
                    usedIndices[j] = true;
                    break;
                }
            }
        }

        // Pass 3: Find dorsal (first purely numeric value, typically 1-99)
        for (var k = 0; k < columns.length; k++) {
            if (usedIndices[k]) continue;
            var val2 = columns[k].trim();
            if (/^\d{1,3}$/.test(val2) && parseInt(val2, 10) <= 999) {
                result.dorsal = val2;
                usedIndices[k] = true;
                break;
            }
        }

        // Pass 4: Remaining text = name (first remaining non-empty)
        for (var m = 0; m < columns.length; m++) {
            if (usedIndices[m]) continue;
            var val3 = columns[m].trim();
            if (val3) {
                result.name = val3.toUpperCase();
                usedIndices[m] = true;
                break;
            }
        }

        return result;
    }

    /* ═══════════════════════════════════════════════════
       BATCH ROW GENERATOR
       ═══════════════════════════════════════════════════ */

    function generateBatchRows(qty) {
        clearModalTable();
        for (var i = 1; i <= qty; i++) {
            addModalPlayerRow(String(i), '', 'M');
        }
        updateTableCount();
    }

    /* ═══════════════════════════════════════════════════
       MODAL HELPERS
       ═══════════════════════════════════════════════════ */

    function resetModal() {
        $('#wooconfig-modal-convert-item-id').val('0');
        $('#wooconfig-modal-title').html('➕ Añadir Prendas / Uniformes al Pedido');
        $('#wooconfig-modal-group-title').val('');
        $('#wooconfig-modal-design-notes').val('');
        $('#wooconfig-modal-sponsor-notes').val('');
        $('#wooconfig-paste-area').val('');
        $('#wooconfig-paste-result').hide();
        $('input[name="wooconfig_modal_payment_mode"][value="full"]').prop('checked', true);
        currentFiles = [];
        renderModalFiles();
        clearModalTable();
        switchTab('paste');
    }

    function clearModalTable() {
        $('#wooconfig-modal-players-table tbody').empty();
    }

    function updateTableCount() {
        var count = $('#wooconfig-modal-players-table tbody tr').length;
        var $label = $('#wooconfig-table-count-label');
        if (count > 0) {
            $label.text('📊 ' + count + ' uniforme' + (count !== 1 ? 's' : '') + ' en la tabla');
        } else {
            $label.text('');
        }
    }

    function loadConfigurableProducts(callback) {
        if (availableProducts.length > 0) {
            if (callback) callback();
            return;
        }

        $.ajax({
            url: wooconfig_admin.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'wooconfig_admin_get_configurable_products',
                nonce: wooconfig_admin.nonce
            },
            success: function(res) {
                if (res.success && res.data.products) {
                    availableProducts = res.data.products;
                    var $select = $('#wooconfig-modal-product-select');
                    $select.empty();
                    $select.append('<option value="">-- Selecciona una prenda personalizable --</option>');
                    $.each(availableProducts, function(i, prod) {
                        $select.append('<option value="' + prod.id + '">' + prod.name + ' (' + prod.price_html + ')</option>');
                    });
                    if (availableProducts.length > 0 && !$select.val()) {
                        $select.val(availableProducts[0].id).trigger('change');
                    }
                }
                if (callback) callback();
            }
        });
    }

    function getSelectedProduct(prodId) {
        if (!prodId) return null;
        for (var i = 0; i < availableProducts.length; i++) {
            if (availableProducts[i].id === prodId) {
                return availableProducts[i];
            }
        }
        return null;
    }

    function addModalPlayerRow(dorsalVal, nameVal, sizeVal, adicionalesArr) {
        var $tbody   = $('#wooconfig-modal-players-table tbody');
        var rowCount = $tbody.find('tr').length + 1;
        var prodId   = parseInt($('#wooconfig-modal-product-select').val(), 10);
        var prod     = getSelectedProduct(prodId);

        dorsalVal = dorsalVal || '';
        nameVal   = nameVal || '';
        sizeVal   = sizeVal || 'M';
        adicionalesArr = adicionalesArr || [];

        var sizes = (prod && prod.sizes) ? prod.sizes : ['4', '6', '8', '10', '12', '14', '16', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'];
        var sizeOptions = '';
        $.each(sizes, function(i, s) {
            var selected = (s.toUpperCase() === sizeVal.toUpperCase()) ? ' selected' : '';
            sizeOptions += '<option value="' + s + '"' + selected + '>' + s + '</option>';
        });

        var adicionalesHtml = '';
        if (prod && prod.adicionales) {
            $.each(prod.adicionales, function(k, ad) {
                var checked = (adicionalesArr.indexOf(k) !== -1) ? ' checked' : '';
                adicionalesHtml += '<label style="display: inline-flex; align-items: center; gap: 4px; margin-right: 12px; font-size: 12px;">' +
                    '<input type="checkbox" class="wooconfig-modal-ad-check" value="' + k + '"' + checked + '> ' +
                    ad.label + ' (+$' + ad.price + ')' +
                    '</label>';
            });
        }
        if (!adicionalesHtml) {
            adicionalesHtml = '<span style="color: #646970; font-size: 12px;">(Sin adicionales)</span>';
        }

        var html = '<tr class="wooconfig-modal-row">' +
            '<td style="text-align: center; vertical-align: middle;"><strong>#' + rowCount + '</strong></td>' +
            '<td><input type="text" class="wooconfig-modal-dorsal" style="width: 100%;" placeholder="Ej: ' + rowCount + '" value="' + escAttr(dorsalVal) + '" tabindex="0" /></td>' +
            '<td><input type="text" class="wooconfig-modal-name" style="width: 100%; text-transform: uppercase;" placeholder="Ej: JUGADOR ' + rowCount + '" value="' + escAttr(nameVal) + '" tabindex="0" /></td>' +
            '<td><select class="wooconfig-modal-size" style="width: 100%;" tabindex="0">' + sizeOptions + '</select></td>' +
            '<td>' + adicionalesHtml + '</td>' +
            '<td style="text-align: center; vertical-align: middle;">' +
                '<button type="button" class="button-link button-link-delete wooconfig-modal-remove-row-btn" style="color: #d63638; text-decoration: none; font-size: 16px;">✕</button>' +
            '</td>' +
        '</tr>';

        $tbody.append(html);
    }

    function escAttr(str) {
        return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function renumberModalRows() {
        $('#wooconfig-modal-players-table tbody tr').each(function(idx) {
            $(this).find('td:first strong').text('#' + (idx + 1));
        });
    }

    function updateModalRowsProductOptions(prod) {
        if (!prod) return;
        var sizes = prod.sizes || ['S', 'M', 'L', 'XL'];
        var sizeOptions = '';
        $.each(sizes, function(i, s) {
            sizeOptions += '<option value="' + s + '">' + s + '</option>';
        });

        $('#wooconfig-modal-players-table tbody tr').each(function() {
            var currentVal = $(this).find('.wooconfig-modal-size').val();
            var $sizeSelect = $(this).find('.wooconfig-modal-size');
            $sizeSelect.html(sizeOptions);
            if (currentVal) {
                $sizeSelect.val(currentVal);
            }

            var adicionalesHtml = '';
            if (prod.adicionales) {
                $.each(prod.adicionales, function(k, ad) {
                    adicionalesHtml += '<label style="display: inline-flex; align-items: center; gap: 4px; margin-right: 12px; font-size: 12px;">' +
                        '<input type="checkbox" class="wooconfig-modal-ad-check" value="' + k + '"> ' +
                        ad.label + ' (+$' + ad.price + ')' +
                        '</label>';
                });
            }
            if (!adicionalesHtml) adicionalesHtml = '<span style="color: #646970; font-size: 12px;">(Sin adicionales)</span>';
            $(this).find('td:nth-child(5)').html(adicionalesHtml);
        });
    }

    function collectModalPlayers() {
        var items = [];
        var prodId = parseInt($('#wooconfig-modal-product-select').val(), 10);
        var prod   = getSelectedProduct(prodId);
        var prodName = prod ? prod.name : 'Uniforme';

        $('#wooconfig-modal-players-table tbody tr').each(function() {
            var $r = $(this);
            var dorsal = $r.find('.wooconfig-modal-dorsal').val().trim();
            var name   = $r.find('.wooconfig-modal-name').val().trim();
            var size   = $r.find('.wooconfig-modal-size').val();

            var ads = [];
            $r.find('.wooconfig-modal-ad-check:checked').each(function() {
                ads.push($(this).val());
            });

            if (size || dorsal || name) {
                items.push({
                    product_id: prodId,
                    product_name: prodName,
                    dorsal: dorsal,
                    name: name,
                    size: size || 'M',
                    adicionales: ads
                });
            }
        });
        return items;
    }

    function renderModalFiles() {
        var $container = $('#wooconfig-modal-files-list');
        $container.empty();

        $.each(currentFiles, function(idx, f) {
            var icon = f.is_image ? '<img src="' + f.file_url + '" style="width: 32px; height: 32px; object-fit: cover; border-radius: 3px;" />' : '<span style="font-size: 20px;">📄</span>';
            var html = '<div style="display: flex; align-items: center; gap: 8px; background: #fff; border: 1px solid #ccd0d4; padding: 4px 8px; border-radius: 4px;">' +
                icon +
                '<span style="font-size: 12px; max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">' + f.original_name + '</span>' +
                '<button type="button" class="button-link wooconfig-modal-delete-file" data-file-index="' + idx + '" style="color: #d63638; text-decoration: none;">✕</button>' +
            '</div>';
            $container.append(html);
        });
    }

})(jQuery);
