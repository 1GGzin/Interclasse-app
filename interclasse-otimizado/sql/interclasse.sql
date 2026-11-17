-- Banco do Interclasse (SQLite). Executado sozinho no primeiro acesso.

CREATE TABLE usuario (
    id_usuario INTEGER PRIMARY KEY AUTOINCREMENT,
    nome       TEXT NOT NULL,
    email      TEXT NOT NULL UNIQUE,
    senha      TEXT NOT NULL,
    papel      TEXT NOT NULL DEFAULT 'jogador' CHECK (papel IN ('jogador', 'organizador'))
);

CREATE TABLE modalidade (
    id_modalidade INTEGER PRIMARY KEY AUTOINCREMENT,
    nome          TEXT NOT NULL UNIQUE
);

CREATE TABLE time (
    id_time       INTEGER PRIMARY KEY AUTOINCREMENT,
    id_modalidade INTEGER NOT NULL REFERENCES modalidade (id_modalidade),
    nome          TEXT NOT NULL,
    UNIQUE (id_modalidade, nome)
);

CREATE TABLE jogador (
    id_jogador INTEGER PRIMARY KEY AUTOINCREMENT,
    id_time    INTEGER NOT NULL REFERENCES time (id_time) ON DELETE CASCADE,
    nome       TEXT NOT NULL,
    numero     INTEGER
);

CREATE TABLE partida (
    id_partida    INTEGER PRIMARY KEY AUTOINCREMENT,
    id_modalidade INTEGER NOT NULL REFERENCES modalidade (id_modalidade),
    id_time_casa  INTEGER NOT NULL REFERENCES time (id_time),
    id_time_fora  INTEGER NOT NULL REFERENCES time (id_time),
    data_hora     TEXT NOT NULL,
    local         TEXT,
    status        TEXT NOT NULL DEFAULT 'agendada' CHECK (status IN ('agendada', 'ao_vivo', 'encerrada')),
    placar_casa   INTEGER NOT NULL DEFAULT 0,
    placar_fora   INTEGER NOT NULL DEFAULT 0,
    CHECK (id_time_casa <> id_time_fora)
);

-- Quem pontuou em cada partida; o placar é a soma destes pontos.
CREATE TABLE pontuacao (
    id_pontuacao INTEGER PRIMARY KEY AUTOINCREMENT,
    id_partida   INTEGER NOT NULL REFERENCES partida (id_partida) ON DELETE CASCADE,
    id_time      INTEGER NOT NULL REFERENCES time (id_time),
    id_jogador   INTEGER REFERENCES jogador (id_jogador) ON DELETE SET NULL,
    pontos       INTEGER NOT NULL DEFAULT 1
);

INSERT INTO modalidade (nome) VALUES ('Futsal'), ('Vôlei');
