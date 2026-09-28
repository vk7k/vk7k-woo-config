/**
 * WooConfig Frontend Script
 * Handles:
 * - Flow 1 (On-page Quick Config with Photo/Excel upload & Adicionales counters)
 * - Flow 2 (3-Step Modal Wizard with per-player Dorsal, Name, Size & Adicionales allocation)
 * - Payment mode (50% Deposit / 100% Full payment selection & live calculation)
 * - Prominent full total display with deposit breakdown
 * - LocalStorage Draft auto-save & Reset
 * - AJAX File Uploads and Cart submission
 */

(function($) {
    'use strict';

    var WooConfigApp = {
        data: window.wooconfig_data || {},
        quantity: 1,
        paymentMode: 'deposit',
        adicionalesCounts: {},
        players: [],
        uploadedFilesQuick: [],
        uploadedFilesWizard: [],
        currentStep: 1,
        isSubmitting: false,

        init: function() {
            if (!this.data.product_id) return;

            this.quantity = Math.max(1, parseInt(this.data.min_units, 10) || 1);
            this.paymentMode = (this.data.deposit_enabled) ? 'deposit' : 'full';
            this.initAdicionalesCounts();

            this.loadDraft();
            if (this.players.length === 0) {
                this.syncPlayersWithQuantity(this.quantity);
            } else {
                this.quantity = this.players.length;
                $('#wooconfig-main-qty-input, #wooconfig-modal-qty-input').val(this.quantity);
            }

            this.bindEvents();
            this.renderOnPageAdicionalesSubtotals();
            this.renderPlayersListStep1();
            this.updateStepLabels();
            this.updateTotals();
        },

        hasAdicionales: function() {
            if (!this.data.adicionales) return false;
            if (Array.isArray(this.data.adicionales)) return this.data.adicionales.length > 0;
            if (typeof this.data.adicionales === 'object') {
                return Object.keys(this.data.adicionales).length > 0;
            }
            return false;
        },

        updateStepLabels: function() {
            var hasAds = this.hasAdicionales();
            var nextText = hasAds 
                ? (this.data.i18n && this.data.i18n.next_adicionales ? this.data.i18n.next_adicionales : 'Siguiente paso: Adicionales >>')
                : (this.data.i18n && this.data.i18n.next_design ? this.data.i18n.next_design : 'Siguiente paso: Diseño >>');

            var step3Title = hasAds 
                ? (this.data.i18n && this.data.i18n.step3_design ? this.data.i18n.step3_design : 'Paso 3: Diseño')
                : (this.data.i18n && this.data.i18n.step2_design ? this.data.i18n.step2_design : 'Paso 2: Diseño');

            $('#wooconfig-step1-next-btn-text').text(nextText);
            $('#wooconfig-step3-title').text(step3Title);
        },

        getDraftKey: function() {
            return 'wooconfig_draft_' + this.data.product_id;
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
                    players: this.players,
                    designDescQuick: $('#wooconfig-quick-design-desc').val() || '',
                    designDescWizard: $('#wooconfig-design-desc').val() || '',
                    uploadedFilesQuick: this.uploadedFilesQuick,
                    uploadedFilesWizard: this.uploadedFilesWizard
                };
                localStorage.setItem(this.getDraftKey(), JSON.stringify(draft));
            } catch (e) {
                console.warn('WooConfig: Could not save draft to localStorage', e);
            }
        },

        loadDraft: function() {
            try {
                var raw = localStorage.getItem(this.getDraftKey());
                if (raw) {
                    var draft = JSON.parse(raw);
                    if (draft) {
                        if (draft.paymentMode) this.paymentMode = draft.paymentMode;
                        if (draft.adicionalesCounts) this.adicionalesCounts = $.extend({}, this.adicionalesCounts, draft.adicionalesCounts);
                        if (draft.players && Array.isArray(draft.players) && draft.players.length > 0) {
                            this.players = draft.players;
                            this.quantity = this.players.length;
                        } else if (draft.quantity) {
                            this.quantity = Math.max(parseInt(this.data.min_units, 10) || 1, parseInt(draft.quantity, 10));
                        }
                        if (draft.uploadedFilesQuick && Array.isArray(draft.uploadedFilesQuick)) this.uploadedFilesQuick = draft.uploadedFilesQuick;
                        if (draft.uploadedFilesWizard && Array.isArray(draft.uploadedFilesWizard)) this.uploadedFilesWizard = draft.uploadedFilesWizard;

                        if (draft.designDescQuick) $('#wooconfig-quick-design-desc').val(draft.designDescQuick);
                        if (draft.designDescWizard) $('#wooconfig-design-desc').val(draft.designDescWizard);

                        $('#wooconfig-main-qty-input, #wooconfig-modal-qty-input').val(this.quantity);
                        
                        // Select payment mode radio
                        $('input[name="wooconfig_payment_mode"][value="' + this.paymentMode + '"]').prop('checked', true);
                        $('.wooconfig-payment-option').removeClass('is-selected');
                        $('input[name="wooconfig_payment_mode"][value="' + this.paymentMode + '"]').closest('.wooconfig-payment-option').addClass('is-selected');

                        this.renderUploadedFilesList('quick');
                        this.renderUploadedFilesList('wizard');
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

        resetForm: function() {
            this.clearDraft();
            this.quantity = Math.max(1, parseInt(this.data.min_units, 10) || 1);
            this.paymentMode = (this.data.deposit_enabled) ? 'deposit' : 'full';
            this.initAdicionalesCounts();
            this.players = [];
            this.uploadedFilesQuick = [];
            this.uploadedFilesWizard = [];
            $('#wooconfig-quick-design-desc').val('');
            $('#wooconfig-design-desc').val('');
            $('#wooconfig-main-qty-input, #wooconfig-modal-qty-input').val(this.quantity);

            $('input[name="wooconfig_payment_mode"][value="deposit"]').prop('checked', true);
            $('.wooconfig-payment-option').removeClass('is-selected');
            $('#wooconfig-label-mode-deposit').addClass('is-selected');

            $('.wooconfig-adicional-input').val(0);
            this.syncPlayersWithQuantity(this.quantity);
            this.renderOnPageAdicionalesSubtotals();
            this.renderPlayersListStep1();
            this.renderUploadedFilesList('quick');
            this.renderUploadedFilesList('wizard');
            this.updateTotals();
        },

        syncPlayersWithQuantity: function(targetQty) {
            targetQty = Math.max(1, parseInt(targetQty, 10) || 1);
            while (this.players.length < targetQty) {
                this.players.push({
                    dorsal: '',
                    name: '',
                    size: '',
                    color: '',
                    adicionales: []
                });
            }
            if (this.players.length > targetQty) {
                this.players = this.players.slice(0, targetQty);
            }
            this.quantity = this.players.length;
            $('#wooconfig-main-qty-input, #wooconfig-modal-qty-input').val(this.quantity);
            this.saveDraft();
        },

        bindEvents: function() {
            var self = this;

            // ================= PAYMENT MODE TOGGLE =================
            $(document).on('change', 'input[name="wooconfig_payment_mode"]', function() {
                self.paymentMode = $(this).val();
                $('.wooconfig-payment-option').removeClass('is-selected');
                $(this).closest('.wooconfig-payment-option').addClass('is-selected');
                self.updateTotals();
                self.saveDraft();
            });

            // ================= ON-PAGE MAIN QTY CONTROLS =================
            $(document).on('click', '#wooconfig-main-qty-minus', function() {
                var min = parseInt(self.data.min_units, 10) || 1;
                if (self.quantity > min) {
                    self.quantity--;
                    $('#wooconfig-main-qty-input, #wooconfig-modal-qty-input').val(self.quantity);
                    self.syncPlayersWithQuantity(self.quantity);
                    self.renderPlayersListStep1();
                    self.updateTotals();
                }
            });

            $(document).on('click', '#wooconfig-main-qty-plus', function() {
                self.quantity++;
                $('#wooconfig-main-qty-input, #wooconfig-modal-qty-input').val(self.quantity);
                self.syncPlayersWithQuantity(self.quantity);
                self.renderPlayersListStep1();
                self.updateTotals();
            });

            $(document).on('change input', '#wooconfig-main-qty-input', function() {
                var val = parseInt($(this).val(), 10) || (parseInt(self.data.min_units, 10) || 1);
                var min = parseInt(self.data.min_units, 10) || 1;
                if (val < min) val = min;
                self.quantity = val;
                $(this).val(val);
                $('#wooconfig-modal-qty-input').val(val);
                self.syncPlayersWithQuantity(val);
                self.renderPlayersListStep1();
                self.updateTotals();
            });

            // ================= ON-PAGE ADICIONALES COUNTERS =================
            $(document).on('click', '.wooconfig-adicional-minus', function() {
                var key = $(this).data('key');
                var current = parseInt(self.adicionalesCounts[key], 10) || 0;
                if (current > 0) {
                    self.adicionalesCounts[key] = current - 1;
                    $('#wooconfig-ad-qty-' + key).val(self.adicionalesCounts[key]);
                    self.renderOnPageAdicionalesSubtotals();
                    self.updateTotals();
                    self.saveDraft();
                }
            });

            $(document).on('click', '.wooconfig-adicional-plus', function() {
                var key = $(this).data('key');
                var current = parseInt(self.adicionalesCounts[key], 10) || 0;
                self.adicionalesCounts[key] = current + 1;
                $('#wooconfig-ad-qty-' + key).val(self.adicionalesCounts[key]);
                self.renderOnPageAdicionalesSubtotals();
                self.updateTotals();
                self.saveDraft();
            });

            $(document).on('change input', '.wooconfig-adicional-input', function() {
                var key = $(this).data('key');
                var val = parseInt($(this).val(), 10) || 0;
                if (val < 0) val = 0;
                self.adicionalesCounts[key] = val;
                $(this).val(val);
                self.renderOnPageAdicionalesSubtotals();
                self.updateTotals();
                self.saveDraft();
            });

            // ================= FLOW 1: TOGGLE & QUICK ACTIONS =================
            $(document).on('click', '#wooconfig-trigger-flow-quick .wooconfig-flow-card-header', function(e) {
                e.preventDefault();
                $('#wooconfig-quick-panel').slideToggle(200);
                $('#wooconfig-trigger-flow-quick').toggleClass('is-open');
            });

            $(document).on('click', '#wooconfig-quick-upload-trigger', function() {
                $('#wooconfig-quick-file-input').trigger('click');
            });

            $(document).on('change', '#wooconfig-quick-file-input', function() {
                if (this.files && this.files.length > 0) {
                    self.uploadFiles(this.files, 'quick');
                }
            });

            $(document).on('click', '#wooconfig-quick-add-to-cart-btn', function(e) {
                e.preventDefault();
                self.submitQuickCart();
            });

            // ================= FLOW 2: MODAL WIZARD =================
            $(document).on('click', '#wooconfig-trigger-flow-wizard, #wooconfig-open-btn', function(e) {
                e.preventDefault();
                self.openModal();
            });

            $(document).on('click', '#wooconfig-close-step-1, #wooconfig-header-close-btn, #wooconfig-close-step-2-x, #wooconfig-close-step-3-x, #wooconfig-overlay', function(e) {
                e.preventDefault();
                self.closeModal();
            });

            $(document).on('click', '#wooconfig-btn-reset-form', function(e) {
                e.preventDefault();
                var msg = (self.data.i18n && self.data.i18n.confirm_reset) ? self.data.i18n.confirm_reset : '¿Estás seguro de que deseas limpiar y reiniciar la configuración?';
                if (window.confirm(msg)) {
                    self.resetForm();
                }
            });

            // Modal Qty Controls (Step 1)
            $(document).on('click', '#wooconfig-modal-qty-minus', function() {
                var min = parseInt(self.data.min_units, 10) || 1;
                if (self.quantity > min) {
                    self.quantity--;
                    $('#wooconfig-main-qty-input, #wooconfig-modal-qty-input').val(self.quantity);
                    self.syncPlayersWithQuantity(self.quantity);
                    self.renderPlayersListStep1();
                    self.updateTotals();
                }
            });

            $(document).on('click', '#wooconfig-modal-qty-plus', function() {
                self.quantity++;
                $('#wooconfig-main-qty-input, #wooconfig-modal-qty-input').val(self.quantity);
                self.syncPlayersWithQuantity(self.quantity);
                self.renderPlayersListStep1();
                self.updateTotals();
            });

            $(document).on('change input', '#wooconfig-modal-qty-input', function() {
                var val = parseInt($(this).val(), 10) || (parseInt(self.data.min_units, 10) || 1);
                var min = parseInt(self.data.min_units, 10) || 1;
                if (val < min) val = min;
                self.quantity = val;
                $(this).val(val);
                $('#wooconfig-main-qty-input').val(val);
                self.syncPlayersWithQuantity(val);
                self.renderPlayersListStep1();
                self.updateTotals();
            });

            // Player Cards Interaction (Step 1)
            $(document).on('click', '#wooconfig-add-player-btn', function() {
                self.addPlayerRow();
            });

            $(document).on('click', '.wooconfig-btn-delete-player', function() {
                var idx = parseInt($(this).closest('.wooconfig-player-card').data('index'), 10);
                self.removePlayerRow(idx);
            });

            $(document).on('input change', '.wooconfig-player-input', function() {
                var $card = $(this).closest('.wooconfig-player-card');
                var index = parseInt($card.data('index'), 10);
                var field = $(this).data('field');
                var val   = $(this).val();

                if (self.players[index]) {
                    self.players[index][field] = val;
                }
                self.saveDraft();
                self.updateTotals();
            });

            // Navigation: Step 1 -> Next (Step 2 if product has adicionales, otherwise Step 3)
            $(document).on('click', '#wooconfig-goto-step-2', function() {
                if (self.validateStep1()) {
                    if (self.hasAdicionales()) {
                        self.goToStep(2);
                    } else {
                        self.goToStep(3);
                    }
                }
            });

            // Navigation: Step 2 -> Step 1
            $(document).on('click', '#wooconfig-step2-backto-step1', function() {
                self.goToStep(1);
            });

            // Navigation: Step 2 -> Step 3
            $(document).on('click', '#wooconfig-goto-step-3', function() {
                self.goToStep(3);
            });

            // Navigation: Step 3 -> Back (Step 2 if product has adicionales, otherwise Step 1)
            $(document).on('click', '#wooconfig-step3-backto-step2', function() {
                if (self.hasAdicionales()) {
                    self.goToStep(2);
                } else {
                    self.goToStep(1);
                }
            });

            // Step 2: Toggle Adicional on Player Card
            $(document).on('click', '.wooconfig-toggle-adicional-btn', function() {
                var pIndex = parseInt($(this).data('player-index'), 10);
                var adKey  = $(this).data('ad-key');

                if (!self.players[pIndex]) return;
                if (!Array.isArray(self.players[pIndex].adicionales)) {
                    self.players[pIndex].adicionales = [];
                }

                var pos = self.players[pIndex].adicionales.indexOf(adKey);
                if (pos > -1) {
                    self.players[pIndex].adicionales.splice(pos, 1);
                    $(this).removeClass('is-active');
                    $(this).find('.toggle-check').text('☐ ');
                } else {
                    self.players[pIndex].adicionales.push(adKey);
                    $(this).addClass('is-active');
                    $(this).find('.toggle-check').text('☑ ');
                }

                self.recalculateAdicionalesCountsFromPlayers();
                self.renderStep2AdicionalesSummary();
                self.updateTotals();
                self.saveDraft();
            });

            // Step 3: Wizard Upload & Submit
            $(document).on('click', '#wooconfig-btn-trigger-upload', function() {
                $('#wooconfig-file-input').trigger('click');
            });

            $(document).on('change', '#wooconfig-file-input', function() {
                if (this.files && this.files.length > 0) {
                    self.uploadFiles(this.files, 'wizard');
                }
            });

            $(document).on('click', '#wooconfig-submit-cart', function() {
                self.submitWizardCart();
            });

            // File Delete
            $(document).on('click', '.wooconfig-btn-delete-file', function(e) {
                e.preventDefault();
                var name = $(this).data('stored-name');
                var type = $(this).data('upload-type') || 'wizard';
                self.deleteFile(name, type);
            });

            // Dialogs
            $(document).on('click', '#wooconfig-btn-configure-another', function() {
                $('#wooconfig-success-dialog').hide();
                self.resetForm();
                self.closeModal();
            });

            // ================= SIZE GUIDE POPUP & TABS =================
            $(document).on('click', '#wooconfig-btn-size-guide, #wooconfig-standalone-btn-size-guide, .wooconfig-btn-guide, .wooconfig-trigger-size-guide, .wooconfig-btn-standalone', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.openSizeGuide();
            });

            $(document).on('click', '#wooconfig-close-guide-btn, #wooconfig-btn-close-guide-action', function(e) {
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
                var gender = $(this).data('gender');
                $('.wooconfig-guide-tab-btn').removeClass('is-active');
                $(this).addClass('is-active');
                $('.wooconfig-guide-gender-panel').hide();
                $('#wooconfig-guide-panel-' + gender).show();
            });

            // ESC key to close size guide or modal
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' || e.keyCode === 27) {
                    if ($('#wooconfig-guide-dialog').is(':visible')) {
                        self.closeSizeGuide();
                    }
                }
            });

            // Warn before unload if unsubmitted
            $(window).on('beforeunload', function() {
                if (self.hasUnsavedData() && !self.isSubmitting) {
                    return 'Tienes cambios en tu equipo que no han sido añadidos al carrito.';
                }
            });
        },

        hasUnsavedData: function() {
            if (this.uploadedFilesQuick.length > 0 || this.uploadedFilesWizard.length > 0) return true;
            for (var i = 0; i < this.players.length; i++) {
                if (this.players[i].dorsal || this.players[i].name || this.players[i].size) {
                    return true;
                }
            }
            return false;
        },

        openModal: function() {
            var currentOnPageQty = parseInt($('#wooconfig-main-qty-input').val(), 10) || this.quantity || 1;
            this.syncPlayersWithQuantity(currentOnPageQty);
            this.renderPlayersListStep1();
            this.updateStepLabels();
            this.goToStep(1);
            $('#wooconfig-modal-wrapper').fadeIn(200);
            $('body').addClass('wooconfig-modal-open');
        },

        closeModal: function() {
            $('#wooconfig-modal-wrapper').fadeOut(200);
            $('body').removeClass('wooconfig-modal-open');
        },

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

        goToStep: function(step) {
            this.currentStep = step;
            this.updateStepLabels();
            $('.wooconfig-step-view').hide();
            $('#wooconfig-step-' + step).show();

            if (step === 2) {
                this.renderStep2AdicionalesSummary();
                this.renderPlayersListStep2();
            } else if (step === 3) {
                this.renderUploadedFilesList('wizard');
            }

            this.updateTotals();
            $('.wooconfig-modal-container').scrollTop(0);
        },

        addPlayerRow: function() {
            this.players.push({
                dorsal: '',
                name: '',
                size: '',
                color: '',
                adicionales: []
            });
            this.quantity = this.players.length;
            $('#wooconfig-main-qty-input, #wooconfig-modal-qty-input').val(this.quantity);
            this.renderPlayersListStep1();
            this.updateTotals();
            this.saveDraft();
        },

        removePlayerRow: function(index) {
            var min = parseInt(this.data.min_units, 10) || 1;
            if (this.players.length <= min) {
                alert(this.data.i18n ? this.data.i18n.min_units_error : 'El pedido mínimo es de ' + min + ' unidades.');
                return;
            }
            this.players.splice(index, 1);
            this.quantity = this.players.length;
            $('#wooconfig-main-qty-input, #wooconfig-modal-qty-input').val(this.quantity);
            this.renderPlayersListStep1();
            this.updateTotals();
            this.saveDraft();
        },

        renderPlayersListStep1: function() {
            var $list = $('#wooconfig-players-list');
            $list.empty();

            var sizes = this.data.available_sizes || ['4', '6', '8', '10', '12', '14', '16', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'];
            var self = this;

            $.each(this.players, function(idx, p) {
                var rowNum = idx + 1;
                var showDelete = self.players.length > (parseInt(self.data.min_units, 10) || 1);

                var optionsHtml = '<option value="">' + (self.data.i18n ? self.data.i18n.select_size : 'Talla *') + '</option>';
                $.each(sizes, function(i, sz) {
                    var sel = (p.size === sz) ? 'selected' : '';
                    optionsHtml += '<option value="' + self.escapeHtml(sz) + '" ' + sel + '>' + self.escapeHtml(sz) + '</option>';
                });

                var cardHtml = '<div class="wooconfig-player-card" data-index="' + idx + '">' +
                    '<div class="wooconfig-player-card-header">' +
                        '<span class="wooconfig-card-number">#' + rowNum + '</span>' +
                        (showDelete ? '<button type="button" class="wooconfig-btn-delete-player"><span class="wooconfig-trash-icon">🗑</span> Borrar</button>' : '') +
                    '</div>' +
                    '<div class="wooconfig-player-inputs-grid">' +
                        '<div class="wooconfig-input-wrap wooconfig-wrap-dorsal">' +
                            '<input type="text" class="wooconfig-player-input" data-field="dorsal" placeholder="Dorsal (#)" value="' + self.escapeHtml(p.dorsal || '') + '">' +
                        '</div>' +
                        '<div class="wooconfig-input-wrap wooconfig-wrap-name">' +
                            '<input type="text" class="wooconfig-player-input" data-field="name" placeholder="Nombre del jugador" value="' + self.escapeHtml(p.name || '') + '">' +
                        '</div>' +
                        '<div class="wooconfig-input-wrap wooconfig-wrap-size">' +
                            '<select class="wooconfig-player-input wooconfig-select" data-field="size">' + optionsHtml + '</select>' +
                        '</div>' +
                    '</div>' +
                '</div>';

                $list.append(cardHtml);
            });
        },

        renderStep2AdicionalesSummary: function() {
            var $summary = $('#wooconfig-step2-adicionales-summary');
            $summary.empty();

            var self = this;
            if (!this.data.adicionales) return;

            var html = '<div class="wooconfig-step2-ad-header-boxes">';
            $.each(this.data.adicionales, function(key, ad) {
                var count = self.adicionalesCounts[key] || 0;
                var unitPrice = parseFloat(ad.price) || 0;
                var subtotal = count * unitPrice;

                html += '<div class="wooconfig-step2-ad-pill">' +
                    '<strong>' + self.escapeHtml(ad.label) + ' (' + self.formatPrice(unitPrice) + '):</strong> ' +
                    '<span class="wooconfig-ad-count-badge">' + count + ' asignados</span>' +
                    '<span class="wooconfig-ad-subtotal-badge">(' + self.formatPrice(subtotal) + ')</span>' +
                '</div>';
            });
            html += '</div>';

            $summary.append(html);
        },

        renderPlayersListStep2: function() {
            var $list = $('#wooconfig-step2-players-list');
            $list.empty();

            var self = this;
            if (!this.data.adicionales) return;

            $.each(this.players, function(idx, p) {
                var rowNum = idx + 1;
                var pLabel = '#' + rowNum;
                if (p.dorsal || p.name) {
                    pLabel += ' - ' + (p.dorsal ? p.dorsal + ' ' : '') + (p.name || '');
                } else {
                    pLabel += ' - Prenda ' + rowNum;
                }
                if (p.size) {
                    pLabel += ' (Talla ' + p.size + ')';
                }

                var assigned = Array.isArray(p.adicionales) ? p.adicionales : [];

                var buttonsHtml = '';
                $.each(self.data.adicionales, function(key, ad) {
                    var isActive = assigned.indexOf(key) > -1;
                    var activeClass = isActive ? 'is-active' : '';
                    var checkIcon = isActive ? '☑ ' : '☐ ';

                    buttonsHtml += '<button type="button" class="wooconfig-toggle-adicional-btn ' + activeClass + '" data-player-index="' + idx + '" data-ad-key="' + key + '">' +
                        '<span class="toggle-check">' + checkIcon + '</span>' +
                        '<span>' + self.escapeHtml(ad.label) + '</span>' +
                    '</button>';
                });

                var cardHtml = '<div class="wooconfig-step2-player-card">' +
                    '<div class="wooconfig-step2-player-title">' + self.escapeHtml(pLabel) + '</div>' +
                    '<div class="wooconfig-step2-toggles-wrap">' + buttonsHtml + '</div>' +
                '</div>';

                $list.append(cardHtml);
            });
        },

        recalculateAdicionalesCountsFromPlayers: function() {
            var self = this;
            self.initAdicionalesCounts();

            $.each(this.players, function(idx, p) {
                if (Array.isArray(p.adicionales)) {
                    $.each(p.adicionales, function(i, adKey) {
                        if (typeof self.adicionalesCounts[adKey] !== 'undefined') {
                            self.adicionalesCounts[adKey]++;
                        }
                    });
                }
            });

            $.each(self.adicionalesCounts, function(key, count) {
                $('#wooconfig-ad-qty-' + key).val(count);
            });
            this.renderOnPageAdicionalesSubtotals();
        },

        renderOnPageAdicionalesSubtotals: function() {
            var self = this;
            if (!this.data.adicionales) return;

            $.each(this.data.adicionales, function(key, ad) {
                var qty = parseInt(self.adicionalesCounts[key], 10) || 0;
                var unitPrice = parseFloat(ad.price) || 0;
                var subtotal = qty * unitPrice;
                $('#wooconfig-ad-subtotal-' + key).html(self.formatPrice(subtotal));
            });
        },

        updateTotals: function() {
            var basePrice = parseFloat(this.data.product_price) || 0;
            var totalQty  = Math.max(1, this.players.length || parseInt(this.quantity, 10) || 1);

            var extraTotal = 0;
            var self = this;
            if (this.data.adicionales) {
                $.each(this.data.adicionales, function(key, ad) {
                    var count = parseInt(self.adicionalesCounts[key], 10) || 0;
                    var unitCost = parseFloat(ad.price) || 0;
                    extraTotal += (count * unitCost);
                });
            }

            var grandTotal = (basePrice * totalQty) + extraTotal;
            var isDepositEnabled = !!this.data.deposit_enabled;
            var depositPct = parseInt(this.data.deposit_percent, 10) || 50;
            var depositAmount = Math.round(grandTotal * (depositPct / 100));
            var balanceAmount = grandTotal - depositAmount;

            var formattedGrandTotal = this.formatPrice(grandTotal);
            var formattedDeposit    = this.formatPrice(depositAmount);
            var formattedBalance    = this.formatPrice(balanceAmount);

            // Update onpage amounts
            $('#wooconfig-onpage-total-amount').html(formattedGrandTotal);
            $('#wooconfig-onpage-deposit-tag-amount').html(formattedDeposit);
            $('#wooconfig-mode-deposit-val').html(formattedDeposit);
            $('#wooconfig-mode-balance-val').html(formattedBalance);
            $('#wooconfig-mode-full-val').html(formattedGrandTotal);

            // Modal summary headers & titles
            $('#wooconfig-summary-product-title, #wooconfig-step2-product-title, #wooconfig-step3-product-title').text(this.data.product_title || '');
            var qtyLabel = totalQty + (totalQty === 1 ? ' unidad' : ' unidades');
            $('#wooconfig-summary-qty-text, #wooconfig-step2-qty-text, #wooconfig-step3-qty-text').text(qtyLabel);

            // Modal step 3 breakdown
            $('#wooconfig-modal-full-total').html(formattedGrandTotal);
            $('#wooconfig-modal-deposit-val').html(formattedDeposit + ' (' + depositPct + '%)');
            $('#wooconfig-modal-balance-val').html(formattedBalance);

            // Footer summary display: Always prominently show the FULL TOTAL!
            var footerPriceHtml = '<div style="font-size:16px; font-weight:900; color:#000;">' + formattedGrandTotal + '</div>';
            if (isDepositEnabled && this.paymentMode === 'deposit') {
                footerPriceHtml += '<div style="font-size:11px; font-weight:700; color:#059669; margin-top:2px;">(Abonas hoy ' + depositPct + '%: ' + formattedDeposit + ')</div>';
            }

            $('#wooconfig-summary-total-price, #wooconfig-step2-total-price, #wooconfig-step3-total-price').html(footerPriceHtml);
        },

        formatPrice: function(num) {
            var symbol = this.data.currency_symbol || '$';
            var formatted = Math.round(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            return symbol + formatted;
        },

        validateStep1: function() {
            var min = parseInt(this.data.min_units, 10) || 1;
            if (this.players.length < min) {
                alert(this.data.i18n ? this.data.i18n.min_units_error : 'El pedido mínimo es de ' + min + ' unidades.');
                return false;
            }

            var allSizesChosen = true;
            $.each(this.players, function(idx, p) {
                if (!p.size) {
                    allSizesChosen = false;
                    return false;
                }
            });

            if (!allSizesChosen) {
                alert(this.data.i18n ? this.data.i18n.empty_size_warn : 'Por favor selecciona la talla para todos los jugadores.');
                return false;
            }

            return true;
        },

        uploadFiles: function(filesList, type) {
            var self = this;
            var formData = new FormData();
            formData.append('action', 'wooconfig_upload_file');
            formData.append('nonce', this.data.nonce);

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

            var $progressWrapper = (type === 'quick') ? $('#wooconfig-quick-upload-progress') : $('#wooconfig-upload-progress');
            var $progressFill    = (type === 'quick') ? $('#wooconfig-quick-progress-fill') : $('#wooconfig-progress-fill');
            var $progressText    = (type === 'quick') ? $('#wooconfig-quick-progress-text') : $('#wooconfig-progress-text');

            $progressWrapper.show();
            $progressFill.css('width', '10%');
            $progressText.text(this.data.i18n ? this.data.i18n.uploading : 'Subiendo archivos...');

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
                        if (type === 'quick') {
                            self.uploadedFilesQuick = self.uploadedFilesQuick.concat(res.data.files);
                            $('#wooconfig-quick-dropzone').removeClass('wooconfig-dropzone-error');
                            $('#wooconfig-quick-error-msg').remove();
                            self.renderUploadedFilesList('quick');
                        } else {
                            self.uploadedFilesWizard = self.uploadedFilesWizard.concat(res.data.files);
                            self.renderUploadedFilesList('wizard');
                        }
                        self.saveDraft();
                    } else {
                        alert((res && res.data && res.data.message) ? res.data.message : 'Error al subir archivos.');
                    }
                },
                error: function() {
                    $progressWrapper.hide();
                    alert('Error de conexión al subir archivos.');
                }
            });
        },

        deleteFile: function(storedName, type) {
            var self = this;
            var list = (type === 'quick') ? self.uploadedFilesQuick : self.uploadedFilesWizard;
            var filtered = list.filter(function(f) { return f.stored_name !== storedName; });

            if (type === 'quick') {
                self.uploadedFilesQuick = filtered;
                self.renderUploadedFilesList('quick');
            } else {
                self.uploadedFilesWizard = filtered;
                self.renderUploadedFilesList('wizard');
            }

            self.saveDraft();

            $.post(self.data.ajax_url, {
                action: 'wooconfig_delete_file',
                nonce: self.data.nonce,
                stored_name: storedName
            });
        },

        renderUploadedFilesList: function(type) {
            var $list = (type === 'quick') ? $('#wooconfig-quick-files-list') : $('#wooconfig-files-list');
            var files = (type === 'quick') ? this.uploadedFilesQuick : this.uploadedFilesWizard;

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
                        '🗑 Borrar' +
                    '</button>' +
                '</div>';
                $list.append(itemHtml);
            });
        },

        submitQuickCart: function() {
            var self = this;
            var min = parseInt(this.data.min_units, 10) || 1;
            if (this.quantity < min) {
                alert(this.data.i18n ? this.data.i18n.min_units_error : 'El pedido mínimo es de ' + min + ' unidades.');
                return;
            }

            if (!this.uploadedFilesQuick || this.uploadedFilesQuick.length === 0) {
                var errorMsg = (this.data.i18n && this.data.i18n.quick_no_files_error) 
                    ? this.data.i18n.quick_no_files_error 
                    : 'No es posible confeccionar tu pedido sin el detalle de las tallas, dorsales y apellidos. Por favor sube una foto o archivo con los datos de tu equipo (o utiliza el Personalizador paso a paso).';

                $('#wooconfig-quick-dropzone').addClass('wooconfig-dropzone-error');
                $('#wooconfig-quick-error-msg').remove();
                $('#wooconfig-quick-dropzone').after(
                    '<div id="wooconfig-quick-error-msg" class="wooconfig-quick-inline-error"><span class="wooconfig-error-icon">⚠️</span> ' + self.escapeHtml(errorMsg) + '</div>'
                );

                if ($('#wooconfig-quick-dropzone').length) {
                    $('html, body').animate({
                        scrollTop: $('#wooconfig-quick-dropzone').offset().top - 120
                    }, 300);
                }

                alert(errorMsg);
                return;
            }

            var $btn = $('#wooconfig-quick-add-to-cart-btn');
            var $text = $('#wooconfig-quick-btn-text');
            var $spinner = $('#wooconfig-quick-spinner');

            $btn.prop('disabled', true);
            $spinner.show();
            $text.text(this.data.i18n ? this.data.i18n.adding_to_cart : 'Añadiendo al carrito...');
            self.isSubmitting = true;

            var payload = {
                action: 'wooconfig_add_team_to_cart',
                nonce: this.data.nonce,
                flow: 'quick',
                product_id: this.data.product_id,
                quantity: this.quantity,
                payment_mode: this.paymentMode,
                adicionales_counts: JSON.stringify(this.adicionalesCounts),
                design_desc: $('#wooconfig-quick-design-desc').val() || '',
                files: JSON.stringify(this.uploadedFilesQuick)
            };

            $.post(this.data.ajax_url, payload, function(res) {
                $btn.prop('disabled', false);
                $spinner.hide();
                $text.text('Añadir al carrito');

                if (res && res.success) {
                    self.clearDraft();
                    if (res.data && res.data.cart_url) {
                        window.location.href = res.data.cart_url;
                    }
                } else {
                    self.isSubmitting = false;
                    alert((res && res.data && res.data.message) ? res.data.message : 'Error al añadir al carrito.');
                }
            }).fail(function() {
                $btn.prop('disabled', false);
                $spinner.hide();
                $text.text('Añadir al carrito');
                self.isSubmitting = false;
                alert('Error de conexión al procesar el pedido.');
            });
        },

        submitWizardCart: function() {
            var self = this;
            if (!this.validateStep1()) return;

            var $btn = $('#wooconfig-submit-cart');
            var $text = $('#wooconfig-submit-btn-text');
            var $spinner = $('#wooconfig-submit-spinner');

            $btn.prop('disabled', true);
            $spinner.show();
            $text.text(this.data.i18n ? this.data.i18n.adding_to_cart : 'Añadiendo al carrito...');
            self.isSubmitting = true;

            var payload = {
                action: 'wooconfig_add_team_to_cart',
                nonce: this.data.nonce,
                flow: 'wizard',
                product_id: this.data.product_id,
                payment_mode: this.paymentMode,
                players: JSON.stringify(this.players),
                design_desc: $('#wooconfig-design-desc').val() || '',
                files: JSON.stringify(this.uploadedFilesWizard)
            };

            $.post(this.data.ajax_url, payload, function(res) {
                $btn.prop('disabled', false);
                $spinner.hide();
                $text.text('Añadir al carrito');

                if (res && res.success) {
                    self.clearDraft();
                    $('#wooconfig-success-dialog').fadeIn(150);
                } else {
                    self.isSubmitting = false;
                    alert((res && res.data && res.data.message) ? res.data.message : 'Error al añadir al carrito.');
                }
            }).fail(function() {
                $btn.prop('disabled', false);
                $spinner.hide();
                $text.text('Añadir al carrito');
                self.isSubmitting = false;
                alert('Error de conexión al procesar el pedido.');
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
        // Direct global size guide triggers (immediate and resilient)
        $(document).on('click', '.wooconfig-btn-guide, .wooconfig-btn-standalone, #wooconfig-btn-size-guide, #wooconfig-standalone-btn-size-guide, [data-action="open-size-guide"]', function(e) {
            e.preventDefault();
            e.stopPropagation();
            WooConfigApp.openSizeGuide();
        });

        $(document).on('click', '#wooconfig-close-guide-btn, #wooconfig-btn-close-guide-action, [data-action="close-size-guide"]', function(e) {
            e.preventDefault();
            e.stopPropagation();
            WooConfigApp.closeSizeGuide();
        });

        $(document).on('click', '#wooconfig-guide-dialog', function(e) {
            if (e.target === this) {
                WooConfigApp.closeSizeGuide();
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

        WooConfigApp.init();
    });

})(jQuery);
