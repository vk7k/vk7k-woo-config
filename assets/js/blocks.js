/**
 * WooConfig Gutenberg Blocks
 * Provides:
 * - 'wooconfig/size-guide-button': Standalone Size Guide button that can be positioned anywhere in the product template.
 * - 'wooconfig/widget': Full on-page WooConfig purchase configurator widget.
 */
(function(wp) {
    'use strict';

    if (!wp || !wp.blocks || !wp.element) {
        return;
    }

    var registerBlockType = wp.blocks.registerBlockType;
    var el = wp.element.createElement;
    var __ = wp.i18n.__;
    var InspectorControls = wp.blockEditor ? wp.blockEditor.InspectorControls : wp.editor.InspectorControls;
    var PanelBody = wp.components ? wp.components.PanelBody : null;
    var TextControl = wp.components ? wp.components.TextControl : null;
    var SelectControl = wp.components ? wp.components.SelectControl : null;

    // SVG Ruler Icon
    var rulerIcon = el('svg', { width: 20, height: 20, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2 },
        el('rect', { x: 2, y: 4, width: 20, height: 16, rx: 2 }),
        el('line', { x1: 6, y1: 8, x2: 6, y2: 16 }),
        el('line', { x1: 10, y1: 8, x2: 10, y2: 12 }),
        el('line', { x1: 14, y1: 8, x2: 14, y2: 16 }),
        el('line', { x1: 18, y1: 8, x2: 18, y2: 12 })
    );

    // ================= 1. BLOCK: GUÍA DE TALLAS =================
    registerBlockType('wooconfig/size-guide-button', {
        title: __('Guía de Tallas (WooConfig)', 'woo-config'),
        description: __('Botón oficial de guía de tallas de Cafú que abre el popup con las medidas correspondientes al producto.', 'woo-config'),
        icon: rulerIcon,
        category: 'woocommerce',
        keywords: [__('talla', 'woo-config'), __('medidas', 'woo-config'), __('guia de tallas', 'woo-config'), __('size guide', 'woo-config'), __('cafu', 'woo-config')],
        supports: {
            align: ['left', 'center', 'right', 'wide'],
            html: false
        },
        attributes: {
            buttonText: {
                type: 'string',
                default: 'Guía de tallas'
            },
            buttonStyle: {
                type: 'string',
                default: 'default' // 'default' (lime), 'dark', 'outline'
            },
            align: {
                type: 'string',
                default: 'left'
            }
        },

        edit: function(props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;

            var styleClass = 'wooconfig-btn-style-' + (attributes.buttonStyle || 'default');
            var alignStyle = {};
            if (attributes.align === 'center') {
                alignStyle = { display: 'flex', justifyContent: 'center' };
            } else if (attributes.align === 'right') {
                alignStyle = { display: 'flex', justifyContent: 'flex-end' };
            }

            return el(
                wp.element.Fragment,
                null,
                InspectorControls && el(
                    InspectorControls,
                    null,
                    el(
                        PanelBody,
                        { title: __('Configuración del Botón', 'woo-config'), initialOpen: true },
                        el(TextControl, {
                            label: __('Texto del Botón', 'woo-config'),
                            value: attributes.buttonText,
                            onChange: function(newVal) {
                                setAttributes({ buttonText: newVal });
                            }
                        }),
                        el(SelectControl, {
                            label: __('Estilo Visual', 'woo-config'),
                            value: attributes.buttonStyle,
                            options: [
                                { label: __('Verde Neón Oficial (Cafú)', 'woo-config'), value: 'default' },
                                { label: __('Gris Claro / Suave', 'woo-config'), value: 'light' },
                                { label: __('Negro / Oscuro', 'woo-config'), value: 'dark' },
                                { label: __('Borde / Outline', 'woo-config'), value: 'outline' }
                            ],
                            onChange: function(newVal) {
                                setAttributes({ buttonStyle: newVal });
                            }
                        }),
                        el(SelectControl, {
                            label: __('Alineación', 'woo-config'),
                            value: attributes.align,
                            options: [
                                { label: __('Izquierda', 'woo-config'), value: 'left' },
                                { label: __('Centrado', 'woo-config'), value: 'center' },
                                { label: __('Derecha', 'woo-config'), value: 'right' }
                            ],
                            onChange: function(newVal) {
                                setAttributes({ align: newVal });
                            }
                        })
                    )
                ),
                el(
                    'div',
                    {
                        className: 'wooconfig-editor-block-wrapper ' + styleClass,
                        style: alignStyle
                    },
                    el(
                        'button',
                        {
                            type: 'button',
                            className: 'wooconfig-btn-guide wooconfig-btn-guide-onpage ' + styleClass,
                            style: { pointerEvents: 'none', cursor: 'default' }
                        },
                        rulerIcon,
                        el('span', null, attributes.buttonText || __('Guía de tallas', 'woo-config'))
                    )
                )
            );
        },

        save: function() {
            // Dynamic block rendered server-side via render_callback
            return null;
        }
    });

    // ================= 2. BLOCK: WOO CONFIGURATOR WIDGET =================
    registerBlockType('wooconfig/widget', {
        title: __('Configurador de Compra (WooConfig)', 'woo-config'),
        description: __('Widget completo de configuración de pedidos de indumentaria deportiva (cantidades, adicionales, personalizador y subida de archivos).', 'woo-config'),
        icon: 'cart',
        category: 'woocommerce',
        keywords: [__('configurador', 'woo-config'), __('wooconfig', 'woo-config'), __('personalizador', 'woo-config'), __('equipo', 'woo-config')],
        supports: {
            align: ['wide', 'full'],
            html: false
        },
        edit: function() {
            return el(
                'div',
                {
                    className: 'wooconfig-editor-widget-preview',
                    style: {
                        padding: '18px',
                        background: '#f8fafc',
                        border: '2px dashed #94ec00',
                        borderRadius: '10px',
                        textAlign: 'center'
                    }
                },
                el('div', { style: { fontSize: '20px', marginBottom: '6px' } }, '⚡'),
                el('strong', { style: { fontSize: '15px', color: '#0f172a', display: 'block' } }, __('Configurador de Compra WooConfig', 'woo-config')),
                el('p', { style: { fontSize: '12px', color: '#64748b', margin: '4px 0 0' } }, __('Este bloque renderiza el configurador completo de compras y adicionales en la página de producto.', 'woo-config'))
            );
        },
        save: function() {
            return null;
        }
    });

    // ================= 3. BLOCK: PRECIO MAYORISTA =================
    registerBlockType('wooconfig/wholesale-price', {
        title: __('Precio Mayorista (WooConfig)', 'woo-config'),
        description: __('Etiqueta con precio mayorista dinámico y botón de información emergente para el producto actual.', 'woo-config'),
        icon: 'tag',
        category: 'woocommerce',
        keywords: [__('mayorista', 'woo-config'), __('precio mayorista', 'woo-config'), __('descuento', 'woo-config'), __('cafu', 'woo-config')],
        supports: {
            align: ['left', 'center', 'right', 'wide'],
            html: false
        },
        attributes: {
            align: {
                type: 'string',
                default: 'left'
            }
        },
        edit: function(props) {
            return el(
                'div',
                {
                    className: 'wooconfig-wholesale-price-tag',
                    style: {
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: '6px',
                        padding: '4px 10px',
                        background: '#f8fafc',
                        border: '1px solid #e2e8f0',
                        borderRadius: '6px',
                        fontSize: '13px'
                    }
                },
                el('span', { style: { fontWeight: '700', color: '#64748b', fontSize: '11px', textTransform: 'uppercase' } }, __('x mayor:', 'woo-config')),
                el('strong', { style: { fontWeight: '800', color: '#0f172a' } }, '$19.791'),
                el('button', {
                    type: 'button',
                    style: {
                        width: '18px',
                        height: '18px',
                        borderRadius: '50%',
                        background: '#0f172a',
                        color: '#94ec00',
                        border: 'none',
                        fontSize: '11px',
                        fontWeight: '800',
                        cursor: 'default',
                        display: 'inline-flex',
                        alignItems: 'center',
                        justifyContent: 'center',
                        lineHeight: '1'
                    }
                }, 'i')
            );
        },
        save: function() {
            return null;
        }
    });

})(window.wp);
