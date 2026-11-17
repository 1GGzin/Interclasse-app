# Interclasse — versão otimizada

Jogadores consultam jogos, placares e quem pontuou. Organizadores agendam partidas, atualizam o placar e montam os times.
**PHP 8.1+** (com `pdo_sqlite`) e **SQLite**: o banco é criado sozinho no primeiro acesso.

## Rodar
```bash
php -S localhost:8000 -t public
```
Abra http://localhost:8000 — organizador inicial: `admin@escola.com` / `admin123`
(para outra senha, edite `Conexao::criarBanco()` antes do primeiro acesso; se já acessou, apague `dados/interclasse.sqlite`).
Em hospedagem Apache: aponte o site para `public/` e dê permissão de escrita à pasta `dados/`.

## Estrutura
```
public/  index.html, estilo.css, app.js (front)  ·  api.php (entrada e lista de rotas)
src/     Conexao (banco) · Http (resposta/validação/acesso) · Auth · Cadastros · Partidas
sql/     interclasse.sql (tabelas: usuario, modalidade, time, jogador, partida, pontuacao)
```
Tabelas e colunas seguem o padrão `id_<tabela>`. O placar da partida é a soma das `pontuacao` de cada time.

## Rotas (`api.php/<rota>`; L = logado · O = organizador)
| Rota | Quem | Faz |
|---|---|---|
| `POST /entrar` `/cadastrar` `/sair` · `GET /eu` | todos | login, conta de jogador, sair, quem sou eu |
| `POST /organizadores` | O | cria organizador |
| `GET /modalidades` · `GET /times` (`?id_modalidade=`) · `GET /times/{id}` | L | consulta |
| `GET /partidas` (`?status=&id_modalidade=`) · `GET /partidas/{id}` | L | jogos; detalhe com jogadores e quem pontuou |
| `POST /modalidades` `/times` `/jogadores` `/partidas` | O | cria (partida: `id_modalidade, id_time_casa, id_time_fora, data_hora, local`) |
| `PUT /partidas/{id}` | O | muda `data_hora, local, status, placar_casa, placar_fora` (só o enviado) |
| `POST /partidas/{id}/pontuacoes` · `DELETE /pontuacoes/{id}` | O | lança / desfaz pontuação |
| `DELETE /modalidades/{id}` `/times/{id}` `/jogadores/{id}` `/partidas/{id}` | O | remove |

No front, botões e formulários com `data-envia="MÉTODO /rota"` chamam essas rotas sozinhos (veja `enviar()` em `app.js`).

> Não consegui executar o PHP onde escrevi o código: as consultas foram testadas no SQLite e o JavaScript teve a sintaxe verificada. Rode uma vez e me avise se algo falhar.
