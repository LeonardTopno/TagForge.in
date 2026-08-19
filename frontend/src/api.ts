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
  const response = await fetch(`${API_BASE_URL}${path}`, {
    ...options,
    headers: {
      'Content-Type': 'application/json',
      ...(authToken ? { Authorization: `Bearer ${authToken}` } : {}),
      ...options.headers,
    },
  });

  if (!response.ok) {
    const error = await response.json().catch(() => ({ detail: 'Request failed' }));
    throw new Error(error.detail ?? 'Request failed');
  }

  return response.json();
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
  updateSettings(payload: Omit<Shop, 'id' | 'name' | 'next_tag_number'>) {
    return request<Shop>('/settings', { method: 'PUT', body: JSON.stringify(payload) });
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
