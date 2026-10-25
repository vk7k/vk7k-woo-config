# ESTADO — vk7k-woo-config

> Última actualización: 2026-10-25 · versión **1.9.7** · rama **flujo-configurador** (¡no es master!) · remoto `github.com/vk7k/vk7k-woo-config` · local 1.9.7, prod 1.9.7.

Leer antes de tocar nada: `..\AGENTS.md`. El **deploy a producción no lo hace un agente de código** (eso es de Hermes + `vk7k-wp-sync`).

**Trabajar en `flujo-configurador`** que es la rama con el desarrollo vivo; `master` está atrás. Confirmar con el usuario antes de mergear a master.

## Qué hace

Configurador de uniformes dentro del admin de WooCommerce: añade «prendas» (dorsal + nombre + talla + adicionales) a un pedido o cotización como líneas nativas de WooCommerce con metadatos propios, agrupadas por equipo (`_wooconfig_order_groups`).

Piezas: `includes/class-wc-config-order.php` (metabox, handler AJAX, promoción de borrador), `includes/class-wc-config-uploader.php` (subida de diseño), `assets/js/admin.js` (modal, tabla, parser), `assets/js/admin-import.js` (importador texto/CSV/XLSX), `assets/js/configurator.js` (front).

## Trampas que ya nos costaron un bug

1. **La pantalla «Añadir pedido» trabaja sobre un `auto-draft`.** Guardar prendas ahí y recargar hace que WooCommerce cree **otro** borrador: el pedido queda huérfano y las prendas «desaparecen». Por eso `promote_draft_order()` convierte el borrador en documento real **antes** de añadir nada, y la respuesta devuelve `order_was_draft` + `order_edit_url` para redirigir.
2. **Dorsal ANTES que talla en el parser**: los números 2, 4, 6 … 20 son a la vez tallas infantiles y dorsales; el negocio usa dorsal en la primera columna. Si se invierte, «10 JUAN PEREZ L» queda como talla 10.
3. **Filas fantasma**: la talla siempre tiene valor (M por defecto), así que validar `size || dorsal || name` guarda prendas vacías. Exigir **dorsal o nombre**.
4. **No meter PHP dentro de plantillas JS**: había un `<?php esc_html_e() ?>` dentro de un template string y la etiqueta salía cruda (1.9.6).
5. **El id del pedido no puede depender de un botón**: se borró un CTA y el `data()` quedó muerto. Hoy es `input hidden #wooconfig-modal-order-id` + `currentOrderId()`.
6. **Nonces + caché**: el 403 `-1` que bloqueaba las subidas era un nonce de una página servida por Varnish **43 h**. Al depurar AJAX que devuelve `-1`, comprobar primero la edad de la caché (`X-Cache-Age`) y probar desde una página recién cargada, no por permisos ni tamaño.
7. **Respetar el candado del otro plugin**: `content_editable()` se apoya en `WC_Order::is_editable()` y hay guardas en los 4 handlers (`save_group`, `add_item`, `update_item`, `delete_item`). Si `vk7k-woo-quotes` no está activo, rige la regla nativa: **cero dependencia dura**.
8. **Estado inicial por tipo**: un pedido creado desde «Añadir pedido» arrancaba en estado *Cotización*; ahora usa *Pendiente* (o el elegido), y sólo las cotizaciones nacen en *Cotización*.
9. Los metadatos `_wooconfig_*` se ocultan de la tabla de artículos con `woocommerce_order_item_get_formatted_meta_data`, pero **siguen legibles con `get_meta()`**: no romper esa vía.

## Convencion de metadatos (2026-10-25)

Cada plugin firma sus datos en la base: `_vk7k_wquote_*` (quotes), `_vk7k_wconfig_*` (config),
`_vk7k_winvoice_*` (factura). Nunca reutilizar prefijos ajenos ni los nativos de WooCommerce.

- Prohibido usar texto traducido como clave de metadato (`__( 'Talla' )`): si cambia el idioma
  los datos quedan ilegibles. Clave fija + etiqueta solo al mostrar.
- Cada plugin tiene `class-*-meta.php` (PREFIX, map(), get(), migrate()) y un comando
  `wp vk7k-... meta-migrate [--dry-run]`. Copia, no borra; idempotente.
- `wc_orders_meta` NO tiene `meta_id` (PK = `id`); `woocommerce_order_itemmeta` NO tiene
  `order_id` (se llega por `woocommerce_order_items`). Usar `SELECT 1` para chequear existencia.

## Cómo probar (Laragon)

```bash
cd /c/laragon/www/cafu
wp plugin list --fields=name,status,version | grep woo-config     # esperado: 1.9.7 active
php -l wp-content/plugins/vk7k-woo-config/includes/class-wc-config-order.php
node --check wp-content/plugins/vk7k-woo-config/assets/js/admin.js
```

Producto de prueba: **1598** «Camiseta de voleibol - Corte masculino» (variable, 26990). Flujo manual: *WooCommerce → Pedidos (o Cotizaciones) → Añadir → ➕ Añadir Prendas* → pegar filas tipo `10 JUAN PEREZ L` → guardar → comprobar que quedaron las líneas y que el documento ya no es `auto-draft`:

```bash
wp eval 'global $wpdb; print_r($wpdb->get_results("SELECT id,type,status,total_amount FROM {$wpdb->prefix}wc_orders ORDER BY id DESC LIMIT 5"));'
```

## Al terminar

Bump de versión en el header **y** en `WOOCONFIG_VERSION`, `php -l` / `node --check`, commit con el qué y el porqué, y actualizar este archivo con lo que falte.
