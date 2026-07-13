/**
 * Host UI — polling cada 1.5s + controles de sala.
 */
(function () {
  const root = document.getElementById('host-app');
  if (!root) return;

  const csrf = root.dataset.csrf;
  const mode = root.dataset.mode || 'quiz';
  const urls = {
    state: root.dataset.stateUrl,
    start: root.dataset.startUrl,
    next: root.dataset.nextUrl,
    finish: root.dataset.finishUrl,
  };

  const els = {
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

  async function postAction(url) {
    const res = await fetch(url, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrf,
        'X-Requested-With': 'XMLHttpRequest',
      },
    });
    if (!res.ok) {
      const data = await res.json().catch(() => ({}));
      alert(data.message || 'No se pudo completar la acción.');
      return null;
    }
    return res.json();
  }

  function renderMatch(match) {
    if (!match || mode !== 'match') return;
    if (els.matchHomeName) els.matchHomeName.textContent = match.home.name;
    if (els.matchAwayName) els.matchAwayName.textContent = match.away.name;
    if (els.matchHomeGoals) els.matchHomeGoals.textContent = match.home.goals;
    if (els.matchAwayGoals) els.matchAwayGoals.textContent = match.away.goals;

    if (els.matchWinner) {
      if (match.winner === 'home') {
        els.matchWinner.hidden = false;
        els.matchWinner.textContent = 'Ganador: ' + match.home.name;
      } else if (match.winner === 'away') {
        els.matchWinner.hidden = false;
        els.matchWinner.textContent = 'Ganador: ' + match.away.name;
      } else if (match.winner === 'draw') {
        els.matchWinner.hidden = false;
        els.matchWinner.textContent = 'Empate';
      } else {
        els.matchWinner.hidden = true;
        els.matchWinner.textContent = '';
      }
    }
  }

  function renderScoreboard(rows) {
    els.scoreboard.innerHTML = (rows || [])
      .map((r, i) => `<li><span>${i + 1}. ${escapeHtml(r.nickname)}</span><strong>${r.score}</strong></li>`)
      .join('');
  }

  function teamLabel(side) {
    if (side === 'home') return 'Local';
    if (side === 'away') return 'Visitante';
    return '';
  }

  function renderPlayers(players) {
    els.playerList.innerHTML = (players || [])
      .map((p) => {
        const team = p.team_name || teamLabel(p.team_side);
        const badge = team ? ` <span class="team-chip team-${p.team_side || ''}">${escapeHtml(team)}</span>` : '';
        return `<li>${escapeHtml(p.nickname)}${badge}</li>`;
      })
      .join('') || '<li class="muted">Nadie aún</li>';
  }

  function startCountdown(startedAt, timeLimit) {
    if (countdownTimer) clearInterval(countdownTimer);
    const start = new Date(startedAt).getTime();
    const limit = (timeLimit || 30) * 1000;

    function tick() {
      const left = Math.max(0, Math.ceil((start + limit - Date.now()) / 1000));
      els.countdown.textContent = left + 's';
      if (left <= 0) clearInterval(countdownTimer);
    }
    tick();
    countdownTimer = setInterval(tick, 250);
  }

  function renderQuestion(state) {
    const q = state.question;
    if (!q) return;

    els.prompt.textContent = q.prompt;
    els.progress.textContent = state.question_index
      ? `Pregunta ${state.question_index} / ${state.total_questions}`
      : '';
    els.answered.textContent = `${state.answered_count} / ${state.players_count} respondieron`;

    const colors = ['answer-red', 'answer-blue', 'answer-yellow', 'answer-green'];
    els.answers.innerHTML = (q.answers || [])
      .map((a, i) => {
        const correct = a.is_correct ? ' is-correct-host' : '';
        return `<div class="host-answer ${colors[i % 4]}${correct}">${escapeHtml(a.text)}</div>`;
      })
      .join('');

    if (q.id !== lastQuestionId) {
      lastQuestionId = q.id;
      startCountdown(q.started_at, q.time_limit);
    } else {
      els.answered.textContent = `${state.answered_count} / ${state.players_count} respondieron`;
    }
  }

  function applyState(state) {
    renderScoreboard(state.scoreboard);
    renderPlayers(state.players);
    renderMatch(state.match);

    els.btnStart.hidden = state.status !== 'lobby';
    els.btnNext.hidden = state.status !== 'active';
    els.btnFinish.hidden = state.status === 'finished';

    els.lobby.hidden = state.status !== 'lobby';
    els.question.hidden = state.status !== 'active';
    els.finished.hidden = state.status !== 'finished';

    if (state.status === 'active') {
      renderQuestion(state);
    }
    if (state.status === 'lobby') {
      lastQuestionId = null;
    }
  }

  async function poll() {
    try {
      const res = await fetch(urls.state, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });
      if (res.ok) applyState(await res.json());
    } catch (e) {
      // silencioso en polling
    }
  }

  els.btnStart.addEventListener('click', async () => {
    const state = await postAction(urls.start);
    if (state) applyState(state);
  });
  els.btnNext.addEventListener('click', async () => {
    const state = await postAction(urls.next);
    if (state) applyState(state);
  });
  els.btnFinish.addEventListener('click', async () => {
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
