/**
 * app.js — спільні утиліти для форми та дашборду
 * Всі компоненти імпортують звідси escapeHtml, apiFetch, showBanner
 */

'use strict';

/**
 * Екранує HTML-спецсимволи.
 * ЗАВЖДИ використовувати для вставки тексту з БД у HTML.
 */
function escapeHtml(str) {
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

/**
 * Fetch-обгортка з JSON Content-Type.
 * Кидає Error при HTTP-помилці або якщо відповідь ok:false.
 */
async function apiFetch(url, opts = {}) {
  const res = await fetch(url, {
    headers: { 'Content-Type': 'application/json' },
    ...opts,
  });

  if (!res.ok) {
    throw new Error(`HTTP ${res.status}: ${res.statusText}`);
  }

  const data = await res.json();

  if (data.ok === false) {
    throw new Error(data.error || 'Unknown server error');
  }

  return data;
}

/**
 * Показує банер повідомлення.
 * type: 'error' | 'success' | 'warning' | 'info'
 */
function showBanner(msg, type = 'error') {
  const el = document.getElementById('banner');
  if (!el) return;
  el.textContent = msg;   // textContent — не innerHTML!
  el.className = `banner banner-${type}`;
  el.hidden = false;

  // Автоматично ховати success/info через 4 секунди
  if (type === 'success' || type === 'info') {
    clearTimeout(el._timer);
    el._timer = setTimeout(() => { el.hidden = true; }, 4000);
  }
}

function hideBanner() {
  const el = document.getElementById('banner');
  if (el) el.hidden = true;
}

/**
 * Генерує унікальний batch_id для поточної сесії форми.
 * Формат: B-<timestamp>
 */
function genBatchId() {
  return `B-${Date.now()}`;
}

/**
 * Форматує дату у вигляді MM-DD-YYYY (формат що очікує форма).
 */
function formatDateInput(date) {
  const d = date instanceof Date ? date : new Date(date);
  const mm = String(d.getMonth() + 1).padStart(2, '0');
  const dd = String(d.getDate()).padStart(2, '0');
  return `${mm}-${dd}-${d.getFullYear()}`;
}

/**
 * Повертає CSS-клас для кольорового відображення KPI%.
 * >= 90% → green, >= 70% → orange, < 70% → red
 */
function kpiClass(value) {
  const v = parseFloat(value);
  if (v >= 90) return 'kpi-good';
  if (v >= 70) return 'kpi-warn';
  return 'kpi-bad';
}
