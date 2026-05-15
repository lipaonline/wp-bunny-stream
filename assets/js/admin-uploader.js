/* global WPBS, tus, jQuery */
(function ($) {
	'use strict';

	var $box, $progress, $bar, $text, $msg, $drop, $file, $current, currentUpload, currentGuid;

	function init() {
		$box      = $('.wpbs-uploader');
		if (!$box.length) return;
		$progress = $box.find('.wpbs-progress');
		$bar      = $box.find('.wpbs-progress-bar span');
		$text     = $box.find('.wpbs-progress-text');
		$msg      = $box.find('.wpbs-message');
		$drop     = $box.find('.wpbs-dropzone');
		$file     = $('#wpbs-file');
		$current  = $box.find('.wpbs-current');
		currentGuid = $box.data('guid') || '';

		$file.on('change', onFile);
		$drop.on('dragover', function (e) { e.preventDefault(); $drop.addClass('is-over'); });
		$drop.on('dragleave drop', function () { $drop.removeClass('is-over'); });
		$drop.on('drop', function (e) {
			e.preventDefault();
			if (e.originalEvent.dataTransfer && e.originalEvent.dataTransfer.files.length) {
				startWith(e.originalEvent.dataTransfer.files[0]);
			}
		});
		$box.on('click', '.wpbs-cancel', function () {
			if (currentUpload) { currentUpload.abort(true); }
			resetUI();
		});
		$box.on('click', '.wpbs-replace', function () {
			$box.find('.wpbs-current').hide();
			$drop.show();
		});
		$box.on('click', '.wpbs-refresh', refresh);
		$box.on('click', '.wpbs-link-btn', linkExisting);
		$box.on('click', '.wpbs-smart', triggerSmart);
		$box.on('click', '.wpbs-transcribe', triggerTranscribe);
		$box.on('click', '.wpbs-chapter-add', addChapterRow);
		$box.on('click', '.wpbs-chapter-remove', removeChapterRow);
		$box.on('click', '.wpbs-chapters-save', saveChapters);
		$box.on('click', '.wpbs-moment-add', addMomentRow);
		$box.on('click', '.wpbs-moment-remove', removeMomentRow);
		$box.on('click', '.wpbs-moments-save', saveMoments);

		$box.on('click', '.wpbs-cap-add', captionAdd);
		$box.on('click', '.wpbs-cap-edit', captionEdit);
		$box.on('click', '.wpbs-cap-delete', captionDelete);
		$box.on('click', '.wpbs-cap-cancel', captionCancel);
		$box.on('click', '.wpbs-cap-save', captionSave);
		$box.on('change', '.wpbs-cap-file', captionFileChange);

		renderChapterRows();
		renderMomentRows();
		renderCaptionList();
	}

	function momentRowHtml(m) {
		var label = m && m.label ? m.label : '';
		var ts    = m && typeof m.timestamp !== 'undefined' ? formatTime(m.timestamp) : '';
		return ''
			+ '<div class="wpbs-moment-row">'
			+   '<input type="text" class="wpbs-m-label" placeholder="Label" value="' + escapeHtml(label) + '">'
			+   '<input type="text" class="wpbs-m-ts" placeholder="0:00" value="' + escapeHtml(ts) + '">'
			+   '<button type="button" class="button-link wpbs-moment-remove" aria-label="Remove">✕</button>'
			+ '</div>';
	}

	function renderMomentRows() {
		var $wrap = $box.find('.wpbs-moments-rows');
		if (!$wrap.length) return;
		var data = [];
		try {
			data = JSON.parse($wrap.attr('data-moments') || '[]') || [];
		} catch (e) {}
		if (!data.length) data = [{ label: '', timestamp: 0 }];
		$wrap.html(data.map(momentRowHtml).join(''));
	}

	function addMomentRow() {
		$box.find('.wpbs-moments-rows').append(momentRowHtml({ label: '', timestamp: 0 }));
	}

	function removeMomentRow() {
		$(this).closest('.wpbs-moment-row').remove();
	}

	function saveMoments() {
		var $btn = $(this).prop('disabled', true).text('Saving…');
		var moments = $box.find('.wpbs-moment-row').map(function () {
			var $r = $(this);
			return {
				label: $r.find('.wpbs-m-label').val() || '',
				timestamp: parseTimeInput($r.find('.wpbs-m-ts').val())
			};
		}).get();

		$.post(WPBS.ajaxUrl, {
			action: 'wpbs_save_moments',
			nonce: WPBS.nonce,
			post_id: WPBS.postId,
			moments: moments
		}).done(function (res) {
			if (res.success) {
				showMessage('Moments saved to Bunny.', 'success');
				var $wrap = $box.find('.wpbs-moments-rows');
				$wrap.attr('data-moments', JSON.stringify(res.data.moments || []));
				renderMomentRows();
			} else {
				showMessage('Save failed: ' + (res.data && res.data.message || 'unknown'), 'error');
			}
		}).fail(function (xhr) {
			showMessage('Save failed: ' + (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message || xhr.statusText), 'error');
		}).always(function () {
			$btn.prop('disabled', false).text('Save moments to Bunny');
		});
	}

	function getCaptions() {
		var $ed = $box.find('.wpbs-captions-editor');
		try {
			return JSON.parse($ed.attr('data-captions') || '[]') || [];
		} catch (e) {
			return [];
		}
	}

	function setCaptions(arr) {
		$box.find('.wpbs-captions-editor').attr('data-captions', JSON.stringify(arr || []));
	}

	function renderCaptionList() {
		var $list = $box.find('.wpbs-captions-list');
		if (!$list.length) return;
		var caps = getCaptions();
		if (!caps.length) {
			$list.html('<p class="description">No captions yet.</p>');
			return;
		}
		var html = '<ul class="wpbs-list wpbs-cap-items">';
		caps.forEach(function (c) {
			var slug = c.srclang || '';
			var label = c.label || slug;
			html += '<li>'
				+ '<code>' + escapeHtml(slug) + '</code> ' + escapeHtml(label) + ' '
				+ '<button type="button" class="button-link wpbs-cap-edit" data-srclang="' + escapeHtml(slug) + '" data-label="' + escapeHtml(label) + '">Edit</button> · '
				+ '<button type="button" class="button-link wpbs-cap-delete" data-srclang="' + escapeHtml(slug) + '">Delete</button>'
				+ '</li>';
		});
		html += '</ul>';
		$list.html(html);
	}

	function captionAdd() {
		var $form = $box.find('.wpbs-caption-form');
		$form.find('.wpbs-caption-form-title').text('Add caption');
		$form.find('.wpbs-cap-lang').prop('disabled', false);
		$form.find('.wpbs-cap-label').val('');
		$form.find('.wpbs-cap-content').val('');
		$form.find('.wpbs-cap-file').val('');
		$form.data('mode', 'add').show();
	}

	function captionEdit() {
		var srclang = $(this).data('srclang');
		var label   = $(this).data('label');
		var $form   = $box.find('.wpbs-caption-form');
		$form.find('.wpbs-caption-form-title').text('Edit caption: ' + srclang);
		$form.find('.wpbs-cap-lang').val(srclang).prop('disabled', true);
		$form.find('.wpbs-cap-label').val(label);
		$form.find('.wpbs-cap-content').val('Loading existing caption…');
		$form.find('.wpbs-cap-file').val('');
		$form.data('mode', 'edit').data('srclang', srclang).show();

		$.post(WPBS.ajaxUrl, {
			action: 'wpbs_fetch_caption',
			nonce: WPBS.nonce,
			post_id: WPBS.postId,
			srclang: srclang
		}).done(function (res) {
			if (res.success) {
				$form.find('.wpbs-cap-content').val(res.data.content || '');
			} else {
				$form.find('.wpbs-cap-content').val('');
				showMessage('Could not load existing caption: ' + (res.data && res.data.message || 'unknown') + '. You can paste new content below.', 'warning');
			}
		}).fail(function () {
			$form.find('.wpbs-cap-content').val('');
		});
	}

	function captionCancel() {
		$box.find('.wpbs-caption-form').hide().removeData('mode srclang');
	}

	function captionFileChange(e) {
		var file = e.target.files && e.target.files[0];
		if (!file) return;
		var reader = new FileReader();
		reader.onload = function () {
			$box.find('.wpbs-cap-content').val(reader.result);
		};
		reader.readAsText(file);
	}

	function captionSave() {
		var $form   = $box.find('.wpbs-caption-form');
		var srclang = $form.find('.wpbs-cap-lang').val();
		var label   = $form.find('.wpbs-cap-label').val() || srclang.toUpperCase();
		var content = $form.find('.wpbs-cap-content').val();
		if (!content.trim()) {
			showMessage('Caption content is empty.', 'error');
			return;
		}

		var $btn = $form.find('.wpbs-cap-save').prop('disabled', true).text('Saving…');
		$.post(WPBS.ajaxUrl, {
			action: 'wpbs_save_caption',
			nonce: WPBS.nonce,
			post_id: WPBS.postId,
			srclang: srclang,
			label: label,
			content: content
		}).done(function (res) {
			if (res.success) {
				setCaptions(res.data.captions || []);
				renderCaptionList();
				captionCancel();
				showMessage('Caption saved.', 'success');
			} else {
				showMessage('Save failed: ' + (res.data && res.data.message || 'unknown'), 'error');
			}
		}).fail(function (xhr) {
			showMessage('Save failed: ' + (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message || xhr.statusText), 'error');
		}).always(function () {
			$btn.prop('disabled', false).text('Save caption');
		});
	}

	function captionDelete() {
		var srclang = $(this).data('srclang');
		if (!window.confirm('Delete caption "' + srclang + '"?')) return;
		var $btn = $(this).prop('disabled', true);
		$.post(WPBS.ajaxUrl, {
			action: 'wpbs_delete_caption',
			nonce: WPBS.nonce,
			post_id: WPBS.postId,
			srclang: srclang
		}).done(function (res) {
			if (res.success) {
				setCaptions(res.data.captions || []);
				renderCaptionList();
				showMessage('Caption deleted.', 'success');
			} else {
				showMessage('Delete failed: ' + (res.data && res.data.message || 'unknown'), 'error');
				$btn.prop('disabled', false);
			}
		}).fail(function () {
			$btn.prop('disabled', false);
		});
	}

	function parseTimeInput(str) {
		if (!str) return 0;
		str = String(str).trim();
		if (/^\d+$/.test(str)) return parseInt(str, 10);
		var parts = str.split(':').map(function (p) { return parseInt(p, 10) || 0; });
		if (parts.length === 2) return parts[0] * 60 + parts[1];
		if (parts.length === 3) return parts[0] * 3600 + parts[1] * 60 + parts[2];
		return 0;
	}

	function chapterRowHtml(ch) {
		var title = ch && ch.title ? ch.title : '';
		var start = ch && typeof ch.start !== 'undefined' ? formatTime(ch.start) : '';
		var end   = ch && typeof ch.end   !== 'undefined' ? formatTime(ch.end)   : '';
		return ''
			+ '<div class="wpbs-chapter-row">'
			+   '<input type="text" class="wpbs-ch-title" placeholder="Title" value="' + escapeHtml(title) + '">'
			+   '<input type="text" class="wpbs-ch-start" placeholder="0:00" value="' + escapeHtml(start) + '">'
			+   '<input type="text" class="wpbs-ch-end" placeholder="0:00" value="' + escapeHtml(end) + '">'
			+   '<button type="button" class="button-link wpbs-chapter-remove" aria-label="Remove">✕</button>'
			+ '</div>';
	}

	function renderChapterRows() {
		var $wrap = $box.find('.wpbs-chapters-rows');
		if (!$wrap.length) return;
		var data = [];
		try {
			data = JSON.parse($wrap.attr('data-chapters') || '[]') || [];
		} catch (e) {}
		if (!data.length) data = [{ title: '', start: 0, end: 0 }];
		$wrap.html(data.map(chapterRowHtml).join(''));
	}

	function addChapterRow() {
		var $wrap = $box.find('.wpbs-chapters-rows');
		var $rows = $wrap.find('.wpbs-chapter-row');
		var lastEnd = 0;
		if ($rows.length) {
			lastEnd = parseTimeInput($rows.last().find('.wpbs-ch-end').val());
		}
		$wrap.append(chapterRowHtml({ title: '', start: lastEnd, end: lastEnd }));
	}

	function removeChapterRow() {
		$(this).closest('.wpbs-chapter-row').remove();
	}

	function saveChapters() {
		var $btn = $(this).prop('disabled', true).text('Saving…');
		var chapters = $box.find('.wpbs-chapter-row').map(function () {
			var $r = $(this);
			return {
				title: $r.find('.wpbs-ch-title').val() || '',
				start: parseTimeInput($r.find('.wpbs-ch-start').val()),
				end:   parseTimeInput($r.find('.wpbs-ch-end').val())
			};
		}).get();

		$.post(WPBS.ajaxUrl, {
			action: 'wpbs_save_chapters',
			nonce: WPBS.nonce,
			post_id: WPBS.postId,
			chapters: chapters
		}).done(function (res) {
			if (res.success) {
				showMessage('Chapters saved to Bunny.', 'success');
				var $wrap = $box.find('.wpbs-chapters-rows');
				$wrap.attr('data-chapters', JSON.stringify(res.data.chapters || []));
				renderChapterRows();
			} else {
				showMessage('Save failed: ' + (res.data && res.data.message || 'unknown'), 'error');
			}
		}).fail(function (xhr) {
			showMessage('Save failed: ' + (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message || xhr.statusText), 'error');
		}).always(function () {
			$btn.prop('disabled', false).text('Save chapters to Bunny');
		});
	}

	function gatherAIArgs() {
		var source = $box.find('.wpbs-ai-source').val() || '';
		var targets = $box.find('.wpbs-ai-target:checked').map(function () {
			return this.value;
		}).get().filter(function (v) { return v && v !== source; });
		return { source: source, targets: targets };
	}

	function triggerSmart() {
		var args = gatherAIArgs();
		var $btn = $(this).prop('disabled', true).text('Queuing…');
		$.post(WPBS.ajaxUrl, {
			action: 'wpbs_smart_actions',
			nonce: WPBS.nonce,
			post_id: WPBS.postId,
			source_language: args.source
		}).done(function (res) {
			if (res.success) {
				showMessage('Chapters & moments generation queued on Bunny. They will appear in the player when ready.', 'success');
			} else {
				showMessage('Failed: ' + (res.data && res.data.message || 'unknown'), 'error');
			}
		}).fail(function (xhr) {
			showMessage('Failed: ' + (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message || xhr.statusText), 'error');
		}).always(function () {
			$btn.prop('disabled', false).text('Generate chapters + moments');
		});
	}

	function triggerTranscribe() {
		var args = gatherAIArgs();
		var langCount = 1 + args.targets.length;
		var confirmMsg = 'This will transcribe the video and translate to ' + (langCount - 1) + ' additional language(s).\n\n'
			+ 'Cost: $0.10 per minute × ' + langCount + ' language(s).\n\nContinue?';
		if (!window.confirm(confirmMsg)) return;

		var $btn = $(this).prop('disabled', true).text('Queuing…');
		$.post(WPBS.ajaxUrl, {
			action: 'wpbs_transcribe',
			nonce: WPBS.nonce,
			post_id: WPBS.postId,
			source_language: args.source,
			target_languages: args.targets
		}).done(function (res) {
			if (res.success) {
				showMessage('Transcription queued. Captions, chapters and moments will appear on Bunny within a few minutes.', 'success');
			} else {
				showMessage('Failed: ' + (res.data && res.data.message || 'unknown'), 'error');
			}
		}).fail(function (xhr) {
			showMessage('Failed: ' + (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message || xhr.statusText), 'error');
		}).always(function () {
			$btn.prop('disabled', false).text('Transcribe + generate all (paid)');
		});
	}

	function linkExisting() {
		var guid = ($('#wpbs-link-guid').val() || '').trim();
		if (!guid) return;
		var $btn = $('.wpbs-link-btn').prop('disabled', true).text('Linking…');
		$.post(WPBS.ajaxUrl, {
			action: 'wpbs_link_video',
			nonce: WPBS.nonce,
			post_id: WPBS.postId,
			guid: guid
		}).done(function (res) {
			if (res.success) {
				showMessage('Video linked successfully. Reloading…', 'success');
				setTimeout(function () { window.location.reload(); }, 1000);
			} else {
				showMessage('Link failed: ' + (res.data && res.data.message || 'unknown'), 'error');
				$btn.prop('disabled', false).text('Link video');
			}
		}).fail(function (xhr) {
			showMessage('Link failed: ' + (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message || xhr.statusText), 'error');
			$btn.prop('disabled', false).text('Link video');
		});
	}

	function onFile(e) {
		if (e.target.files && e.target.files[0]) {
			startWith(e.target.files[0]);
		}
	}

	function resetUI() {
		$progress.hide();
		$bar.css('width', '0%');
		$text.text('0%');
		$file.val('');
		currentUpload = null;
	}

	function showMessage(text, type) {
		$msg.removeClass('notice-success notice-error notice-warning')
			.addClass('notice notice-' + (type || 'info'))
			.html('<p>' + text + '</p>')
			.show();
	}

	function startWith(file) {
		if (!WPBS.configured) {
			showMessage(WPBS.i18n.configure, 'warning');
			return;
		}
		var title = $('#title').val() || file.name.replace(/\.[^/.]+$/, '');
		showMessage(WPBS.i18n.creating, 'info');

		$.post(WPBS.ajaxUrl, {
			action: 'wpbs_create_video',
			nonce: WPBS.nonce,
			post_id: WPBS.postId,
			title: title
		}).done(function (res) {
			if (!res.success) {
				showMessage((res.data && res.data.message) || WPBS.i18n.failed, 'error');
				return;
			}
			tusUpload(file, res.data.signature);
		}).fail(function (xhr) {
			showMessage(WPBS.i18n.failed + ': ' + (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message || xhr.statusText), 'error');
		});
	}

	function tusUpload(file, sig) {
		$drop.hide();
		$progress.show();
		$msg.hide();

		currentUpload = new tus.Upload(file, {
			endpoint: sig.endpoint,
			retryDelays: [0, 1000, 3000, 5000, 10000, 20000],
			chunkSize: 50 * 1024 * 1024,
			metadata: {
				filetype: file.type,
				title: file.name,
			},
			headers: {
				AuthorizationSignature: sig.signature,
				AuthorizationExpire: String(sig.expire),
				VideoId: sig.videoId,
				LibraryId: sig.libraryId
			},
			onError: function (error) {
				showMessage(WPBS.i18n.failed + ': ' + error, 'error');
				$progress.hide();
			},
			onProgress: function (bytesUploaded, bytesTotal) {
				var pct = ((bytesUploaded / bytesTotal) * 100).toFixed(1);
				$bar.css('width', pct + '%');
				$text.text(WPBS.i18n.uploading + ' ' + pct + '%');
			},
			onSuccess: function () {
				$.post(WPBS.ajaxUrl, {
					action: 'wpbs_save_video',
					nonce: WPBS.nonce,
					post_id: WPBS.postId
				});
				currentGuid = sig.videoId;
				showMessage(WPBS.i18n.done + ' ' + (WPBS.i18n.saveReminder || ''), 'success');
				$progress.hide();
				renderCurrent(sig.videoId);
				refresh();
			}
		});
		currentUpload.start();
	}

	function refresh() {
		var $rb = $box.find('.wpbs-refresh').prop('disabled', true);
		$.post(WPBS.ajaxUrl, {
			action: 'wpbs_refresh_video',
			nonce: WPBS.nonce,
			post_id: WPBS.postId
		}).done(function (res) {
			if (res.success) {
				$box.find('.wpbs-status-label')
					.text(res.data.label)
					.attr('data-status', res.data.status);
				if (res.data.duration) {
					$box.find('.wpbs-duration').text(formatTime(res.data.duration));
				}
				renderGenerated(res.data);
			}
		}).always(function () {
			$rb.prop('disabled', false);
		});
	}

	function formatTime(seconds) {
		seconds = Math.max(0, parseInt(seconds, 10) || 0);
		var h = Math.floor(seconds / 3600);
		var m = Math.floor((seconds % 3600) / 60);
		var s = seconds % 60;
		var pad = function (n) { return (n < 10 ? '0' : '') + n; };
		return h ? h + ':' + pad(m) + ':' + pad(s) : m + ':' + pad(s);
	}

	function renderGenerated(data) {
		// Sync chapter editor.
		if (data.chapters) {
			var $cw = $box.find('.wpbs-chapters-rows');
			if ($cw.length) {
				$cw.attr('data-chapters', JSON.stringify(data.chapters));
				renderChapterRows();
			}
		}
		// Sync moment editor.
		if (data.moments) {
			var $mw = $box.find('.wpbs-moments-rows');
			if ($mw.length) {
				$mw.attr('data-moments', JSON.stringify(data.moments));
				renderMomentRows();
			}
		}
		// Sync caption list.
		if (data.captions) {
			setCaptions(data.captions);
			renderCaptionList();
		}
	}

	function renderCurrent(guid) {
		$drop.hide();
		if ($current.length) {
			$current.find('code').text(guid);
			$current.show();
			return;
		}
		var html = ''
			+ '<div class="wpbs-current">'
			+   '<p><strong>Video GUID:</strong> <code>' + escapeHtml(guid) + '</code></p>'
			+   '<p><strong>Status:</strong> <span class="wpbs-status-label" data-status="0">Queued</span>'
			+   '&nbsp;|&nbsp;<strong>Duration:</strong> <span class="wpbs-duration">00:00</span></p>'
			+   '<p><em>Save the post to keep your title and any other changes.</em></p>'
			+   '<p>'
			+     '<button type="button" class="button wpbs-refresh">Refresh status</button> '
			+     '<button type="button" class="button wpbs-replace">Replace video</button>'
			+   '</p>'
			+ '</div>';
		$drop.before(html);
		$current = $box.find('.wpbs-current');
	}

	function escapeHtml(s) {
		return String(s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	$(init);
})(jQuery);
