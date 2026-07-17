    </div>
  </div>
</div>

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
    confirmAction(form.getAttribute('data-confirm'), () => { HTMLFormElement.prototype.submit.call(form); });
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
// Modal functions
function openModal(id) { const m = document.getElementById(id); if (m) { m.style.display = 'flex'; setTimeout(() => m.classList.add('open'), 10); } }
function closeModal(id) { const m = document.getElementById(id); if (m) { m.classList.remove('open'); setTimeout(() => m.style.display = 'none', 300); } }
// Flash auto-dismiss
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.flash').forEach(f => { setTimeout(() => { f.style.opacity = '0'; setTimeout(() => f.remove(), 500); }, 4000); });
});
</script>
</body>
</html>
