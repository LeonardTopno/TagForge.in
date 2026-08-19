export type Shop = {
  id: number;
  name: string;
  short_name: string;
  tag_prefix: string;
  next_tag_number: number;
  tag_width_mm: string;
  tag_height_mm: string;
  font_size_pt: string;
  horizontal_offset_mm: string;
  vertical_offset_mm: string;
  show_shop_name: boolean;
};

export type User = {
  id: number;
  shop_id: number;
  name: string;
  email: string;
  role: string;
};

export type AuthResponse = {
  access_token: string;
  token_type: string;
  user: User;
  shop: Shop;
};

export type JewelleryTag = {
  id: number;
  shop_id: number;
  tag_number: string;
  item_name: string;
  category: string | null;
  purity: string | null;
  pieces: number;
  gross_weight: string;
  stone_weight: string;
  other_deduction: string;
  net_weight: string;
  copies: number;
  status: string;
  print_count: number;
  created_at: string;
};

export type DashboardStats = {
  total_tags: number;
  today_tags: number;
  total_prints: number;
  recent_tags: JewelleryTag[];
  billing: BillingSummary;
};

export type BillingSummary = {
  tag_credit_balance: number;
  credits_expire_at: string | null;
  unlimited_until: string | null;
  is_unlimited_active: boolean;
  tag_price_inr: number;
};

export type BillingPlan = {
  id: number;
  code: string;
  name: string;
  description: string;
  price_inr: number;
  tag_credits: number;
  validity_days: number;
  is_unlimited: boolean;
  is_active: boolean;
  sort_order: number;
};

export type PlanPurchase = {
  id: number;
  shop_id: number;
  plan_id: number;
  amount_inr: number;
  tag_credits: number;
  validity_days: number;
  is_unlimited: boolean;
  status: string;
  razorpay_order_id: string | null;
  razorpay_payment_id: string | null;
  created_at: string;
  paid_at: string | null;
};

export type CreditLedgerEntry = {
  id: number;
  entry_type: string;
  credits: number;
  balance_after: number;
  description: string;
  created_at: string;
};

export type AdminTenant = {
  id: number;
  name: string;
  short_name: string;
  tag_credit_balance: number;
  credits_expire_at: string | null;
  unlimited_until: string | null;
  is_unlimited_active: boolean;
  created_at: string;
  owner_email: string | null;
  owner_name: string | null;
};

export type AdminCreditAdjustment = {
  credits_delta: number;
  set_credit_balance: number | null;
  credits_validity_days: number | null;
  unlimited_validity_days: number | null;
  payment_reference: string | null;
  note: string;
};

export type TagForm = {
  item_name: string;
  category: string;
  purity: string;
  pieces: number;
  gross_weight: string;
  stone_weight: string;
  other_deduction: string;
  copies: number;
};
