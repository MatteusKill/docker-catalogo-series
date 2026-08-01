from contextlib import asynccontextmanager

from fastapi import FastAPI, HTTPException, status
from sqlalchemy import text
from sqlalchemy.exc import SQLAlchemyError

from database.series_db import Base, engine
from routes.series import router as series_router


@asynccontextmanager
async def lifespan(_: FastAPI):
    Base.metadata.create_all(bind=engine)
    yield


app = FastAPI(
    title="Catálogo de Séries API",
    description="API baseada no projeto LuanOI/CatalogodeSeries, com persistência MySQL.",
    version="1.0.0",
    lifespan=lifespan,
)
app.include_router(series_router)


@app.get("/")
def home() -> dict[str, str]:
    return {"mensagem": "Catálogo de séries em funcionamento"}


@app.get("/health")
def health() -> dict[str, str]:
    try:
        with engine.connect() as connection:
            connection.execute(text("SELECT 1"))
    except SQLAlchemyError as error:
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail="Banco de dados indisponível.",
        ) from error

    return {"status": "ok", "database": "connected"}
