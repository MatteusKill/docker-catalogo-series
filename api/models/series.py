from datetime import datetime

from pydantic import BaseModel, ConfigDict, Field, field_validator, model_validator


class SerieBase(BaseModel):
    titulo: str = Field(min_length=1, max_length=255)
    genero: str = Field(min_length=1, max_length=100)
    ano_lancamento: int = Field(gt=1900, le=2100)
    temporadas: int = Field(gt=0)

    @field_validator("titulo", "genero")
    @classmethod
    def remove_espacos_extras(cls, value: str) -> str:
        value = " ".join(value.split())
        if not value:
            raise ValueError("O campo não pode conter apenas espaços.")
        return value


class SerieCreate(SerieBase):
    pass


class SerieUpdate(BaseModel):
    titulo: str | None = Field(default=None, min_length=1, max_length=255)
    genero: str | None = Field(default=None, min_length=1, max_length=100)
    ano_lancamento: int | None = Field(default=None, gt=1900, le=2100)
    temporadas: int | None = Field(default=None, gt=0)

    @field_validator("titulo", "genero")
    @classmethod
    def remove_espacos_extras(cls, value: str | None) -> str | None:
        if value is None:
            return None
        value = " ".join(value.split())
        if not value:
            raise ValueError("O campo não pode conter apenas espaços.")
        return value

    @model_validator(mode="after")
    def validar_campos_informados(self) -> "SerieUpdate":
        if not self.model_fields_set:
            raise ValueError("Informe ao menos um campo para atualizar.")
        if any(getattr(self, campo) is None for campo in self.model_fields_set):
            raise ValueError("Os campos informados não podem ser nulos.")
        return self


class SerieResponse(SerieBase):
    model_config = ConfigDict(from_attributes=True)

    id: int
    criado_em: datetime
    atualizado_em: datetime
