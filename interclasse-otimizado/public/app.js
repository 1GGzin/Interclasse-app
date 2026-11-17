/*
 * FRONT-END — uma página só; as telas mudam conforme o endereço (#/jogos, #/jogo/3 ...).
 *
 *   1. api()                 conversa com o servidor
 *   2. tela...()             busca os dados e desenha o HTML
 *   3. data-envia="..."      botões e formulários que chamam a API sozinhos (veja enviar())
 *
 * Tela nova: escreva tela...(), registre em rotear() e ponha um link no index.html.
 */

const app = document.getElementById('app');
let usuario = null;                                     // quem está logado (ou null)
let filtro = { status: 'agendada', id_modalidade: '' };  // filtro da tela de jogos
let timer = null;                                       // atualização automática do placar ao vivo
let todosOsTimes = [];                                  // usado na tela de agendar partida

const organizador = () => usuario?.papel === 'organizador';
const ROTULO = { agendada: 'Agendada', ao_vivo: 'AO VIVO', encerrada: 'Encerrada' };

/* ---------- Servidor ---------- */

async function api(metodo, rota, corpo) {
  const resposta = await fetch('api.php' + rota, {
    method: metodo,
    headers: corpo ? { 'Content-Type': 'application/json' } : {},
    body: corpo ? JSON.stringify(corpo) : undefined,
  });
  const dados = await resposta.json().catch(() => null);
  if (!resposta.ok) throw new Error(dados?.erro || 'Algo deu errado.');
  return dados;
}

/* ---------- Peças de HTML ---------- */

// Texto vindo do servidor sempre passa por esc() (evita código malicioso na página).
const esc = (t) => String(t ?? '').replace(/[&<>"']/g, (c) =>
  ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

const quando = (s) => new Date(s.replace(' ', 'T')).toLocaleString('pt-BR',
  { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });

const vazio = (texto) => `<p class="vazio">${texto}</p>`;
const lista = (itens, linha, textoVazio) => itens.length ? itens.map(linha).join('') : vazio(textoVazio);
const opcoes = (itens, id, rotulo = 'nome') =>
  itens.map((i) => `<option value="${i[id]}">${esc(typeof rotulo === 'function' ? rotulo(i) : i[rotulo])}</option>`).join('');
const campo = (rotulo, nome, tipo = 'text', extra = 'required') =>
  `<div><label>${rotulo}</label><input name="${nome}" type="${tipo}" ${extra}></div>`;

/* Botão que chama a API. Opções: corpo (JSON), confirma (pergunta), vaipara (endereço depois), classe */
const botao = (texto, envia, { classe = '', corpo, confirma, vaipara } = {}) =>
  `<button class="botao ${classe}" data-envia="${envia}"${corpo ? ` data-corpo='${JSON.stringify(corpo)}'` : ''}` +
  `${confirma ? ` data-confirma="${confirma}"` : ''}${vaipara ? ` data-vaipara="${vaipara}"` : ''}>${texto}</button>`;

const cartaoPartida = (p) => `
  <a class="cartao" href="#/jogo/${p.id_partida}">
    <div class="linha">
      <small>${esc(p.modalidade)} · ${quando(p.data_hora)}${p.local ? ' · ' + esc(p.local) : ''}</small>
      <span class="selo ${p.status}">${ROTULO[p.status]}</span>
    </div>
    <div class="confronto"><span>${esc(p.time_casa)}</span>
      <strong>${p.status === 'agendada' ? 'x' : `${p.placar_casa} x ${p.placar_fora}`}</strong>
      <span>${esc(p.time_fora)}</span></div>
  </a>`;

const jogadoresDoTime = (nome, jogadores) => `
  <div class="cartao" style="flex:1"><strong>${esc(nome)}</strong>
    ${lista(jogadores, (j) => `<div>${j.numero ?? '–'} · ${esc(j.nome)}</div>`, 'Sem jogadores.')}</div>`;

/* ---------- Rotas ---------- */

async function rotear() {
  clearInterval(timer);
  document.body.classList.toggle('logado', !!usuario);
  document.body.classList.toggle('organizador', organizador());
  document.getElementById('nome-usuario').textContent = usuario?.nome ?? '';
  if (!usuario) return telaLogin();

  const hash = location.hash || '#/jogos';
  const id = hash.split('/')[2];
  try {
    if (hash === '#/jogos') await telaJogos();
    else if (hash.startsWith('#/jogo/')) await telaJogo(id);
    else if (hash === '#/times') await telaTimes();
    else if (hash.startsWith('#/time/')) await telaTime(id);
    else if (hash === '#/gerenciar' && organizador()) await telaGerenciar();
    else app.innerHTML = vazio('Página não encontrada.');
  } catch (erro) {
    app.innerHTML = vazio(esc(erro.message));
  }
}

/* ---------- Telas ---------- */

function telaLogin() {
  app.innerHTML = `
    <div class="entrada">
      <h1>Entrar</h1>
      <form class="cartao" data-envia="POST /entrar" data-sessao data-vaipara="#/jogos">
        ${campo('E-mail', 'email', 'email')}${campo('Senha', 'senha', 'password')}
        <button class="botao">Entrar</button>
      </form>
      <details class="cartao"><summary>Primeiro acesso? Criar conta de jogador</summary>
        <form data-envia="POST /cadastrar" data-sessao data-vaipara="#/jogos" style="display:grid;gap:.7rem;margin-top:.8rem">
          ${campo('Nome', 'nome')}${campo('E-mail', 'email', 'email')}${campo('Senha (mín. 6)', 'senha', 'password', 'minlength=6 required')}
          <button class="botao">Criar conta</button>
        </form>
      </details>
    </div>`;
}

/* Jogos: abas Próximos / Ao vivo / Resultados + filtro por modalidade */
async function telaJogos() {
  const consulta = new URLSearchParams({ status: filtro.status });
  if (filtro.id_modalidade) consulta.set('id_modalidade', filtro.id_modalidade);
  const [modalidades, partidas] = await Promise.all([api('GET', '/modalidades'), api('GET', '/partidas?' + consulta)]);

  const aba = (status, texto) => `<button class="${filtro.status === status ? 'ativa' : ''}" data-status="${status}">${texto}</button>`;
  app.innerHTML = `
    <h1>Jogos</h1>
    <div class="abas">${aba('agendada', 'Próximos')}${aba('ao_vivo', 'Ao vivo')}${aba('encerrada', 'Resultados')}</div>
    <div style="max-width:240px;margin-bottom:1rem"><label>Modalidade</label>
      <select data-ao-mudar="modalidade"><option value="">Todas</option>${opcoes(modalidades, 'id_modalidade')}</select></div>
    ${lista(partidas, cartaoPartida, 'Nenhum jogo aqui.')}`;

  app.querySelector('select').value = filtro.id_modalidade;
  if (filtro.status === 'ao_vivo') timer = setInterval(rotear, 10000);
}

/* Jogo: placar, quem pontuou, jogadores — e os controles do organizador */
async function telaJogo(id) {
  const p = await api('GET', '/partidas/' + id);
  const nomeDoTime = (idTime) => (idTime === p.id_time_casa ? p.time_casa : p.time_fora);

  app.innerHTML = `
    <p><a href="#/jogos">← Jogos</a></p>
    <div class="cartao">
      <div class="linha">
        <small>${esc(p.modalidade)} · ${quando(p.data_hora)}${p.local ? ' · ' + esc(p.local) : ''}</small>
        <span class="selo ${p.status}">${ROTULO[p.status]}</span>
      </div>
      <div class="confronto grande"><span>${esc(p.time_casa)}</span>
        <strong>${p.placar_casa} x ${p.placar_fora}</strong><span>${esc(p.time_fora)}</span></div>
    </div>

    <h2>Quem pontuou</h2>
    <div class="cartao">${lista(p.pontuacoes, (x) => `
      <div class="item">
        <span><strong>${esc(x.jogador ?? 'Jogador removido')}</strong> <small>(${esc(nomeDoTime(x.id_time))})</small> +${x.pontos}</span>
        ${organizador() ? botao('Desfazer', 'DELETE /pontuacoes/' + x.id_pontuacao, { classe: 'pequeno perigo' }) : ''}
      </div>`, 'Ninguém pontuou ainda.')}</div>

    <h2>Jogadores</h2>
    <div class="linha" style="align-items:flex-start">
      ${jogadoresDoTime(p.time_casa, p.jogadores_casa)}${jogadoresDoTime(p.time_fora, p.jogadores_fora)}
    </div>
    ${organizador() ? controlesDoJogo(p) : ''}`;

  // Jogadores veem o placar mudar sozinho. O organizador não, para não perder o que está digitando.
  if (p.status === 'ao_vivo' && !organizador()) timer = setInterval(rotear, 10000);
}

/* Só o organizador vê isto */
function controlesDoJogo(p) {
  const rota = '/partidas/' + p.id_partida;
  const [status, texto, classe] = {
    agendada:  ['ao_vivo', 'Iniciar jogo', 'ok'],
    ao_vivo:   ['encerrada', 'Encerrar jogo', 'ok'],
    encerrada: ['ao_vivo', 'Reabrir jogo', 'claro'],
  }[p.status];

  return `
    <h2>Controle do jogo</h2>
    <div class="cartao linha" style="justify-content:flex-start">
      ${botao(texto, 'PUT ' + rota, { classe, corpo: { status } })}
      ${botao('Excluir partida', 'DELETE ' + rota, { classe: 'perigo', confirma: 'Excluir esta partida?', vaipara: '#/jogos' })}
    </div>

    ${p.status === 'agendada' ? '' : `
    <form class="cartao" data-envia="POST ${rota}/pontuacoes">
      <strong>Registrar pontuação</strong>
      <div class="campos">
        <div><label>Jogador</label><select name="id_jogador" required>
          <optgroup label="${esc(p.time_casa)}">${opcoes(p.jogadores_casa, 'id_jogador')}</optgroup>
          <optgroup label="${esc(p.time_fora)}">${opcoes(p.jogadores_fora, 'id_jogador')}</optgroup></select></div>
        ${campo('Pontos', 'pontos', 'number', 'min=1 max=10 value=1 required')}
      </div>
      <button class="botao">Pontuar</button>
    </form>

    <form class="cartao" data-envia="PUT ${rota}">
      <strong>Corrigir placar manualmente</strong>
      <div class="campos">
        ${campo(esc(p.time_casa), 'placar_casa', 'number', `min=0 value=${p.placar_casa} required`)}
        ${campo(esc(p.time_fora), 'placar_fora', 'number', `min=0 value=${p.placar_fora} required`)}
      </div>
      <small>Registrar uma pontuação recalcula o placar a partir das pontuações.</small>
      <button class="botao claro">Salvar placar</button>
    </form>`}

    <form class="cartao" data-envia="PUT ${rota}">
      <strong>Remarcar</strong>
      <div class="campos">
        ${campo('Data e hora', 'data_hora', 'datetime-local', `value="${p.data_hora.replace(' ', 'T')}" required`)}
        ${campo('Local', 'local', 'text', `value="${esc(p.local ?? '')}"`)}
      </div>
      <button class="botao claro">Salvar</button>
    </form>`;
}

async function telaTimes() {
  const times = await api('GET', '/times');
  const modalidades = [...new Set(times.map((t) => t.modalidade))];

  app.innerHTML = '<h1>Times</h1>' + (times.length
    ? modalidades.map((modalidade) => `<h2>${esc(modalidade)}</h2>` + times.filter((t) => t.modalidade === modalidade)
        .map((t) => `<a class="cartao" href="#/time/${t.id_time}">${esc(t.nome)}</a>`).join('')).join('')
    : vazio('Nenhum time cadastrado.'));
}

/* Time e seus jogadores (o organizador adiciona e remove aqui) */
async function telaTime(id) {
  const t = await api('GET', '/times/' + id);

  app.innerHTML = `
    <p><a href="#/times">← Times</a></p>
    <h1>${esc(t.nome)} <small>· ${esc(t.modalidade)}</small></h1>
    <div class="cartao">${lista(t.jogadores, (j) => `
      <div class="item"><span>${j.numero ?? '–'} · ${esc(j.nome)}</span>
        ${organizador() ? botao('Remover', 'DELETE /jogadores/' + j.id_jogador, { classe: 'pequeno perigo', confirma: 'Remover este jogador?' }) : ''}
      </div>`, 'Nenhum jogador neste time.')}</div>
    ${organizador() ? `
      <form class="cartao" data-envia="POST /jogadores">
        <strong>Adicionar jogador</strong>
        <input type="hidden" name="id_time" value="${t.id_time}">
        <div class="campos">${campo('Nome', 'nome')}${campo('Número', 'numero', 'number', 'min=0')}</div>
        <button class="botao">Adicionar</button>
      </form>
      ${botao('Excluir time', 'DELETE /times/' + t.id_time, { classe: 'perigo', confirma: 'Excluir este time?', vaipara: '#/times' })}` : ''}`;
}

/* Cadastros do organizador */
async function telaGerenciar() {
  const [modalidades, times] = await Promise.all([api('GET', '/modalidades'), api('GET', '/times')]);
  todosOsTimes = times;
  const seletorModalidade = `<div><label>Modalidade</label><select name="id_modalidade" required>${opcoes(modalidades, 'id_modalidade')}</select></div>`;

  app.innerHTML = `
    <h1>Gerenciar</h1>

    <h2>Agendar partida</h2>
    <form class="cartao" data-envia="POST /partidas" data-vaipara="#/jogo/{id_partida}">
      <div class="campos">
        ${seletorModalidade.replace('<select', '<select data-ao-mudar="times"')}
        <div><label>Time da casa</label><select name="id_time_casa" required></select></div>
        <div><label>Time visitante</label><select name="id_time_fora" required></select></div>
      </div>
      <div class="campos">${campo('Data e hora', 'data_hora', 'datetime-local')}${campo('Local', 'local', 'text', '')}</div>
      <button class="botao">Agendar</button>
    </form>

    <h2>Modalidades</h2>
    <div class="cartao">
      ${modalidades.map((m) => `<div class="item"><span>${esc(m.nome)}</span>
        ${botao('Remover', 'DELETE /modalidades/' + m.id_modalidade, { classe: 'pequeno perigo', confirma: 'Remover esta modalidade?' })}</div>`).join('')}
      <form class="linha" data-envia="POST /modalidades" style="margin-top:.6rem">
        <input name="nome" placeholder="Nova modalidade" required style="flex:1"><button class="botao">Criar</button>
      </form>
    </div>

    <h2>Novo time</h2>
    <form class="cartao" data-envia="POST /times">
      <div class="campos">${seletorModalidade}${campo('Nome do time', 'nome')}</div>
      <button class="botao">Criar time</button>
      <small>Para adicionar jogadores, abra o time em “Times”.</small>
    </form>

    <h2>Novo organizador</h2>
    <form class="cartao" data-envia="POST /organizadores">
      <div class="campos">${campo('Nome', 'nome')}${campo('E-mail', 'email', 'email')}${campo('Senha (mín. 6)', 'senha', 'password', 'minlength=6 required')}</div>
      <button class="botao">Criar conta</button>
    </form>`;

  aoMudar.times(app.querySelector('[data-ao-mudar="times"]'));
}

/* ---------- Ações ---------- */

/* Qualquer botão ou formulário com data-envia="MÉTODO /rota" chama a API.
   Opcionais: data-confirma (pergunta antes), data-sessao (a resposta é o usuário logado),
   data-vaipara (endereço depois; {campo} usa a resposta). Sem vaipara, a tela é recarregada. */
async function enviar(el, corpo) {
  if (el.dataset.confirma && !confirm(el.dataset.confirma)) return;
  const [metodo, rota] = el.dataset.envia.split(' ');
  const resposta = await api(metodo, rota, corpo);

  if ('sessao' in el.dataset) usuario = resposta;
  const destino = el.dataset.vaipara?.replace(/\{(\w+)\}/g, (_, campo) => resposta[campo]);
  if (destino && destino !== location.hash) location.hash = destino;
  else rotear();
}

// Mudanças em <select data-ao-mudar="...">
const aoMudar = {
  modalidade(el) {
    filtro.id_modalidade = el.value;
    rotear();
  },
  // Na tela de agendar: mostra só os times da modalidade escolhida
  times(el) {
    const times = todosOsTimes.filter((t) => String(t.id_modalidade) === el.value);
    const html = times.length ? opcoes(times, 'id_time') : '<option value="">(crie times nesta modalidade)</option>';
    app.querySelector('[name=id_time_casa]').innerHTML = html;
    app.querySelector('[name=id_time_fora]').innerHTML = html;
    if (times.length > 1) app.querySelector('[name=id_time_fora]').selectedIndex = 1;
  },
};

const tentar = async (funcao) => {
  try {
    await funcao();
  } catch (erro) {
    const aviso = document.getElementById('aviso');
    aviso.textContent = erro.message;
    aviso.hidden = false;
    setTimeout(() => { aviso.hidden = true; }, 4000);
  }
};

document.addEventListener('click', (e) => {
  const aba = e.target.closest('[data-status]');
  if (aba) { filtro.status = aba.dataset.status; rotear(); }

  const botaoApi = e.target.closest('button[data-envia]');
  if (botaoApi) tentar(() => enviar(botaoApi, botaoApi.dataset.corpo && JSON.parse(botaoApi.dataset.corpo)));
});

document.addEventListener('submit', (e) => {
  const formulario = e.target.closest('form[data-envia]');
  if (!formulario) return;
  e.preventDefault();
  tentar(() => enviar(formulario, Object.fromEntries(new FormData(formulario))));
});

document.addEventListener('change', (e) => aoMudar[e.target.dataset.aoMudar]?.(e.target));
window.addEventListener('hashchange', rotear);

// Ao abrir a página: descobre se já existe login ativo
api('GET', '/eu').then((u) => { usuario = u; }).catch(() => {}).finally(rotear);
