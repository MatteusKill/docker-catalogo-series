from typing import Annotated

from fastapi import APIRouter, Depends, HTTPException, Response, status
from sqlalchemy import func, select
from sqlalchemy.exc import IntegrityError
from sqlalchemy.orm import Session

from database.series_db import SerieModel, get_session
from models.series import SerieCreate, SerieResponse, SerieUpdate

router = APIRouter(prefix="/series", tags=["Séries"])
DatabaseSession = Annotated[Session, Depends(get_session)]


@router.get("", response_model=list[SerieResponse])
def listar_series(session: DatabaseSession) -> list[SerieModel]:
    return list(session.scalars(select(SerieModel).order_by(SerieModel.titulo)).all())


@router.post("", response_model=SerieResponse, status_code=status.HTTP_201_CREATED)
def criar_serie(serie: SerieCreate, session: DatabaseSession) -> SerieModel:
    registro = SerieModel(**serie.model_dump())
    session.add(registro)

    try:
        session.commit()
        session.refresh(registro)
    except IntegrityError as error:
        session.rollback()
        raise HTTPException(
            status_code=status.HTTP_409_CONFLICT,
            detail="Já existe uma série com esse título.",
        ) from error

    return registro


@router.get("/id/{serie_id}", response_model=SerieResponse)
def buscar_serie_por_id(serie_id: int, session: DatabaseSession) -> SerieModel:
    return obter_serie_ou_404(serie_id, session)


@router.get("/{titulo}", response_model=SerieResponse)
def buscar_serie_por_titulo(titulo: str, session: DatabaseSession) -> SerieModel:
    query = select(SerieModel).where(func.lower(SerieModel.titulo) == titulo.lower())
    registro = session.scalar(query)

    if registro is None:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Série não encontrada.",
        )

    return registro


@router.patch("/{serie_id}", response_model=SerieResponse)
def editar_serie(
    serie_id: int, serie: SerieUpdate, session: DatabaseSession
) -> SerieModel:
    registro = obter_serie_ou_404(serie_id, session)

    for campo, valor in serie.model_dump(exclude_unset=True).items():
        setattr(registro, campo, valor)

    try:
        session.commit()
        session.refresh(registro)
    except IntegrityError as error:
        session.rollback()
        raise HTTPException(
            status_code=status.HTTP_409_CONFLICT,
            detail="Já existe uma série com esse título.",
        ) from error

    return registro


@router.delete("/{serie_id}", status_code=status.HTTP_204_NO_CONTENT)
def remover_serie(serie_id: int, session: DatabaseSession) -> Response:
    registro = obter_serie_ou_404(serie_id, session)
    session.delete(registro)
    session.commit()
    return Response(status_code=status.HTTP_204_NO_CONTENT)


def obter_serie_ou_404(serie_id: int, session: Session) -> SerieModel:
    registro = session.get(SerieModel, serie_id)
    if registro is None:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Série não encontrada.",
        )
    return registro
