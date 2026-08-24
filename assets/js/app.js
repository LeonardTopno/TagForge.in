(function () {
  const blankTag = {
    item_name: 'Ring',
    category: 'Gold',
    purity: '',
    pieces: 1,
    gross_weight: '4.080',
    stone_weight: '0.000',
    other_deduction: '0.000',
    copies: 1,
  };

  const state = {
    user: null,
    shop: null,
    view: 'create',
    settingsSection: 'shop',
    stats: null,
    tags: [],
    plans: [],
    ledger: [],
    activeTag: null,
    form: Object.assign({}, blankTag),
    itemCatalog: [],
    authMode: 'register',
    error: '',
    success: '',
    busy: false,
    navOpen: false,
    billingMessage: '',
    settingsMessage: '',
    itemError: '',
    logoError: '',
    newItemName: '',
    editingId: null,
    editingName: '',
  };

  const root = document.getElementById('app');

  function formatWeight(value) {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed.toFixed(3) : '0.000';
  }

  function tagItemLabel(itemName, category) {
    const item = String(itemName || '').trim();
    const metal = String(category || '').trim();
    if (!metal) return item;
    return (metal + ' ' + item).replace(/\s+/g, ' ').trim();
  }

  function calculateNet(form) {
    return formatWeight(Number(form.gross_weight || 0) - Number(form.stone_weight || 0));
  }

  function nextPreviewNumber(shop) {
    if (!shop) return 'T000001';
    return shop.tag_prefix + String(shop.next_tag_number).padStart(6, '0');
  }

  function formatDate(value) {
    if (!value) return 'Not set';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return 'Not set';
    return new Intl.DateTimeFormat('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }).format(date);
  }

  function formatDateTime(value) {
    if (!value) return 'Not set';
    const date = new Date(value.indexOf('T') >= 0 ? value : value.replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return 'Not set';
    return new Intl.DateTimeFormat('en-IN', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hour12: true,
    }).format(date);
  }

  function companyFooter() {
    return '<footer class="app-footer">Migids Software LLP, Bengaluru</footer>';
  }

  function draftTag() {
    const shop = state.shop;
    const form = state.form;
    const active = state.activeTag;
    return {
      id: active ? active.id : 0,
      shop_id: shop ? shop.id : 0,
      tag_number: active ? active.tag_number : nextPreviewNumber(shop),
      item_name: active ? active.item_name : form.item_name,
      category: active ? active.category : form.category,
      purity: active ? active.purity : form.purity,
      pieces: active ? active.pieces : form.pieces,
      gross_weight: active ? active.gross_weight : formatWeight(form.gross_weight),
      stone_weight: active ? active.stone_weight : formatWeight(form.stone_weight),
      other_deduction: active ? active.other_deduction : formatWeight(form.other_deduction),
      net_weight: active ? active.net_weight : calculateNet(form),
      copies: active ? active.copies : form.copies,
      status: 'active',
      print_count: active ? active.print_count : 0,
      created_at: new Date().toISOString(),
    };
  }

  function printableTagHtml(tag, shop) {
    const style = [
      'width:' + shop.tag_width_mm + 'mm',
      'height:' + shop.tag_height_mm + 'mm',
      'font-size:' + shop.font_size_pt + 'pt',
      'transform:translate(' + shop.horizontal_offset_mm + 'mm,' + shop.vertical_offset_mm + 'mm)',
    ].join(';');
    return (
      '<article class="print-tag" style="' + style + '">' +
        '<div class="tag-printable-face">' +
          '<div class="tag-panel tag-panel-left" aria-label="Left panel: weights">' +
            '<div class="tag-weight-grid">' +
              '<span>Grs.Wt</span><span>:</span><strong>' + formatWeight(tag.gross_weight) + '</strong>' +
              '<span>Stn.Wt</span><span>:</span><strong>' + formatWeight(tag.stone_weight) + '</strong>' +
              '<span>Nt.Wt</span><span>:</span><strong>' + formatWeight(tag.net_weight) + '</strong>' +
            '</div>' +
          '</div>' +
          '<div class="tag-fold-mark" aria-hidden="true" title="Fold line"></div>' +
          '<div class="tag-panel tag-panel-right" aria-label="Right panel: item, barcode, tag number">' +
            '<div class="tag-back">' +
              '<span class="tag-back-item">' + escapeHtml(tagItemLabel(tag.item_name, tag.category).toUpperCase()) + '</span>' +
              barcodeSvg(tag.tag_number) +
              '<span class="tag-back-number">' + escapeHtml(tag.tag_number) + '</span>' +
            '</div>' +
          '</div>' +
        '</div>' +
        '<div class="tag-neck" aria-hidden="true"></div>' +
        '<div class="tag-tail" aria-hidden="true"></div>' +
      '</article>'
    );
  }

  function acceptAuth(auth) {
    state.user = auth.user;
    state.shop = auth.shop;
  }

  function refreshData() {
    if (!state.user) return Promise.resolve();
    return Promise.all([
      api.dashboard(),
      api.listTags(),
      api.settings(),
      api.billingPlans(),
      api.billingLedger(),
      api.listShopItems(),
    ]).then(function (results) {
      state.stats = results[0];
      state.tags = results[1];
      state.shop = results[2];
      state.plans = results[3];
      state.ledger = results[4];
      state.itemCatalog = results[5];
      if (state.itemCatalog.length && !state.itemCatalog.some(function (item) { return item.name === state.form.item_name; })) {
        state.form.item_name = state.itemCatalog[0].name;
      }
    });
  }

  function setBusy(value) {
    state.busy = value;
    render();
  }

  function renderAuth() {
    const registerFields = state.authMode === 'register'
      ? '<label>Shop name<input class="form-control" name="shop_name" value="Bhaskaran Jewellers" required></label>' +
        '<label>Tag short name<input class="form-control" name="shop_short_name" value="BHJ" required></label>' +
        '<label>Owner name<input class="form-control" name="owner_name" value="Owner" required></label>'
      : '';
    return (
      '<main class="auth-layout">' +
        '<section class="auth-panel">' +
          '<div class="auth-heading">' +
            '<span class="brand-mark">JT</span>' +
            '<div><h1>Jewellery Tag Printer</h1><p>Shop accounts, weight entry, preview and browser printing.</p></div>' +
          '</div>' +
          '<form data-action="auth" class="form-grid">' +
            registerFields +
            '<label>Email<input class="form-control" name="email" type="email" value="owner@example.com" required></label>' +
            '<label>Password<input class="form-control" name="password" type="password" value="password123" minlength="8" required></label>' +
            (state.error ? '<div class="error-banner">' + escapeHtml(state.error) + '</div>' : '') +
            '<button class="primary-button" ' + (state.busy ? 'disabled' : '') + '><i class="bi bi-person-plus"></i> ' +
              (state.authMode === 'register' ? 'Create shop account' : 'Sign in') +
            '</button>' +
          '</form>' +
          '<button class="link-button" type="button" data-action="toggle-auth">' +
            (state.authMode === 'register' ? 'Use an existing account' : 'Create a new shop account') +
          '</button>' +
        '</section>' +
        companyFooter() +
      '</main>'
    );
  }

  function navButton(view, icon, label) {
    return '<button type="button" class="' + (state.view === view ? 'active' : '') + '" data-action="go" data-view="' + view + '">' +
      '<i class="bi ' + icon + '"></i> ' + label + '</button>';
  }

  function viewTitle() {
    if (state.view === 'create') return 'Create Tag';
    if (state.view === 'history') return 'Tag History';
    if (state.view === 'billing') return 'Credits';
    if (state.settingsSection === 'shop') return 'Shop Settings';
    if (state.settingsSection === 'tag') return 'Tag Settings';
    return 'Item Settings';
  }

  function renderCreate() {
    const tag = draftTag();
    const shop = state.shop;
    const options = state.itemCatalog.map(function (item) {
      return '<option value="' + escapeHtml(item.name) + '"' + (state.form.item_name === item.name ? ' selected' : '') + '>' + escapeHtml(item.name) + '</option>';
    }).join('');
    return (
      '<section class="work-grid">' +
        '<div class="entry-panel">' +
          '<div class="panel-title"><h2>Weight Entry</h2><span>' + escapeHtml(tag.tag_number) + '</span></div>' +
          '<div class="form-grid compact">' +
            '<label>Item<select class="form-select" data-field="item_name">' + options + '</select></label>' +
            '<div class="choice-field"><span>Category</span><div class="radio-row">' +
              ['Gold', 'Silver'].map(function (option) {
                return '<label class="radio-option' + (state.form.category === option ? ' is-selected' : '') + '">' +
                  '<input type="radio" name="category" value="' + option + '"' + (state.form.category === option ? ' checked' : '') + '> ' + option +
                '</label>';
              }).join('') +
            '</div></div>' +
            '<label>Gross weight<input class="form-control" data-field="gross_weight" step="0.001" type="number" value="' + escapeHtml(state.form.gross_weight) + '"></label>' +
            '<label>Stone weight<input class="form-control" data-field="stone_weight" step="0.001" type="number" value="' + escapeHtml(state.form.stone_weight) + '"></label>' +
          '</div>' +
          '<div class="net-box"><span>Net weight</span><strong>' + calculateNet(state.form) + ' g</strong></div>' +
          '<div class="button-row">' +
            '<button type="button" data-action="save" ' + (state.busy ? 'disabled' : '') + '><i class="bi bi-floppy"></i> Save</button>' +
            '<button type="button" class="primary-button" data-action="save-print" ' + (state.busy ? 'disabled' : '') + '><i class="bi bi-printer"></i> Save &amp; Print</button>' +
            '<button type="button" data-action="test-print" ' + (state.busy ? 'disabled' : '') + '><i class="bi bi-printer"></i> Test Print</button>' +
          '</div>' +
        '</div>' +
        '<div class="preview-panel">' +
          '<div class="panel-title"><h2>Actual Preview</h2><span>' + shop.tag_width_mm + ' x ' + shop.tag_height_mm + ' mm · single side, fold at centre</span></div>' +
          '<div class="tag-stage print-bundle">' + printableTagHtml(tag, shop) + '</div>' +
        '</div>' +
      '</section>'
    );
  }

  function renderHistory() {
    const rows = state.tags.map(function (tag) {
      return '<tr>' +
        '<td>' + escapeHtml(tag.tag_number) + '</td>' +
        '<td>' + escapeHtml(tagItemLabel(tag.item_name, tag.category)) + '</td>' +
        '<td>' + escapeHtml(formatDateTime(tag.created_at)) + '</td>' +
        '<td>' + formatWeight(tag.gross_weight) + '</td>' +
        '<td>' + formatWeight(tag.net_weight) + '</td>' +
        '<td>' + tag.print_count + '</td>' +
        '<td><button type="button" data-action="reprint" data-id="' + tag.id + '"><i class="bi bi-printer"></i> Reprint</button></td>' +
      '</tr>';
    }).join('');
    const hiddenPrint = state.activeTag
      ? '<div class="print-bundle print-offscreen">' + printableTagHtml(state.activeTag, state.shop) + '</div>'
      : '';
    return '<section class="table-panel"><table class="table"><thead><tr><th>Tag</th><th>Item</th><th>Created at</th><th>Gross</th><th>Net</th><th>Prints</th><th></th></tr></thead><tbody>' +
      rows + '</tbody></table></section>' + hiddenPrint;
  }

  function renderBilling() {
    const billing = state.stats && state.stats.billing ? state.stats.billing : null;
    const plans = state.plans.map(function (plan) {
      return '<article class="plan-card">' +
        '<div class="plan-heading"><h2>' + escapeHtml(plan.name) + '</h2>' + (plan.is_unlimited ? '<i class="bi bi-crown"></i>' : '') + '</div>' +
        '<p>' + escapeHtml(plan.description) + '</p>' +
        '<div class="plan-price">Rs. ' + Number(plan.price_inr).toLocaleString('en-IN') + '</div>' +
        '<dl><div><dt>Tags</dt><dd>' + (plan.is_unlimited ? 'Unlimited' : Number(plan.tag_credits).toLocaleString('en-IN')) + '</dd></div>' +
        '<div><dt>Validity</dt><dd>' + plan.validity_days + ' days</dd></div></dl>' +
        '<button class="primary-button" type="button" data-action="buy-plan" data-id="' + plan.id + '"><i class="bi bi-currency-rupee"></i> Buy with Razorpay</button>' +
      '</article>';
    }).join('');
    const ledger = state.ledger.map(function (entry) {
      return '<tr><td>' + escapeHtml(formatDate(entry.created_at)) + '</td><td>' + escapeHtml(entry.entry_type) + '</td><td>' + entry.credits + '</td><td>' + entry.balance_after + '</td><td>' + escapeHtml(entry.description) + '</td></tr>';
    }).join('');
    return (
      '<section class="billing-layout">' +
        '<div class="billing-summary">' +
          '<div><span>Available credits</span><strong>' + (billing && billing.is_unlimited_active ? 'Unlimited' : (billing ? billing.tag_credit_balance : 0)) + '</strong></div>' +
          '<div><span>Credit validity</span><strong>' + formatDate(billing && billing.credits_expire_at) + '</strong></div>' +
          '<div><span>Pro valid until</span><strong>' + formatDate(billing && billing.unlimited_until) + '</strong></div>' +
          '<div><span>Rate</span><strong>Rs. ' + (billing ? billing.tag_price_inr : 2) + '/tag</strong></div>' +
        '</div>' +
        (state.billingMessage ? '<div class="success-banner">' + escapeHtml(state.billingMessage) + '</div>' : '') +
        '<div class="plan-grid">' + plans + '</div>' +
        '<section class="table-panel"><div class="panel-title"><h2>Credit Ledger</h2><span>' + state.ledger.length + ' entries</span></div>' +
          '<table class="table"><thead><tr><th>Date</th><th>Type</th><th>Credits</th><th>Balance</th><th>Description</th></tr></thead><tbody>' + ledger + '</tbody></table>' +
        '</section>' +
      '</section>'
    );
  }

  function renderSettings() {
    const shop = state.shop;
    const draft = shop;
    if (state.settingsSection === 'shop') {
      return (
        '<section class="settings-stack"><div class="entry-panel settings-panel">' +
          '<div class="logo-section"><div class="panel-title"><h2>Shop logo</h2><span>Shown in the app for this shop</span></div>' +
            '<div class="logo-row">' +
              (shop.logo_url ? '<img class="logo-preview" src="' + escapeHtml(shop.logo_url) + '" alt="">' : '<div class="logo-preview placeholder">No logo</div>') +
              '<div class="logo-actions">' +
                '<input id="logo-input" accept="image/jpeg,image/png,image/webp,image/gif" type="file" hidden>' +
                '<button type="button" data-action="pick-logo"><i class="bi bi-image"></i> ' + (shop.logo_url ? 'Replace logo' : 'Add logo') + '</button>' +
                (shop.logo_url ? '<button type="button" data-action="remove-logo"><i class="bi bi-trash"></i> Remove</button>' : '') +
              '</div>' +
            '</div>' +
            (state.logoError ? '<div class="error-banner">' + escapeHtml(state.logoError) + '</div>' : '') +
          '</div>' +
          '<form data-action="save-shop" class="form-grid compact">' +
            '<label class="full-row">Shop name<input class="form-control" name="name" value="' + escapeHtml(draft.name) + '" required></label>' +
            '<label class="full-row">Address<textarea class="form-control" name="address" rows="3">' + escapeHtml(draft.address || '') + '</textarea></label>' +
            '<label>Phone number<input class="form-control" name="phone_number" value="' + escapeHtml(draft.phone_number || '') + '"></label>' +
            '<label>GST No<input class="form-control" name="gst_no" value="' + escapeHtml(draft.gst_no || '') + '"></label>' +
            '<div class="button-row settings-actions full-row">' +
              '<button class="primary-button" type="submit"><i class="bi bi-floppy"></i> Save shop settings</button>' +
              (state.settingsMessage ? '<span class="success-message">' + escapeHtml(state.settingsMessage) + '</span>' : '') +
            '</div>' +
          '</form>' +
        '</div></section>'
      );
    }
    if (state.settingsSection === 'tag') {
      return (
        '<section class="settings-stack"><div class="entry-panel settings-panel">' +
          '<div class="printer-info"><div><span>Label printer</span><strong>TVS LP 46 NEO</strong></div>' +
            '<div><span>Manufacturer / model</span><strong>TVS Electronics · LP 46 NEO</strong></div>' +
            '<div><span>Tag paper type</span><strong>Jewellery hang tag · single-side print, fold at centre (sticker back)</strong></div></div>' +
          '<p class="settings-help">Both panels print on the same face. Fold on the centre mark so the sticker backs meet. Select <strong>TVS LP 46 NEO</strong> in the browser print dialog, then tune millimetre size and offsets below.</p>' +
          '<form data-action="save-tag" class="form-grid compact">' +
            '<label>Tag prefix<input class="form-control" name="tag_prefix" value="' + escapeHtml(draft.tag_prefix) + '"></label>' +
            '<label>Width mm<input class="form-control" name="tag_width_mm" type="number" step="0.1" value="' + escapeHtml(draft.tag_width_mm) + '"></label>' +
            '<label>Height mm<input class="form-control" name="tag_height_mm" type="number" step="0.1" value="' + escapeHtml(draft.tag_height_mm) + '"></label>' +
            '<label>Font pt<input class="form-control" name="font_size_pt" type="number" step="0.1" value="' + escapeHtml(draft.font_size_pt) + '"></label>' +
            '<label>X offset mm<input class="form-control" name="horizontal_offset_mm" type="number" step="0.1" value="' + escapeHtml(draft.horizontal_offset_mm) + '"></label>' +
            '<label>Y offset mm<input class="form-control" name="vertical_offset_mm" type="number" step="0.1" value="' + escapeHtml(draft.vertical_offset_mm) + '"></label>' +
            '<label class="check-row"><input type="checkbox" name="show_shop_name"' + (draft.show_shop_name ? ' checked' : '') + '> Show shop name</label>' +
            '<div class="button-row settings-actions full-row">' +
              '<button class="primary-button" type="submit"><i class="bi bi-floppy"></i> Save tag settings</button>' +
              (state.settingsMessage ? '<span class="success-message">' + escapeHtml(state.settingsMessage) + '</span>' : '') +
            '</div>' +
          '</form>' +
        '</div></section>'
      );
    }
    const items = state.itemCatalog.map(function (item) {
      if (state.editingId === item.id) {
        return '<li><input class="form-control" data-edit-name value="' + escapeHtml(state.editingName) + '">' +
          '<div class="item-catalog-actions">' +
            '<button class="primary-button" type="button" data-action="save-item" data-id="' + item.id + '"><i class="bi bi-floppy"></i> Save</button>' +
            '<button type="button" data-action="cancel-edit">Cancel</button>' +
          '</div></li>';
      }
      return '<li><strong>' + escapeHtml(item.name) + '</strong><div class="item-catalog-actions">' +
        '<button type="button" data-action="edit-item" data-id="' + item.id + '" data-name="' + escapeHtml(item.name) + '"><i class="bi bi-pencil"></i> Edit</button>' +
        '<button type="button" data-action="delete-item" data-id="' + item.id + '"><i class="bi bi-trash"></i> Delete</button>' +
        '</div></li>';
    }).join('');
    return (
      '<section class="settings-stack"><div class="entry-panel">' +
        '<div class="panel-title"><h2>Jewellery items</h2><span>' + state.itemCatalog.length + ' in dropdown</span></div>' +
        '<p class="settings-help">These names appear in the Create Tag item dropdown. Add, rename, or remove them here.</p>' +
        '<div class="item-add-row">' +
          '<input class="form-control" data-new-item placeholder="Add item, e.g. Tops" value="' + escapeHtml(state.newItemName) + '">' +
          '<button class="primary-button" type="button" data-action="add-item"' + (state.newItemName.trim() ? '' : ' disabled') + '><i class="bi bi-plus-lg"></i> Add</button>' +
        '</div>' +
        (state.itemError ? '<div class="error-banner">' + escapeHtml(state.itemError) + '</div>' : '') +
        '<ul class="item-catalog">' + items + '</ul>' +
      '</div></section>'
    );
  }

  function renderApp() {
    const shop = state.shop;
    const logo = shop.logo_url
      ? '<img class="brand-mark brand-logo" src="' + escapeHtml(shop.logo_url) + '" alt="">'
      : '<span class="brand-mark">JT</span>';
    const settingsSubnav = state.view === 'settings'
      ? '<div class="subnav">' +
          '<button type="button" class="' + (state.settingsSection === 'shop' ? 'active' : '') + '" data-action="go" data-view="settings" data-section="shop"><i class="bi bi-building"></i> Shop Settings</button>' +
          '<button type="button" class="' + (state.settingsSection === 'tag' ? 'active' : '') + '" data-action="go" data-view="settings" data-section="tag"><i class="bi bi-tag"></i> Tag Settings</button>' +
          '<button type="button" class="' + (state.settingsSection === 'items' ? 'active' : '') + '" data-action="go" data-view="settings" data-section="items"><i class="bi bi-list-ul"></i> Item Settings</button>' +
        '</div>'
      : '';
    let body = '';
    if (state.view === 'create') body = renderCreate();
    else if (state.view === 'history') body = renderHistory();
    else if (state.view === 'billing') body = renderBilling();
    else body = renderSettings();

    return (
      '<div class="app-shell' + (state.navOpen ? ' nav-open' : '') + '">' +
        '<button type="button" class="nav-toggle" data-action="toggle-nav" aria-expanded="' + state.navOpen + '">' +
          '<i class="bi ' + (state.navOpen ? 'bi-x-lg' : 'bi-list') + '"></i><span>' + (state.navOpen ? 'Close menu' : 'Open menu') + '</span>' +
        '</button>' +
        (state.navOpen ? '<button type="button" class="nav-backdrop" data-action="toggle-nav" aria-label="Close menu"></button>' : '') +
        '<div class="app-body">' +
          '<aside id="shop-sidebar" class="sidebar">' +
            '<button type="button" class="brand brand-home" data-action="go" data-view="create">' +
              logo +
              '<div><strong>' + escapeHtml(shop.name) + '</strong><small>' + escapeHtml(state.user.name) + '</small></div>' +
            '</button>' +
            '<nav>' +
              navButton('create', 'bi-tag', 'Create Tag') +
              navButton('history', 'bi-clock-history', 'History') +
              '<div class="nav-group">' +
                '<button type="button" class="' + (state.view === 'settings' ? 'active' : '') + '" data-action="go" data-view="settings" data-section="shop"><i class="bi bi-gear"></i> Settings</button>' +
                settingsSubnav +
              '</div>' +
              navButton('billing', 'bi-currency-rupee', 'Credits') +
            '</nav>' +
            '<button class="ghost-button" type="button" data-action="logout"><i class="bi bi-box-arrow-right"></i> Sign out</button>' +
          '</aside>' +
          '<main class="workspace">' +
            '<section class="topbar"><div><h1>' + viewTitle() + '</h1></div></section>' +
            (state.error ? '<div class="error-banner">' + escapeHtml(state.error) + '</div>' : '') +
            body +
          '</main>' +
        '</div>' +
        companyFooter() +
      '</div>'
    );
  }

  function render() {
    document.body.classList.toggle('nav-locked', state.navOpen);
    root.innerHTML = (!state.user || !state.shop) ? renderAuth() : renderApp();
  }

  function currentSettingsPayload(form) {
    const data = new FormData(form);
    return {
      name: data.get('name') || state.shop.name,
      address: data.get('address') || state.shop.address || '',
      phone_number: data.get('phone_number') || state.shop.phone_number || '',
      gst_no: String(data.get('gst_no') || state.shop.gst_no || '').toUpperCase(),
      short_name: data.get('short_name') || state.shop.short_name,
      tag_prefix: (data.get('tag_prefix') || state.shop.tag_prefix).toString().toUpperCase(),
      tag_width_mm: data.get('tag_width_mm') || state.shop.tag_width_mm,
      tag_height_mm: data.get('tag_height_mm') || state.shop.tag_height_mm,
      font_size_pt: data.get('font_size_pt') || state.shop.font_size_pt,
      horizontal_offset_mm: data.get('horizontal_offset_mm') || state.shop.horizontal_offset_mm,
      vertical_offset_mm: data.get('vertical_offset_mm') || state.shop.vertical_offset_mm,
      show_shop_name: form.querySelector('[name="show_shop_name"]') ? form.querySelector('[name="show_shop_name"]').checked : !!state.shop.show_shop_name,
    };
  }

  function saveTag(printAfter) {
    state.busy = true;
    state.error = '';
    render();
    api.createTag(state.form).then(function (tag) {
      state.activeTag = tag;
      return refreshData().then(function () {
        state.busy = false;
        render();
        if (printAfter) {
          window.setTimeout(function () { printTag(tag); }, 100);
        }
      });
    }).catch(function (err) {
      state.busy = false;
      state.error = err.message || 'Could not save tag';
      render();
    });
  }

  function printTag(tag) {
    state.activeTag = tag || draftTag();
    render();
    window.setTimeout(function () { window.print(); }, 50);
    if (state.activeTag && state.activeTag.id) {
      api.markPrinted(state.activeTag.id).then(function (updated) {
        state.activeTag = updated;
        return refreshData();
      }).then(render).catch(function (err) {
        state.error = err.message;
        render();
      });
    }
  }

  function saveSettingsFrom(form) {
    const payload = currentSettingsPayload(form);
    api.updateSettings(payload).then(function (updated) {
      state.shop = updated;
      state.settingsMessage = 'Saved';
      render();
    }).catch(function (err) {
      state.error = err.message;
      render();
    });
  }

  root.addEventListener('click', function (event) {
    const button = event.target.closest('[data-action]');
    if (!button) return;
    const action = button.getAttribute('data-action');
    if (action === 'toggle-auth') {
      state.authMode = state.authMode === 'register' ? 'login' : 'register';
      state.error = '';
      render();
    }
    if (action === 'toggle-nav') {
      state.navOpen = !state.navOpen;
      render();
    }
    if (action === 'go') {
      state.view = button.getAttribute('data-view');
      if (button.getAttribute('data-section')) state.settingsSection = button.getAttribute('data-section');
      state.navOpen = false;
      state.error = '';
      state.settingsMessage = '';
      render();
    }
    if (action === 'logout') {
      api.logout().finally(function () {
        state.user = null;
        state.shop = null;
        state.stats = null;
        state.tags = [];
        state.plans = [];
        state.ledger = [];
        state.navOpen = false;
        render();
      });
    }
    if (action === 'save') saveTag(false);
    if (action === 'save-print') saveTag(true);
    if (action === 'test-print') printTag();
    if (action === 'reprint') {
      const tag = state.tags.filter(function (row) { return String(row.id) === button.getAttribute('data-id'); })[0];
      if (tag) printTag(tag);
    }
    if (action === 'buy-plan') {
      const planId = Number(button.getAttribute('data-id'));
      button.disabled = true;
      api.createPurchase(planId).then(function (purchase) {
        return api.confirmPurchase(purchase.id);
      }).then(function () {
        return refreshData();
      }).then(function () {
        state.billingMessage = 'Plan activated for local testing. Razorpay capture will replace this confirmation step in production.';
        render();
      }).catch(function (err) {
        state.error = err.message;
        render();
      });
    }
    if (action === 'pick-logo') {
      const input = document.getElementById('logo-input');
      if (input) input.click();
    }
    if (action === 'remove-logo') {
      api.deleteShopLogo().then(function (shop) {
        state.shop = shop;
        state.settingsMessage = 'Logo removed';
        state.logoError = '';
        render();
      }).catch(function (err) {
        state.logoError = err.message;
        render();
      });
    }
    if (action === 'add-item') {
      api.createShopItem(state.newItemName).then(function () {
        state.newItemName = '';
        state.itemError = '';
        return api.listShopItems();
      }).then(function (items) {
        state.itemCatalog = items;
        render();
      }).catch(function (err) {
        state.itemError = err.message;
        render();
      });
    }
    if (action === 'edit-item') {
      state.editingId = Number(button.getAttribute('data-id'));
      state.editingName = button.getAttribute('data-name') || '';
      render();
    }
    if (action === 'cancel-edit') {
      state.editingId = null;
      render();
    }
    if (action === 'save-item') {
      const editInput = root.querySelector('[data-edit-name]');
      const name = editInput ? editInput.value : state.editingName;
      api.updateShopItem(Number(button.getAttribute('data-id')), name).then(function () {
        state.editingId = null;
        state.itemError = '';
        return api.listShopItems();
      }).then(function (items) {
        state.itemCatalog = items;
        render();
      }).catch(function (err) {
        state.itemError = err.message;
        render();
      });
    }
    if (action === 'delete-item') {
      api.deleteShopItem(Number(button.getAttribute('data-id'))).then(function () {
        if (state.editingId === Number(button.getAttribute('data-id'))) state.editingId = null;
        return api.listShopItems();
      }).then(function (items) {
        state.itemCatalog = items;
        render();
      }).catch(function (err) {
        state.itemError = err.message;
        render();
      });
    }
  });

  root.addEventListener('submit', function (event) {
    const form = event.target.closest('form');
    if (!form) return;
    event.preventDefault();
    const action = form.getAttribute('data-action');
    if (action === 'auth') {
      const data = new FormData(form);
      state.busy = true;
      state.error = '';
      render();
      const request = state.authMode === 'register'
        ? api.register({
            shop_name: String(data.get('shop_name') || ''),
            shop_short_name: String(data.get('shop_short_name') || ''),
            owner_name: String(data.get('owner_name') || ''),
            email: String(data.get('email') || ''),
            password: String(data.get('password') || ''),
          })
        : api.login({
            email: String(data.get('email') || ''),
            password: String(data.get('password') || ''),
          });
      request.then(function (auth) {
        acceptAuth(auth);
        return refreshData();
      }).then(function () {
        state.busy = false;
        render();
      }).catch(function (err) {
        state.busy = false;
        state.error = err.message || 'Something went wrong';
        render();
      });
    }
    if (action === 'save-shop' || action === 'save-tag') {
      saveSettingsFrom(form);
    }
  });

  root.addEventListener('input', function (event) {
    const field = event.target.getAttribute('data-field');
    if (field) {
      state.activeTag = null;
      state.form[field] = event.target.value;
      if (field === 'gross_weight' || field === 'stone_weight') {
        const netBox = root.querySelector('.net-box strong');
        if (netBox) netBox.textContent = calculateNet(state.form) + ' g';
        const preview = root.querySelector('.print-bundle');
        if (preview && state.shop) preview.innerHTML = printableTagHtml(draftTag(), state.shop);
      }
    }
    if (event.target.hasAttribute('data-new-item')) {
      state.newItemName = event.target.value;
      const addBtn = root.querySelector('[data-action="add-item"]');
      if (addBtn) addBtn.disabled = !state.newItemName.trim();
    }
    if (event.target.hasAttribute('data-edit-name')) {
      state.editingName = event.target.value;
    }
  });

  root.addEventListener('change', function (event) {
    if (event.target.name === 'category') {
      state.activeTag = null;
      state.form.category = event.target.value;
      render();
    }
    if (event.target.getAttribute('data-field') === 'item_name') {
      state.activeTag = null;
      state.form.item_name = event.target.value;
      render();
    }
    if (event.target.id === 'logo-input' && event.target.files && event.target.files[0]) {
      api.uploadShopLogo(event.target.files[0]).then(function (shop) {
        state.shop = shop;
        state.settingsMessage = 'Logo saved';
        state.logoError = '';
        render();
      }).catch(function (err) {
        state.logoError = err.message;
        render();
      });
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && state.navOpen) {
      state.navOpen = false;
      render();
    }
    if (event.key === 'Enter' && event.target && event.target.hasAttribute('data-new-item')) {
      const addBtn = root.querySelector('[data-action="add-item"]');
      if (addBtn && !addBtn.disabled) addBtn.click();
    }
  });

  api.me().then(function (auth) {
    acceptAuth(auth);
    return refreshData();
  }).catch(function () {
    state.user = null;
  }).then(render);
})();
