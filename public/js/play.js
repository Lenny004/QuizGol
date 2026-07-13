/**
 * Player UI — polling cada 1.5s + envío de respuestas.
 * Animaciones: goal-burst / miss-shake (placeholder for Lottie en public/lottie/).
 */
(function () {
  const root = document.getElementById('play-app');
  if (!root) return;

  const csrf = root.dataset.csrf;
  const mode = root.dataset.mode || 'quiz';
  const urls = {
    state: root.dataset.stateUrl,
    answer: root.dataset.answerUrl,
  };

  const els = {
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
  let answering = false;
  const colors = ['answer-red', 'answer-blue', 'answer-yellow', 'answer-green'];

  function showPhase(phase) {
    els.lobby.hidden = phase !== 'lobby';
    els.question.hidden = phase !== 'question';
    els.reveal.hidden = phase !== 'reveal';
    els.finished.hidden = phase !== 'finished';
  }

  function renderTeamBadge(myTeam) {
    if (!els.teamBadge) return;
    if (!myTeam) {
      els.teamBadge.hidden = true;
      return;
    }
    els.teamBadge.hidden = false;
    els.teamBadge.textContent = myTeam.name;
    els.teamBadge.className = 'team-badge team-' + (myTeam.side || '');
  }

  function renderMatch(match) {
    if (!match || mode !== 'match') return;
    if (els.homeName) els.homeName.textContent = match.home.name;
    if (els.awayName) els.awayName.textContent = match.away.name;
    if (els.homeGoals) els.homeGoals.textContent = match.home.goals;
    if (els.awayGoals) els.awayGoals.textContent = match.away.goals;
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

  function renderAnswers(answers) {
    els.answers.innerHTML = '';
    (answers || []).forEach((a, i) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'answer-btn ' + colors[i % 4];
      btn.textContent = a.text;
      btn.dataset.answerId = a.id;
      btn.addEventListener('click', () => submitAnswer(a.id));
      els.answers.appendChild(btn);
    });
  }

  async function submitAnswer(answerId) {
    if (answering) return;
    answering = true;
    els.answers.querySelectorAll('button').forEach((b) => (b.disabled = true));

    try {
      const res = await fetch(urls.answer, {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ answer_id: answerId }),
      });

      const data = await res.json();
      if (!res.ok) {
        alert(data.message || (data.errors && Object.values(data.errors).flat()[0]) || 'Error al responder');
        answering = false;
        els.answers.querySelectorAll('button').forEach((b) => (b.disabled = false));
        return;
      }

      if (data.state) applyState(data.state);
    } catch (e) {
      alert('Error de red al responder.');
      answering = false;
    }
  }

  function renderReveal(myAnswer) {
    const ok = myAnswer && myAnswer.is_correct;
    const cls = ok ? 'goal-burst' : 'miss-shake';
    // placeholder for Lottie — CSS + emoji (ver public/lottie/)
    const emoji = ok ? '⚽ GOL!' : '💨 Fallo';
    const pts = myAnswer ? myAnswer.points_awarded : 0;
    const goalNote = ok && mode === 'match' ? '<p class="muted">+1 gol para tu equipo</p>' : '';
    els.revealContent.innerHTML =
      `<div class="feedback ${cls}"><span class="feedback-emoji">${emoji}</span>` +
      `<p>${ok ? '+' + pts + ' puntos' : 'Sigue intentando'}</p>${goalNote}</div>`;
  }

  function renderScoreboard(rows) {
    els.scoreboard.innerHTML = (rows || [])
      .map((r, i) => `<li><span>${i + 1}. ${escapeHtml(r.nickname)}</span><strong>${r.score}</strong></li>`)
      .join('');
  }

  function renderFinalMatch(match) {
    if (!els.finalMatch || !match) return;
    els.finalMatch.hidden = false;
    const score = match.home.name + ' ' + match.home.goals + ' – ' + match.away.goals + ' ' + match.away.name;
    let result = 'Empate';
    if (match.winner === 'home') result = 'Gana ' + match.home.name;
    if (match.winner === 'away') result = 'Gana ' + match.away.name;
    els.finalMatch.textContent = score + ' · ' + result;
  }

  function applyState(state) {
    els.score.textContent = (state.my_score || 0) + ' pts';
    if (state.nickname) els.nick.textContent = state.nickname;
    renderTeamBadge(state.my_team);
    renderMatch(state.match);

    if (state.phase === 'lobby') {
      showPhase('lobby');
      els.lobbyCount.textContent = state.players_count;
      lastQuestionId = null;
      answering = false;
      return;
    }

    if (state.phase === 'finished') {
      showPhase('finished');
      els.finalScore.textContent = state.my_score || 0;
      renderScoreboard(state.scoreboard);
      renderFinalMatch(state.match);
      return;
    }

    if (state.phase === 'reveal' && state.my_answer) {
      showPhase('reveal');
      renderReveal(state.my_answer);
      answering = false;
      return;
    }

    // question
    showPhase('question');
    const q = state.question;
    if (q) {
      els.prompt.textContent = q.prompt;
      if (q.id !== lastQuestionId) {
        lastQuestionId = q.id;
        answering = false;
        renderAnswers(q.answers);
        startCountdown(q.started_at, q.time_limit);
      }
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
      // silencioso
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
