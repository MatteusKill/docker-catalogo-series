<?php

declare(strict_types=1);

session_start();

require_once dirname(__DIR__) . '/app/ApiClient.php';

$apiBaseUrl = getenv('API_BASE_URL') ?: 'http://api:8000';
$api = new ApiClient($apiBaseUrl);
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

try {
    if ($path === '/criar') {
        paginaCriacao($api, $method);
    } elseif ($path === '/editar') {
        paginaEdicao($api, $method);
    } elseif ($path === '/remover' && $method === 'POST') {
        removerSerie($api);
    } elseif ($path === '/status') {
        paginaStatus($api, $apiBaseUrl);
    } elseif ($path === '/') {
        paginaListagem($api);
    } else {
        http_response_code(404);
        cabecalho('Página não encontrada');
        alerta('A página solicitada não existe.', 'erro');
        echo '<a class="button" href="/">Voltar ao catálogo</a>';
        rodape();
    }
} catch (ApiException $error) {
    http_response_code($error->getStatusCode() >= 400 ? $error->getStatusCode() : 502);
    cabecalho('Falha de comunicação');
    alerta($error->getMessage(), 'erro');
    echo '<p>O frontend não acessa o banco diretamente. Verifique os contêineres da API e do MySQL.</p>';
    echo '<a class="button" href="/status">Ver status</a>';
    rodape();
} catch (Throwable $error) {
    http_response_code(500);
    cabecalho('Erro inesperado');
    alerta('Não foi possível concluir a solicitação.', 'erro');
    echo '<a class="button" href="/">Voltar ao catálogo</a>';
    rodape();
}

function paginaListagem(ApiClient $api): void
{
    $series = $api->get('/series');
    cabecalho('Catálogo de Séries');
    exibirFlash();

    echo '<section class="hero">';
    echo '<div><span class="eyebrow">Sua videoteca</span><h1>Séries para acompanhar</h1>';
    echo '<p>Cadastre, organize e atualize as séries do seu catálogo.</p></div>';
    echo '<a class="button" href="/criar">Adicionar série</a></section>';

    if ($series === []) {
        echo '<section class="empty"><h2>O catálogo está vazio</h2>';
        echo '<p>Adicione a primeira série para começar.</p>';
        echo '<a class="button button-secondary" href="/criar">Criar primeiro registro</a></section>';
        rodape();
        return;
    }

    echo '<section class="series-grid" aria-label="Séries cadastradas">';
    foreach ($series as $serie) {
        $id = (int) ($serie['id'] ?? 0);
        echo '<article class="series-card">';
        echo '<div class="series-card-top"><span class="genre">' . escapar((string) ($serie['genero'] ?? '')) . '</span>';
        echo '<span class="year">' . escapar((string) ($serie['ano_lancamento'] ?? '')) . '</span></div>';
        echo '<h2>' . escapar((string) ($serie['titulo'] ?? '')) . '</h2>';
        echo '<p>' . escapar((string) ($serie['temporadas'] ?? '')) . ' temporada(s)</p>';
        echo '<div class="actions"><a class="text-link" href="/editar?id=' . $id . '">Editar</a>';
        echo '<form action="/remover" method="post">' . campoCsrf();
        echo '<input type="hidden" name="id" value="' . $id . '">';
        echo '<button class="danger-link" type="submit">Remover</button></form></div>';
        echo '</article>';
    }
    echo '</section>';
    rodape();
}

function paginaCriacao(ApiClient $api, string $method): void
{
    $values = valoresFormulario($_POST);
    $error = null;

    if ($method === 'POST') {
        validarCsrf();
        try {
            $api->post('/series', payloadFormulario($values));
            definirFlash('Série adicionada ao catálogo.');
            redirecionar('/');
        } catch (ApiException $exception) {
            $error = $exception->getMessage();
        }
    }

    cabecalho('Adicionar série');
    echo '<div class="page-heading"><div><span class="eyebrow">Novo registro</span><h1>Adicionar série</h1></div>';
    echo '<a class="text-link" href="/">Cancelar</a></div>';
    if ($error !== null) {
        alerta($error, 'erro');
    }
    formularioSerie('/criar', 'Adicionar ao catálogo', $values);
    rodape();
}

function paginaEdicao(ApiClient $api, string $method): void
{
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        throw new ApiException('Identificador da série inválido.', 400);
    }

    $registro = $api->get('/series/id/' . $id);
    $values = $method === 'POST' ? valoresFormulario($_POST) : valoresFormulario($registro);
    $error = null;

    if ($method === 'POST') {
        validarCsrf();
        try {
            $api->patch('/series/' . $id, payloadFormulario($values));
            definirFlash('Série atualizada com sucesso.');
            redirecionar('/');
        } catch (ApiException $exception) {
            $error = $exception->getMessage();
        }
    }

    cabecalho('Editar série');
    echo '<div class="page-heading"><div><span class="eyebrow">Registro #' . $id . '</span><h1>Editar série</h1></div>';
    echo '<a class="text-link" href="/">Cancelar</a></div>';
    if ($error !== null) {
        alerta($error, 'erro');
    }
    formularioSerie('/editar?id=' . $id, 'Salvar alterações', $values);
    rodape();
}

function removerSerie(ApiClient $api): never
{
    validarCsrf();
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        throw new ApiException('Identificador da série inválido.', 400);
    }

    $api->delete('/series/' . $id);
    definirFlash('Série removida do catálogo.');
    redirecionar('/');
}

function paginaStatus(ApiClient $api, string $apiBaseUrl): void
{
    $online = true;
    $status = [];
    $message = null;

    try {
        $status = $api->get('/health');
    } catch (ApiException $error) {
        $online = false;
        $message = $error->getMessage();
    }

    cabecalho('Status dos serviços');
    echo '<div class="page-heading"><div><span class="eyebrow">Observabilidade</span><h1>Status da aplicação</h1></div>';
    echo '<a class="text-link" href="/">Voltar</a></div>';
    echo '<section class="status-card">';
    echo '<span class="status-dot ' . ($online ? 'online' : 'offline') . '"></span>';
    echo '<div><h2>API ' . ($online ? 'disponível' : 'indisponível') . '</h2>';
    echo '<p>' . ($online
        ? 'A API respondeu e confirmou a conexão com o MySQL.'
        : escapar($message ?? 'Não foi possível consultar a API.')) . '</p></div></section>';
    echo '<dl class="details"><div><dt>Endereço interno usado pelo PHP</dt><dd><code>' . escapar($apiBaseUrl) . '</code></dd></div>';
    echo '<div><dt>API</dt><dd>' . escapar((string) ($status['status'] ?? 'offline')) . '</dd></div>';
    echo '<div><dt>Banco de dados</dt><dd>' . escapar((string) ($status['database'] ?? 'indisponível')) . '</dd></div></dl>';
    rodape();
}

/** @param array<string, mixed> $source
 *  @return array{titulo: string, genero: string, ano_lancamento: string, temporadas: string}
 */
function valoresFormulario(array $source): array
{
    return [
        'titulo' => trim((string) ($source['titulo'] ?? '')),
        'genero' => trim((string) ($source['genero'] ?? '')),
        'ano_lancamento' => trim((string) ($source['ano_lancamento'] ?? '')),
        'temporadas' => trim((string) ($source['temporadas'] ?? '')),
    ];
}

/** @param array{titulo: string, genero: string, ano_lancamento: string, temporadas: string} $values
 *  @return array{titulo: string, genero: string, ano_lancamento: int, temporadas: int}
 */
function payloadFormulario(array $values): array
{
    return [
        'titulo' => $values['titulo'],
        'genero' => $values['genero'],
        'ano_lancamento' => (int) $values['ano_lancamento'],
        'temporadas' => (int) $values['temporadas'],
    ];
}

/** @param array{titulo: string, genero: string, ano_lancamento: string, temporadas: string} $values */
function formularioSerie(string $action, string $buttonLabel, array $values): void
{
    echo '<form class="series-form" action="' . escapar($action) . '" method="post">' . campoCsrf();
    echo '<label>Título<input name="titulo" type="text" maxlength="255" required value="' . escapar($values['titulo']) . '"></label>';
    echo '<label>Gênero<input name="genero" type="text" maxlength="100" required value="' . escapar($values['genero']) . '"></label>';
    echo '<div class="form-row"><label>Ano de lançamento<input name="ano_lancamento" type="number" min="1901" max="2100" required value="' . escapar($values['ano_lancamento']) . '"></label>';
    echo '<label>Temporadas<input name="temporadas" type="number" min="1" required value="' . escapar($values['temporadas']) . '"></label></div>';
    echo '<button class="button" type="submit">' . escapar($buttonLabel) . '</button></form>';
}

function cabecalho(string $title): void
{
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . escapar($title) . ' | Catálogo</title>';
    echo '<link rel="stylesheet" href="/style.css"></head><body>';
    echo '<header class="site-header"><a class="brand" href="/">CATÁLOGO<span>.</span></a>';
    echo '<nav><a href="/">Séries</a><a href="/criar">Adicionar</a><a href="/status">Status</a></nav></header>';
    echo '<main class="container">';
}

function rodape(): void
{
    echo '</main><footer>Frontend PHP · API FastAPI · MySQL</footer></body></html>';
}

function alerta(string $message, string $type): void
{
    echo '<div class="alert alert-' . escapar($type) . '" role="alert">' . escapar($message) . '</div>';
}

function definirFlash(string $message): void
{
    $_SESSION['flash'] = $message;
}

function exibirFlash(): void
{
    if (!isset($_SESSION['flash'])) {
        return;
    }
    alerta((string) $_SESSION['flash'], 'sucesso');
    unset($_SESSION['flash']);
}

function validarCsrf(): void
{
    $providedToken = (string) ($_POST['csrf_token'] ?? '');
    $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
    if ($sessionToken === '' || !hash_equals($sessionToken, $providedToken)) {
        throw new ApiException('A sessão expirou. Atualize a página e tente novamente.', 400);
    }
}

function campoCsrf(): string
{
    return '<input type="hidden" name="csrf_token" value="' . escapar((string) $_SESSION['csrf_token']) . '">';
}

function redirecionar(string $path): never
{
    header('Location: ' . $path, true, 303);
    exit;
}

function escapar(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
