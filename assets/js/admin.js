(function () {
  const blankAdjustment = {
    credits_delta: 0,
    set_credit_balance: '',
    credits_validity_days: '',
    unlimited_validity_days: '',
    payment_reference: '',
    note: 'Admin credit adjustment',
  };

  const state = {
    adminUser: null,
    error: '',
    busy: false,
    plans: [],
    planMessage: '',
    tenants: [],
    selectedTenantId: null,
    ledger: [],
    adjustment: Object.assign({}, blankAdjustment),
    tenantMessage: '',
    tenantError: '',
  };

  const root = document.getElementById('app');

  function formatDate(value) {
    if (!value) return 'Not set';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return 'Not set';
    return new Intl.DateTimeFormat('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }).format(date);
  }

  function footer() {
    return '<footer class="app-footer">Migids Software LLP, Bengaluru</footer>';
  }

  function renderLogin() {
    return (
      '<main class="auth-layout admin-auth-layout">' +
        '<section class="auth-panel">' +
          '<div class="auth-heading">' +
            '<span class="brand-mark">JT</span>' +
            '<div><h1>Admin Portal</h1><p>Owner access for billing plans and platform settings.</p></div>' +
          '</div>' +
          '<form data-action="admin-login" class="form-grid">' +
            '<label>Admin email<input class="form-control" name="email" type="email" required></label>' +
            '<label>Password<input class="form-control" name="password" type="password" minlength="8" required></label>' +
            (state.error ? '<div class="error-banner">' + escapeHtml(state.error) + '</div>' : '') +
            '<button class="primary-button" ' + (state.busy ? 'disabled' : '') + '><i class="bi bi-shield-lock"></i> Sign in to admin</button>' +
          '</form>' +
        '</section>' +
        footer() +
      '</main>'
    );
  }

  function renderPlans() {
    const cards = state.plans.map(function (plan) {
      return '<article class="entry-panel admin-plan">' +
        '<div class="panel-title"><h2>' + escapeHtml(plan.code) + '</h2>' +
          '<label class="check-row"><input type="checkbox" data-plan="' + plan.id + '" data-field="is_active"' + (plan.is_active ? ' checked' : '') + '> Active</label>' +
        '</div>' +
        '<div class="form-grid compact">' +
          '<label>Name<input class="form-control" data-plan="' + plan.id + '" data-field="name" value="' + escapeHtml(plan.name) + '"></label>' +
          '<label>Price INR<input class="form-control" type="number" data-plan="' + plan.id + '" data-field="price_inr" value="' + plan.price_inr + '"></label>' +
          '<label>Tag credits<input class="form-control" type="number" data-plan="' + plan.id + '" data-field="tag_credits" value="' + plan.tag_credits + '"></label>' +
          '<label>Validity days<input class="form-control" type="number" data-plan="' + plan.id + '" data-field="validity_days" value="' + plan.validity_days + '"></label>' +
          '<label>Sort order<input class="form-control" type="number" data-plan="' + plan.id + '" data-field="sort_order" value="' + plan.sort_order + '"></label>' +
          '<label class="check-row"><input type="checkbox" data-plan="' + plan.id + '" data-field="is_unlimited"' + (plan.is_unlimited ? ' checked' : '') + '> Unlimited plan</label>' +
          '<label class="wide-field">Description<input class="form-control" data-plan="' + plan.id + '" data-field="description" value="' + escapeHtml(plan.description) + '"></label>' +
        '</div>' +
        '<div class="button-row settings-actions">' +
          '<button class="primary-button" type="button" data-action="save-plan" data-id="' + plan.id + '"><i class="bi bi-floppy"></i> Save plan</button>' +
        '</div>' +
      '</article>';
    }).join('');
    return '<section class="admin-plan-list">' +
      (state.planMessage ? '<div class="success-banner">' + escapeHtml(state.planMessage) + '</div>' : '') +
      cards +
    '</section>';
  }

  function selectedTenant() {
    return state.tenants.filter(function (tenant) { return tenant.id === state.selectedTenantId; })[0] || null;
  }

  function renderTenants() {
    const tenant = selectedTenant();
    const rows = state.tenants.map(function (row) {
      return '<tr class="' + (row.id === state.selectedTenantId ? 'selected-row' : '') + '" data-action="select-tenant" data-id="' + row.id + '">' +
        '<td>' + escapeHtml(row.name) + '</td>' +
        '<td>' + escapeHtml(row.owner_email || '-') + '</td>' +
        '<td>' + row.tag_credit_balance + '</td>' +
        '<td>' + (row.is_unlimited_active ? formatDate(row.unlimited_until) : '-') + '</td>' +
      '</tr>';
    }).join('');
    const ledger = state.ledger.map(function (entry) {
      return '<tr><td>' + escapeHtml(formatDate(entry.created_at)) + '</td><td>' + escapeHtml(entry.entry_type) + '</td><td>' + entry.credits + '</td><td>' + entry.balance_after + '</td><td>' + escapeHtml(entry.description) + '</td></tr>';
    }).join('');
    const adj = state.adjustment;
    return (
      '<section class="admin-tenant-section">' +
        '<div class="panel-title"><h2>Tenant Credits</h2><span>' + state.tenants.length + ' tenants</span></div>' +
        (state.tenantMessage ? '<div class="success-banner">' + escapeHtml(state.tenantMessage) + '</div>' : '') +
        (state.tenantError ? '<div class="error-banner">' + escapeHtml(state.tenantError) + '</div>' : '') +
        '<div class="admin-tenant-grid">' +
          '<section class="table-panel"><table class="table"><thead><tr><th>Tenant</th><th>Owner</th><th>Credits</th><th>Unlimited</th></tr></thead><tbody>' + rows + '</tbody></table></section>' +
          '<section class="entry-panel">' +
            '<div class="panel-title"><h2>' + escapeHtml(tenant ? tenant.name : 'Select tenant') + '</h2>' +
              (tenant ? '<span>' + escapeHtml(tenant.short_name) + '</span>' : '') +
            '</div>' +
            (tenant ? (
              '<div class="billing-summary tenant-summary">' +
                '<div><span>Credits</span><strong>' + tenant.tag_credit_balance + '</strong></div>' +
                '<div><span>Credit validity</span><strong>' + formatDate(tenant.credits_expire_at) + '</strong></div>' +
                '<div><span>Unlimited</span><strong>' + (tenant.is_unlimited_active ? formatDate(tenant.unlimited_until) : 'No') + '</strong></div>' +
              '</div>' +
              '<form data-action="adjust-credits" class="form-grid compact">' +
                '<label>Add/remove credits<input class="form-control" type="number" name="credits_delta" value="' + escapeHtml(adj.credits_delta) + '"></label>' +
                '<label>Set balance<input class="form-control" type="number" name="set_credit_balance" value="' + escapeHtml(adj.set_credit_balance) + '" placeholder="Leave blank"></label>' +
                '<label>Credit validity days<input class="form-control" type="number" name="credits_validity_days" value="' + escapeHtml(adj.credits_validity_days) + '" placeholder="Leave unchanged"></label>' +
                '<label>Unlimited days<input class="form-control" type="number" name="unlimited_validity_days" value="' + escapeHtml(adj.unlimited_validity_days) + '" placeholder="0 clears Pro"></label>' +
                '<label class="wide-field">Payment reference<input class="form-control" name="payment_reference" value="' + escapeHtml(adj.payment_reference) + '" placeholder="Razorpay payment id, cash receipt, manual note"></label>' +
                '<label class="wide-field">Note<input class="form-control" name="note" value="' + escapeHtml(adj.note) + '" required></label>' +
                '<div class="button-row settings-actions full-row">' +
                  '<button class="primary-button" type="submit"><i class="bi bi-floppy"></i> Update tenant credits</button>' +
                '</div>' +
              '</form>'
            ) : '') +
          '</section>' +
        '</div>' +
        '<section class="table-panel"><div class="panel-title"><h2>Tenant Ledger</h2><span>' + state.ledger.length + ' entries</span></div>' +
          '<table class="table"><thead><tr><th>Date</th><th>Type</th><th>Credits</th><th>Balance</th><th>Description</th></tr></thead><tbody>' + ledger + '</tbody></table>' +
        '</section>' +
      '</section>'
    );
  }

  function renderPortal() {
    return (
      '<main class="admin-portal-shell">' +
        '<section class="admin-portal-topbar">' +
          '<div class="brand"><span class="brand-mark">JT</span><div><h1>Admin Portal</h1><p>' + escapeHtml(state.adminUser.email) + '</p></div></div>' +
          '<div class="button-row">' +
            '<a class="btn btn-light" href="index.php"><i class="bi bi-tag"></i> Shop app</a>' +
            '<button type="button" data-action="logout"><i class="bi bi-box-arrow-right"></i> Sign out</button>' +
          '</div>' +
        '</section>' +
        renderPlans() +
        renderTenants() +
        footer() +
      '</main>'
    );
  }

  function render() {
    root.innerHTML = state.adminUser ? renderPortal() : renderLogin();
  }

  function loadAdminData() {
    return Promise.all([api.adminPlans(), api.adminTenants()]).then(function (results) {
      state.plans = results[0];
      state.tenants = results[1];
      if (state.selectedTenantId == null && state.tenants[0]) {
        state.selectedTenantId = state.tenants[0].id;
      }
      if (state.selectedTenantId) {
        return api.adminTenantLedger(state.selectedTenantId).then(function (ledger) {
          state.ledger = ledger;
        });
      }
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

  root.addEventListener('click', function (event) {
    const target = event.target.closest('[data-action]');
    if (!target) return;
    const action = target.getAttribute('data-action');
    if (action === 'logout') {
      api.logout().finally(function () {
        state.adminUser = null;
        render();
      });
    }
    if (action === 'select-tenant') {
      state.selectedTenantId = Number(target.getAttribute('data-id'));
      state.adjustment = Object.assign({}, blankAdjustment);
      api.adminTenantLedger(state.selectedTenantId).then(function (ledger) {
        state.ledger = ledger;
        render();
      }).catch(function (err) {
        state.tenantError = err.message;
        render();
      });
    }
    if (action === 'save-plan') {
      const plan = state.plans.filter(function (row) { return String(row.id) === target.getAttribute('data-id'); })[0];
      if (!plan) return;
      api.updateAdminPlan(plan).then(function (updated) {
        state.plans = state.plans.map(function (row) { return row.id === updated.id ? updated : row; });
        state.planMessage = updated.name + ' saved';
        render();
      }).catch(function (err) {
        state.error = err.message;
        render();
      });
    }
  });

  root.addEventListener('input', function (event) {
    const planId = event.target.getAttribute('data-plan');
    const field = event.target.getAttribute('data-field');
    if (planId && field) {
      let value = event.target.type === 'checkbox' ? event.target.checked : event.target.value;
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
    if (form.getAttribute('data-action') === 'admin-login') {
      const data = new FormData(form);
      state.busy = true;
      state.error = '';
      render();
      api.login({
        email: String(data.get('email') || ''),
        password: String(data.get('password') || ''),
      }).then(function (auth) {
        if (String(auth.user.role).toLowerCase() !== 'admin') {
          return api.logout().then(function () {
            throw new Error('This account does not have admin access');
          });
        }
        state.adminUser = auth.user;
        return loadAdminData();
      }).then(function () {
        state.busy = false;
        render();
      }).catch(function (err) {
        state.busy = false;
        state.adminUser = null;
        state.error = err.message || 'Could not sign in';
        render();
      });
    }
    if (form.getAttribute('data-action') === 'adjust-credits' && state.selectedTenantId) {
      const data = new FormData(form);
      function numericOrNull(name) {
        const value = String(data.get(name) || '');
        return value === '' ? null : Number(value);
      }
      const payload = {
        credits_delta: Number(data.get('credits_delta') || 0),
        set_credit_balance: numericOrNull('set_credit_balance'),
        credits_validity_days: numericOrNull('credits_validity_days'),
        unlimited_validity_days: numericOrNull('unlimited_validity_days'),
        payment_reference: String(data.get('payment_reference') || '') || null,
        note: String(data.get('note') || ''),
      };
      api.adjustTenantCredits(state.selectedTenantId, payload).then(function (updated) {
        state.tenants = state.tenants.map(function (tenant) { return tenant.id === updated.id ? updated : tenant; });
        state.adjustment = Object.assign({}, blankAdjustment);
        state.tenantMessage = updated.name + ' updated';
        state.tenantError = '';
        return api.adminTenantLedger(updated.id);
      }).then(function (ledger) {
        state.ledger = ledger;
        render();
      }).catch(function (err) {
        state.tenantError = err.message;
        render();
      });
    }
  });

  api.me().then(function (auth) {
    if (String(auth.user.role).toLowerCase() !== 'admin') {
      throw new Error('not admin');
    }
    state.adminUser = auth.user;
    return loadAdminData();
  }).catch(function () {
    state.adminUser = null;
  }).then(render);
})();
