(function() {
    'use strict';

    let currentPeriod = 'year';
    let charts = {};

    // Chart.js default options
    const chartDefaults = {
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
    let COLORS = getCOLORS();
    // Refresh colors and rebuild charts when theme changes
    new MutationObserver(() => {
      COLORS = getCOLORS();
      loadAllData();
    }).observe(
      document.documentElement, { attributes: true, attributeFilter: ['data-theme'] }
    );

    // Period buttons
    document.querySelectorAll('.period-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.period-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentPeriod = btn.dataset.period;
        loadAllData();
      });
    });

    // Tabs
    document.querySelectorAll('.analytics-tab').forEach(tab => {
      tab.addEventListener('click', () => {
        document.querySelectorAll('.analytics-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        tab.classList.add('active');
        document.getElementById(`tab-${tab.dataset.tab}`).classList.add('active');
      });
    });

    // Refresh button
    document.getElementById('refreshBtn')?.addEventListener('click', loadAllData);

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
    }

    // Helper: build trend indicator HTML
    function trendHtml(trend) {
      if (trend > 0) {
        return `<span class="overview-card-trend up">\u25b2 ${Math.abs(trend)}%</span>`;
      } else if (trend < 0) {
        return `<span class="overview-card-trend down">\u25bc ${Math.abs(trend)}%</span>`;
      }
      return `<span class="overview-card-trend">\u2014 stable</span>`;
    }

    async function loadOverview() {
      const container = document.getElementById('overviewCards');
      try {
        const { body } = await api(`/api/v1/analytics.php?type=overview&period=${currentPeriod}`);
        const data = body?.data;
        if (!data) throw new Error('Donn\u00e9es non disponibles');

        const totals = data.totals || {};
        const avgRate = Math.round(parseFloat(data.avg_participation_rate) || 0);
        const decisions = data.motion_decisions || {};

        const adopted = parseInt(decisions.adopted || 0);
        const rejected = parseInt(decisions.rejected || 0);
        const total = adopted + rejected;
        const adoptionRate = total > 0 ? Math.round(adopted / total * 100) : 0;

        // Trends compared to previous period (from API or default to 0)
        const trends = data.trends || {};
        const meetingsTrend = trends.meetings || 0;
        const membersTrend = trends.members || 0;
        const motionsTrend = trends.motions || 0;
        const ballotsTrend = trends.ballots || 0;
        const participationTrend = trends.participation || 0;
        const adoptionTrend = trends.adoption || 0;

        container.innerHTML = `
          <div class="overview-card">
            <div class="overview-card-label">S\u00e9ances totales</div>
            <div class="overview-card-value primary">${totals.meetings || 0}</div>
            ${trendHtml(meetingsTrend)}
          </div>
          <div class="overview-card">
            <div class="overview-card-label">Membres</div>
            <div class="overview-card-value">${totals.members || 0}</div>
            ${trendHtml(membersTrend)}
          </div>
          <div class="overview-card">
            <div class="overview-card-label">R\u00e9solutions</div>
            <div class="overview-card-value">${totals.motions || 0}</div>
            ${trendHtml(motionsTrend)}
          </div>
          <div class="overview-card">
            <div class="overview-card-label">Votes enregistr\u00e9s</div>
            <div class="overview-card-value">${totals.ballots || 0}</div>
            ${trendHtml(ballotsTrend)}
          </div>
          <div class="overview-card">
            <div class="overview-card-label">Participation moyenne</div>
            <div class="overview-card-value success">${avgRate}%</div>
            ${trendHtml(participationTrend)}
            <div class="progress-bar mt-2">
              <div class="progress-bar-fill success" style="width:${avgRate}%"></div>
            </div>
          </div>
          <div class="overview-card">
            <div class="overview-card-label">Taux d'adoption</div>
            <div class="overview-card-value ${adoptionRate >= 50 ? 'success' : 'warning'}">${adoptionRate}%</div>
            ${trendHtml(adoptionTrend)}
            <div class="overview-card-sub">${adopted} adopt\u00e9es / ${rejected} rejet\u00e9es</div>
          </div>
        `;
      } catch (err) {
        container.innerHTML = `<div class="error-message">Erreur: ${escapeHtml(err.message)}</div>`;
      }
    }

    async function loadParticipation() {
      try {
        const { body } = await api(`/api/v1/analytics.php?type=participation&period=${currentPeriod}`);
        const data = body?.data;
        if (!data) throw new Error('Donn\u00e9es non disponibles');

        const meetings = data.meetings || [];
        const labels = meetings.map(m => m.title?.substring(0, 15) || 'S\u00e9ance');
        const rates = meetings.map(m => parseFloat(m.rate) || 0);
        const presents = meetings.map(m => parseInt(m.present) || 0);
        const proxies = meetings.map(m => parseInt(m.proxy) || 0);

        // Destroy existing chart
        if (charts.participation) charts.participation.destroy();

        const ctx = document.getElementById('participationChart')?.getContext('2d');
        if (ctx) {
          charts.participation = new Chart(ctx, {
            type: 'line',
            data: {
              labels,
              datasets: [{
                label: 'Taux (%)',
                data: rates,
                borderColor: COLORS.primary,
                backgroundColor: COLORS.primary + '20',
                fill: true,
                tension: 0.3,
              }]
            },
            options: {
              ...chartDefaults,
              scales: {
                y: {
                  beginAtZero: true,
                  max: 100,
                  ticks: { callback: v => v + '%' }
                }
              }
            }
          });
        }

        // Table
        const tableContainer = document.getElementById('participationTable');
        if (meetings.length === 0) {
          tableContainer.innerHTML = '<p class="text-muted text-center p-4">Aucune donn\u00e9e disponible</p>';
        } else {
          tableContainer.innerHTML = `
            <table class="data-table">
              <thead>
                <tr>
                  <th scope="col">S\u00e9ance</th>
                  <th scope="col">Pr\u00e9sents</th>
                  <th scope="col">Procurations</th>
                  <th scope="col">Taux</th>
                </tr>
              </thead>
              <tbody>
                ${meetings.slice(-10).reverse().map(m => {
                  const rate = parseFloat(m.rate) || 0;
                  return `
                  <tr>
                    <td>${escapeHtml(m.title?.substring(0, 30) || 'S\u00e9ance')}</td>
                    <td>${parseInt(m.present) || 0} / ${parseInt(m.eligible) || 0}</td>
                    <td>${parseInt(m.proxy) || 0}</td>
                    <td>
                      <div class="table-progress-cell">
                        <div class="progress-bar table-progress-bar">
                          <div class="progress-bar-fill ${rate >= 50 ? 'success' : 'warning'}" style="width:${Math.round(rate)}%"></div>
                        </div>
                        <span>${Math.round(rate)}%</span>
                      </div>
                    </td>
                  </tr>`;
                }).join('')}
              </tbody>
            </table>
          `;
        }
      } catch (err) {
        var pChart = document.getElementById('participationChart');
        if (pChart) pChart.parentElement.innerHTML = chartErrorHtml('Erreur de chargement des participations');
        document.getElementById('participationTable').innerHTML = '';
      }
    }

    async function loadMotions() {
      try {
        const { body } = await api(`/api/v1/analytics.php?type=motions&period=${currentPeriod}`);
        const data = body?.data;
        if (!data) throw new Error('Donn\u00e9es non disponibles');

        const summary = data.summary || {};
        const byMeeting = data.by_meeting || [];

        // Pie chart
        if (charts.motions) charts.motions.destroy();
        const ctx1 = document.getElementById('motionsChart')?.getContext('2d');
        if (ctx1) {
          charts.motions = new Chart(ctx1, {
            type: 'doughnut',
            data: {
              labels: ['Adopt\u00e9es', 'Rejet\u00e9es', 'En attente'],
              datasets: [{
                data: [
                  summary.adopted || 0,
                  summary.rejected || 0,
                  (summary.total || 0) - (summary.adopted || 0) - (summary.rejected || 0)
                ],
                backgroundColor: [COLORS.success, COLORS.danger, COLORS.muted],
              }]
            },
            options: chartDefaults
          });
        }

        // Trend chart
        if (charts.motionsTrend) charts.motionsTrend.destroy();
        const ctx2 = document.getElementById('motionsTrendChart')?.getContext('2d');
        if (ctx2 && byMeeting.length > 0) {
          charts.motionsTrend = new Chart(ctx2, {
            type: 'bar',
            data: {
              labels: byMeeting.map(m => m.meeting_title?.substring(0, 12) || ''),
              datasets: [
                {
                  label: 'Adopt\u00e9es',
                  data: byMeeting.map(m => parseInt(m.adopted) || 0),
                  backgroundColor: COLORS.success,
                },
                {
                  label: 'Rejet\u00e9es',
                  data: byMeeting.map(m => parseInt(m.rejected) || 0),
                  backgroundColor: COLORS.danger,
                }
              ]
            },
            options: {
              ...chartDefaults,
              scales: {
                x: { stacked: true },
                y: { stacked: true, beginAtZero: true }
              }
            }
          });
        }
      } catch (err) {
        var mChart = document.getElementById('motionsChart');
        if (mChart) mChart.parentElement.innerHTML = chartErrorHtml('Erreur de chargement des r\u00e9solutions');
        var mtChart = document.getElementById('motionsTrendChart');
        if (mtChart) mtChart.parentElement.innerHTML = '';
      }
    }

    async function loadVoteDuration() {
      try {
        const { body } = await api(`/api/v1/analytics.php?type=vote_duration&period=${currentPeriod}`);
        const data = body?.data;
        if (!data) throw new Error('Donn\u00e9es non disponibles');

        const distribution = data.distribution || {};

        if (charts.duration) charts.duration.destroy();
        const ctx = document.getElementById('durationChart')?.getContext('2d');
        if (ctx) {
          charts.duration = new Chart(ctx, {
            type: 'bar',
            data: {
              labels: Object.keys(distribution),
              datasets: [{
                label: 'Nombre de votes',
                data: Object.values(distribution),
                backgroundColor: COLORS.info,
              }]
            },
            options: {
              ...chartDefaults,
              plugins: {
                ...chartDefaults.plugins,
                title: {
                  display: true,
                  text: `Dur\u00e9e moyenne: ${data.avg_formatted || 'N/A'}`
                }
              }
            }
          });
        }
      } catch (err) {
        var dChart = document.getElementById('durationChart');
        if (dChart) dChart.parentElement.innerHTML = chartErrorHtml('Erreur de chargement des dur\u00e9es');
      }
    }

    async function loadVoteTiming() {
      try {
        const { body } = await api(`/api/v1/analytics.php?type=vote_timing&period=${currentPeriod}`);
        const data = body?.data;
        if (!data) throw new Error('Donn\u00e9es non disponibles');

        const distribution = data.distribution || {};

        if (charts.responseTime) charts.responseTime.destroy();
        const ctx = document.getElementById('responseTimeChart')?.getContext('2d');
        if (ctx) {
          charts.responseTime = new Chart(ctx, {
            type: 'bar',
            data: {
              labels: Object.keys(distribution),
              datasets: [{
                label: 'Nombre de votes',
                data: Object.values(distribution),
                backgroundColor: COLORS.primary,
              }]
            },
            options: {
              ...chartDefaults,
              plugins: {
                ...chartDefaults.plugins,
                title: {
                  display: true,
                  text: `Temps moyen: ${data.avg_formatted || 'N/A'}`
                }
              }
            }
          });
        }
      } catch (err) {
        var rtChart = document.getElementById('responseTimeChart');
        if (rtChart) rtChart.parentElement.innerHTML = chartErrorHtml('Erreur de chargement des temps de r\u00e9ponse');
      }
    }

    async function loadAnomalies() {
      const overviewContainer = document.getElementById('anomaliesOverview');
      const meetingsContainer = document.getElementById('anomaliesMeetings');

      try {
        const { body } = await api(`/api/v1/analytics.php?type=anomalies&period=${currentPeriod}`);
        const data = body?.data;
        if (!data) throw new Error('Donn\u00e9es non disponibles');

        const indicators = data.indicators || {};
        const meetings = data.flagged_meetings || [];

        // Indicateurs de qualit\u00e9 (agr\u00e9g\u00e9s, sans identification)
        overviewContainer.innerHTML = `
          <div class="anomaly-indicators">
            <div class="anomaly-item ${indicators.low_participation_count > 0 ? 'warning' : 'ok'}">
              <div class="anomaly-icon">${indicators.low_participation_count > 0 ? icon('alert-triangle', 'icon-md icon-warning') : icon('check-circle', 'icon-md icon-success')}</div>
              <div class="anomaly-content">
                <div class="anomaly-label">Participation faible (&lt;50%)</div>
                <div class="anomaly-value">${indicators.low_participation_count || 0} s\u00e9ance(s)</div>
              </div>
            </div>
            <div class="anomaly-item ${indicators.quorum_issues_count > 0 ? 'warning' : 'ok'}">
              <div class="anomaly-icon">${indicators.quorum_issues_count > 0 ? icon('alert-triangle', 'icon-md icon-warning') : icon('check-circle', 'icon-md icon-success')}</div>
              <div class="anomaly-content">
                <div class="anomaly-label">Probl\u00e8mes de quorum</div>
                <div class="anomaly-value">${indicators.quorum_issues_count || 0} s\u00e9ance(s)</div>
              </div>
            </div>
            <div class="anomaly-item ${indicators.incomplete_votes_count > 0 ? 'info' : 'ok'}">
              <div class="anomaly-icon">${indicators.incomplete_votes_count > 0 ? icon('info', 'icon-md icon-info') : icon('check-circle', 'icon-md icon-success')}</div>
              <div class="anomaly-content">
                <div class="anomaly-label">R\u00e9solutions sans d\u00e9cision</div>
                <div class="anomaly-value">${indicators.incomplete_votes_count || 0} r\u00e9solution(s)</div>
              </div>
            </div>
            <div class="anomaly-item ${indicators.high_proxy_concentration > 0 ? 'warning' : 'ok'}">
              <div class="anomaly-icon">${indicators.high_proxy_concentration > 0 ? icon('alert-triangle', 'icon-md icon-warning') : icon('check-circle', 'icon-md icon-success')}</div>
              <div class="anomaly-content">
                <div class="anomaly-label">Concentration procurations (&gt;3/membre)</div>
                <div class="anomaly-value">${indicators.high_proxy_concentration || 0} cas</div>
              </div>
            </div>
            <div class="anomaly-item ${indicators.abstention_rate > 20 ? 'info' : 'ok'}">
              <div class="anomaly-icon">${indicators.abstention_rate > 20 ? icon('info', 'icon-md icon-info') : icon('check-circle', 'icon-md icon-success')}</div>
              <div class="anomaly-content">
                <div class="anomaly-label">Taux d'abstention moyen</div>
                <div class="anomaly-value">${indicators.abstention_rate || 0}%</div>
              </div>
            </div>
            <div class="anomaly-item ${indicators.very_short_votes_count > 0 ? 'info' : 'ok'}">
              <div class="anomaly-icon">${indicators.very_short_votes_count > 0 ? icon('info', 'icon-md icon-info') : icon('check-circle', 'icon-md icon-success')}</div>
              <div class="anomaly-content">
                <div class="anomaly-label">Votes tr\u00e8s courts (&lt;30s)</div>
                <div class="anomaly-value">${indicators.very_short_votes_count || 0} vote(s)</div>
              </div>
            </div>
          </div>
        `;

        // S\u00e9ances \u00e0 v\u00e9rifier
        if (meetings.length === 0) {
          meetingsContainer.innerHTML = '<p class="text-muted text-center p-4">Aucune anomalie d\u00e9tect\u00e9e</p>';
        } else {
          meetingsContainer.innerHTML = `
            <table class="data-table">
              <thead>
                <tr>
                  <th scope="col">S\u00e9ance</th>
                  <th scope="col">Date</th>
                  <th scope="col">Participation</th>
                  <th scope="col">Anomalies</th>
                </tr>
              </thead>
              <tbody>
                ${meetings.slice(0, 10).map(m => `
                  <tr>
                    <td>${escapeHtml(m.title?.substring(0, 25) || 'S\u00e9ance')}</td>
                    <td>${m.date ? new Date(m.date).toLocaleDateString('fr-FR') : '-'}</td>
                    <td>${Math.round(parseFloat(m.participation_rate) || 0)}%</td>
                    <td>
                      ${m.flags?.map(f => `<span class="badge badge-warning anomaly-flag">${escapeHtml(f)}</span>`).join('') || '-'}
                    </td>
                  </tr>
                `).join('')}
              </tbody>
            </table>
          `;
        }
      } catch (err) {
        overviewContainer.innerHTML = `<div class="error-message">Erreur: ${escapeHtml(err.message)}</div>`;
        meetingsContainer.innerHTML = '';
      }
    }

    // Chart export buttons
    document.querySelectorAll('.chart-export-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        const chartId = this.dataset.chartId;
        const canvas = document.getElementById(chartId);
        if (canvas) {
          const link = document.createElement('a');
          link.download = chartId + '.png';
          link.href = canvas.toDataURL('image/png');
          link.click();
        }
      });
    });

    // Initial load
    loadAllData();
  })();
