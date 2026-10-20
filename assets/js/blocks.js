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
    var useBlockProps = (wp.blockEditor && wp.blockEditor.useBlockProps) || function(p) { return p || {}; };
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

    // ================= 4. BLOCK: BANNER MAYORISTA =================
    var bannerIcon = el('svg', { width: 20, height: 20, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2 },
        el('rect', { x: 3, y: 3, width: 18, height: 18, rx: 2 }),
        el('circle', { cx: 8.5, cy: 8.5, r: 1.5 }),
        el('polyline', { points: '21 15 16 10 5 21' })
    );

    registerBlockType('wooconfig/wholesale-banner', {
        title: __('Banner Portal Mayorista (Cafú)', 'woo-config'),
        description: __('Banner responsivo oficial de la Zona Mayorista con enlace al portal de Mi Cuenta o catálogo.', 'woo-config'),
        icon: bannerIcon,
        category: 'woocommerce',
        keywords: [__('mayorista', 'woo-config'), __('banner', 'woo-config'), __('cafu', 'woo-config'), __('wholesale', 'woo-config')],
        supports: {
            align: ['left', 'center', 'right', 'wide', 'full'],
            html: false,
            spacing: {
                margin: true,
                padding: true
            },
            color: {
                background: true,
                text: false
            },
            __experimentalBorder: {
                radius: true,
                color: true,
                width: true,
                style: true
            }
        },
        attributes: {
            imageId: {
                type: 'number',
                default: 2171
            },
            imageUrl: {
                type: 'string',
                default: ''
            },
            linkUrl: {
                type: 'string',
                default: ''
            },
            align: {
                type: 'string',
                default: 'center'
            }
        },
        edit: function(props) {
            var attributes = props.attributes;
            var setAttributes = props.setAttributes;

            var defaultImg = attributes.imageUrl || '/wp-content/uploads/2026/10/banner-compras-sobre-50-unidades.jpg';
            var blockProps = useBlockProps({
                className: 'cafu-wholesale-home-banner-wrap'
            });

            return el(
                wp.element.Fragment,
                null,
                InspectorControls && el(
                    InspectorControls,
                    null,
                    el(
                        PanelBody,
                        { title: __('Ajustes del Banner Mayorista', 'woo-config'), initialOpen: true },
                        el(TextControl, {
                            label: __('URL de Destino (opcional)', 'woo-config'),
                            help: __('Por defecto redirige a Mi Cuenta / Login según corresponda.', 'woo-config'),
                            value: attributes.linkUrl || '',
                            onChange: function(newVal) {
                                setAttributes({ linkUrl: newVal });
                            }
                        }),
                        el(TextControl, {
                            label: __('ID de Imagen (Adjunto de Medios)', 'woo-config'),
                            value: attributes.imageId ? String(attributes.imageId) : '2171',
                            onChange: function(newVal) {
                                setAttributes({ imageId: parseInt(newVal, 10) || 0 });
                            }
                        })
                    )
                ),
                el(
                    'div',
                    blockProps,
                    el('img', {
                        src: defaultImg,
                        alt: 'Zona Mayorista Cafú',
                        style: {
                            width: '100%',
                            height: 'auto',
                            display: 'block'
                        }
                    })
                )
            );
        },
        save: function() {
            return null; // Rendered by PHP render_callback
        }
    });

    // ================= 5. BLOCK: CONTENIDO VISIBLE SOLO PARA MAYORISTAS =================
    var eyeIcon = el('svg', { width: 20, height: 20, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2 },
        el('path', { d: 'M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z' }),
        el('circle', { cx: 12, cy: 12, r: 3 })
    );

    var InnerBlocks = (wp.blockEditor && wp.blockEditor.InnerBlocks) || (wp.editor && wp.editor.InnerBlocks);

    registerBlockType('wooconfig/wholesale-content-visible', {
        title: __('Solo Mayoristas (Contenedor Condicional)', 'woo-config'),
        description: __('Contenedor que muestra sus bloques internos ÚNICAMENTE a usuarios logueados que son Clientes Mayoristas.', 'woo-config'),
        icon: eyeIcon,
        category: 'woocommerce',
        keywords: [__('mayorista', 'woo-config'), __('condicional', 'woo-config'), __('privado', 'woo-config'), __('cafu', 'woo-config')],
        supports: {
            align: ['wide', 'full'],
            html: false,
            spacing: {
                margin: true,
                padding: true,
                blockGap: true
            },
            color: {
                background: true,
                text: true,
                gradients: true
            },
            __experimentalBorder: {
                radius: true,
                color: true,
                width: true,
                style: true
            },
            layout: true
        },
        attributes: {},
        edit: function(props) {
            var blockProps = useBlockProps({
                className: 'wooconfig-conditional-wholesale-editor-wrap',
                style: {
                    border: '1.5px solid #94ec00',
                    borderRadius: '8px',
                    padding: '12px',
                    background: 'rgba(148, 236, 0, 0.03)',
                    position: 'relative'
                }
            });

            return el(
                'div',
                blockProps,
                el('div', {
                    style: {
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: '6px',
                        fontSize: '11px',
                        fontWeight: '800',
                        color: '#0f172a',
                        background: '#94ec00',
                        padding: '2px 8px',
                        borderRadius: '4px',
                        marginBottom: '8px',
                        textTransform: 'uppercase'
                    }
                }, '👑 Solo Clientes Mayoristas'),
                InnerBlocks ? el(InnerBlocks, {
                    templateLock: false,
                    placeholder: __('Inserta bloques aquí (solo se mostrarán a mayoristas autenticados)...', 'woo-config')
                }) : el('p', null, __('Editor de bloques internos no disponible.', 'woo-config'))
            );
        },
        save: function() {
            return InnerBlocks ? el(InnerBlocks.Content) : null;
        }
    });

    // ================= 6. BLOCK: CONTENIDO VISIBLE SOLO PARA VISITANTES / NO MAYORISTAS =================
    var eyeOffIcon = el('svg', { width: 20, height: 20, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2 },
        el('path', { d: 'M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24' }),
        el('line', { x1: 1, y1: 1, x2: 23, y2: 23 })
    );

    registerBlockType('wooconfig/wholesale-content-hidden', {
        title: __('Visitantes / No Mayoristas (Contenedor Condicional)', 'woo-config'),
        description: __('Contenedor que muestra sus bloques internos ÚNICAMENTE a visitantes públicos o usuarios que NO son mayoristas.', 'woo-config'),
        icon: eyeOffIcon,
        category: 'woocommerce',
        keywords: [__('publico', 'woo-config'), __('visitantes', 'woo-config'), __('no mayorista', 'woo-config'), __('teaser', 'woo-config')],
        supports: {
            align: ['wide', 'full'],
            html: false,
            spacing: {
                margin: true,
                padding: true,
                blockGap: true
            },
            color: {
                background: true,
                text: true,
                gradients: true
            },
            __experimentalBorder: {
                radius: true,
                color: true,
                width: true,
                style: true
            },
            layout: true
        },
        attributes: {},
        edit: function(props) {
            var blockProps = useBlockProps({
                className: 'wooconfig-conditional-not-wholesale-editor-wrap',
                style: {
                    border: '1.5px dashed #94a3b8',
                    borderRadius: '8px',
                    padding: '12px',
                    background: '#f8fafc',
                    position: 'relative'
                }
            });

            return el(
                'div',
                blockProps,
                el('div', {
                    style: {
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: '6px',
                        fontSize: '11px',
                        fontWeight: '800',
                        color: '#334155',
                        background: '#e2e8f0',
                        padding: '2px 8px',
                        borderRadius: '4px',
                        marginBottom: '8px',
                        textTransform: 'uppercase'
                    }
                }, '🌐 Solo Visitantes o No Mayoristas'),
                InnerBlocks ? el(InnerBlocks, {
                    templateLock: false,
                    placeholder: __('Inserta bloques aquí (invitación a mayoristas, banners públicos, etc.)...', 'woo-config')
                }) : el('p', null, __('Editor de bloques internos no disponible.', 'woo-config'))
            );
        },
        save: function() {
            return InnerBlocks ? el(InnerBlocks.Content) : null;
        }
    });

})(window.wp);
