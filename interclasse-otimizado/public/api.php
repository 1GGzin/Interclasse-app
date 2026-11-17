<?php
declare(strict_types=1);

/*
 * ENTRADA DA API — endereços no formato api.php/partidas, api.php/partidas/3 ...
 * Rota nova: escreva o método na classe do assunto e adicione uma linha em $rotas.
 */

ini_set('display_errors', '0');
spl_autoload_register(fn (string $classe) => require __DIR__ . "/../src/$classe.php");
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

// Cria a rota de remoção de uma tabela: DELETE /<tabela>/{id}
$remover = fn (string $tabela) => fn (int $id) => Http::responder(Conexao::remover($tabela, $id));

// [método, caminho, o que executar, quem pode]    quem pode: publica | login | organizador
$rotas = [
    ['POST',   '/entrar',                   [Auth::class, 'entrar'],              'publica'],
    ['POST',   '/cadastrar',                [Auth::class, 'cadastrar'],           'publica'],
    ['POST',   '/sair',                     [Auth::class, 'sair'],                'publica'],
    ['GET',    '/eu',                       [Auth::class, 'eu'],                  'publica'],
    ['POST',   '/organizadores',            [Auth::class, 'criarOrganizador'],    'organizador'],

    ['GET',    '/modalidades',              [Cadastros::class, 'modalidades'],    'login'],
    ['POST',   '/modalidades',              [Cadastros::class, 'criarModalidade'], 'organizador'],
    ['DELETE', '/modalidades/{id}',         $remover('modalidade'),               'organizador'],

    ['GET',    '/times',                    [Cadastros::class, 'times'],          'login'],
    ['GET',    '/times/{id}',               [Cadastros::class, 'time'],           'login'],
    ['POST',   '/times',                    [Cadastros::class, 'criarTime'],      'organizador'],
    ['DELETE', '/times/{id}',               $remover('time'),                     'organizador'],

    ['POST',   '/jogadores',                [Cadastros::class, 'criarJogador'],   'organizador'],
    ['DELETE', '/jogadores/{id}',           $remover('jogador'),                  'organizador'],

    ['GET',    '/partidas',                 [Partidas::class, 'listar'],          'login'],
    ['GET',    '/partidas/{id}',            [Partidas::class, 'ver'],             'login'],
    ['POST',   '/partidas',                 [Partidas::class, 'criar'],           'organizador'],
    ['PUT',    '/partidas/{id}',            [Partidas::class, 'atualizar'],       'organizador'],
    ['DELETE', '/partidas/{id}',            $remover('partida'),                  'organizador'],
    ['POST',   '/partidas/{id}/pontuacoes', [Partidas::class, 'pontuar'],         'organizador'],
    ['DELETE', '/pontuacoes/{id}',          [Partidas::class, 'removerPontuacao'], 'organizador'],
];

// Caminho pedido: tudo que vem depois de "api.php"
$url = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$caminho = '/' . trim((string) substr($url, (int) strpos($url, 'api.php') + 7), '/');

try {
    foreach ($rotas as [$metodo, $padrao, $executar, $quemPode]) {
        $expressao = '#^' . str_replace('{id}', '(\d+)', $padrao) . '$#';
        if ($metodo !== $_SERVER['REQUEST_METHOD'] || !preg_match($expressao, $caminho, $achou)) {
            continue;
        }
        if ($quemPode === 'login') {
            Http::exigirLogin();
        } elseif ($quemPode === 'organizador') {
            Http::exigirOrganizador();
        }
        $executar(...array_map('intval', array_slice($achou, 1)));
    }
    Http::falhar('Rota não encontrada.', 404);
} catch (PDOException $erro) {
    $mensagem = $erro->getMessage();
    if (str_contains($mensagem, 'FOREIGN KEY')) {
        Http::falhar('Não é possível remover: existem dados ligados a este item (ex.: o time tem partidas).', 409);
    }
    if (str_contains($mensagem, 'UNIQUE')) {
        Http::falhar('Já existe um registro com esses dados.', 409);
    }
    error_log($mensagem);
    Http::falhar('Erro ao acessar o banco de dados.', 500);
}
