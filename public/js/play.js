/**
 * UI del jugador (play.js)
 * Fases: lobby → question → waiting → reveal → finished
 */
(function () {
  const root = document.getElementById('play-app');
  if (!root) return;

  const csrfToken = root.dataset.csrf;
  const gameMode = root.dataset.mode || 'quiz';
  const urls = {
    state: root.dataset.stateUrl,
    answer: root.dataset.answerUrl,
  };

  const elements = {
    nick: document.getElementById('play-nick'),
    score: document.getElementById('play-score'),
    teamBadge: document.getElementById('play-team-badge'),
    lobby: document.getElementById('play-lobby'),
    question: document.getElementById('play-question'),
    reveal: document.getElementById('play-reveal'),
    finished: document.getElementById('play-finished'),
    lobbyCount: document.getElementById('lobby-count'),
    prompt: document.getElementById('play-prompt'),
    answers: document.getElementById('play-answers'),
    countdown: document.getElementById('play-countdown'),
    revealContent: document.getElementById('reveal-content'),
    finalScore: document.getElementById('final-score'),
    finalMatch: document.getElementById('final-match-result'),
    scoreboard: document.getElementById('play-scoreboard'),
    homeName: document.getElementById('play-home-name'),
    awayName: document.getElementById('play-away-name'),
    homeGoals: document.getElementById('play-home-goals'),
    awayGoals: document.getElementById('play-away-goals'),
  };

  let countdownTimer = null;
  let lastQuestionId = null;
  let isSubmittingAnswer = false;
  const answerColors = [
    'answer-grid__btn--red',
    'answer-grid__btn--blue',
    'answer-grid__btn--yellow',
    'answer-grid__btn--green',
  ];

  function showPhase(phase) {
    elements.lobby.hidden = phase !== 'lobby';
    elements.question.hidden = phase !== 'question';
    elements.reveal.hidden = phase !== 'reveal' && phase !== 'waiting';
    elements.finished.hidden = phase !== 'finished';
  }

  function renderTeamBadge(myTeam) {
    if (!elements.teamBadge) return;
    if (!myTeam) {
      elements.teamBadge.hidden = true;
      return;
    }
    elements.teamBadge.hidden = false;
    elements.teamBadge.textContent = myTeam.name;
    elements.teamBadge.className = 'team__badge team__badge--' + (myTeam.side || '');
  }

  function renderMatch(match) {
    if (!match || gameMode !== 'match') return;
    if (elements.homeName) elements.homeName.textContent = match.home.name;
    if (elements.awayName) elements.awayName.textContent = match.away.name;
    if (elements.homeGoals) elements.homeGoals.textContent = match.home.goals;
    if (elements.awayGoals) elements.awayGoals.textContent = match.away.goals;
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

  function renderAnswers(answers) {
    elements.answers.innerHTML = '';
    (answers || []).forEach((answer, index) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'answer-grid__btn ' + answerColors[index % 4];
      button.textContent = answer.text;
      button.dataset.answerId = answer.id;
      button.addEventListener('click', () => submitAnswer(answer.id));
      elements.answers.appendChild(button);
    });
  }

  async function submitAnswer(answerId) {
    if (isSubmittingAnswer) return;
    isSubmittingAnswer = true;
    elements.answers.querySelectorAll('button').forEach((button) => (button.disabled = true));

    try {
      const response = await fetch(urls.answer, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ answer_id: answerId }),
      });

      const data = await response.json();
      if (!response.ok) {
        alert(data.message || (data.errors && Object.values(data.errors).flat()[0]) || 'Error al responder');
        isSubmittingAnswer = false;
        elements.answers.querySelectorAll('button').forEach((button) => (button.disabled = false));
        return;
      }

      if (data.state) applyState(data.state);
    } catch (error) {
      alert('Error de red al responder.');
      isSubmittingAnswer = false;
    }
  }

  function renderWaitingOrReveal(state) {
    const myAnswer = state.my_answer;
    const isCorrect = myAnswer && myAnswer.is_correct;
    const collective = state.phase === 'reveal';
    const feedbackClass = myAnswer
      ? isCorrect
        ? 'feedback--goal'
        : 'feedback--miss'
      : 'feedback--miss';

    let title = 'Esperando a los demás…';
    let detail = 'Ya respondiste. Cuando termine el tiempo se revelará la correcta.';

    if (collective) {
      title = myAnswer
        ? isCorrect
          ? '⚽ ¡GOL!'
          : '💨 Fallo'
        : 'Tiempo agotado';
      detail = myAnswer
        ? isCorrect
          ? '+' + myAnswer.points_awarded + ' puntos'
          : 'Mira la respuesta correcta en el proyector'
        : 'No alcanzaste a responder';

      if (state.question && state.question.answers) {
        const correct = state.question.answers.find((a) => a.is_correct);
        if (correct) {
          detail += '<br><strong>Correcta:</strong> ' + escapeHtml(correct.text);
        }
      }
    } else if (myAnswer) {
      title = isCorrect ? '⚽ ¡GOL!' : 'Respuesta enviada';
      detail = isCorrect
        ? '+' + myAnswer.points_awarded + ' puntos · esperando revelación'
        : 'Esperando revelación…';
    }

    const goalNote =
      collective && isCorrect && gameMode === 'match'
        ? '<p class="text--muted">+1 gol para tu equipo</p>'
        : '';

    elements.revealContent.innerHTML =
      `<div class="feedback ${feedbackClass}"><span class="feedback__emoji">${title}</span>` +
      `<p>${detail}</p>${goalNote}</div>`;
  }

  function renderScoreboard(rows) {
    elements.scoreboard.innerHTML = (rows || [])
      .map((row, index) =>
        `<li><span>${index + 1}. ${escapeHtml(row.nickname)}</span><strong>${row.score}</strong></li>`
      )
      .join('');
  }

  function renderFinalMatch(match) {
    if (!elements.finalMatch || !match) return;
    elements.finalMatch.hidden = false;
    const scoreLine =
      match.home.name + ' ' + match.home.goals + ' – ' + match.away.goals + ' ' + match.away.name;
    let resultLabel = 'Empate';
    if (match.winner === 'home') resultLabel = 'Gana ' + match.home.name;
    if (match.winner === 'away') resultLabel = 'Gana ' + match.away.name;
    elements.finalMatch.textContent = scoreLine + ' · ' + resultLabel;
  }

  function applyState(state) {
    elements.score.textContent = (state.my_score || 0) + ' pts';
    if (state.nickname) elements.nick.textContent = state.nickname;
    renderTeamBadge(state.my_team);
    renderMatch(state.match);

    if (state.phase === 'lobby') {
      showPhase('lobby');
      elements.lobbyCount.textContent = state.players_count;
      lastQuestionId = null;
      isSubmittingAnswer = false;
      return;
    }

    if (state.phase === 'finished') {
      showPhase('finished');
      elements.finalScore.textContent = state.my_score || 0;
      renderScoreboard(state.scoreboard);
      renderFinalMatch(state.match);
      return;
    }

    if (state.phase === 'waiting' || state.phase === 'reveal') {
      showPhase('reveal');
      renderWaitingOrReveal(state);
      isSubmittingAnswer = false;
      return;
    }

    showPhase('question');
    const question = state.question;
    if (question) {
      elements.prompt.textContent = question.prompt;
      if (question.id !== lastQuestionId) {
        lastQuestionId = question.id;
        isSubmittingAnswer = false;
        renderAnswers(question.answers);
        startCountdown(question.started_at, question.time_limit);
      }
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
      // Silencioso
    }
  }

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
