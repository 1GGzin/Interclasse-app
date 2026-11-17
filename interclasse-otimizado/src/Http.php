<?php
declare(strict_types=1);

/* Apoio às rotas: resposta em JSON, leitura/validação da entrada e controle de acesso. */
final class Http
{
    public static function responder(mixed $dados, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        exit(json_encode($dados, JSON_UNESCAPED_UNICODE));
    }

    public static function falhar(string $mensagem, int $status = 400): never
    {
        self::responder(['erro' => $mensagem], $status);
    }

    public static function corpo(): array
    {
        $dados = json_decode((string) file_get_contents('php://input'), true);
        return is_array($dados) ? $dados : [];
    }

    public static function texto(array $dados, string $campo): string
    {
        $valor = trim((string) ($dados[$campo] ?? ''));
        return $valor !== '' ? $valor : self::falhar("Preencha o campo \"$campo\".", 422);
    }

    public static function inteiro(array $dados, string $campo): int
    {
        $valor = $dados[$campo] ?? null;
        return filter_var($valor, FILTER_VALIDATE_INT) !== false
            ? (int) $valor
            : self::falhar("O campo \"$campo\" precisa ser um número.", 422);
    }

    /** Aceita "2026-11-20T14:30" ou "2026-11-20 14:30" e devolve "2026-11-20 14:30". */
    public static function dataHora(mixed $valor): string
    {
        $texto = substr(str_replace('T', ' ', trim((string) $valor)), 0, 16);
        return preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $texto) && strtotime($texto)
            ? $texto
            : self::falhar('Data e hora inválidas.', 422);
    }

    // ---------- Acesso (usuário guardado na sessão do PHP) ----------

    public static function usuario(): ?array
    {
        return $_SESSION['usuario'] ?? null;
    }

    public static function exigirLogin(): array
    {
        return self::usuario() ?? self::falhar('Faça login para continuar.', 401);
    }

    public static function exigirOrganizador(): void
    {
        if (self::exigirLogin()['papel'] !== 'organizador') {
            self::falhar('Apenas organizadores podem fazer isso.', 403);
        }
    }
}
