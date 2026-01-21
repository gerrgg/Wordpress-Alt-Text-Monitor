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

  function escapeHtml(str) {
    return str
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;');
  }

  function render(job) {
    el.innerHTML =
      '<pre style="margin:0;white-space:pre-wrap;">' +
      escapeHtml(JSON.stringify(job, null, 2)) +
      '</pre>';
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
          // no job -> stop polling
          stop();
          return;
        }

        render(job);

        // stop polling unless job is running
        if (job.status !== 'running') {
          stop();
        }
      })
      .catch(() => {
        // if ajax fails, stop to avoid hammering
        stop();
      });
  }

  // initial tick, then keep polling until completed/cancelled/error
  tick();
  timer = setInterval(tick, 1500);
})();
