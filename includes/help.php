<?php
$votehubHelpTips = [
    'dashboard.php' => [
        'icon' => '📊',
        'title' => 'Dashboard',
        'tips' => [
            'Stat cards show total voters, active elections, candidates, and votes cast at a glance.',
            'Your subscription plan info is shown at the top — click Manage to renew.',
            'Recent elections are listed below for quick access to results.',
        ]
    ],
    'voters.php' => [
        'icon' => '👤',
        'title' => 'Voters',
        'tips' => [
            'Add voters manually or import via CSV using the template provided.',
            'Use the search bar to filter voters by name or student ID instantly.',
            'Reset a voter\'s password if they forget it — they\'ll get a new random password.',
            'Delete a voter only if necessary — their vote history is also removed.',
        ]
    ],
    'elections.php' => [
        'icon' => '🗳',
        'title' => 'Elections',
        'tips' => [
            'Create an election, then add positions (e.g. President, Secretary) and candidates.',
            'An election must be activated for voting to begin.',
            'Pending elections can be cancelled. Active ones can be closed or suspended.',
            'Suspended elections can be resumed. Deactivated elections can be reactivated.',
        ]
    ],
    'candidates.php' => [
        'icon' => '🏆',
        'title' => 'Candidates',
        'tips' => [
            'View all candidates across all elections in one place.',
            'Candidates are added per position within each election.',
            'You can add a photo and manifesto for each candidate.',
        ]
    ],
    'results.php' => [
        'icon' => '📈',
        'title' => 'Results',
        'tips' => [
            'Select an election to view its results with vote counts and percentages.',
            'Winners are shown with a 🏆 trophy and highlighted row.',
            'Candidates are ranked 1st, 2nd, 3rd based on votes received.',
            'Turnout shows what percentage of registered voters cast their ballot.',
        ]
    ],
    'payment.php' => [
        'icon' => '💳',
        'title' => 'Payments',
        'tips' => [
            'Select your desired plan — amounts auto-fill based on the plan price.',
            'After payment, the super admin must approve it before your subscription activates.',
            'You can view your payment history and approval status here.',
        ]
    ],
    'profile.php' => [
        'icon' => '⚙️',
        'title' => 'Settings',
        'tips' => [
            'Update your institution name, contact details, and location.',
            'Upload a logo and choose a brand color for your voter portal.',
            'The about section appears on your public school portal page.',
        ]
    ],
];

function getHelpForVoteHubPage(string $page): ?array {
    global $votehubHelpTips;
    return $votehubHelpTips[$page] ?? null;
}

function renderHelpButtonVoteHub(): string {
    return '
<style>
@keyframes handWaveVh{0%,100%{transform:rotate(0deg)}25%{transform:rotate(-18deg)}50%{transform:rotate(8deg)}75%{transform:rotate(-8deg)}}
.help-fab-vh{position:fixed;bottom:24px;right:24px;z-index:1000;background:#111;color:#fff;border:1px solid rgba(201,161,39,.35);padding:10px 20px;border-radius:50px;cursor:grab;font-weight:600;font-size:.82rem;box-shadow:0 4px 16px rgba(0,0,0,.3);display:flex;align-items:center;gap:8px;transition:all .2s;user-select:none;touch-action:none}
.help-fab-vh:active{cursor:grabbing}
.help-fab-vh:hover{color:#c9a127;border-color:#c9a127}
.help-fab-vh .help-wave-vh{display:inline-block;animation:handWaveVh 1.6s ease-in-out infinite;transform-origin:70% 80%;font-size:1.2rem}
</style>
<button type="button" class="help-fab-vh" id="helpFabVh" title="Help"><span class="help-wave-vh">🙋</span> Help</button>
<script>
(function(){
  var fab = document.getElementById("helpFabVh");
  if (!fab) return;
  var saved = localStorage.getItem("vh_help_pos");
  if (saved) {
    try {
      var p = JSON.parse(saved);
      fab.style.bottom = "auto"; fab.style.right = "auto";
      fab.style.left = p.x + "px"; fab.style.top = p.y + "px";
    } catch(e){}
  }
  var dragStartX, dragStartY, dragged = false;
  fab.addEventListener("mousedown", startDrag);
  fab.addEventListener("touchstart", startDrag, {passive: true});
  fab.addEventListener("click", function(e){
    if (dragged) { e.stopPropagation(); dragged = false; return; }
    toggleHelpVh();
  });
  function startDrag(e){
    dragged = false;
    var p = e.touches ? e.touches[0] : e;
    dragStartX = p.clientX; dragStartY = p.clientY;
    var rect = fab.getBoundingClientRect();
    var shiftX = p.clientX - rect.left;
    var shiftY = p.clientY - rect.top;
    fab.style.bottom = "auto"; fab.style.right = "auto";
    function moveAt(cx, cy){
      fab.style.left = (cx - shiftX) + "px";
      fab.style.top = (cy - shiftY) + "px";
    }
    function onMove(ev){
      var pt = ev.touches ? ev.touches[0] : ev;
      if (Math.abs(pt.clientX - dragStartX) > 5 || Math.abs(pt.clientY - dragStartY) > 5) dragged = true;
      ev.preventDefault();
      moveAt(pt.clientX, pt.clientY);
    }
    function onUp(){
      document.removeEventListener("mousemove", onMove);
      document.removeEventListener("mouseup", onUp);
      document.removeEventListener("touchmove", onMove);
      document.removeEventListener("touchend", onUp);
      try {
        var r = fab.getBoundingClientRect();
        localStorage.setItem("vh_help_pos", JSON.stringify({x: Math.round(r.left), y: Math.round(r.top)}));
      } catch(e){}
    }
    document.addEventListener("mousemove", onMove);
    document.addEventListener("mouseup", onUp);
    document.addEventListener("touchmove", onMove, {passive: false});
    document.addEventListener("touchend", onUp);
  }
})();
</script>';
}

function renderHelpModalVoteHub(string $currentPage): string {
    $help = getHelpForVoteHubPage($currentPage);
    if (!$help) return '';

    $tipsHtml = '';
    foreach ($help['tips'] as $tip) {
        $tipsHtml .= "<li style=\"padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.05);font-size:.88rem;line-height:1.6;color:#8899bb\">💡 {$tip}</li>";
    }

    return '
    <div class="modal-overlay" id="helpModalVh">
      <div class="modal" style="max-width:500px;padding:24px">
        <div class="modal-body">
          <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
            <div style="font-size:2rem">' . $help['icon'] . '</div>
            <div>
              <h3 style="color:#c9a127;font-size:1.1rem;margin:0">' . $help['title'] . ' — Quick Guide</h3>
              <p style="color:#8899bb;font-size:.8rem;margin:4px 0 0">Tips to help you work faster</p>
            </div>
          </div>
          <ul style="list-style:none;padding:0;margin:0">' . $tipsHtml . '</ul>
          <button type="button" class="btn btn-gold" style="width:100%;margin-top:18px;padding:10px;border-radius:8px;cursor:pointer;font-weight:600" onclick="closeModal(\'helpModalVh\')">Got it</button>
        </div>
      </div>
    </div>';
}
