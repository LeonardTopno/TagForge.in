from datetime import datetime
from decimal import Decimal

from pydantic import BaseModel, EmailStr, Field


class RegisterRequest(BaseModel):
    shop_name: str = Field(min_length=2, max_length=160)
    shop_short_name: str = Field(min_length=2, max_length=40)
    owner_name: str = Field(min_length=2, max_length=120)
    email: EmailStr
    password: str = Field(min_length=8, max_length=128)


class LoginRequest(BaseModel):
    email: EmailStr
    password: str


class ShopResponse(BaseModel):
    id: int
    name: str
    short_name: str
    tag_prefix: str
    next_tag_number: int
    tag_width_mm: Decimal
    tag_height_mm: Decimal
    font_size_pt: Decimal
    horizontal_offset_mm: Decimal
    vertical_offset_mm: Decimal
    show_shop_name: bool
    tag_credit_balance: int
    credits_expire_at: datetime | None
    unlimited_until: datetime | None

    class Config:
        from_attributes = True


class UserResponse(BaseModel):
    id: int
    shop_id: int
    name: str
    email: EmailStr
    role: str

    class Config:
        from_attributes = True


class TokenResponse(BaseModel):
    access_token: str
    token_type: str = "bearer"
    user: UserResponse
    shop: ShopResponse


class ShopSettingsUpdate(BaseModel):
    short_name: str = Field(min_length=2, max_length=40)
    tag_prefix: str = Field(min_length=1, max_length=12)
    tag_width_mm: Decimal = Field(gt=Decimal("20.00"), le=Decimal("100.00"))
    tag_height_mm: Decimal = Field(gt=Decimal("8.00"), le=Decimal("50.00"))
    font_size_pt: Decimal = Field(gt=Decimal("4.00"), le=Decimal("14.00"))
    horizontal_offset_mm: Decimal = Field(ge=Decimal("-10.00"), le=Decimal("10.00"))
    vertical_offset_mm: Decimal = Field(ge=Decimal("-10.00"), le=Decimal("10.00"))
    show_shop_name: bool


class TagCreate(BaseModel):
    item_name: str = Field(min_length=1, max_length=100)
    category: str | None = Field(default=None, max_length=60)
    purity: str | None = Field(default=None, max_length=30)
    pieces: int = Field(default=1, ge=1, le=999)
    gross_weight: Decimal = Field(ge=Decimal("0.000"), decimal_places=3)
    stone_weight: Decimal = Field(default=Decimal("0.000"), ge=Decimal("0.000"), decimal_places=3)
    other_deduction: Decimal = Field(default=Decimal("0.000"), ge=Decimal("0.000"), decimal_places=3)
    copies: int = Field(default=1, ge=1, le=99)


class TagResponse(BaseModel):
    id: int
    shop_id: int
    tag_number: str
    item_name: str
    category: str | None
    purity: str | None
    pieces: int
    gross_weight: Decimal
    stone_weight: Decimal
    other_deduction: Decimal
    net_weight: Decimal
    copies: int
    status: str
    print_count: int
    created_at: datetime

    class Config:
        from_attributes = True


class DashboardStats(BaseModel):
    total_tags: int
    today_tags: int
    total_prints: int
    recent_tags: list[TagResponse]
    billing: "BillingSummary"


class BillingSummary(BaseModel):
    tag_credit_balance: int
    credits_expire_at: datetime | None
    unlimited_until: datetime | None
    is_unlimited_active: bool
    tag_price_inr: int = 2


class BillingPlanResponse(BaseModel):
    id: int
    code: str
    name: str
    description: str
    price_inr: int
    tag_credits: int
    validity_days: int
    is_unlimited: bool
    is_active: bool
    sort_order: int

    class Config:
        from_attributes = True


class BillingPlanUpdate(BaseModel):
    name: str = Field(min_length=2, max_length=80)
    description: str = Field(min_length=2, max_length=240)
    price_inr: int = Field(ge=1)
    tag_credits: int = Field(ge=0)
    validity_days: int = Field(ge=1, le=3660)
    is_unlimited: bool
    is_active: bool
    sort_order: int = Field(ge=0)


class PurchaseCreate(BaseModel):
    plan_id: int


class PlanPurchaseResponse(BaseModel):
    id: int
    shop_id: int
    plan_id: int
    amount_inr: int
    tag_credits: int
    validity_days: int
    is_unlimited: bool
    status: str
    razorpay_order_id: str | None
    razorpay_payment_id: str | None
    created_at: datetime
    paid_at: datetime | None

    class Config:
        from_attributes = True


class CreditLedgerResponse(BaseModel):
    id: int
    entry_type: str
    credits: int
    balance_after: int
    description: str
    created_at: datetime

    class Config:
        from_attributes = True


class AdminTenantResponse(BaseModel):
    id: int
    name: str
    short_name: str
    tag_credit_balance: int
    credits_expire_at: datetime | None
    unlimited_until: datetime | None
    is_unlimited_active: bool
    created_at: datetime
    owner_email: EmailStr | None = None
    owner_name: str | None = None


class AdminCreditAdjustment(BaseModel):
    credits_delta: int = Field(default=0, ge=-100000, le=100000)
    set_credit_balance: int | None = Field(default=None, ge=0, le=1000000)
    credits_validity_days: int | None = Field(default=None, ge=1, le=3660)
    unlimited_validity_days: int | None = Field(default=None, ge=0, le=3660)
    payment_reference: str | None = Field(default=None, max_length=120)
    note: str = Field(min_length=3, max_length=240)
