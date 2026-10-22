/* ============================================================
   Vinamilk CDM — Application JavaScript  v2
   ============================================================ */

'use strict';

// ── Global Chart.js defaults ──────────────────────────────────
(function () {
  if (typeof Chart === 'undefined') return;

  Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
  Chart.defaults.font.size   = 11;
  Chart.defaults.color       = '#5a6a7e';
  Chart.defaults.plugins.legend.display = false;
  Chart.defaults.plugins.tooltip.padding  = 10;
  Chart.defaults.plugins.tooltip.boxPadding = 4;
  Chart.defaults.plugins.tooltip.backgroundColor = '#18273a';
  Chart.defaults.plugins.tooltip.cornerRadius = 6;
  Chart.defaults.plugins.tooltip.titleFont = { weight: 'bold', size: 11 };
  Chart.defaults.plugins.tooltip.bodyFont  = { size: 11 };
  Chart.defaults.animation.duration = 500;
})();

// ── Dashboard Charts ─────────────────────────────────────────
(function () {
  const d = window.VNM_CHARTS;
  if (!d || typeof Chart === 'undefined') return;

  /* ---- Status Doughnut ---- */
  const ctxStatus = document.getElementById('chartStatus');
  if (ctxStatus) {
    // Filter out zero-value entries so the chart stays clean
    const nonZeroIdx = d.status.data.reduce((acc, v, i) => { if (v > 0) acc.push(i); return acc; }, []);
    const labels = nonZeroIdx.map(i => d.status.labels[i]);
    const data   = nonZeroIdx.map(i => d.status.data[i]);
    const colors = nonZeroIdx.map(i => d.status.colors[i]);

    new Chart(ctxStatus, {
      type: 'doughnut',
      data: {
        labels: labels,
        datasets: [{
          data:            data,
          backgroundColor: colors,
          borderColor:     '#ffffff',
          borderWidth:     3,
          hoverBorderWidth:3,
          hoverOffset:     4,
        }]
      },
      options: {
        cutout: '68%',
        responsive: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: ctx => ` ${ctx.label}: ${ctx.parsed} contract${ctx.parsed !== 1 ? 's' : ''}`
            }
          }
        }
      }
    });
  }

  /* ---- Type Bar ---- */
  const ctxTypes = document.getElementById('chartTypes');
  if (ctxTypes && d.types.labels.length) {
    // Shorten long TypeNames for the axis
    const shortLabels = d.types.labels.map(l =>
      l.replace('HĐ ', '').replace('Hợp đồng ', '')
    );

    new Chart(ctxTypes, {
      type: 'bar',
      data: {
        labels: shortLabels,
        datasets: [{
          data:            d.types.data,
          backgroundColor: '#1761a8',
          borderRadius:    4,
          borderSkipped:   false,
          hoverBackgroundColor: '#1351a0',
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: { legend: { display: false } },
        scales: {
          x: {
            grid: { display: false },
            border: { display: false },
            ticks: { maxRotation: 30, minRotation: 0, font: { size: 10 } }
          },
          y: {
            beginAtZero: true,
            grid: { color: '#e3e8f0', drawBorder: false },
            border: { display: false },
            ticks: { stepSize: 1, precision: 0 }
          }
        }
      }
    });
  }

  /* ---- Monthly Line ---- */
  const ctxMonthly = document.getElementById('chartMonthly');
  if (ctxMonthly && d.monthly.labels.length) {
    new Chart(ctxMonthly, {
      type: 'line',
      data: {
        labels: d.monthly.labels,
        datasets: [{
          data:         d.monthly.data,
          borderColor:  '#1761a8',
          backgroundColor: 'rgba(23,97,168,.08)',
          fill:         true,
          tension:      0.3,
          pointRadius:  3,
          pointBackgroundColor: '#1761a8',
          pointHoverRadius: 5,
          borderWidth:  2,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: { legend: { display: false } },
        scales: {
          x: {
            grid: { display: false },
            border: { display: false },
            ticks: { maxRotation: 30, minRotation: 0, font: { size: 10 } }
          },
          y: {
            beginAtZero: true,
            grid: { color: '#e3e8f0', drawBorder: false },
            border: { display: false },
            ticks: { stepSize: 1, precision: 0 }
          }
        }
      }
    });
  }
})();

// ── Sidebar toggle (mobile) ───────────────────────────────────
(function () {
  const toggle  = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('appSidebar');
  if (!toggle || !sidebar) return;

  function checkMobile() {
    toggle.style.display = window.innerWidth <= 768 ? '' : 'none';
    if (window.innerWidth > 768) sidebar.classList.remove('open');
  }

  toggle.addEventListener('click', () => sidebar.classList.toggle('open'));

  document.addEventListener('click', (e) => {
    if (window.innerWidth <= 768 &&
        !sidebar.contains(e.target) &&
        !toggle.contains(e.target)) {
      sidebar.classList.remove('open');
    }
  });

  window.addEventListener('resize', checkMobile);
  checkMobile();
})();

// ── User dropdown menu ────────────────────────────────────────
(function () {
  const wrap    = document.getElementById('userMenuWrap');
  const trigger = document.getElementById('userMenuTrigger');
  const drop    = document.getElementById('userDropdown');
  if (!wrap || !trigger || !drop) return;

  trigger.addEventListener('click', (e) => {
    e.stopPropagation();
    const open = wrap.classList.toggle('open');
    trigger.setAttribute('aria-expanded', String(open));
  });

  document.addEventListener('click', () => {
    wrap.classList.remove('open');
    trigger.setAttribute('aria-expanded', 'false');
  });

  drop.addEventListener('click', (e) => e.stopPropagation());

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
      wrap.classList.remove('open');
      trigger.setAttribute('aria-expanded', 'false');
      trigger.focus();
    }
  });
})();

// ── Auto-dismiss flash alerts ─────────────────────────────────
(function () {
  const flash = document.getElementById('flashAlert');
  if (!flash) return;
  setTimeout(() => {
    flash.style.transition = 'opacity .4s ease';
    flash.style.opacity    = '0';
    setTimeout(() => flash.remove(), 400);
  }, 6000);
})();

// ── File drop zone ────────────────────────────────────────────
function initFileDropZone(dropZoneId, inputId, previewId) {
  const zone    = document.getElementById(dropZoneId);
  const input   = document.getElementById(inputId);
  const preview = document.getElementById(previewId);
  if (!zone || !input) return;

  zone.addEventListener('click', () => input.click());

  zone.addEventListener('dragover',  (e) => { e.preventDefault(); zone.classList.add('dragover'); });
  zone.addEventListener('dragleave', ()  => zone.classList.remove('dragover'));

  zone.addEventListener('drop', (e) => {
    e.preventDefault();
    zone.classList.remove('dragover');
    if (e.dataTransfer.files.length) {
      input.files = e.dataTransfer.files;
      showFilePreview(input.files[0], preview, zone);
    }
  });

  input.addEventListener('change', () => {
    if (input.files.length) showFilePreview(input.files[0], preview, zone);
  });
}

function showFilePreview(file, previewEl, zoneEl) {
  if (!previewEl || !zoneEl) return;
  const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
  previewEl.innerHTML = `
    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
         fill="none" stroke="currentColor" stroke-width="2">
      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
      <polyline points="14 2 14 8 20 8"/>
    </svg>
    <span class="file-preview-name">${escHtml(file.name)}</span>
    <span class="file-preview-size">${sizeMB} MB</span>
    <button type="button" class="file-preview-remove" aria-label="Remove file">✕</button>
  `;
  previewEl.style.display = 'flex';
  zoneEl.style.display    = 'none';

  previewEl.querySelector('.file-preview-remove').addEventListener('click', () => {
    previewEl.style.display = 'none';
    zoneEl.style.display    = '';
    // Clear the input
    const inp = document.getElementById(previewEl.dataset.inputId || '');
    if (inp) inp.value = '';
  });
}

// ── Form validation helpers ───────────────────────────────────
function showFieldError(fieldId, message) {
  const field = document.getElementById(fieldId);
  if (!field) return;
  field.classList.add('is-invalid');
  let err = document.getElementById(fieldId + '_error');
  if (!err) {
    err = document.createElement('span');
    err.id        = fieldId + '_error';
    err.className = 'form-error';
    field.parentNode.insertBefore(err, field.nextSibling);
  }
  err.textContent = message;
  err.classList.add('visible');
}

function clearFieldError(fieldId) {
  const field = document.getElementById(fieldId);
  if (!field) return;
  field.classList.remove('is-invalid');
  const err = document.getElementById(fieldId + '_error');
  if (err) err.classList.remove('visible');
}

function clearAllErrors(formId) {
  const form = document.getElementById(formId);
  if (!form) return;
  form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
  form.querySelectorAll('.form-error').forEach(el => el.classList.remove('visible'));
}

// Clear error when user starts typing
document.addEventListener('input', (e) => {
  if (e.target.classList.contains('form-control')) clearFieldError(e.target.id);
});

// ── Contract Form (create_request.php) ───────────────────────
(function () {
  const form      = document.getElementById('contractRequestForm');
  if (!form) return;
  const submitBtn   = document.getElementById('submitBtn');
  const saveDraftBtn= document.getElementById('saveDraftBtn');

  function validateForm(isDraft) {
    clearAllErrors('contractRequestForm');
    let valid = true;

    if (!isDraft) {
      const required = [
        ['ContractTitle',  'Contract title is required.'],
        ['ContractTypeID', 'Please select a contract type.'],
        ['DepartmentID',   'Please select a department.'],
        ['CounterpartyID', 'Please select a counterparty.'],
        ['OwnerStaffID',   'Please select a contract owner.'],
        ['StartDate',      'Start date is required.'],
        ['EndDate',        'End date is required.'],
        ['Summary',        'Contract summary is required.'],
      ];

      required.forEach(([id, msg]) => {
        const el = document.getElementById(id);
        if (!el || !el.value.trim()) { showFieldError(id, msg); valid = false; }
      });

      const start = document.getElementById('StartDate');
      const end   = document.getElementById('EndDate');
      if (start && end && start.value && end.value) {
        if (new Date(end.value) < new Date(start.value)) {
          showFieldError('EndDate', 'End date must be on or after start date.');
          valid = false;
        }
      }
    }

    const valEl = document.getElementById('ContractValue');
    if (valEl && valEl.value !== '') {
      const num = parseFloat(valEl.value);
      if (isNaN(num) || num < 0) {
        showFieldError('ContractValue', 'Contract value must be a non-negative number.');
        valid = false;
      }
    }

    return valid;
  }

  if (submitBtn) {
    submitBtn.addEventListener('click', (e) => {
      e.preventDefault();
      if (!validateForm(false)) {
        form.querySelector('.is-invalid')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
      }
      setLoading(true);
      document.getElementById('action_type').value = 'submit';
      form.submit();
    });
  }

  if (saveDraftBtn) {
    saveDraftBtn.addEventListener('click', (e) => {
      e.preventDefault();
      if (!validateForm(true)) return;
      setLoading(true);
      document.getElementById('action_type').value = 'draft';
      form.submit();
    });
  }

  function setLoading(on) {
    if (!submitBtn) return;
    submitBtn.disabled   = on;
    if (saveDraftBtn) saveDraftBtn.disabled = on;
    if (on) submitBtn.innerHTML = `<span class="spinner"></span> Saving…`;
    else    submitBtn.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg> Create Contract`;
  }

  // File drop
  initFileDropZone('fileDropZone', 'supporting_document', 'filePreview');
})();

// ── Approval form ─────────────────────────────────────────────
(function () {
  const form = document.getElementById('approvalForm');
  if (!form) return;

  form.addEventListener('submit', (e) => {
    const action = form.querySelector('input[name="approval_action"]:checked');
    if (!action) { e.preventDefault(); alert('Please select an approval decision.'); return; }
    const comments = document.getElementById('comments');
    if (action.value === 'Rejected' && (!comments || !comments.value.trim())) {
      e.preventDefault();
      showFieldError('comments', 'Comments are required when rejecting a request.');
      comments.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  });
})();

// ── Filter form: auto-submit on select change ─────────────────
(function () {
  const filterForm = document.getElementById('filterForm');
  if (!filterForm) return;

  filterForm.querySelectorAll('select[data-autosubmit]').forEach(sel => {
    sel.addEventListener('change', () => filterForm.submit());
  });

  const searchInput = filterForm.querySelector('input[name="q"]');
  if (searchInput) {
    let timer;
    searchInput.addEventListener('input', () => {
      clearTimeout(timer);
      timer = setTimeout(() => filterForm.submit(), 480);
    });
  }
})();

// ── Confirm dialogs ───────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', (e) => {
      if (!confirm(el.dataset.confirm)) e.preventDefault();
    });
  });
});

// ── Utility ───────────────────────────────────────────────────
function escHtml(str) {
  const d = document.createElement('div');
  d.textContent = str;
  return d.innerHTML;
}
