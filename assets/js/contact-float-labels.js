/**
 * Contact Form 7: floating labels — label text sits in the field, then animates up on focus / when filled.
 */
(function () {
	'use strict';

	function getPrimaryControl(wrap) {
		if (!wrap) {
			return null;
		}
		return wrap.querySelector(
			'input:not([type="checkbox"]):not([type="radio"]):not([type="submit"]):not([type="button"]):not([type="hidden"]):not([type="file"]), textarea, select'
		);
	}

	function extractLabelText(label, wrap) {
		var parts = [];
		var child = label.firstChild;
		while (child) {
			if (child === wrap) {
				break;
			}
			if (child.nodeType === 3) {
				parts.push(child.textContent);
			} else if (child.nodeName === 'BR') {
				parts.push(' ');
			} else if (
				child.nodeType === 1 &&
				child !== wrap &&
				(!child.classList || !child.classList.contains('wpcf7-form-control-wrap'))
			) {
				parts.push(child.textContent || '');
			}
			child = child.nextSibling;
		}
		return parts.join('').replace(/\s+/g, ' ').trim();
	}

	function updateHasValue(control, label) {
		if (!control) {
			return;
		}
		var filled = String(control.value || '').trim() !== '';
		label.classList.toggle('has-value', filled);
	}

	function bindControl(control, label) {
		function refresh() {
			updateHasValue(control, label);
		}
		control.addEventListener('input', refresh);
		control.addEventListener('change', refresh);
		control.addEventListener('blur', refresh);
		refresh();
	}

	function initFloatLabels(root) {
		if (!root || !root.querySelectorAll) {
			return;
		}
		var labels = root.querySelectorAll('.wpcf7-form label');
		for (var i = 0; i < labels.length; i++) {
			var label = labels[i];
			if (label.querySelector('.eluminate-float-label__text')) {
				continue;
			}
			var wrap = label.querySelector('.wpcf7-form-control-wrap');
			if (!wrap || !label.contains(wrap)) {
				continue;
			}
			var control = getPrimaryControl(wrap);
			if (!control) {
				continue;
			}
			var labelText = extractLabelText(label, wrap);
			if (!labelText) {
				continue;
			}
			while (label.firstChild && label.firstChild !== wrap) {
				label.removeChild(label.firstChild);
			}
			var span = document.createElement('span');
			span.className = 'eluminate-float-label__text';
			span.textContent = labelText;
			label.insertBefore(span, wrap);
			label.classList.add('eluminate-float-label');
			if (control.tagName === 'TEXTAREA') {
				label.classList.add('eluminate-float-label--textarea');
			}
			bindControl(control, label);
		}
	}

	function refreshAllFloatLabels(root) {
		if (!root || !root.querySelectorAll) {
			return;
		}
		var labels = root.querySelectorAll('label.eluminate-float-label');
		for (var i = 0; i < labels.length; i++) {
			var label = labels[i];
			var wrap = label.querySelector('.wpcf7-form-control-wrap');
			var control = wrap ? getPrimaryControl(wrap) : null;
			updateHasValue(control, label);
		}
	}

	function bindDelegatedHasValueSync() {
		function syncFromEvent(ev) {
			var t = ev.target;
			if (!t || t.nodeType !== 1) {
				return;
			}
			var tag = t.tagName;
			if (tag !== 'INPUT' && tag !== 'TEXTAREA' && tag !== 'SELECT') {
				return;
			}
			var typ = t.type;
			if (
				typ === 'checkbox' ||
				typ === 'radio' ||
				typ === 'submit' ||
				typ === 'button' ||
				typ === 'hidden' ||
				typ === 'file'
			) {
				return;
			}
			var wrap = t.closest('.wpcf7-form-control-wrap');
			if (!wrap || !wrap.closest('.wpcf7-form')) {
				return;
			}
			var label = wrap.closest('label.eluminate-float-label');
			if (!label) {
				return;
			}
			updateHasValue(t, label);
			window.requestAnimationFrame(function () {
				updateHasValue(t, label);
			});
		}

		/* Capture: CF7 may replace inputs after validation; per-control listeners would stay on detached nodes. */
		document.addEventListener('input', syncFromEvent, true);
		document.addEventListener('change', syncFromEvent, true);
		document.addEventListener('cut', syncFromEvent, true);
		document.addEventListener('paste', syncFromEvent, true);
	}

	function onReady() {
		initFloatLabels(document);
		bindDelegatedHasValueSync();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', onReady);
	} else {
		onReady();
	}

	['wpcf7submit', 'wpcf7invalid', 'wpcf7spam', 'wpcf7mailfailed', 'wpcf7mailsent'].forEach(function (evt) {
		document.addEventListener(
			evt,
			function () {
				window.requestAnimationFrame(function () {
					var scope = document.querySelector('.body-page .entry-content') || document.body;
					initFloatLabels(scope);
					refreshAllFloatLabels(scope);
					window.requestAnimationFrame(function () {
						refreshAllFloatLabels(scope);
					});
				});
			},
			true
		);
	});
})();
