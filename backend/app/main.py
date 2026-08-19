from contextlib import asynccontextmanager
from datetime import UTC, datetime, timedelta
from decimal import Decimal
from typing import Annotated

from fastapi import Depends, FastAPI, HTTPException, Query, status
from fastapi.middleware.cors import CORSMiddleware
from sqlalchemy import func, select
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.orm import selectinload

from app.config import get_settings
from app.database import SessionLocal, create_database, get_db
from app.deps import get_current_user
from app.models import BillingPlan, CreditLedgerEntry, JewelleryTag, LedgerEntryType, PlanPurchase, PrintLog, PurchaseStatus, Shop, User, UserRole
from app.schemas import (
    AdminCreditAdjustment,
    AdminTenantResponse,
    BillingPlanResponse,
    BillingPlanUpdate,
    BillingSummary,
    CreditLedgerResponse,
    DashboardStats,
    LoginRequest,
    PlanPurchaseResponse,
    PurchaseCreate,
    RegisterRequest,
    ShopResponse,
    ShopSettingsUpdate,
    TagCreate,
    TagResponse,
    TokenResponse,
    UserResponse,
)
from app.security import create_access_token, hash_password, verify_password

TAG_PRICE_INR = 2
FREE_REGISTRATION_CREDITS = 15


@asynccontextmanager
async def lifespan(_: FastAPI):
    await create_database()
    async with SessionLocal() as db:
        await seed_default_plans(db)
    yield


settings = get_settings()
app = FastAPI(title=settings.app_name, version="0.1.0", lifespan=lifespan)

app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.cors_origins,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


def serialize_auth(user: User) -> TokenResponse:
    return TokenResponse(
        access_token=create_access_token(str(user.id)),
        user=UserResponse.model_validate(user),
        shop=ShopResponse.model_validate(user.shop),
    )


def next_tag_number(shop: Shop) -> str:
    return f"{shop.tag_prefix}{shop.next_tag_number:06d}"


def calculate_net_weight(tag: TagCreate) -> Decimal:
    net_weight = tag.gross_weight - tag.stone_weight - tag.other_deduction
    if net_weight < Decimal("0.000"):
        raise HTTPException(status_code=status.HTTP_422_UNPROCESSABLE_ENTITY, detail="Net weight cannot be negative")
    return net_weight.quantize(Decimal("0.001"))


def now_utc() -> datetime:
    return datetime.now(UTC)


def as_utc(value: datetime | None) -> datetime | None:
    if value is None:
        return None
    if value.tzinfo is None:
        return value.replace(tzinfo=UTC)
    return value.astimezone(UTC)


def is_unlimited_active(shop: Shop) -> bool:
    unlimited_until = as_utc(shop.unlimited_until)
    return bool(unlimited_until and unlimited_until > now_utc())


def billing_summary(shop: Shop) -> BillingSummary:
    return BillingSummary(
        tag_credit_balance=shop.tag_credit_balance,
        credits_expire_at=shop.credits_expire_at,
        unlimited_until=shop.unlimited_until,
        is_unlimited_active=is_unlimited_active(shop),
        tag_price_inr=TAG_PRICE_INR,
    )


async def seed_default_plans(db: AsyncSession) -> None:
    defaults = [
        {
            "code": "starter",
            "name": "Starter",
            "description": "500 tag credits for focused shop usage.",
            "price_inr": 1000,
            "tag_credits": 500,
            "validity_days": 90,
            "is_unlimited": False,
            "sort_order": 1,
        },
        {
            "code": "growth",
            "name": "Growth",
            "description": "750 tag credits for higher monthly printing.",
            "price_inr": 1500,
            "tag_credits": 750,
            "validity_days": 90,
            "is_unlimited": False,
            "sort_order": 2,
        },
        {
            "code": "pro",
            "name": "Pro Annual",
            "description": "Unlimited tag printing for one year.",
            "price_inr": 10000,
            "tag_credits": 0,
            "validity_days": 365,
            "is_unlimited": True,
            "sort_order": 3,
        },
    ]
    for plan_data in defaults:
        existing = await db.scalar(select(BillingPlan).where(BillingPlan.code == plan_data["code"]))
        if not existing:
            db.add(BillingPlan(**plan_data))
    await db.commit()


def require_admin(current_user: User) -> None:
    if current_user.role != UserRole.ADMIN:
        raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Admin access required")


def ensure_credits_are_current(shop: Shop) -> None:
    credits_expire_at = as_utc(shop.credits_expire_at)
    if credits_expire_at and credits_expire_at <= now_utc():
        shop.tag_credit_balance = 0


def charge_tag_credits(shop: Shop, credits: int, description: str, tag_id: int | None = None) -> CreditLedgerEntry | None:
    if credits <= 0 or is_unlimited_active(shop):
        return None

    ensure_credits_are_current(shop)
    if shop.tag_credit_balance < credits:
        raise HTTPException(
            status_code=status.HTTP_402_PAYMENT_REQUIRED,
            detail=f"Not enough tag credits. Required {credits}, available {shop.tag_credit_balance}.",
        )

    shop.tag_credit_balance -= credits
    return CreditLedgerEntry(
        shop_id=shop.id,
        entry_type=LedgerEntryType.DEBIT,
        credits=credits,
        balance_after=shop.tag_credit_balance,
        description=description,
        jewellery_tag_id=tag_id,
    )


def grant_purchase_to_shop(shop: Shop, purchase: PlanPurchase) -> CreditLedgerEntry | None:
    paid_until = now_utc() + timedelta(days=purchase.validity_days)
    if purchase.is_unlimited:
        existing = as_utc(shop.unlimited_until)
        shop.unlimited_until = max(existing or paid_until, paid_until)
        return None

    ensure_credits_are_current(shop)
    shop.tag_credit_balance += purchase.tag_credits
    existing_expiry = as_utc(shop.credits_expire_at)
    shop.credits_expire_at = max(existing_expiry or paid_until, paid_until)
    return CreditLedgerEntry(
        shop_id=shop.id,
        entry_type=LedgerEntryType.CREDIT,
        credits=purchase.tag_credits,
        balance_after=shop.tag_credit_balance,
        description=f"{purchase.tag_credits} credits purchased",
        purchase_id=purchase.id,
    )


async def owner_for_shop(db: AsyncSession, shop_id: int) -> User | None:
    return await db.scalar(select(User).where(User.shop_id == shop_id, User.role == UserRole.OWNER).order_by(User.id))


async def admin_tenant_response(db: AsyncSession, shop: Shop) -> AdminTenantResponse:
    owner = await owner_for_shop(db, shop.id)
    return AdminTenantResponse(
        id=shop.id,
        name=shop.name,
        short_name=shop.short_name,
        tag_credit_balance=shop.tag_credit_balance,
        credits_expire_at=shop.credits_expire_at,
        unlimited_until=shop.unlimited_until,
        is_unlimited_active=is_unlimited_active(shop),
        created_at=shop.created_at,
        owner_email=owner.email if owner else None,
        owner_name=owner.name if owner else None,
    )


@app.get("/health")
async def health() -> dict[str, str]:
    return {"status": "ok"}


@app.post("/auth/register", response_model=TokenResponse, status_code=status.HTTP_201_CREATED)
async def register(payload: RegisterRequest, db: Annotated[AsyncSession, Depends(get_db)]) -> TokenResponse:
    existing = await db.scalar(select(User).where(User.email == payload.email.lower()))
    if existing:
        raise HTTPException(status_code=status.HTTP_409_CONFLICT, detail="Email is already registered")

    shop = Shop(name=payload.shop_name, short_name=payload.shop_short_name)
    user = User(
        shop=shop,
        name=payload.owner_name,
        email=payload.email.lower(),
        password_hash=hash_password(payload.password),
        role=UserRole.OWNER,
    )
    db.add(user)
    await db.flush()
    db.add(
        CreditLedgerEntry(
            shop_id=shop.id,
            entry_type=LedgerEntryType.CREDIT,
            credits=FREE_REGISTRATION_CREDITS,
            balance_after=FREE_REGISTRATION_CREDITS,
            description="Free registration credits",
        )
    )
    await db.commit()
    await db.refresh(user, attribute_names=["shop"])
    return serialize_auth(user)


@app.post("/auth/login", response_model=TokenResponse)
async def login(payload: LoginRequest, db: Annotated[AsyncSession, Depends(get_db)]) -> TokenResponse:
    result = await db.execute(select(User).options(selectinload(User.shop)).where(User.email == payload.email.lower()))
    user = result.scalar_one_or_none()
    if not user or not verify_password(payload.password, user.password_hash):
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Invalid email or password")
    return serialize_auth(user)


@app.get("/me", response_model=TokenResponse)
async def me(
    current_user: Annotated[User, Depends(get_current_user)],
    db: Annotated[AsyncSession, Depends(get_db)],
) -> TokenResponse:
    result = await db.execute(select(User).options(selectinload(User.shop)).where(User.id == current_user.id))
    user = result.scalar_one()
    return serialize_auth(user)


@app.get("/dashboard", response_model=DashboardStats)
async def dashboard(
    current_user: Annotated[User, Depends(get_current_user)],
    db: Annotated[AsyncSession, Depends(get_db)],
) -> DashboardStats:
    today = datetime.now(UTC).date()
    total_tags = await db.scalar(select(func.count()).select_from(JewelleryTag).where(JewelleryTag.shop_id == current_user.shop_id))
    today_tags = await db.scalar(
        select(func.count())
        .select_from(JewelleryTag)
        .where(JewelleryTag.shop_id == current_user.shop_id, func.date(JewelleryTag.created_at) == today)
    )
    total_prints = await db.scalar(select(func.coalesce(func.sum(JewelleryTag.print_count), 0)).where(JewelleryTag.shop_id == current_user.shop_id))
    recent = await db.scalars(
        select(JewelleryTag)
        .where(JewelleryTag.shop_id == current_user.shop_id)
        .order_by(JewelleryTag.created_at.desc())
        .limit(5)
    )
    return DashboardStats(
        total_tags=total_tags or 0,
        today_tags=today_tags or 0,
        total_prints=total_prints or 0,
        recent_tags=list(recent),
        billing=billing_summary(current_user.shop),
    )


@app.get("/tags", response_model=list[TagResponse])
async def list_tags(
    current_user: Annotated[User, Depends(get_current_user)],
    db: Annotated[AsyncSession, Depends(get_db)],
    search: str | None = Query(default=None, max_length=80),
) -> list[JewelleryTag]:
    query = select(JewelleryTag).where(JewelleryTag.shop_id == current_user.shop_id)
    if search:
        like_search = f"%{search}%"
        query = query.where((JewelleryTag.tag_number.ilike(like_search)) | (JewelleryTag.item_name.ilike(like_search)))
    result = await db.scalars(query.order_by(JewelleryTag.created_at.desc()).limit(100))
    return list(result)


@app.post("/tags", response_model=TagResponse, status_code=status.HTTP_201_CREATED)
async def create_tag(
    payload: TagCreate,
    current_user: Annotated[User, Depends(get_current_user)],
    db: Annotated[AsyncSession, Depends(get_db)],
) -> JewelleryTag:
    shop = await db.get(Shop, current_user.shop_id, with_for_update=True)
    if not shop:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Shop not found")

    tag = JewelleryTag(
        shop_id=current_user.shop_id,
        created_by=current_user.id,
        tag_number=next_tag_number(shop),
        item_name=payload.item_name,
        category=payload.category,
        purity=payload.purity,
        pieces=payload.pieces,
        gross_weight=payload.gross_weight,
        stone_weight=payload.stone_weight,
        other_deduction=payload.other_deduction,
        net_weight=calculate_net_weight(payload),
        copies=payload.copies,
    )
    shop.next_tag_number += 1
    db.add(tag)
    await db.flush()
    ledger_entry = charge_tag_credits(shop, payload.copies, f"Created {payload.copies} tag copy/copies for {tag.tag_number}", tag.id)
    if ledger_entry:
        db.add(ledger_entry)
    await db.commit()
    await db.refresh(tag)
    return tag


@app.post("/tags/{tag_id}/print", response_model=TagResponse)
async def mark_printed(
    tag_id: int,
    current_user: Annotated[User, Depends(get_current_user)],
    db: Annotated[AsyncSession, Depends(get_db)],
) -> JewelleryTag:
    tag = await db.get(JewelleryTag, tag_id)
    if not tag or tag.shop_id != current_user.shop_id:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Tag not found")

    shop = await db.get(Shop, current_user.shop_id, with_for_update=True)
    if not shop:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Shop not found")

    if tag.print_count > 0:
        ledger_entry = charge_tag_credits(shop, tag.copies, f"Reprinted {tag.copies} tag copy/copies for {tag.tag_number}", tag.id)
        if ledger_entry:
            db.add(ledger_entry)

    tag.print_count += tag.copies
    db.add(PrintLog(shop_id=current_user.shop_id, jewellery_tag_id=tag.id, printed_by=current_user.id, copies=tag.copies))
    await db.commit()
    await db.refresh(tag)
    return tag


@app.get("/billing/summary", response_model=BillingSummary)
async def get_billing_summary(current_user: Annotated[User, Depends(get_current_user)]) -> BillingSummary:
    return billing_summary(current_user.shop)


@app.get("/billing/plans", response_model=list[BillingPlanResponse])
async def list_billing_plans(db: Annotated[AsyncSession, Depends(get_db)]) -> list[BillingPlan]:
    result = await db.scalars(select(BillingPlan).where(BillingPlan.is_active.is_(True)).order_by(BillingPlan.sort_order, BillingPlan.price_inr))
    return list(result)


@app.get("/billing/ledger", response_model=list[CreditLedgerResponse])
async def list_billing_ledger(
    current_user: Annotated[User, Depends(get_current_user)],
    db: Annotated[AsyncSession, Depends(get_db)],
) -> list[CreditLedgerEntry]:
    result = await db.scalars(
        select(CreditLedgerEntry)
        .where(CreditLedgerEntry.shop_id == current_user.shop_id)
        .order_by(CreditLedgerEntry.created_at.desc())
        .limit(50)
    )
    return list(result)


@app.post("/billing/purchases", response_model=PlanPurchaseResponse, status_code=status.HTTP_201_CREATED)
async def create_purchase(
    payload: PurchaseCreate,
    current_user: Annotated[User, Depends(get_current_user)],
    db: Annotated[AsyncSession, Depends(get_db)],
) -> PlanPurchase:
    plan = await db.get(BillingPlan, payload.plan_id)
    if not plan or not plan.is_active:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Plan not found")

    purchase = PlanPurchase(
        shop_id=current_user.shop_id,
        plan_id=plan.id,
        amount_inr=plan.price_inr,
        tag_credits=plan.tag_credits,
        validity_days=plan.validity_days,
        is_unlimited=plan.is_unlimited,
        razorpay_order_id=f"local_order_{current_user.shop_id}_{int(now_utc().timestamp())}",
        notes="Local test order. Replace with Razorpay Orders API in production.",
    )
    db.add(purchase)
    await db.commit()
    await db.refresh(purchase)
    return purchase


@app.post("/billing/purchases/{purchase_id}/confirm", response_model=BillingSummary)
async def confirm_purchase_for_local_testing(
    purchase_id: int,
    current_user: Annotated[User, Depends(get_current_user)],
    db: Annotated[AsyncSession, Depends(get_db)],
) -> BillingSummary:
    purchase = await db.get(PlanPurchase, purchase_id)
    if not purchase or purchase.shop_id != current_user.shop_id:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Purchase not found")
    if purchase.status == PurchaseStatus.PAID:
        shop = await db.get(Shop, current_user.shop_id)
        if not shop:
            raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Shop not found")
        return billing_summary(shop)

    shop = await db.get(Shop, current_user.shop_id, with_for_update=True)
    if not shop:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Shop not found")

    purchase.status = PurchaseStatus.PAID
    purchase.razorpay_payment_id = f"local_payment_{purchase.id}"
    purchase.paid_at = now_utc()
    ledger_entry = grant_purchase_to_shop(shop, purchase)
    if ledger_entry:
        db.add(ledger_entry)
    await db.commit()
    await db.refresh(shop)
    return billing_summary(shop)


@app.get("/admin/plans", response_model=list[BillingPlanResponse])
async def admin_list_plans(
    current_user: Annotated[User, Depends(get_current_user)],
    db: Annotated[AsyncSession, Depends(get_db)],
) -> list[BillingPlan]:
    require_admin(current_user)
    result = await db.scalars(select(BillingPlan).order_by(BillingPlan.sort_order, BillingPlan.price_inr))
    return list(result)


@app.put("/admin/plans/{plan_id}", response_model=BillingPlanResponse)
async def admin_update_plan(
    plan_id: int,
    payload: BillingPlanUpdate,
    current_user: Annotated[User, Depends(get_current_user)],
    db: Annotated[AsyncSession, Depends(get_db)],
) -> BillingPlan:
    require_admin(current_user)
    plan = await db.get(BillingPlan, plan_id)
    if not plan:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Plan not found")

    for key, value in payload.model_dump().items():
        setattr(plan, key, value)
    await db.commit()
    await db.refresh(plan)
    return plan


@app.get("/admin/tenants", response_model=list[AdminTenantResponse])
async def admin_list_tenants(
    current_user: Annotated[User, Depends(get_current_user)],
    db: Annotated[AsyncSession, Depends(get_db)],
) -> list[AdminTenantResponse]:
    require_admin(current_user)
    shops = await db.scalars(select(Shop).order_by(Shop.created_at.desc()))
    return [await admin_tenant_response(db, shop) for shop in shops]


@app.get("/admin/tenants/{shop_id}/ledger", response_model=list[CreditLedgerResponse])
async def admin_tenant_ledger(
    shop_id: int,
    current_user: Annotated[User, Depends(get_current_user)],
    db: Annotated[AsyncSession, Depends(get_db)],
) -> list[CreditLedgerEntry]:
    require_admin(current_user)
    shop = await db.get(Shop, shop_id)
    if not shop:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Tenant not found")
    result = await db.scalars(
        select(CreditLedgerEntry)
        .where(CreditLedgerEntry.shop_id == shop_id)
        .order_by(CreditLedgerEntry.created_at.desc())
        .limit(100)
    )
    return list(result)


@app.post("/admin/tenants/{shop_id}/credit-adjustments", response_model=AdminTenantResponse)
async def admin_adjust_tenant_credits(
    shop_id: int,
    payload: AdminCreditAdjustment,
    current_user: Annotated[User, Depends(get_current_user)],
    db: Annotated[AsyncSession, Depends(get_db)],
) -> AdminTenantResponse:
    require_admin(current_user)
    shop = await db.get(Shop, shop_id, with_for_update=True)
    if not shop:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Tenant not found")

    old_balance = shop.tag_credit_balance
    if payload.set_credit_balance is not None:
        shop.tag_credit_balance = payload.set_credit_balance
        credits_changed = shop.tag_credit_balance - old_balance
    else:
        shop.tag_credit_balance = max(0, shop.tag_credit_balance + payload.credits_delta)
        credits_changed = shop.tag_credit_balance - old_balance

    if payload.credits_validity_days is not None:
        shop.credits_expire_at = now_utc() + timedelta(days=payload.credits_validity_days)

    if payload.unlimited_validity_days is not None:
        shop.unlimited_until = None if payload.unlimited_validity_days == 0 else now_utc() + timedelta(days=payload.unlimited_validity_days)

    description_parts = [payload.note]
    if payload.payment_reference:
        description_parts.append(f"Payment: {payload.payment_reference}")
    if payload.set_credit_balance is not None:
        description_parts.append(f"Balance set from {old_balance} to {shop.tag_credit_balance}")
    elif credits_changed:
        description_parts.append(f"Balance changed by {credits_changed}")
    if payload.credits_validity_days is not None:
        description_parts.append(f"Credit validity {payload.credits_validity_days} days")
    if payload.unlimited_validity_days is not None:
        description_parts.append("Unlimited cleared" if payload.unlimited_validity_days == 0 else f"Unlimited {payload.unlimited_validity_days} days")

    entry_type = LedgerEntryType.CREDIT if credits_changed >= 0 else LedgerEntryType.DEBIT
    db.add(
        CreditLedgerEntry(
            shop_id=shop.id,
            entry_type=entry_type,
            credits=abs(credits_changed),
            balance_after=shop.tag_credit_balance,
            description=" | ".join(description_parts),
        )
    )
    await db.commit()
    await db.refresh(shop)
    return await admin_tenant_response(db, shop)


@app.get("/settings", response_model=ShopResponse)
async def get_shop_settings(
    current_user: Annotated[User, Depends(get_current_user)],
    db: Annotated[AsyncSession, Depends(get_db)],
) -> Shop:
    shop = await db.get(Shop, current_user.shop_id)
    if not shop:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Shop not found")
    return shop


@app.put("/settings", response_model=ShopResponse)
async def update_settings(
    payload: ShopSettingsUpdate,
    current_user: Annotated[User, Depends(get_current_user)],
    db: Annotated[AsyncSession, Depends(get_db)],
) -> Shop:
    shop = await db.get(Shop, current_user.shop_id)
    if not shop:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Shop not found")

    for key, value in payload.model_dump().items():
        setattr(shop, key, value)
    await db.commit()
    await db.refresh(shop)
    return shop
