/**
 * On-site playlist episode switching for singular videos pages.
 * Updates the main iframe without leaving for youtube.com.
 * Uses replaceState so Back/Forward leave the series page (no per-episode history).
 */
(function () {
	'use strict';

	var player = document.querySelector('[data-eluminate-series-player]');
	var list = document.querySelector('[data-eluminate-series-episodes]');
	var dataEl = document.getElementById('eluminate-series-episodes-data');
	if (!player || !list || !dataEl) {
		return;
	}

	var iframe = player.querySelector('[data-eluminate-series-iframe]');
	var titleEl = player.querySelector('[data-eluminate-series-title]');
	var descEl = player.querySelector('[data-eluminate-series-description]');
	if (!iframe) {
		return;
	}

	var episodes = [];
	try {
		episodes = JSON.parse(dataEl.textContent || '[]');
	} catch (e) {
		return;
	}
	if (!episodes.length) {
		return;
	}

	var byId = {};
	episodes.forEach(function (ep) {
		if (ep && ep.id) {
			byId[ep.id] = ep;
		}
	});

	function embedUrl(id, autoplay) {
		var url = 'https://www.youtube.com/embed/' + encodeURIComponent(id);
		if (autoplay) {
			url += '?autoplay=1';
		}
		return url;
	}

	function setPlayingCard(vid) {
		list.querySelectorAll('[data-eluminate-series-episode]').forEach(function (card) {
			var isActive = card.getAttribute('data-vid') === vid;
			card.classList.toggle('is-playing', isActive);
			if (isActive) {
				card.setAttribute('hidden', '');
			} else {
				card.removeAttribute('hidden');
			}
		});
	}

	function playEpisode(vid) {
		var ep = byId[vid];
		if (!ep) {
			return;
		}

		iframe.src = embedUrl(ep.id, true);
		iframe.setAttribute('title', ep.title || '');
		if (titleEl) {
			titleEl.textContent = ep.title || '';
		}
		if (descEl) {
			descEl.innerHTML = ep.description || '';
		}
		setPlayingCard(ep.id);

		// Replace URL for sharing/reload without creating Back-stack entries per episode.
		if (ep.url && window.history && window.history.replaceState) {
			window.history.replaceState(null, '', ep.url);
		}
	}

	function vidFromUrl(href) {
		try {
			var url = new URL(href, window.location.origin);
			return url.searchParams.get('vid') || '';
		} catch (e) {
			return '';
		}
	}

	list.addEventListener('click', function (event) {
		var link = event.target.closest('a');
		if (!link || !list.contains(link)) {
			return;
		}
		var card = link.closest('[data-eluminate-series-episode]');
		if (!card) {
			return;
		}
		var vid = card.getAttribute('data-vid') || vidFromUrl(link.href);
		if (!vid || !byId[vid]) {
			return;
		}
		event.preventDefault();
		playEpisode(vid);
		if (player.scrollIntoView) {
			player.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
		}
	});
})();
