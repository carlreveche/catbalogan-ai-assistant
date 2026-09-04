(function () {
  var data = window.DASHBOARD_DATA;
  if (!data) return;

  Chart.defaults.font.family = '"Inter", "Public Sans", sans-serif';
  Chart.defaults.color = '#78716c';

  // Citizen engagement (donut)
  var engageCtx = document.getElementById('engageChart');
  if (engageCtx) {
    var total = data.engagement.returning + data.engagement.new;
    var hasData = total > 0;
    var centerTextPlugin = {
      id: 'engagementCenterText',
      afterDraw: function (chart) {
        var ctx = chart.ctx;
        var area = chart.chartArea;
        var x = (area.left + area.right) / 2;
        var y = (area.top + area.bottom) / 2;
        ctx.save();
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillStyle = '#292524';
        ctx.font = '700 20px Inter, sans-serif';
        ctx.fillText(hasData ? total.toLocaleString() : '0', x, y - 7);
        ctx.fillStyle = '#78716c';
        ctx.font = '500 10px Inter, sans-serif';
        ctx.fillText(hasData ? 'citizens' : 'no data', x, y + 12);
        ctx.restore();
      }
    };

    new Chart(engageCtx, {
      type: 'doughnut',
      data: {
        labels: ['Returning citizens', 'New this period'],
        datasets: [{
          data: hasData
            ? [data.engagement.returning, data.engagement.new]
            : [1, 0],
          backgroundColor: hasData ? ['#0e7c66', '#fed7aa'] : ['#e7e5e4', '#e7e5e4'],
          borderColor: '#ffffff',
          borderWidth: 2
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '72%',
        plugins: {
          legend: { display: false },
          tooltip: hasData ? {
            callbacks: {
              label: function (ctx) {
                var pct = total > 0 ? Math.round(ctx.parsed / total * 100) : 0;
                return ' ' + ctx.label + ': ' + ctx.parsed + ' (' + pct + '%)';
              }
            }
          } : { enabled: false }
        }
      },
      plugins: [centerTextPlugin]
    });
  }
})();