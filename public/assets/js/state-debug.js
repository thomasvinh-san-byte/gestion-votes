/* State Debug panel (Trust/Operator/President):
   Shows meeting_id, current motion, eligible count, ballots count, tokens active/expired.
*/
(function(){
  const $ = (s) => document.querySelector(s);
  function meetingId(){ try { return (new URLSearchParams(location.search)).get('meeting_id') || ''; } catch(e){ return ''; } }

  function setText(id, txt){
    const el = document.getElementById(id);
    if (el) el.textContent = txt;
  }

  async function fetchJson(url){
    const r = await fetch(url, { credentials:'same-origin' });
    const txt = await r.text();
    try{ return JSON.parse(txt); }catch(e){ return null; }
  }

  async function refresh(){
    const mid = meetingId();
    if (!mid) return;

    setText('dbgMeetingId', mid);

    const [cur, ready, anom] = await Promise.all([
      fetchJson(`/api/v1/current_motion.php?meeting_id=${encodeURIComponent(mid)}`),
      fetchJson(`/api/v1/meeting_ready_check.php?meeting_id=${encodeURIComponent(mid)}`),
      fetchJson(`/api/v1/operator_anomalies.php?meeting_id=${encodeURIComponent(mid)}`),
    ]);

    const m = cur && cur.motion ? cur.motion : null;
    setText('dbgCurrentMotion', m && m.id ? `${m.title || '—'} (#${m.id})` : '—');
    setText('dbgCurrentMotionState', m && m.id ? (m.closed_at ? 'clôturée' : (m.opened_at ? 'ouverte' : 'non ouverte')) : '—');

    const meta = ready && ready.meta ? ready.meta : {};
    if (meta.eligible_count != null) setText('dbgEligible', String(meta.eligible_count));
    setText('dbgFallback', meta.fallback_eligible_used ? 'oui' : 'non');

    const g = anom && anom.global ? anom.global : {};
    setText('dbgOpenMotions', g.open_motions_count != null ? String(g.open_motions_count) : '—');
    setText('dbgUnexpectedBallots', g.unexpected_ballots_count != null ? String(g.unexpected_ballots_count) : '—');
    setText('dbgExpiredTokens', g.expired_unused_tokens_count != null ? String(g.expired_unused_tokens_count) : '—');

    if (m && m.id && anom && Array.isArray(anom.motions)) {
      const row = anom.motions.find(x => String(x.id) === String(m.id));
      if (row) {
        setText('dbgBallots', String(row.ballots_count ?? '—'));
        setText('dbgMissing', String(row.missing_count ?? '—'));
        setText('dbgTokensActive', String(row.tokens_active_unused ?? '—'));
        setText('dbgTokensExpired', String(row.tokens_expired_unused ?? '—'));
      } else {
        setText('dbgBallots', '—'); setText('dbgMissing','—'); setText('dbgTokensActive','—'); setText('dbgTokensExpired','—');
      }
    }
  }

  function bind(){
    const btn = document.getElementById('btnDbgRefresh');
    btn?.addEventListener('click', () => refresh().catch(()=>{}));
    refresh().catch(()=>{});
    setInterval(() => refresh().catch(()=>{}), 1500);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind);
  else bind();
})();