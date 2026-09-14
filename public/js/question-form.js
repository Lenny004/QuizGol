(function () {
  var difficulty = document.getElementById('question-difficulty');
  var points = document.getElementById('question-points');

  if (!difficulty || !points) {
    return;
  }

  var pointsMap = {};
  try {
    pointsMap = JSON.parse(points.getAttribute('data-points-map') || '{}');
  } catch (error) {
    pointsMap = { easy: 500, medium: 1000, hard: 2000 };
  }

  function syncPoints() {
    var value = pointsMap[difficulty.value];
    if (value) {
      points.value = value;
    }
  }

  difficulty.addEventListener('change', syncPoints);
  syncPoints();
})();
