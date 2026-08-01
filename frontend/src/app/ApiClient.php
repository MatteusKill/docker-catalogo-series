<?php

declare(strict_types=1);

final class ApiException extends RuntimeException
{
    public function __construct(string $message, private readonly int $statusCode = 0)
    {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}

final class ApiClient
{
    public function __construct(private readonly string $baseUrl)
    {
    }

    /** @return array<string, mixed>|list<array<string, mixed>> */
    public function get(string $path): array
    {
        $response = $this->request('GET', $path);
        return is_array($response) ? $response : [];
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    public function post(string $path, array $payload): array
    {
        $response = $this->request('POST', $path, $payload);
        return is_array($response) ? $response : [];
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    public function patch(string $path, array $payload): array
    {
        $response = $this->request('PATCH', $path, $payload);
        return is_array($response) ? $response : [];
    }

    public function delete(string $path): void
    {
        $this->request('DELETE', $path);
    }

    /** @param array<string, mixed>|null $payload
     *  @return array<string, mixed>|list<array<string, mixed>>|null
     */
    private function request(string $method, string $path, ?array $payload = null): ?array
    {
        $handle = curl_init(rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/'));
        if ($handle === false) {
            throw new ApiException('Não foi possível iniciar a conexão com a API.');
        }

        $headers = ['Accept: application/json'];
        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 8,
        ]);

        if ($payload !== null) {
            $encodedPayload = json_encode($payload, JSON_THROW_ON_ERROR);
            $headers[] = 'Content-Type: application/json';
            curl_setopt($handle, CURLOPT_POSTFIELDS, $encodedPayload);
        }

        curl_setopt($handle, CURLOPT_HTTPHEADER, $headers);
        $body = curl_exec($handle);

        if ($body === false) {
            $message = curl_error($handle);
            curl_close($handle);
            throw new ApiException('API indisponível: ' . $message);
        }

        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if ($statusCode === 204) {
            return null;
        }

        try {
            $decodedBody = $body === '' ? [] : json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new ApiException('A API retornou uma resposta inválida.', $statusCode);
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            $detail = is_array($decodedBody) ? ($decodedBody['detail'] ?? null) : null;
            if (is_array($detail)) {
                $detail = 'Confira os dados informados.';
            }
            throw new ApiException(
                is_string($detail) ? $detail : 'A API não conseguiu concluir a operação.',
                $statusCode,
            );
        }

        return is_array($decodedBody) ? $decodedBody : [];
    }
}
