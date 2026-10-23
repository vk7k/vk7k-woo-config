/**
 * WooConfig — Importador de planillas (pegar o subir archivo).
 *
 * Objetivo: que una tabulación de Excel/Sheets, un CSV o un .xlsx se conviertan en
 * filas (dorsal / nombre / talla / adicionales) sin adivinar mal.
 *
 * Regla clave del parser (bug corregido): un número al inicio de la fila es DORSAL,
 * no talla. Antes, un dorsal 10 se interpretaba como talla 10 y el dorsal se perdía.
 */
(function (window) {
    'use strict';

    var SIZES_LETTER = ['XXXL', 'XXL', 'XL', 'XS', 'XXS', 'L', 'M', 'S', '2XL', '3XL', '4XL', '5XL'];
    var SIZES_NUM = ['2', '4', '6', '8', '10', '12', '14', '16', '18', '20'];

    var ADICIONALES = [
        ['manga larga', 'manga_larga'], ['manga_larga', 'manga_larga'], ['mangalarga', 'manga_larga'], ['ml', 'manga_larga'],
        ['cuello polo', 'cuello_polo'], ['cuello piqué', 'cuello_polo'], ['cuello pique', 'cuello_polo'],
        ['cuello_polo', 'cuello_polo'], ['piqué', 'cuello_polo'], ['pique', 'cuello_polo'], ['polo', 'cuello_polo']
    ];

    function normalize(str) {
        return String(str == null ? '' : str)
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function isLetterSize(cell) {
        return SIZES_LETTER.indexOf(String(cell).trim().toUpperCase()) !== -1;
    }

    function isNumericSize(cell) {
        return SIZES_NUM.indexOf(String(cell).trim().toUpperCase()) !== -1;
    }

    function isNumeric(cell) {
        return /^\d{1,3}$/.test(String(cell).trim());
    }

    function matchAdicionales(cell) {
        var lower = normalize(cell);
        if (!lower) { return []; }
        var out = [];
        for (var i = 0; i < ADICIONALES.length; i++) {
            if (lower.indexOf(ADICIONALES[i][0]) !== -1 && out.indexOf(ADICIONALES[i][1]) === -1) {
                out.push(ADICIONALES[i][1]);
            }
        }
        return out;
    }

    /** Divide una línea probando los separadores habituales. */
    function splitLine(line) {
        var raw = String(line).replace(/\u00a0/g, ' ');
        if (raw.indexOf('\t') !== -1) { return raw.split('\t'); }
        if (raw.indexOf('|') !== -1) { return raw.split('|'); }
        if (raw.indexOf(';') !== -1) { return raw.split(';'); }
        if ((raw.match(/,/g) || []).length >= 2) { return raw.split(','); }
        if (raw.indexOf(' - ') !== -1) { return raw.split(' - '); }
        if (/\s{2,}/.test(raw)) { return raw.split(/\s{2,}/); }
        // Último recurso: espacios simples (nombres con un solo espacio se unen luego).
        return raw.split(/\s+/);
    }

    function cleanCells(cells) {
        var out = [];
        for (var i = 0; i < cells.length; i++) {
            var v = String(cells[i] == null ? '' : cells[i]).trim();
            if (v !== '') { out.push(v); }
        }
        return out;
    }

    /** ¿La primera fila es una cabecera? Devuelve el mapeo por nombres. */
    function detectHeader(cells) {
        var map = {};
        var hits = 0;
        for (var i = 0; i < cells.length; i++) {
            var h = normalize(cells[i]);
            if (/^(dorsal|dorsales|numero|num|n|#|id|camiseta)$/.test(h)) { map.dorsal = i; hits++; }
            else if (/^(nombre|nombres|jugador|jugadora|jugadores|alias|apellido|apellidos|deportista)$/.test(h)) { map.name = i; hits++; }
            else if (/^(talla|tallas|talle|talles|size|medida|tamano)$/.test(h)) { map.size = i; hits++; }
            else if (/^(adicional|adicionales|extras|extra|opciones|detalle|detalles|personalizacion)$/.test(h)) { map.ads = i; hits++; }
        }
        return hits >= 2 ? map : null;
    }

    /**
     * Asigna celdas a un jugador.
     * @param {Array} cells   Columnas de la fila.
     * @param {Object} mapping Mapeo explícito {dorsal,name,size,ads} con índices.
     */
    function assign(cells, mapping) {
        var row = { dorsal: '', name: '', size: '', adicionales: [], issues: [], map: {} };
        var used = {};

        if (mapping && (typeof mapping.dorsal === 'number' || typeof mapping.name === 'number' || typeof mapping.size === 'number' || typeof mapping.ads === 'number')) {
            if (typeof mapping.dorsal === 'number' && cells[mapping.dorsal] !== undefined) { row.dorsal = String(cells[mapping.dorsal]).trim(); }
            if (typeof mapping.name === 'number' && cells[mapping.name] !== undefined) { row.name = String(cells[mapping.name]).trim(); }
            if (typeof mapping.size === 'number' && cells[mapping.size] !== undefined) { row.size = String(cells[mapping.size]).trim().toUpperCase(); }
            if (typeof mapping.ads === 'number' && cells[mapping.ads] !== undefined) { row.adicionales = matchAdicionales(cells[mapping.ads]); }
            row.map = { dorsal: mapping.dorsal, name: mapping.name, size: mapping.size, ads: mapping.ads };
        } else {
            var i;

            // 1) Adicionales: texto reconocible
            for (i = 0; i < cells.length; i++) {
                if (used[i]) { continue; }
                var ads = matchAdicionales(cells[i]);
                if (ads.length) { row.adicionales = ads; used[i] = true; row.map.ads = i; }
            }

            // 2) Talla en letra (S, M, L, XL, 2XL...): inequívoca
            var sizeIdx = -1;
            for (i = 0; i < cells.length; i++) {
                if (!used[i] && isLetterSize(cells[i])) { sizeIdx = i; used[i] = true; row.size = String(cells[i]).trim().toUpperCase(); row.map.size = i; break; }
            }

            // 3) Números: el primero de la fila es dorsal; una talla numérica solo cuenta
            //    como talla si está al final y ya hay un dorsal (o si es la única celda numérica
            //    en posición final).
            var numericIdx = [];
            for (i = 0; i < cells.length; i++) {
                if (!used[i] && isNumeric(cells[i])) { numericIdx.push(i); }
            }
            if (numericIdx.length === 1) {
                var only = numericIdx[0];
                if (sizeIdx < 0 && only === cells.length - 1 && cells.length > 1) {
                    row.size = String(cells[only]).trim().toUpperCase(); // talla numérica sin dorsal
                    used[only] = true; row.map.size = only;
                } else {
                    row.dorsal = String(cells[only]).trim();            // dorsal sin talla
                    used[only] = true; row.map.dorsal = only;
                }
            } else if (numericIdx.length >= 2) {
                row.dorsal = String(cells[numericIdx[0]]).trim();       // el primero es el dorsal
                used[numericIdx[0]] = true; row.map.dorsal = numericIdx[0];
                if (sizeIdx < 0) {
                    var lastNum = numericIdx[numericIdx.length - 1];
                    row.size = String(cells[lastNum]).trim().toUpperCase();
                    used[lastNum] = true; row.map.size = lastNum;
                }
            }

            // 4) Nombre: lo que quede con letras (se une si venía partido por espacios)
            var nameParts = [];
            for (i = 0; i < cells.length; i++) {
                if (used[i]) { continue; }
                if (/[a-záéíóúüñ]/i.test(cells[i])) { nameParts.push(String(cells[i]).trim()); used[i] = true; }
            }
            if (nameParts.length) { row.name = nameParts.join(' '); }
        }

        row.dorsal = String(row.dorsal || '').trim();
        row.name = String(row.name || '').trim();
        row.size = String(row.size || '').trim().toUpperCase();
        if (row.dorsal && !/^\d{1,3}$/.test(row.dorsal)) { row.issues.push('dorsal no numérico'); }
        if (!row.name) { row.issues.push('sin nombre'); }
        if (!row.size) { row.issues.push('sin talla'); row.size = 'M'; }
        else if (!isLetterSize(row.size) && !isNumericSize(row.size)) { row.issues.push('talla no reconocida (' + row.size + ')'); }
        return row;
    }

    /**
     * Parsea texto pegado/leído.
     * @returns {{rows:Array, cols:number, mapping:Object|null, header:boolean}}
     */
    function parseText(text, mapping) {
        var lines = String(text == null ? '' : text).split(/\r?\n/);
        var rows = [];
        var headerMap = null;
        var cols = 0;

        for (var i = 0; i < lines.length; i++) {
            var line = lines[i];
            if (!line || !line.trim()) { continue; }
            var cells = cleanCells(splitLine(line));
            if (!cells.length) { continue; }
            cols = Math.max(cols, cells.length);

            if (i === 0 || !rows.length) {
                var h = detectHeader(cells);
                if (h) { headerMap = h; continue; }
            }
            rows.push(assign(cells, mapping || headerMap));
        }

        var detected = mapping || headerMap || (rows[0] && rows[0].map) || null;
        return { rows: rows, cols: cols, mapping: detected, header: !!headerMap };
    }

    /* ─────────────────────────── Lectura de archivos ─────────────────────────── */

    function decodeXmlEntities(str) {
        return String(str == null ? '' : str)
            .replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"')
            .replace(/&apos;/g, "'").replace(/&#(\d+);/g, function (m, d) { return String.fromCharCode(parseInt(d, 10)); })
            .replace(/&amp;/g, '&');
    }

    function colLetterToIndex(ref) {
        var letters = String(ref || '').replace(/[^A-Z]/gi, '').toUpperCase();
        var n = 0;
        for (var i = 0; i < letters.length; i++) { n = n * 26 + (letters.charCodeAt(i) - 64); }
        return Math.max(0, n - 1);
    }

    /** Descomprime un ZIP en memoria (necesita DecompressionStream). */
    async function unzip(buffer) {
        var u8 = new Uint8Array(buffer);
        var dv = new DataView(buffer);
        var eocd = -1;
        for (var i = u8.length - 22; i >= 0 && i > u8.length - 66000; i--) {
            if (dv.getUint32(i, true) === 0x06054b50) { eocd = i; break; }
        }
        if (eocd < 0) { throw new Error('El archivo no es un ZIP/XLSX válido.'); }
        var count = dv.getUint16(eocd + 10, true);
        var off = dv.getUint32(eocd + 16, true);
        var files = {};
        for (var k = 0; k < count; k++) {
            if (dv.getUint32(off, true) !== 0x02014b50) { break; }
            var method = dv.getUint16(off + 10, true);
            var compSize = dv.getUint32(off + 20, true);
            var nameLen = dv.getUint16(off + 28, true);
            var extraLen = dv.getUint16(off + 30, true);
            var commentLen = dv.getUint16(off + 32, true);
            var localOff = dv.getUint32(off + 42, true);
            var name = new TextDecoder().decode(u8.slice(off + 46, off + 46 + nameLen));
            var lNameLen = dv.getUint16(localOff + 26, true);
            var lExtraLen = dv.getUint16(localOff + 28, true);
            var start = localOff + 30 + lNameLen + lExtraLen;
            files[name] = { method: method, data: u8.slice(start, start + compSize) };
            off += 46 + nameLen + extraLen + commentLen;
        }
        return files;
    }

    async function inflate(fileEntry) {
        if (!fileEntry) { return ''; }
        if (fileEntry.method === 0) { return new TextDecoder().decode(fileEntry.data); }
        if (typeof DecompressionStream === 'undefined') {
            throw new Error('Este navegador no puede leer .xlsx; guarda la planilla como CSV.');
        }
        var stream = new Blob([fileEntry.data]).stream().pipeThrough(new DecompressionStream('deflate-raw'));
        return await new Response(stream).text();
    }

    /** Convierte un .xlsx en texto TSV (una fila por línea). */
    async function xlsxToText(buffer) {
        var files = await unzip(buffer);
        var shared = [];
        if (files['xl/sharedStrings.xml']) {
            var ssXml = await inflate(files['xl/sharedStrings.xml']);
            var ssDoc = new DOMParser().parseFromString(ssXml, 'application/xml');
            Array.prototype.forEach.call(ssDoc.getElementsByTagName('si'), function (si) {
                var texts = si.getElementsByTagName('t');
                var buf = '';
                for (var t = 0; t < texts.length; t++) { buf += texts[t].textContent; }
                shared.push(buf);
            });
        }

        var sheetName = null;
        Object.keys(files).forEach(function (n) {
            if (!sheetName && /^xl\/worksheets\/sheet1\.xml$/.test(n)) { sheetName = n; }
            if (!sheetName && /^xl\/worksheets\/sheet\d+\.xml$/.test(n)) { sheetName = n; }
        });
        if (!sheetName) { throw new Error('El .xlsx no tiene hojas legibles.'); }

        var sheetXml = await inflate(files[sheetName]);
        var doc = new DOMParser().parseFromString(sheetXml, 'application/xml');
        var out = [];
        Array.prototype.forEach.call(doc.getElementsByTagName('row'), function (rowEl) {
            var cells = [];
            Array.prototype.forEach.call(rowEl.getElementsByTagName('c'), function (c) {
                var idx = colLetterToIndex(c.getAttribute('r'));
                var type = c.getAttribute('t');
                var value = '';
                if (type === 's') {
                    var vEl = c.getElementsByTagName('v')[0];
                    value = vEl ? (shared[parseInt(vEl.textContent, 10)] || '') : '';
                } else if (type === 'inlineStr') {
                    var isEl = c.getElementsByTagName('t')[0];
                    value = isEl ? isEl.textContent : '';
                } else {
                    var vv = c.getElementsByTagName('v')[0];
                    value = vv ? vv.textContent : '';
                }
                cells[idx] = String(value).replace(/[\t\r\n]+/g, ' ').trim();
            });
            for (var i = 0; i < cells.length; i++) { if (cells[i] === undefined) { cells[i] = ''; } }
            var line = cells.join('\t').replace(/\t+$/, '');
            if (line.replace(/\t/g, '').trim() !== '') { out.push(line); }
        });
        return out.join('\n');
    }

    /** Convierte CSV/TSV con comillas a texto TSV (respeta "RÍOS, ANA" y ""escapadas""). */
    function csvToTsv(text) {
        var sep = null;
        var firstLine = String(text).split(/\r?\n/)[0] || '';
        var counts = {
            ';': (firstLine.match(/;/g) || []).length,
            ',': (firstLine.match(/,/g) || []).length,
            '\t': (firstLine.match(/\t/g) || []).length
        };
        if (counts['\t'] >= counts[';'] && counts['\t'] >= counts[','] && counts['\t'] > 0) { sep = '\t'; }
        else if (counts[';'] >= counts[',']) { sep = ';'; }
        else { sep = ','; }

        var rows = [], row = [], cur = '', inQuotes = false;
        for (var i = 0; i < text.length; i++) {
            var ch = text[i];
            if (inQuotes) {
                if (ch === '"') {
                    if (text[i + 1] === '"') { cur += '"'; i++; } else { inQuotes = false; }
                } else { cur += ch; }
            } else if (ch === '"') {
                inQuotes = true;
            } else if (ch === sep) {
                row.push(cur); cur = '';
            } else if (ch === '\n') {
                row.push(cur); rows.push(row); row = []; cur = '';
            } else if (ch !== '\r') {
                cur += ch;
            }
        }
        if (cur !== '' || row.length) { row.push(cur); rows.push(row); }

        return rows.map(function (r) {
            return r.map(function (c) { return String(c).trim(); }).join('\t');
        }).join('\n');
    }

    /** Lee un File del input y devuelve texto (TSV para xlsx). */
    async function fileToText(file) {
        var name = (file.name || '').toLowerCase();
        if (/\.xlsx$/.test(name) || /\.xlsm$/.test(name)) {
            return await xlsxToText(await file.arrayBuffer());
        }
        var raw = await file.text();
        if (/\.csv$/.test(name)) { return csvToTsv(raw); }
        return raw;
    }

    window.WooConfigImport = {
        SIZES_LETTER: SIZES_LETTER,
        SIZES_NUM: SIZES_NUM,
        normalize: normalize,
        splitLine: splitLine,
        detectHeader: detectHeader,
        assign: assign,
        parseText: parseText,
        fileToText: fileToText,
        csvToTsv: csvToTsv,
        xlsxToText: xlsxToText
    };
})(window);
