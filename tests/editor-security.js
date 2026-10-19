'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(__dirname + '/../blocks/affiliate-cards/index.js', 'utf8');

// Minimal DOM serialization boundary: attributes and text serialize independently.
const escapeText = (value) => String(value).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;');
const escapeAttribute = (value) => escapeText(value).replaceAll('"', '&quot;');
class DomElement {
    constructor(tag) { this.tag = tag; this.attributes = {}; this.children = []; }
    setAttribute(name, value) { this.attributes[name] = String(value); }
    set textContent(value) { this.children = [{ outerHTML: escapeText(value) }]; }
    appendChild(child) { this.children.push(child); }
    get innerHTML() { return this.children.map((child) => child.outerHTML).join(''); }
    get outerHTML() {
        const attrs = Object.entries(this.attributes).map(([name, value]) => ' ' + name + '="' + escapeAttribute(value) + '"').join('');
        return '<' + this.tag + attrs + '>' + this.innerHTML + '</' + this.tag + '>';
    }
}

function editor(content, response) {
    let subscription;
    let edit;
    const block = { name: 'core/paragraph', clientId: 'paragraph', attributes: { content } };
    const selector = { getBlocks: () => [block], getBlock: () => block, getSelectedBlockClientId: () => null };
    const dispatcher = { updateBlockAttributes: (id, attributes) => Object.assign(block.attributes, attributes) };
    const fetch = async () => ({ ok: true, json: async () => response });
    const wp = {
        blocks: { registerBlockType: (name, config) => { edit = config.edit; }, createBlock: () => { throw new Error('Unexpected card insertion'); } },
        blockEditor: { useBlockProps: () => ({}) },
        components: {},
        element: {
            createElement: (type, props, ...children) => ({ type, props: props || {}, children }),
            useEffect: () => {}, useRef: (current) => ({ current }), useState: (value) => [value, () => {}]
        },
        i18n: { __: (text) => text },
        domReady: (callback) => callback(),
        data: {
            subscribe: (callback) => { subscription = callback; },
            select: (store) => store === 'core/block-editor' ? selector : { getCurrentPostId: () => 1 },
            dispatch: (store) => store === 'core/block-editor' ? dispatcher : { createNotice: () => {} }
        }
    };
    const document = { createElement: (tag) => new DomElement(tag), createTextNode: (value) => ({ outerHTML: escapeText(value) }) };
    vm.runInNewContext(source, { window: { wp, fetch, setTimeout: (callback) => callback() }, document, fetch });
    return { block, trigger: () => subscription(), edit };
}

async function inline(content, response) {
    const app = editor(content, response);
    app.trigger();
    // Drain the fetch, optional hydration, Promise.all, and finally callbacks.
    for (let i = 0; i < 5; i++) { await new Promise(setImmediate); }
    return app.block.attributes.content;
}
function elements(node, type) {
    if (!node || typeof node !== 'object') { return []; }
    if (Array.isArray(node)) { return node.flatMap((child) => elements(child, type)); }
    return (node.type === type ? [node] : []).concat(elements(node.children, type));
}

(async () => {
    const title = '$& <img src=x onerror=alert(1)> "Titel" & Ende';
    const url = 'https://example.com/?x=" data-test="injected&tag=old-21#A';
    for (const token of ['B0SECURE01', 'last', 'heute']) {
        const response = token === 'B0SECURE01' ? { title, detailUrl: url }
            : token === 'last' ? { asin: 'B0SECURE01', title, detail_url: url }
                : [{ asin: 'B0SECURE01', title, detail_url: url }];
        const html = await inline('Vorher <strong>amazon:' + token + '</strong> nachher', response);
        assert.equal(html, 'Vorher <strong><a href="' + escapeAttribute(url) + '" rel="nofollow noopener sponsored">' + escapeText(title) + '</a> (Affiliate-Link)</strong> nachher', 'Inline insertion must preserve formatting and escape API/library values through the DOM');
        assert.ok(!html.includes('<img'), 'Titles must never introduce elements');
    }

    // Shorthand products with missing titles go through the hydration branch too.
    const hydrated = await inline('Produkt amazon:last verwenden', { asin: 'B0SECURE01', title: '', detail_url: url });
    assert.ok(hydrated.includes('href="' + escapeAttribute(url) + '"'), 'Hydrated shorthand URLs must use safe DOM attributes');

    for (const bad of ['javascript:alert(1)', 'JaVaScRiPt:alert(1)', 'data:image/svg+xml,<svg onload=alert(1)>', 'ftp://example.com/a', '//example.com/a', 'javascript&#58;alert(1)', 'java\nscript:alert(1)']) {
        for (const token of ['B0SECURE01', 'last']) {
            const response = token === 'last' ? { asin: 'B0SECURE01', title: 'Produkt', detail_url: bad } : { title: 'Produkt', detailUrl: bad };
            assert.ok((await inline('Im Text amazon:' + token + ' verwenden', response)).includes('<a href=""'), 'Invalid inline protocol must produce an empty link: ' + bad);
        }
        for (const stored of [{ items: [{ asin: 'B0SECURE01', detail_url: bad, image_url: bad }] }, { items: [{ asin: 'B0SECURE01' }], detailUrl: bad, images: [bad] }]) {
            const tree = editor('', {}).edit({ attributes: stored, setAttributes: () => {} });
            assert.equal(elements(tree, 'a')[0].props.href, '', 'Persisted editor CTA must reject invalid protocols');
            assert.equal(elements(tree, 'img').length, 0, 'Persisted editor images must reject invalid protocols');
        }
    }
    for (const url of ['http://example.com/?tag=old-21&x=%2f#A', 'HTTPS://example.com/ä?x=1&amp;y=2']) {
        const tree = editor('', {}).edit({ attributes: { items: [{ asin: 'B0SECURE01', detail_url: url, image_url: url }] }, setAttributes: () => {} });
        assert.equal(elements(tree, 'a')[0].props.href, url, 'Valid editor links must remain byte-identical');
        assert.equal(elements(tree, 'img')[0].props.src, url, 'Valid editor images must remain byte-identical');
    }
    console.log('ok');
})().catch((error) => { console.error(error); process.exitCode = 1; });
