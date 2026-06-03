/**
 * assets/js/charts.js
 * Chart.js & ApexCharts wrappers for HMS dashboard
 * Include AFTER Chart.js and ApexCharts are loaded
 */

const HMSCharts = (() => {
  'use strict';

  // Chart.js global defaults
  if (typeof Chart !== 'undefined') {
    Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";
    Chart.defaults.font.size   = 12;
    Chart.defaults.color       = '#64748b';
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.padding       = 16;
    Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(15,23,42,0.9)';
    Chart.defaults.plugins.tooltip.titleFont.weight    = '700';
    Chart.defaults.plugins.tooltip.padding             = 12;
    Chart.defaults.plugins.tooltip.cornerRadius        = 8;
  }

  const PRIMARY   = '#0ea5e9';
  const SECONDARY = '#6366f1';
  const SUCCESS   = '#22c55e';
  const WARNING   = '#f59e0b';
  const DANGER    = '#ef4444';
  const INFO      = '#06b6d4';
  const PURPLE    = '#8b5cf6';
  const PINK      = '#ec4899';

  const PALETTE = [PRIMARY, SECONDARY, SUCCESS, WARNING, DANGER, INFO, PURPLE, PINK];

  function gradient(ctx, color1, color2) {
    const g = ctx.createLinearGradient(0, 0, 0, 300);
    g.addColorStop(0, color1);
    g.addColorStop(1, color2);
    return g;
  }

  /* ── Revenue Line Chart ─────────────────────────────────── */
  function revenueChart(canvasId, labels, data) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return null;
    const ctx = canvas.getContext('2d');
    return new Chart(ctx, {
      type: 'line',
      data: {
        labels,
        datasets: [{
          label: 'Revenue (₹)',
          data,
          borderColor: PRIMARY,
          backgroundColor: gradient(ctx, 'rgba(14,165,233,.25)', 'rgba(14,165,233,0)'),
          fill: true,
          tension: .45,
          pointBackgroundColor: PRIMARY,
          pointRadius: 4,
          pointHoverRadius: 7,
          borderWidth: 2.5,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        scales: {
          x: { grid: { display: false }, ticks: { maxRotation: 0 } },
          y: {
            beginAtZero: true,
            grid: { color: 'rgba(100,116,139,.1)' },
            ticks: { callback: v => '₹' + (v >= 1000 ? (v/1000).toFixed(0)+'K' : v) }
          }
        },
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: ctx => ' ₹' + ctx.parsed.y.toLocaleString('en-IN') } }
        }
      }
    });
  }

  /* ── Appointments Bar Chart ─────────────────────────────── */
  function appointmentsChart(canvasId, labels, datasets) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return null;
    const ctx = canvas.getContext('2d');
    return new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: datasets || [
          { label: 'OPD',       data: [45,60,55,70,65,80,72], backgroundColor: PRIMARY,   borderRadius: 6, borderSkipped: false },
          { label: 'IPD',       data: [12,18,14,20,16,22,19], backgroundColor: SECONDARY, borderRadius: 6, borderSkipped: false },
          { label: 'Emergency', data: [5, 8,  6,  9,  7, 11, 8],  backgroundColor: DANGER,    borderRadius: 6, borderSkipped: false },
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        scales: {
          x: { grid: { display: false }, stacked: false },
          y: { beginAtZero: true, grid: { color: 'rgba(100,116,139,.1)' }, stacked: false }
        },
        plugins: { legend: { position: 'top' } }
      }
    });
  }

  /* ── Department Pie Chart ───────────────────────────────── */
  function departmentPieChart(canvasId, labels, data) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return null;
    return new Chart(canvas.getContext('2d'), {
      type: 'doughnut',
      data: {
        labels,
        datasets: [{
          data,
          backgroundColor: PALETTE,
          borderWidth: 0,
          hoverOffset: 8,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '65%',
        plugins: {
          legend: { position: 'bottom', labels: { padding: 14 } },
          tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.parsed} patients` } }
        }
      }
    });
  }

  /* ── Bed Occupancy Horizontal Bar ───────────────────────── */
  function bedOccupancyChart(canvasId, wards, occupied, total) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return null;
    return new Chart(canvas.getContext('2d'), {
      type: 'bar',
      data: {
        labels: wards,
        datasets: [
          { label: 'Occupied', data: occupied, backgroundColor: DANGER,  borderRadius: 4, borderSkipped: false },
          { label: 'Available',data: total.map((t,i) => t - occupied[i]), backgroundColor: 'rgba(34,197,94,.2)', borderRadius: 4, borderSkipped: false },
        ]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          x: { stacked: true, beginAtZero: true, grid: { color: 'rgba(100,116,139,.1)' } },
          y: { stacked: true, grid: { display: false } }
        },
        plugins: { legend: { position: 'top' } }
      }
    });
  }

  /* ── Pharmacy Sales Bar ─────────────────────────────────── */
  function pharmacySalesChart(canvasId, labels, data) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return null;
    const ctx = canvas.getContext('2d');
    return new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: 'Sales (₹)',
          data,
          backgroundColor: gradient(ctx, 'rgba(99,102,241,.8)', 'rgba(99,102,241,.3)'),
          borderRadius: 8,
          borderSkipped: false,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { display: false } },
          y: { beginAtZero: true, grid: { color: 'rgba(100,116,139,.1)' }, ticks: { callback: v => '₹' + (v/1000).toFixed(0)+'K' } }
        }
      }
    });
  }

  /* ── Patient Growth Chart ───────────────────────────────── */
  function patientGrowthChart(canvasId, labels, newPt, returning) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return null;
    const ctx = canvas.getContext('2d');
    return new Chart(ctx, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'New Patients',
            data: newPt,
            borderColor: SUCCESS,
            backgroundColor: 'rgba(34,197,94,.1)',
            fill: true, tension: .4, borderWidth: 2,
            pointBackgroundColor: SUCCESS, pointRadius: 3,
          },
          {
            label: 'Returning',
            data: returning,
            borderColor: WARNING,
            backgroundColor: 'rgba(245,158,11,.1)',
            fill: true, tension: .4, borderWidth: 2,
            pointBackgroundColor: WARNING, pointRadius: 3,
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        scales: {
          x: { grid: { display: false } },
          y: { beginAtZero: true, grid: { color: 'rgba(100,116,139,.1)' } }
        },
        plugins: { legend: { position: 'top' } }
      }
    });
  }

  /* ── Sparkline (tiny inline chart) ─────────────────────── */
  function sparkline(canvasId, data, color = PRIMARY) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return null;
    const ctx = canvas.getContext('2d');
    return new Chart(ctx, {
      type: 'line',
      data: {
        labels: data.map((_, i) => i),
        datasets: [{
          data,
          borderColor: color,
          backgroundColor: color + '22',
          fill: true, tension: .4, borderWidth: 2,
          pointRadius: 0,
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false }, tooltip: { enabled: false } },
        scales: { x: { display: false }, y: { display: false } }
      }
    });
  }

  /* ── Radial / Gauge via ApexCharts ──────────────────────── */
  function radialChart(elId, value, label, color = PRIMARY) {
    const el = document.getElementById(elId);
    if (!el || typeof ApexCharts === 'undefined') return null;
    return new ApexCharts(el, {
      series: [value],
      chart:  { type: 'radialBar', height: 160, sparkline: { enabled: true } },
      plotOptions: {
        radialBar: {
          hollow: { size: '60%' },
          dataLabels: { name: { show: false }, value: { fontSize: '20px', fontWeight: 800, fontFamily: 'Plus Jakarta Sans', color: 'var(--text-primary)', offsetY: 6, formatter: v => v + '%' } }
        }
      },
      fill: { colors: [color] },
      labels: [label],
    }).render();
  }

  return { revenueChart, appointmentsChart, departmentPieChart, bedOccupancyChart, pharmacySalesChart, patientGrowthChart, sparkline, radialChart, PALETTE };
})();
