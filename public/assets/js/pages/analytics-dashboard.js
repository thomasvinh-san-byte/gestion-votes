(function() {
  'use strict';

  var currentPeriod = '1an';
  var charts = {};

  // Chart.js default options
  var chartDefaults = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: 'bottom',
        labels: {
          padding: 15,
          usePointStyle: true,
        }
      }
    }
  };

  // Colors — read from CSS variables for dark mode compatibility
  function cssVar(name, fallback) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;
  }
  function getCOLORS() {
    return {
      primary: cssVar('--color-primary', '#6366f1'),
      success: cssVar('--color-success', '#10b981'),
      danger:  cssVar('--color-danger', '#ef4444'),
      warning: cssVar('--color-warning', '#f59e0b'),
      muted:   cssVar('--color-text-muted', '#9ca3af'),
      info:    cssVar('--color-info', '#3b82f6'),
    };
  }
  var COLORS = getCOLORS();
  // Refresh colors and rebuild charts when theme changes
  var _themeMO = new MutationObserver(function() {
    COLORS = getCOLORS();
    loadAllData();
  });
  _themeMO.observe(
    document.documentElement, { attributes: true, attributeFilter: ['data-theme'] }
  );
  window.addEventListener('pagehide', function() { _themeMO.disconnect(); }, { once: true });

  // Period pills (new analytics-period-pill class)
  document.querySelectorAll('.analytics-period-pill').forEach(function(btn) {
    btn.addEventListener('click', function() {
      document.querySelectorAll('.analytics-period-pill').forEach(function(b) { b.classList.remove('active'); });
      btn.classList.add('active');
      currentPeriod = btn.dataset.period;
      loadAllData();
    });
  });

  // Tabs
  document.querySelectorAll('.analytics-tab').forEach(function(tab) {
    tab.addEventListener('click', function() {
      document.querySelectorAll('.analytics-tab').forEach(function(t) { t.classList.remove('active'); });
      document.querySelectorAll('.tab-content').forEach(function(c) { c.classList.remove('active'); });
      tab.classList.add('active');
      document.getElementById('tab-' + tab.dataset.tab).classList.add('active');
    });
  });

  // Year filter
  var currentYear = '';
  var yearFilterEl = document.getElementById('yearFilter');
  if (yearFilterEl) {
    currentYear = yearFilterEl.value || '';
    yearFilterEl.addEventListener('change', function() {
      currentYear = yearFilterEl.value;
      loadAllData();
    });
  }

  // PDF export
  var _btnPdf = document.getElementById('btnExportPdf');
  if (_btnPdf) _btnPdf.addEventListener('click', function() { window.print(); });

  // Refresh button
  var _btnRefresh = document.getElementById('refreshBtn');
  if (_btnRefresh) _btnRefresh.addEventListener('click', loadAllData);

  // =========================================================================
  // DATA LOADING
  // =========================================================================

  function chartErrorHtml(msg) {
    return '<div class="text-center p-6 text-muted"><p>' + escapeHtml(msg) + '</p></div>';
  }

  async function loadAllData() {
    await Promise.all([
      loadOverview(),
      loadParticipation(),
      loadMotions(),
      loadVoteDuration(),
      loadVoteTiming(),
      loadAnomalies(),
    ]);
    // Mark all chart containers as loaded (hides CSS spinner)
    document.querySelectorAll('.chart-container').forEach(function(c) { c.classList.add('loaded'); });
  }

  // Helper: update a trend DOM element by ID
  // trendValue: positive = up, negative = down, 0 = stable
  // When year filter is "all", hide the trend element entirely
  function updateTrend(elementId, trendValue) {
    var el = document.getElementById(elementId);
    if (!el) return;
    var isAllYears = (currentYear === 'all' || currentYear === '');
    if (isAllYears) {
      el.hidden = true;
      return;
    }
    el.hidden = false;
    var arrowEl = el.querySelector('.trend-arrow');
    // Remove existing direction classes
    el.classList.remove('up', 'down');
    if (trendValue > 0) {
      el.classList.add('up');
      if (arrowEl) arrowEl.textContent = '\u25b2 ' + Math.abs(trendValue) + '%';
    } else if (trendValue < 0) {
      el.classList.add('down');
      if (arrowEl) arrowEl.textContent = '\u25bc ' + Math.abs(trendValue) + '%';
    } else {
      if (arrowEl) arrowEl.textContent = '\u2014 stable';
    }
  }

  async function loadOverview() {
    var container = document.getElementById('overviewCards');
    try {
      var result = await api('/api/v1/analytics.php?type=overview&period=' + encodeURIComponent(currentPeriod) + (currentYear ? '&year=' + encodeURIComponent(currentYear) : ''));
      var data = result.body && result.body.data;
      if (!data) throw new Error('Donn\u00e9es non disponibles');

      var totals = data.totals || {};
      var avgRate = Math.round(parseFloat(data.avg_participation_rate) || 0);
      var decisions = data.motion_decisions || {};

      var adopted = parseInt(decisions.adopted || 0);
      var rejected = parseInt(decisions.rejected || 0);
      var total = adopted + rejected;
      var adoptionRate = total > 0 ? Math.round(adopted / total * 100) : 0;

      // Trends compared to previous year (from API or default to 0)
      var trends = data.trends || {};
      var meetingsTrend = trends.meetings || 0;
      var motionsTrend = trends.motions || 0;
      var participationTrend = trends.participation || 0;
      var adoptionTrend = trends.adoption || 0;

      // Update KPI values by ID (static card structure preserved in HTML)
      var kpiMeetings = document.getElementById('kpiMeetings');
      if (kpiMeetings) kpiMeetings.textContent = totals.meetings || 0;

      var kpiResolutions = document.getElementById('kpiResolutions');
      if (kpiResolutions) kpiResolutions.textContent = totals.motions || 0;

      var kpiAdoptionRate = document.getElementById('kpiAdoptionRate');
      if (kpiAdoptionRate) kpiAdoptionRate.textContent = adoptionRate + '%';

      var kpiParticipation = document.getElementById('kpiParticipation');
      if (kpiParticipation) kpiParticipation.textContent = avgRate + '%';

      // Update trend arrows (hidden when year = all)
      updateTrend('kpiMeetingsTrend', meetingsTrend);
      updateTrend('kpiResolutionsTrend', motionsTrend);
      updateTrend('kpiAdoptionTrend', adoptionTrend);
      updateTrend('kpiParticipationTrend', participationTrend);

    } catch (err) {
      if (container) container.innerHTML = '<div class="error-message">Erreur: ' + escapeHtml(err.message) + '</div>';
    }
  }

  async function loadParticipation() {
    try {
      var result = await api('/api/v1/analytics.php?type=participation&period=' + encodeURIComponent(currentPeriod) + (currentYear ? '&year=' + encodeURIComponent(currentYear) : ''));
      var data = result.body && result.body.data;
      if (!data) throw new Error('Donn\u00e9es non disponibles');

      var meetings = data.meetings || [];

      // Aggregate participation rates by month (12-point line chart per wireframe)
      var monthLabels = ['Jan', 'F\u00e9v', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Ao\u00fb', 'Sep', 'Oct', 'Nov', 'D\u00e9c'];
      var monthRateSum = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
      var monthRateCount = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
      meetings.forEach(function(m) {
        var d = m.date ? new Date(m.date) : null;
        if (d && !isNaN(d.getTime())) {
          var idx = d.getMonth(); // 0-based
          monthRateSum[idx] += parseFloat(m.rate) || 0;
          monthRateCount[idx] += 1;
        }
      });
      var monthlyRates = monthRateSum.map(function(sum, i) {
        return monthRateCount[i] > 0 ? Math.round(sum / monthRateCount[i]) : null;
      });

      // Destroy existing chart
      if (charts.participation) charts.participation.destroy();

      var ctx = document.getElementById('participationChart');
      if (ctx) {
        charts.participation = new Chart(ctx.getContext('2d'), {
          type: 'line',
          data: {
            labels: monthLabels,
            datasets: [{
              label: 'Taux (%)',
              data: monthlyRates,
              borderColor: COLORS.primary,
              backgroundColor: COLORS.primary + '33',
              fill: true,
              tension: 0.3,
              spanGaps: true,
            }]
          },
          options: Object.assign({}, chartDefaults, {
            scales: {
              y: {
                beginAtZero: true,
                max: 100,
                ticks: { callback: function(v) { return v + '%'; } }
              }
            }
          })
        });
      }

      // Sessions by month (bar chart)
      if (charts.sessionsByMonth) charts.sessionsByMonth.destroy();
      var ctxMonth = document.getElementById('sessionsByMonthChart');
      if (ctxMonth) {
        var monthCounts = [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0];
        meetings.forEach(function(m) {
          var d = m.date ? new Date(m.date) : null;
          if (d && !isNaN(d.getTime())) {
            monthCounts[d.getMonth()] += 1;
          }
        });
        charts.sessionsByMonth = new Chart(ctxMonth.getContext('2d'), {
          type: 'bar',
          data: {
            labels: monthLabels,
            datasets: [{
              label: 'S\u00e9ances',
              data: monthCounts,
              backgroundColor: COLORS.primary,
            }]
          },
          options: Object.assign({}, chartDefaults, { scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } })
        });
      }

      // Table
      var tableContainer = document.getElementById('participationTable');
      if (meetings.length === 0) {
        tableContainer.innerHTML = '<p class="text-muted text-center p-4">Aucune donn\u00e9e disponible</p>';
      } else {
        var tableRows = meetings.slice(-10).reverse().map(function(m) {
          var rate = parseFloat(m.rate) || 0;
          return '<tr>' +
            '<td>' + escapeHtml((m.title || 'S\u00e9ance').substring(0, 30)) + '</td>' +
            '<td>' + (parseInt(m.present) || 0) + ' / ' + (parseInt(m.eligible) || 0) + '</td>' +
            '<td>' + (parseInt(m.proxy) || 0) + '</td>' +
            '<td><div class="table-progress-cell">' +
              '<div class="progress-bar table-progress-bar">' +
                '<div class="progress-bar-fill ' + (rate >= 50 ? 'success' : 'warning') + '" style="width:' + Math.round(rate) + '%"></div>' +
              '</div>' +
              '<span>' + Math.round(rate) + '%</span>' +
            '</div></td>' +
            '</tr>';
        }).join('');
        tableContainer.innerHTML = '<table class="data-table">' +
          '<thead><tr>' +
            '<th scope="col">S\u00e9ance</th>' +
            '<th scope="col">Pr\u00e9sents</th>' +
            '<th scope="col">Procurations</th>' +
            '<th scope="col">Taux</th>' +
          '</tr></thead>' +
          '<tbody>' + tableRows + '</tbody>' +
          '</table>';
      }
    } catch (err) {
      var pChart = document.getElementById('participationChart');
      if (pChart) pChart.parentElement.innerHTML = chartErrorHtml('Erreur de chargement des participations');
      var ptable = document.getElementById('participationTable');
      if (ptable) ptable.innerHTML = '<div class="text-center text-muted text-sm" style="padding:1.5rem;">Aucune donn\u00e9e de participation disponible.</div>';
    }
  }

  async function loadMotions() {
    try {
      var result = await api('/api/v1/analytics.php?type=motions&period=' + encodeURIComponent(currentPeriod) + (currentYear ? '&year=' + encodeURIComponent(currentYear) : ''));
      var data = result.body && result.body.data;
      if (!data) throw new Error('Donn\u00e9es non disponibles');

      var summary = data.summary || {};
      var byMeeting = data.by_meeting || [];

      // Animate the SVG donut segments for Pour/Contre/Abstention
      var totalVotes = (parseInt(summary.pour) || 0) + (parseInt(summary.contre) || 0) + (parseInt(summary.abstention) || 0);
      var circumference = 314; // 2 * PI * 50 (r=50)
      if (totalVotes > 0) {
        var pourPct = (parseInt(summary.pour) || 0) / totalVotes;
        var contrePct = (parseInt(summary.contre) || 0) / totalVotes;
        var abstentionPct = (parseInt(summary.abstention) || 0) / totalVotes;

        var pourLen = pourPct * circumference;
        var contreLen = contrePct * circumference;
        var abstentionLen = abstentionPct * circumference;

        var donutFor = document.getElementById('donutFor');
        var donutAgainst = document.getElementById('donutAgainst');
        var donutAbstain = document.getElementById('donutAbstain');

        if (donutFor) donutFor.setAttribute('stroke-dasharray', pourLen.toFixed(1) + ' ' + (circumference - pourLen).toFixed(1));
        if (donutAgainst) {
          donutAgainst.setAttribute('stroke-dasharray', contreLen.toFixed(1) + ' ' + (circumference - contreLen).toFixed(1));
          donutAgainst.setAttribute('stroke-dashoffset', (78.5 - pourLen).toFixed(1));
        }
        if (donutAbstain) {
          donutAbstain.setAttribute('stroke-dasharray', abstentionLen.toFixed(1) + ' ' + (circumference - abstentionLen).toFixed(1));
          donutAbstain.setAttribute('stroke-dashoffset', (78.5 - pourLen - contreLen).toFixed(1));
        }

        var donutTotal = document.getElementById('donutTotal');
        if (donutTotal) donutTotal.textContent = totalVotes;

        var donutForPct = document.getElementById('donutForPct');
        if (donutForPct) donutForPct.textContent = Math.round(pourPct * 100) + '%';
        var donutAgainstPct = document.getElementById('donutAgainstPct');
        if (donutAgainstPct) donutAgainstPct.textContent = Math.round(contrePct * 100) + '%';
        var donutAbstainPct = document.getElementById('donutAbstainPct');
        if (donutAbstainPct) donutAbstainPct.textContent = Math.round(abstentionPct * 100) + '%';
      } else if (summary.adopted !== undefined || summary.rejected !== undefined) {
        // Fallback: use adopted/rejected data from summary if pour/contre/abstention not available
        var adoptedCount = parseInt(summary.adopted) || 0;
        var rejectedCount = parseInt(summary.rejected) || 0;
        var pendingCount = (parseInt(summary.total) || 0) - adoptedCount - rejectedCount;
        var fallbackTotal = adoptedCount + rejectedCount + Math.max(0, pendingCount);
        if (fallbackTotal > 0) {
          var aLen = (adoptedCount / fallbackTotal) * circumference;
          var rLen = (rejectedCount / fallbackTotal) * circumference;
          var donutFor2 = document.getElementById('donutFor');
          var donutAgainst2 = document.getElementById('donutAgainst');
          if (donutFor2) donutFor2.setAttribute('stroke-dasharray', aLen.toFixed(1) + ' ' + (circumference - aLen).toFixed(1));
          if (donutAgainst2) {
            donutAgainst2.setAttribute('stroke-dasharray', rLen.toFixed(1) + ' ' + (circumference - rLen).toFixed(1));
            donutAgainst2.setAttribute('stroke-dashoffset', (78.5 - aLen).toFixed(1));
          }
          var donutTotal2 = document.getElementById('donutTotal');
          if (donutTotal2) donutTotal2.textContent = fallbackTotal;
        }
      }

      // Motions chart (doughnut via Chart.js)
      if (charts.motions) charts.motions.destroy();
      var ctx1El = document.getElementById('motionsChart');
      if (ctx1El) {
        charts.motions = new Chart(ctx1El.getContext('2d'), {
          type: 'doughnut',
          data: {
            labels: ['Adopt\u00e9es', 'Rejet\u00e9es', 'En attente'],
            datasets: [{
              data: [
                summary.adopted || 0,
                summary.rejected || 0,
                Math.max(0, (summary.total || 0) - (summary.adopted || 0) - (summary.rejected || 0))
              ],
              backgroundColor: [COLORS.success, COLORS.danger, COLORS.muted],
            }]
          },
          options: chartDefaults
        });
      }

      // Majority breakdown chart
      if (charts.majority) charts.majority.destroy();
      var ctxMajEl = document.getElementById('majorityChart');
      if (ctxMajEl) {
        var majLabels = Object.keys(summary.by_majority || {});
        var majValues = Object.values(summary.by_majority || {});
        if (majLabels.length > 0) {
          charts.majority = new Chart(ctxMajEl.getContext('2d'), {
            type: 'doughnut',
            data: {
              labels: majLabels,
              datasets: [{
                data: majValues,
                backgroundColor: [COLORS.primary, COLORS.success, COLORS.warning, COLORS.info, COLORS.danger].slice(0, majLabels.length),
              }]
            },
            options: chartDefaults
          });
        }
      }

      // Trend chart
      if (charts.motionsTrend) charts.motionsTrend.destroy();
      var ctx2El = document.getElementById('motionsTrendChart');
      if (ctx2El && byMeeting.length > 0) {
        charts.motionsTrend = new Chart(ctx2El.getContext('2d'), {
          type: 'bar',
          data: {
            labels: byMeeting.map(function(m) { return (m.meeting_title || '').substring(0, 12); }),
            datasets: [
              {
                label: 'Adopt\u00e9es',
                data: byMeeting.map(function(m) { return parseInt(m.adopted) || 0; }),
                backgroundColor: COLORS.success,
              },
              {
                label: 'Rejet\u00e9es',
                data: byMeeting.map(function(m) { return parseInt(m.rejected) || 0; }),
                backgroundColor: COLORS.danger,
              }
            ]
          },
          options: Object.assign({}, chartDefaults, {
            scales: {
              x: { stacked: true },
              y: { stacked: true, beginAtZero: true }
            }
          })
        });
      }
    } catch (err) {
      var mChart = document.getElementById('motionsChart');
      if (mChart) mChart.parentElement.innerHTML = chartErrorHtml('Erreur de chargement des r\u00e9solutions');
      var mtChart = document.getElementById('motionsTrendChart');
      if (mtChart) mtChart.parentElement.innerHTML = '<div class="text-center text-muted text-sm" style="padding:1.5rem;">Aucune tendance disponible.</div>';
    }
  }

  async function loadVoteDuration() {
    try {
      var result = await api('/api/v1/analytics.php?type=vote_duration&period=' + encodeURIComponent(currentPeriod) + (currentYear ? '&year=' + encodeURIComponent(currentYear) : ''));
      var data = result.body && result.body.data;
      if (!data) throw new Error('Donn\u00e9es non disponibles');

      var distribution = data.distribution || {};

      if (charts.duration) charts.duration.destroy();
      var dCtxEl = document.getElementById('durationChart');
      if (dCtxEl) {
        charts.duration = new Chart(dCtxEl.getContext('2d'), {
          type: 'bar',
          data: {
            labels: Object.keys(distribution),
            datasets: [{
              label: 'Nombre de votes',
              data: Object.values(distribution),
              backgroundColor: COLORS.info,
            }]
          },
          options: Object.assign({}, chartDefaults, {
            plugins: Object.assign({}, chartDefaults.plugins, {
              title: {
                display: true,
                text: 'Dur\u00e9e moyenne: ' + (data.avg_formatted || 'N/A')
              }
            })
          })
        });
      }
      // Average duration by type
      var byType = data.by_type || {};
      if (charts.avgDuration) charts.avgDuration.destroy();
      var avgCtxEl = document.getElementById('avgDurationChart');
      if (avgCtxEl && Object.keys(byType).length > 0) {
        charts.avgDuration = new Chart(avgCtxEl.getContext('2d'), {
          type: 'bar',
          data: {
            labels: Object.keys(byType),
            datasets: [{
              label: 'Dur\u00e9e moyenne (s)',
              data: Object.values(byType).map(function(v) { return parseFloat(v) || 0; }),
              backgroundColor: COLORS.warning,
            }]
          },
          options: Object.assign({}, chartDefaults, { indexAxis: 'y', scales: { x: { beginAtZero: true } } })
        });
      }
    } catch (err) {
      var dChart = document.getElementById('durationChart');
      if (dChart) dChart.parentElement.innerHTML = chartErrorHtml('Erreur de chargement des dur\u00e9es');
    }
  }

  async function loadVoteTiming() {
    try {
      var result = await api('/api/v1/analytics.php?type=vote_timing&period=' + encodeURIComponent(currentPeriod) + (currentYear ? '&year=' + encodeURIComponent(currentYear) : ''));
      var data = result.body && result.body.data;
      if (!data) throw new Error('Donn\u00e9es non disponibles');

      var distribution = data.distribution || {};

      if (charts.responseTime) charts.responseTime.destroy();
      var rtCtxEl = document.getElementById('responseTimeChart');
      if (rtCtxEl) {
        charts.responseTime = new Chart(rtCtxEl.getContext('2d'), {
          type: 'bar',
          data: {
            labels: Object.keys(distribution),
            datasets: [{
              label: 'Nombre de votes',
              data: Object.values(distribution),
              backgroundColor: COLORS.primary,
            }]
          },
          options: Object.assign({}, chartDefaults, {
            plugins: Object.assign({}, chartDefaults.plugins, {
              title: {
                display: true,
                text: 'Temps moyen: ' + (data.avg_formatted || 'N/A')
              }
            })
          })
        });
      }
    } catch (err) {
      var rtChart = document.getElementById('responseTimeChart');
      if (rtChart) rtChart.parentElement.innerHTML = chartErrorHtml('Erreur de chargement des temps de r\u00e9ponse');
    }
  }

  async function loadAnomalies() {
    var overviewContainer = document.getElementById('anomaliesOverview');
    var meetingsContainer = document.getElementById('anomaliesMeetings');

    try {
      var result = await api('/api/v1/analytics.php?type=anomalies&period=' + encodeURIComponent(currentPeriod) + (currentYear ? '&year=' + encodeURIComponent(currentYear) : ''));
      var data = result.body && result.body.data;
      if (!data) throw new Error('Donn\u00e9es non disponibles');

      var indicators = data.indicators || {};
      var meetings = data.flagged_meetings || [];

      // Helper for anomaly item HTML
      function anomalyItem(isIssue, cls, iconName, iconCls, label, value) {
        return '<div class="anomaly-item ' + (isIssue ? cls : 'ok') + '">' +
          '<div class="anomaly-icon">' + icon(isIssue ? iconName : 'check-circle', isIssue ? iconCls : 'icon-md icon-success') + '</div>' +
          '<div class="anomaly-content">' +
            '<div class="anomaly-label">' + label + '</div>' +
            '<div class="anomaly-value">' + value + '</div>' +
          '</div></div>';
      }

      // Indicateurs de qualit\u00e9 (agr\u00e9g\u00e9s, sans identification)
      overviewContainer.innerHTML = '<div class="anomaly-indicators">' +
        anomalyItem(indicators.low_participation_count > 0, 'warning', 'alert-triangle', 'icon-md icon-warning',
          'Participation faible (&lt;50%)', (indicators.low_participation_count || 0) + ' s\u00e9ance(s)') +
        anomalyItem(indicators.quorum_issues_count > 0, 'warning', 'alert-triangle', 'icon-md icon-warning',
          'Probl\u00e8mes de quorum', (indicators.quorum_issues_count || 0) + ' s\u00e9ance(s)') +
        anomalyItem(indicators.incomplete_votes_count > 0, 'info', 'info', 'icon-md icon-info',
          'R\u00e9solutions sans d\u00e9cision', (indicators.incomplete_votes_count || 0) + ' r\u00e9solution(s)') +
        anomalyItem(indicators.high_proxy_concentration > 0, 'warning', 'alert-triangle', 'icon-md icon-warning',
          'Concentration procurations (&gt;3/membre)', (indicators.high_proxy_concentration || 0) + ' cas') +
        anomalyItem(indicators.abstention_rate > 20, 'info', 'info', 'icon-md icon-info',
          "Taux d'abstention moyen", (indicators.abstention_rate || 0) + '%') +
        anomalyItem(indicators.very_short_votes_count > 0, 'info', 'info', 'icon-md icon-info',
          'Votes tr\u00e8s courts (&lt;30s)', (indicators.very_short_votes_count || 0) + ' vote(s)') +
        '</div>';

      var badgeEl = document.getElementById('tabBadgeAnomalies');
      var issueCount = (indicators.low_participation_count > 0 ? 1 : 0) +
        (indicators.quorum_issues_count > 0 ? 1 : 0) +
        (indicators.incomplete_votes_count > 0 ? 1 : 0) +
        (indicators.high_proxy_concentration > 0 ? 1 : 0) +
        (indicators.abstention_rate > 20 ? 1 : 0) +
        (indicators.very_short_votes_count > 0 ? 1 : 0);
      if (badgeEl) badgeEl.textContent = issueCount > 0 ? issueCount : '';

      // S\u00e9ances \u00e0 v\u00e9rifier
      if (meetings.length === 0) {
        meetingsContainer.innerHTML = '<p class="text-muted text-center p-4">Aucune anomalie d\u00e9tect\u00e9e</p>';
      } else {
        var anomalyRows = meetings.slice(0, 10).map(function(m) {
          var flags = (m.flags || []).map(function(f) {
            return '<span class="badge badge-warning anomaly-flag">' + escapeHtml(f) + '</span>';
          }).join('') || '-';
          return '<tr>' +
            '<td>' + escapeHtml((m.title || 'S\u00e9ance').substring(0, 25)) + '</td>' +
            '<td>' + (m.date ? new Date(m.date).toLocaleDateString('fr-FR') : '-') + '</td>' +
            '<td>' + Math.round(parseFloat(m.participation_rate) || 0) + '%</td>' +
            '<td>' + flags + '</td>' +
            '</tr>';
        }).join('');
        meetingsContainer.innerHTML = '<table class="data-table">' +
          '<thead><tr>' +
            '<th scope="col">S\u00e9ance</th>' +
            '<th scope="col">Date</th>' +
            '<th scope="col">Participation</th>' +
            '<th scope="col">Anomalies</th>' +
          '</tr></thead>' +
          '<tbody>' + anomalyRows + '</tbody>' +
          '</table>';
      }
    } catch (err) {
      if (overviewContainer) overviewContainer.innerHTML = '<div class="error-message">Erreur: ' + escapeHtml(err.message) + '</div>';
      if (meetingsContainer) meetingsContainer.innerHTML = '';
    }
  }

  // =========================================================================
  // CSV EXPORT
  // =========================================================================

  // Escape a CSV field value (wrap in quotes if contains comma, quote, or newline)
  function csvField(val) {
    var s = val == null ? '' : String(val);
    if (s.indexOf(',') !== -1 || s.indexOf('"') !== -1 || s.indexOf('\n') !== -1) {
      return '"' + s.replace(/"/g, '""') + '"';
    }
    return s;
  }

  var _btnExportCsv = document.getElementById('btnExportCsv');
  if (_btnExportCsv) {
    _btnExportCsv.addEventListener('click', async function() {
      try {
        _btnExportCsv.disabled = true;
        // Fetch overview data (sessions list)
        var result = await api('/api/v1/analytics.php?type=participation&period=all' + (currentYear && currentYear !== 'all' ? '&year=' + encodeURIComponent(currentYear) : ''));
        var data = result.body && result.body.data;
        var meetings = (data && data.meetings) || [];

        // Also fetch motions data for resolution counts
        var motionsResult = await api('/api/v1/analytics.php?type=motions&period=all' + (currentYear && currentYear !== 'all' ? '&year=' + encodeURIComponent(currentYear) : ''));
        var motionsData = motionsResult.body && motionsResult.body.data;
        var byMeeting = (motionsData && motionsData.by_meeting) || [];
        // Build lookup by meeting id/title
        var motionsByTitle = {};
        byMeeting.forEach(function(m) {
          motionsByTitle[m.meeting_title || m.meeting_id] = m;
        });

        // CSV header row
        var headers = ['Date', 'Type', 'Titre', 'Participants', 'Quorum %', 'R\u00e9solutions', 'Taux adoption', 'Pour', 'Contre', 'Abstention', 'Statut'];
        var rows = [headers.map(csvField).join(',')];

        meetings.forEach(function(m) {
          var motionInfo = motionsByTitle[m.title] || motionsByTitle[m.id] || {};
          var row = [
            m.date || '',
            m.type || '',
            m.title || '',
            m.present != null ? m.present : '',
            m.quorum_rate != null ? m.quorum_rate : (m.rate != null ? m.rate : ''),
            motionInfo.total != null ? motionInfo.total : (m.motions_count != null ? m.motions_count : ''),
            motionInfo.adoption_rate != null ? motionInfo.adoption_rate : '',
            motionInfo.pour != null ? motionInfo.pour : (m.pour != null ? m.pour : ''),
            motionInfo.contre != null ? motionInfo.contre : (m.contre != null ? m.contre : ''),
            motionInfo.abstention != null ? motionInfo.abstention : (m.abstention != null ? m.abstention : ''),
            m.status || ''
          ];
          rows.push(row.map(csvField).join(','));
        });

        var csvContent = '\uFEFF' + rows.join('\r\n'); // BOM for Excel UTF-8
        var blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        var url = URL.createObjectURL(blob);
        var link = document.createElement('a');
        var yearSuffix = (currentYear && currentYear !== 'all') ? currentYear : 'toutes';
        link.download = 'ag-vote-statistiques-' + yearSuffix + '.csv';
        link.href = url;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
      } catch (err) {
        console.error('CSV export failed:', err);
      } finally {
        _btnExportCsv.disabled = false;
      }
    });
  }

  // =========================================================================
  // CHART EXPORT BUTTONS (PNG)
  // =========================================================================

  // Chart export buttons
  document.querySelectorAll('.chart-export-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var chartId = this.dataset.chartId;
      var canvas = document.getElementById(chartId);
      if (canvas) {
        var link = document.createElement('a');
        link.download = chartId + '.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
      }
    });
  });

  // Initial load
  loadAllData();
})();
