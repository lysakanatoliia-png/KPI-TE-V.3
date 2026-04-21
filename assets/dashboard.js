'use strict';

/**
 * dashboard.js — логіка дашборду KPI
 * Залежить від: assets/app.js (escapeHtml, apiFetch, showBanner, kpiClass)
 */

let currentPage = 1;
const PER_PAGE  = 50;

// ─────────────────────────────────────
// Ініціалізація
// ─────────────────────────────────────
document.addEventListener('DOMContentLoaded', async () => {
  // Встановити значення дат за замовчуванням: поточний місяць
  const today = new Date();
  const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
  document.getElementById('from-input').value = toIsoDate(firstDay);
  document.getElementById('to-input').value   = toIsoDate(today);

  try {
    const cfg = await apiFetch('/kpi/api/config.php');
    renderRoomFilter(cfg.rooms);
  } catch (e) {
    showBanner('Помилка завантаження конфігу: ' + e.message);
  }

  // Завантажити дані одразу
  loadSummary();
  loadViolations();

  // Обробники фільтрів
  document.getElementById('btn-filter')?.addEventListener('click', () => {
    currentPage = 1;
    loadSummary();
    loadViolations();
  });

  document.getElementById('btn-daily')?.addEventListener('click', loadDaily);
});

function toIsoDate(d) {
  return d.toISOString().slice(0, 10);
}

// ─────────────────────────────────────
// Фільтр кімнат
// ─────────────────────────────────────
function renderRoomFilter(rooms) {
  const sel = document.getElementById('room-filter');
  sel.innerHTML = '<option value="">All Rooms</option>';
  for (const r of rooms) {
    const opt = document.createElement('option');
    opt.value = r.room_code;
    opt.textContent = r.room_name;
    sel.appendChild(opt);
  }
}

// ─────────────────────────────────────
// Summary таблиця
// ─────────────────────────────────────
async function loadSummary() {
  const from = document.getElementById('from-input').value;
  const to   = document.getElementById('to-input').value;
  const room = document.getElementById('room-filter').value;

  if (!from || !to) return showBanner('Вкажіть діапазон дат');

  const tbody = document.getElementById('summary-tbody');
  tbody.innerHTML = '<tr><td colspan="6" class="loading">Завантаження...</td></tr>';

  try {
    let url = `/kpi/api/reports.php?action=summary&from=${from}&to=${to}&page=${currentPage}&per_page=${PER_PAGE}`;
    if (room) url += `&room=${encodeURIComponent(room)}`;

    const data = await apiFetch(url);
    renderSummaryTable(data.data);
    renderPagination(data.page, data.per_page, data.data.length);
  } catch (e) {
    showBanner('Помилка summary: ' + e.message);
    tbody.innerHTML = '<tr><td colspan="6" class="empty">Помилка завантаження</td></tr>';
  }
}

function renderSummaryTable(rows) {
  const tbody = document.getElementById('summary-tbody');
  tbody.innerHTML = '';

  if (!rows || rows.length === 0) {
    tbody.innerHTML = '<tr><td colspan="6" class="empty">Немає даних за вибраний період</td></tr>';
    return;
  }

  // escapeHtml + template literal — захист від XSS
  tbody.innerHTML = rows.map(r => `
    <tr>
      <td>${escapeHtml(r.staff_name ?? r.staff_id)}</td>
      <td>${escapeHtml(r.room_code)}</td>
      <td class="${kpiClass(r.team_kpi)}">${r.team_kpi}%</td>
      <td class="${kpiClass(r.ind_kpi)}">${r.ind_kpi}%</td>
      <td class="${kpiClass(r.total_kpi)}"><strong>${r.total_kpi}%</strong></td>
    </tr>
  `).join('');
}

function renderPagination(page, perPage, count) {
  const wrap = document.getElementById('pagination');
  if (!wrap) return;
  wrap.innerHTML = '';

  const prev = document.createElement('button');
  prev.className = 'page-btn';
  prev.textContent = '← Prev';
  prev.disabled = page <= 1;
  prev.addEventListener('click', () => { currentPage--; loadSummary(); });
  wrap.appendChild(prev);

  const info = document.createElement('span');
  info.textContent = `Page ${page}`;
  wrap.appendChild(info);

  const next = document.createElement('button');
  next.className = 'page-btn';
  next.textContent = 'Next →';
  next.disabled = count < perPage;
  next.addEventListener('click', () => { currentPage++; loadSummary(); });
  wrap.appendChild(next);
}

// ─────────────────────────────────────
// Топ порушень
// ─────────────────────────────────────
async function loadViolations() {
  const from = document.getElementById('from-input').value;
  const to   = document.getElementById('to-input').value;
  const room = document.getElementById('room-filter').value;

  if (!from || !to) return;

  const tbody = document.getElementById('violations-tbody');
  tbody.innerHTML = '<tr><td colspan="4" class="loading">Завантаження...</td></tr>';

  try {
    let url = `/kpi/api/reports.php?action=violations&from=${from}&to=${to}&limit=20`;
    if (room) url += `&room=${encodeURIComponent(room)}`;

    const data = await apiFetch(url);
    renderViolationsTable(data.data);
  } catch (e) {
    tbody.innerHTML = '<tr><td colspan="4" class="empty">Помилка завантаження</td></tr>';
  }
}

function renderViolationsTable(rows) {
  const tbody = document.getElementById('violations-tbody');
  tbody.innerHTML = '';

  if (!rows || rows.length === 0) {
    tbody.innerHTML = '<tr><td colspan="4" class="empty">Порушень немає</td></tr>';
    return;
  }

  tbody.innerHTML = rows.map(r => `
    <tr>
      <td>${escapeHtml(r.indicator_text)}</td>
      <td>${escapeHtml(r.category)}</td>
      <td>${escapeHtml(r.scope)}</td>
      <td><strong>${r.violation_count}</strong></td>
    </tr>
  `).join('');
}

// ─────────────────────────────────────
// Денний звіт
// ─────────────────────────────────────
async function loadDaily() {
  const date = document.getElementById('daily-date').value;
  const room = document.getElementById('daily-room').value;

  if (!date) return showBanner('Оберіть дату для денного звіту', 'warning');
  if (!room) return showBanner('Оберіть кімнату для денного звіту', 'warning');

  const tbody = document.getElementById('daily-tbody');
  tbody.innerHTML = '<tr><td colspan="7" class="loading">Завантаження...</td></tr>';

  try {
    const data = await apiFetch(
      `/kpi/api/reports.php?action=daily&date=${date}&room=${encodeURIComponent(room)}`
    );
    renderDailyTable(data.data);
  } catch (e) {
    tbody.innerHTML = '<tr><td colspan="7" class="empty">Помилка завантаження</td></tr>';
    showBanner('Помилка денного звіту: ' + e.message);
  }
}

function renderDailyTable(rows) {
  const tbody = document.getElementById('daily-tbody');
  tbody.innerHTML = '';

  if (!rows || rows.length === 0) {
    tbody.innerHTML = '<tr><td colspan="7" class="empty">Записів немає</td></tr>';
    return;
  }

  tbody.innerHTML = rows.map(r => `
    <tr>
      <td>${escapeHtml(r.slot_code)}</td>
      <td>${escapeHtml(r.staff_name ?? r.staff_id)}</td>
      <td>${escapeHtml(r.scope)}</td>
      <td>${escapeHtml(r.indicator_text)}</td>
      <td>${r.check_value == 1 ? '<span class="kpi-good">Yes</span>' : '<span class="kpi-bad">No</span>'}</td>
      <td>${escapeHtml(String(r.weight))}</td>
      <td>${escapeHtml(r.comment ?? '')}</td>
    </tr>
  `).join('');
}
