<?php
declare(strict_types=1);

/* Conexão com o banco (SQLite). O arquivo e as tabelas são criados no primeiro acesso. */
final class Conexao
{
    private static ?PDO $pdo = null;

    private static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $arquivo = __DIR__ . '/../dados/interclasse.sqlite';
            is_dir(dirname($arquivo)) || mkdir(dirname($arquivo), 0775, true);
            $novo = !file_exists($arquivo);

            self::$pdo = new PDO('sqlite:' . $arquivo, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            self::$pdo->exec('PRAGMA foreign_keys = ON');
            if ($novo) {
                self::criarBanco();
            }
        }
        return self::$pdo;
    }

    /** Tabelas + primeiro organizador (TROQUE A SENHA depois). */
    private static function criarBanco(): void
    {
        self::pdo()->exec(file_get_contents(__DIR__ . '/../sql/interclasse.sql'));
        self::executar(
            "INSERT INTO usuario (nome, email, senha, papel) VALUES ('Organizador', 'admin@escola.com', ?, 'organizador')",
            [password_hash('admin123', PASSWORD_DEFAULT)]
        );
    }

    /** Várias linhas. Valores sempre pelos "?", nunca dentro do texto do SQL. */
    public static function consultar(string $sql, array $valores = []): array
    {
        $comando = self::pdo()->prepare($sql);
        $comando->execute($valores);
        return $comando->fetchAll();
    }

    /** Uma linha, ou null. */
    public static function um(string $sql, array $valores = []): ?array
    {
        return self::consultar($sql, $valores)[0] ?? null;
    }

    public static function executar(string $sql, array $valores = []): void
    {
        self::pdo()->prepare($sql)->execute($valores);
    }

    /** INSERT que devolve o id criado. */
    public static function inserir(string $sql, array $valores = []): int
    {
        self::executar($sql, $valores);
        return (int) self::pdo()->lastInsertId();
    }

    /** Linha de uma tabela pelo id (a chave é sempre "id_<tabela>") ou erro 404. */
    public static function achar(string $tabela, int $id): array
    {
        return self::um("SELECT * FROM $tabela WHERE id_$tabela = ?", [$id])
            ?? Http::falhar('Registro não encontrado.', 404);
    }

    public static function remover(string $tabela, int $id): array
    {
        self::achar($tabela, $id);
        self::executar("DELETE FROM $tabela WHERE id_$tabela = ?", [$id]);
        return ['ok' => true];
    }
}
