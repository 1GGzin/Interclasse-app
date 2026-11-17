<?php
declare(strict_types=1);

/* Login, cadastro e contas. */
final class Auth
{
    public static function entrar(): never
    {
        $dados = Http::corpo();
        $usuario = Conexao::um('SELECT * FROM usuario WHERE email = ?', [strtolower(Http::texto($dados, 'email'))]);

        if (!$usuario || !password_verify(Http::texto($dados, 'senha'), $usuario['senha'])) {
            Http::falhar('E-mail ou senha incorretos.', 401);
        }
        session_regenerate_id(true);
        Http::responder($_SESSION['usuario'] = [
            'id_usuario' => $usuario['id_usuario'], 'nome' => $usuario['nome'], 'papel' => $usuario['papel'],
        ]);
    }

    public static function sair(): never
    {
        session_destroy();
        Http::responder(null);
    }

    /** Quem está logado (o front pergunta isto ao abrir a página). */
    public static function eu(): never
    {
        Http::responder(Http::usuario());
    }

    /** Cadastro aberto: sempre conta de jogador, já deixa logado. */
    public static function cadastrar(): never
    {
        self::criarUsuario('jogador');
        self::entrar();
    }

    public static function criarOrganizador(): never
    {
        Http::responder(['id_usuario' => self::criarUsuario('organizador')], 201);
    }

    private static function criarUsuario(string $papel): int
    {
        $dados = Http::corpo();
        $email = strtolower(Http::texto($dados, 'email'));
        $senha = Http::texto($dados, 'senha');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($senha) < 6) {
            Http::falhar('Informe um e-mail válido e uma senha de pelo menos 6 caracteres.', 422);
        }
        return Conexao::inserir(
            'INSERT INTO usuario (nome, email, senha, papel) VALUES (?, ?, ?, ?)',
            [Http::texto($dados, 'nome'), $email, password_hash($senha, PASSWORD_DEFAULT), $papel]
        );
    }
}
