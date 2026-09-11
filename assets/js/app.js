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
    support: null,
    platform: null,
    view: 'create',
    settingsSection: 'shop',
    stats: null,
    tags: [],
    plans: [],
    ledger: [],
    activeTag: null,
    form: Object.assign({}, blankTag),
    itemCatalog: [],
    authMode: 'login',
    resetToken: '',
    resetUrl: '',
    error: '',
    success: '',
    busy: false,
    navOpen: false,
    billingMessage: '',
    purchaseMonths: 1,
    settingsMessage: '',
    itemError: '',
    logoError: '',
    newItemName: '',
    editingId: null,
    editingName: '',
    promoCode: '',
  };

  const root = document.getElementById('app');
  const brandName = (window.APP_CONFIG && window.APP_CONFIG.appName) || 'TagForge';
  const brandTagline = (window.APP_CONFIG && window.APP_CONFIG.appTagline) || 'Print tags. Run your shop.';

  function setDocumentTitle(part) {
    const base = brandName + ' — ' + brandTagline;
    document.title = part ? (part + ' · ' + brandName) : base;
  }

  function brandHeading(supportText) {
    return (
      '<div class="auth-heading">' +
        '<img class="brand-lockup" src="assets/img/logo.png" alt="' + escapeHtml(brandName) + '">' +
        (supportText ? '<p class="auth-support">' + escapeHtml(supportText) + '</p>' : '') +
      '</div>'
    );
  }

  function formatWeight(value) {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed.toFixed(3) : '0.000';
  }

  function formatPurity(value) {
    const purity = String(value || '').trim().replace(/\s*\/\s*/g, '/').toUpperCase();
    if (!purity) return '—';
    return 'Purity: ' + purity;
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
    const ver = (window.APP_CONFIG && window.APP_CONFIG.appVersion) || '';
    const suffix = ver ? ' · v' + escapeHtml(ver) : '';
    return '<footer class="app-footer">Migids Software LLP, Bengaluru' + suffix + '</footer>';
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

  function tagBrandMark(shop) {
    if (shop && shop.logo_url) {
      return '<img class="tag-back-logo" src="' + escapeHtml(shop.logo_url) + '" alt="Shop logo">';
    }
    return '<span class="tag-back-number">B&amp;G</span>';
  }

  function printableTagHtml(tag, shop) {
    const width = shop.tag_width_mm || 64;
    const height = shop.tag_height_mm || 18;
    const x = Number(shop.horizontal_offset_mm) || 0;
    const y = Number(shop.vertical_offset_mm) || 0;
    // Position with left/top (not transform) so print engines don't invent a 2nd page.
    const style = [
      'width:' + width + 'mm',
      'height:' + height + 'mm',
      'font-size:' + shop.font_size_pt + 'pt',
      'left:' + x + 'mm',
      'top:' + y + 'mm',
      'position:relative',
    ].join(';');
    return (
      '<article class="print-tag" style="' + style + '">' +
        '<div class="tag-printable-face">' +
          '<div class="tag-panel tag-panel-left" aria-label="Left panel: weights">' +
            '<div class="tag-weight-grid">' +
              '<span class="tag-weight-row">Grs.Wt: ' + formatWeight(tag.gross_weight) + '</span>' +
              '<span class="tag-weight-row">Stn.Wt: ' + formatWeight(tag.stone_weight) + '</span>' +
              '<span class="tag-weight-row">Nt.Wt: ' + formatWeight(tag.net_weight) + '</span>' +
            '</div>' +
          '</div>' +
          '<div class="tag-fold-mark" aria-hidden="true" title="Fold line"></div>' +
          '<div class="tag-panel tag-panel-right" aria-label="Right panel: item, purity, shop logo">' +
            '<div class="tag-back">' +
              '<span class="tag-back-item">' + escapeHtml(tagItemLabel(tag.item_name, tag.category).toUpperCase()) + '</span>' +
              '<span class="tag-back-purity">' + escapeHtml(formatPurity(tag.purity)) + '</span>' +
              tagBrandMark(shop) +
            '</div>' +
          '</div>' +
        '</div>' +
        '<div class="tag-tail" aria-hidden="true"></div>' +
      '</article>'
    );
  }

  function acceptAuth(auth) {
    state.user = auth.user;
    state.shop = auth.shop;
    state.support = auth.support || null;
    state.platform = auth.platform || null;
  }

  function announcementBanner() {
    const message = state.platform && state.platform.announcement ? state.platform.announcement : '';
    if (!message) return '';
    return '<div class="announcement-banner"><strong>Notice</strong><span>' + escapeHtml(message) + '</span></div>';
  }

  function isSupportReadonly() {
    return !!(state.support && state.support.readonly);
  }

  function supportBanner() {
    if (!state.support) return '';
    const modeLabel = state.support.readonly ? 'Read-only support view' : 'Timed support edit session';
    const expires = state.support.expires_at ? formatDateTime(state.support.expires_at) : '';
    return (
      '<div class="support-banner' + (state.support.readonly ? ' is-readonly' : '') + '">' +
        '<div>' +
          '<strong>' + escapeHtml(modeLabel) + '</strong>' +
          '<span>Viewing as ' + escapeHtml(state.shop && state.shop.name ? state.shop.name : 'shop') +
          (state.support.admin_name ? ' · started by ' + escapeHtml(state.support.admin_name) : '') +
          (expires ? ' · ends ' + escapeHtml(expires) : '') +
          '</span>' +
        '</div>' +
        '<button type="button" data-action="end-support"><i class="bi bi-box-arrow-left"></i> Exit support view</button>' +
      '</div>'
    );
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
    const isRegister = state.authMode === 'register';
    const isForgot = state.authMode === 'forgot';
    const isReset = state.authMode === 'reset';

    let heading = '';
    let formFields = '';
    let submitLabel = 'Sign in';
    let submitIcon = 'bi-box-arrow-in-right';
    let secondary = '';

    if (isRegister) {
      heading = 'Create a shop account to start printing hang tags.';
      submitLabel = 'Create shop account';
      submitIcon = 'bi-person-plus';
      formFields =
        '<label>Shop name<input class="form-control" name="shop_name" value="Bhaskaran Jewellers" required></label>' +
        '<label>Tag short name<input class="form-control" name="shop_short_name" value="BHJ" required></label>' +
        '<label>Owner name<input class="form-control" name="owner_name" value="Owner" required></label>' +
        '<label>Email<input class="form-control" name="email" type="email" value="owner@example.com" required></label>' +
        '<label>Password<input class="form-control" name="password" type="password" value="password123" minlength="8" required></label>';
      secondary = '<button class="link-button" type="button" data-action="toggle-auth">Use an existing account</button>';
    } else if (isForgot) {
      heading = 'Enter your account email and we will send a password reset link.';
      submitLabel = 'Send reset link';
      submitIcon = 'bi-envelope';
      formFields = '<label>Email<input class="form-control" name="email" type="email" required></label>';
      secondary = '<button class="link-button" type="button" data-action="show-login">Back to sign in</button>';
    } else if (isReset) {
      heading = 'Choose a new password for your account.';
      submitLabel = 'Update password';
      submitIcon = 'bi-key';
      formFields =
        '<label>New password<input class="form-control" name="password" type="password" minlength="8" required></label>' +
        '<label>Confirm password<input class="form-control" name="password_confirm" type="password" minlength="8" required></label>';
      secondary = '<button class="link-button" type="button" data-action="show-login">Back to sign in</button>';
    } else {
      formFields =
        '<label>Email<input class="form-control" name="email" type="email" required></label>' +
        '<label>Password<input class="form-control" name="password" type="password" minlength="8" required></label>' +
        '<div class="auth-links"><button class="link-button" type="button" data-action="show-forgot">Forgot password?</button></div>';
      secondary =
        '<button class="link-button" type="button" data-action="toggle-auth">Create a new shop account</button>';
    }

    return (
      '<main class="auth-layout">' +
        '<section class="auth-panel">' +
          brandHeading(heading) +
          '<form data-action="auth" class="form-grid">' +
            formFields +
            (state.error ? '<div class="error-banner">' + escapeHtml(state.error) + '</div>' : '') +
            (state.success ? '<div class="success-banner">' + escapeHtml(state.success) + '</div>' : '') +
            (state.success && state.resetUrl
              ? '<p class="auth-reset-link"><a href="' + escapeHtml(state.resetUrl) + '">Open password reset link</a></p>'
              : '') +
            '<button class="primary-button" ' + (state.busy ? 'disabled' : '') + '><i class="bi ' + submitIcon + '"></i> ' +
              submitLabel +
            '</button>' +
          '</form>' +
          secondary +
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
            '<label>Purity<input class="form-control purity-input" data-field="purity" placeholder="22K/916" autocapitalize="characters" spellcheck="false" value="' + escapeHtml(state.form.purity) + '"></label>' +
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
    const monthlyPrice = billing && billing.monthly_plan_price_inr ? Number(billing.monthly_plan_price_inr) : 599;
    const months = Math.max(1, Math.min(24, Number(state.purchaseMonths) || 1));
    const total = monthlyPrice * months;
    const plan = state.plans[0] || null;
    const unlimitedActive = !!(billing && billing.is_unlimited_active);
    const freeCredits = billing ? billing.tag_credit_balance : 0;
    const freeDays = billing ? billing.free_registration_validity_days : 2;

    const statusCard = unlimitedActive
      ? '<div class="success-banner">Unlimited plan is active until <strong>' + escapeHtml(formatDate(billing.unlimited_until)) + '</strong>.</div>'
      : (freeCredits > 0
        ? '<div class="success-banner">Free starter pack: <strong>' + freeCredits + '</strong> tags left' +
          (billing && billing.credits_expire_at ? ' · valid until <strong>' + escapeHtml(formatDate(billing.credits_expire_at)) + '</strong>' : '') +
          '.</div>'
        : '<div class="error-banner">Free tags used up or expired. Purchase Monthly Unlimited to continue creating tags.</div>');

    const monthOptions = [1, 2, 3, 6, 12].map(function (value) {
      return '<option value="' + value + '"' + (months === value ? ' selected' : '') + '>' + value + (value === 1 ? ' month' : ' months') + '</option>';
    }).join('');

    const planCard = plan
      ? '<article class="plan-card">' +
          '<div class="plan-heading"><h2>' + escapeHtml(plan.name) + '</h2><i class="bi bi-crown"></i></div>' +
          '<p>' + escapeHtml(plan.description) + '</p>' +
          '<div class="plan-price">Rs. ' + monthlyPrice.toLocaleString('en-IN') + '<small> / month</small></div>' +
          '<dl><div><dt>Tags</dt><dd>Unlimited</dd></div><div><dt>Billing</dt><dd>Choose months</dd></div></dl>' +
          '<label>Number of months<select class="form-select" data-field="purchase_months">' + monthOptions + '</select></label>' +
          '<div class="plan-total">Total payable: <strong>Rs. ' + total.toLocaleString('en-IN') + '</strong> for ' + months + (months === 1 ? ' month' : ' months') + '</div>' +
          '<button class="primary-button" type="button" data-action="buy-plan" data-id="' + plan.id + '"' + (state.busy ? ' disabled' : '') + '>' +
            '<i class="bi bi-currency-rupee"></i> Pay with Razorpay' +
          '</button>' +
        '</article>'
      : '<div class="error-banner">No active plan is configured. Contact support.</div>';

    const ledger = state.ledger.map(function (entry) {
      return '<tr><td>' + escapeHtml(formatDate(entry.created_at)) + '</td><td>' + escapeHtml(entry.entry_type) + '</td><td>' + entry.credits + '</td><td>' + entry.balance_after + '</td><td>' + escapeHtml(entry.description) + '</td></tr>';
    }).join('');

    return (
      '<section class="billing-layout">' +
        '<div class="billing-summary">' +
          '<div><span>Available tags</span><strong>' + (unlimitedActive ? 'Unlimited' : freeCredits) + '</strong></div>' +
          '<div><span>Free pack validity</span><strong>' + formatDate(billing && billing.credits_expire_at) + '</strong></div>' +
          '<div><span>Unlimited until</span><strong>' + formatDate(billing && billing.unlimited_until) + '</strong></div>' +
          '<div><span>Monthly plan</span><strong>Rs. ' + monthlyPrice.toLocaleString('en-IN') + '</strong></div>' +
        '</div>' +
        statusCard +
        '<p class="settings-help">New shops get <strong>' + (billing ? billing.free_registration_credits : 20) + ' free tags for ' + freeDays + ' days</strong>. Buy Monthly Unlimited or redeem a promo code below.</p>' +
        (state.billingMessage ? '<div class="success-banner">' + escapeHtml(state.billingMessage) + '</div>' : '') +
        '<article class="entry-panel"><div class="panel-title"><h2>Promo / trial code</h2></div>' +
          '<form data-action="redeem-promo" class="form-grid compact">' +
            '<label>Code<input class="form-control" name="code" value="' + escapeHtml(state.promoCode || '') + '" placeholder="TRIAL50" required></label>' +
            '<button class="secondary-button" type="submit"><i class="bi bi-ticket-perforated"></i> Redeem</button>' +
          '</form></article>' +
        '<div class="plan-grid single-plan">' + planCard + '</div>' +
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
      : '<img class="brand-mark brand-mark-img" src="assets/img/mark.png" alt="">';
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
      '<div class="app-shell' + (state.navOpen ? ' nav-open' : '') + (isSupportReadonly() ? ' support-readonly' : '') + '">' +
        announcementBanner() +
        supportBanner() +
        '<button type="button" class="nav-toggle" data-action="toggle-nav" aria-expanded="' + state.navOpen + '" aria-controls="shop-sidebar">' +
          '<i class="bi ' + (state.navOpen ? 'bi-x-lg' : 'bi-list') + '"></i><span>' + (state.navOpen ? 'Close menu' : 'Menu') + '</span>' +
        '</button>' +
        (state.navOpen ? '<button type="button" class="nav-backdrop" data-action="toggle-nav" aria-label="Close menu"></button>' : '') +
        '<div class="app-body">' +
          '<aside id="shop-sidebar" class="sidebar">' +
            '<button type="button" class="brand brand-home" data-action="go" data-view="create">' +
              logo +
              '<div><strong>' + escapeHtml(shop.name) + '</strong><small>' + escapeHtml(state.user.name) + (state.support ? ' · Support' : '') + '</small></div>' +
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
            (state.support
              ? '<button class="ghost-button" type="button" data-action="end-support"><i class="bi bi-box-arrow-left"></i> Exit support view</button>'
              : '<button class="ghost-button" type="button" data-action="logout"><i class="bi bi-box-arrow-right"></i> Sign out</button>') +
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
    document.documentElement.classList.add('app-ready');
    document.body.classList.toggle('nav-locked', state.navOpen);
    if (!state.user || !state.shop) {
      setDocumentTitle(state.authMode === 'register' ? 'Create shop' : (state.authMode === 'forgot' || state.authMode === 'reset' ? 'Reset password' : 'Sign in'));
      root.innerHTML = renderAuth();
      return;
    }
    setDocumentTitle(viewTitle());
    root.innerHTML = renderApp();
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

  function ensurePrintPageSize(shop) {
    const width = shop && shop.tag_width_mm ? shop.tag_width_mm : '64';
    const height = shop && shop.tag_height_mm ? shop.tag_height_mm : '18';
    let styleEl = document.getElementById('print-page-size');
    if (!styleEl) {
      styleEl = document.createElement('style');
      styleEl.id = 'print-page-size';
      document.head.appendChild(styleEl);
    }
    styleEl.textContent =
      '@media print {' +
        '@page { size: ' + width + 'mm ' + height + 'mm; margin: 0; }' +
        'html, body, #print-root { width: ' + width + 'mm !important; height: ' + height + 'mm !important; margin: 0 !important; padding: 0 !important; overflow: hidden !important; }' +
        '#print-root .print-tag { width: ' + width + 'mm !important; height: ' + height + 'mm !important; border-radius: 1mm !important; overflow: hidden !important; background: #ffffff !important; }' +
        '#print-root .tag-printable-face { border-radius: 1mm !important; overflow: hidden !important; background: #ffffff !important; }' +
      '}';
  }

  function getPrintRoot() {
    let root = document.getElementById('print-root');
    if (!root) {
      root = document.createElement('div');
      root.id = 'print-root';
      root.setAttribute('aria-hidden', 'true');
      document.body.appendChild(root);
    }
    return root;
  }

  function printTag(tag) {
    const shop = state.shop;
    if (!shop) return;
    state.activeTag = tag || draftTag();
    ensurePrintPageSize(shop);
    const printRoot = getPrintRoot();
    printRoot.innerHTML = printableTagHtml(state.activeTag, shop);

    const cleanup = function () {
      window.removeEventListener('afterprint', cleanup);
      printRoot.innerHTML = '';
      render();
    };
    window.addEventListener('afterprint', cleanup);
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

  function loadRazorpayScript() {
    return new Promise(function (resolve, reject) {
      if (window.Razorpay) {
        resolve();
        return;
      }
      const existing = document.querySelector('script[data-razorpay="1"]');
      if (existing) {
        existing.addEventListener('load', function () { resolve(); });
        existing.addEventListener('error', function () { reject(new Error('Could not load Razorpay Checkout')); });
        return;
      }
      const script = document.createElement('script');
      script.src = 'https://checkout.razorpay.com/v1/checkout.js';
      script.async = true;
      script.setAttribute('data-razorpay', '1');
      script.onload = function () { resolve(); };
      script.onerror = function () { reject(new Error('Could not load Razorpay Checkout')); };
      document.head.appendChild(script);
    });
  }

  function finalizePurchase(purchase, paymentPayload) {
    const payload = Object.assign({ id: purchase.id }, paymentPayload || {});
    return api.confirmPurchase(payload).then(function () {
      return refreshData();
    }).then(function () {
      state.busy = false;
      state.billingMessage = purchase.checkout_mode === 'razorpay'
        ? 'Payment successful. Unlimited plan is now active.'
        : 'Plan activated (local test mode — add Razorpay keys in config for live checkout).';
      render();
    });
  }

  function openRazorpayCheckout(purchase) {
    return loadRazorpayScript().then(function () {
      return new Promise(function (resolve, reject) {
        const options = {
          key: purchase.razorpay_key_id,
          amount: purchase.amount_paise,
          currency: purchase.currency || 'INR',
          name: 'TagForge',
          description: 'Monthly Unlimited · ' + purchase.months + (purchase.months === 1 ? ' month' : ' months'),
          order_id: purchase.razorpay_order_id,
          handler: function (response) {
            resolve({
              razorpay_order_id: response.razorpay_order_id,
              razorpay_payment_id: response.razorpay_payment_id,
              razorpay_signature: response.razorpay_signature,
            });
          },
          modal: {
            ondismiss: function () {
              if (api.failPurchase) {
                api.failPurchase(purchase.id, 'Payment cancelled').catch(function () {});
              }
              reject(new Error('Payment cancelled'));
            },
          },
          theme: { color: '#0f766e' },
        };
        const rzp = new window.Razorpay(options);
        rzp.on('payment.failed', function (response) {
          const detail = response && response.error && response.error.description
            ? response.error.description
            : 'Payment failed';
          if (api.failPurchase) {
            api.failPurchase(purchase.id, detail).catch(function () {});
          }
          reject(new Error(detail));
        });
        rzp.open();
      });
    });
  }

  function startPlanPurchase(planId, months) {
    api.createPurchase(planId, months).then(function (purchase) {
      if (purchase.checkout_mode === 'razorpay') {
        return openRazorpayCheckout(purchase).then(function (payment) {
          return finalizePurchase(purchase, payment);
        });
      }
      return finalizePurchase(purchase, {});
    }).catch(function (err) {
      state.busy = false;
      state.error = err.message || 'Could not complete purchase';
      render();
    });
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
      state.success = '';
      state.resetUrl = '';
      render();
    }
    if (action === 'show-forgot') {
      state.authMode = 'forgot';
      state.error = '';
      state.success = '';
      state.resetUrl = '';
      render();
    }
    if (action === 'show-login') {
      state.authMode = 'login';
      state.error = '';
      state.success = '';
      state.resetUrl = '';
      state.resetToken = '';
      if (window.history && window.history.replaceState) {
        window.history.replaceState({}, '', window.location.pathname);
      }
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
        state.support = null;
        state.stats = null;
        state.tags = [];
        state.plans = [];
        state.ledger = [];
        state.navOpen = false;
        render();
      });
    }
    if (action === 'end-support') {
      api.supportEnd().finally(function () {
        state.user = null;
        state.shop = null;
        state.support = null;
        state.navOpen = false;
        const adminUrl = (window.APP_CONFIG && window.APP_CONFIG.adminUrl) || '';
        if (adminUrl) {
          window.location.href = adminUrl;
          return;
        }
        render();
      });
      return;
    }
    if (isSupportReadonly() && (action === 'save' || action === 'save-print' || action === 'test-print' || action === 'reprint' || action === 'buy-plan' || action === 'delete-logo' || action === 'delete-item' || action === 'save-item')) {
      state.error = 'Support view is read-only. Start a timed session from admin to make changes.';
      render();
      return;
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
      const months = Math.max(1, Math.min(24, Number(state.purchaseMonths) || 1));
      state.busy = true;
      state.error = '';
      state.billingMessage = '';
      render();
      startPlanPurchase(planId, months);
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
    if (action !== 'auth' && isSupportReadonly()) {
      state.error = 'Support view is read-only. Start a timed session from admin to make changes.';
      render();
      return;
    }
    if (action === 'redeem-promo') {
      const code = String(data.get('code') || '');
      state.busy = true;
      state.error = '';
      render();
      api.redeemPromo(code).then(function () {
        state.busy = false;
        state.promoCode = '';
        state.billingMessage = 'Promo redeemed';
        return refreshData();
      }).then(render).catch(function (err) {
        state.busy = false;
        state.error = err.message;
        render();
      });
      return;
    }
    if (action === 'auth') {
      const data = new FormData(form);
      state.busy = true;
      state.error = '';
      state.success = '';
      state.resetUrl = '';
      render();

      if (state.authMode === 'forgot') {
        api.forgotPassword({
          email: String(data.get('email') || ''),
        }).then(function (result) {
          state.busy = false;
          state.success = (result && result.detail) || 'If that email is registered, a password reset link has been sent.';
          if (result && result.reset_url) {
            state.resetUrl = result.reset_url;
          }
          render();
        }).catch(function (err) {
          state.busy = false;
          state.error = err.message || 'Could not send reset link';
          render();
        });
        return;
      }

      if (state.authMode === 'reset') {
        const password = String(data.get('password') || '');
        const confirm = String(data.get('password_confirm') || '');
        if (password !== confirm) {
          state.busy = false;
          state.error = 'Passwords do not match';
          render();
          return;
        }
        api.resetPassword({
          token: state.resetToken,
          password: password,
        }).then(function (result) {
          state.busy = false;
          state.authMode = 'login';
          state.resetToken = '';
          state.success = (result && result.detail) || 'Password updated. You can sign in now.';
          if (window.history && window.history.replaceState) {
            window.history.replaceState({}, '', window.location.pathname);
          }
          render();
        }).catch(function (err) {
          state.busy = false;
          state.error = err.message || 'Could not reset password';
          render();
        });
        return;
      }

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
    if (field === 'purchase_months') {
      state.purchaseMonths = Math.max(1, Math.min(24, Number(event.target.value) || 1));
      render();
      return;
    }
    if (field) {
      state.activeTag = null;
      let value = event.target.value;
      if (field === 'purity') {
        value = value.toUpperCase();
        if (event.target.value !== value) {
          event.target.value = value;
        }
      }
      state.form[field] = value;
      if (field === 'gross_weight' || field === 'stone_weight' || field === 'purity') {
        const netBox = root.querySelector('.net-box strong');
        if (netBox && field !== 'purity') netBox.textContent = calculateNet(state.form) + ' g';
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
    if (event.target.getAttribute('data-field') === 'purchase_months') {
      state.purchaseMonths = Math.max(1, Math.min(24, Number(event.target.value) || 1));
      render();
      return;
    }
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

  window.addEventListener('resize', function () {
    if (state.navOpen && window.matchMedia('(min-width: 901px)').matches) {
      state.navOpen = false;
      document.body.classList.remove('nav-locked');
      render();
    }
  });

  window.addEventListener('orientationchange', function () {
    if (state.navOpen) {
      state.navOpen = false;
      document.body.classList.remove('nav-locked');
      render();
    }
  });

  const resetParams = new URLSearchParams(window.location.search || '');
  const resetFromUrl = resetParams.get('reset');
  const supportFromUrl = resetParams.get('support');
  if (resetFromUrl) {
    state.authMode = 'reset';
    state.resetToken = resetFromUrl;
  }

  function clearSupportQuery() {
    if (!supportFromUrl) return;
    const url = new URL(window.location.href);
    url.searchParams.delete('support');
    window.history.replaceState({}, document.title, url.pathname + url.search + url.hash);
  }

  function boot() {
    // Paint auth/shell immediately so SEO fallback never flashes after scripts load.
    render();

    if (supportFromUrl) {
      return api.supportStart(supportFromUrl).then(function (auth) {
        clearSupportQuery();
        acceptAuth(auth);
        return refreshData();
      }).catch(function (err) {
        clearSupportQuery();
        state.user = null;
        state.support = null;
        state.error = err.message || 'Could not start support view';
      }).then(render);
    }

    return api.me().then(function (auth) {
      acceptAuth(auth);
      return refreshData();
    }).catch(function () {
      state.user = null;
      state.support = null;
    }).then(render);
  }

  boot();
})();
