/**
 * assets/js/dashboard.js
 * Dashboard-specific JS — live refresh, stat counters, chart init
 */

const HMSDashboard = (() => {
  'use strict';

  // ── Auto-refresh interval (ms) ───────────────────────────
  const REFRESH_INTERVAL = 30000; // 30 seconds
  let   refreshTimer     = null;

  // ── Initialise dashboard with live data ─────────────────
  async function init() {
    await loadStats();
    startAutoRefresh();
    initChartFilters();
    initBedGrid();
  }

  // ── Load stats via AJAX ──────────────────────────────────
  async function loadStats() {
    const res = await HMSAjax.loadDashboard();
    if (!res.success) return;
    const d = res.data;

    // Update counters
    const mappings = {
      'stat-total-patients'   : d.total_patients,
      'stat-today-appointments': d.today_appointments,
      'stat-month-revenue'    : d.month_revenue,
      'stat-beds-occupied'    : d.beds_occupied,
      'stat-pending-labs'     : d.pending_labs,
      'stat-pharmacy-today'   : d.pharmacy_today,
    };

    Object.entries(mappings).forEach(([id, val]) => {
      const el = document.getElementById(id);
      if (el && val !== undefined) {
        HMS.animateCounter(el, parseFloat(val) || 0);
      }
    });

    // Update sparkline if data available
    if (d.revenue_spark && typeof HMSCharts !== 'undefined') {
      const sparkEl = document.getElementById('revenueSparkline');
      if (sparkEl) {
        HMSCharts.sparkline('revenueSparkline',
          d.revenue_spark.map(r => parseFloat(r.val) || 0)
        );
      }
    }

    // Update queue
    if (d.queue && d.queue.length) {
      updateQueueDisplay(d.queue);
    }

    // Update last-refreshed timestamp
    const ts = document.getElementById('lastRefreshed');
    if (ts) ts.textContent = 'Updated ' + new Date().toLocaleTimeString('en-IN');
  }

  // ── Queue display ────────────────────────────────────────
  function updateQueueDisplay(queue) {
    const el = document.getElementById('liveQueue');
    if (!el) return;

    el.innerHTML = queue.map(q => `
      <div class="d-flex align-items-center gap-3 p-2 border-bottom">
        <span class="badge grad-primary fw-800" style="font-size:16px">#${q.token_number}</span>
        <div class="flex-1">
          <div class="fw-600 text-sm">${q.full_name}</div>
          <div class="text-muted text-xs">${q.appointment_time}</div>
        </div>
        <span class="status-badge status-${q.status}">${q.status.replace('_',' ')}</span>
      </div>
    `).join('');
  }

  // ── Auto refresh ─────────────────────────────────────────
  function startAutoRefresh() {
    refreshTimer = setInterval(loadStats, REFRESH_INTERVAL);
  }

  function stopAutoRefresh() {
    if (refreshTimer) clearInterval(refreshTimer);
  }

  // ── Chart period filters ─────────────────────────────────
  function initChartFilters() {
    document.querySelectorAll('[data-chart-filter]').forEach(btn => {
      btn.addEventListener('click', function() {
        const chartId = this.dataset.chartTarget;
        const period  = this.dataset.chartFilter;

        // Update active button
        document.querySelectorAll(`[data-chart-target="${chartId}"]`).forEach(b => {
          b.classList.remove('active', 'btn-primary');
          b.classList.add('btn-outline-secondary');
        });
        this.classList.add('active', 'btn-primary');
        this.classList.remove('btn-outline-secondary');

        // Reload chart data
        loadChartData(chartId, period);
      });
    });
  }

  async function loadChartData(chartId, period) {
    // In a full implementation, fetch data from server for the selected period
    HMS.toast(`Showing ${period} data`, 'info');
  }

  // ── Bed grid ─────────────────────────────────────────────
  function initBedGrid() {
    document.querySelectorAll('.bed-cell').forEach(cell => {
      cell.addEventListener('mouseenter', function() {
        const tooltip = document.createElement('div');
        tooltip.className = 'position-absolute bg-dark text-white rounded px-2 py-1 text-xs';
        tooltip.style.cssText = 'z-index:9999;white-space:nowrap;bottom:calc(100%+4px);left:50%;transform:translateX(-50%)';
        tooltip.textContent = `Bed ${this.dataset.bed || this.textContent.trim()} — ${this.classList.contains('bed-available') ? 'Available' : this.classList.contains('bed-occupied') ? 'Occupied' : 'Maintenance'}`;
        this.style.position = 'relative';
        this.appendChild(tooltip);
      });
      cell.addEventListener('mouseleave', function() {
        this.querySelector('div')?.remove();
      });
    });
  }

  // ── Revenue chart date range ─────────────────────────────
  function setRevenueRange(range, btn) {
    document.querySelectorAll('.rev-range-btn').forEach(b => {
      b.classList.remove('active','btn-primary');
      b.classList.add('btn-outline-secondary');
    });
    btn.classList.add('active','btn-primary');
    btn.classList.remove('btn-outline-secondary');
    // In full impl: re-fetch and re-render chart
    HMS.toast('Loading ' + range + ' data…', 'info');
  }

  // ── Department click drill-down ──────────────────────────
  function drillDepartment(deptId) {
    window.location.href = APP_URL + '/admin/appointments.php?department_id=' + deptId;
  }

  // ── Print dashboard ──────────────────────────────────────
  function printDashboard() {
    window.print();
  }

  // ── Export to CSV ────────────────────────────────────────
  function exportCSV(tableId, filename = 'export.csv') {
    const table = document.getElementById(tableId);
    if (!table) return;

    const rows   = [...table.querySelectorAll('tr')];
    const csv    = rows.map(row => {
      return [...row.querySelectorAll('th,td')].map(cell => {
        return '"' + cell.textContent.trim().replace(/"/g, '""') + '"';
      }).join(',');
    }).join('\n');

    const blob = new Blob([csv], { type: 'text/csv' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url; a.download = filename; a.click();
    URL.revokeObjectURL(url);
    HMS.toast('Exported to CSV!', 'success');
  }

  // ── Mini sparkline helper (inline stats cards) ───────────
  function initSparklines() {
    document.querySelectorAll('[data-sparkline]').forEach(canvas => {
      const data  = JSON.parse(canvas.dataset.sparkline || '[]');
      const color = canvas.dataset.color || '#0ea5e9';
      if (data.length && typeof HMSCharts !== 'undefined') {
        HMSCharts.sparkline(canvas.id, data, color);
      }
    });
  }

  // ── Notification polling ─────────────────────────────────
  async function pollNotifications() {
    const res = await HMSAjax.get(APP_URL + '/ajax/notifications.php?action=unread_count');
    if (!res.success) return;
    const badge = document.querySelector('.notif-badge');
    if (res.count > 0) {
      if (badge) {
        badge.textContent = res.count;
      } else {
        const bell = document.getElementById('notifBell');
        if (bell) {
          const b = document.createElement('span');
          b.className = 'notif-badge';
          b.textContent = res.count;
          bell.appendChild(b);
        }
      }
    } else {
      badge?.remove();
    }
  }

  // ── Page visibility API — pause refresh when hidden ──────
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      stopAutoRefresh();
    } else {
      loadStats();
      startAutoRefresh();
    }
  });

  // ── On DOMContentLoaded ──────────────────────────────────
  document.addEventListener('DOMContentLoaded', () => {
    // Only init dashboard on dashboard pages
    if (document.getElementById('dashboardPage')) {
      init();
      setInterval(pollNotifications, 60000);
    }
    initSparklines();
  });

  return {
    init, loadStats, updateQueueDisplay,
    setRevenueRange, drillDepartment,
    printDashboard, exportCSV, initSparklines,
    startAutoRefresh, stopAutoRefresh, pollNotifications,
  };
})();
