<?php
declare(strict_types=1);

/* Partidas: agenda, status, placar e quem pontuou. */
final class Partidas
{
    private const STATUS = ['agendada', 'ao_vivo', 'encerrada'];

    private const SQL = 'SELECT p.*, m.nome AS modalidade, tc.nome AS time_casa, tf.nome AS time_fora
        FROM partida p JOIN modalidade m USING (id_modalidade)
        JOIN time tc ON tc.id_time = p.id_time_casa
        JOIN time tf ON tf.id_time = p.id_time_fora';

    // ---------- Leitura ----------

    /** Filtros opcionais: ?status=agendada|ao_vivo|encerrada  &id_modalidade=1 */
    public static function listar(): never
    {
        $onde = [];
        $valores = [];
        if (isset($_GET['id_modalidade'])) {
            $onde[] = 'p.id_modalidade = ?';
            $valores[] = (int) $_GET['id_modalidade'];
        }
        $status = $_GET['status'] ?? '';
        if (in_array($status, self::STATUS, true)) {
            $onde[] = 'p.status = ?';
            $valores[] = $status;
        }
        $ordem = $status === 'encerrada' ? 'DESC' : 'ASC'; // resultados: mais recentes primeiro

        Http::responder(Conexao::consultar(
            self::SQL . ($onde ? ' WHERE ' . implode(' AND ', $onde) : '') . " ORDER BY p.data_hora $ordem",
            $valores
        ));
    }

    /** Partida + jogadores dos dois times + quem pontuou. */
    public static function ver(int $id): never
    {
        $partida = self::buscar($id);
        $partida['jogadores_casa'] = Cadastros::jogadoresDoTime($partida['id_time_casa']);
        $partida['jogadores_fora'] = Cadastros::jogadoresDoTime($partida['id_time_fora']);
        $partida['pontuacoes'] = Conexao::consultar(
            'SELECT po.*, j.nome AS jogador FROM pontuacao po LEFT JOIN jogador j USING (id_jogador)
              WHERE po.id_partida = ? ORDER BY po.id_pontuacao',
            [$id]
        );
        Http::responder($partida);
    }

    // ---------- Organizador: agenda ----------

    public static function criar(): never
    {
        $dados = Http::corpo();
        $modalidade = Http::inteiro($dados, 'id_modalidade');
        $casa = Http::inteiro($dados, 'id_time_casa');
        $fora = Http::inteiro($dados, 'id_time_fora');

        if ($casa === $fora) {
            Http::falhar('Escolha dois times diferentes.', 422);
        }
        foreach ([$casa, $fora] as $idTime) {
            $time = Conexao::um('SELECT id_modalidade FROM time WHERE id_time = ?', [$idTime]);
            if ((int) ($time['id_modalidade'] ?? 0) !== $modalidade) {
                Http::falhar('Os dois times precisam existir e ser da modalidade escolhida.', 422);
            }
        }

        $id = Conexao::inserir(
            'INSERT INTO partida (id_modalidade, id_time_casa, id_time_fora, data_hora, local) VALUES (?, ?, ?, ?, ?)',
            [$modalidade, $casa, $fora, Http::dataHora($dados['data_hora'] ?? ''), trim((string) ($dados['local'] ?? '')) ?: null]
        );
        Http::responder(self::buscar($id), 201);
    }

    /**
     * Altera só o que for enviado: data_hora, local, status, placar_casa, placar_fora.
     * Obs.: lançar ou desfazer uma pontuação recalcula o placar a partir delas.
     */
    public static function atualizar(int $id): never
    {
        Conexao::achar('partida', $id);
        $dados = Http::corpo();
        $campos = [];

        foreach (array_intersect_key($dados, array_flip(['data_hora', 'local', 'status', 'placar_casa', 'placar_fora'])) as $campo => $valor) {
            $campos[$campo] = match ($campo) {
                'data_hora' => Http::dataHora($valor),
                'local'     => trim((string) $valor) ?: null,
                'status'    => in_array($valor, self::STATUS, true) ? $valor : Http::falhar('Status inválido.', 422),
                default     => max(0, Http::inteiro($dados, $campo)),
            };
        }
        if (!$campos) {
            Http::falhar('Nada para atualizar.', 422);
        }

        // Os nomes das colunas vêm da lista fixa acima, nunca do usuário.
        $trechos = implode(', ', array_map(fn ($coluna) => "$coluna = ?", array_keys($campos)));
        Conexao::executar("UPDATE partida SET $trechos WHERE id_partida = ?", [...array_values($campos), $id]);
        Http::responder(self::buscar($id));
    }

    // ---------- Organizador: quem pontuou ----------

    public static function pontuar(int $id): never
    {
        $partida = self::buscar($id);
        if ($partida['status'] === 'agendada') {
            Http::falhar('Inicie a partida antes de lançar pontos.', 409);
        }

        $dados = Http::corpo();
        $jogador = Conexao::achar('jogador', Http::inteiro($dados, 'id_jogador'));
        if (!in_array($jogador['id_time'], [$partida['id_time_casa'], $partida['id_time_fora']], true)) {
            Http::falhar('Esse jogador não é de nenhum dos times da partida.', 422);
        }

        $pontos = isset($dados['pontos']) ? Http::inteiro($dados, 'pontos') : 1;
        if ($pontos < 1 || $pontos > 10) {
            Http::falhar('Os pontos precisam estar entre 1 e 10.', 422);
        }

        Conexao::inserir(
            'INSERT INTO pontuacao (id_partida, id_time, id_jogador, pontos) VALUES (?, ?, ?, ?)',
            [$id, $jogador['id_time'], $jogador['id_jogador'], $pontos]
        );
        self::recalcularPlacar($id);
        Http::responder(self::buscar($id), 201);
    }

    public static function removerPontuacao(int $id): never
    {
        $pontuacao = Conexao::achar('pontuacao', $id);
        Conexao::executar('DELETE FROM pontuacao WHERE id_pontuacao = ?', [$id]);
        self::recalcularPlacar($pontuacao['id_partida']);
        Http::responder(['ok' => true]);
    }

    // ---------- Internos ----------

    private static function buscar(int $id): array
    {
        return Conexao::um(self::SQL . ' WHERE p.id_partida = ?', [$id]) ?? Http::falhar('Partida não encontrada.', 404);
    }

    /** O placar é sempre a soma das pontuações de cada time. */
    private static function recalcularPlacar(int $id): void
    {
        Conexao::executar(
            'UPDATE partida SET
                placar_casa = (SELECT COALESCE(SUM(pontos), 0) FROM pontuacao WHERE id_partida = partida.id_partida AND id_time = partida.id_time_casa),
                placar_fora = (SELECT COALESCE(SUM(pontos), 0) FROM pontuacao WHERE id_partida = partida.id_partida AND id_time = partida.id_time_fora)
              WHERE id_partida = ?',
            [$id]
        );
    }
}
