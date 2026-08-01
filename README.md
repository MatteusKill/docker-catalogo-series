# Catálogo de Séries com Docker Compose

Aplicação web composta por Nginx, frontend PHP-FPM, API FastAPI e MySQL. O navegador acessa somente o Nginx; os demais serviços se comunicam pelas redes internas do Docker Compose.

A API foi adaptada do repositório [LuanOI/CatalogodeSeries](https://github.com/LuanOI/CatalogodeSeries). O contrato original de séries foi mantido e a persistência SQLite foi substituída por MySQL.

## Arquitetura

```text
Navegador
    |
    | http://localhost:8080
    v
  Nginx -- rede frontend -- PHP
                              |
                              | http://api:8000
                              v
                       rede backend -- FastAPI
                                          |
                                          | mysql:3306
                                          v
                              rede banco_dados -- MySQL
                                                    |
                                                    v
                                               mysql_data
```

O projeto possui exatamente quatro serviços:

| Serviço | Responsabilidade | Porta publicada no host |
|---|---|---|
| `nginx` | Receber as requisições do navegador e encaminhar PHP para o PHP-FPM | `8080` |
| `php` | Renderizar as páginas e consumir a API | Nenhuma |
| `api` | Validar as operações e executar o CRUD | Nenhuma |
| `mysql` | Persistir a tabela `series` | Nenhuma |

## Funcionalidades

- Listagem de séries em `/`;
- criação em `/criar`;
- edição em `/editar?id={id}`;
- remoção pela listagem;
- status da API e do banco em `/status`;
- proteção CSRF nos formulários do frontend;
- validação dos dados na API;
- persistência no volume nomeado `mysql_data`.

## Pré-requisitos

- Docker Engine;
- Docker Compose v2 (`docker compose`).

Não é necessário instalar PHP, Python ou MySQL na máquina.

## Configuração

As variáveis possuem valores locais padrão no Compose. Para personalizá-las:

```bash
cp .env.example .env
```

Edite `.env` antes da primeira inicialização do banco. O MySQL aplica as credenciais somente quando cria um volume vazio.

## Como subir o ambiente

Na raiz do repositório, execute:

```bash
docker compose up --build -d
docker compose ps
```

Os `healthchecks` controlam a ordem de inicialização: MySQL saudável, depois API, PHP e Nginx.

## Como acessar o frontend

Abra [http://localhost:8080](http://localhost:8080) no navegador.

Para usar outra porta no host, defina `FRONTEND_PORT` no `.env`, por exemplo `FRONTEND_PORT=8081`, e recrie o ambiente.

## Como parar o ambiente

Parar e remover os contêineres sem apagar os dados:

```bash
docker compose down
```

Somente pausar os contêineres:

```bash
docker compose stop
```

## Como apagar os volumes

Para remover os contêineres e apagar definitivamente os dados do MySQL:

```bash
docker compose down --volumes
```

Na próxima subida, o volume `mysql_data` e a tabela `series` serão criados novamente pelo script `database/init/00_schema_inicial.sql`.

## Como testar a API internamente

A API não publica porta no host. Para consultar a raiz e o healthcheck a partir do contêiner PHP, usando a rede `backend`:

```bash
docker compose exec php php -r 'echo file_get_contents(getenv("API_BASE_URL") . "/");'
docker compose exec php php -r 'echo file_get_contents(getenv("API_BASE_URL") . "/health");'
```

Para listar as séries diretamente dentro do contêiner da API:

```bash
docker compose exec api python -c "import urllib.request; print(urllib.request.urlopen('http://localhost:8000/series').read().decode())"
```

Endpoints disponíveis:

| Método | Endpoint | Descrição |
|---|---|---|
| `GET` | `/` | Identifica a API |
| `GET` | `/health` | Verifica API e conexão MySQL |
| `GET` | `/series` | Lista as séries |
| `POST` | `/series` | Cria uma série |
| `GET` | `/series/{titulo}` | Busca pelo título |
| `GET` | `/series/id/{id}` | Busca pelo ID para a tela de edição |
| `PATCH` | `/series/{id}` | Atualiza parcialmente uma série |
| `DELETE` | `/series/{id}` | Remove uma série |

## Como ver os logs

Logs de todos os serviços:

```bash
docker compose logs -f
```

Logs de serviços específicos:

```bash
docker compose logs -f nginx php
docker compose logs -f api mysql
```

Use `Ctrl+C` para sair do acompanhamento sem parar os contêineres.

## Como funcionam as redes

### Rede `frontend`

Conecta somente `nginx` e `php`. O Nginx encaminha arquivos `.php` para `php:9000`, nome e porta internos do PHP-FPM. O Nginx é o único serviço com uma porta publicada, portanto é a única entrada da aplicação a partir do host.

### Rede `backend`

Conecta somente `php` e `api`. O frontend consome a API por `http://api:8000`, mas o navegador não consegue acessar esse endereço. A rede é marcada como `internal`, bloqueando acesso externo direto.

### Rede `banco_dados`

Conecta somente `api` e `mysql`. Apenas a API possui caminho de rede até o banco. Essa rede também é `internal`.

## Por que o banco não tem porta publicada

O MySQL precisa receber conexões somente da API, pelo endereço interno `mysql:3306`. Publicar `3306:3306` aumentaria a superfície de ataque e permitiria tentativas de conexão externas desnecessárias. Com a rede `banco_dados`, nem o Nginx nem o PHP acessam o banco diretamente.

Para executar uma consulta administrativa sem publicar a porta:

```bash
docker compose exec mysql mysql -u root -p catalogo_series
```

## Por que o PHP usa `http://api:8000`

O Docker Compose fornece DNS interno para os serviços que compartilham uma rede. Na rede `backend`, o nome `api` resolve para o contêiner FastAPI e `8000` é a porta em que o Uvicorn escuta dentro dele. `localhost` no contêiner PHP apontaria para o próprio PHP, não para a API.

Esse valor é configurado pela variável `API_BASE_URL`, mantendo o endereço fora do código do frontend.

## Estrutura do projeto

```text
.
├── api/                    # FastAPI, modelos, rotas e acesso ao MySQL
├── database/init/          # Criação inicial da tabela series
├── frontend/               # PHP-FPM, cliente HTTP e páginas
├── nginx/                  # Virtual host do frontend
├── docker-compose.yml      # Serviços, redes, healthchecks e volume
└── .env.example            # Exemplo de configuração local
```

## Entrega

Repositório: [MatteusKill/docker-catalogo-series](https://github.com/MatteusKill/docker-catalogo-series)
