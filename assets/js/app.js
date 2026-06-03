/**
 * assets/js/app.js
 * Global HMS namespace — theme, sidebar, toasts, counters, utilities
 */

const HMS = (() => {
  'use strict';

  /* ── Theme ──────────────────────────────────────────────── */
  const THEME_KEY = 'hms_theme';

  function getTheme()  { return localStorage.getItem(THEME_KEY) || 'light'; }
  function applyTheme(t) {
    document.documentElement.setAttribute('data-bs-theme', t);
    const icon = document.getElementById('themeIcon');
    if (icon) { icon.className = t === 'dark' ? 'fa-solid fa-sun' : 'fa-solid fa-moon'; }
  }
  function toggleTheme() {
    const next = getTheme() === 'dark' ? 'light' : 'dark';
    localStorage.setItem(THEME_KEY, next);
    applyTheme(next);
  }

  /* ── Sidebar ────────────────────────────────────────────── */
  const SIDEBAR_KEY = 'hms_sidebar_collapsed';

  function toggleSidebar() {
    const isMobile = window.innerWidth < 992;
    if (isMobile) {
      document.getElementById('sidebar').classList.toggle('open');
      document.getElementById('sidebarOverlay').classList.toggle('active');
    } else {
      const collapsed = document.body.classList.toggle('sidebar-collapsed');
      localStorage.setItem(SIDEBAR_KEY, collapsed ? '1' : '0');
    }
  }

  function closeSidebar() {
    document.getElementById('sidebar')?.classList.remove('open');
    document.getElementById('sidebarOverlay')?.classList.remove('active');
  }

  function initSidebar() {
    if (window.innerWidth >= 992) {
      if (localStorage.getItem(SIDEBAR_KEY) === '1') {
        document.body.classList.add('sidebar-collapsed');
      }
    }
  }

  /* ── Toast ──────────────────────────────────────────────── */
  const ICONS = { success:'fa-circle-check', danger:'fa-circle-xmark', warning:'fa-triangle-exclamation', info:'fa-circle-info' };
  const COLORS = { success:'#22c55e', danger:'#ef4444', warning:'#f59e0b', info:'#0ea5e9' };

  function toast(message, type = 'info', duration = 4000) {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const id   = 'toast_' + Date.now();
    const icon = ICONS[type]  || ICONS.info;
    const color= COLORS[type] || COLORS.info;

    const el = document.createElement('div');
    el.id = id;
    el.className = 'toast hms-toast show animate-fade-in-up';
    el.setAttribute('role', 'alert');
    el.innerHTML = `
      <div class="toast-body d-flex align-items-center gap-3 p-3">
        <i class="fa-solid ${icon}" style="color:${color};font-size:18px;flex-shrink:0"></i>
        <span class="flex-1" style="font-size:13.5px;font-weight:500">${message}</span>
        <button type="button" class="btn-close btn-close-sm ms-auto" onclick="document.getElementById('${id}').remove()"></button>
      </div>
    `;
    container.appendChild(el);
    setTimeout(() => { el.classList.remove('show'); setTimeout(() => el.remove(), 300); }, duration);
  }

  /* ── Animated counter ───────────────────────────────────── */
  function animateCounter(el, target, duration = 1200, prefix = '', suffix = '') {
    const start    = performance.now();
    const startVal = 0;
    const isFloat  = target % 1 !== 0;

    function step(now) {
      const elapsed  = now - start;
      const progress = Math.min(elapsed / duration, 1);
      const ease     = 1 - Math.pow(1 - progress, 4); // ease-out-quart
      const current  = startVal + (target - startVal) * ease;
      el.textContent = prefix + (isFloat ? current.toFixed(1) : Math.floor(current).toLocaleString('en-IN')) + suffix;
      if (progress < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }

  function initCounters() {
    document.querySelectorAll('[data-counter]').forEach(el => {
      const target  = parseFloat(el.dataset.counter) || 0;
      const prefix  = el.dataset.prefix  || '';
      const suffix  = el.dataset.suffix  || '';
      const duration= parseInt(el.dataset.duration) || 1200;
      // Use IntersectionObserver so counter runs when visible
      const obs = new IntersectionObserver(entries => {
        entries.forEach(e => {
          if (e.isIntersecting) {
            animateCounter(el, target, duration, prefix, suffix);
            obs.unobserve(el);
          }
        });
      }, { threshold: 0.5 });
      obs.observe(el);
    });
  }

  /* ── Ripple effect on buttons ───────────────────────────── */
  function initRipple() {
    document.querySelectorAll('.ripple-btn').forEach(btn => {
      btn.addEventListener('click', function(e) {
        const rect = this.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height);
        const wave = document.createElement('span');
        wave.className = 'ripple-wave';
        wave.style.cssText = `width:${size}px;height:${size}px;left:${e.clientX-rect.left-size/2}px;top:${e.clientY-rect.top-size/2}px`;
        this.appendChild(wave);
        setTimeout(() => wave.remove(), 700);
      });
    });
  }

  /* ── DataTable default init ─────────────────────────────── */
  function initDataTables(selector = '.hms-table', opts = {}) {
    const defaults = {
      responsive:  true,
      pageLength:  10,
      language: {
        search:       '',
        searchPlaceholder: 'Search…',
        lengthMenu:   'Show _MENU_ entries',
        paginate: { previous: '<i class="fa-solid fa-chevron-left"></i>', next: '<i class="fa-solid fa-chevron-right"></i>' }
      },
      dom: "<'row align-items-center mb-3'<'col-sm-6'l><'col-sm-6'f>>" +
           "<'row'<'col-12'tr>>" +
           "<'row mt-3 align-items-center'<'col-sm-5'i><'col-sm-7'p>>",
    };
    document.querySelectorAll(selector).forEach(tbl => {
      if (!$.fn.DataTable.isDataTable(tbl)) {
        $(tbl).DataTable({ ...defaults, ...opts });
      }
    });
  }

  /* ── Confirm dialog ─────────────────────────────────────── */
  function confirm(message, onConfirm, onCancel = null) {
    const id  = 'confirmModal_' + Date.now();
    const el  = document.createElement('div');
    el.innerHTML = `
      <div class="modal fade" id="${id}" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
          <div class="modal-content rounded-xl">
            <div class="modal-body text-center p-4">
              <div class="mb-3"><i class="fa-solid fa-triangle-exclamation fa-2x text-warning"></i></div>
              <p class="fw-600 mb-1">${message}</p>
              <p class="text-muted small">This action cannot be undone.</p>
              <div class="d-flex gap-2 justify-content-center mt-3">
                <button class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-danger btn-sm" id="${id}_confirm">Confirm</button>
              </div>
            </div>
          </div>
        </div>
      </div>`;
    document.body.appendChild(el);
    const modal = new bootstrap.Modal(document.getElementById(id));
    modal.show();
    document.getElementById(id + '_confirm').onclick = () => {
      modal.hide();
      onConfirm && onConfirm();
    };
    document.getElementById(id).addEventListener('hidden.bs.modal', () => {
      el.remove();
      onCancel && onCancel();
    });
  }

  /* ── Notifications ──────────────────────────────────────── */
  async function loadNotifications() {
    const list = document.getElementById('notifList');
    if (!list) return;
    try {
      const res  = await fetch(APP_URL + '/ajax/notifications.php?action=list');
      const data = await res.json();
      if (!data.items?.length) {
        list.innerHTML = '<div class="p-4 text-center text-muted small">No notifications</div>';
        return;
      }
      list.innerHTML = data.items.map(n => `
        <div class="notif-item ${n.is_read ? '' : 'unread'}" onclick="HMS.readNotif(${n.id}, this)">
          <div class="notif-icon ${n.type}">
            <i class="fa-solid ${notifIcon(n.type)}"></i>
          </div>
          <div class="flex-1">
            <div class="notif-title">${n.title}</div>
            <div class="notif-msg">${n.message}</div>
            <div class="notif-time">${n.ago}</div>
          </div>
          ${n.is_read ? '' : '<div class="dot" style="width:6px;height:6px;border-radius:50%;background:var(--primary);margin-top:6px;flex-shrink:0"></div>'}
        </div>`).join('');
    } catch { list.innerHTML = '<div class="p-4 text-center text-muted small">Failed to load</div>'; }
  }

  function notifIcon(type) {
    const m = { info:'fa-circle-info', success:'fa-circle-check', warning:'fa-triangle-exclamation', danger:'fa-circle-xmark' };
    return m[type] || m.info;
  }

  async function readNotif(id, el) {
    el.classList.remove('unread');
    el.querySelector('.dot')?.remove();
    await fetch(APP_URL + '/ajax/notifications.php?action=read&id=' + id);
    const badge = document.querySelector('.notif-badge');
    if (badge) {
      const cnt = (parseInt(badge.textContent) || 1) - 1;
      cnt > 0 ? (badge.textContent = cnt) : badge.remove();
    }
  }

  async function markAllRead(e) {
    e.preventDefault();
    await fetch(APP_URL + '/ajax/notifications.php?action=read_all');
    document.querySelectorAll('.notif-item').forEach(i => { i.classList.remove('unread'); });
    document.querySelectorAll('.notif-item .dot').forEach(d => d.remove());
    document.querySelector('.notif-badge')?.remove();
  }

  /* ── Page loader ────────────────────────────────────────── */
  function showLoader() { document.getElementById('pageLoader')?.classList.add('active'); }
  function hideLoader() { document.getElementById('pageLoader')?.classList.remove('active'); }

  /* ── Init ───────────────────────────────────────────────── */
  function init() {
    applyTheme(getTheme());
    initSidebar();
    initCounters();
    initRipple();

    // Load notifications when bell dropdown opens
    document.getElementById('notifBell')?.addEventListener('click', loadNotifications);

    // Auto-init datatables
    if (typeof $ !== 'undefined' && $.fn.DataTable) {
      $(document).ready(() => initDataTables());
    }

    // Close sidebar on outside click (mobile)
    window.addEventListener('resize', () => {
      if (window.innerWidth >= 992) closeSidebar();
    });
  }

  document.addEventListener('DOMContentLoaded', init);

  /* ── Public API ─────────────────────────────────────────── */
  return {
    toast, toggleTheme, toggleSidebar, closeSidebar,
    showLoader, hideLoader, confirm,
    loadNotifications, readNotif, markAllRead,
    initDataTables, animateCounter, initCounters,
  };
})();
