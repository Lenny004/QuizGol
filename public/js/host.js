/**
 * UI del anfitrión / proyector (host.js)
 *
 * Flujo: lobby (QR) → asking → reveal → siguiente (auto o manual)
 */
(function () {
  const root = document.getElementById('host-app');
  if (!root) return;

  const csrfToken = root.dataset.csrf;
  const gameMode = root.dataset.mode || 'quiz';
  const joinUrl = root.dataset.joinUrl;
  const urls = {
    state: root.dataset.stateUrl,
    start: root.dataset.startUrl,
    reveal: root.dataset.revealUrl,
    next: root.dataset.nextUrl,
    finish: root.dataset.finishUrl,
    results: root.dataset.resultsUrl,
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
    phaseBadge: document.getElementById('host-phase-badge'),
    btnStart: document.getElementById('btn-start'),
    btnReveal: document.getElementById('btn-reveal'),
    btnNext: document.getElementById('btn-next'),
    btnFinish: document.getElementById('btn-finish'),
    btnResults: document.getElementById('btn-results'),
    qrCanvas: document.getElementById('host-qr'),
    matchHomeName: document.getElementById('match-home-name'),
    matchAwayName: document.getElementById('match-away-name'),
    matchHomeGoals: document.getElementById('match-home-goals'),
    matchAwayGoals: document.getElementById('match-away-goals'),
    matchWinner: document.getElementById('host-match-winner'),
  };

  let countdownTimer = null;
  let lastQuestionId = null;
  let lastPhase = null;
  let qrDrawn = false;

  function drawQr() {
    if (qrDrawn || !elements.qrCanvas || !joinUrl) return;
    if (typeof QRCode === 'undefined') {
      setTimeout(drawQr, 120);
      return;
    }
    elements.qrCanvas.innerHTML = '';
    new QRCode(elements.qrCanvas, {
      text: joinUrl,
      width: 260,
      height: 260,
      colorDark: '#0B3D2E',
      colorLight: '#F7F3E8',
      correctLevel: QRCode.CorrectLevel.M,
    });
    qrDrawn = true;
  }

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

  function renderPlayers(players) {
    elements.playerList.innerHTML = (players || [])
      .map((player) => {
        const teamName = player.team_name || teamLabel(player.team_side);
        const badge = teamName
          ? ` <span class="team__chip team__chip--${player.team_side || ''}">${escapeHtml(teamName)}</span>`
          : '';
        return `<li>${escapeHtml(player.nickname)}${badge}</li>`;
      })
      .join('') || '<li class="text--muted">Nadie aún — proyecta el QR</li>';
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

  function renderQuestion(state) {
    const question = state.question;
    if (!question) return;

    const isReveal = state.question_phase === 'reveal' || state.phase === 'reveal';

    elements.prompt.textContent = question.prompt;
    elements.progress.textContent = state.question_index
      ? `Pregunta ${state.question_index} / ${state.total_questions}`
      : '';
    elements.answered.textContent = `${state.answered_count} / ${state.players_count} respondieron`;

    if (elements.phaseBadge) {
      if (isReveal) {
        elements.phaseBadge.hidden = false;
        elements.phaseBadge.textContent = 'Revelación';
        elements.countdown.textContent = '✓';
      } else {
        elements.phaseBadge.hidden = true;
      }
    }

    const answerColors = [
      'host__answer--red',
      'host__answer--blue',
      'host__answer--yellow',
      'host__answer--green',
    ];
    elements.answers.innerHTML = (question.answers || [])
      .map((answer, index) => {
        const correctClass = isReveal && answer.is_correct ? ' host__answer--correct' : '';
        const dimClass = isReveal && answer.is_correct === false ? ' host__answer--dim' : '';
        const stats =
          isReveal && answer.count != null
            ? `<span class="host__answer-stat">${answer.count} · ${answer.percent || 0}%</span>`
            : '';
        return (
          `<div class="host__answer ${answerColors[index % 4]}${correctClass}${dimClass}">` +
          `<span>${escapeHtml(answer.text)}</span>${stats}</div>`
        );
      })
      .join('');

    if (question.id !== lastQuestionId || question.phase !== lastPhase) {
      if (question.id !== lastQuestionId && !isReveal) {
        startCountdown(question.started_at, question.time_limit);
      }
      lastQuestionId = question.id;
      lastPhase = question.phase;
    }
  }

  function applyState(state) {
    renderScoreboard(state.scoreboard);
    renderPlayers(state.players);
    renderMatch(state.match);

    const isAsking = state.status === 'active' && state.question_phase === 'asking';
    const isReveal = state.status === 'active' && state.question_phase === 'reveal';

    elements.btnStart.hidden = state.status !== 'lobby';
    elements.btnReveal.hidden = !isAsking;
    elements.btnNext.hidden = !isReveal;
    elements.btnFinish.hidden = state.status === 'finished';
    if (elements.btnResults) {
      elements.btnResults.hidden = state.status !== 'finished';
    }

    elements.lobby.hidden = state.status !== 'lobby';
    elements.question.hidden = state.status !== 'active';
    elements.finished.hidden = state.status !== 'finished';

    if (state.status === 'lobby') {
      drawQr();
      lastQuestionId = null;
      lastPhase = null;
    }
    if (state.status === 'active') {
      renderQuestion(state);
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
  elements.btnReveal.addEventListener('click', async () => {
    const state = await postAction(urls.reveal);
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

  drawQr();
  poll();
  setInterval(poll, 1500);
})();
