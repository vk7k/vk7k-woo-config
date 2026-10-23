/**
 * WooConfig Unified 4-Step Flow Frontend Script
 * - Step 1: On-page Quantities & Adicionales Steppers
 * - Step 2: Design Upload & Notes (Modal)
 * - Step 3: Sponsors Upload & Notes (Modal)
 * - Step 4: Players File Upload OR Interactive Table Sub-modal
 * - Popups for Instructions / Examples
 * - Full Validation on "Añadir y ver carrito"
 * - Live deposit calculation & Cart AJAX submission
 */

(function($) {
    'use strict';

    var WooConfigApp = {
        data: window.wooconfig_data || {},
        quantity: 1,
        paymentMode: 'deposit',
        adicionalesCounts: {},
        filesDesign: [],
        designNotes: '',
        filesSponsors: [],
        sponsorNotes: '',
        filesPlayers: [],
        players: [],
        useTable: false,
        currentStep: 1,
        isSubmitting: false,
        uploadToken: '',
        uploadTokenIsNew: false,
        nonceFresh: false,

        init: function() {
            if (!this.data.product_id) return;

            this.uploadToken = this.getUploadToken();

            var minU = parseInt(this.data.min_units, 10) || 1;
            this.quantity = Math.max(1, minU);
            this.paymentMode = (this.data.deposit_enabled) ? 'deposit' : 'full';
            this.initAdicionalesCounts();

            this.loadDraft();
            this.syncPlayersWithQuantity(this.quantity);

            this.bindEvents();
            this.renderOnPageValues();
            this.updateTotals();

            // The cached HTML carries an old nonce: refresh it in the background.
            this.refreshNonce();
        },

        /**
         * Fetch a nonce that is valid right now (cached pages ship an expired one).
         */
        refreshNonce: function(cb) {
            var self = this;

            if (this.nonceFresh) {
                if (cb) { cb(); }
                return;
            }

            jQuery.post(this.data.ajax_url, { action: 'wooconfig_refresh_nonce', _t: Date.now() })
                .done(function(res) {
                    if (res && res.success && res.data && res.data.nonce) {
                        self.data.nonce = res.data.nonce;
                        self.nonceFresh = true;
                    }
                })
                .always(function() {
                    if (cb) { cb(); }
                });
        },

        getDraftKey: function() {
            return 'wooconfig_unified_draft_' + this.data.product_id;
        },

        getUploadToken: function() {
            var match = document.cookie.match(/(?:^|;\s*)wooconfig_upload_token=([a-f0-9]{32,64})/i);
            if (match) {
                this.uploadTokenIsNew = false;
                return match[1].toLowerCase();
            }

            this.uploadTokenIsNew = true;
            var token = this.generateUploadToken();
            var secure = location.protocol === 'https:' ? '; Secure' : '';
            document.cookie = 'wooconfig_upload_token=' + token + '; path=/; max-age=' + (48 * 60 * 60) + '; SameSite=Lax' + secure;
            return token;
        },

        generateUploadToken: function() {
            try {
                if (window.crypto && window.crypto.getRandomValues) {
                    var bytes = new Uint8Array(16);
                    window.crypto.getRandomValues(bytes);
                    var out = '';
                    for (var i = 0; i < bytes.length; i++) {
                        out += ('0' + bytes[i].toString(16)).slice(-2);
                    }
                    return out;
                }
            } catch (e) {}

            var fallback = '';
            while (fallback.length < 32) {
                fallback += Math.floor(Math.random() * 0xffffffff).toString(16);
            }
            return fallback.slice(0, 32);
        },

        initAdicionalesCounts: function() {
            var self = this;
            self.adicionalesCounts = {};
            if (self.data.adicionales) {
                $.each(self.data.adicionales, function(key) {
                    self.adicionalesCounts[key] = 0;
                });
            }
        },

        saveDraft: function() {
            try {
                var draft = {
                    quantity: this.quantity,
                    paymentMode: this.paymentMode,
                    adicionalesCounts: this.adicionalesCounts,
                    filesDesign: this.filesDesign,
                    designNotes: $('#wooconfig-design-notes').val() || '',
                    filesSponsors: this.filesSponsors,
                    sponsorNotes: $('#wooconfig-sponsors-notes').val() || '',
                    filesPlayers: this.filesPlayers,
                    players: this.players,
                    useTable: this.useTable
                };
                localStorage.setItem(this.getDraftKey(), JSON.stringify(draft));
            } catch (e) {
                console.warn('WooConfig: Could not save draft', e);
            }
        },

        loadDraft: function() {
            try {
                var raw = localStorage.getItem(this.getDraftKey());
                if (raw) {
                    var draft = JSON.parse(raw);
                    if (draft) {
                        if (draft.quantity) this.quantity = Math.max(1, parseInt(draft.quantity, 10));
                        if (draft.paymentMode) this.paymentMode = draft.paymentMode;
                        if (draft.adicionalesCounts) this.adicionalesCounts = $.extend({}, this.adicionalesCounts, draft.adicionalesCounts);
                        if (!this.uploadTokenIsNew) {
                            if (Array.isArray(draft.filesDesign)) this.filesDesign = draft.filesDesign;
                            if (Array.isArray(draft.filesSponsors)) this.filesSponsors = draft.filesSponsors;
                            if (Array.isArray(draft.filesPlayers)) this.filesPlayers = draft.filesPlayers;
                        }
                        if (Array.isArray(draft.players)) this.players = draft.players;
                        if (typeof draft.useTable === 'boolean') this.useTable = draft.useTable;

                        if (draft.designNotes) $('#wooconfig-design-notes').val(draft.designNotes);
                        if (draft.sponsorNotes) $('#wooconfig-sponsors-notes').val(draft.sponsorNotes);

                        $('#wooconfig-main-qty-input').val(this.quantity);
                        $.each(this.adicionalesCounts, function(key, val) {
                            $('#wooconfig-ad-qty-' + key).val(val);
                        });

                        this.renderUploadedFilesList('design');
                        this.renderUploadedFilesList('sponsors');
                        this.renderUploadedFilesList('players');
                        this.updateTableSavedBadge();
                    }
                }
            } catch (e) {
                console.warn('WooConfig: Error loading draft', e);
            }
        },

        clearDraft: function() {
            try {
                localStorage.removeItem(this.getDraftKey());
            } catch (e) {}
        },

        syncPlayersWithQuantity: function(targetQty) {
            targetQty = Math.max(1, parseInt(targetQty, 10) || 1);
            while (this.players.length < targetQty) {
                this.players.push({
                    name: '',
                    size: '',
                    dorsal: '',
                    adicionales: []
                });
            }
            if (this.players.length > targetQty) {
                this.players = this.players.slice(0, targetQty);
            }
            this.saveDraft();
        },

        getEffectiveBasePrice: function(qty) {
            var regularPrice   = parseFloat(this.data.product_price) || 0;
            var isWsEnabled    = this.data.wholesale_enabled !== false;
            var wsPrice        = isWsEnabled ? (parseFloat(this.data.wholesale_price) || 0) : 0;
            var wsMinQty       = parseInt(this.data.wholesale_min_qty, 10) || 1;
            var wsCartQty      = parseInt(this.data.wholesale_cart_qty, 10) || 50;
            var wsOverride     = !!this.data.wholesale_override;

            if (!isWsEnabled || wsPrice <= 0) {
                return regularPrice;
            }

            // If local product threshold met OR global cart threshold met in widget
            if (qty >= wsMinQty || (wsOverride && qty >= wsCartQty)) {
                return wsPrice;
            }

            return regularPrice;
        },

        renderOnPageValues: function() {
            var self = this;
            var totalQty  = Math.max(1, parseInt(this.quantity, 10) || 1);
            var basePrice = this.getEffectiveBasePrice(totalQty);
            var mainSub   = totalQty * basePrice;
            $('#wooconfig-main-subtotal').html(this.formatPrice(mainSub));

            if (this.data.adicionales) {
                $.each(this.data.adicionales, function(key, ad) {
                    var count = parseInt(self.adicionalesCounts[key], 10) || 0;
                    var cost = (parseFloat(ad.price) || 0) * count;
                    $('#wooconfig-ad-subtotal-' + key).html(self.formatPrice(cost));
                });
            }
        },

        updateTotals: function() {
            var totalQty  = Math.max(1, parseInt(this.quantity, 10) || 1);
            var basePrice = this.getEffectiveBasePrice(totalQty);

            var extraTotal = 0;
            var totalAdsCount = 0;
            var self = this;

            if (this.data.adicionales) {
                $.each(this.data.adicionales, function(key, ad) {
                    var count = parseInt(self.adicionalesCounts[key], 10) || 0;
                    var unitCost = parseFloat(ad.price) || 0;
                    extraTotal += (count * unitCost);
                    totalAdsCount += count;
                });
            }

            var grandTotal = (basePrice * totalQty) + extraTotal;
            var isDepositEnabled = !!this.data.deposit_enabled;
            var depositPct = parseInt(this.data.deposit_percent, 10) || 50;
            var depositAmount = Math.round(grandTotal * (depositPct / 100));

            var formattedGrandTotal = this.formatPrice(grandTotal);
            var formattedDeposit    = this.formatPrice(depositAmount);

            // On-page summaries
            $('#wooconfig-onpage-total-amount, .wooconfig-modal-total-amount').html(formattedGrandTotal);
            $('#wooconfig-onpage-deposit-tag-amount, .wooconfig-live-deposit-amount').html(formattedDeposit);

            var unitsLabel = totalQty + (totalQty === 1 ? ' unidad' : ' unidades');
            var adsLabel   = totalAdsCount + (totalAdsCount === 1 ? ' adicional' : ' adicionales');

            $('#wooconfig-total-units-text, .wooconfig-live-units-label').text(unitsLabel);
            $('#wooconfig-total-ads-text, .wooconfig-live-ads-label').text(adsLabel);
        },

        formatPrice: function(num) {
            var symbol = this.data.currency_symbol || '$';
            var formatted = Math.round(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            return symbol + formatted;
        },

        // ================= NAVIGATION (IN-WIDGET 4-STEP FLOW) =================
        goToStep: function(step) {
            step = parseInt(step, 10) || 1;
            if (step < 1) step = 1;
            if (step > 4) step = 4;
            this.currentStep = step;

            $('.wooconfig-step-view').hide().removeClass('is-active');
            $('#wooconfig-step-' + step).css('display', 'flex').addClass('is-active');

            // Update Progress Bar Segments and Labels
            $('.wooconfig-prog-segment').removeClass('is-active is-completed');
            $('.wooconfig-stepper-label').removeClass('is-active is-completed');

            for (var s = 1; s <= 4; s++) {
                var $seg = $('.wooconfig-prog-segment[data-step="' + s + '"]');
                var $lbl = $('.wooconfig-stepper-label[data-step="' + s + '"]');
                if (s < step) {
                    $seg.addClass('is-completed');
                    $lbl.addClass('is-completed');
                } else if (s === step) {
                    $seg.addClass('is-active');
                    $lbl.addClass('is-active');
                }
            }

            $('#wooconfig-current-step-badge').text('Paso ' + step + ' de 4');

            // Hide validation banner on step change
            $('#wooconfig-validation-banner').slideUp(150);

            // Smooth scroll to top of widget if user had scrolled down
            var $widget = $('#wooconfig-onpage-widget');
            if ($widget.length) {
                var widgetTop = $widget.offset().top - 70;
                if ($(window).scrollTop() > widgetTop + 60 || $(window).scrollTop() < widgetTop - 350) {
                    $('html, body').animate({ scrollTop: widgetTop }, 200);
                }
            }
        },

        openModal: function(step) {
            step = step || 2;
            this.goToStep(step);
        },

        closeModal: function() {
            this.goToStep(1);
        },

        // ================= UPLOAD HANDLING =================
        uploadFiles: function(filesList, type) {
            var self = this;
            var formData = new FormData();
            formData.append('action', 'wooconfig_upload_file');
            formData.append('nonce', this.data.nonce);
            formData.append('upload_token', this.uploadToken);

            var maxMb = parseInt(this.data.max_file_size_mb, 10) || 20;
            var validFilesCount = 0;

            for (var i = 0; i < filesList.length; i++) {
                if (filesList[i].size > maxMb * 1024 * 1024) {
                    alert('El archivo ' + filesList[i].name + ' supera el tamaño máximo permitido de ' + maxMb + 'MB.');
                    continue;
                }
                formData.append('files[]', filesList[i]);
                validFilesCount++;
            }

            if (validFilesCount === 0) return;

            this.sendUpload(formData, type, 0);
        },

        /**
         * Send the upload; a 403 means the nonce is stale (cached page) -> refresh + retry once.
         */
        sendUpload: function(formData, type, attempt) {
            var self = this;

            var $progressWrapper = $('#wooconfig-' + type + '-progress');
            var $progressFill    = $('#wooconfig-' + type + '-progress-fill');
            var $progressText    = $('#wooconfig-' + type + '-progress-text');

            $progressWrapper.show();
            $progressFill.css('width', '10%');
            $progressText.text(this.data.i18n ? this.data.i18n.uploading : 'Subiendo archivo...');

            $.ajax({
                url: self.data.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    var xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(evt) {
                        if (evt.lengthComputable) {
                            var pct = Math.round((evt.loaded / evt.total) * 100);
                            $progressFill.css('width', pct + '%');
                        }
                    }, false);
                    return xhr;
                },
                success: function(res) {
                    $progressWrapper.hide();
                    if (res && res.success && res.data && res.data.files) {
                        if (type === 'design') {
                            self.filesDesign = self.filesDesign.concat(res.data.files);
                            self.renderUploadedFilesList('design');
                        } else if (type === 'sponsors') {
                            self.filesSponsors = self.filesSponsors.concat(res.data.files);
                            self.renderUploadedFilesList('sponsors');
                        } else if (type === 'players') {
                            // Max 1 for players file
                            self.filesPlayers = res.data.files.slice(0, 1);
                            self.renderUploadedFilesList('players');
                        }
                        self.saveDraft();
                    } else {
                        alert((res && res.data && res.data.message) ? res.data.message : 'Error al subir archivo.');
                    }
                },
                error: function(xhr) {
                    if (xhr && xhr.status === 403 && attempt < 1) {
                        // Stale nonce from a cached page: get a fresh one and retry.
                        self.nonceFresh = false;
                        self.refreshNonce(function() {
                            formData.set('nonce', self.data.nonce);
                            self.sendUpload(formData, type, attempt + 1);
                        });
                        return;
                    }
                    $progressWrapper.hide();
                    if (xhr && xhr.status === 413) {
                        alert('El archivo es demasiado grande para el servidor. Reduce su tamaño e inténtalo de nuevo.');
                        return;
                    }
                    alert('Error de conexión al subir el archivo.');
                }
            });
        },

        deleteFile: function(storedName, type) {
            var self = this;
            if (type === 'design') {
                self.filesDesign = self.filesDesign.filter(function(f) { return f.stored_name !== storedName; });
                self.renderUploadedFilesList('design');
            } else if (type === 'sponsors') {
                self.filesSponsors = self.filesSponsors.filter(function(f) { return f.stored_name !== storedName; });
                self.renderUploadedFilesList('sponsors');
            } else if (type === 'players') {
                self.filesPlayers = self.filesPlayers.filter(function(f) { return f.stored_name !== storedName; });
                self.renderUploadedFilesList('players');
            }
            self.saveDraft();

            $.post(self.data.ajax_url, {
                action: 'wooconfig_delete_file',
                nonce: self.data.nonce,
                upload_token: self.uploadToken,
                stored_name: storedName
            });
        },

        renderUploadedFilesList: function(type) {
            var $list = $('#wooconfig-' + type + '-files-list');
            var files = (type === 'design') ? this.filesDesign : ((type === 'sponsors') ? this.filesSponsors : this.filesPlayers);

            $list.empty();
            if (!files || files.length === 0) return;

            var self = this;
            $.each(files, function(i, f) {
                var previewHtml = f.is_image ? '<img src="' + f.file_url + '" class="file-thumb" alt="preview">' : '<span class="file-doc-icon">📄</span>';
                var itemHtml = '<div class="wooconfig-file-item">' +
                    '<div class="file-meta">' +
                        previewHtml +
                        '<span class="file-name" title="' + self.escapeHtml(f.original_name) + '">' + self.escapeHtml(f.original_name) + '</span>' +
                    '</div>' +
                    '<button type="button" class="wooconfig-btn-delete-file" data-stored-name="' + self.escapeHtml(f.stored_name) + '" data-upload-type="' + type + '">' +
                        '<span class="trash-icon">🗑</span> Borrar' +
                    '</button>' +
                '</div>';
                $list.append(itemHtml);
            });
        },

        // ================= INTERACTIVE TABLE SUB-MODAL =================
        openTableModal: function() {
            this.syncPlayersWithQuantity(this.quantity);
            this.renderTablePills();
            this.renderTableContent();
            $('#wooconfig-table-modal').fadeIn(200);
            $('body').addClass('wooconfig-submodal-open');
        },

        closeTableModal: function() {
            $('#wooconfig-table-modal').fadeOut(200);
            $('body').removeClass('wooconfig-submodal-open');
        },

        getAssignedAdicionalCount: function(adKey) {
            var count = 0;
            $.each(this.players, function(i, p) {
                if (Array.isArray(p.adicionales) && p.adicionales.indexOf(adKey) > -1) {
                    count++;
                }
            });
            return count;
        },

        renderTablePills: function() {
            var $wrap = $('#wooconfig-table-ads-badges');
            $wrap.empty();

            var self = this;
            if (!this.data.adicionales) return;

            $.each(this.data.adicionales, function(key, ad) {
                var available = parseInt(self.adicionalesCounts[key], 10) || 0;
                var assigned  = self.getAssignedAdicionalCount(key);
                var isComplete = (available > 0 && assigned === available);
                var badgeClass = isComplete ? 'is-complete' : (assigned > 0 ? 'is-partial' : 'is-zero');
                var adLabel = ad.label;
                if (/polo/i.test(adLabel)) {
                    adLabel = 'Cuello piqué';
                }

                var pillHtml = '<div class="wooconfig-table-ad-pill ' + badgeClass + '">' +
                    '<strong>' + self.escapeHtml(adLabel) + ':</strong> ' +
                    '<span>' + assigned + '/' + available + (isComplete ? ' ✓' : '') + '</span>' +
                '</div>';
                $wrap.append(pillHtml);
            });
        },

        renderTableContent: function() {
            var $theadRow = $('#wooconfig-table-thead-row');
            var $tbody    = $('#wooconfig-table-tbody');

            // Reset dynamic columns in thead
            $theadRow.find('.col-adicional').remove();
            $tbody.empty();

            var self = this;
            var adicionalesKeys = [];

            if (this.data.adicionales) {
                $.each(this.data.adicionales, function(key, ad) {
                    adicionalesKeys.push(key);
                    var adLabel = ad.label;
                    if (/polo/i.test(adLabel)) {
                        adLabel = 'Cuello piqué';
                    }
                    var thHtml = '<th class="col-adicional" data-ad-key="' + key + '">' + self.escapeHtml(adLabel) + '</th>';
                    $theadRow.append(thHtml);
                });
            }

            var sizes = this.data.available_sizes || ['4', '6', '8', '10', '12', '14', '16', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'];

            $.each(this.players, function(idx, p) {
                var rowNum = idx + 1;
                var tr = $('<tr class="wooconfig-table-row" data-index="' + idx + '"></tr>');

                // Col 1: # Prenda
                tr.append('<td class="cell-prenda">' + rowNum + '</td>');

                // Col 2: N° Dorsal input
                tr.append('<td class="cell-dorsal"><input type="text" class="wooconfig-table-input-dorsal" placeholder="#" maxlength="4" value="' + self.escapeHtml(p.dorsal || '') + '"></td>');

                // Col 3: Nombre
                tr.append('<td class="cell-name"><input type="text" class="wooconfig-table-input-name" placeholder="Nombre" value="' + self.escapeHtml(p.name || '') + '"></td>');

                // Col 4: Talla
                var selHtml = '<select class="wooconfig-table-select-size"><option value="">Talla *</option>';
                $.each(sizes, function(i, sz) {
                    var isSel = (p.size === sz) ? 'selected' : '';
                    selHtml += '<option value="' + self.escapeHtml(sz) + '" ' + isSel + '>' + self.escapeHtml(sz) + '</option>';
                });
                selHtml += '</select>';
                tr.append('<td class="cell-size">' + selHtml + '</td>');

                // Col 5+: Adicionales Checkboxes
                $.each(adicionalesKeys, function(i, adKey) {
                    var isChecked = Array.isArray(p.adicionales) && (p.adicionales.indexOf(adKey) > -1);
                    var checkedAttr = isChecked ? 'checked' : '';
                    var activeClass = isChecked ? 'is-active' : '';

                    var tdHtml = '<td class="cell-adicional">' +
                        '<label class="wooconfig-custom-checkbox-wrap ' + activeClass + '">' +
                            '<input type="checkbox" class="wooconfig-table-cb-ad" data-ad-key="' + adKey + '" ' + checkedAttr + '>' +
                            '<span class="wooconfig-cb-mark"></span>' +
                        '</label>' +
                    '</td>';
                    tr.append(tdHtml);
                });

                $tbody.append(tr);
            });
        },

        saveTableDataFromDom: function() {
            var self = this;
            var hasAnyData = false;

            $('#wooconfig-table-tbody tr').each(function() {
                var idx = parseInt($(this).data('index'), 10);
                if (typeof self.players[idx] !== 'undefined') {
                    var dorsal = $(this).find('.wooconfig-table-input-dorsal').val() || '';
                    var name   = $(this).find('.wooconfig-table-input-name').val() || '';
                    var size   = $(this).find('.wooconfig-table-select-size').val() || '';
                    var ads    = [];

                    $(this).find('.wooconfig-table-cb-ad:checked').each(function() {
                        ads.push($(this).data('ad-key'));
                    });

                    self.players[idx].dorsal = dorsal;
                    self.players[idx].name   = name;
                    self.players[idx].size   = size;
                    self.players[idx].adicionales = ads;

                    if (dorsal || name || size || ads.length > 0) hasAnyData = true;
                }
            });

            this.useTable = hasAnyData;
            this.updateTableSavedBadge();
            this.saveDraft();
        },

        updateTableSavedBadge: function() {
            if (this.useTable) {
                $('#wooconfig-table-saved-badge').fadeIn(150);
                $('#wooconfig-table-btn-label').text('Editar tabla (' + this.players.length + ' prendas)');
            } else {
                $('#wooconfig-table-saved-badge').hide();
                $('#wooconfig-table-btn-label').text('Usar tabla (opcional)');
            }
        },

        // ================= HELP POPUPS =================
        openHelpPopup: function(helpKey) {
            var titles = {
                'cantidades': 'Instrucciones: Cantidad de prendas o sets',
                'adicionales': 'Instrucciones: Cantidad de adicionales',
                'diseno': 'Ejemplo: Archivos de diseño del producto',
                'sponsors': 'Ejemplo: Logos de Auspiciadores / Sponsors',
                'jugadores': 'Ejemplo: Archivo de Jugadores y Tallas',
                'tabla': 'Instrucciones: Tabla interactiva de jugadores'
            };

            var title = titles[helpKey] || 'Instrucciones';
            var imgUrl = (this.data.help_images && this.data.help_images[helpKey]) ? this.data.help_images[helpKey] : '';

            $('#wooconfig-help-modal-title').text(title);
            if (imgUrl) {
                $('#wooconfig-help-modal-img').attr('src', imgUrl).show();
            } else {
                $('#wooconfig-help-modal-img').hide();
            }

            $('#wooconfig-help-modal').fadeIn(150);
            $('body').addClass('wooconfig-help-open');
        },

        closeHelpPopup: function() {
            $('#wooconfig-help-modal').fadeOut(150);
            $('body').removeClass('wooconfig-help-open');
        },

        // ================= SIZE GUIDE POPUP & TABS =================
        openSizeGuide: function() {
            $('#wooconfig-guide-dialog').css({
                'display': 'flex',
                'z-index': '2147483647'
            }).hide().fadeIn(150);
            $('body').addClass('wooconfig-guide-open');
        },

        closeSizeGuide: function() {
            $('#wooconfig-guide-dialog').fadeOut(150, function() {
                $(this).css('display', 'none');
            });
            $('body').removeClass('wooconfig-guide-open');
        },

        // ================= VALIDATION & SUBMISSION =================
        validateOrder: function() {
            var errors = [];
            var self = this;

            // Step 1: Minimum quantity check (at product level, min is min_units or 1)
            var minUnits = parseInt(this.data.min_units, 10) || 1;
            if (this.quantity < minUnits) {
                errors.push({
                    step: 1,
                    msg: 'Paso 1 (Cantidades): Debes ingresar al menos ' + minUnits + (minUnits === 1 ? ' unidad.' : ' unidades.')
                });
            }

            // Step 2: Diseño file check
            if (!this.filesDesign || this.filesDesign.length === 0) {
                errors.push({
                    step: 2,
                    msg: 'Paso 2 (Diseño): Debes subir al menos un archivo o imagen con el diseño.'
                });
            }

            // Step 3: Sponsors file check
            if (!this.filesSponsors || this.filesSponsors.length === 0) {
                errors.push({
                    step: 3,
                    msg: 'Paso 3 (Sponsors): Debes subir los logos de los sponsors.'
                });
            }

            // Step 4: Jugadores check (Either file OR complete table)
            var hasPlayersFile = (this.filesPlayers && this.filesPlayers.length > 0);
            var isTableUsed = this.useTable;

            if (!hasPlayersFile && !isTableUsed) {
                errors.push({
                    step: 4,
                    msg: 'Paso 4 (Jugadores y tallas): Debes subir un archivo con las tallas o completar la tabla manual.'
                });
            } else if (isTableUsed) {
                // Validate table contents
                var missingSizes = [];
                $.each(this.players, function(idx, p) {
                    if (!p.size) {
                        missingSizes.push(idx + 1);
                    }
                });

                if (missingSizes.length > 0) {
                    errors.push({
                        step: 4,
                        msg: 'Paso 4 (Tabla de jugadores): Falta seleccionar la talla para la(s) prenda(s) #' + missingSizes.join(', #') + '.'
                    });
                }

                // Validate matching adicionales counts
                if (this.data.adicionales) {
                    $.each(this.data.adicionales, function(key, ad) {
                        var expected = parseInt(self.adicionalesCounts[key], 10) || 0;
                        var assigned = self.getAssignedAdicionalCount(key);
                        if (expected > 0 && assigned !== expected) {
                            errors.push({
                                step: 4,
                                msg: 'Paso 4 (Tabla): Seleccionaste ' + expected + ' ' + ad.label + ' en el Paso 1, pero asignaste ' + assigned + ' en la tabla.'
                            });
                        }
                    });
                }
            }

            return errors;
        },

        submitCart: function() {
            var self = this;
            var errors = this.validateOrder();

            if (errors.length > 0) {
                var $list = $('#wooconfig-validation-errors-list');
                $list.empty();

                $.each(errors, function(i, err) {
                    var item = $('<li><span>' + self.escapeHtml(err.msg) + '</span> </li>');
                    var jumpBtn = $('<button type="button" class="wooconfig-btn-jump-step">Corregir ›</button>');
                    jumpBtn.on('click', function(e) {
                        e.preventDefault();
                        self.goToStep(err.step);
                    });
                    item.append(jumpBtn);
                    $list.append(item);
                });

                $('#wooconfig-validation-banner').slideDown(200);
                var $widget = $('#wooconfig-onpage-widget');
                if ($widget.length) {
                    $('html, body').animate({ scrollTop: $widget.offset().top - 80 }, 250);
                }
                return;
            }

            var $btn     = $('#wooconfig-submit-cart-btn');
            var $label   = $('#wooconfig-submit-cart-label');
            var $spinner = $('#wooconfig-submit-spinner');

            $btn.prop('disabled', true);
            $spinner.show();
            $label.text(this.data.i18n ? this.data.i18n.adding_to_cart : 'Añadiendo al carrito...');
            self.isSubmitting = true;

            var payload = {
                action: 'wooconfig_add_team_to_cart',
                nonce: this.data.nonce,
                upload_token: this.uploadToken,
                flow: 'unified',
                product_id: this.data.product_id,
                quantity: this.quantity,
                payment_mode: this.paymentMode,
                adicionales_counts: JSON.stringify(this.adicionalesCounts),
                files_design: JSON.stringify(this.filesDesign),
                design_notes: $('#wooconfig-design-notes').val() || '',
                files_sponsors: JSON.stringify(this.filesSponsors),
                sponsor_notes: $('#wooconfig-sponsors-notes').val() || '',
                files_players: JSON.stringify(this.filesPlayers),
                use_table: this.useTable ? '1' : '0',
                players: JSON.stringify(this.players)
            };

            this.sendCart(payload, 0);
        },

        /**
         * Envía el pedido; un 403 significa nonce vencido (página cacheada) -> refrescar y reintentar.
         */
        sendCart: function(payload, attempt) {
            var self = this;
            var $btn     = $('#wooconfig-submit-cart-btn');
            var $label   = $('#wooconfig-submit-cart-label');
            var $spinner = $('#wooconfig-submit-spinner');

            $.post(this.data.ajax_url, payload, function(res) {
                $btn.prop('disabled', false);
                $spinner.hide();
                $label.text('Añadir y ver carrito');

                if (res && res.success) {
                    self.clearDraft();
                    if (res.data && res.data.cart_url) {
                        window.location.href = res.data.cart_url;
                    } else {
                        $('#wooconfig-success-dialog').fadeIn(150);
                    }
                } else {
                    self.isSubmitting = false;
                    alert((res && res.data && res.data.message) ? res.data.message : 'Error al añadir al carrito.');
                }
            }).fail(function(xhr) {
                if (xhr && xhr.status === 403 && attempt < 1) {
                    self.nonceFresh = false;
                    self.refreshNonce(function() {
                        payload.nonce = self.data.nonce;
                        self.sendCart(payload, attempt + 1);
                    });
                    return;
                }
                $btn.prop('disabled', false);
                $spinner.hide();
                $label.text('Añadir y ver carrito');
                self.isSubmitting = false;
                alert('Error de conexión al procesar el pedido.');
            });
        },

        bindEvents: function() {
            var self = this;

            // ================= STEP 1: QUANTITY CONTROLS =================
            $(document).on('click', '#wooconfig-main-qty-minus', function() {
                var min = parseInt(self.data.min_units, 10) || 1;
                if (self.quantity > min) {
                    self.quantity--;
                    $('#wooconfig-main-qty-input').val(self.quantity);
                    self.syncPlayersWithQuantity(self.quantity);
                    self.renderOnPageValues();
                    self.updateTotals();
                    self.saveDraft();
                }
            });

            $(document).on('click', '#wooconfig-main-qty-plus', function() {
                self.quantity++;
                $('#wooconfig-main-qty-input').val(self.quantity);
                self.syncPlayersWithQuantity(self.quantity);
                self.renderOnPageValues();
                self.updateTotals();
                self.saveDraft();
            });

            $(document).on('change input', '#wooconfig-main-qty-input', function() {
                var min = parseInt(self.data.min_units, 10) || 1;
                var val = parseInt($(this).val(), 10) || min;
                if (val < min) val = min;
                self.quantity = val;
                $(this).val(val);
                self.syncPlayersWithQuantity(self.quantity);
                self.renderOnPageValues();
                self.updateTotals();
                self.saveDraft();
            });

            // ================= STEP 1: ADICIONALES CONTROLS =================
            $(document).on('click', '.wooconfig-adicional-minus', function() {
                var key = $(this).data('key');
                var current = parseInt(self.adicionalesCounts[key], 10) || 0;
                if (current > 0) {
                    self.adicionalesCounts[key] = current - 1;
                    $('#wooconfig-ad-qty-' + key).val(self.adicionalesCounts[key]);
                    self.renderOnPageValues();
                    self.updateTotals();
                    self.saveDraft();
                }
            });

            $(document).on('click', '.wooconfig-adicional-plus', function() {
                var key = $(this).data('key');
                var current = parseInt(self.adicionalesCounts[key], 10) || 0;
                self.adicionalesCounts[key] = current + 1;
                $('#wooconfig-ad-qty-' + key).val(self.adicionalesCounts[key]);
                self.renderOnPageValues();
                self.updateTotals();
                self.saveDraft();
            });

            $(document).on('change input', '.wooconfig-adicional-input', function() {
                var key = $(this).data('key');
                var val = parseInt($(this).val(), 10) || 0;
                if (val < 0) val = 0;
                self.adicionalesCounts[key] = val;
                $(this).val(val);
                self.renderOnPageValues();
                self.updateTotals();
                self.saveDraft();
            });

            // ================= STEP NAVIGATION (ALL IN WIDGET) =================
            $(document).on('click', '#wooconfig-btn-start-config, #wooconfig-step1-goto-step2', function(e) {
                e.preventDefault();
                self.goToStep(2);
            });

            $(document).on('click', '#wooconfig-step2-back-to-step1', function() {
                self.goToStep(1);
            });

            $(document).on('click', '#wooconfig-step2-goto-step3', function() {
                self.goToStep(3);
            });

            $(document).on('click', '#wooconfig-step3-back-to-step2', function() {
                self.goToStep(2);
            });

            $(document).on('click', '#wooconfig-step3-goto-step4', function() {
                self.goToStep(4);
            });

            $(document).on('click', '#wooconfig-step4-back-to-step3', function() {
                self.goToStep(3);
            });

            // Clickable segments and labels in progress bar
            $(document).on('click', '.wooconfig-prog-segment, .wooconfig-stepper-label', function() {
                var step = parseInt($(this).data('step'), 10);
                if (step >= 1 && step <= 4) {
                    self.goToStep(step);
                }
            });

            // ================= DROPZONES & FILE UPLOADS =================
            $(document).on('click', '#wooconfig-design-dropzone-trigger', function() {
                $('#wooconfig-design-file-input').trigger('click');
            });
            $(document).on('change', '#wooconfig-design-file-input', function() {
                if (this.files && this.files.length > 0) {
                    self.uploadFiles(this.files, 'design');
                }
            });

            $(document).on('click', '#wooconfig-sponsors-dropzone-trigger', function() {
                $('#wooconfig-sponsors-file-input').trigger('click');
            });
            $(document).on('change', '#wooconfig-sponsors-file-input', function() {
                if (this.files && this.files.length > 0) {
                    self.uploadFiles(this.files, 'sponsors');
                }
            });

            $(document).on('click', '#wooconfig-players-dropzone-trigger', function() {
                $('#wooconfig-players-file-input').trigger('click');
            });
            $(document).on('change', '#wooconfig-players-file-input', function() {
                if (this.files && this.files.length > 0) {
                    self.uploadFiles(this.files, 'players');
                }
            });

            // Delete File
            $(document).on('click', '.wooconfig-btn-delete-file', function(e) {
                e.preventDefault();
                var name = $(this).data('stored-name');
                var type = $(this).data('upload-type');
                self.deleteFile(name, type);
            });

            // Notes Autosave
            $(document).on('input change', '#wooconfig-design-notes, #wooconfig-sponsors-notes', function() {
                self.saveDraft();
            });

            // ================= TABLE SUB-MODAL =================
            $(document).on('click', '#wooconfig-btn-open-table', function(e) {
                e.preventDefault();
                self.openTableModal();
            });

            $(document).on('click', '#wooconfig-close-table-x-btn, #wooconfig-table-overlay', function(e) {
                e.preventDefault();
                self.saveTableDataFromDom();
                self.closeTableModal();
            });

            $(document).on('click', '#wooconfig-btn-save-table', function(e) {
                e.preventDefault();
                self.saveTableDataFromDom();
                self.closeTableModal();
            });

            $(document).on('click', '#wooconfig-btn-clear-table', function(e) {
                e.preventDefault();
                if (window.confirm('¿Deseas descartar los datos de la tabla?')) {
                    self.useTable = false;
                    self.syncPlayersWithQuantity(self.quantity);
                    self.updateTableSavedBadge();
                    self.saveDraft();
                }
            });

            // Adicional Checkbox Limit Enforcement in Table
            $(document).on('change', '.wooconfig-table-cb-ad', function() {
                var adKey = $(this).data('ad-key');
                var isChecked = $(this).is(':checked');
                var available = parseInt(self.adicionalesCounts[adKey], 10) || 0;
                var assigned = 0;

                $('#wooconfig-table-tbody .wooconfig-table-cb-ad[data-ad-key="' + adKey + '"]:checked').each(function() {
                    assigned++;
                });

                var $labelWrap = $(this).closest('.wooconfig-custom-checkbox-wrap');

                if (isChecked && assigned > available) {
                    $(this).prop('checked', false);
                    $labelWrap.removeClass('is-active');

                    var adObj = self.data.adicionales && self.data.adicionales[adKey] ? self.data.adicionales[adKey] : { label: adKey };
                    var warningMsg = '⚠️ Ya asignaste todos los adicionales de "' + adObj.label + '" disponibles (' + available + '/' + available + '). Si deseas asignarlo a este jugador, primero desmarca a otro.';
                    
                    var $warn = $('#wooconfig-table-ad-warning');
                    $warn.find('.wooconfig-ad-warning-text').text(warningMsg);
                    $warn.stop(true, true).slideDown(150).delay(4000).slideUp(150);
                    return;
                }

                if (isChecked) {
                    $labelWrap.addClass('is-active');
                } else {
                    $labelWrap.removeClass('is-active');
                }

                // Update pills
                self.renderTablePills();
            });

            // ================= HELP POPUPS =================
            $(document).on('click', '[data-help]', function(e) {
                e.preventDefault();
                var helpKey = $(this).data('help');
                self.openHelpPopup(helpKey);
            });

            $(document).on('click', '#wooconfig-help-close-btn, #wooconfig-btn-close-help-action, #wooconfig-help-overlay', function(e) {
                e.preventDefault();
                self.closeHelpPopup();
            });

            // ================= SUBMIT TO CART =================
            $(document).on('click', '#wooconfig-submit-cart-btn', function(e) {
                e.preventDefault();
                self.submitCart();
            });

            // ================= SIZE GUIDE POPUP & TABS =================
            $(document).on('click', '.wooconfig-btn-guide, .wooconfig-btn-standalone, #wooconfig-btn-size-guide, #wooconfig-standalone-btn-size-guide, [data-action="open-size-guide"]', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.openSizeGuide();
            });

            $(document).on('click', '#wooconfig-close-guide-btn, #wooconfig-btn-close-guide-action, [data-action="close-size-guide"]', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.closeSizeGuide();
            });

            $(document).on('click', '#wooconfig-guide-dialog', function(e) {
                if (e.target === this) {
                    self.closeSizeGuide();
                }
            });

            $(document).on('click', '.wooconfig-guide-tab-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var gender = $(this).data('gender');
                $('.wooconfig-guide-tab-btn').removeClass('is-active');
                $(this).addClass('is-active');
                $('.wooconfig-guide-gender-panel').hide();
                $('#wooconfig-guide-panel-' + gender).show();
            });

            // ESC key closes top active dialog
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' || e.keyCode === 27) {
                    if ($('#wooconfig-guide-dialog').is(':visible')) {
                        self.closeSizeGuide();
                    } else if ($('#wooconfig-help-modal').is(':visible')) {
                        self.closeHelpPopup();
                    } else if ($('#wooconfig-table-modal').is(':visible')) {
                        self.saveTableDataFromDom();
                        self.closeTableModal();
                    }
                }
            });
        },

        escapeHtml: function(text) {
            if (!text) return '';
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
        }
    };

    $(document).ready(function() {
        WooConfigApp.init();
    });

})(jQuery);
