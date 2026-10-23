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

        $('#order_status').on('change', updateDraftNotice);

        // Open modal (from toolbar button)
        $('#wooconfig-btn-open-add-group').on('click', function(e) {
            e.preventDefault();
            resetModal();
            updateDraftNotice();
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

        // ─── Lightbox de importación (pegar / subir planilla) ───
        $('#wooconfig-open-import-btn').on('click', function(e) {
            e.preventDefault();
            openImportLightbox();
        });

        $('#wooconfig-import-close-btn, #wooconfig-import-cancel-btn').on('click', function(e) {
            e.preventDefault();
            closeImportLightbox();
        });

        // Cerrar con clic en el fondo o Escape
        $('#wooconfig-import-backdrop').on('click', function(e) {
            if (e.target === this) { closeImportLightbox(); }
        });
        $(document).on('keydown.wooconfigImport', function(e) {
            if (e.key === 'Escape' && $('#wooconfig-import-backdrop').is(':visible')) {
                closeImportLightbox();
            }
        });

        // ─── Importar: parsear texto pegado ───
        $('#wooconfig-paste-parse-btn').on('click', function(e) {
            e.preventDefault();
            var rawText = $('#wooconfig-paste-area').val().trim();
            if (!rawText) {
                alert('Pega al menos una fila con datos o sube un archivo.');
                return;
            }
            runImport(rawText, null);
        });

        // ─── Subir archivo (Excel .xlsx / CSV / TSV) ───
        $('#wooconfig-paste-file').on('change', function() {
            var input = this;
            var file  = input.files && input.files[0];
            if (!file) { return; }

            var $result = $('#wooconfig-paste-result');
            $result.css('color', '#64748b').text('Leyendo ' + file.name + '…').show();

            WooConfigImport.fileToText(file).then(function(text) {
                if (!text || !text.trim()) {
                    throw new Error('el archivo no tiene filas legibles');
                }
                $('#wooconfig-paste-area').val(text);
                runImport(text, null);
            }).catch(function(err) {
                $result.hide();
                alert('No se pudo leer el archivo: ' + (err && err.message ? err.message : 'error desconocido') +
                      '\n\nSi es un Excel antiguo (.xls) o protegido, guárdalo como .xlsx o .csv y vuelve a intentar.');
            }).then(function() {
                input.value = '';
            });
        });

        // ─── Reaplicar mapeo de columnas elegido a mano ───
        $('#wooconfig-map-apply-btn').on('click', function(e) {
            e.preventDefault();
            var rawText = $('#wooconfig-paste-area').val().trim();
            if (!rawText) {
                alert('Primero pega las filas o sube el archivo.');
                return;
            }
            runImport(rawText, readMappingUI());
        });

        // ─── Generar filas correlativas ───
        $('#wooconfig-batch-generate-btn').on('click', function(e) {
            e.preventDefault();
            var qty = parseInt($('#wooconfig-batch-qty').val(), 10) || 10;
            if (qty < 1 || qty > 100) {
                alert('Ingresa una cantidad entre 1 y 100.');
                return;
            }
            generateBatchRows(qty, $('#wooconfig-quick-default-size').val() || 'M');
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

        // ─── Lightbox de importación ───
        // (los botones se enlazan arriba, junto al parseo)

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
            var orderId    = currentOrderId();
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
            var orderId      = currentOrderId();
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
                    payment_mode: paymentMode,
                    order_status: $('#order_status').val() || ''
                },
                success: function(res) {
                    $btn.prop('disabled', false).text('Guardar y Añadir al Pedido');
                    if (!res.success) {
                        alert(res.data && res.data.message ? res.data.message : wooconfig_admin.i18n.error);
                        return;
                    }

                    var msg = res.data && res.data.message ? res.data.message : wooconfig_admin.i18n.saved;

                    // El pedido venía de la pantalla "Añadir pedido" (auto-draft). Recargar esa
                    // pantalla crea OTRO borrador y las prendas quedan huérfanas: se navega al
                    // pedido real que acaba de guardarse.
                    if (res.data && res.data.order_was_draft && res.data.order_edit_url) {
                        alert(msg);
                        window.location.href = res.data.order_edit_url;
                        return;
                    }

                    alert(msg);
                    window.location.reload();
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

    /**
     * La tabla es la vista principal: ya no hay pestañas. Se mantiene la función
     * por compatibilidad con llamadas existentes.
     */
    function switchTab() {
        $('#wooconfig-modal-table-wrapper').show();
    }

    /* ═══════════════════════════════════════════════════
       PASTE PARSER (Excel / WhatsApp / CSV / TSV)
       ═══════════════════════════════════════════════════ */

    /* ═══════════════════════════════════════════════════
       IMPORTACIÓN DE PLANILLAS (parseo + mapeo + avisos)
       El parser vive en admin-import.js (WooConfigImport).
       ═══════════════════════════════════════════════════ */

    function runImport(text, mapping) {
        if (!window.WooConfigImport) {
            alert('No se cargó el importador de planillas (admin-import.js). Recarga la página.');
            return;
        }

        var res = WooConfigImport.parseText(text, mapping);
        if (!res.rows.length) {
            alert('No se pudo interpretar ninguna fila. Revisa el formato o ajusta el mapeo de columnas.');
            return;
        }

        var append = $('#wooconfig-paste-append').is(':checked');
        if (!append) {
            clearModalTable();
        }

        var issues = [];
        for (var i = 0; i < res.rows.length; i++) {
            var row = res.rows[i];
            addModalPlayerRow(row.dorsal, row.name, row.size, row.adicionales);

            var $tr = $('#wooconfig-modal-players-table tbody tr').last();
            if (row.issues && row.issues.length) {
                issues.push({ n: i + 1, text: row.issues.join(', ') });
                $tr.css('background', '#fef3c7').attr('title', 'Revisar: ' + row.issues.join(', '));
            } else {
                $tr.removeAttr('title');
            }
        }

        updateTableCount();
        fillMappingUI(res.cols, mapping || res.mapping || null);
        renderImportSummary(res, issues);

        if (!issues.length) {
            // Todo limpio: se cierra el lightbox para dejar la tabla a la vista.
            closeImportLightbox();
        }
    }

    /** Id del pedido que se está editando (input oculto del modal). */
    function currentOrderId() {
        var id = parseInt($('#wooconfig-modal-order-id').val(), 10);
        if (id > 0) { return id; }
        var fallback = parseInt($('#wooconfig-btn-open-add-group').data('order-id'), 10);
        return fallback > 0 ? fallback : 0;
    }

    function openImportLightbox() {
        $('#wooconfig-import-backdrop').css('display', 'flex');
        $('#wooconfig-paste-warnings').hide();
        $('#wooconfig-paste-result').hide();
        setTimeout(function() { $('#wooconfig-paste-area').trigger('focus'); }, 50);
    }

    function closeImportLightbox() {
        $('#wooconfig-import-backdrop').hide();
    }

    function fillMappingUI(cols, mapping) {
        cols = Math.max(cols || 0, 1);
        mapping = mapping || {};

        var labels = ['Columna 1', 'Columna 2', 'Columna 3', 'Columna 4', 'Columna 5', 'Columna 6'];
        function opts(selected) {
            var html = '<option value="">(ninguna)</option>';
            for (var i = 0; i < cols; i++) {
                html += '<option value="' + i + '"' + (selected === i ? ' selected' : '') + '>' +
                        (labels[i] || ('Columna ' + (i + 1))) + '</option>';
            }
            return html;
        }

        $('#wooconfig-map-dorsal').html(opts(mapping.dorsal));
        $('#wooconfig-map-name').html(opts(mapping.name));
        $('#wooconfig-map-size').html(opts(mapping.size));
        $('#wooconfig-map-ads').html(opts(mapping.ads));
    }

    function readMappingUI() {
        function val(sel) {
            var v = $(sel).val();
            return (v === '' || v === null || typeof v === 'undefined') ? null : parseInt(v, 10);
        }
        return {
            dorsal: val('#wooconfig-map-dorsal'),
            name:   val('#wooconfig-map-name'),
            size:   val('#wooconfig-map-size'),
            ads:    val('#wooconfig-map-ads')
        };
    }

    function renderImportSummary(res, issues) {
        var $w = $('#wooconfig-paste-warnings');
        var $main = $('#wooconfig-main-summary');
        $('#wooconfig-paste-result').hide();

        if (!issues.length) {
            $main.css({ background: '#ecfdf5', border: '1px solid #10b981', color: '#065f46' })
                 .html('✓ <strong>' + res.rows.length + ' prenda(s)</strong> importadas desde la planilla. Revisa la tabla antes de guardar.')
                 .show();
        } else {
            $main.css({ background: '#fffbeb', border: '1px solid #f59e0b', color: '#92400e' })
                 .html('⚠ <strong>' + issues.length + ' de ' + res.rows.length + ' fila(s)</strong> requieren revisión (marcadas en amarillo).')
                 .show();
        }

        if (!issues.length) {
            $w.css({ background: '#ecfdf5', border: '1px solid #10b981', color: '#065f46' })
              .html('✓ <strong>' + res.rows.length + ' fila' + (res.rows.length !== 1 ? 's' : '') + '</strong> cargadas y listas para guardar.')
              .show();
            return;
        }

        var list = '';
        var max = Math.min(issues.length, 8);
        for (var i = 0; i < max; i++) {
            list += '<li>fila ' + issues[i].n + ': ' + issues[i].text + '</li>';
        }
        if (issues.length > max) {
            list += '<li>… y ' + (issues.length - max) + ' fila(s) más</li>';
        }

        $w.css({ background: '#fffbeb', border: '1px solid #f59e0b', color: '#92400e' })
          .html('<strong>⚠ ' + issues.length + ' de ' + res.rows.length + ' fila(s) necesitan revisión</strong>' +
                '<ul style="margin: 6px 0 0 18px;">' + list + '</ul>' +
                '<div style="margin-top: 6px; color: #475569;">Las filas en amarillo quedaron en la tabla: corrígelas ahí mismo, ' +
                'o ajusta el <strong>mapeo de columnas</strong> y pulsa «Reaplicar mapeo».</div>')
          .show();

        $('#wooconfig-map-panel').attr('open', 'open');
    }

    /* ═══════════════════════════════════════════════════
       BATCH ROW GENERATOR
       ═══════════════════════════════════════════════════ */

    function generateBatchRows(qty, size) {
        clearModalTable();
        for (var i = 1; i <= qty; i++) {
            addModalPlayerRow(String(i), '', size || 'M');
        }
        updateTableCount();
    }

    /* ═══════════════════════════════════════════════════
       MODAL HELPERS
       ═══════════════════════════════════════════════════ */

    /**
     * Muestra el aviso "este pedido todavía no está guardado" cuando se está creando
     * un pedido nuevo (auto-draft), para que el usuario sepa que se guardará solo.
     */
    function updateDraftNotice() {
        var $notice = $('#wooconfig-modal-draft-notice');
        if (!$notice.length) { return; }

        var ctx    = (window.wooconfig_admin && wooconfig_admin.current_order) || {};
        var isNew  = /[?&]action=new\b/.test(window.location.search) || ctx.is_draft === true;

        if (!isNew) {
            $notice.hide();
            return;
        }

        var $status = $('#order_status');
        var label   = $status.length ? $status.find('option:selected').text() : (ctx.status_label || '');
        $('#wooconfig-modal-draft-status').text(jQuery.trim(label) || 'Cotización');
        $notice.show();
    }

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
        closeImportLightbox();
        $('#wooconfig-paste-append').prop('checked', false);
        $('#wooconfig-paste-warnings').hide();
        $('#wooconfig-main-summary').hide();
        $('#wooconfig-paste-file').val('');
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

            // La talla SIEMPRE tiene valor (por defecto M): una fila cuenta solo si trae
            // dorsal o nombre. Antes las filas vacías se guardaban como prendas sin datos.
            if (dorsal || name) {
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
