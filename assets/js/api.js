function apiRequest(route, options) {
  options = options || {};
  const headers = new Headers(options.headers || {});
  if (window.CSRF_TOKEN) {
    headers.set('X-CSRF-Token', window.CSRF_TOKEN);
  }
  if (options.body && !(options.body instanceof FormData) && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json');
  }

  let url = 'api.php?r=' + encodeURIComponent(route);
  if (options.params) {
    Object.keys(options.params).forEach(function (key) {
      if (options.params[key] === undefined || options.params[key] === null || options.params[key] === '') return;
      url += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(options.params[key]);
    });
  }

  const fetchOptions = {
    credentials: 'same-origin',
    method: options.method || 'GET',
    headers: headers,
  };
  if (options.body) fetchOptions.body = options.body;

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

const api = {
  register: function (payload) {
    return apiRequest('auth/register', { method: 'POST', body: JSON.stringify(payload) });
  },
  login: function (payload) {
    return apiRequest('auth/login', { method: 'POST', body: JSON.stringify(payload) });
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
  createPurchase: function (planId) {
    return apiRequest('billing/purchases', { method: 'POST', body: JSON.stringify({ plan_id: planId }) });
  },
  confirmPurchase: function (purchaseId) {
    return apiRequest('billing/purchases/confirm', { method: 'POST', body: JSON.stringify({ id: purchaseId }) });
  },
  adminPlans: function () {
    return apiRequest('admin/plans');
  },
  updateAdminPlan: function (plan) {
    return apiRequest('admin/plans', { method: 'PUT', body: JSON.stringify(plan) });
  },
  adminTenants: function () {
    return apiRequest('admin/tenants');
  },
  adminTenantLedger: function (shopId) {
    return apiRequest('admin/tenants/ledger', { params: { id: shopId } });
  },
  adjustTenantCredits: function (shopId, payload) {
    payload.shop_id = shopId;
    return apiRequest('admin/tenants/credit-adjustments', { method: 'POST', body: JSON.stringify(payload) });
  },
};
