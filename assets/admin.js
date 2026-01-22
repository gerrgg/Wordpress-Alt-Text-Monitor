(function () {
  const el = document.getElementById('fatm-job');
  if (!el) return;

  const nonce = el.getAttribute('data-nonce');
  let timer = null;

  function stop() {
    if (timer) {
      clearInterval(timer);
      timer = null;
    }
  }

  function start() {
    if (!timer) {
      timer = setInterval(tick, 1500);
    }
  }

  function escapeHtml(str) {
    return String(str)
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;');
  }

  function pct(current, total) {
    if (!total || total <= 0) return 0;
    return Math.max(0, Math.min(100, Math.floor((current / total) * 100)));
  }

  function render(job) {
    const status = job?.status || '';
    const type = job?.type || '';
    const msg = job?.message || '';
    const cur = Number(job?.progress?.current || 0);
    const tot = Number(job?.progress?.total || 0);
    const p = pct(cur, tot);
    const err = job?.error || '';

    const counts = job?.id ? '' : '';

    let html = '';
    html += '<div style="display:flex;gap:12px;align-items:baseline;flex-wrap:wrap;">';
    html += '<div><strong>Status:</strong> ' + escapeHtml(status) + '</div>';
    html += '<div><strong>Type:</strong> ' + escapeHtml(type) + '</div>';
    html += '</div>';

    if (msg) {
      html += '<p style="margin:8px 0 10px;">' + escapeHtml(msg) + '</p>';
    }

    html +=
      '<div style="background:#f0f0f1;border:1px solid #ccd0d4;border-radius:4px;overflow:hidden;height:18px;max-width:520px;">' +
      '<div style="background:#2271b1;height:18px;width:' + p + '%;"></div>' +
      '</div>';

    html += '<p class="description" style="margin:6px 0 0;">' +
      cur + ' / ' + tot + ' (' + p + '%)' +
      '</p>';

    if (err) {
      html += '<div style="margin-top:12px;padding:10px 12px;border-left:4px solid #d63638;background:#fff;">' +
        '<strong>Error:</strong> ' + escapeHtml(err) +
        '</div>';
    }

    if (status === 'completed') {
      html += '<p style="margin-top:12px;">' +
        '<a class="button button-primary" href="' + escapeHtml(window.ajaxurl.replace('admin-ajax.php', 'admin.php?page=fatm-results')) + '">' +
        'View Results</a></p>';
    }

    // Optional: keep a small debug toggle
    html +=
      '<details style="margin-top:12px;">' +
      '<summary style="cursor:pointer;">Debug</summary>' +
      '<pre style="margin:8px 0 0;white-space:pre-wrap;">' +
      escapeHtml(JSON.stringify(job, null, 2)) +
      '</pre></details>';

    el.innerHTML = html;
  }

  function tick() {
    const fd = new FormData();
    fd.append('action', 'fatm_run_step');
    fd.append('_ajax_nonce', nonce);

    fetch(ajaxurl, { method: 'POST', body: fd })
      .then(r => r.json())
      .then(({ data }) => {
        if (!data) return;

        const job = data.job;

        if (!job) {
          stop();
          el.innerHTML = '<p>No scan running.</p>';
          return;
        }

        render(job);

        if (job.status === 'running') {
          start();
        } else {
          stop();
        }
      })
      .catch(() => {
        stop();
      });
  }

  // First tick decides whether to poll.
  tick();
})();
