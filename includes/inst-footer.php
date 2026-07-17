    </div>
  </div>
</div>

<?php
require_once __DIR__ . '/help.php';
echo renderHelpButtonVoteHub();
echo renderHelpModalVoteHub($currentInstPage ?? basename($_SERVER['PHP_SELF']));
?>

<div class="confirm-overlay" id="confirmOverlay">
  <div class="confirm-box">
    <p id="confirmMsg">Are you sure?</p>
    <div class="confirm-actions">
      <button class="btn btn-ghost" onclick="closeConfirm()">Cancel</button>
      <button class="btn btn-gold" id="confirmYes">Yes</button>
    </div>
  </div>
</div>

<script>
let confirmCallback = null;
function confirmAction(msg, cb) {
  document.getElementById('confirmMsg').textContent = msg;
  confirmCallback = cb;
  document.getElementById('confirmOverlay').classList.add('show');
}
function closeConfirm() {
  document.getElementById('confirmOverlay').classList.remove('show');
  confirmCallback = null;
}
document.getElementById('confirmYes').addEventListener('click', () => {
  if (confirmCallback) confirmCallback();
  closeConfirm();
});
document.addEventListener('submit', e => {
  const form = e.target;
  if (form.hasAttribute('data-confirm')) {
    e.preventDefault();
    confirmAction(form.getAttribute('data-confirm'), () => { form.submit(); });
  }
});
document.addEventListener('click', e => {
  const el = e.target.closest('a[data-confirm]');
  if (el) {
    e.preventDefault();
    confirmAction(el.dataset.confirm, () => { window.location.href = el.href; });
  }
});
function toggleSidebar() {
  const sidebar = document.querySelector('.sidebar');
  if (!sidebar || window.getComputedStyle(sidebar).position !== 'fixed') return;
  const overlay = document.querySelector('.sidebar-overlay');
  const btn = document.querySelector('.hamburger');
  sidebar.classList.toggle('open');
  if (overlay) overlay.classList.toggle('show');
  if (btn) btn.textContent = sidebar.classList.contains('open') ? '✕' : '☰';
}
function toggleHelpVh() {
  const m = document.getElementById('helpModalVh');
  if (!m) return;
  if (m.style.display === 'flex') { closeModal('helpModalVh'); }
  else { openModal('helpModalVh'); }
}
function openModal(id) { const m = document.getElementById(id); if (m) { m.style.display = 'flex'; setTimeout(() => m.classList.add('open'), 10); } }
function closeModal(id) { const m = document.getElementById(id); if (m) { m.classList.remove('open'); setTimeout(() => m.style.display = 'none', 300); } }
let searchTimeout;
function globalSearch(q) {
  const resultsDiv = document.getElementById('searchResults');
  if (q.length < 2) { resultsDiv.style.display = 'none'; filterNav(q); return; }
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    fetch(BASE_URL + '/ajax/global-search.php?q=' + encodeURIComponent(q))
      .then(r => r.json())
      .then(data => {
        if ((!data.nav || data.nav.length === 0) && (!data.results || data.results.length === 0)) {
          resultsDiv.innerHTML = '<div style="padding:12px;color:#8899bb;font-size:.78rem;text-align:center">No results found</div>';
          resultsDiv.style.display = 'block';
          return;
        }
        let html = '';
        if (data.nav && data.nav.length > 0) {
          html += '<div style="padding:6px 12px;font-size:.7rem;color:#c9a127;font-weight:700;text-transform:uppercase;letter-spacing:1px">Pages</div>';
          data.nav.forEach(item => {
            html += '<a href="' + BASE_URL + item.url + '" style="display:flex;align-items:center;gap:8px;padding:8px 12px;color:#e0e0e0;text-decoration:none;font-size:.78rem;border-bottom:1px solid rgba(255,255,255,.04);transition:background .15s" onmouseover="this.style.background=\'rgba(255,255,255,.06)\'" onmouseout="this.style.background=\'transparent\'">'
              + '<span>' + item.icon + '</span>'
              + '<span>' + item.label + '</span>'
              + '</a>';
          });
        }
        if (data.results && data.results.length > 0) {
          let lastSection = '';
          data.results.forEach(item => {
            if (item.section !== lastSection) {
              lastSection = item.section;
              html += '<div style="padding:6px 12px;font-size:.7rem;color:#c9a127;font-weight:700;text-transform:uppercase;letter-spacing:1px;border-top:1px solid rgba(255,255,255,.06)">' + item.section + '</div>';
            }
            html += '<a href="' + item.url + '" style="display:flex;align-items:center;gap:8px;padding:8px 12px;color:#e0e0e0;text-decoration:none;font-size:.78rem;border-bottom:1px solid rgba(255,255,255,.04);transition:background .15s" onmouseover="this.style.background=\'rgba(255,255,255,.06)\'" onmouseout="this.style.background=\'transparent\'">'
              + '<span>' + item.icon + '</span>'
              + '<span>' + item.label + '</span>'
              + '</a>';
          });
        }
        resultsDiv.innerHTML = html;
        resultsDiv.style.display = 'block';
      });
  }, 250);
  filterNav(q);
}
document.addEventListener('click', e => {
  const sd = document.getElementById('searchResults');
  if (sd && !e.target.closest('#pageSearch') && !e.target.closest('#searchResults')) sd.style.display = 'none';
});
function filterNav(q) {
  q = q.toLowerCase().trim();
  document.querySelectorAll('#navList .nav-item').forEach(item => {
    const label = item.querySelector('.nav-label');
    if (!label) return;
    item.style.display = !q || label.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.flash').forEach(f => { setTimeout(() => { f.style.opacity = '0'; setTimeout(() => f.remove(), 500); }, 4000); });
});
</script>
</body>
</html>
