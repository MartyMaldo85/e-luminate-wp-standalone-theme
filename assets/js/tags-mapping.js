/**
 * Insert/remove [eluminate-videos tag_slug="…"] shortcode blocks when
 * Tags mapping checkboxes are toggled in the page editor.
 */
(function () {
	'use strict';

	var SHORTCODE_TAG = 'eluminate-videos';

	function shortcodeForSlug(slug) {
		return '[' + SHORTCODE_TAG + ' tag_slug="' + slug + '"]';
	}

	function hasBlockEditor() {
		try {
			return !!(
				window.wp &&
				wp.data &&
				wp.blocks &&
				wp.data.select('core/block-editor') &&
				wp.data.dispatch('core/block-editor')
			);
		} catch (e) {
			return false;
		}
	}

	function textMatchesVideosShortcode(text, slug, termId) {
		if (!text || text.indexOf('[' + SHORTCODE_TAG) === -1) {
			return false;
		}
		if (slug) {
			if (
				text.indexOf('tag_slug="' + slug + '"') !== -1 ||
				text.indexOf("tag_slug='" + slug + "'") !== -1
			) {
				return true;
			}
		}
		if (termId) {
			var idRe = new RegExp(
				'\\bterm_id=["\']?' + String(termId) + '["\']?'
			);
			if (idRe.test(text)) {
				return true;
			}
		}
		return false;
	}

	function blockMatchesTerm(block, slug, termId) {
		if (!block || block.name !== 'core/shortcode') {
			return false;
		}
		var text = (block.attributes && block.attributes.text) || '';
		return textMatchesVideosShortcode(text, slug, termId);
	}

	function flattenBlocks(blocks) {
		var all = [];
		(blocks || []).forEach(function (block) {
			all.push(block);
			if (block.innerBlocks && block.innerBlocks.length) {
				all = all.concat(flattenBlocks(block.innerBlocks));
			}
		});
		return all;
	}

	function findBlocksForTerm(slug, termId) {
		var blocks = flattenBlocks(
			wp.data.select('core/block-editor').getBlocks() || []
		);
		return blocks.filter(function (block) {
			return blockMatchesTerm(block, slug, termId);
		});
	}

	function contentHasTermShortcode(slug, termId) {
		if (
			!window.wp ||
			!wp.data ||
			!wp.data.select('core/editor') ||
			!wp.data.select('core/editor').getEditedPostContent
		) {
			return false;
		}
		var content = wp.data.select('core/editor').getEditedPostContent() || '';
		return textMatchesVideosShortcode(content, slug, termId);
	}

	function insertBlockEditorShortcode(slug, termId) {
		if (
			findBlocksForTerm(slug, termId).length ||
			contentHasTermShortcode(slug, termId)
		) {
			return;
		}
		var block = wp.blocks.createBlock('core/shortcode', {
			text: shortcodeForSlug(slug),
		});
		wp.data.dispatch('core/block-editor').insertBlocks(block);
	}

	function removeBlockEditorShortcode(slug, termId) {
		findBlocksForTerm(slug, termId).forEach(function (block) {
			wp.data.dispatch('core/block-editor').removeBlock(block.clientId);
		});
	}

	function getClassicContent() {
		if (window.tinymce && tinymce.get('content')) {
			return tinymce.get('content').getContent({ format: 'raw' });
		}
		var textarea = document.getElementById('content');
		return textarea ? textarea.value : '';
	}

	function setClassicContent(html) {
		if (window.tinymce && tinymce.get('content')) {
			tinymce.get('content').setContent(html);
			tinymce.get('content').fire('change');
			return;
		}
		var textarea = document.getElementById('content');
		if (textarea) {
			textarea.value = html;
			textarea.dispatchEvent(new Event('change', { bubbles: true }));
		}
	}

	function classicHasTerm(content, slug, termId) {
		return textMatchesVideosShortcode(content, slug, termId);
	}

	function insertClassicShortcode(slug, termId) {
		var content = getClassicContent();
		if (classicHasTerm(content, slug, termId)) {
			return;
		}
		var chunk = shortcodeForSlug(slug);
		var next =
			content.replace(/\s+$/, '') === ''
				? chunk
				: content.replace(/\s+$/, '') + '\n\n' + chunk;
		setClassicContent(next);
	}

	function removeClassicShortcode(slug, termId) {
		var content = getClassicContent();
		var tag = SHORTCODE_TAG;
		var escapedSlug = slug
			? slug.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
			: '';

		var slugRe = slug
			? new RegExp(
					'<!--\\s*wp:shortcode\\s*-->\\s*\\[' +
						tag +
						'[^\\]]*?\\btag_slug=["\']' +
						escapedSlug +
						'["\'][^\\]]*\\]\\s*<!--\\s*\\/wp:shortcode\\s*-->\\s*',
					'gi'
				)
			: null;
		var slugPlain = slug
			? new RegExp(
					'\\[' +
						tag +
						'[^\\]]*?\\btag_slug=["\']' +
						escapedSlug +
						'["\'][^\\]]*\\]\\s*',
					'gi'
				)
			: null;
		var idBlock = termId
			? new RegExp(
					'<!--\\s*wp:shortcode\\s*-->\\s*\\[' +
						tag +
						'[^\\]]*?\\bterm_id=["\']?' +
						termId +
						'["\']?[^\\]]*\\]\\s*<!--\\s*\\/wp:shortcode\\s*-->\\s*',
					'gi'
				)
			: null;
		var idPlain = termId
			? new RegExp(
					'\\[' +
						tag +
						'[^\\]]*?\\bterm_id=["\']?' +
						termId +
						'["\']?[^\\]]*\\]\\s*',
					'gi'
				)
			: null;

		[slugRe, slugPlain, idBlock, idPlain].forEach(function (re) {
			if (re) {
				content = content.replace(re, '');
			}
		});
		setClassicContent(content);
	}

	function onCheckboxChange(input) {
		var slug = input.getAttribute('data-term-slug') || '';
		var termId = parseInt(input.value, 10) || 0;
		if (!slug && !termId) {
			return;
		}

		if (input.checked) {
			if (hasBlockEditor()) {
				insertBlockEditorShortcode(slug, termId);
			} else {
				insertClassicShortcode(slug, termId);
			}
			return;
		}

		if (hasBlockEditor()) {
			removeBlockEditorShortcode(slug, termId);
		} else {
			removeClassicShortcode(slug, termId);
		}
	}

	function bindCheckboxes() {
		document.addEventListener('change', function (event) {
			var target = event.target;
			if (
				!(target instanceof HTMLInputElement) ||
				target.type !== 'checkbox' ||
				!target.classList.contains('eluminate-tags-term-checkbox')
			) {
				return;
			}
			onCheckboxChange(target);
		});
	}

	function isEditorSaving() {
		if (!window.wp || !wp.data || !wp.data.select('core/editor')) {
			return false;
		}
		var editor = wp.data.select('core/editor');
		return !!(
			(editor.isSavingPost && editor.isSavingPost()) ||
			(editor.isAutosavingPost && editor.isAutosavingPost())
		);
	}

	/**
	 * When a shortcode block is removed in the canvas, uncheck its term box.
	 */
	function syncCheckboxesFromBlocks() {
		if (!hasBlockEditor() || isEditorSaving()) {
			return;
		}
		var inputs = document.querySelectorAll(
			'.eluminate-tags-term-checkbox'
		);
		if (!inputs.length) {
			return;
		}
		inputs.forEach(function (input) {
			var slug = input.getAttribute('data-term-slug') || '';
			var termId = parseInt(input.value, 10) || 0;
			var present = findBlocksForTerm(slug, termId).length > 0;
			if (input.checked !== present) {
				input.checked = present;
			}
		});
	}

	/**
	 * Keep at most one shortcode block per tag_slug in the canvas.
	 */
	function dedupeEditorShortcodeBlocks() {
		if (!hasBlockEditor()) {
			return;
		}
		var seen = {};
		flattenBlocks(
			wp.data.select('core/block-editor').getBlocks() || []
		).forEach(function (block) {
			if (!block || block.name !== 'core/shortcode') {
				return;
			}
			var text = (block.attributes && block.attributes.text) || '';
			if (text.indexOf('[' + SHORTCODE_TAG) === -1) {
				return;
			}
			var match = text.match(/\btag_slug=["']([^"']+)["']/);
			var key = match ? match[1] : text;
			if (seen[key]) {
				wp.data.dispatch('core/block-editor').removeBlock(block.clientId);
				return;
			}
			seen[key] = true;
		});
	}

	function bindBlockEditorMirror() {
		if (!hasBlockEditor() || !wp.data.subscribe) {
			return;
		}
		var scheduled = null;
		var wasSaving = false;
		wp.data.subscribe(function () {
			var saving = isEditorSaving();
			if (saving && !wasSaving) {
				dedupeEditorShortcodeBlocks();
			}
			wasSaving = saving;

			if (scheduled) {
				return;
			}
			scheduled = window.setTimeout(function () {
				scheduled = null;
				syncCheckboxesFromBlocks();
			}, 100);
		});
	}

	function initTagsMapping() {
		bindCheckboxes();
		if (!window.wp || !wp.domReady) {
			bindBlockEditorMirror();
			return;
		}
		wp.domReady(function () {
			if (hasBlockEditor()) {
				bindBlockEditorMirror();
				return;
			}
			if (!wp.data || !wp.data.subscribe) {
				return;
			}
			var unsubscribe = wp.data.subscribe(function () {
				if (!hasBlockEditor()) {
					return;
				}
				if (typeof unsubscribe === 'function') {
					unsubscribe();
				}
				bindBlockEditorMirror();
			});
		});
	}

	initTagsMapping();
})();
