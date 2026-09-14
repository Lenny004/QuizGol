/**
 * Formulario de join: detecta modo por código y muestra equipo solo en partido.
 */
(function () {
  const root = document.getElementById('join-app');
  if (!root) return;

  const lookupUrl = root.dataset.lookupUrl;
  const codeInput = document.getElementById('join-code');
  const teamField = document.getElementById('join-team-field');
  const banner = document.getElementById('join-mode-banner');
  const pill = document.getElementById('join-mode-pill');
  const hint = document.getElementById('join-mode-hint');
  const form = document.getElementById('join-form');

  let lookupTimer = null;

  function setTeamRequired(required) {
    teamField.hidden = !required;
    form.querySelectorAll('input[name="team"]').forEach((input) => {
      input.required = required;
      if (!required) input.checked = false;
    });
  }

  async function lookupCode() {
    const code = (codeInput.value || '').trim().toUpperCase();
    codeInput.value = code;

    if (code.length < 3) {
      banner.hidden = true;
      setTeamRequired(false);
      return;
    }

    try {
      const response = await fetch(lookupUrl + '?code=' + encodeURIComponent(code), {
        headers: { Accept: 'application/json' },
      });
      const data = await response.json();

      if (!data.found) {
        banner.hidden = false;
        pill.textContent = 'Código no encontrado';
        pill.className = 'join-mode__pill join-mode__pill--warn';
        hint.textContent = 'Pide el código al maestro o escanea el QR.';
        setTeamRequired(false);
        return;
      }

      if (!data.can_join) {
        banner.hidden = false;
        pill.textContent = 'Sala terminada';
        pill.className = 'join-mode__pill join-mode__pill--warn';
        hint.textContent = 'Esta sala ya no acepta jugadores.';
        setTeamRequired(false);
        return;
      }

      banner.hidden = false;
      if (data.is_match) {
        pill.textContent = 'Partido 2 equipos';
        pill.className = 'join-mode__pill join-mode__pill--match';
        hint.textContent = data.section_title
          ? data.section_title + ' · elige Local o Visitante'
          : 'Elige Local o Visitante';
        setTeamRequired(true);
      } else {
        pill.textContent = 'Quiz individual';
        pill.className = 'join-mode__pill join-mode__pill--quiz';
        hint.textContent = data.section_title || 'Cada uno suma sus propios puntos';
        setTeamRequired(false);
      }
    } catch (error) {
      // Silencioso: el submit validará en servidor.
    }
  }

  codeInput.addEventListener('input', () => {
    clearTimeout(lookupTimer);
    lookupTimer = setTimeout(lookupCode, 280);
  });

  if (codeInput.value) {
    lookupCode();
  }
})();
