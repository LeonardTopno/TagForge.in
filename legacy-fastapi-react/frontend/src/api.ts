import type {
  AdminCreditAdjustment,
  AdminTenant,
  AuthResponse,
  BillingPlan,
  BillingSummary,
  CreditLedgerEntry,
  DashboardStats,
  JewelleryTag,
  PlanPurchase,
  Shop,
  ShopItem,
  TagForm,
} from './types';

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8000';

let authToken = localStorage.getItem('authToken') ?? '';

export function setAuthToken(token: string) {
  authToken = token;
  localStorage.setItem('authToken', token);
}

export function clearAuthToken() {
  authToken = '';
  localStorage.removeItem('authToken');
}

async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
  const headers = new Headers(options.headers);
  if (authToken) {
    headers.set('Authorization', `Bearer ${authToken}`);
  }
  if (!(options.body instanceof FormData) && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json');
  }

  const response = await fetch(`${API_BASE_URL}${path}`, {
    ...options,
    headers,
  });

  if (!response.ok) {
    const error = await response.json().catch(() => ({ detail: 'Request failed' }));
    throw new Error(error.detail ?? 'Request failed');
  }

  if (response.status === 204) {
    return undefined as T;
  }

  return response.json();
}

export function shopLogoSrc(logoUrl?: string | null) {
  if (!logoUrl) return '';
  if (logoUrl.startsWith('blob:') || logoUrl.startsWith('http://') || logoUrl.startsWith('https://')) {
    return logoUrl;
  }
  return `${API_BASE_URL}${logoUrl}`;
}

export const api = {
  register(payload: {
    shop_name: string;
    shop_short_name: string;
    owner_name: string;
    email: string;
    password: string;
  }) {
    return request<AuthResponse>('/auth/register', { method: 'POST', body: JSON.stringify(payload) });
  },
  login(payload: { email: string; password: string }) {
    return request<AuthResponse>('/auth/login', { method: 'POST', body: JSON.stringify(payload) });
  },
  me() {
    return request<AuthResponse>('/me');
  },
  dashboard() {
    return request<DashboardStats>('/dashboard');
  },
  listTags(search = '') {
    const params = search ? `?search=${encodeURIComponent(search)}` : '';
    return request<JewelleryTag[]>(`/tags${params}`);
  },
  createTag(payload: TagForm) {
    return request<JewelleryTag>('/tags', { method: 'POST', body: JSON.stringify(payload) });
  },
  markPrinted(tagId: number) {
    return request<JewelleryTag>(`/tags/${tagId}/print`, { method: 'POST' });
  },
  settings() {
    return request<Shop>('/settings');
  },
  updateSettings(payload: Omit<Shop, 'id' | 'next_tag_number' | 'logo_url'>) {
    return request<Shop>('/settings', { method: 'PUT', body: JSON.stringify(payload) });
  },
  uploadShopLogo(file: File) {
    const body = new FormData();
    body.append('file', file);
    return request<Shop>('/settings/logo', { method: 'POST', body });
  },
  deleteShopLogo() {
    return request<Shop>('/settings/logo', { method: 'DELETE' });
  },
  listShopItems() {
    return request<ShopItem[]>('/settings/items');
  },
  createShopItem(name: string) {
    return request<ShopItem>('/settings/items', { method: 'POST', body: JSON.stringify({ name }) });
  },
  updateShopItem(itemId: number, name: string) {
    return request<ShopItem>(`/settings/items/${itemId}`, { method: 'PUT', body: JSON.stringify({ name }) });
  },
  deleteShopItem(itemId: number) {
    return request<void>(`/settings/items/${itemId}`, { method: 'DELETE' });
  },
  billingSummary() {
    return request<BillingSummary>('/billing/summary');
  },
  billingPlans() {
    return request<BillingPlan[]>('/billing/plans');
  },
  billingLedger() {
    return request<CreditLedgerEntry[]>('/billing/ledger');
  },
  createPurchase(planId: number) {
    return request<PlanPurchase>('/billing/purchases', { method: 'POST', body: JSON.stringify({ plan_id: planId }) });
  },
  confirmPurchase(purchaseId: number) {
    return request<BillingSummary>(`/billing/purchases/${purchaseId}/confirm`, { method: 'POST' });
  },
  adminPlans() {
    return request<BillingPlan[]>('/admin/plans');
  },
  updateAdminPlan(plan: BillingPlan) {
    return request<BillingPlan>(`/admin/plans/${plan.id}`, { method: 'PUT', body: JSON.stringify(plan) });
  },
  adminTenants() {
    return request<AdminTenant[]>('/admin/tenants');
  },
  adminTenantLedger(shopId: number) {
    return request<CreditLedgerEntry[]>(`/admin/tenants/${shopId}/ledger`);
  },
  adjustTenantCredits(shopId: number, payload: AdminCreditAdjustment) {
    return request<AdminTenant>(`/admin/tenants/${shopId}/credit-adjustments`, { method: 'POST', body: JSON.stringify(payload) });
  },
};
