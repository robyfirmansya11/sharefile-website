import fs from 'node:fs';
import assert from 'node:assert/strict';
import ts from 'typescript';
import { createSSRApp, h } from 'vue';
import { renderToString } from '@vue/server-renderer';
const messages = JSON.parse(fs.readFileSync('resources/js/i18n/messages.json', 'utf8'));
for (const [key, entry] of Object.entries(messages)) {
    const placeholders = value => [...value.matchAll(/\{(\w+)\}/g)].map(match => match[1]).sort();
    for (const lang of ['en', 'zh-CN']) {
        assert.ok(entry[lang]?.trim(), `${key}: missing ${lang}`);
        assert.deepEqual(placeholders(entry[lang]), placeholders(entry.id ?? key), `${key}: inconsistent placeholders`);
    }
}
let source = fs.readFileSync('resources/js/i18n/index.ts','utf8').replace("import messages from './messages.json';", `const messages = ${JSON.stringify(messages)};`);
// Exercise the actual formatter and preference logic without a browser.
source = source.replace("from 'vue';", `from '${import.meta.resolve('vue')}';`);
const js = ts.transpileModule(source, { compilerOptions: { target: ts.ScriptTarget.ES2022, module: ts.ModuleKind.ES2022 } }).outputText;
const locale = await import('data:text/javascript;base64,'+Buffer.from(js).toString('base64'));
const englishApp = createSSRApp({ render: () => h('p', locale.t('Ganti nama')) });
const chineseApp = createSSRApp({ render: () => h('p', locale.t('Ganti nama')) });
locale.installLocale(englishApp, 'en', true);
locale.installLocale(chineseApp, 'zh-CN', true);
const rendered = await Promise.all([renderToString(englishApp), renderToString(chineseApp)]);
assert.deepEqual(rendered, ['<p>Rename</p>', '<p>重命名</p>']);
globalThis.document = { cookie: '', documentElement: { lang: '' } };
globalThis.location = { protocol: 'https:' };
const storage = new Map();
globalThis.localStorage = { getItem: key => storage.get(key) ?? null, setItem: (key,value) => storage.set(key,value) };
locale.initializeLocale(); assert.equal(locale.locale.value,'id');
locale.setLocale('zh-CN'); assert.equal(locale.t('Ganti nama'),'重命名'); assert.match(document.cookie,/Secure/);
locale.locale.value = 'id'; locale.initializeLocale(); assert.equal(locale.locale.value,'zh-CN');
locale.setLocale('en'); assert.equal(locale.t('delete.confirm',{count:3}),'Move 3 items to the Recycle Bin?');
assert.equal(locale.formatNumber(1234.5,1),'1,234.5');
locale.setLocale('id'); assert.equal(locale.formatNumber(1234.5,1),'1.234,5');
locale.setLocale('invalid'); assert.equal(locale.locale.value,'id');
assert.equal(locale.t('unknown message'),'unknown message');
console.log(`Locale checks passed: ${Object.keys(messages).length} messages, independent SSR locales, interpolation, persistence, fallback, and number formats.`);
