import os
from collections.abc import Generator
from datetime import datetime
from urllib.parse import quote_plus

from sqlalchemy import DateTime, Integer, String, create_engine
from sqlalchemy.orm import DeclarativeBase, Mapped, Session, mapped_column, sessionmaker
from sqlalchemy.sql import func


def build_database_url() -> str:
    user = quote_plus(os.getenv("DB_USER", "catalogo_user"))
    password = quote_plus(os.getenv("DB_PASSWORD", "catalogo_password"))
    host = os.getenv("DB_HOST", "mysql")
    port = os.getenv("DB_PORT", "3306")
    database = quote_plus(os.getenv("DB_NAME", "catalogo_series"))

    return f"mysql+pymysql://{user}:{password}@{host}:{port}/{database}?charset=utf8mb4"


class Base(DeclarativeBase):
    pass


class SerieModel(Base):
    __tablename__ = "series"

    id: Mapped[int] = mapped_column(Integer, primary_key=True, autoincrement=True)
    titulo: Mapped[str] = mapped_column(String(255), unique=True, nullable=False, index=True)
    genero: Mapped[str] = mapped_column(String(100), nullable=False)
    ano_lancamento: Mapped[int] = mapped_column(Integer, nullable=False)
    temporadas: Mapped[int] = mapped_column(Integer, nullable=False)
    criado_em: Mapped[datetime] = mapped_column(
        DateTime, nullable=False, server_default=func.now()
    )
    atualizado_em: Mapped[datetime] = mapped_column(
        DateTime, nullable=False, server_default=func.now(), onupdate=func.now()
    )


engine = create_engine(build_database_url(), pool_pre_ping=True, pool_recycle=3600)
SessionLocal = sessionmaker(bind=engine, autoflush=False, expire_on_commit=False)


def get_session() -> Generator[Session, None, None]:
    session = SessionLocal()
    try:
        yield session
    finally:
        session.close()
