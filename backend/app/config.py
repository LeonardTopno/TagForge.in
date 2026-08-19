from functools import lru_cache

from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    app_name: str = "Jewellery Tag Printer"
    app_env: str = "development"
    database_url: str = "sqlite+aiosqlite:///./tag_printer.db"
    allowed_origins: str = "http://localhost:5173,http://127.0.0.1:5173"
    secret_key: str = "change-this-before-production"

    model_config = SettingsConfigDict(env_file=".env", env_file_encoding="utf-8")

    @property
    def cors_origins(self) -> list[str]:
        return [origin.strip() for origin in self.allowed_origins.split(",") if origin.strip()]


@lru_cache
def get_settings() -> Settings:
    return Settings()
