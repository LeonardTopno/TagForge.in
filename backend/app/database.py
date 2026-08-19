from collections.abc import AsyncGenerator

from sqlalchemy import inspect, text
from sqlalchemy.ext.asyncio import AsyncSession, async_sessionmaker, create_async_engine
from sqlalchemy.orm import DeclarativeBase

from app.config import get_settings


class Base(DeclarativeBase):
    pass


settings = get_settings()
engine = create_async_engine(settings.database_url, echo=settings.app_env == "development")
SessionLocal = async_sessionmaker(engine, expire_on_commit=False, class_=AsyncSession)


async def get_db() -> AsyncGenerator[AsyncSession, None]:
    async with SessionLocal() as session:
        yield session


async def create_database() -> None:
    from app import models  # noqa: F401

    async with engine.begin() as conn:
        await conn.run_sync(Base.metadata.create_all)
        if settings.database_url.startswith("sqlite"):
            await conn.run_sync(_ensure_sqlite_columns)


def _ensure_sqlite_columns(sync_conn) -> None:
    inspector = inspect(sync_conn)
    if "shops" not in inspector.get_table_names():
        return

    existing = {column["name"] for column in inspector.get_columns("shops")}
    additions = {
        "tag_credit_balance": "INTEGER NOT NULL DEFAULT 15",
        "credits_expire_at": "DATETIME",
        "unlimited_until": "DATETIME",
    }
    for column_name, column_sql in additions.items():
        if column_name not in existing:
            sync_conn.execute(text(f"ALTER TABLE shops ADD COLUMN {column_name} {column_sql}"))
