// Run: node tests/autosave-security.test.js
// No browser, network requests, or application database writes.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const saved = new Map([['autosave_self-rating-form', JSON.stringify({
  data: { csrf_token: 'previous-session-token', comments: 'Preserve my answers' },
  timestamp: Date.now()
})]]);
const fields = {
  csrf_token: {type: 'hidden', value: 'current-server-token'},
  comments: {type: 'textarea', value: ''}
};
const handlers = {};
const form = {
  id: '', elements: fields,
  getAttribute: () => 'self-rating-form',
  addEventListener: (name, callback) => { handlers[name] = callback; }
};
function node() {
  return {style: {}, classList: {add() {}},
    setAttribute() {}, appendChild() {}, addEventListener() {}, querySelector: () => node()};
}
const context = {
  console, Date, Object, JSON,
  setTimeout: () => 1, clearTimeout() {},
  localStorage: {
    getItem: key => saved.get(key) ?? null,
    setItem: (key, value) => saved.set(key, value),
    removeItem: key => saved.delete(key)
  },
  document: {readyState: 'complete', body: node(), createElement: node,
    querySelectorAll: () => [form]},
  window: {addEventListener() {}, matchMedia: () => ({matches: false})},
  FormData: class {
    *entries() { for (const [name, field] of Object.entries(fields)) yield [name, field.value]; }
  }
};
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../assets/js/auto-save.js'), 'utf8'), context);
assert.equal(fields.csrf_token.value, 'current-server-token', 'restoring a draft must preserve the fresh CSRF token');
assert.equal(fields.comments.value, 'Preserve my answers', 'saved employee answers must still restore');
assert.equal('csrf_token' in JSON.parse(saved.get('autosave_self-rating-form')).data, false,
  'legacy stored tokens must be removed without losing the draft');
handlers.input({target: fields.comments});
assert.equal('csrf_token' in JSON.parse(saved.get('autosave_self-rating-form')).data, false,
  'new autosave drafts must never persist session CSRF tokens');
assert.equal(JSON.parse(saved.get('autosave_self-rating-form')).data.comments, 'Preserve my answers');
console.log('PASS: autosave preserves the fresh CSRF token, cleans legacy tokens, and retains saved answers.');
