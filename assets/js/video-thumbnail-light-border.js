/**
 * Adds a subtle edge when a video thumbnail is mostly white/near-white so it doesn't disappear into the panel.
 * Uses canvas sampling; cross-origin images only work if the CDN sends CORS headers (otherwise skipped).
 */
(function () {
	'use strict';

	const SELECTOR = 'img.video-series-thumbnail, img.video-entry-thumbnail';
	/** Fraction of sampled pixels that must read as near-white */
	const WHITE_PIXEL_RATIO = 0.5;
	/** Mean luminance (0–1) above which we treat the frame as very bright */
	const MEAN_LUMINANCE = 0.93;

	function sampleMostlyWhite(imageData) {
		const d = imageData.data;
		let whiteish = 0;
		let n = 0;
		let sumL = 0;
		for (let i = 0; i < d.length; i += 4) {
			if (d[i + 3] < 12) {
				continue;
			}
			n++;
			const r = d[i];
			const g = d[i + 1];
			const b = d[i + 2];
			const L = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
			sumL += L;
			if (r > 236 && g > 236 && b > 236) {
				whiteish++;
			}
		}
		if (!n) {
			return false;
		}
		const avgL = sumL / n;
		const whiteRatio = whiteish / n;
		return whiteRatio >= WHITE_PIXEL_RATIO || avgL >= MEAN_LUMINANCE;
	}

	function check(img) {
		if (img.dataset.eluminateLightChecked === '1') {
			return;
		}
		const src = img.currentSrc || img.src;
		if (!src || src.indexOf('data:') === 0) {
			img.dataset.eluminateLightChecked = '1';
			return;
		}
		const probe = new Image();
		probe.crossOrigin = 'anonymous';
		probe.onload = function () {
			try {
				const w = 56;
				const nw = probe.naturalWidth || w;
				const nh = probe.naturalHeight || 1;
				const h = Math.max(1, Math.round((nh / nw) * w));
				const canvas = document.createElement('canvas');
				canvas.width = w;
				canvas.height = h;
				const ctx = canvas.getContext('2d');
				ctx.drawImage(probe, 0, 0, w, h);
				const id = ctx.getImageData(0, 0, w, h);
				if (sampleMostlyWhite(id)) {
					img.classList.add('video-thumbnail--mostly-light');
				}
			} catch (e) {
				/* Tainted canvas (no CORS) or unsupported — leave thumbnail unchanged */
			}
			img.dataset.eluminateLightChecked = '1';
		};
		probe.onerror = function () {
			img.dataset.eluminateLightChecked = '1';
		};
		probe.src = src;
	}

	function init() {
		const nodes = document.querySelectorAll(SELECTOR);
		if (!nodes.length) {
			return;
		}
		if (!('IntersectionObserver' in window)) {
			Array.prototype.forEach.call(nodes, check);
			return;
		}
		const io = new IntersectionObserver(
			function (entries) {
				entries.forEach(function (entry) {
					if (!entry.isIntersecting) {
						return;
					}
					io.unobserve(entry.target);
					check(entry.target);
				});
			},
			{ root: null, rootMargin: '80px', threshold: 0.01 }
		);
		Array.prototype.forEach.call(nodes, function (img) {
			io.observe(img);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
