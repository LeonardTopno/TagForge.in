from datetime import datetime
from decimal import Decimal
from enum import StrEnum

from sqlalchemy import Boolean, DateTime, Enum, ForeignKey, Integer, Numeric, String, Text, UniqueConstraint, func
from sqlalchemy.orm import Mapped, mapped_column, relationship

from app.database import Base


class UserRole(StrEnum):
    OWNER = "owner"
    OPERATOR = "operator"
    ADMIN = "admin"


class TagStatus(StrEnum):
    ACTIVE = "active"
    CANCELLED = "cancelled"


class PrintStatus(StrEnum):
    REQUESTED = "requested"
    PRINTED = "printed"


class PurchaseStatus(StrEnum):
    PENDING = "pending"
    PAID = "paid"
    FAILED = "failed"


class LedgerEntryType(StrEnum):
    CREDIT = "credit"
    DEBIT = "debit"


class Shop(Base):
    __tablename__ = "shops"

    id: Mapped[int] = mapped_column(primary_key=True, index=True)
    name: Mapped[str] = mapped_column(String(160), nullable=False)
    address: Mapped[str] = mapped_column(String(255), default="", nullable=False)
    phone_number: Mapped[str] = mapped_column(String(20), default="", nullable=False)
    gst_no: Mapped[str] = mapped_column(String(20), default="", nullable=False)
    logo_path: Mapped[str | None] = mapped_column(String(255))
    short_name: Mapped[str] = mapped_column(String(40), nullable=False)
    tag_prefix: Mapped[str] = mapped_column(String(12), default="T", nullable=False)
    next_tag_number: Mapped[int] = mapped_column(Integer, default=1, nullable=False)
    tag_width_mm: Mapped[Decimal] = mapped_column(Numeric(6, 2), default=Decimal("80.00"), nullable=False)
    tag_height_mm: Mapped[Decimal] = mapped_column(Numeric(6, 2), default=Decimal("13.00"), nullable=False)
    font_size_pt: Mapped[Decimal] = mapped_column(Numeric(5, 2), default=Decimal("8.00"), nullable=False)
    horizontal_offset_mm: Mapped[Decimal] = mapped_column(Numeric(6, 2), default=Decimal("0.00"), nullable=False)
    vertical_offset_mm: Mapped[Decimal] = mapped_column(Numeric(6, 2), default=Decimal("0.00"), nullable=False)
    show_shop_name: Mapped[bool] = mapped_column(Boolean, default=True, nullable=False)
    tag_credit_balance: Mapped[int] = mapped_column(Integer, default=15, nullable=False)
    credits_expire_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True))
    unlimited_until: Mapped[datetime | None] = mapped_column(DateTime(timezone=True))
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now(), nullable=False)

    users: Mapped[list["User"]] = relationship(back_populates="shop")
    tags: Mapped[list["JewelleryTag"]] = relationship(back_populates="shop")
    purchases: Mapped[list["PlanPurchase"]] = relationship(back_populates="shop")
    ledger_entries: Mapped[list["CreditLedgerEntry"]] = relationship(back_populates="shop")
    catalogue_items: Mapped[list["ShopItem"]] = relationship(back_populates="shop")


class User(Base):
    __tablename__ = "users"

    id: Mapped[int] = mapped_column(primary_key=True, index=True)
    shop_id: Mapped[int] = mapped_column(ForeignKey("shops.id"), nullable=False, index=True)
    name: Mapped[str] = mapped_column(String(120), nullable=False)
    email: Mapped[str] = mapped_column(String(255), unique=True, index=True, nullable=False)
    password_hash: Mapped[str] = mapped_column(String(255), nullable=False)
    role: Mapped[UserRole] = mapped_column(Enum(UserRole), default=UserRole.OWNER, nullable=False)
    is_active: Mapped[bool] = mapped_column(Boolean, default=True, nullable=False)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now(), nullable=False)

    shop: Mapped[Shop] = relationship(back_populates="users")
    tags: Mapped[list["JewelleryTag"]] = relationship(back_populates="created_by_user")


class JewelleryTag(Base):
    __tablename__ = "jewellery_tags"
    __table_args__ = (UniqueConstraint("shop_id", "tag_number", name="uq_shop_tag_number"),)

    id: Mapped[int] = mapped_column(primary_key=True, index=True)
    shop_id: Mapped[int] = mapped_column(ForeignKey("shops.id"), nullable=False, index=True)
    created_by: Mapped[int] = mapped_column(ForeignKey("users.id"), nullable=False)
    tag_number: Mapped[str] = mapped_column(String(50), nullable=False)
    item_name: Mapped[str] = mapped_column(String(100), nullable=False)
    category: Mapped[str | None] = mapped_column(String(60))
    purity: Mapped[str | None] = mapped_column(String(30))
    pieces: Mapped[int] = mapped_column(Integer, default=1, nullable=False)
    gross_weight: Mapped[Decimal] = mapped_column(Numeric(10, 3), nullable=False)
    stone_weight: Mapped[Decimal] = mapped_column(Numeric(10, 3), default=Decimal("0.000"), nullable=False)
    other_deduction: Mapped[Decimal] = mapped_column(Numeric(10, 3), default=Decimal("0.000"), nullable=False)
    net_weight: Mapped[Decimal] = mapped_column(Numeric(10, 3), nullable=False)
    copies: Mapped[int] = mapped_column(Integer, default=1, nullable=False)
    status: Mapped[TagStatus] = mapped_column(Enum(TagStatus), default=TagStatus.ACTIVE, nullable=False)
    print_count: Mapped[int] = mapped_column(Integer, default=0, nullable=False)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    updated_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False)

    shop: Mapped[Shop] = relationship(back_populates="tags")
    created_by_user: Mapped[User] = relationship(back_populates="tags")
    print_logs: Mapped[list["PrintLog"]] = relationship(back_populates="tag")


class PrintLog(Base):
    __tablename__ = "print_logs"

    id: Mapped[int] = mapped_column(primary_key=True, index=True)
    shop_id: Mapped[int] = mapped_column(ForeignKey("shops.id"), nullable=False, index=True)
    jewellery_tag_id: Mapped[int] = mapped_column(ForeignKey("jewellery_tags.id"), nullable=False)
    printed_by: Mapped[int] = mapped_column(ForeignKey("users.id"), nullable=False)
    copies: Mapped[int] = mapped_column(Integer, default=1, nullable=False)
    print_status: Mapped[PrintStatus] = mapped_column(Enum(PrintStatus), default=PrintStatus.PRINTED, nullable=False)
    printed_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now(), nullable=False)

    tag: Mapped[JewelleryTag] = relationship(back_populates="print_logs")


class BillingPlan(Base):
    __tablename__ = "billing_plans"

    id: Mapped[int] = mapped_column(primary_key=True, index=True)
    code: Mapped[str] = mapped_column(String(40), unique=True, nullable=False)
    name: Mapped[str] = mapped_column(String(80), nullable=False)
    description: Mapped[str] = mapped_column(String(240), nullable=False)
    price_inr: Mapped[int] = mapped_column(Integer, nullable=False)
    tag_credits: Mapped[int] = mapped_column(Integer, default=0, nullable=False)
    validity_days: Mapped[int] = mapped_column(Integer, nullable=False)
    is_unlimited: Mapped[bool] = mapped_column(Boolean, default=False, nullable=False)
    is_active: Mapped[bool] = mapped_column(Boolean, default=True, nullable=False)
    sort_order: Mapped[int] = mapped_column(Integer, default=0, nullable=False)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    updated_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now(), onupdate=func.now(), nullable=False)

    purchases: Mapped[list["PlanPurchase"]] = relationship(back_populates="plan")


class PlanPurchase(Base):
    __tablename__ = "plan_purchases"

    id: Mapped[int] = mapped_column(primary_key=True, index=True)
    shop_id: Mapped[int] = mapped_column(ForeignKey("shops.id"), nullable=False, index=True)
    plan_id: Mapped[int] = mapped_column(ForeignKey("billing_plans.id"), nullable=False)
    amount_inr: Mapped[int] = mapped_column(Integer, nullable=False)
    tag_credits: Mapped[int] = mapped_column(Integer, nullable=False)
    validity_days: Mapped[int] = mapped_column(Integer, nullable=False)
    is_unlimited: Mapped[bool] = mapped_column(Boolean, default=False, nullable=False)
    status: Mapped[PurchaseStatus] = mapped_column(Enum(PurchaseStatus), default=PurchaseStatus.PENDING, nullable=False)
    razorpay_order_id: Mapped[str | None] = mapped_column(String(120))
    razorpay_payment_id: Mapped[str | None] = mapped_column(String(120))
    notes: Mapped[str | None] = mapped_column(Text)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now(), nullable=False)
    paid_at: Mapped[datetime | None] = mapped_column(DateTime(timezone=True))

    shop: Mapped[Shop] = relationship(back_populates="purchases")
    plan: Mapped[BillingPlan] = relationship(back_populates="purchases")


class CreditLedgerEntry(Base):
    __tablename__ = "credit_ledger_entries"

    id: Mapped[int] = mapped_column(primary_key=True, index=True)
    shop_id: Mapped[int] = mapped_column(ForeignKey("shops.id"), nullable=False, index=True)
    entry_type: Mapped[LedgerEntryType] = mapped_column(Enum(LedgerEntryType), nullable=False)
    credits: Mapped[int] = mapped_column(Integer, nullable=False)
    balance_after: Mapped[int] = mapped_column(Integer, nullable=False)
    description: Mapped[str] = mapped_column(String(240), nullable=False)
    jewellery_tag_id: Mapped[int | None] = mapped_column(ForeignKey("jewellery_tags.id"))
    purchase_id: Mapped[int | None] = mapped_column(ForeignKey("plan_purchases.id"))
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now(), nullable=False)

    shop: Mapped[Shop] = relationship(back_populates="ledger_entries")


class ShopItem(Base):
    __tablename__ = "shop_items"
    __table_args__ = (UniqueConstraint("shop_id", "name", name="uq_shop_item_name"),)

    id: Mapped[int] = mapped_column(primary_key=True, index=True)
    shop_id: Mapped[int] = mapped_column(ForeignKey("shops.id"), nullable=False, index=True)
    name: Mapped[str] = mapped_column(String(100), nullable=False)
    sort_order: Mapped[int] = mapped_column(Integer, default=0, nullable=False)
    created_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), server_default=func.now(), nullable=False)

    shop: Mapped[Shop] = relationship(back_populates="catalogue_items")
