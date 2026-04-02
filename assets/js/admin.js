(function () {
	'use strict';

	var authModeSelect = document.querySelector('[data-cloudflare-cirino-auth-mode="1"]');
	var authPanels = document.querySelectorAll('[data-cloudflare-cirino-auth-panel]');

	function syncAuthModePanels() {
		if (!authModeSelect) {
			return;
		}

		var mode = authModeSelect.value;
		authPanels.forEach(function (panel) {
			var panelMode = panel.getAttribute('data-cloudflare-cirino-auth-panel');
			panel.style.display = panelMode === mode ? '' : 'none';
		});
	}

	if (authModeSelect) {
		authModeSelect.addEventListener('change', syncAuthModePanels);
		syncAuthModePanels();
	}

	document.addEventListener('click', function (event) {
		var trigger = event.target.closest('[data-cloudflare-cirino-confirm="1"]');
		if (!trigger) {
			return;
		}

		var message = trigger.getAttribute('data-cloudflare-cirino-confirm-text') || 'Run cache purge now?';
		var confirmed = window.confirm(message);
		if (!confirmed) {
			event.preventDefault();
		}
	});
})();
