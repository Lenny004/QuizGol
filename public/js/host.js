/**
 * UI del anfitrión / proyector (host.js)
 *
 * - Polling del estado cada 1.5s (GET rooms.state)
 * - Controles: iniciar, siguiente pregunta, finalizar
 * - Muestra lista de jugadores, pregunta actual y marcador (mode=match)
 */
(function () {
  const root = document.getElementById('host-app');
  if (!root) return;

  const csrfToken = root.dataset.csrf;
  const gameMode = root.dataset.mode || 'quiz';
  const urls = {
    state: root.dataset.stateUrl,
    start: root.dataset.startUrl,
    next: root.dataset.nextUrl,
    finish: root.dataset.finishUrl,
  };

  const elements = {
    lobby: document.getElementById('host-lobby'),
    question: document.getElementById('host-question'),
    finished: document.getElementById('host-finished'),
    playerList: document.getElementById('host-player-list'),
    scoreboard: document.getElementById('host-scoreboard'),
    prompt: document.getElementById('host-prompt'),
    answers: document.getElementById('host-answers'),
    answered: document.getElementById('host-answered'),
    countdown: document.getElementById('host-countdown'),
    progress: document.getElementById('host-q-progress'),
    btnStart: document.getElementById('btn-start'),
    btnNext: document.getElementById('btn-next'),
    btnFinish: document.getElementById('btn-finish'),
    matchHomeName: document.getElementById('match-home-name'),
    matchAwayName: document.getElementById('match-away-name'),
    matchHomeGoals: document.getElementById('match-home-goals'),
    matchAwayGoals: document.getElementById('match-away-goals'),
    matchWinner: document.getElementById('host-match-winner'),
  };

  let countdownTimer = null;
  let lastQuestionId = null;

  /** POST a start/next/finish; devuelve el estado host o null si falló. */
  async function postAction(url) {
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrfToken,
        'X-Requested-With': 'XMLHttpRequest',
      },
    });
    if (!response.ok) {
      const data = await response.json().catch(() => ({}));
      alert(data.message || 'No se pudo completar la acción.');
      return null;
    }
    return response.json();
  }

  /** Actualiza marcador Local vs Visitante. */
  function renderMatch(match) {
    if (!match || gameMode !== 'match') return;
    if (elements.matchHomeName) elements.matchHomeName.textContent = match.home.name;
    if (elements.matchAwayName) elements.matchAwayName.textContent = match.away.name;
    if (elements.matchHomeGoals) elements.matchHomeGoals.textContent = match.home.goals;
    if (elements.matchAwayGoals) elements.matchAwayGoals.textContent = match.away.goals;

    if (elements.matchWinner) {
      if (match.winner === 'home') {
        elements.matchWinner.hidden = false;
        elements.matchWinner.textContent = 'Ganador: ' + match.home.name;
      } else if (match.winner === 'away') {
        elements.matchWinner.hidden = false;
        elements.matchWinner.textContent = 'Ganador: ' + match.away.name;
      } else if (match.winner === 'draw') {
        elements.matchWinner.hidden = false;
        elements.matchWinner.textContent = 'Empate';
      } else {
        elements.matchWinner.hidden = true;
        elements.matchWinner.textContent = '';
      }
    }
  }

  function renderScoreboard(rows) {
    elements.scoreboard.innerHTML = (rows || [])
      .map((row, index) =>
        `<li><span>${index + 1}. ${escapeHtml(row.nickname)}</span><strong>${row.score}</strong></li>`
      )
      .join('');
  }

  function teamLabel(side) {
    if (side === 'home') return 'Local';
    if (side === 'away') return 'Visitante';
    return '';
  }

  /** Lista de jugadores en lobby (con chip de equipo si aplica). */
  function renderPlayers(players) {
    elements.playerList.innerHTML = (players || [])
      .map((player) => {
        const teamName = player.team_name || teamLabel(player.team_side);
        const badge = teamName
          ? ` <span class="team__chip team__chip--${player.team_side || ''}">${escapeHtml(teamName)}</span>`
          : '';
        return `<li>${escapeHtml(player.nickname)}${badge}</li>`;
      })
      .join('') || '<li class="text--muted">Nadie aún</li>';
  }

  function startCountdown(startedAt, timeLimit) {
    if (countdownTimer) clearInterval(countdownTimer);
    const startMs = new Date(startedAt).getTime();
    const limitMs = (timeLimit || 30) * 1000;

    function tick() {
      const secondsLeft = Math.max(0, Math.ceil((startMs + limitMs - Date.now()) / 1000));
      elements.countdown.textContent = secondsLeft + 's';
      if (secondsLeft <= 0) clearInterval(countdownTimer);
    }
    tick();
    countdownTimer = setInterval(tick, 250);
  }

  /** Pregunta actual + opciones (el host sí ve cuál es correcta). */
  function renderQuestion(state) {
    const question = state.question;
    if (!question) return;

    elements.prompt.textContent = question.prompt;
    elements.progress.textContent = state.question_index
      ? `Pregunta ${state.question_index} / ${state.total_questions}`
      : '';
    elements.answered.textContent = `${state.answered_count} / ${state.players_count} respondieron`;

    const answerColors = [
      'host__answer--red',
      'host__answer--blue',
      'host__answer--yellow',
      'host__answer--green',
    ];
    elements.answers.innerHTML = (question.answers || [])
      .map((answer, index) => {
        const correctClass = answer.is_correct ? ' host__answer--correct' : '';
        return `<div class="host__answer ${answerColors[index % 4]}${correctClass}">${escapeHtml(answer.text)}</div>`;
      })
      .join('');

    if (question.id !== lastQuestionId) {
      lastQuestionId = question.id;
      startCountdown(question.started_at, question.time_limit);
    } else {
      elements.answered.textContent = `${state.answered_count} / ${state.players_count} respondieron`;
    }
  }

  /** Aplica el JSON de estado a la UI del host. */
  function applyState(state) {
    renderScoreboard(state.scoreboard);
    renderPlayers(state.players);
    renderMatch(state.match);

    elements.btnStart.hidden = state.status !== 'lobby';
    elements.btnNext.hidden = state.status !== 'active';
    elements.btnFinish.hidden = state.status === 'finished';

    elements.lobby.hidden = state.status !== 'lobby';
    elements.question.hidden = state.status !== 'active';
    elements.finished.hidden = state.status !== 'finished';

    if (state.status === 'active') {
      renderQuestion(state);
    }
    if (state.status === 'lobby') {
      lastQuestionId = null;
    }
  }

  async function poll() {
    try {
      const response = await fetch(urls.state, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });
      if (response.ok) applyState(await response.json());
    } catch (error) {
      // Silencioso en polling.
    }
  }

  elements.btnStart.addEventListener('click', async () => {
    const state = await postAction(urls.start);
    if (state) applyState(state);
  });
  elements.btnNext.addEventListener('click', async () => {
    const state = await postAction(urls.next);
    if (state) applyState(state);
  });
  elements.btnFinish.addEventListener('click', async () => {
    if (!confirm('¿Finalizar el partido?')) return;
    const state = await postAction(urls.finish);
    if (state) applyState(state);
  });

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  poll();
  setInterval(poll, 1500);
})();
