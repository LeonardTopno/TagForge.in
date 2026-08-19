import { FormEvent, useEffect, useMemo, useState } from 'react';
import { BadgeIndianRupee, Crown, History, LogOut, Printer, Save, Settings, Shield, Tag, UserPlus } from 'lucide-react';
import { api, clearAuthToken, setAuthToken } from './api';
import type {
  AdminCreditAdjustment,
  AdminTenant,
  AuthResponse,
  BillingPlan,
  BillingSummary,
  CreditLedgerEntry,
  DashboardStats,
  JewelleryTag,
  Shop,
  TagForm,
  User,
} from './types';

type View = 'create' | 'history' | 'billing' | 'settings';

const blankTag: TagForm = {
  item_name: 'Ring',
  category: 'Gold',
  purity: '22K / 916',
  pieces: 1,
  gross_weight: '4.080',
  stone_weight: '0.000',
  other_deduction: '0.000',
  copies: 1,
};

function formatWeight(value: string | number) {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed.toFixed(3) : '0.000';
}

function calculateNet(form: TagForm) {
  return formatWeight(Number(form.gross_weight || 0) - Number(form.stone_weight || 0) - Number(form.other_deduction || 0));
}

function nextPreviewNumber(shop: Shop | null) {
  if (!shop) return 'T000001';
  return `${shop.tag_prefix}${String(shop.next_tag_number).padStart(6, '0')}`;
}

export function App() {
  if (window.location.pathname === '/admin-portal') {
    return <AdminPortal />;
  }

  const [user, setUser] = useState<User | null>(null);
  const [shop, setShop] = useState<Shop | null>(null);
  const [view, setView] = useState<View>('create');
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [tags, setTags] = useState<JewelleryTag[]>([]);
  const [plans, setPlans] = useState<BillingPlan[]>([]);
  const [ledger, setLedger] = useState<CreditLedgerEntry[]>([]);
  const [activeTag, setActiveTag] = useState<JewelleryTag | null>(null);
  const [form, setForm] = useState<TagForm>(blankTag);
  const [authMode, setAuthMode] = useState<'login' | 'register'>('register');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  function acceptAuth(auth: AuthResponse) {
    setAuthToken(auth.access_token);
    setUser(auth.user);
    setShop(auth.shop);
  }

  async function hydrate() {
    try {
      acceptAuth(await api.me());
    } catch {
      clearAuthToken();
    }
  }

  async function refreshData() {
    if (!user) return;
    const [dashboard, tagRows, settings, planRows, ledgerRows] = await Promise.all([
      api.dashboard(),
      api.listTags(),
      api.settings(),
      api.billingPlans(),
      api.billingLedger(),
    ]);
    setStats(dashboard);
    setTags(tagRows);
    setShop(settings);
    setPlans(planRows);
    setLedger(ledgerRows);
  }

  useEffect(() => {
    hydrate();
  }, []);

  useEffect(() => {
    refreshData().catch((err) => setError(err.message));
  }, [user]);

  const draftTag: JewelleryTag = useMemo(
    () => ({
      id: 0,
      shop_id: shop?.id ?? 0,
      tag_number: activeTag?.tag_number ?? nextPreviewNumber(shop),
      item_name: activeTag?.item_name ?? form.item_name,
      category: activeTag?.category ?? form.category,
      purity: activeTag?.purity ?? form.purity,
      pieces: activeTag?.pieces ?? form.pieces,
      gross_weight: activeTag?.gross_weight ?? formatWeight(form.gross_weight),
      stone_weight: activeTag?.stone_weight ?? formatWeight(form.stone_weight),
      other_deduction: activeTag?.other_deduction ?? formatWeight(form.other_deduction),
      net_weight: activeTag?.net_weight ?? calculateNet(form),
      copies: activeTag?.copies ?? form.copies,
      status: 'active',
      print_count: activeTag?.print_count ?? 0,
      created_at: new Date().toISOString(),
    }),
    [activeTag, form, shop],
  );

  async function handleAuth(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setBusy(true);
    setError('');
    const data = new FormData(event.currentTarget);
    try {
      const auth =
        authMode === 'register'
          ? await api.register({
              shop_name: String(data.get('shop_name')),
              shop_short_name: String(data.get('shop_short_name')),
              owner_name: String(data.get('owner_name')),
              email: String(data.get('email')),
              password: String(data.get('password')),
            })
          : await api.login({
              email: String(data.get('email')),
              password: String(data.get('password')),
            });
      acceptAuth(auth);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Something went wrong');
    } finally {
      setBusy(false);
    }
  }

  async function saveTag(printAfter = false) {
    setBusy(true);
    setError('');
    try {
      const tag = await api.createTag(form);
      setActiveTag(tag);
      await refreshData();
      if (printAfter) {
        window.setTimeout(() => printTag(tag), 100);
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not save tag');
    } finally {
      setBusy(false);
    }
  }

  async function printTag(tag = draftTag) {
    setActiveTag(tag);
    window.setTimeout(() => window.print(), 50);
    if (tag.id) {
      const updated = await api.markPrinted(tag.id);
      setActiveTag(updated);
      await refreshData();
    }
  }

  function logout() {
    clearAuthToken();
    setUser(null);
    setShop(null);
    setStats(null);
    setTags([]);
    setPlans([]);
    setLedger([]);
  }

  if (!user || !shop) {
    return <AuthScreen mode={authMode} setMode={setAuthMode} onSubmit={handleAuth} error={error} busy={busy} />;
  }

  return (
    <div className="app-shell">
      <aside className="sidebar">
        <div className="brand">
          <span className="brand-mark">JT</span>
          <div>
            <strong>{shop.name}</strong>
            <small>{user.name}</small>
          </div>
        </div>
        <nav>
          <button className={view === 'create' ? 'active' : ''} onClick={() => setView('create')}>
            <Tag size={18} /> Create Tag
          </button>
          <button className={view === 'history' ? 'active' : ''} onClick={() => setView('history')}>
            <History size={18} /> History
          </button>
          <button className={view === 'billing' ? 'active' : ''} onClick={() => setView('billing')}>
            <BadgeIndianRupee size={18} /> Billing
          </button>
          <button className={view === 'settings' ? 'active' : ''} onClick={() => setView('settings')}>
            <Settings size={18} /> Settings
          </button>
        </nav>
        <button className="ghost-button" onClick={logout}>
          <LogOut size={18} /> Sign out
        </button>
      </aside>

      <main className="workspace">
        <section className="topbar">
          <div>
            <h1>
              {view === 'create'
                ? 'Jewellery Tag'
                : view === 'history'
                  ? 'Tag History'
                  : view === 'billing'
                    ? 'Billing'
                    : 'Shop Settings'}
            </h1>
            <p>TVS LP 46 NEO browser-print workflow</p>
          </div>
          <div className="stats-strip">
            <Metric label="Today" value={stats?.today_tags ?? 0} />
            <Metric label="Tags" value={stats?.total_tags ?? 0} />
            <Metric label="Prints" value={stats?.total_prints ?? 0} />
            <Metric label={stats?.billing.is_unlimited_active ? 'Unlimited' : 'Credits'} value={stats?.billing.is_unlimited_active ? 'Pro' : (stats?.billing.tag_credit_balance ?? 0)} />
          </div>
        </section>

        {error && <div className="error-banner">{error}</div>}

        {view === 'create' && (
          <CreateTag
            form={form}
            setForm={(next) => {
              setActiveTag(null);
              setForm(next);
            }}
            tag={draftTag}
            shop={shop}
            busy={busy}
            onSave={() => saveTag(false)}
            onSavePrint={() => saveTag(true)}
            onPrint={() => printTag()}
          />
        )}
        {view === 'history' && <HistoryView tags={tags} onReprint={(tag) => printTag(tag)} />}
        {view === 'billing' && (
          <BillingView
            billing={stats?.billing ?? null}
            plans={plans}
            ledger={ledger}
            onPlanActivated={async () => {
              await refreshData();
            }}
          />
        )}
        {view === 'settings' && <SettingsView shop={shop} onSaved={(updated) => setShop(updated)} />}
      </main>
    </div>
  );
}

function AdminPortal() {
  const [adminUser, setAdminUser] = useState<User | null>(null);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  async function hydrateAdmin() {
    try {
      const auth = await api.me();
      if (auth.user.role.toLowerCase() === 'admin') {
        setAdminUser(auth.user);
      } else {
        clearAuthToken();
      }
    } catch {
      clearAuthToken();
    }
  }

  useEffect(() => {
    hydrateAdmin();
  }, []);

  async function handleAdminLogin(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setBusy(true);
    setError('');
    const data = new FormData(event.currentTarget);
    try {
      const auth = await api.login({
        email: String(data.get('email')),
        password: String(data.get('password')),
      });
      if (auth.user.role.toLowerCase() !== 'admin') {
        clearAuthToken();
        setError('This account does not have admin access');
        return;
      }
      setAuthToken(auth.access_token);
      setAdminUser(auth.user);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not sign in');
    } finally {
      setBusy(false);
    }
  }

  function logoutAdmin() {
    clearAuthToken();
    setAdminUser(null);
  }

  if (!adminUser) {
    return (
      <main className="auth-layout admin-auth-layout">
        <section className="auth-panel">
          <div className="auth-heading">
            <span className="brand-mark">JT</span>
            <div>
              <h1>Admin Portal</h1>
              <p>Owner access for billing plans and platform settings.</p>
            </div>
          </div>
          <form onSubmit={handleAdminLogin} className="form-grid">
            <label>
              Admin email
              <input name="email" type="email" defaultValue="leo.topno@gmail.com" required />
            </label>
            <label>
              Password
              <input name="password" type="password" minLength={8} required />
            </label>
            {error && <div className="error-banner">{error}</div>}
            <button disabled={busy} className="primary-button">
              <Shield size={18} /> Sign in to admin
            </button>
          </form>
        </section>
      </main>
    );
  }

  return (
    <main className="admin-portal-shell">
      <section className="admin-portal-topbar">
        <div className="brand">
          <span className="brand-mark">JT</span>
          <div>
            <h1>Admin Portal</h1>
            <p>{adminUser.email}</p>
          </div>
        </div>
        <div className="button-row">
          <button onClick={() => window.location.assign('/')}>
            <Tag size={18} /> Shop app
          </button>
          <button onClick={logoutAdmin}>
            <LogOut size={18} /> Sign out
          </button>
        </div>
      </section>
      <AdminPlansView onSaved={async () => undefined} />
      <AdminTenantsView />
    </main>
  );
}

function AuthScreen({
  mode,
  setMode,
  onSubmit,
  error,
  busy,
}: {
  mode: 'login' | 'register';
  setMode: (mode: 'login' | 'register') => void;
  onSubmit: (event: FormEvent<HTMLFormElement>) => void;
  error: string;
  busy: boolean;
}) {
  return (
    <main className="auth-layout">
      <section className="auth-panel">
        <div className="auth-heading">
          <span className="brand-mark">JT</span>
          <div>
            <h1>Jewellery Tag Printer</h1>
            <p>Shop accounts, weight entry, preview and browser printing.</p>
          </div>
        </div>
        <form onSubmit={onSubmit} className="form-grid">
          {mode === 'register' && (
            <>
              <label>
                Shop name
                <input name="shop_name" defaultValue="Bhaskaran Jewellers" required />
              </label>
              <label>
                Tag short name
                <input name="shop_short_name" defaultValue="BHJ" required />
              </label>
              <label>
                Owner name
                <input name="owner_name" defaultValue="Owner" required />
              </label>
            </>
          )}
          <label>
            Email
            <input name="email" type="email" defaultValue="owner@example.com" required />
          </label>
          <label>
            Password
            <input name="password" type="password" defaultValue="password123" minLength={8} required />
          </label>
          {error && <div className="error-banner">{error}</div>}
          <button disabled={busy} className="primary-button">
            <UserPlus size={18} /> {mode === 'register' ? 'Create shop account' : 'Sign in'}
          </button>
        </form>
        <button className="link-button" onClick={() => setMode(mode === 'register' ? 'login' : 'register')}>
          {mode === 'register' ? 'Use an existing account' : 'Create a new shop account'}
        </button>
      </section>
    </main>
  );
}

function Metric({ label, value }: { label: string; value: number | string }) {
  return (
    <div className="metric">
      <span>{value}</span>
      <small>{label}</small>
    </div>
  );
}

function CreateTag({
  form,
  setForm,
  tag,
  shop,
  busy,
  onSave,
  onSavePrint,
  onPrint,
}: {
  form: TagForm;
  setForm: (form: TagForm) => void;
  tag: JewelleryTag;
  shop: Shop;
  busy: boolean;
  onSave: () => void;
  onSavePrint: () => void;
  onPrint: () => void;
}) {
  function patch(field: keyof TagForm, value: string | number) {
    setForm({ ...form, [field]: value });
  }

  return (
    <section className="work-grid">
      <div className="entry-panel">
        <div className="panel-title">
          <h2>Weight Entry</h2>
          <span>{tag.tag_number}</span>
        </div>
        <div className="form-grid compact">
          <label>
            Item
            <input value={form.item_name} onChange={(event) => patch('item_name', event.target.value)} />
          </label>
          <label>
            Category
            <input value={form.category} onChange={(event) => patch('category', event.target.value)} />
          </label>
          <label>
            Purity
            <input value={form.purity} onChange={(event) => patch('purity', event.target.value)} />
          </label>
          <label>
            Pieces
            <input type="number" min="1" value={form.pieces} onChange={(event) => patch('pieces', Number(event.target.value))} />
          </label>
          <label>
            Gross weight
            <input step="0.001" type="number" value={form.gross_weight} onChange={(event) => patch('gross_weight', event.target.value)} />
          </label>
          <label>
            Stone weight
            <input step="0.001" type="number" value={form.stone_weight} onChange={(event) => patch('stone_weight', event.target.value)} />
          </label>
          <label>
            Other deduction
            <input step="0.001" type="number" value={form.other_deduction} onChange={(event) => patch('other_deduction', event.target.value)} />
          </label>
          <label>
            Copies
            <input type="number" min="1" value={form.copies} onChange={(event) => patch('copies', Number(event.target.value))} />
          </label>
        </div>
        <div className="net-box">
          <span>Net weight</span>
          <strong>{calculateNet(form)} g</strong>
        </div>
        <div className="button-row">
          <button disabled={busy} onClick={onSave}>
            <Save size={18} /> Save
          </button>
          <button disabled={busy} onClick={onSavePrint} className="primary-button">
            <Printer size={18} /> Save & Print
          </button>
          <button disabled={busy} onClick={onPrint}>
            <Printer size={18} /> Test Print
          </button>
        </div>
      </div>
      <TagPreview tag={tag} shop={shop} />
    </section>
  );
}

function TagPreview({ tag, shop }: { tag: JewelleryTag; shop: Shop }) {
  return (
    <div className="preview-panel">
      <div className="panel-title">
        <h2>Actual Preview</h2>
        <span>
          {shop.tag_width_mm} x {shop.tag_height_mm} mm
        </span>
      </div>
      <div className="tag-stage">
        <PrintableTag tag={tag} shop={shop} />
      </div>
    </div>
  );
}

function PrintableTag({ tag, shop }: { tag: JewelleryTag; shop: Shop }) {
  const style = {
    width: `${shop.tag_width_mm}mm`,
    height: `${shop.tag_height_mm}mm`,
    fontSize: `${shop.font_size_pt}pt`,
    transform: `translate(${shop.horizontal_offset_mm}mm, ${shop.vertical_offset_mm}mm)`,
  };

  return (
    <article className="print-tag" style={style}>
      <div className="tag-main-body">
        <div className="tag-weight-grid" aria-label="Printable jewellery weight tag">
          <span>Grs.Wt</span>
          <span>:</span>
          <strong>{formatWeight(tag.gross_weight)}</strong>
          <span>Stn.Wt</span>
          <span>:</span>
          <strong>{formatWeight(tag.stone_weight)}</strong>
          <span>Nt.Wt</span>
          <span>:</span>
          <strong>{formatWeight(tag.net_weight)}</strong>
        </div>
      </div>
      <div className="tag-neck" aria-hidden="true" />
      <div className="tag-tail" aria-hidden="true" />
    </article>
  );
}

function HistoryView({ tags, onReprint }: { tags: JewelleryTag[]; onReprint: (tag: JewelleryTag) => void }) {
  return (
    <section className="table-panel">
      <table>
        <thead>
          <tr>
            <th>Tag</th>
            <th>Item</th>
            <th>Purity</th>
            <th>Gross</th>
            <th>Net</th>
            <th>Prints</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          {tags.map((tag) => (
            <tr key={tag.id}>
              <td>{tag.tag_number}</td>
              <td>{tag.item_name}</td>
              <td>{tag.purity}</td>
              <td>{formatWeight(tag.gross_weight)}</td>
              <td>{formatWeight(tag.net_weight)}</td>
              <td>{tag.print_count}</td>
              <td>
                <button onClick={() => onReprint(tag)}>
                  <Printer size={16} /> Reprint
                </button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </section>
  );
}

function formatDate(value: string | null) {
  if (!value) return 'Not set';
  return new Intl.DateTimeFormat('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(value));
}

function BillingView({
  billing,
  plans,
  ledger,
  onPlanActivated,
}: {
  billing: BillingSummary | null;
  plans: BillingPlan[];
  ledger: CreditLedgerEntry[];
  onPlanActivated: () => Promise<void>;
}) {
  const [busyPlanId, setBusyPlanId] = useState<number | null>(null);
  const [message, setMessage] = useState('');

  async function activatePlan(planId: number) {
    setBusyPlanId(planId);
    setMessage('');
    try {
      const purchase = await api.createPurchase(planId);
      await api.confirmPurchase(purchase.id);
      await onPlanActivated();
      setMessage('Plan activated for local testing. Razorpay capture will replace this confirmation step in production.');
    } finally {
      setBusyPlanId(null);
    }
  }

  return (
    <section className="billing-layout">
      <div className="billing-summary">
        <div>
          <span>Available credits</span>
          <strong>{billing?.is_unlimited_active ? 'Unlimited' : billing?.tag_credit_balance ?? 0}</strong>
        </div>
        <div>
          <span>Credit validity</span>
          <strong>{formatDate(billing?.credits_expire_at ?? null)}</strong>
        </div>
        <div>
          <span>Pro valid until</span>
          <strong>{formatDate(billing?.unlimited_until ?? null)}</strong>
        </div>
        <div>
          <span>Rate</span>
          <strong>Rs. {billing?.tag_price_inr ?? 2}/tag</strong>
        </div>
      </div>

      {message && <div className="success-banner">{message}</div>}

      <div className="plan-grid">
        {plans.map((plan) => (
          <article className="plan-card" key={plan.id}>
            <div className="plan-heading">
              <h2>{plan.name}</h2>
              {plan.is_unlimited && <Crown size={18} />}
            </div>
            <p>{plan.description}</p>
            <div className="plan-price">Rs. {plan.price_inr.toLocaleString('en-IN')}</div>
            <dl>
              <div>
                <dt>Tags</dt>
                <dd>{plan.is_unlimited ? 'Unlimited' : plan.tag_credits.toLocaleString('en-IN')}</dd>
              </div>
              <div>
                <dt>Validity</dt>
                <dd>{plan.validity_days} days</dd>
              </div>
            </dl>
            <button className="primary-button" disabled={busyPlanId === plan.id} onClick={() => activatePlan(plan.id)}>
              <BadgeIndianRupee size={18} /> Buy with Razorpay
            </button>
          </article>
        ))}
      </div>

      <section className="table-panel">
        <div className="panel-title">
          <h2>Credit Ledger</h2>
          <span>{ledger.length} entries</span>
        </div>
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Type</th>
              <th>Credits</th>
              <th>Balance</th>
              <th>Description</th>
            </tr>
          </thead>
          <tbody>
            {ledger.map((entry) => (
              <tr key={entry.id}>
                <td>{formatDate(entry.created_at)}</td>
                <td>{entry.entry_type}</td>
                <td>{entry.credits}</td>
                <td>{entry.balance_after}</td>
                <td>{entry.description}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </section>
    </section>
  );
}

function AdminPlansView({ onSaved }: { onSaved: () => Promise<void> }) {
  const [plans, setPlans] = useState<BillingPlan[]>([]);
  const [message, setMessage] = useState('');

  async function loadPlans() {
    setPlans(await api.adminPlans());
  }

  useEffect(() => {
    loadPlans().catch(() => setMessage('Could not load admin plans'));
  }, []);

  function patch(planId: number, field: keyof BillingPlan, value: string | number | boolean) {
    setPlans((rows) => rows.map((plan) => (plan.id === planId ? { ...plan, [field]: value } : plan)));
  }

  async function save(plan: BillingPlan) {
    const updated = await api.updateAdminPlan(plan);
    setPlans((rows) => rows.map((row) => (row.id === updated.id ? updated : row)));
    await onSaved();
    setMessage(`${updated.name} saved`);
  }

  return (
    <section className="admin-plan-list">
      {message && <div className="success-banner">{message}</div>}
      {plans.map((plan) => (
        <article className="entry-panel admin-plan" key={plan.id}>
          <div className="panel-title">
            <h2>{plan.code}</h2>
            <label className="check-row">
              <input checked={plan.is_active} type="checkbox" onChange={(event) => patch(plan.id, 'is_active', event.target.checked)} />
              Active
            </label>
          </div>
          <div className="form-grid compact">
            <label>
              Name
              <input value={plan.name} onChange={(event) => patch(plan.id, 'name', event.target.value)} />
            </label>
            <label>
              Price INR
              <input type="number" value={plan.price_inr} onChange={(event) => patch(plan.id, 'price_inr', Number(event.target.value))} />
            </label>
            <label>
              Tag credits
              <input type="number" value={plan.tag_credits} onChange={(event) => patch(plan.id, 'tag_credits', Number(event.target.value))} />
            </label>
            <label>
              Validity days
              <input type="number" value={plan.validity_days} onChange={(event) => patch(plan.id, 'validity_days', Number(event.target.value))} />
            </label>
            <label>
              Sort order
              <input type="number" value={plan.sort_order} onChange={(event) => patch(plan.id, 'sort_order', Number(event.target.value))} />
            </label>
            <label className="check-row">
              <input checked={plan.is_unlimited} type="checkbox" onChange={(event) => patch(plan.id, 'is_unlimited', event.target.checked)} />
              Unlimited plan
            </label>
            <label className="wide-field">
              Description
              <input value={plan.description} onChange={(event) => patch(plan.id, 'description', event.target.value)} />
            </label>
          </div>
          <div className="button-row settings-actions">
            <button className="primary-button" onClick={() => save(plan)}>
              <Save size={18} /> Save plan
            </button>
          </div>
        </article>
      ))}
    </section>
  );
}

const blankAdjustment: AdminCreditAdjustment = {
  credits_delta: 0,
  set_credit_balance: null,
  credits_validity_days: null,
  unlimited_validity_days: null,
  payment_reference: '',
  note: 'Admin credit adjustment',
};

function AdminTenantsView() {
  const [tenants, setTenants] = useState<AdminTenant[]>([]);
  const [selectedTenantId, setSelectedTenantId] = useState<number | null>(null);
  const [ledger, setLedger] = useState<CreditLedgerEntry[]>([]);
  const [adjustment, setAdjustment] = useState<AdminCreditAdjustment>(blankAdjustment);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

  const selectedTenant = tenants.find((tenant) => tenant.id === selectedTenantId) ?? null;

  async function loadTenants() {
    const rows = await api.adminTenants();
    setTenants(rows);
    setSelectedTenantId((current) => current ?? rows[0]?.id ?? null);
  }

  async function loadLedger(shopId: number) {
    setLedger(await api.adminTenantLedger(shopId));
  }

  useEffect(() => {
    loadTenants().catch((err) => setError(err instanceof Error ? err.message : 'Could not load tenants'));
  }, []);

  useEffect(() => {
    if (selectedTenantId) {
      loadLedger(selectedTenantId).catch((err) => setError(err instanceof Error ? err.message : 'Could not load tenant ledger'));
    }
  }, [selectedTenantId]);

  function patchAdjustment(field: keyof AdminCreditAdjustment, value: string | number | null) {
    setAdjustment({ ...adjustment, [field]: value });
  }

  function numericOrNull(value: string) {
    return value === '' ? null : Number(value);
  }

  async function saveAdjustment() {
    if (!selectedTenant) return;
    setMessage('');
    setError('');
    try {
      const updated = await api.adjustTenantCredits(selectedTenant.id, {
        ...adjustment,
        payment_reference: adjustment.payment_reference || null,
      });
      setTenants((rows) => rows.map((tenant) => (tenant.id === updated.id ? updated : tenant)));
      await loadLedger(updated.id);
      setAdjustment(blankAdjustment);
      setMessage(`${updated.name} updated`);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Could not update tenant');
    }
  }

  return (
    <section className="admin-tenant-section">
      <div className="panel-title">
        <h2>Tenant Credits</h2>
        <span>{tenants.length} tenants</span>
      </div>
      {message && <div className="success-banner">{message}</div>}
      {error && <div className="error-banner">{error}</div>}

      <div className="admin-tenant-grid">
        <section className="table-panel">
          <table>
            <thead>
              <tr>
                <th>Tenant</th>
                <th>Owner</th>
                <th>Credits</th>
                <th>Unlimited</th>
              </tr>
            </thead>
            <tbody>
              {tenants.map((tenant) => (
                <tr className={tenant.id === selectedTenantId ? 'selected-row' : ''} key={tenant.id} onClick={() => setSelectedTenantId(tenant.id)}>
                  <td>{tenant.name}</td>
                  <td>{tenant.owner_email ?? '-'}</td>
                  <td>{tenant.tag_credit_balance}</td>
                  <td>{tenant.is_unlimited_active ? formatDate(tenant.unlimited_until) : '-'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </section>

        <section className="entry-panel">
          <div className="panel-title">
            <h2>{selectedTenant?.name ?? 'Select tenant'}</h2>
            {selectedTenant && <span>{selectedTenant.short_name}</span>}
          </div>
          {selectedTenant && (
            <>
              <div className="billing-summary tenant-summary">
                <div>
                  <span>Credits</span>
                  <strong>{selectedTenant.tag_credit_balance}</strong>
                </div>
                <div>
                  <span>Credit validity</span>
                  <strong>{formatDate(selectedTenant.credits_expire_at)}</strong>
                </div>
                <div>
                  <span>Unlimited</span>
                  <strong>{selectedTenant.is_unlimited_active ? formatDate(selectedTenant.unlimited_until) : 'No'}</strong>
                </div>
              </div>

              <div className="form-grid compact">
                <label>
                  Add/remove credits
                  <input type="number" value={adjustment.credits_delta} onChange={(event) => patchAdjustment('credits_delta', Number(event.target.value))} />
                </label>
                <label>
                  Set balance
                  <input
                    type="number"
                    value={adjustment.set_credit_balance ?? ''}
                    onChange={(event) => patchAdjustment('set_credit_balance', numericOrNull(event.target.value))}
                    placeholder="Leave blank"
                  />
                </label>
                <label>
                  Credit validity days
                  <input
                    type="number"
                    value={adjustment.credits_validity_days ?? ''}
                    onChange={(event) => patchAdjustment('credits_validity_days', numericOrNull(event.target.value))}
                    placeholder="Leave unchanged"
                  />
                </label>
                <label>
                  Unlimited days
                  <input
                    type="number"
                    value={adjustment.unlimited_validity_days ?? ''}
                    onChange={(event) => patchAdjustment('unlimited_validity_days', numericOrNull(event.target.value))}
                    placeholder="0 clears Pro"
                  />
                </label>
                <label className="wide-field">
                  Payment reference
                  <input
                    value={adjustment.payment_reference ?? ''}
                    onChange={(event) => patchAdjustment('payment_reference', event.target.value)}
                    placeholder="Razorpay payment id, cash receipt, manual note"
                  />
                </label>
                <label className="wide-field">
                  Note
                  <input value={adjustment.note} onChange={(event) => patchAdjustment('note', event.target.value)} />
                </label>
              </div>
              <div className="button-row settings-actions">
                <button className="primary-button" onClick={saveAdjustment}>
                  <Save size={18} /> Update tenant credits
                </button>
              </div>
            </>
          )}
        </section>
      </div>

      <section className="table-panel">
        <div className="panel-title">
          <h2>Tenant Ledger</h2>
          <span>{ledger.length} entries</span>
        </div>
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Type</th>
              <th>Credits</th>
              <th>Balance</th>
              <th>Description</th>
            </tr>
          </thead>
          <tbody>
            {ledger.map((entry) => (
              <tr key={entry.id}>
                <td>{formatDate(entry.created_at)}</td>
                <td>{entry.entry_type}</td>
                <td>{entry.credits}</td>
                <td>{entry.balance_after}</td>
                <td>{entry.description}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </section>
    </section>
  );
}

function SettingsView({ shop, onSaved }: { shop: Shop; onSaved: (shop: Shop) => void }) {
  const [draft, setDraft] = useState(shop);
  const [message, setMessage] = useState('');

  async function save() {
    const updated = await api.updateSettings({
      short_name: draft.short_name,
      tag_prefix: draft.tag_prefix,
      tag_width_mm: draft.tag_width_mm,
      tag_height_mm: draft.tag_height_mm,
      font_size_pt: draft.font_size_pt,
      horizontal_offset_mm: draft.horizontal_offset_mm,
      vertical_offset_mm: draft.vertical_offset_mm,
      show_shop_name: draft.show_shop_name,
    });
    onSaved(updated);
    setMessage('Saved');
  }

  function patch(field: keyof Shop, value: string | boolean) {
    setDraft({ ...draft, [field]: value });
  }

  return (
    <section className="entry-panel settings-panel">
      <div className="form-grid compact">
        <label>
          Tag short name
          <input value={draft.short_name} onChange={(event) => patch('short_name', event.target.value)} />
        </label>
        <label>
          Tag prefix
          <input value={draft.tag_prefix} onChange={(event) => patch('tag_prefix', event.target.value.toUpperCase())} />
        </label>
        <label>
          Width mm
          <input value={draft.tag_width_mm} type="number" step="0.1" onChange={(event) => patch('tag_width_mm', event.target.value)} />
        </label>
        <label>
          Height mm
          <input value={draft.tag_height_mm} type="number" step="0.1" onChange={(event) => patch('tag_height_mm', event.target.value)} />
        </label>
        <label>
          Font pt
          <input value={draft.font_size_pt} type="number" step="0.1" onChange={(event) => patch('font_size_pt', event.target.value)} />
        </label>
        <label>
          X offset mm
          <input value={draft.horizontal_offset_mm} type="number" step="0.1" onChange={(event) => patch('horizontal_offset_mm', event.target.value)} />
        </label>
        <label>
          Y offset mm
          <input value={draft.vertical_offset_mm} type="number" step="0.1" onChange={(event) => patch('vertical_offset_mm', event.target.value)} />
        </label>
        <label className="check-row">
          <input checked={draft.show_shop_name} type="checkbox" onChange={(event) => patch('show_shop_name', event.target.checked)} />
          Show shop name
        </label>
      </div>
      <div className="button-row settings-actions">
        <button onClick={save} className="primary-button">
          <Save size={18} /> Save settings
        </button>
        {message && <span className="success-message">{message}</span>}
      </div>
    </section>
  );
}
