function escapeHtml(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function apiBaseUrl() {
  const cfg = window.APP_CONFIG || {};
  if (cfg.apiBaseUrl) {
    return String(cfg.apiBaseUrl).replace(/\/$/, '');
  }
  // Fallback: same-origin app in a subdirectory (e.g. localhost/tagforge.in/).
  const path = window.location.pathname || '/';
  const dir = path.replace(/\/[^/]*$/, '') || '';
  return window.location.origin + dir;
}

function apiCredentials() {
  const cfg = window.APP_CONFIG || {};
  return cfg.apiCredentials || 'same-origin';
}

function refreshCsrfToken() {
  const url = apiBaseUrl() + '/api.php?r=csrf';
  return fetch(url, {
    method: 'GET',
    credentials: apiCredentials(),
    headers: { Accept: 'application/json' },
  }).then(function (response) {
    return response.json().catch(function () {
      return {};
    }).then(function (payload) {
      if (!response.ok) {
        throw new Error((payload && payload.detail) || 'Could not refresh security token');
      }
      if (payload && payload.csrf_token) {
        window.CSRF_TOKEN = payload.csrf_token;
      }
      return window.CSRF_TOKEN;
    });
  });
}

function withCsrfBody(body) {
  if (!body || body instanceof FormData || !window.CSRF_TOKEN) {
    return body;
  }
  try {
    const parsed = JSON.parse(body);
    if (parsed && typeof parsed === 'object' && !Array.isArray(parsed) && !parsed.csrf_token) {
      parsed.csrf_token = window.CSRF_TOKEN;
      return JSON.stringify(parsed);
    }
  } catch (err) {
    // leave body unchanged
  }
  return body;
}

function apiRequest(route, options) {
  options = options || {};
  const method = String(options.method || 'GET').toUpperCase();
  const needsCsrf = method !== 'GET' && method !== 'HEAD' && method !== 'OPTIONS';

  function send() {
    const headers = new Headers(options.headers || {});
    if (window.CSRF_TOKEN) {
      headers.set('X-CSRF-Token', window.CSRF_TOKEN);
    }
    let body = options.body;
    if (needsCsrf) {
      body = withCsrfBody(body);
    }
    if (body && !(body instanceof FormData) && !headers.has('Content-Type')) {
      headers.set('Content-Type', 'application/json');
    }

    let url = apiBaseUrl() + '/api.php?r=' + encodeURIComponent(route);
    if (options.params) {
      Object.keys(options.params).forEach(function (key) {
        if (options.params[key] === undefined || options.params[key] === null || options.params[key] === '') return;
        url += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(options.params[key]);
      });
    }

    const fetchOptions = {
      credentials: apiCredentials(),
      method: options.method || 'GET',
      headers: headers,
    };
    if (body) fetchOptions.body = body;

    return fetch(url, fetchOptions).then(function (response) {
      if (response.status === 204) return undefined;
      return response.json().catch(function () {
        return { detail: 'Request failed' };
      }).then(function (payload) {
        if (!response.ok) {
          throw new Error((payload && payload.detail) || 'Request failed');
        }
        if (payload && payload.csrf_token) {
          window.CSRF_TOKEN = payload.csrf_token;
        }
        return payload;
      });
    });
  }

  if (!needsCsrf) {
    return send();
  }

  return refreshCsrfToken().then(send).catch(function (err) {
    if (String(err && err.message || '').indexOf('Invalid security token') === -1) {
      throw err;
    }
    return refreshCsrfToken().then(send);
  });
}

const api = {
  register: function (payload) {
    return apiRequest('auth/register', { method: 'POST', body: JSON.stringify(payload) });
  },
  login: function (payload) {
    return apiRequest('auth/login', { method: 'POST', body: JSON.stringify(payload) });
  },
  forgotPassword: function (payload) {
    return apiRequest('auth/forgot-password', { method: 'POST', body: JSON.stringify(payload) });
  },
  resetPassword: function (payload) {
    return apiRequest('auth/reset-password', { method: 'POST', body: JSON.stringify(payload) });
  },
  logout: function () {
    return apiRequest('auth/logout', { method: 'POST', body: JSON.stringify({}) });
  },
  me: function () {
    return apiRequest('me');
  },
  dashboard: function () {
    return apiRequest('dashboard');
  },
  listTags: function (search) {
    return apiRequest('tags', { params: { search: search || '' } });
  },
  createTag: function (payload) {
    return apiRequest('tags', { method: 'POST', body: JSON.stringify(payload) });
  },
  markPrinted: function (tagId) {
    return apiRequest('tags/print', { method: 'POST', body: JSON.stringify({ id: tagId }) });
  },
  settings: function () {
    return apiRequest('settings');
  },
  updateSettings: function (payload) {
    return apiRequest('settings', { method: 'PUT', body: JSON.stringify(payload) });
  },
  uploadShopLogo: function (file) {
    const body = new FormData();
    body.append('file', file);
    return apiRequest('settings/logo', { method: 'POST', body: body });
  },
  deleteShopLogo: function () {
    return apiRequest('settings/logo', { method: 'DELETE', body: JSON.stringify({}) });
  },
  listShopItems: function () {
    return apiRequest('settings/items');
  },
  createShopItem: function (name) {
    return apiRequest('settings/items', { method: 'POST', body: JSON.stringify({ name: name }) });
  },
  updateShopItem: function (itemId, name) {
    return apiRequest('settings/items', { method: 'PUT', body: JSON.stringify({ id: itemId, name: name }) });
  },
  deleteShopItem: function (itemId) {
    return apiRequest('settings/items', { method: 'DELETE', body: JSON.stringify({ id: itemId }) });
  },
  billingPlans: function () {
    return apiRequest('billing/plans');
  },
  billingLedger: function () {
    return apiRequest('billing/ledger');
  },
  createPurchase: function (planId, months) {
    return apiRequest('billing/purchases', {
      method: 'POST',
      body: JSON.stringify({ plan_id: planId, months: months || 1 }),
    });
  },
  confirmPurchase: function (payload) {
    return apiRequest('billing/purchases/confirm', { method: 'POST', body: JSON.stringify(payload) });
  },
  failPurchase: function (id, reason) {
    return apiRequest('billing/purchases/fail', {
      method: 'POST',
      body: JSON.stringify({ id: id, reason: reason || null }),
    });
  },
  redeemPromo: function (code) {
    return apiRequest('billing/promo', { method: 'POST', body: JSON.stringify({ code: code }) });
  },
  platform: function () {
    return apiRequest('platform');
  },
  adminPlans: function () {
    return apiRequest('admin/plans');
  },
  createAdminPlan: function (plan) {
    return apiRequest('admin/plans', { method: 'POST', body: JSON.stringify(plan) });
  },
  updateAdminPlan: function (plan) {
    return apiRequest('admin/plans', { method: 'PUT', body: JSON.stringify(plan) });
  },
  adminDashboard: function () {
    return apiRequest('admin/dashboard');
  },
  adminTenants: function (params) {
    return apiRequest('admin/tenants', { params: params || {} });
  },
  adminTenantDetail: function (shopId) {
    return apiRequest('admin/tenants/detail', { params: { id: shopId } });
  },
  adminTenantLedger: function (shopId) {
    return apiRequest('admin/tenants/ledger', { params: { id: shopId } });
  },
  adjustTenantCredits: function (shopId, payload) {
    payload.shop_id = shopId;
    return apiRequest('admin/tenants/credit-adjustments', { method: 'POST', body: JSON.stringify(payload) });
  },
  setTenantStatus: function (shopId, isActive, reason) {
    return apiRequest('admin/tenants/status', {
      method: 'POST',
      body: JSON.stringify({ shop_id: shopId, is_active: !!isActive, suspended_reason: reason || null }),
    });
  },
  recordOfflinePayment: function (payload) {
    return apiRequest('admin/tenants/offline-payment', { method: 'POST', body: JSON.stringify(payload) });
  },
  adminPurchases: function (params) {
    return apiRequest('admin/purchases', { params: params || {} });
  },
  refundPurchase: function (id, note, revokeBenefits) {
    return apiRequest('admin/purchases/refund', {
      method: 'POST',
      body: JSON.stringify({ id: id, note: note, revoke_benefits: revokeBenefits !== false }),
    });
  },
  adminFailAlerts: function () {
    return apiRequest('admin/purchases/fail-alerts');
  },
  openPurchaseReceipt: function (id) {
    const url = apiBaseUrl() + '/api.php?r=' + encodeURIComponent('admin/purchases/receipt') + '&id=' + encodeURIComponent(id);
    window.open(url, '_blank', 'noopener');
  },
  adminUsers: function () {
    return apiRequest('admin/users');
  },
  createAdminUser: function (payload) {
    return apiRequest('admin/users', { method: 'POST', body: JSON.stringify(payload) });
  },
  resetAdminPassword: function (payload) {
    return apiRequest('admin/users/reset-password', { method: 'POST', body: JSON.stringify(payload) });
  },
  impersonateTenant: function (shopId, mode, durationMinutes) {
    return apiRequest('admin/tenants/impersonate', {
      method: 'POST',
      body: JSON.stringify({
        shop_id: shopId,
        mode: mode || 'readonly',
        duration_minutes: durationMinutes || 30,
      }),
    });
  },
  updateTenantProfile: function (payload) {
    return apiRequest('admin/tenants/profile', { method: 'PUT', body: JSON.stringify(payload) });
  },
  adminTenantNotes: function (shopId) {
    return apiRequest('admin/tenants/notes', { params: { id: shopId } });
  },
  addTenantNote: function (shopId, body) {
    return apiRequest('admin/tenants/notes', {
      method: 'POST',
      body: JSON.stringify({ shop_id: shopId, body: body }),
    });
  },
  deleteTenantNote: function (noteId) {
    return apiRequest('admin/tenants/notes', {
      method: 'DELETE',
      body: JSON.stringify({ id: noteId }),
    });
  },
  sendOwnerResetLink: function (shopId) {
    return apiRequest('admin/tenants/send-reset', {
      method: 'POST',
      body: JSON.stringify({ shop_id: shopId }),
    });
  },
  exportTenantsCsv: function () {
    const headers = new Headers();
    if (window.CSRF_TOKEN) headers.set('X-CSRF-Token', window.CSRF_TOKEN);
    const url = apiBaseUrl() + '/api.php?r=' + encodeURIComponent('admin/tenants/export');
    return fetch(url, {
      method: 'GET',
      credentials: apiCredentials(),
      headers: headers,
    }).then(function (response) {
      if (!response.ok) {
        return response.json().catch(function () {
          return { detail: 'Export failed' };
        }).then(function (payload) {
          throw new Error((payload && payload.detail) || 'Export failed');
        });
      }
      return response.blob().then(function (blob) {
        const link = document.createElement('a');
        const objectUrl = URL.createObjectURL(blob);
        link.href = objectUrl;
        link.download = 'tagforge-tenants.csv';
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(objectUrl);
      });
    });
  },
  supportStart: function (token) {
    return apiRequest('auth/support-start', {
      method: 'POST',
      body: JSON.stringify({ token: token }),
    });
  },
  supportEnd: function () {
    return apiRequest('auth/support-end', { method: 'POST', body: JSON.stringify({}) });
  },
  verify2fa: function (payload) {
    return apiRequest('auth/verify-2fa', { method: 'POST', body: JSON.stringify(payload) });
  },
  adminPromos: function () {
    return apiRequest('admin/promos');
  },
  createAdminPromo: function (payload) {
    return apiRequest('admin/promos', { method: 'POST', body: JSON.stringify(payload) });
  },
  updateAdminPromo: function (payload) {
    return apiRequest('admin/promos', { method: 'PUT', body: JSON.stringify(payload) });
  },
  adminSettings: function () {
    return apiRequest('admin/settings');
  },
  saveAdminSettings: function (payload) {
    return apiRequest('admin/settings', { method: 'PUT', body: JSON.stringify(payload) });
  },
  adminActivity: function () {
    return apiRequest('admin/activity');
  },
  adminLoginAudit: function (failedOnly) {
    return apiRequest('admin/login-audit', { params: failedOnly ? { failed: 1 } : {} });
  },
  adminMailOutbox: function () {
    return apiRequest('admin/mail-outbox');
  },
  adminMailOutboxRead: function (file) {
    return apiRequest('admin/mail-outbox/read', { params: { file: file } });
  },
  adminHealth: function () {
    return apiRequest('admin/health');
  },
  admin2faStatus: function () {
    return apiRequest('admin/security/2fa');
  },
  admin2faSetup: function (payload) {
    return apiRequest('admin/security/2fa', { method: 'POST', body: JSON.stringify(payload) });
  },
};
