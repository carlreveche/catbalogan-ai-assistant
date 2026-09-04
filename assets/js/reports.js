(function () {
  var data = window.REPORTS_DATA;
  if (!data) return;

  Chart.defaults.font.family = '"Inter", "Public Sans", sans-serif';
  Chart.defaults.color = '#78716c';
  Chart.defaults.borderColor = 'rgba(120, 53, 15, 0.1)';

  var PALETTE = ['#c2410c', '#f97316', '#0e7c66', '#ce1126', '#9a3412', '#f59e0b', '#0f766e', '#78716c', '#d6d3d1', '#7c2d12'];

  // Most asked topics (horizontal bars)
  var topicCtx = document.getElementById('topicChart');
  if (topicCtx && data.labels.length) {
    new Chart(topicCtx, {
      type: 'bar',
      data: {
        labels: data.labels,
        datasets: [{
          label: 'Questions',
          data: data.values,
          backgroundColor: PALETTE[0],
          borderRadius: 4
        }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
      }
    });
  } else if (topicCtx) {
    topicCtx.parentElement.innerHTML = '<p class="empty-hint">No assistant answers in this period.</p>';
  }

  // Preset shortcuts fill the calendar fields, then submit
  var period = document.getElementById('reportPeriod');
  if (period) {
    period.addEventListener('change', function () {
      var days = parseInt(period.value, 10);
      var fromEl = document.getElementById('reportFrom');
      var toEl = document.getElementById('reportTo');
      if (days > 0) {
        var now = new Date();
        var from = new Date(now);
        from.setDate(now.getDate() - days + 1);
        fromEl.value = fmtLocal(from);
        toEl.value = fmtLocal(now);
      } else {
        fromEl.value = '';
        toEl.value = '';
      }
      document.getElementById('reportForm').submit();
    });
  }

  function fmtLocal(d) {
    var m = d.getMonth() + 1;
    var day = d.getDate();
    return d.getFullYear() + '-' + (m < 10 ? '0' : '') + m + '-' + (day < 10 ? '0' : '') + day;
  }
})();