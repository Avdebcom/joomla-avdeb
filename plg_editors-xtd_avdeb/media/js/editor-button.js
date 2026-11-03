/**
 * AVDEB Products for Joomla — editor button. Opens a small dialog and inserts an
 * {avdeb-products …}, {avdeb-product …} or {avdeb-button …} tag at the cursor.
 * @license GNU General Public License version 2 or later
 */
import { JoomlaEditorButton } from 'editor-api';
import JoomlaDialog from 'joomla.dialog';

const t = (key, fallback) => (window.Joomla && Joomla.Text._(key, fallback)) || fallback;

/** Value for a tag attribute: double quotes are the delimiter and braces end the tag. */
const q = (value) => String(value).replace(/[{}]/g, '').replace(/["\u201C\u201D]/g, "'").trim();

const field = (label, control) => {
  const wrap = document.createElement('div');
  wrap.className = 'mb-3';
  const lab = document.createElement('label');
  lab.className = 'form-label';
  lab.textContent = label;
  lab.append(control);
  control.classList.add('mt-1');
  wrap.append(lab);
  return wrap;
};

const select = (name, options) => {
  const el = document.createElement('select');
  el.name = name;
  el.className = 'form-select';
  options.forEach(([value, label]) => el.add(new Option(label, value)));
  return el;
};

const input = (name, placeholder, type = 'text') => {
  const el = document.createElement('input');
  el.type = type;
  el.name = name;
  el.className = 'form-control';
  el.placeholder = placeholder;
  return el;
};

export const buildTag = ({ kind, link, layout, limit, label }) => {
  if (kind === 'button') {
    return `{avdeb-button href="${q(link || '/')}" label="${q(label || 'Shop on AVDEB')}"}`;
  }
  if (kind === 'product') {
    return `{avdeb-product ${q(link) ? `"${q(link)}"` : '""'}}`;
  }
  const parts = ['{avdeb-products'];
  if (q(link)) parts.push(`"${q(link)}"`);
  if (layout) parts.push(`layout="${layout}"`);
  if (limit && Number(limit) > 0) parts.push(`limit="${Number(limit)}"`);
  return `${parts.join(' ')}}`;
};

const openDialog = (editor) => {
  const form = document.createElement('form');
  form.className = 'p-3';
  const kind = select('kind', [
    ['products', t('PLG_EDITORS-XTD_AVDEB_KIND_PRODUCTS', 'Several products')],
    ['product', t('PLG_EDITORS-XTD_AVDEB_KIND_PRODUCT', 'One featured product')],
    ['button', t('PLG_EDITORS-XTD_AVDEB_KIND_BUTTON', 'Button')],
  ]);
  const link = input('link', 'https://avdeb.com/creators/your-shop/');
  const layout = select('layout', [
    ['', t('PLG_EDITORS-XTD_AVDEB_LAYOUT_DEFAULT', 'Default layout')],
    ['grid', 'Grid'], ['carousel', 'Carousel'], ['list', 'List'], ['spotlight', 'Spotlight'],
  ]);
  const limit = input('limit', '6', 'number');
  limit.min = '1';
  limit.max = '48';
  const label = input('label', 'Shop on AVDEB');
  const hint = document.createElement('p');
  hint.className = 'small text-muted mb-0';
  hint.textContent = t('PLG_EDITORS-XTD_AVDEB_HINT', 'Paste an avdeb.com creator, category or product link — or type a search. More options: see the AVDEB Products documentation.');

  const rows = {
    link: field(t('PLG_EDITORS-XTD_AVDEB_LINK', 'AVDEB link or search'), link),
    layout: field(t('PLG_EDITORS-XTD_AVDEB_LAYOUT', 'Layout'), layout),
    limit: field(t('PLG_EDITORS-XTD_AVDEB_LIMIT', 'Number of products'), limit),
    label: field(t('PLG_EDITORS-XTD_AVDEB_LABEL', 'Button text'), label),
  };
  form.append(field(t('PLG_EDITORS-XTD_AVDEB_KIND', 'Insert'), kind), rows.link, rows.layout, rows.limit, rows.label, hint);

  const sync = () => {
    rows.layout.hidden = kind.value !== 'products';
    rows.limit.hidden = kind.value !== 'products';
    rows.label.hidden = kind.value !== 'button';
    link.placeholder = {
      products: 'https://avdeb.com/creators/your-shop/',
      product: 'https://avdeb.com/store/cat-sticker/',
      button: '/creators/your-shop/',
    }[kind.value];
  };
  kind.addEventListener('change', sync);
  sync();

  const dialog = new JoomlaDialog({
    popupType: 'inline',
    textHeader: t('PLG_EDITORS-XTD_AVDEB_TITLE', 'Insert AVDEB products'),
    popupContent: form,
    width: '34rem',
    popupButtons: [
      {
        label: t('PLG_EDITORS-XTD_AVDEB_INSERT', 'Insert'),
        onClick: () => {
          editor.replaceSelection(buildTag({
            kind: kind.value, link: link.value, layout: layout.value, limit: limit.value, label: label.value,
          }));
          dialog.close();
        },
      },
      {
        label: t('JCANCEL', 'Cancel'),
        className: 'btn btn-secondary',
        onClick: () => dialog.close(),
      },
    ],
  });
  dialog.addEventListener('joomla-dialog:close', () => dialog.destroy());
  form.addEventListener('submit', (e) => e.preventDefault());
  dialog.show();
  link.focus();
};

JoomlaEditorButton.registerAction('avdeb-insert', (editor) => openDialog(editor));
