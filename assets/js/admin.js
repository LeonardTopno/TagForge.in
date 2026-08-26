(function () {
  const blankAdjustment = {
    credits_delta: 0,
    set_credit_balance: '',
    credits_validity_days: '',
    unlimited_validity_days: '',
    payment_reference: '',
    note: 'Admin credit adjustment',
  };

  const blankOffline = {
    payment_method: 'cash',
    amount_inr: '',
    tag_credits: '',
    unlimited_days: '',
    receipt_note: '',
    note: 'Offline payment',
    plan_id: '',
  };

  const state = {
    adminUser: null,
    error: '',
    busy: false,
    view: 'dashboard',
    message: '',
    viewError: '',
    dashboard: null,
    plans: [],
    planMessage: '',
    newPlan: {
      code: '',
      name: '',
      description: '',
      price_inr: 599,
      tag_credits: 0,
      validity_days: 30,
      is_unlimited: true,
      is_active: true,
      sort_order: 10,
    },
    tenants: [],
    tenantFilters: { q: '', status: 'all', credits: 'all' },
    tenantDetail: null,
    detailTenantId: null,
    adjustment: Object.assign({}, blankAdjustment),
    offline: Object.assign({}, blankOffline),
    purchases: [],
    purchaseFilters: { q: '', status: 'all' },
    failAlerts: [],
    admins: [],
    adminForm: { name: '', email: '', password: '' },
    promos: [],
    newPromo: {
      code: '',
      description: '',
      tag_credits: 50,
      validity_days: 7,
      is_unlimited: false,
      max_redemptions: 0,
    },
    settings: {},
    activity: [],
    loginAudit: [],
    mailOutbox: [],
    mailPreview: '',
    health: null,
    security: null,
    pendingTotpSecret: '',
    pendingTotpUrl: '',
    pending2fa: null,
  };

  const root = document.getElementById('app');
  const brandName = (window.APP_CONFIG && window.APP_CONFIG.appName) || 'TagForge';
  const brandTagline = (window.APP_CONFIG && window.APP_CONFIG.appTagline) || 'Print tags. Run your shop.';
  const shopUrl = (window.APP_CONFIG && window.APP_CONFIG.shopUrl) || 'index.php';

  const NAV = [
    { id: 'dashboard', label: 'Dashboard', icon: 'bi-speedometer2' },
    { id: 'tenants', label: 'Tenants', icon: 'bi-shop' },
    { id: 'purchases', label: 'Purchases', icon: 'bi-receipt' },
    { id: 'promos', label: 'Promos', icon: 'bi-ticket-perforated' },
    { id: 'plans', label: 'Plans', icon: 'bi-sliders' },
    { id: 'settings', label: 'Settings', icon: 'bi-gear' },
    { id: 'security', label: 'Security', icon: 'bi-shield-lock' },
    { id: 'ops', label: 'Ops', icon: 'bi-heart-pulse' },
    { id: 'admins', label: 'Admins', icon: 'bi-people' },
  ];

  function formatDate(value) {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return new Intl.DateTimeFormat('en-IN', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    }).format(date);
  }

  function formatDateTime(value) {
    if (!value) return '—';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '—';
    return new Intl.DateTimeFormat('en-IN', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    }).format(date);
  }

  function formatInr(amount) {
    return new Intl.NumberFormat('en-IN', {
      style: 'currency',
      currency: 'INR',
      maximumFractionDigits: 0,
    }).format(Number(amount) || 0);
  }

  function footer() {
    return '<footer class="app-footer">Migids Software LLP, Bengaluru</footer>';
  }

  function flash() {
    let html = '';
    if (state.message) {
      html += '<div class="success-banner">' + escapeHtml(state.message) + '</div>';
    }
    if (state.viewError) {
      html += '<div class="error-banner">' + escapeHtml(state.viewError) + '</div>';
    }
    return html;
  }

  function statusBadge(tenant) {
    if (!tenant.is_active) {
      return '<span class="admin-badge admin-badge-danger">Suspended</span>';
    }
    if (tenant.is_unlimited_active) {
      return '<span class="admin-badge admin-badge-ok">Unlimited</span>';
    }
    if (tenant.credits_expired) {
      return '<span class="admin-badge admin-badge-warn">Expired</span>';
    }
    if (tenant.tag_credit_balance <= 5) {
      return '<span class="admin-badge admin-badge-warn">Low credits</span>';
    }
    return '<span class="admin-badge">Active</span>';
  }

  function purchaseStatusBadge(status) {
    const map = {
      paid: 'admin-badge-ok',
      pending: 'admin-badge-warn',
      failed: 'admin-badge-danger',
      refunded: 'admin-badge-muted',
    };
    return '<span class="admin-badge ' + (map[status] || '') + '">' + escapeHtml(status || '—') + '</span>';
  }

  function renderLogin() {
    if (state.pending2fa) {
      const methods = state.pending2fa.methods || [];
      return (
        '<main class="auth-layout admin-auth-layout">' +
          '<section class="auth-panel">' +
            '<div class="auth-heading">' +
              '<img class="brand-lockup" src="assets/img/logo.png" alt="' + escapeHtml(brandName) + '">' +
              '<p class="auth-support">Two-factor authentication</p>' +
            '</div>' +
            '<form data-action="admin-2fa" class="form-grid">' +
              (methods.indexOf('totp') >= 0 && methods.indexOf('email') >= 0
                ? '<label>Method<select class="form-control" name="method">' +
                    '<option value="totp">Authenticator app</option><option value="email">Email code</option>' +
                  '</select></label>'
                : '<input type="hidden" name="method" value="' + escapeHtml(methods[0] || 'totp') + '">') +
              '<label>Verification code<input class="form-control" name="code" inputmode="numeric" autocomplete="one-time-code" required></label>' +
              (state.error ? '<div class="error-banner">' + escapeHtml(state.error) + '</div>' : '') +
              '<button class="primary-button" ' + (state.busy ? 'disabled' : '') + '>Verify and continue</button>' +
            '</form>' +
          '</section>' +
          footer() +
        '</main>'
      );
    }
    return (
      '<main class="auth-layout admin-auth-layout">' +
        '<section class="auth-panel">' +
          '<div class="auth-heading">' +
            '<img class="brand-lockup" src="assets/img/logo.png" alt="' + escapeHtml(brandName) + '">' +
            '<p class="brand-tagline">' + escapeHtml(brandTagline) + '</p>' +
            '<p class="auth-support">Admin portal — shops, billing, and platform controls.</p>' +
          '</div>' +
          '<form data-action="admin-login" class="form-grid">' +
            '<label>Admin email<input class="form-control" name="email" type="email" required></label>' +
            '<label>Password<input class="form-control" name="password" type="password" minlength="8" required></label>' +
            (state.error ? '<div class="error-banner">' + escapeHtml(state.error) + '</div>' : '') +
            '<button class="primary-button" ' + (state.busy ? 'disabled' : '') + '>' +
              '<i class="bi bi-shield-lock"></i> Sign in to admin' +
            '</button>' +
          '</form>' +
        '</section>' +
        footer() +
      '</main>'
    );
  }

  function renderNav() {
    return (
      '<nav class="admin-nav">' +
        NAV.map(function (item) {
          const active = state.view === item.id || (item.id === 'tenants' && state.view === 'tenant-detail');
          return (
            '<button type="button" class="admin-nav-btn' + (active ? ' is-active' : '') + '" data-action="nav" data-view="' + item.id + '">' +
              '<i class="bi ' + item.icon + '"></i> ' + escapeHtml(item.label) +
            '</button>'
          );
        }).join('') +
      '</nav>'
    );
  }

  function renderDashboard() {
    const d = state.dashboard || {};
    const low = (d.low_credit_shops || []).map(function (shop) {
      return (
        '<tr data-action="open-tenant" data-id="' + shop.id + '">' +
          '<td>' + escapeHtml(shop.name) + '</td>' +
          '<td>' + escapeHtml(shop.owner_email || '—') + '</td>' +
          '<td>' + shop.tag_credit_balance + '</td>' +
          '<td>' + formatDateTime(shop.last_active_at) + '</td>' +
        '</tr>'
      );
    }).join('');

    return (
      '<section class="admin-view">' +
        '<div class="panel-title"><h2>Overview</h2><span>Live platform metrics</span></div>' +
        flash() +
        '<div class="admin-stat-grid">' +
          metric('Shops', d.total_shops) +
          metric('Active unlimited', d.active_unlimited) +
          metric('Tags today', d.tags_today) +
          metric('Tags this month', d.tags_month) +
          metric('Revenue this month', formatInr(d.revenue_month_inr)) +
          metric('Suspended', d.suspended_shops) +
          metric('Pending payments', d.pending_purchases) +
          metric('Failed / stale payments', d.failed_payment_alerts) +
        '</div>' +
        '<section class="table-panel">' +
          '<div class="panel-title"><h2>Low-credit shops</h2><span>≤ 5 credits, no unlimited</span></div>' +
          '<table class="table"><thead><tr><th>Shop</th><th>Owner</th><th>Credits</th><th>Last active</th></tr></thead>' +
          '<tbody>' + (low || '<tr><td colspan="4">None right now</td></tr>') + '</tbody></table>' +
        '</section>' +
      '</section>'
    );
  }

  function metric(label, value) {
    return (
      '<div class="admin-stat">' +
        '<span>' + escapeHtml(label) + '</span>' +
        '<strong>' + (value === undefined || value === null ? '—' : value) + '</strong>' +
      '</div>'
    );
  }

  function renderTenants() {
    const f = state.tenantFilters;
    const rows = state.tenants.map(function (row) {
      return (
        '<tr data-action="open-tenant" data-id="' + row.id + '">' +
          '<td><strong>' + escapeHtml(row.name) + '</strong><div class="muted">' + escapeHtml(row.short_name || '') + '</div></td>' +
          '<td>' + escapeHtml(row.owner_name || '—') + '<div class="muted">' + escapeHtml(row.owner_email || '') + '</div></td>' +
          '<td>' + row.tag_credit_balance + '</td>' +
          '<td>' + (row.is_unlimited_active ? formatDate(row.unlimited_until) : '—') + '</td>' +
          '<td>' + statusBadge(row) + '</td>' +
          '<td>' + formatDateTime(row.last_active_at) + '</td>' +
        '</tr>'
      );
    }).join('');

    return (
      '<section class="admin-view">' +
        '<div class="panel-title"><h2>Tenants</h2>' +
          '<div class="button-row">' +
            '<span>' + state.tenants.length + ' shown</span>' +
            '<button type="button" class="secondary-button" data-action="export-tenants"><i class="bi bi-download"></i> Export CSV</button>' +
          '</div>' +
        '</div>' +
        flash() +
        '<form data-action="filter-tenants" class="admin-filters form-grid compact">' +
          '<label>Search<input class="form-control" name="q" value="' + escapeHtml(f.q) + '" placeholder="Name, email, phone, GST"></label>' +
          '<label>Status<select class="form-control" name="status">' +
            option('all', 'All', f.status) +
            option('active', 'Active', f.status) +
            option('suspended', 'Suspended', f.status) +
            option('unlimited', 'Unlimited', f.status) +
            option('expired', 'Expired credits', f.status) +
          '</select></label>' +
          '<label>Credits<select class="form-control" name="credits">' +
            option('all', 'All', f.credits) +
            option('low', 'Low (≤5)', f.credits) +
            option('zero', 'Zero', f.credits) +
          '</select></label>' +
          '<div class="button-row settings-actions">' +
            '<button class="primary-button" type="submit"><i class="bi bi-search"></i> Filter</button>' +
          '</div>' +
        '</form>' +
        '<section class="table-panel">' +
          '<table class="table"><thead><tr>' +
            '<th>Shop</th><th>Owner</th><th>Credits</th><th>Unlimited</th><th>Status</th><th>Last active</th>' +
          '</tr></thead><tbody>' +
            (rows || '<tr><td colspan="6">No tenants match</td></tr>') +
          '</tbody></table>' +
        '</section>' +
      '</section>'
    );
  }

  function option(value, label, current) {
    return '<option value="' + value + '"' + (current === value ? ' selected' : '') + '>' + escapeHtml(label) + '</option>';
  }

  function renderTenantDetail() {
    const detail = state.tenantDetail;
    if (!detail || !detail.tenant) {
      return '<section class="admin-view">' + flash() + '<p>Loading tenant…</p></section>';
    }
    const t = detail.tenant;
    const adj = state.adjustment;
    const off = state.offline;
    const usage = detail.usage || {};
    const notes = (detail.notes || []).map(function (note) {
      return (
        '<article class="admin-note">' +
          '<div class="admin-note-meta">' +
            '<strong>' + escapeHtml(note.author_name || 'Admin') + '</strong>' +
            '<span>' + formatDateTime(note.created_at) + '</span>' +
            '<button type="button" class="ghost-button" data-action="delete-note" data-id="' + note.id + '">Delete</button>' +
          '</div>' +
          '<p>' + escapeHtml(note.body) + '</p>' +
        '</article>'
      );
    }).join('');
    const tags = (detail.recent_tags || []).map(function (tag) {
      return '<tr><td>' + escapeHtml(tag.tag_number) + '</td><td>' + escapeHtml(tag.item_name || '') + '</td><td>' + formatDateTime(tag.created_at) + '</td></tr>';
    }).join('');
    const prints = (detail.recent_prints || []).map(function (row) {
      return '<tr><td>' + escapeHtml(row.tag_number || '') + '</td><td>' + row.copies + '</td><td>' + escapeHtml(row.printed_by_name || '') + '</td><td>' + formatDateTime(row.printed_at) + '</td></tr>';
    }).join('');
    const purchases = (detail.purchases || []).map(function (p) {
      return (
        '<tr>' +
          '<td>' + formatDateTime(p.paid_at || p.created_at) + '</td>' +
          '<td>' + escapeHtml(p.plan_name || p.payment_method || '') + '</td>' +
          '<td>' + formatInr(p.amount_inr) + '</td>' +
          '<td>' + purchaseStatusBadge(p.status) + '</td>' +
          '<td>' + escapeHtml(p.receipt_note || p.razorpay_payment_id || '—') + '</td>' +
        '</tr>'
      );
    }).join('');
    const ledger = (detail.ledger || []).map(function (entry) {
      return '<tr><td>' + formatDateTime(entry.created_at) + '</td><td>' + escapeHtml(entry.entry_type) + '</td><td>' + entry.credits + '</td><td>' + entry.balance_after + '</td><td>' + escapeHtml(entry.description) + '</td></tr>';
    }).join('');

    return (
      '<section class="admin-view">' +
        '<div class="panel-title">' +
          '<div>' +
            '<button type="button" class="ghost-button" data-action="nav" data-view="tenants"><i class="bi bi-arrow-left"></i> Tenants</button>' +
            '<h2>' + escapeHtml(t.name) + '</h2>' +
          '</div>' +
          '<div class="button-row">' +
            statusBadge(t) +
            '<button type="button" class="secondary-button" data-action="impersonate" data-id="' + t.id + '" data-mode="readonly"><i class="bi bi-eye"></i> View as shop</button>' +
            '<button type="button" class="secondary-button" data-action="impersonate" data-id="' + t.id + '" data-mode="timed"><i class="bi bi-clock-history"></i> Timed edit (30m)</button>' +
            (t.is_active
              ? '<button type="button" class="danger-button" data-action="suspend-tenant" data-id="' + t.id + '"><i class="bi bi-slash-circle"></i> Suspend</button>'
              : '<button type="button" class="primary-button" data-action="activate-tenant" data-id="' + t.id + '"><i class="bi bi-check-circle"></i> Activate</button>') +
          '</div>' +
        '</div>' +
        flash() +
        (!t.is_active && t.suspended_reason ? '<div class="error-banner">Suspended: ' + escapeHtml(t.suspended_reason) + '</div>' : '') +
        '<div class="admin-stat-grid">' +
          metric('Tags created', usage.tags_created) +
          metric('Prints', usage.total_prints) +
          metric('Reprints', usage.reprints) +
          metric('Credits used', usage.credits_used) +
          metric('Last print', usage.last_print_at ? formatDateTime(usage.last_print_at) : '—') +
          metric('Last tag', usage.last_tag_at ? formatDateTime(usage.last_tag_at) : '—') +
        '</div>' +
        '<div class="admin-detail-grid">' +
          '<article class="entry-panel">' +
            '<div class="panel-title"><h2>Edit shop profile</h2></div>' +
            (t.logo_url ? '<img class="admin-logo-preview" src="' + escapeHtml(t.logo_url) + '" alt="">' : '') +
            '<form data-action="edit-profile" class="form-grid compact">' +
              '<label>Shop name<input class="form-control" name="name" value="' + escapeHtml(t.name || '') + '" required></label>' +
              '<label>Short name<input class="form-control" name="short_name" value="' + escapeHtml(t.short_name || '') + '" required></label>' +
              '<label>Phone<input class="form-control" name="phone_number" value="' + escapeHtml(t.phone_number || '') + '"></label>' +
              '<label>GST<input class="form-control" name="gst_no" value="' + escapeHtml(t.gst_no || '') + '"></label>' +
              '<label>Tag prefix<input class="form-control" name="tag_prefix" value="' + escapeHtml(t.tag_prefix || '') + '" required></label>' +
              '<label class="wide-field">Address<input class="form-control" name="address" value="' + escapeHtml(t.address || '') + '"></label>' +
              '<button class="primary-button" type="submit"><i class="bi bi-floppy"></i> Save profile</button>' +
            '</form>' +
            '<p class="muted" style="margin-top:12px">Created ' + formatDate(t.created_at) + ' · Last active ' + formatDateTime(t.last_active_at) + '</p>' +
          '</article>' +
          '<article class="entry-panel">' +
            '<div class="panel-title"><h2>Owner & access</h2></div>' +
            '<dl class="admin-dl">' +
              '<div><dt>Owner</dt><dd>' + escapeHtml(t.owner_name || '—') + '</dd></div>' +
              '<div><dt>Email</dt><dd>' + escapeHtml(t.owner_email || '—') + '</dd></div>' +
              '<div><dt>Credits</dt><dd>' + t.tag_credit_balance + '</dd></div>' +
              '<div><dt>Credit expiry</dt><dd>' + formatDate(t.credits_expire_at) + '</dd></div>' +
              '<div><dt>Unlimited until</dt><dd>' + (t.is_unlimited_active ? formatDate(t.unlimited_until) : '—') + '</dd></div>' +
            '</dl>' +
            '<div class="button-row" style="margin-top:12px">' +
              '<button type="button" class="secondary-button" data-action="send-owner-reset" data-id="' + t.id + '"><i class="bi bi-envelope"></i> Email reset link</button>' +
            '</div>' +
            (t.owner_id
              ? '<form data-action="reset-owner-password" class="form-grid compact" style="margin-top:14px">' +
                  '<input type="hidden" name="user_id" value="' + t.owner_id + '">' +
                  '<label class="wide-field">Set password directly<input class="form-control" name="password" type="password" minlength="8" required placeholder="New password"></label>' +
                  '<button class="secondary-button" type="submit"><i class="bi bi-key"></i> Set owner password</button>' +
                '</form>'
              : '') +
          '</article>' +
        '</div>' +
        '<div class="admin-detail-grid">' +
          '<article class="entry-panel">' +
            '<div class="panel-title"><h2>Support notes</h2></div>' +
            '<form data-action="add-note" class="form-grid compact">' +
              '<label class="wide-field">Internal note<textarea class="form-control" name="body" rows="3" required placeholder="Called, will renew Friday"></textarea></label>' +
              '<button class="primary-button" type="submit"><i class="bi bi-journal-plus"></i> Add note</button>' +
            '</form>' +
            '<div class="admin-notes-list">' + (notes || '<p class="muted">No notes yet</p>') + '</div>' +
          '</article>' +
          '<article class="entry-panel">' +
            '<div class="panel-title"><h2>Record offline payment</h2></div>' +
            '<form data-action="offline-payment" class="form-grid compact">' +
              '<label>Method<select class="form-control" name="payment_method">' +
                option('cash', 'Cash', off.payment_method) +
                option('upi', 'UPI', off.payment_method) +
                option('bank_transfer', 'Bank transfer', off.payment_method) +
                option('manual', 'Manual', off.payment_method) +
              '</select></label>' +
              '<label>Amount INR<input class="form-control" type="number" name="amount_inr" value="' + escapeHtml(off.amount_inr) + '" min="0" required></label>' +
              '<label>Tag credits<input class="form-control" type="number" name="tag_credits" value="' + escapeHtml(off.tag_credits) + '" min="0" placeholder="0"></label>' +
              '<label>Unlimited days<input class="form-control" type="number" name="unlimited_days" value="' + escapeHtml(off.unlimited_days) + '" min="0" placeholder="0"></label>' +
              '<label class="wide-field">Receipt note<input class="form-control" name="receipt_note" value="' + escapeHtml(off.receipt_note) + '" placeholder="UPI ref / cash receipt #"></label>' +
              '<label class="wide-field">Internal note<input class="form-control" name="note" value="' + escapeHtml(off.note) + '" required></label>' +
              '<button class="primary-button" type="submit"><i class="bi bi-cash-coin"></i> Grant credits</button>' +
            '</form>' +
          '</article>' +
        '</div>' +
        '<div class="admin-detail-grid">' +
          '<article class="entry-panel">' +
            '<div class="panel-title"><h2>Adjust credits</h2></div>' +
            '<form data-action="adjust-credits" class="form-grid compact">' +
              '<label>Add/remove credits<input class="form-control" type="number" name="credits_delta" value="' + escapeHtml(adj.credits_delta) + '"></label>' +
              '<label>Set balance<input class="form-control" type="number" name="set_credit_balance" value="' + escapeHtml(adj.set_credit_balance) + '" placeholder="Leave blank"></label>' +
              '<label>Credit validity days<input class="form-control" type="number" name="credits_validity_days" value="' + escapeHtml(adj.credits_validity_days) + '"></label>' +
              '<label>Unlimited days<input class="form-control" type="number" name="unlimited_validity_days" value="' + escapeHtml(adj.unlimited_validity_days) + '"></label>' +
              '<label class="wide-field">Payment reference<input class="form-control" name="payment_reference" value="' + escapeHtml(adj.payment_reference) + '"></label>' +
              '<label class="wide-field">Note<input class="form-control" name="note" value="' + escapeHtml(adj.note) + '" required></label>' +
              '<button class="primary-button" type="submit"><i class="bi bi-floppy"></i> Update credits</button>' +
            '</form>' +
          '</article>' +
        '</div>' +
        '<section class="table-panel"><div class="panel-title"><h2>Recent tags</h2></div>' +
          '<table class="table"><thead><tr><th>Tag</th><th>Item</th><th>Created</th></tr></thead><tbody>' +
          (tags || '<tr><td colspan="3">No tags yet</td></tr>') + '</tbody></table></section>' +
        '<section class="table-panel"><div class="panel-title"><h2>Recent prints</h2></div>' +
          '<table class="table"><thead><tr><th>Tag</th><th>Copies</th><th>By</th><th>When</th></tr></thead><tbody>' +
          (prints || '<tr><td colspan="4">No prints yet</td></tr>') + '</tbody></table></section>' +
        '<section class="table-panel"><div class="panel-title"><h2>Purchases</h2></div>' +
          '<table class="table"><thead><tr><th>Date</th><th>Plan / method</th><th>Amount</th><th>Status</th><th>Receipt</th></tr></thead><tbody>' +
          (purchases || '<tr><td colspan="5">No purchases</td></tr>') + '</tbody></table></section>' +
        '<section class="table-panel"><div class="panel-title"><h2>Ledger</h2></div>' +
          '<table class="table"><thead><tr><th>Date</th><th>Type</th><th>Credits</th><th>Balance</th><th>Description</th></tr></thead><tbody>' +
          (ledger || '<tr><td colspan="5">No ledger entries</td></tr>') + '</tbody></table></section>' +
      '</section>'
    );
  }

  function renderPurchases() {
    const f = state.purchaseFilters;
    const rows = state.purchases.map(function (p) {
      return (
        '<tr>' +
          '<td>' + formatDateTime(p.paid_at || p.created_at) + '</td>' +
          '<td><strong>' + escapeHtml(p.shop_name || '—') + '</strong><div class="muted">' + escapeHtml(p.plan_name || '') + '</div></td>' +
          '<td>' + formatInr(p.amount_inr) + '</td>' +
          '<td>' + escapeHtml(p.payment_method || 'razorpay') + '</td>' +
          '<td>' + purchaseStatusBadge(p.status) + '</td>' +
          '<td>' + escapeHtml(p.receipt_note || p.razorpay_payment_id || '—') + '</td>' +
          '<td>' +
            (p.status === 'paid'
              ? '<button type="button" class="ghost-button" data-action="open-receipt" data-id="' + p.id + '">Receipt</button> ' +
                '<button type="button" class="ghost-button" data-action="refund-purchase" data-id="' + p.id + '">Refund</button>'
              : '—') +
          '</td>' +
        '</tr>'
      );
    }).join('');

    return (
      '<section class="admin-view">' +
        '<div class="panel-title"><h2>Purchase history</h2><span>' + state.purchases.length + ' shown</span></div>' +
        flash() +
        '<form data-action="filter-purchases" class="admin-filters form-grid compact">' +
          '<label>Search<input class="form-control" name="q" value="' + escapeHtml(f.q) + '" placeholder="Shop, payment id, receipt"></label>' +
          '<label>Status<select class="form-control" name="status">' +
            option('all', 'All', f.status) +
            option('paid', 'Paid', f.status) +
            option('pending', 'Pending', f.status) +
            option('failed', 'Failed', f.status) +
            option('refunded', 'Refunded', f.status) +
          '</select></label>' +
          '<div class="button-row settings-actions">' +
            '<button class="primary-button" type="submit"><i class="bi bi-search"></i> Filter</button>' +
          '</div>' +
        '</form>' +
        '<section class="table-panel">' +
          '<table class="table"><thead><tr>' +
            '<th>Date</th><th>Shop / plan</th><th>Amount</th><th>Method</th><th>Status</th><th>Receipt</th><th></th>' +
          '</tr></thead><tbody>' +
            (rows || '<tr><td colspan="7">No purchases</td></tr>') +
          '</tbody></table>' +
        '</section>' +
      '</section>'
    );
  }

  function renderAdmins() {
    const rows = state.admins.map(function (user) {
      return (
        '<tr>' +
          '<td>' + escapeHtml(user.name) + '</td>' +
          '<td>' + escapeHtml(user.email) + '</td>' +
          '<td>' + formatDate(user.created_at) + '</td>' +
          '<td>' +
            '<button type="button" class="ghost-button" data-action="reset-admin-password" data-id="' + user.id + '" data-email="' + escapeHtml(user.email) + '">' +
              '<i class="bi bi-key"></i> Reset password' +
            '</button>' +
          '</td>' +
        '</tr>'
      );
    }).join('');
    const form = state.adminForm;

    return (
      '<section class="admin-view">' +
        '<div class="panel-title"><h2>Admin users</h2><span>' + state.admins.length + ' accounts</span></div>' +
        flash() +
        '<div class="admin-detail-grid">' +
          '<article class="entry-panel">' +
            '<div class="panel-title"><h2>Invite admin</h2></div>' +
            '<form data-action="create-admin" class="form-grid compact">' +
              '<label>Name<input class="form-control" name="name" value="' + escapeHtml(form.name) + '" required></label>' +
              '<label>Email<input class="form-control" name="email" type="email" value="' + escapeHtml(form.email) + '" required></label>' +
              '<label class="wide-field">Temporary password<input class="form-control" name="password" type="password" minlength="8" value="' + escapeHtml(form.password) + '" required></label>' +
              '<button class="primary-button" type="submit"><i class="bi bi-person-plus"></i> Create admin</button>' +
            '</form>' +
          '</article>' +
          '<section class="table-panel">' +
            '<div class="panel-title"><h2>Existing admins</h2></div>' +
            '<table class="table"><thead><tr><th>Name</th><th>Email</th><th>Created</th><th></th></tr></thead>' +
            '<tbody>' + (rows || '<tr><td colspan="4">No admins</td></tr>') + '</tbody></table>' +
          '</section>' +
        '</div>' +
      '</section>'
    );
  }

  function renderPlans() {
    const np = state.newPlan;
    const cards = state.plans.map(function (plan) {
      return (
        '<article class="entry-panel admin-plan">' +
          '<div class="panel-title"><h2>' + escapeHtml(plan.code) + (plan.is_system ? ' <span class="admin-badge">system</span>' : '') + '</h2>' +
            '<label class="check-row"><input type="checkbox" data-plan="' + plan.id + '" data-field="is_active"' + (plan.is_active ? ' checked' : '') + '> Active</label>' +
          '</div>' +
          '<div class="form-grid compact">' +
            '<label>Name<input class="form-control" data-plan="' + plan.id + '" data-field="name" value="' + escapeHtml(plan.name) + '"></label>' +
            '<label>Price INR<input class="form-control" type="number" data-plan="' + plan.id + '" data-field="price_inr" value="' + plan.price_inr + '"></label>' +
            '<label>Tag credits<input class="form-control" type="number" data-plan="' + plan.id + '" data-field="tag_credits" value="' + plan.tag_credits + '"></label>' +
            '<label>Validity days<input class="form-control" type="number" data-plan="' + plan.id + '" data-field="validity_days" value="' + plan.validity_days + '"></label>' +
            '<label>Sort order<input class="form-control" type="number" data-plan="' + plan.id + '" data-field="sort_order" value="' + plan.sort_order + '"></label>' +
            '<label class="check-row"><input type="checkbox" data-plan="' + plan.id + '" data-field="is_unlimited"' + (plan.is_unlimited ? ' checked' : '') + '> Unlimited plan</label>' +
            '<label class="wide-field">Description<input class="form-control" data-plan="' + plan.id + '" data-field="description" value="' + escapeHtml(plan.description || '') + '"></label>' +
          '</div>' +
          '<div class="button-row settings-actions">' +
            '<button class="primary-button" type="button" data-action="save-plan" data-id="' + plan.id + '"><i class="bi bi-floppy"></i> Save plan</button>' +
          '</div>' +
        '</article>'
      );
    }).join('');

    return (
      '<section class="admin-view admin-plan-list">' +
        '<div class="panel-title"><h2>Billing plans</h2></div>' +
        flash() +
        (state.planMessage ? '<div class="success-banner">' + escapeHtml(state.planMessage) + '</div>' : '') +
        '<article class="entry-panel">' +
          '<div class="panel-title"><h2>Create plan</h2></div>' +
          '<form data-action="create-plan" class="form-grid compact">' +
            '<label>Code<input class="form-control" name="code" value="' + escapeHtml(np.code) + '" required placeholder="credits50"></label>' +
            '<label>Name<input class="form-control" name="name" value="' + escapeHtml(np.name) + '" required></label>' +
            '<label>Price INR<input class="form-control" type="number" name="price_inr" value="' + escapeHtml(np.price_inr) + '" min="0"></label>' +
            '<label>Tag credits<input class="form-control" type="number" name="tag_credits" value="' + escapeHtml(np.tag_credits) + '" min="0"></label>' +
            '<label>Validity days<input class="form-control" type="number" name="validity_days" value="' + escapeHtml(np.validity_days) + '" min="1"></label>' +
            '<label>Sort<input class="form-control" type="number" name="sort_order" value="' + escapeHtml(np.sort_order) + '"></label>' +
            '<label class="check-row"><input type="checkbox" name="is_unlimited"' + (np.is_unlimited ? ' checked' : '') + '> Unlimited</label>' +
            '<label class="wide-field">Description<input class="form-control" name="description" value="' + escapeHtml(np.description) + '" required></label>' +
            '<button class="primary-button" type="submit"><i class="bi bi-plus-lg"></i> Create plan</button>' +
          '</form>' +
        '</article>' +
        cards +
      '</section>'
    );
  }

  function renderPromos() {
    const np = state.newPromo;
    const rows = state.promos.map(function (p) {
      return (
        '<tr>' +
          '<td><strong>' + escapeHtml(p.code) + '</strong><div class="muted">' + escapeHtml(p.description || '') + '</div></td>' +
          '<td>' + (p.is_unlimited ? 'Unlimited' : p.tag_credits + ' credits') + '</td>' +
          '<td>' + p.validity_days + ' days</td>' +
          '<td>' + p.redemption_count + (p.max_redemptions ? ' / ' + p.max_redemptions : '') + '</td>' +
          '<td>' + (p.is_active ? '<span class="admin-badge admin-badge-ok">Active</span>' : '<span class="admin-badge admin-badge-muted">Off</span>') + '</td>' +
          '<td><button type="button" class="ghost-button" data-action="toggle-promo" data-id="' + p.id + '" data-active="' + (p.is_active ? '0' : '1') + '">' +
            (p.is_active ? 'Disable' : 'Enable') + '</button></td>' +
        '</tr>'
      );
    }).join('');
    return (
      '<section class="admin-view">' +
        '<div class="panel-title"><h2>Promo codes / free trials</h2></div>' +
        flash() +
        '<article class="entry-panel">' +
          '<form data-action="create-promo" class="form-grid compact">' +
            '<label>Code<input class="form-control" name="code" value="' + escapeHtml(np.code) + '" required placeholder="TRIAL50"></label>' +
            '<label>Credits<input class="form-control" type="number" name="tag_credits" value="' + escapeHtml(np.tag_credits) + '" min="0"></label>' +
            '<label>Validity days<input class="form-control" type="number" name="validity_days" value="' + escapeHtml(np.validity_days) + '" min="1"></label>' +
            '<label>Max redemptions<input class="form-control" type="number" name="max_redemptions" value="' + escapeHtml(np.max_redemptions) + '" min="0" placeholder="0 = unlimited"></label>' +
            '<label class="check-row"><input type="checkbox" name="is_unlimited"' + (np.is_unlimited ? ' checked' : '') + '> Unlimited trial</label>' +
            '<label class="wide-field">Description<input class="form-control" name="description" value="' + escapeHtml(np.description) + '" placeholder="50 tags for 7 days"></label>' +
            '<button class="primary-button" type="submit"><i class="bi bi-plus-lg"></i> Create promo</button>' +
          '</form>' +
        '</article>' +
        '<section class="table-panel"><table class="table"><thead><tr><th>Code</th><th>Grant</th><th>Validity</th><th>Uses</th><th>Status</th><th></th></tr></thead>' +
          '<tbody>' + (rows || '<tr><td colspan="6">No promos yet</td></tr>') + '</tbody></table></section>' +
      '</section>'
    );
  }

  function renderSettings() {
    const s = state.settings || {};
    return (
      '<section class="admin-view">' +
        '<div class="panel-title"><h2>Platform settings</h2></div>' +
        flash() +
        '<form data-action="save-settings" class="admin-detail-grid">' +
          '<article class="entry-panel"><div class="panel-title"><h2>Free pack & defaults</h2></div><div class="form-grid compact">' +
            '<label>Signup credits<input class="form-control" name="free_registration_credits" type="number" value="' + escapeHtml(s.free_registration_credits || '') + '"></label>' +
            '<label>Signup validity days<input class="form-control" name="free_registration_validity_days" type="number" value="' + escapeHtml(s.free_registration_validity_days || '') + '"></label>' +
            '<label>Default tag width mm<input class="form-control" name="default_tag_width_mm" value="' + escapeHtml(s.default_tag_width_mm || '80.00') + '"></label>' +
            '<label>Default tag height mm<input class="form-control" name="default_tag_height_mm" value="' + escapeHtml(s.default_tag_height_mm || '18.00') + '"></label>' +
            '<label>Default font pt<input class="form-control" name="default_font_size_pt" value="' + escapeHtml(s.default_font_size_pt || '8.00') + '"></label>' +
          '</div></article>' +
          '<article class="entry-panel"><div class="panel-title"><h2>Announcement & flags</h2></div><div class="form-grid compact">' +
            '<label class="check-row"><input type="checkbox" name="announcement_enabled"' + (s.announcement_enabled === '1' || s.announcement_enabled === true ? ' checked' : '') + '> Show announcement banner</label>' +
            '<label class="wide-field">Announcement message<textarea class="form-control" name="announcement_message" rows="3">' + escapeHtml(s.announcement_message || '') + '</textarea></label>' +
            '<label class="check-row"><input type="checkbox" name="feature_razorpay"' + (s.feature_razorpay !== '0' ? ' checked' : '') + '> Razorpay enabled</label>' +
            '<label class="check-row"><input type="checkbox" name="feature_registration"' + (s.feature_registration !== '0' ? ' checked' : '') + '> Registration open</label>' +
            '<label class="check-row"><input type="checkbox" name="feature_reprints"' + (s.feature_reprints !== '0' ? ' checked' : '') + '> Reprints allowed</label>' +
          '</div></article>' +
          '<article class="entry-panel"><div class="panel-title"><h2>Invoice / GST (platform)</h2></div><div class="form-grid compact">' +
            '<label>Legal name<input class="form-control" name="invoice_legal_name" value="' + escapeHtml(s.invoice_legal_name || '') + '"></label>' +
            '<label>GSTIN<input class="form-control" name="invoice_gstin" value="' + escapeHtml(s.invoice_gstin || '') + '"></label>' +
            '<label>State<input class="form-control" name="invoice_state" value="' + escapeHtml(s.invoice_state || '') + '"></label>' +
            '<label>State code<input class="form-control" name="invoice_state_code" value="' + escapeHtml(s.invoice_state_code || '') + '"></label>' +
            '<label class="wide-field">Address<input class="form-control" name="invoice_address" value="' + escapeHtml(s.invoice_address || '') + '"></label>' +
            '<label class="wide-field">Invoice email<input class="form-control" name="invoice_email" value="' + escapeHtml(s.invoice_email || '') + '"></label>' +
          '</div></article>' +
          '<div class="button-row"><button class="primary-button" type="submit"><i class="bi bi-floppy"></i> Save settings</button></div>' +
        '</form>' +
      '</section>'
    );
  }

  function renderSecurity() {
    const sec = state.security || {};
    const activity = (state.activity || []).map(function (row) {
      return '<tr><td>' + formatDateTime(row.created_at) + '</td><td>' + escapeHtml(row.admin_email || '—') + '</td><td>' + escapeHtml(row.action) + '</td><td>' + escapeHtml(row.detail || '') + '</td><td>' + escapeHtml(row.ip || '') + '</td></tr>';
    }).join('');
    const audit = (state.loginAudit || []).map(function (row) {
      return '<tr><td>' + formatDateTime(row.created_at) + '</td><td>' + escapeHtml(row.email) + '</td><td>' + (row.success ? 'ok' : 'fail') + '</td><td>' + escapeHtml(row.detail || '') + '</td><td>' + escapeHtml(row.ip || '') + '</td></tr>';
    }).join('');
    return (
      '<section class="admin-view">' +
        '<div class="panel-title"><h2>Security</h2></div>' +
        flash() +
        '<div class="admin-detail-grid">' +
          '<article class="entry-panel"><div class="panel-title"><h2>Admin 2FA</h2></div>' +
            '<p class="muted">Email OTP: ' + (sec.email_otp_enabled ? 'on' : 'off') + ' · TOTP: ' + (sec.totp_enabled ? 'on' : 'off') + '</p>' +
            '<div class="button-row">' +
              '<button type="button" class="secondary-button" data-action="2fa-email-toggle" data-enable="' + (sec.email_otp_enabled ? '0' : '1') + '">' +
                (sec.email_otp_enabled ? 'Disable email OTP' : 'Enable email OTP') + '</button>' +
              '<button type="button" class="secondary-button" data-action="2fa-totp-begin">Set up authenticator</button>' +
              (sec.totp_enabled ? '<button type="button" class="danger-button" data-action="2fa-totp-disable">Disable TOTP</button>' : '') +
            '</div>' +
            (state.pendingTotpSecret
              ? '<form data-action="2fa-totp-confirm" class="form-grid compact" style="margin-top:12px">' +
                  '<p class="muted">Secret: <code>' + escapeHtml(state.pendingTotpSecret) + '</code></p>' +
                  '<label>Enter code from app<input class="form-control" name="code" required></label>' +
                  '<button class="primary-button" type="submit">Confirm TOTP</button></form>'
              : '') +
          '</article>' +
          '<article class="entry-panel"><div class="panel-title"><h2>Failed admin logins</h2></div>' +
            '<table class="table"><thead><tr><th>When</th><th>Email</th><th>Result</th><th>Detail</th><th>IP</th></tr></thead><tbody>' +
            (audit || '<tr><td colspan="5">No entries</td></tr>') + '</tbody></table></article>' +
        '</div>' +
        '<section class="table-panel"><div class="panel-title"><h2>Admin activity log</h2></div>' +
          '<table class="table"><thead><tr><th>When</th><th>Admin</th><th>Action</th><th>Detail</th><th>IP</th></tr></thead><tbody>' +
          (activity || '<tr><td colspan="5">No activity yet</td></tr>') + '</tbody></table></section>' +
      '</section>'
    );
  }

  function renderOps() {
    const h = state.health || {};
    const alerts = (state.failAlerts || []).map(function (p) {
      return '<tr><td>' + formatDateTime(p.created_at) + '</td><td>' + escapeHtml(p.shop_name || '') + '</td><td>' + formatInr(p.amount_inr) + '</td><td>' + purchaseStatusBadge(p.status) + '</td><td>' + escapeHtml(p.alert_reason || '') + '</td></tr>';
    }).join('');
    const mails = (state.mailOutbox || []).map(function (f) {
      return '<tr><td>' + escapeHtml(f.file) + '</td><td>' + formatDateTime(f.modified_at) + '</td><td>' + f.size + ' B</td>' +
        '<td><button type="button" class="ghost-button" data-action="read-mail" data-file="' + escapeHtml(f.file) + '">View</button></td></tr>';
    }).join('');
    return (
      '<section class="admin-view">' +
        '<div class="panel-title"><h2>Ops & health</h2></div>' +
        flash() +
        '<div class="admin-stat-grid">' +
          metric('Database', h.database && h.database.ok ? 'OK' : 'FAIL') +
          metric('Uploads writable', h.uploads && h.uploads.writable ? 'Yes' : 'No') +
          metric('Mail configured', h.mail && h.mail.configured ? 'Yes' : 'No') +
          metric('Outbox files', h.mail ? h.mail.outbox_files : '—') +
          metric('Razorpay', h.razorpay && h.razorpay.configured ? (h.razorpay.feature_enabled ? 'Ready' : 'Disabled') : 'Not set') +
        '</div>' +
        '<section class="table-panel"><div class="panel-title"><h2>Failed payment alerts</h2></div>' +
          '<table class="table"><thead><tr><th>When</th><th>Shop</th><th>Amount</th><th>Status</th><th>Reason</th></tr></thead><tbody>' +
          (alerts || '<tr><td colspan="5">No alerts</td></tr>') + '</tbody></table></section>' +
        '<section class="table-panel"><div class="panel-title"><h2>Mail outbox</h2></div>' +
          '<table class="table"><thead><tr><th>File</th><th>Modified</th><th>Size</th><th></th></tr></thead><tbody>' +
          (mails || '<tr><td colspan="4">Empty</td></tr>') + '</tbody></table>' +
          (state.mailPreview ? '<pre class="admin-mail-preview">' + escapeHtml(state.mailPreview) + '</pre>' : '') +
        '</section>' +
      '</section>'
    );
  }

  function renderView() {
    if (state.view === 'dashboard') return renderDashboard();
    if (state.view === 'tenants') return renderTenants();
    if (state.view === 'tenant-detail') return renderTenantDetail();
    if (state.view === 'purchases') return renderPurchases();
    if (state.view === 'promos') return renderPromos();
    if (state.view === 'admins') return renderAdmins();
    if (state.view === 'plans') return renderPlans();
    if (state.view === 'settings') return renderSettings();
    if (state.view === 'security') return renderSecurity();
    if (state.view === 'ops') return renderOps();
    return '';
  }

  function renderPortal() {
    return (
      '<main class="admin-portal-shell">' +
        '<section class="admin-portal-topbar">' +
          '<div class="brand"><img class="brand-mark brand-mark-img" src="assets/img/mark.png" alt=""><div>' +
            '<h1>TagForge Admin</h1><p>' + escapeHtml(state.adminUser.email) + '</p>' +
          '</div></div>' +
          '<div class="button-row">' +
            '<a class="btn btn-light" href="' + escapeHtml(shopUrl) + '"><i class="bi bi-tag"></i> Shop app</a>' +
            '<button type="button" data-action="logout"><i class="bi bi-box-arrow-right"></i> Sign out</button>' +
          '</div>' +
        '</section>' +
        renderNav() +
        renderView() +
        footer() +
      '</main>'
    );
  }

  function render() {
    root.innerHTML = state.adminUser ? renderPortal() : renderLogin();
  }

  function clearFlash() {
    state.message = '';
    state.viewError = '';
    state.planMessage = '';
  }

  function loadViewData() {
    clearFlash();
    if (state.view === 'dashboard') {
      return api.adminDashboard().then(function (data) {
        state.dashboard = data;
      });
    }
    if (state.view === 'tenants') {
      return api.adminTenants(state.tenantFilters).then(function (rows) {
        state.tenants = rows;
      });
    }
    if (state.view === 'tenant-detail' && state.detailTenantId) {
      return api.adminTenantDetail(state.detailTenantId).then(function (data) {
        state.tenantDetail = data;
        state.adjustment = Object.assign({}, blankAdjustment);
        state.offline = Object.assign({}, blankOffline);
      });
    }
    if (state.view === 'purchases') {
      return api.adminPurchases(state.purchaseFilters).then(function (rows) {
        state.purchases = rows;
      });
    }
    if (state.view === 'admins') {
      return api.adminUsers().then(function (rows) {
        state.admins = rows;
      });
    }
    if (state.view === 'plans') {
      return api.adminPlans().then(function (rows) {
        state.plans = rows;
      });
    }
    if (state.view === 'promos') {
      return api.adminPromos().then(function (rows) {
        state.promos = rows;
      });
    }
    if (state.view === 'settings') {
      return api.adminSettings().then(function (rows) {
        state.settings = rows;
      });
    }
    if (state.view === 'security') {
      return Promise.all([
        api.admin2faStatus(),
        api.adminActivity(),
        api.adminLoginAudit(true),
      ]).then(function (results) {
        state.security = results[0];
        state.activity = results[1];
        state.loginAudit = results[2];
      });
    }
    if (state.view === 'ops') {
      return Promise.all([
        api.adminHealth(),
        api.adminFailAlerts(),
        api.adminMailOutbox(),
      ]).then(function (results) {
        state.health = results[0];
        state.failAlerts = results[1];
        state.mailOutbox = results[2];
      });
    }
    return Promise.resolve();
  }

  function go(view) {
    state.view = view;
    return loadViewData().then(render).catch(function (err) {
      state.viewError = err.message || 'Failed to load';
      render();
    });
  }

  function openTenant(id) {
    state.detailTenantId = Number(id);
    state.view = 'tenant-detail';
    return loadViewData().then(render).catch(function (err) {
      state.viewError = err.message;
      render();
    });
  }

  function patchPlan(planId, field, value) {
    state.plans = state.plans.map(function (plan) {
      if (plan.id !== Number(planId)) return plan;
      const next = Object.assign({}, plan);
      next[field] = value;
      return next;
    });
  }

  function numericOrNull(data, name) {
    const value = String(data.get(name) || '');
    return value === '' ? null : Number(value);
  }

  root.addEventListener('click', function (event) {
    const target = event.target.closest('[data-action]');
    if (!target) return;
    const action = target.getAttribute('data-action');

    if (action === 'logout') {
      api.logout().finally(function () {
        state.adminUser = null;
        render();
      });
      return;
    }

    if (action === 'nav') {
      go(target.getAttribute('data-view'));
      return;
    }

    if (action === 'open-tenant') {
      openTenant(target.getAttribute('data-id'));
      return;
    }

    if (action === 'save-plan') {
      const plan = state.plans.filter(function (row) {
        return String(row.id) === target.getAttribute('data-id');
      })[0];
      if (!plan) return;
      api.updateAdminPlan(plan).then(function (updated) {
        state.plans = state.plans.map(function (row) {
          return row.id === updated.id ? updated : row;
        });
        state.planMessage = updated.name + ' saved';
        render();
      }).catch(function (err) {
        state.viewError = err.message;
        render();
      });
      return;
    }

    if (action === 'suspend-tenant') {
      const reason = window.prompt('Suspension reason (shown to the shop):', 'Unpaid balance / policy');
      if (reason === null) return;
      api.setTenantStatus(Number(target.getAttribute('data-id')), false, reason).then(function () {
        state.message = 'Shop suspended';
        return loadViewData();
      }).then(render).catch(function (err) {
        state.viewError = err.message;
        render();
      });
      return;
    }

    if (action === 'activate-tenant') {
      api.setTenantStatus(Number(target.getAttribute('data-id')), true, null).then(function () {
        state.message = 'Shop activated';
        return loadViewData();
      }).then(render).catch(function (err) {
        state.viewError = err.message;
        render();
      });
      return;
    }

    if (action === 'refund-purchase') {
      const note = window.prompt('Refund note (required):', 'Customer refund');
      if (!note) return;
      const revoke = window.confirm('Also revoke credits / unlimited granted by this purchase?');
      api.refundPurchase(Number(target.getAttribute('data-id')), note, revoke).then(function () {
        state.message = 'Purchase refunded';
        return loadViewData();
      }).then(render).catch(function (err) {
        state.viewError = err.message;
        render();
      });
      return;
    }

    if (action === 'reset-admin-password') {
      const password = window.prompt('New password for ' + (target.getAttribute('data-email') || 'admin') + ' (min 8 chars):');
      if (!password) return;
      api.resetAdminPassword({
        user_id: Number(target.getAttribute('data-id')),
        password: password,
      }).then(function () {
        state.message = 'Admin password updated';
        render();
      }).catch(function (err) {
        state.viewError = err.message;
        render();
      });
      return;
    }

    if (action === 'export-tenants') {
      api.exportTenantsCsv().then(function () {
        state.message = 'Tenant CSV downloaded';
        render();
      }).catch(function (err) {
        state.viewError = err.message;
        render();
      });
      return;
    }

    if (action === 'impersonate') {
      const mode = target.getAttribute('data-mode') || 'readonly';
      api.impersonateTenant(Number(target.getAttribute('data-id')), mode, mode === 'timed' ? 30 : 60).then(function (result) {
        state.message = (mode === 'readonly' ? 'Read-only' : 'Timed') + ' support link ready — opening shop UI';
        render();
        window.open(result.shop_url, '_blank', 'noopener');
      }).catch(function (err) {
        state.viewError = err.message;
        render();
      });
      return;
    }

    if (action === 'send-owner-reset') {
      api.sendOwnerResetLink(Number(target.getAttribute('data-id'))).then(function (result) {
        state.message = result.detail || 'Reset link emailed';
        if (result.reset_url) {
          state.message += ' (debug: ' + result.reset_url + ')';
        }
        render();
      }).catch(function (err) {
        state.viewError = err.message;
        render();
      });
      return;
    }

    if (action === 'delete-note') {
      if (!window.confirm('Delete this support note?')) return;
      api.deleteTenantNote(Number(target.getAttribute('data-id'))).then(function () {
        state.message = 'Note deleted';
        return loadViewData();
      }).then(render).catch(function (err) {
        state.viewError = err.message;
        render();
      });
      return;
    }

    if (action === 'open-receipt') {
      api.openPurchaseReceipt(Number(target.getAttribute('data-id')));
      return;
    }

    if (action === 'toggle-promo') {
      const promo = state.promos.filter(function (p) { return String(p.id) === target.getAttribute('data-id'); })[0];
      if (!promo) return;
      api.updateAdminPromo({
        id: promo.id,
        description: promo.description,
        tag_credits: promo.tag_credits,
        validity_days: promo.validity_days,
        is_unlimited: promo.is_unlimited,
        max_redemptions: promo.max_redemptions,
        is_active: target.getAttribute('data-active') === '1',
      }).then(function () {
        state.message = 'Promo updated';
        return loadViewData();
      }).then(render).catch(function (err) {
        state.viewError = err.message;
        render();
      });
      return;
    }

    if (action === 'read-mail') {
      api.adminMailOutboxRead(target.getAttribute('data-file')).then(function (result) {
        state.mailPreview = result.content || '';
        render();
      }).catch(function (err) {
        state.viewError = err.message;
        render();
      });
      return;
    }

    if (action === '2fa-email-toggle') {
      const enable = target.getAttribute('data-enable') === '1';
      api.admin2faSetup({ action: enable ? 'enable_email_otp' : 'disable_email_otp' }).then(function () {
        state.message = enable ? 'Email OTP enabled' : 'Email OTP disabled';
        return loadViewData();
      }).then(render).catch(function (err) {
        state.viewError = err.message;
        render();
      });
      return;
    }

    if (action === '2fa-totp-begin') {
      api.admin2faSetup({ action: 'begin_totp' }).then(function (result) {
        state.pendingTotpSecret = result.secret;
        state.pendingTotpUrl = result.otpauth_url;
        render();
      }).catch(function (err) {
        state.viewError = err.message;
        render();
      });
      return;
    }

    if (action === '2fa-totp-disable') {
      const code = window.prompt('Enter current authenticator code to disable TOTP:');
      if (!code) return;
      api.admin2faSetup({ action: 'disable_totp', code: code }).then(function () {
        state.message = 'TOTP disabled';
        state.pendingTotpSecret = '';
        return loadViewData();
      }).then(render).catch(function (err) {
        state.viewError = err.message;
        render();
      });
    }
  });

  root.addEventListener('input', function (event) {
    const planId = event.target.getAttribute('data-plan');
    const field = event.target.getAttribute('data-field');
    if (planId && field && event.target.type !== 'checkbox') {
      let value = event.target.value;
      if (['price_inr', 'tag_credits', 'validity_days', 'sort_order'].indexOf(field) >= 0) {
        value = Number(value);
      }
      patchPlan(planId, field, value);
    }
  });

  root.addEventListener('change', function (event) {
    const planId = event.target.getAttribute('data-plan');
    const field = event.target.getAttribute('data-field');
    if (planId && field && event.target.type === 'checkbox') {
      patchPlan(planId, field, event.target.checked);
    }
  });

  root.addEventListener('submit', function (event) {
    const form = event.target.closest('form');
    if (!form) return;
    event.preventDefault();
    const action = form.getAttribute('data-action');
    const data = new FormData(form);

    if (action === 'admin-login') {
      state.busy = true;
      state.error = '';
      render();
      api.login({
        email: String(data.get('email') || ''),
        password: String(data.get('password') || ''),
      }).then(function (auth) {
        if (auth && auth.requires_2fa) {
          state.busy = false;
          state.pending2fa = auth;
          render();
          return null;
        }
        if (String(auth.user.role).toLowerCase() !== 'admin') {
          return api.logout().then(function () {
            throw new Error('This account does not have admin access');
          });
        }
        state.pending2fa = null;
        state.adminUser = auth.user;
        state.view = 'dashboard';
        return loadViewData();
      }).then(function (loaded) {
        if (loaded === null) return;
        state.busy = false;
        render();
      }).catch(function (err) {
        state.busy = false;
        state.adminUser = null;
        state.error = err.message || 'Could not sign in';
        render();
      });
      return;
    }

    if (action === 'admin-2fa') {
      state.busy = true;
      state.error = '';
      render();
      api.verify2fa({
        code: String(data.get('code') || ''),
        method: String(data.get('method') || ''),
      }).then(function (auth) {
        if (String(auth.user.role).toLowerCase() !== 'admin') {
          throw new Error('This account does not have admin access');
        }
        state.pending2fa = null;
        state.adminUser = auth.user;
        state.view = 'dashboard';
        return loadViewData();
      }).then(function () {
        state.busy = false;
        render();
      }).catch(function (err) {
        state.busy = false;
        state.error = err.message || 'Verification failed';
        render();
      });
      return;
    }

    if (action === 'filter-tenants') {
      state.tenantFilters = {
        q: String(data.get('q') || ''),
        status: String(data.get('status') || 'all'),
        credits: String(data.get('credits') || 'all'),
      };
      go('tenants');
      return;
    }

    if (action === 'filter-purchases') {
      state.purchaseFilters = {
        q: String(data.get('q') || ''),
        status: String(data.get('status') || 'all'),
      };
      go('purchases');
      return;
    }

    if (action === 'adjust-credits' && state.detailTenantId) {
      const payload = {
        credits_delta: Number(data.get('credits_delta') || 0),
        set_credit_balance: numericOrNull(data, 'set_credit_balance'),
        credits_validity_days: numericOrNull(data, 'credits_validity_days'),
        unlimited_validity_days: numericOrNull(data, 'unlimited_validity_days'),
        payment_reference: String(data.get('payment_reference') || '') || null,
        note: String(data.get('note') || ''),
      };
      api.adjustTenantCredits(state.detailTenantId, payload).then(function () {
        state.message = 'Credits updated';
        return loadViewData();
      }).then(render).catch(function (err) {
        state.viewError = err.message;
        render();
      });
      return;
    }

    if (action === 'offline-payment' && state.detailTenantId) {
      const payload = {
        shop_id: state.detailTenantId,
        payment_method: String(data.get('payment_method') || 'cash'),
        amount_inr: Number(data.get('amount_inr') || 0),
        tag_credits: Number(data.get('tag_credits') || 0),
        unlimited_days: Number(data.get('unlimited_days') || 0),
        receipt_note: String(data.get('receipt_note') || '') || null,
        note: String(data.get('note') || ''),
      };
      api.recordOfflinePayment(payload).then(function () {
        state.message = 'Offline payment recorded and credits granted';
        state.offline = Object.assign({}, blankOffline);
        return loadViewData();
      }).then(render).catch(function (err) {
        state.viewError = err.message;
        render();
      });
      return;
    }

    if (action === 'create-admin') {
      const payload = {
        name: String(data.get('name') || ''),
        email: String(data.get('email') || ''),
        password: String(data.get('password') || ''),
      };
      api.createAdminUser(payload).then(function () {
        state.message = 'Admin user created';
        state.adminForm = { name: '', email: '', password: '' };
        return loadViewData();
      }).then(render).catch(function (err) {
        state.viewError = err.message;
        render();
      });
      return;
    }

    if (action === 'reset-owner-password') {
      api.resetAdminPassword({
        user_id: Number(data.get('user_id')),
        password: String(data.get('password') || ''),
        allow_shop_user: true,
      }).then(function () {
        state.message = 'Owner password updated';
        render();
      }).catch(function (err) {
        state.viewError = err.message;
        render();
      });
      return;
    }

    if (action === 'edit-profile' && state.detailTenantId) {
      api.updateTenantProfile({
        shop_id: state.detailTenantId,
        name: String(data.get('name') || ''),
        short_name: String(data.get('short_name') || ''),
        phone_number: String(data.get('phone_number') || ''),
        gst_no: String(data.get('gst_no') || ''),
        tag_prefix: String(data.get('tag_prefix') || ''),
        address: String(data.get('address') || ''),
      }).then(function () {
        state.message = 'Shop profile updated';
        return loadViewData();
      }).then(render).catch(function (err) {
        state.viewError = err.message;
        render();
      });
      return;
    }

    if (action === 'add-note' && state.detailTenantId) {
      api.addTenantNote(state.detailTenantId, String(data.get('body') || '')).then(function () {
        state.message = 'Support note added';
        return loadViewData();
      }).then(render).catch(function (err) {
        state.viewError = err.message;
        render();
      });
      return;
    }

    if (action === 'create-plan') {
      api.createAdminPlan({
        code: String(data.get('code') || ''),
        name: String(data.get('name') || ''),
        description: String(data.get('description') || ''),
        price_inr: Number(data.get('price_inr') || 0),
        tag_credits: Number(data.get('tag_credits') || 0),
        validity_days: Number(data.get('validity_days') || 30),
        sort_order: Number(data.get('sort_order') || 10),
        is_unlimited: !!data.get('is_unlimited'),
        is_active: true,
      }).then(function () {
        state.planMessage = 'Plan created';
        state.newPlan = {
          code: '', name: '', description: '', price_inr: 599, tag_credits: 0,
          validity_days: 30, is_unlimited: true, is_active: true, sort_order: 10,
        };
        return loadViewData();
      }).then(render).catch(function (err) {
        state.viewError = err.message;
        render();
      });
      return;
    }

    if (action === 'create-promo') {
      api.createAdminPromo({
        code: String(data.get('code') || ''),
        description: String(data.get('description') || ''),
        tag_credits: Number(data.get('tag_credits') || 0),
        validity_days: Number(data.get('validity_days') || 7),
        max_redemptions: Number(data.get('max_redemptions') || 0),
        is_unlimited: !!data.get('is_unlimited'),
      }).then(function () {
        state.message = 'Promo created';
        state.newPromo = { code: '', description: '', tag_credits: 50, validity_days: 7, is_unlimited: false, max_redemptions: 0 };
        return loadViewData();
      }).then(render).catch(function (err) {
        state.viewError = err.message;
        render();
      });
      return;
    }

    if (action === 'save-settings') {
      const payload = {};
      ['free_registration_credits', 'free_registration_validity_days', 'default_tag_width_mm', 'default_tag_height_mm',
        'default_font_size_pt', 'announcement_message', 'invoice_legal_name', 'invoice_gstin', 'invoice_address',
        'invoice_email', 'invoice_state', 'invoice_state_code'].forEach(function (key) {
        payload[key] = String(data.get(key) || '');
      });
      payload.announcement_enabled = data.get('announcement_enabled') ? '1' : '0';
      payload.feature_razorpay = data.get('feature_razorpay') ? '1' : '0';
      payload.feature_registration = data.get('feature_registration') ? '1' : '0';
      payload.feature_reprints = data.get('feature_reprints') ? '1' : '0';
      api.saveAdminSettings(payload).then(function (settings) {
        state.settings = settings;
        state.message = 'Settings saved';
        render();
      }).catch(function (err) {
        state.viewError = err.message;
        render();
      });
      return;
    }

    if (action === '2fa-totp-confirm') {
      api.admin2faSetup({ action: 'confirm_totp', code: String(data.get('code') || '') }).then(function () {
        state.message = 'Authenticator enabled';
        state.pendingTotpSecret = '';
        return loadViewData();
      }).then(render).catch(function (err) {
        state.viewError = err.message;
        render();
      });
    }
  });

  api.me().then(function (auth) {
    if (String(auth.user.role).toLowerCase() !== 'admin') {
      throw new Error('not admin');
    }
    state.adminUser = auth.user;
    state.view = 'dashboard';
    return loadViewData();
  }).catch(function () {
    state.adminUser = null;
  }).then(render);
})();
