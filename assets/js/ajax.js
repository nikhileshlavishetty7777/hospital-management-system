/**
 * assets/js/ajax.js
 * Lightweight AJAX wrapper for HMS
 */

const HMSAjax = (() => {
  'use strict';

  /**
   * Core fetch wrapper
   * @param {string} url
   * @param {object} opts  - method, body (FormData or plain object), headers
   * @returns {Promise<object>}
   */
  async function request(url, opts = {}) {
    const { method = 'GET', body = null, headers = {} } = opts;
    const config = { method, headers: { 'X-Requested-With': 'XMLHttpRequest', ...headers } };

    if (body) {
      if (body instanceof FormData) {
        config.body = body;
      } else {
        config.body   = JSON.stringify(body);
        config.headers['Content-Type'] = 'application/json';
      }
    }

    HMS.showLoader();
    try {
      const res  = await fetch(url, config);
      const text = await res.text();
      let json;
      try { json = JSON.parse(text); }
      catch { json = { success: false, message: 'Invalid server response', raw: text }; }

      if (!res.ok) {
        HMS.toast(json.message || `HTTP ${res.status}`, 'danger');
        return { success: false, ...json };
      }
      return json;
    } catch (err) {
      HMS.toast('Network error. Please try again.', 'danger');
      return { success: false, message: err.message };
    } finally {
      HMS.hideLoader();
    }
  }

  const get    = (url)        => request(url);
  const post   = (url, body)  => request(url, { method: 'POST',   body });
  const put    = (url, body)  => request(url, { method: 'PUT',    body });
  const del    = (url)        => request(url, { method: 'DELETE' });

  /* ── Specific helpers ──────────────────────────────────── */

  /** Search patients by query string */
  async function searchPatient(query) {
    return get(APP_URL + '/ajax/search_patient.php?q=' + encodeURIComponent(query));
  }

  /** Load dashboard stats */
  async function loadDashboard() {
    return get(APP_URL + '/ajax/load_dashboard.php');
  }

  /** Book / update appointment */
  async function saveAppointment(data) {
    return post(APP_URL + '/ajax/appointments.php', data);
  }

  /** Generic CRUD on any API endpoint */
  const api = {
    patients:     (method, body) => request(APP_URL + '/api/patients.php',     { method, body }),
    doctors:      (method, body) => request(APP_URL + '/api/doctors.php',      { method, body }),
    appointments: (method, body) => request(APP_URL + '/api/appointments.php', { method, body }),
    billing:      (method, body) => request(APP_URL + '/api/billing.php',      { method, body }),
    reports:      (method, body) => request(APP_URL + '/api/reports.php',      { method, body }),
  };

  /* ── Form submit helper ────────────────────────────────── */
  /**
   * Attach AJAX submit to a form
   * @param {string}   formSel  - CSS selector for the form
   * @param {string}   url      - endpoint
   * @param {Function} onSuccess
   */
  function ajaxForm(formSel, url, onSuccess) {
    const form = document.querySelector(formSel);
    if (!form) return;
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = form.querySelector('[type=submit]');
      const origText = btn?.innerHTML;
      if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-2"></i>Saving…'; }

      const fd = new FormData(form);
      const res = await post(url, fd);

      if (btn) { btn.disabled = false; btn.innerHTML = origText; }

      if (res.success) {
        HMS.toast(res.message || 'Saved successfully!', 'success');
        onSuccess && onSuccess(res);
      } else {
        HMS.toast(res.message || 'Something went wrong.', 'danger');
        // Show inline field errors if provided
        if (res.errors) {
          Object.entries(res.errors).forEach(([field, msg]) => {
            const el = form.querySelector(`[name="${field}"]`);
            if (el) {
              el.classList.add('is-invalid');
              let fb = el.nextElementSibling;
              if (!fb || !fb.classList.contains('invalid-feedback')) {
                fb = document.createElement('div');
                fb.className = 'invalid-feedback';
                el.parentNode.insertBefore(fb, el.nextSibling);
              }
              fb.textContent = msg;
            }
          });
        }
      }
    });

    // Clear validation state on input
    form.querySelectorAll('.form-control, .form-select').forEach(el => {
      el.addEventListener('input', () => el.classList.remove('is-invalid'));
    });
  }

  /* ── Live search with debounce ─────────────────────────── */
  function liveSearch(inputSel, resultsId, endpoint, renderFn) {
    const input   = document.querySelector(inputSel);
    const results = document.getElementById(resultsId);
    if (!input || !results) return;

    let timer;
    input.addEventListener('input', () => {
      clearTimeout(timer);
      const q = input.value.trim();
      if (q.length < 2) { results.innerHTML = ''; return; }
      timer = setTimeout(async () => {
        const res = await get(endpoint + '?q=' + encodeURIComponent(q));
        results.innerHTML = renderFn(res.data || res.items || []);
      }, 320);
    });
  }

  return { request, get, post, put, del, api, searchPatient, loadDashboard, saveAppointment, ajaxForm, liveSearch };
})();
