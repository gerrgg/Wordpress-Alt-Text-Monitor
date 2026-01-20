(function () {
  const el = document.getElementById('fatm-job');
  if (!el) return;

  const nonce = el.getAttribute('data-nonce');

  function tick() {
    const fd = new FormData();
    fd.append('action', 'fatm_run_step');
    fd.append('_ajax_nonce', nonce);

    fetch(ajaxurl, { method: 'POST', body: fd })
      .then(r => r.json())
      .then(({ data }) => {
        if (!data || !data.job) return;
        el.innerHTML = '<pre style="margin:0;white-space:pre-wrap;">' +
          escapeHtml(JSON.stringify(data.job, null, 2)) +
          '</pre>';
      })
      .catch(() => {});
  }

  function escapeHtml(str) {
    return str
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;');
  }

  setInterval(tick, 1500);
})();
