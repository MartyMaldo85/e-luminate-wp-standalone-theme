/**
 * Contact Form 7: floating labels — label text sits in the field, then animates up on focus / when filled.
 *
 * Primary field selection: FLOAT_LABEL_PRIMARY_SELECTOR (documented in styles/page-contact.css).
 * After upgrade, the control also gets .eluminate-float-primary for CSS hooks and faster re-query after CF7 DOM swaps.
 */
(function () {
	'use strict';

	/** @type {string} Kept in sync with the comment block in page-contact.css */
	const FLOAT_LABEL_PRIMARY_SELECTOR =
		'input:not([type="checkbox"]):not([type="radio"]):not([type="submit"]):not([type="button"]):not([type="hidden"]):not([type="file"]), textarea, select';

	/** Class added to the primary control so CSS (and re-init) can target it without repeating the long selector. */
	const FLOAT_LABEL_PRIMARY_CLASS = 'eluminate-float-primary';

	function getPrimaryControl(wrap) {
		if (!wrap) {
			return null;
		}
		const marked = wrap.querySelector('.' + FLOAT_LABEL_PRIMARY_CLASS);
		if (marked) {
			return marked;
		}
		return wrap.querySelector(FLOAT_LABEL_PRIMARY_SELECTOR);
	}

	function extractLabelText(label, wrap) {
		const parts = [];
		for (let n = label.firstChild; n && n !== wrap; n = n.nextSibling) {
			if (n.nodeType === Node.TEXT_NODE) {
				parts.push(n.textContent);
			} else if (n.nodeName === 'BR') {
				parts.push(' ');
			} else if (
				n.nodeType === Node.ELEMENT_NODE &&
				!(n.classList && n.classList.contains('wpcf7-form-control-wrap'))
			) {
				parts.push(n.textContent || '');
			}
		}
		return parts.join('').replace(/\s+/g, ' ').trim();
	}

	function updateHasValue(control, label) {
		if (!control) {
			return;
		}
		const filled = String(control.value || '').trim() !== '';
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
		const labels = root.querySelectorAll('.wpcf7-form label');
		for (let i = 0; i < labels.length; i++) {
			const label = labels[i];
			if (label.querySelector('.eluminate-float-label__text')) {
				continue;
			}
			const wrap = label.querySelector('.wpcf7-form-control-wrap');
			if (!wrap || !label.contains(wrap)) {
				continue;
			}
			const control = getPrimaryControl(wrap);
			if (!control) {
				continue;
			}
			const labelText = extractLabelText(label, wrap);
			if (!labelText) {
				continue;
			}
			while (label.firstChild && label.firstChild !== wrap) {
				label.removeChild(label.firstChild);
			}
			const span = document.createElement('span');
			span.className = 'eluminate-float-label__text';
			span.textContent = labelText;
			label.insertBefore(span, wrap);
			label.classList.add('eluminate-float-label');
			if (control.tagName === 'TEXTAREA') {
				label.classList.add('eluminate-float-label--textarea');
			}
			control.classList.add(FLOAT_LABEL_PRIMARY_CLASS);
			bindControl(control, label);
		}
	}

	function refreshAllFloatLabels(root) {
		if (!root || !root.querySelectorAll) {
			return;
		}
		const labels = root.querySelectorAll('label.eluminate-float-label');
		for (let i = 0; i < labels.length; i++) {
			const label = labels[i];
			const wrap = label.querySelector('.wpcf7-form-control-wrap');
			const control = wrap ? getPrimaryControl(wrap) : null;
			updateHasValue(control, label);
		}
	}

	function bindDelegatedHasValueSync() {
		function syncFromEvent(ev) {
			const t = ev.target;
			if (!t || t.nodeType !== 1) {
				return;
			}
			const tag = t.tagName;
			if (tag !== 'INPUT' && tag !== 'TEXTAREA' && tag !== 'SELECT') {
				return;
			}
			const typ = t.type;
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
			const wrap = t.closest('.wpcf7-form-control-wrap');
			if (!wrap || !wrap.closest('.wpcf7-form')) {
				return;
			}
			const label = wrap.closest('label.eluminate-float-label');
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
					const scope = document.querySelector('.body-page .entry-content') || document.body;
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
