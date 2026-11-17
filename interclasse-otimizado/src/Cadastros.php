<?php
declare(strict_types=1);

/* Modalidades, times e jogadores. (Remover qualquer um deles: Conexao::remover.) */
final class Cadastros
{
    private const SQL_TIME = 'SELECT t.*, m.nome AS modalidade FROM time t JOIN modalidade m USING (id_modalidade)';

    public static function modalidades(): never
    {
        Http::responder(Conexao::consultar('SELECT * FROM modalidade ORDER BY nome'));
    }

    public static function criarModalidade(): never
    {
        $id = Conexao::inserir('INSERT INTO modalidade (nome) VALUES (?)', [Http::texto(Http::corpo(), 'nome')]);
        Http::responder(['id_modalidade' => $id], 201);
    }

    /** Filtro opcional: ?id_modalidade=1 */
    public static function times(): never
    {
        $filtrar = isset($_GET['id_modalidade']);
        Http::responder(Conexao::consultar(
            self::SQL_TIME . ($filtrar ? ' WHERE t.id_modalidade = ?' : '') . ' ORDER BY m.nome, t.nome',
            $filtrar ? [(int) $_GET['id_modalidade']] : []
        ));
    }

    public static function time(int $id): never
    {
        $time = Conexao::um(self::SQL_TIME . ' WHERE t.id_time = ?', [$id]) ?? Http::falhar('Time não encontrado.', 404);
        $time['jogadores'] = self::jogadoresDoTime($id);
        Http::responder($time);
    }

    public static function criarTime(): never
    {
        $dados = Http::corpo();
        Conexao::achar('modalidade', Http::inteiro($dados, 'id_modalidade'));

        $id = Conexao::inserir(
            'INSERT INTO time (id_modalidade, nome) VALUES (?, ?)',
            [Http::inteiro($dados, 'id_modalidade'), Http::texto($dados, 'nome')]
        );
        Http::responder(['id_time' => $id], 201);
    }

    public static function criarJogador(): never
    {
        $dados = Http::corpo();
        Conexao::achar('time', Http::inteiro($dados, 'id_time'));

        $numero = ($dados['numero'] ?? '') === '' ? null : Http::inteiro($dados, 'numero');
        $id = Conexao::inserir(
            'INSERT INTO jogador (id_time, nome, numero) VALUES (?, ?, ?)',
            [Http::inteiro($dados, 'id_time'), Http::texto($dados, 'nome'), $numero]
        );
        Http::responder(['id_jogador' => $id], 201);
    }

    public static function jogadoresDoTime(int $idTime): array
    {
        return Conexao::consultar('SELECT * FROM jogador WHERE id_time = ? ORDER BY numero IS NULL, numero, nome', [$idTime]);
    }
}
