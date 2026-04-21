'use strict';

/**
 * form.js — логіка форми KPI V3
 * UX відповідає V2 (start screen, themes, multi-dropdown, Yes/No seg buttons)
 * Backend: /api/kpi.php?action=saveBatch (один POST, одна транзакція)
 */

// ─── State ───────────────────────────────────────────────────────
let config        = null;   // { rooms, slots, staff, indicators }
let selectedRoom  = null;
let selectedSlot  = null;
let presentStaff  = [];     // [{ StaffId, StaffName }]
let teamConfirmed = false;
let indBreach     = false;  // чи були порушення у Individual
let selectedIndStaff = [];  // [{ StaffId, StaffName }]
let teamItems     = [];     // зібрані Team результати
let indItems      = {};     // { staffId: [ items ] }
let batchId       = genBatchId();

// ─── DOM refs ─────────────────────────────────────────────────────
const startScreen   = document.getElementById('startScreen');
const startBtn      = document.getElementById('startBtn');
const formCard      = document.getElementById('formCard');

const roomToggle    = document.getElementById('roomToggle');
const roomLabel     = document.getElementById('roomLabel');
const roomPanel     = document.getElementById('roomPanel');
const roomErr       = document.getElementById('roomErr');

const slotToggle    = document.getElementById('slotToggle');
const slotLabel     = document.getElementById('slotLabel');
const slotPanel     = document.getElementById('slotPanel');
const slotErr       = document.getElementById('slotErr');

const dateInput     = document.getElementById('dateInput');
const dateErr       = document.getElementById('dateErr');

const staffToggle   = document.getElementById('staffToggle');
const staffLabel    = document.getElementById('staffLabel');
const staffPanel    = document.getElementById('staffPanel');
const staffErr      = document.getElementById('staffErr');

const teamBlock     = document.getElementById('teamBlock');
const teamPrepWrap  = document.getElementById('teamPrepWrap');
const teamPrepBtn   = document.getElementById('teamPrepBtn');
const teamLoading   = document.getElementById('teamLoading');
const teamTableWrap = document.getElementById('teamTableWrap');
const teamBody      = document.getElementById('teamBody');
const teamConfirmBtn= document.getElementById('teamConfirm');
const teamValMsg    = document.getElementById('teamValMsg');

const indGate       = document.getElementById('indGate');
const breachSeg     = document.getElementById('breachSeg');
const indBlock      = document.getElementById('indBlock');
const indStaffToggle= document.getElementById('indStaffToggle');
const indStaffLabel = document.getElementById('indStaffLabel');
const indStaffPanel = document.getElementById('indStaffPanel');
const indErr        = document.getElementById('indErr');
const indLoading    = document.getElementById('indLoading');
const indTableWrap  = document.getElementById('indTableWrap');
const indBody       = document.getElementById('indBody');
const indValMsg     = document.getElementById('indValMsg');
const finishIndBtn  = document.getElementById('finishInd');

const finishSection = document.getElementById('finishSection');
const finishBtn     = document.getElementById('finishBtn');
const msgEl         = document.getElementById('msg');

// ─── Helpers ──────────────────────────────────────────────────────
function show(el)  { el?.classList.remove('hidden'); }
function hide(el)  { el?.classList.add('hidden'); }
function showErr(el, msg) { if (!el) return; el.textContent = msg; show(el); }
function hideErr(el) { if (!el) return; el.textContent = ''; hide(el); }

function closeAllPanels(except) {
  [roomPanel, slotPanel, staffPanel, indStaffPanel].forEach(p => {
    if (p && p !== except) hide(p);
  });
}

function setBtnLoading(btn, on) {
  if (!btn) return;
  if (on) {
    btn._html = btn.innerHTML;
    btn.innerHTML = '<span class="spinner"></span><span style="margin-left:8px">Processing…</span>';
    btn.disabled = true;
  } else {
    if (btn._html) btn.innerHTML = btn._html;
    btn.disabled = false;
  }
}

function applyRoomTheme(roomCode) {
  document.body.classList.remove('theme-pink', 'theme-green', 'theme-blue');
  const code = (roomCode || '').toLowerCase();
  if (code === 'pink')  document.body.classList.add('theme-pink');
  if (code === 'green') document.body.classList.add('theme-green');
  if (code === 'blue')  document.body.classList.add('theme-blue');
}

function roomIsAdmin() {
  return config?.rooms?.find(r => r.room_code === selectedRoom)?.is_admin === 1;
}

// ─── Dropdown builder ────────────────────────────────────────────
function buildDropdown(panel, items, onSelect) {
  panel.innerHTML = '';
  items.forEach(({ value, label }) => {
    const div = document.createElement('div');
    div.className = 'opt';
    div.textContent = label;
    div.addEventListener('click', e => {
      e.stopPropagation();
      hide(panel);
      onSelect(value, label);
    });
    panel.appendChild(div);
  });
}

// Multi-checkbox dropdown (Staff)
function buildMultiDropdown(panel, items, selectedIds, onToggle, onDone) {
  panel.innerHTML = '';
  items.forEach(({ value, label }) => {
    const div = document.createElement('div');
    div.className = 'opt';
    const cb = document.createElement('input');
    cb.type = 'checkbox';
    cb.checked = selectedIds.includes(value);
    cb.addEventListener('change', () => onToggle(value, label, cb.checked));
    const span = document.createElement('span');
    span.textContent = label;
    div.appendChild(cb);
    div.appendChild(span);
    div.addEventListener('click', e => { if (e.target !== cb) { cb.checked = !cb.checked; cb.dispatchEvent(new Event('change')); } });
    panel.appendChild(div);
  });

  // Actions: Select All / Done
  const actions = document.createElement('div');
  actions.className = 'panel-actions';
  const allBtn = document.createElement('button');
  allBtn.className = 'mini-btn secondary';
  allBtn.textContent = 'All';
  allBtn.addEventListener('click', () => {
    panel.querySelectorAll('input[type=checkbox]').forEach(cb => { cb.checked = true; cb.dispatchEvent(new Event('change')); });
  });
  const doneBtn = document.createElement('button');
  doneBtn.className = 'mini-btn';
  doneBtn.textContent = 'Done';
  doneBtn.addEventListener('click', () => { hide(panel); if (onDone) onDone(); });
  actions.appendChild(allBtn);
  actions.appendChild(doneBtn);
  panel.appendChild(actions);
}

// Toggle panel on click
function makeToggle(toggle, panel) {
  toggle.addEventListener('click', e => {
    e.stopPropagation();
    const isHidden = panel.classList.contains('hidden');
    closeAllPanels(isHidden ? panel : null);
    if (isHidden) show(panel); else hide(panel);
  });
}

document.addEventListener('click', () => closeAllPanels(null));

// ─── Start Screen ─────────────────────────────────────────────────
startBtn.addEventListener('click', async () => {
  hide(startScreen);
  show(formCard);
  dateInput.valueAsDate = new Date();
  await loadConfig();
});

// ─── Load Config ──────────────────────────────────────────────────
async function loadConfig() {
  roomLabel.innerHTML  = '<span class="spinner"></span> Loading…';
  slotLabel.innerHTML  = '<span class="spinner"></span> Loading…';
  staffLabel.textContent = 'Select room first…';

  try {
    config = await apiFetch('/kpi/api/config.php');
    renderRoomDropdown();
    renderSlotDropdown();
  } catch(e) {
    roomLabel.textContent = 'Error loading config';
    msgEl.textContent = 'Config error: ' + e.message;
  }
}

// ─── Rooms ────────────────────────────────────────────────────────
function renderRoomDropdown() {
  const items = config.rooms.map(r => ({ value: r.room_code, label: r.room_name }));
  buildDropdown(roomPanel, items, (val, lbl) => {
    selectedRoom = val;
    roomLabel.textContent = lbl;
    hideErr(roomErr);
    applyRoomTheme(val);
    renderStaffDropdown();
    hideTeamSection();
  });
  makeToggle(roomToggle, roomPanel);
  roomLabel.textContent = 'Select room…';
}

// ─── Slots ────────────────────────────────────────────────────────
function renderSlotDropdown() {
  const items = [...config.slots]
    .sort((a,b) => a.sort_order - b.sort_order)
    .map(s => ({ value: s.slot_code, label: s.slot_label }));
  buildDropdown(slotPanel, items, (val, lbl) => {
    selectedSlot = val;
    slotLabel.textContent = lbl;
    hideErr(slotErr);
    hideTeamSection();
    maybeShowTeamBlock();
  });
  makeToggle(slotToggle, slotPanel);
  slotLabel.textContent = 'Select time…';
}

// ─── Staff (multi-checkbox) ───────────────────────────────────────
function renderStaffDropdown() {
  if (!selectedRoom || !config) return;

  const roomStaff = config.staff.filter(s => {
    if (!s.rooms) return true;
    return s.rooms.split(',').map(r => r.trim()).includes(selectedRoom);
  });

  presentStaff = [];
  staffLabel.textContent = 'Select staff…';

  const items = roomStaff.map(s => ({ value: s.staff_id, label: s.staff_name }));

  buildMultiDropdown(
    staffPanel, items,
    presentStaff.map(s => s.StaffId),
    (id, name, checked) => {
      if (checked) {
        if (!presentStaff.find(s => s.StaffId === id)) presentStaff.push({ StaffId: id, StaffName: name });
      } else {
        presentStaff = presentStaff.filter(s => s.StaffId !== id);
      }
      updateStaffLabel();
    },
    () => {
      updateStaffLabel();
      maybeShowTeamBlock();
    }
  );
  makeToggle(staffToggle, staffPanel);
}

function updateStaffLabel() {
  if (presentStaff.length === 0) {
    staffLabel.textContent = 'Select staff…';
  } else if (presentStaff.length === 1) {
    staffLabel.textContent = presentStaff[0].StaffName;
  } else {
    staffLabel.innerHTML = `${escapeHtml(presentStaff[0].StaffName)} <span class="badge">+${presentStaff.length - 1}</span>`;
  }
}

// ─── Show/hide Team block ─────────────────────────────────────────
function maybeShowTeamBlock() {
  if (selectedRoom && selectedSlot && presentStaff.length > 0) {
    show(teamBlock);
  }
}

function hideTeamSection() {
  teamConfirmed = false;
  hide(teamBlock);
  hide(teamPrepBtn.closest ? null : teamPrepWrap);
  show(teamPrepWrap);
  hide(teamTableWrap);
  hide(teamLoading);
  hide(indGate);
  hide(indBlock);
  hide(finishSection);
  teamItems = [];
  indItems  = {};
  msgEl.textContent = '';
}

// ─── Team Prep button ─────────────────────────────────────────────
teamPrepBtn.addEventListener('click', async () => {
  if (!validateHeader()) return;
  hide(teamPrepWrap);
  show(teamLoading);

  try {
    const indicators = getFilteredIndicators('Team');
    if (!indicators.length) {
      msgEl.textContent = 'No Team indicators found for this room/slot.';
      show(teamPrepWrap);
      hide(teamLoading);
      return;
    }
    renderIndicatorTable(teamBody, indicators);
    hide(teamLoading);
    show(teamTableWrap);
  } catch(e) {
    hide(teamLoading);
    show(teamPrepWrap);
    msgEl.textContent = 'Error: ' + e.message;
  }
});

// ─── Team Confirm ─────────────────────────────────────────────────
teamConfirmBtn.addEventListener('click', () => {
  const result = collectTableResults(teamBody);
  if (!result.valid) {
    showErr(teamValMsg, result.error);
    return;
  }
  hideErr(teamValMsg);
  teamItems = result.items;
  teamConfirmed = true;

  // Заблокувати таблицю
  teamTableWrap.querySelectorAll('.seg button, textarea').forEach(el => el.disabled = true);
  teamConfirmBtn.disabled = true;
  teamConfirmBtn.textContent = 'Confirmed ✓';

  // Показати Individual gate (або одразу Finish для Admin)
  if (roomIsAdmin()) {
    indItems = {};
    show(finishSection);
  } else {
    show(indGate);
  }
});

// ─── Individual breach gate ───────────────────────────────────────
breachSeg.querySelectorAll('button').forEach(btn => {
  btn.addEventListener('click', () => {
    breachSeg.querySelectorAll('button').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    indBreach = btn.dataset.val === 'yes';

    if (indBreach) {
      show(indBlock);
      renderIndStaffDropdown();
      hide(finishSection);
    } else {
      hide(indBlock);
      indItems = {};
      show(finishSection);
    }
  });
});

// ─── Individual staff dropdown ────────────────────────────────────
function renderIndStaffDropdown() {
  selectedIndStaff = [];
  indStaffLabel.textContent = 'Select staff…';

  const items = presentStaff.map(s => ({ value: s.StaffId, label: s.StaffName }));

  buildMultiDropdown(
    indStaffPanel, items, [],
    (id, name, checked) => {
      if (checked) {
        if (!selectedIndStaff.find(s => s.StaffId === id)) selectedIndStaff.push({ StaffId: id, StaffName: name });
      } else {
        selectedIndStaff = selectedIndStaff.filter(s => s.StaffId !== id);
      }
      updateIndStaffLabel();
    },
    async () => {
      updateIndStaffLabel();
      if (selectedIndStaff.length > 0) {
        await loadIndividualTable();
      }
    }
  );
  makeToggle(indStaffToggle, indStaffPanel);
}

function updateIndStaffLabel() {
  if (!selectedIndStaff.length) {
    indStaffLabel.textContent = 'Select staff…';
  } else if (selectedIndStaff.length === 1) {
    indStaffLabel.textContent = selectedIndStaff[0].StaffName;
  } else {
    indStaffLabel.innerHTML = `${escapeHtml(selectedIndStaff[0].StaffName)} <span class="badge">+${selectedIndStaff.length - 1}</span>`;
  }
}

async function loadIndividualTable() {
  show(indLoading);
  hide(indTableWrap);

  const indicators = getFilteredIndicators('Individual');
  hide(indLoading);

  if (!indicators.length) {
    msgEl.textContent = 'No Individual indicators found for this room.';
    return;
  }

  renderIndicatorTable(indBody, indicators);
  show(indTableWrap);
}

// ─── Finish Individual ────────────────────────────────────────────
finishIndBtn.addEventListener('click', () => {
  if (!selectedIndStaff.length) {
    showErr(indErr, 'Select at least one staff member');
    return;
  }
  hideErr(indErr);

  const result = collectTableResults(indBody);
  if (!result.valid) {
    showErr(indValMsg, result.error);
    return;
  }
  hideErr(indValMsg);

  // Зберігаємо individual items для кожного обраного стафу
  selectedIndStaff.forEach(s => {
    indItems[s.StaffId] = result.items;
  });

  indTableWrap.querySelectorAll('.seg button, textarea').forEach(el => el.disabled = true);
  finishIndBtn.disabled = true;
  finishIndBtn.textContent = 'Confirmed ✓';

  show(finishSection);
});

// ─── Filter indicators from config ───────────────────────────────
function getFilteredIndicators(scope) {
  if (!config?.indicators) return [];
  return config.indicators.filter(ind => {
    if (ind.scope !== scope) return false;
    const roomOk = ind.room_code === '*' || ind.room_code === selectedRoom;
    const slotOk = ind.slot_code === '*' || ind.slot_code === selectedSlot;
    return roomOk && slotOk;
  });
}

// ─── Render indicator table ───────────────────────────────────────
function renderIndicatorTable(tbody, indicators) {
  tbody.innerHTML = '';
  indicators.forEach(ind => {
    const tr = document.createElement('tr');
    tr.dataset.indicatorId = ind.id;
    tr.dataset.weight       = ind.weight;
    tr.dataset.indText      = ind.indicator_text;
    tr.dataset.category     = ind.category;

    const tdCat = document.createElement('td');
    tdCat.textContent = ind.category;

    const tdInd = document.createElement('td');
    tdInd.textContent = ind.indicator_text;

    const tdCheck = document.createElement('td');
    tdCheck.innerHTML = `
      <div class="seg">
        <button type="button" data-val="no">No</button>
        <button type="button" data-val="yes">Yes</button>
      </div>`;
    tdCheck.querySelectorAll('button').forEach(btn => {
      btn.addEventListener('click', () => {
        tdCheck.querySelectorAll('button').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        // Показати textarea якщо No
        const textarea = tr.querySelector('textarea');
        if (textarea) textarea.style.display = btn.dataset.val === 'no' ? 'block' : 'none';
      });
    });

    const tdComment = document.createElement('td');
    const ta = document.createElement('textarea');
    ta.placeholder = 'Comment…';
    ta.style.display = 'none';
    tdComment.appendChild(ta);

    tr.appendChild(tdCat);
    tr.appendChild(tdInd);
    tr.appendChild(tdCheck);
    tr.appendChild(tdComment);
    tbody.appendChild(tr);
  });
}

// ─── Collect table results ────────────────────────────────────────
function collectTableResults(tbody) {
  const items = [];
  let unanswered = 0;

  tbody.querySelectorAll('tr').forEach(tr => {
    const activeBtn = tr.querySelector('.seg button.active');
    if (!activeBtn) { unanswered++; return; }

    const check   = activeBtn.dataset.val;
    const comment = tr.querySelector('textarea')?.value?.trim() || '';

    if (check === 'no' && !comment) {
      // Дозволяємо без коментаря — не блокуємо
    }

    items.push({
      indicator_id: parseInt(tr.dataset.indicatorId),
      check,
      comment,
    });
  });

  if (unanswered > 0) {
    return { valid: false, error: `Please answer all ${unanswered} indicator(s) before confirming.`, items: [] };
  }
  return { valid: true, error: '', items };
}

// ─── Header validation ────────────────────────────────────────────
function validateHeader() {
  let ok = true;
  if (!selectedRoom)             { showErr(roomErr, 'Select a room'); ok = false; }
  if (!selectedSlot)             { showErr(slotErr, 'Select a time slot'); ok = false; }
  if (!dateInput.value)          { showErr(dateErr, 'Select a date'); ok = false; }
  if (!presentStaff.length)      { showErr(staffErr, 'Select present staff'); ok = false; }
  return ok;
}

// ─── Final Submit ─────────────────────────────────────────────────
finishBtn.addEventListener('click', async () => {
  setBtnLoading(finishBtn, true);
  msgEl.textContent = '';

  try {
    // UX-перевірка дублікату
    const dup = await apiFetch(`/kpi/api/kpi.php?action=checkDuplicate&batch_id=${encodeURIComponent(batchId)}`);
    if (dup.exists) {
      msgEl.textContent = '⚠ This slot was already saved. Refresh the page to start a new check.';
      setBtnLoading(finishBtn, false);
      return;
    }

    const result = await apiFetch('/kpi/api/kpi.php?action=saveBatch', {
      method: 'POST',
      body: JSON.stringify({
        batch_id:          batchId,
        date:              dateInput.value,
        room_code:         selectedRoom,
        slot_code:         selectedSlot,
        present_staff_ids: presentStaff.map(s => s.StaffId),
        team_items:        teamItems,
        individual_items:  indItems,
      }),
    });

    finishBtn.innerHTML = '✓ Saved!';
    finishBtn.disabled  = true;
    msgEl.style.color   = '#15803d';
    msgEl.textContent   = `Successfully saved ${result.saved} records.`;
    batchId = genBatchId(); // наступний check — новий batch

  } catch(e) {
    setBtnLoading(finishBtn, false);
    msgEl.style.color = '#dc2626';
    msgEl.textContent = 'Save error: ' + e.message;
  }
});
