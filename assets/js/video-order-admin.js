/**
 * Drag-and-drop ordering for series cards (pages) and episodes (videos).
 */
(function ($) {
	'use strict';

	var cfg = window.eluminateVideoOrder || {};

	function hasBlockEditor() {
		return !!(
			window.wp &&
			wp.data &&
			wp.data.dispatch &&
			wp.data.dispatch('core/editor')
		);
	}

	function parseJson(value, fallback) {
		if (!value) {
			return fallback;
		}
		if (typeof value === 'object') {
			return value;
		}
		try {
			return JSON.parse(value);
		} catch (e) {
			return fallback;
		}
	}

	function syncEditorMeta(metaKey, value) {
		if (!hasBlockEditor() || !metaKey) {
			return;
		}
		var editor = wp.data.dispatch('core/editor');
		if (!editor || !editor.editPost) {
			return;
		}
		var patch = {};
		patch[metaKey] = value;
		editor.editPost({ meta: patch });
	}

	function renderItem(item) {
		var thumb = item.thumb
			? '<img class="eluminate-sortable-thumb" src="' +
			  String(item.thumb).replace(/"/g, '&quot;') +
			  '" alt="" loading="lazy" />'
			: '';
		return (
			'<li class="eluminate-sortable-item" data-id="' +
			item.id +
			'">' +
			'<span class="eluminate-sortable-handle" aria-hidden="true">≡</span>' +
			thumb +
			'<span class="eluminate-sortable-label">' +
			$('<div>').text(item.title || '').html() +
			'</span></li>'
		);
	}

	function renderList($ul, items) {
		$ul.empty();
		(items || []).forEach(function (item) {
			$ul.append(renderItem(item));
		});
	}

	function initSortable($ul, onUpdate) {
		if (!$ul.length || $ul.data('eluminate-sortable-init')) {
			return;
		}
		$ul.data('eluminate-sortable-init', true);
		$ul.sortable({
			handle: '.eluminate-sortable-handle',
			placeholder: 'eluminate-sortable-placeholder',
			forcePlaceholderSize: true,
			update: function () {
				if (typeof onUpdate === 'function') {
					onUpdate();
				}
			},
		});
	}

	function listPostIds($ul) {
		return $ul
			.find('.eluminate-sortable-item')
			.map(function () {
				return parseInt($(this).attr('data-id'), 10) || 0;
			})
			.get()
			.filter(function (id) {
				return id > 0;
			});
	}

	function listEpisodeCodes($ul) {
		return $ul
			.find('.eluminate-sortable-item')
			.map(function () {
				var id = $(this).attr('data-id');
				return id ? String(id) : '';
			})
			.get()
			.filter(function (id) {
				return id !== '';
			});
	}

	function readSeriesOrderMap() {
		var $input = $('#eluminate_tags_videos_order');
		return parseJson($input.val(), {});
	}

	function writeSeriesOrderMap(map) {
		var json = JSON.stringify(map || {});
		$('#eluminate_tags_videos_order').val(json);
		syncEditorMeta(cfg.seriesOrderMeta, json);
	}

	function syncSeriesSection($section) {
		var termId = String($section.attr('data-term-id') || '');
		if (!termId) {
			return;
		}
		var $ul = $section.find('.eluminate-series-sortable');
		var map = readSeriesOrderMap();
		map[termId] = listPostIds($ul);
		writeSeriesOrderMap(map);
	}

	function syncAllSeriesSections() {
		$('#eluminate-series-order-root .eluminate-order-section').each(function () {
			syncSeriesSection($(this));
		});
	}

	function writeEpisodeOrder() {
		var $ul = $('#eluminate-episode-order-root .eluminate-episode-sortable');
		var codes = listEpisodeCodes($ul);
		var json = JSON.stringify(codes);
		$('#eluminate_episode_order').val(json);
	}

	function ensureSeriesSection(termId, termName, termSlug, items) {
		var $root = $('#eluminate-series-order-root');
		if (!$root.length) {
			return;
		}
		$root.find('.eluminate-order-empty').first().remove();

		var selector =
			'.eluminate-order-section[data-term-id="' + String(termId) + '"]';
		var $section = $root.find(selector);
		if (!$section.length) {
			$section = $(
				'<div class="eluminate-order-section" data-term-id="' +
					termId +
					'" data-term-slug="' +
					$('<div>').text(termSlug || '').html() +
					'">' +
					'<h4 class="eluminate-order-section-title"></h4>' +
					'<ul class="eluminate-sortable-list eluminate-series-sortable"></ul>' +
					'</div>'
			);
			$root.append($section);
		}

		$section.find('.eluminate-order-section-title').text(termName || '');
		var $ul = $section.find('.eluminate-series-sortable');
		if (!items || !items.length) {
			$section.find('.eluminate-order-empty').remove();
			$section.append(
				'<p class="eluminate-order-empty"><em>' +
					(cfg.strings && cfg.strings.noSeriesDefault
						? cfg.strings.noSeriesDefault
						: 'No published videos with this tag yet.') +
					'</em></p>'
			);
			$ul.empty();
		} else {
			$section.find('.eluminate-order-empty').remove();
			renderList($ul, items);
			initSortable($ul, function () {
				syncSeriesSection($section);
			});
		}
	}

	function removeSeriesSection(termId) {
		$('#eluminate-series-order-root')
			.find('.eluminate-order-section[data-term-id="' + String(termId) + '"]')
			.remove();

		var map = readSeriesOrderMap();
		delete map[String(termId)];
		writeSeriesOrderMap(map);

		if (
			!$('#eluminate-series-order-root .eluminate-order-section').length &&
			!$('#eluminate-series-order-root .eluminate-order-empty').length
		) {
			$('#eluminate-series-order-root').html(
				'<p class="eluminate-order-empty"><em>' +
					(cfg.strings && cfg.strings.noTagSections
						? cfg.strings.noTagSections
						: 'Check a tag in the sidebar to add a video section, then arrange its videos here.') +
					'</em></p>'
			);
		}
	}

	function fetchSeriesForTerm(termId, termSlug, callback) {
		var map = readSeriesOrderMap();
		var order = map[String(termId)] || [];
		$.post(cfg.ajaxUrl, {
			action: 'eluminate_series_for_term',
			nonce: cfg.nonce,
			term_id: termId,
			order: order,
		})
			.done(function (response) {
				if (!response || !response.success || !response.data) {
					return;
				}
				ensureSeriesSection(
					response.data.term_id,
					response.data.term_name,
					response.data.term_slug || termSlug,
					response.data.items || []
				);
				syncSeriesSection(
					$('#eluminate-series-order-root').find(
						'.eluminate-order-section[data-term-id="' +
							String(termId) +
							'"]'
					)
				);
				if (typeof callback === 'function') {
					callback();
				}
			})
			.fail(function () {
				if (typeof callback === 'function') {
					callback();
				}
			});
	}

	function bindPageSeriesOrder() {
		var $root = $('#eluminate-series-order-root');
		if (!$root.length) {
			return;
		}

		$root.find('.eluminate-series-sortable').each(function () {
			var $ul = $(this);
			initSortable($ul, syncAllSeriesSections);
		});

		$(document).on('change', '.eluminate-tags-term-checkbox', function () {
			var $input = $(this);
			var termId = parseInt($input.val(), 10) || 0;
			var termSlug = $input.attr('data-term-slug') || '';
			if (!termId) {
				return;
			}
			if ($input.is(':checked')) {
				fetchSeriesForTerm(termId, termSlug);
				return;
			}
			removeSeriesSection(termId);
		});
	}

	function youtubeCodeFromThumbnail($item) {
		var href = $item.find('a[href*="watch?v="]').attr('href') || '';
		var match = href.match(/[?&]v=([^&]+)/);
		return match && match[1] ? decodeURIComponent(match[1]) : '';
	}

	function measureThumbnailPositions($items) {
		var positions = {};
		$items.each(function () {
			var $item = $(this);
			var code = youtubeCodeFromThumbnail($item);
			if (!code) {
				return;
			}
			var rect = $item[0].getBoundingClientRect();
			positions[code] = {
				left: rect.left,
				top: rect.top,
			};
		});
		return positions;
	}

	function thumbnailOrderMatches($items, order) {
		var current = $items
			.map(function () {
				return youtubeCodeFromThumbnail($(this));
			})
			.get()
			.filter(function (code) {
				return code !== '';
			});
		if (current.length !== order.length) {
			return false;
		}
		for (var i = 0; i < order.length; i += 1) {
			if (current[i] !== order[i]) {
				return false;
			}
		}
		return true;
	}

	function shouldAnimateThumbnails() {
		return !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	}

	function animateThumbnailReorder($niztechList, beforePositions) {
		var duration = 320;
		$niztechList.find('li.niztech-youtube-thumbnail').each(function () {
			var $item = $(this);
			var code = youtubeCodeFromThumbnail($item);
			var before = beforePositions[code];
			if (!before) {
				return;
			}

			var rect = this.getBoundingClientRect();
			var dx = before.left - rect.left;
			var dy = before.top - rect.top;
			if (Math.abs(dx) < 1 && Math.abs(dy) < 1) {
				return;
			}

			$item.addClass('eluminate-niztech-thumb-animating');
			$item.css({
				transform: 'translate(' + dx + 'px, ' + dy + 'px)',
				transition: 'none',
			});
			void this.offsetWidth;
			$item.css({
				transform: 'translate(0, 0)',
				transition: 'transform ' + duration + 'ms ease',
			});

			window.setTimeout(function () {
				$item.removeClass('eluminate-niztech-thumb-animating');
				$item.css({ transform: '', transition: '' });
			}, duration + 50);
		});
	}

	function enhanceNiztechThumbnailPictures() {
		$('.niztech-youtube-thumbnail-picture').each(function () {
			var $link = $(this);
			if ($link.data('eluminateThumbEnhanced')) {
				return;
			}

			var style = $link.attr('style') || '';
			var match = style.match(/url\((['"]?)([^'")]+)\1\)/i);
			if (!match || !match[2]) {
				return;
			}

			var alt = $link.find('.niztech-youtube-hidden').text() || '';
			var $img = $('<img>', {
				class: 'eluminate-niztech-thumb',
				src: match[2],
				alt: alt,
				loading: 'lazy',
			});
			$link.empty().append($img).removeAttr('style');
			$link.data('eluminateThumbEnhanced', true);
		});
	}

	function syncNiztechAdminThumbnails(animate) {
		var $episodeList = $('#eluminate-episode-order-root .eluminate-episode-sortable');
		var $niztechList = $('.niztech-youtube-thumbnails');
		enhanceNiztechThumbnailPictures();
		if (!$episodeList.length || !$niztechList.length) {
			return;
		}

		var order = listEpisodeCodes($episodeList);
		if (!order.length) {
			return;
		}

		var $items = $niztechList.find('li.niztech-youtube-thumbnail');
		if (thumbnailOrderMatches($items, order)) {
			return;
		}

		var beforePositions =
			animate && shouldAnimateThumbnails()
				? measureThumbnailPositions($items)
				: null;

		var byCode = {};
		$items.each(function () {
			var $item = $(this);
			var code = youtubeCodeFromThumbnail($item);
			if (code) {
				byCode[code] = $item;
			}
		});

		order.forEach(function (code) {
			if (byCode[code]) {
				$niztechList.append(byCode[code]);
			}
		});

		if (beforePositions) {
			window.requestAnimationFrame(function () {
				animateThumbnailReorder($niztechList, beforePositions);
			});
		}
	}

	function bindEpisodeOrder() {
		var $root = $('#eluminate-episode-order-root');
		if (!$root.length) {
			return;
		}
		var $ul = $root.find('.eluminate-episode-sortable');
		// Keep hidden input in sync while dragging; persisted on Update only.
		initSortable($ul, function () {
			writeEpisodeOrder();
			syncNiztechAdminThumbnails(true);
		});
		syncNiztechAdminThumbnails(false);

		var $form = $('#post');
		if ($form.length) {
			$form.on('submit', function () {
				writeEpisodeOrder();
			});
		}
		$(document).on('click', '#publish, #save-post', function () {
			writeEpisodeOrder();
		});
	}

	$(function () {
		bindPageSeriesOrder();
		bindEpisodeOrder();
		enhanceNiztechThumbnailPictures();
	});
})(jQuery);
