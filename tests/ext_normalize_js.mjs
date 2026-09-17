#!/usr/bin/env node
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import vm from 'vm';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const formJs = fs.readFileSync(path.join(__dirname, '../resources/assets/form.js'), 'utf8');
const start = formJs.indexOf('// EXT_NORMALIZE_START');
const end = formJs.indexOf('// EXT_NORMALIZE_END');
if (start < 0 || end < 0 || end <= start) {
  console.error('EXT_NORMALIZE markers missing in form.js');
  process.exit(1);
}
const body = formJs.slice(start, end + '// EXT_NORMALIZE_END'.length);
const sandbox = { console, EXT_FALLBACK: undefined, normalizeExtList: undefined, asExtToken: undefined };
vm.createContext(sandbox);
vm.runInContext(
  `"use strict";\n` + body.replace(/\/\/ EXT_NORMALIZE_START/, '').replace(/\/\/ EXT_NORMALIZE_END/, '') +
  `\n;this.EXT_FALLBACK = EXT_FALLBACK; this.normalizeExtList = normalizeExtList; this.asExtToken = asExtToken;`,
  sandbox
);

const { normalizeExtList, asExtToken, EXT_FALLBACK } = sandbox;
let passed = 0;
let failed = 0;
function expect(label, actual, expected) {
  const ok = JSON.stringify(actual) === JSON.stringify(expected);
  if (ok) {
    passed++;
    console.log('ok ', label);
  } else {
    failed++;
    console.log('FAIL', label);
    console.log('  expected', expected);
    console.log('  actual  ', actual);
  }
}

expect(
  'junk settings object → EXT_FALLBACK',
  normalizeExtList({ rush: false, currency: 'KRW', title: 'DEFAULT', n: 2 }),
  EXT_FALLBACK.slice()
);
expect('csv string', normalizeExtList('stl, obj, 3mf'), ['STL', 'OBJ', '3MF']);
expect('array strings', normalizeExtList(['STL', 'OBJ']), ['STL', 'OBJ']);
expect('ext map keys', normalizeExtList({ STL: true, OBJ: false, PDF: 'on' }), ['STL', 'PDF']);
expect('rejects single letters / FALSE', asExtToken('F'), '');
expect('rejects FALSE', asExtToken('FALSE'), '');
expect('rejects KRW', asExtToken('KRW'), '');
expect('rejects digit', asExtToken('2'), '');
expect('accepts STL', asExtToken('stl'), 'STL');
expect('option object', normalizeExtList([{ value: 'dwg' }]), ['DWG']);
expect('FALSE stripped keeps STL', normalizeExtList(['STL', 'FALSE']), ['STL']);
expect('unknown token pollutes → fallback', normalizeExtList(['STL', 'NOPE']), EXT_FALLBACK.slice());

console.log(`\n${passed} passed, ${failed} failed`);
process.exit(failed === 0 ? 0 : 1);
