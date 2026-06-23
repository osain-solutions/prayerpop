(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		var messageNode = document.querySelector('#message.updated p');
		if (messageNode && window.prayerPopBulkEmail && prayerPopBulkEmail.changesSaved) {
			messageNode.textContent = prayerPopBulkEmail.changesSaved;
		}
	});

	function getMessages() {
		if (window.prayerPopBulkEmail && typeof window.prayerPopBulkEmail === 'object') {
			return window.prayerPopBulkEmail;
		}

		return {
			fieldLabel: 'Recipient email',
			fieldPlaceholder: 'name@example.com',
			invalidEmail: 'Please enter a valid recipient email address.',
			answerPromptNew: 'Optional answer update message (shown on the prayer card):',
			answerPromptEdit: 'Update the answered prayer message (leave empty to remove it):',
			answerPromptBulk: 'Optional answered prayer message for selected items (leave empty to keep current messages):',
			answerModalTitleNew: 'Mark Prayer as Answered',
			answerModalTitleEdit: 'Update Answered Prayer',
			answerModalTitleBulk: 'Bulk Mark as Answered',
			answerModalDescriptionNew: 'Optional answer update message shown on the prayer card.',
			answerModalDescriptionEdit: 'Update the answered prayer message. Leave empty to remove it.',
			answerModalDescriptionBulk: 'Add optional answer notes for selected prayer requests. You can fill only the ones you need.',
			answerModalCancel: 'Cancel',
			answerModalSave: 'Save',
			answerModalNoSelection: 'Please select at least one submission first.',
			answerModalSubmissionLabel: 'Submission',
			answerModalNoteLabel: 'Answered note',
			bulkEditModalTitle: 'Bulk Edit Submissions',
			bulkEditModalDescription: 'Edit selected submissions here. Leave Name empty to keep the item anonymous.',
			bulkEditNoSelection: 'Please select at least one submission first.',
			bulkEditNameLabel: 'Name',
			bulkEditNamePlaceholder: 'Leave empty for Anonymous',
			bulkEditTextLabel: 'Submission text',
			inlineTextSave: 'Save',
			inlineTextCancel: 'Cancel',
			inlineTextSaveError: 'Could not save this text. Please try again.',
		};
	}

	function isValidEmail(value) {
		if (!value) {
			return false;
		}

		var testInput = document.createElement('input');
		testInput.type = 'email';
		testInput.value = value;
		return testInput.checkValidity();
	}

	function ensureHiddenRecipientField(form) {
		var hidden = form.querySelector('input[name="prayer_pop_bulk_email"]');
		if (hidden) {
			return hidden;
		}

		hidden = document.createElement('input');
		hidden.type = 'hidden';
		hidden.name = 'prayer_pop_bulk_email';
		form.appendChild(hidden);
		return hidden;
	}

	function ensureHiddenAnsweredField(form) {
		var hidden = form.querySelector('input[name="prayer_pop_bulk_answered_message"]');
		if (hidden) {
			return hidden;
		}

		hidden = document.createElement('input');
		hidden.type = 'hidden';
		hidden.name = 'prayer_pop_bulk_answered_message';
		form.appendChild(hidden);
		return hidden;
	}

	function ensureHiddenAnsweredMapField(form) {
		var hidden = form.querySelector('input[name="prayer_pop_bulk_answered_messages"]');
		if (hidden) {
			return hidden;
		}

		hidden = document.createElement('input');
		hidden.type = 'hidden';
		hidden.name = 'prayer_pop_bulk_answered_messages';
		form.appendChild(hidden);
		return hidden;
	}

	function ensureHiddenBulkEditPayloadField(form) {
		var hidden = form.querySelector('input[name="prayer_pop_bulk_edit_payload"]');
		if (hidden) {
			return hidden;
		}

		hidden = document.createElement('input');
		hidden.type = 'hidden';
		hidden.name = 'prayer_pop_bulk_edit_payload';
		form.appendChild(hidden);
		return hidden;
	}

	function createRecipientField(select, messages) {
		var container = document.createElement('span');
		container.className = 'prayer-pop-bulk-email-wrap';
		container.style.marginLeft = '8px';
		container.style.display = 'none';

		var label = document.createElement('label');
		label.style.marginRight = '6px';
		label.style.fontWeight = '500';
		label.textContent = messages.fieldLabel + ':';

		var input = document.createElement('input');
		input.type = 'email';
		input.className = 'regular-text prayer-pop-bulk-email-input';
		input.placeholder = messages.fieldPlaceholder;
		input.setAttribute('aria-label', messages.fieldLabel);
		input.style.width = '220px';

		label.appendChild(input);
		container.appendChild(label);

		var bulkActionsWrap = select.closest('.bulkactions');
		if (bulkActionsWrap) {
			var applyButton = bulkActionsWrap.querySelector('input[type="submit"]');
			if (applyButton && applyButton.parentNode) {
				applyButton.parentNode.insertBefore(container, applyButton);
			} else {
				bulkActionsWrap.appendChild(container);
			}
		}

		return {
			container: container,
			input: input,
		};
	}

	function getActionValue(select) {
		if (!select) {
			return '-1';
		}
		return select.value || '-1';
	}

	function getCompactViewStorageKey() {
		return 'prayerPopSubmissionCompactView_v1';
	}

	function readCompactViewPreference() {
		try {
			return window.localStorage.getItem(getCompactViewStorageKey()) === '1';
		} catch (error) {
			return false;
		}
	}

	function writeCompactViewPreference(enabled) {
		try {
			window.localStorage.setItem(getCompactViewStorageKey(), enabled ? '1' : '0');
		} catch (error) {
			// Ignore storage failures.
		}
	}

	function applyCompactViewState(enabled) {
		var table = document.querySelector('.wp-list-table');
		if (!table) {
			return;
		}
		table.classList.toggle('pp-submissions-compact-view', !!enabled);
	}

	function findCompactToggleMountPoint() {
		var form = document.getElementById('posts-filter');
		var topPages = form ? (
			form.querySelector('.tablenav.top .tablenav-pages') ||
			form.querySelector(':scope > div > div')
		) : null;
		if (topPages) {
			var before = topPages.querySelector('.displaying-num');
			return {
				container: topPages,
				beforeNode: before || null,
			};
		}
		return null;
	}

	function updateCompactToggleLabel(button, enabled, messages) {
		if (!button) {
			return;
		}
		var onLabel = (messages && messages.compactViewOnLabel) ? messages.compactViewOnLabel : 'Compact On';
		var offLabel = (messages && messages.compactViewOffLabel) ? messages.compactViewOffLabel : 'Compact Off';
		var titleOn = (messages && messages.compactViewOnTitle) ? messages.compactViewOnTitle : 'Switch to full submission text';
		var titleOff = (messages && messages.compactViewOffTitle) ? messages.compactViewOffTitle : 'Switch to compact submission text';

		button.classList.toggle('is-active', !!enabled);
		button.setAttribute('aria-pressed', enabled ? 'true' : 'false');
		button.textContent = enabled ? onLabel : offLabel;
		button.title = enabled ? titleOn : titleOff;
	}



	function bindCompactRowExpandToggle() {
		document.addEventListener('click', function (event) {
			var table = document.querySelector('.wp-list-table.pp-submissions-compact-view');
			if (!table) {
				return;
			}

			var clickable = event.target.closest('.column-pp_name .pp-submission-full, .column-pp_name .pp-answered-note-text');
			if (!clickable) {
				return;
			}

			var row = clickable.closest('tr');
			if (!row) {
				return;
			}

			if (event.target.closest('.pp-inline-text-editor-actions, .pp-inline-text-editor-input')) {
				return;
			}

			// Keep compact rows expanded while inline text editor is opening.
			if (clickable.classList.contains('pp-inline-text-editable')) {
				row.classList.add('pp-compact-row-expanded');
				return;
			}

			event.preventDefault();
			row.classList.toggle('pp-compact-row-expanded');
		});
	}
	function addCompactToggle(messages) {
		if (document.getElementById('pp-admin-compact-toggle')) {
			return document.getElementById('pp-admin-compact-toggle');
		}

		var mount = findCompactToggleMountPoint();
		if (!mount || !mount.container) {
			return null;
		}

		var button = document.createElement('button');
		button.type = 'button';
		button.id = 'pp-admin-compact-toggle';
		button.className = 'button pp-admin-compact-toggle';

		var isEnabled = readCompactViewPreference();
		applyCompactViewState(isEnabled);
		updateCompactToggleLabel(button, isEnabled, messages);

		button.addEventListener('click', function () {
			var next = !readCompactViewPreference();
			writeCompactViewPreference(next);
			applyCompactViewState(next);
			updateCompactToggleLabel(button, next, messages);
		});

		if (mount.beforeNode) {
			mount.container.insertBefore(button, mount.beforeNode);
		} else {
			mount.container.appendChild(button);
		}

		return button;
	}

	function toggleRecipientField(select, fieldRef) {
		var shouldShow = getActionValue(select) === 'send_via_email';
		fieldRef.container.style.display = shouldShow ? 'inline-block' : 'none';
		if (!shouldShow) {
			fieldRef.input.setCustomValidity('');
		}
	}

	function getSelectBySubmitter(form, submitterName) {
		if (submitterName === 'doaction2') {
			return form.querySelector('select[name="action2"]');
		}

		if (submitterName === 'doaction') {
			return form.querySelector('select[name="action"]');
		}

		// Fallback when submitter info isn't available.
		var topSelect = form.querySelector('select[name="action"]');
		var bottomSelect = form.querySelector('select[name="action2"]');
		if (getActionValue(topSelect) !== '-1') {
			return topSelect;
		}
		return bottomSelect;
	}

	function createAnswerModalElements(messages) {
		var overlay = document.createElement('div');
		overlay.className = 'pp-answer-modal-overlay';
		overlay.style.display = 'none';

		var dialog = document.createElement('div');
		dialog.className = 'pp-answer-modal';
		dialog.setAttribute('role', 'dialog');
		dialog.setAttribute('aria-modal', 'true');
		dialog.setAttribute('aria-labelledby', 'pp-answer-modal-title');
		overlay.appendChild(dialog);

		var title = document.createElement('h2');
		title.id = 'pp-answer-modal-title';
		title.className = 'pp-answer-modal-title';
		dialog.appendChild(title);

		var description = document.createElement('p');
		description.className = 'pp-answer-modal-description';
		dialog.appendChild(description);

		var body = document.createElement('div');
		body.className = 'pp-answer-modal-body';
		dialog.appendChild(body);

		var footer = document.createElement('div');
		footer.className = 'pp-answer-modal-footer';
		dialog.appendChild(footer);

		var cancelBtn = document.createElement('button');
		cancelBtn.type = 'button';
		cancelBtn.className = 'button button-secondary';
		cancelBtn.textContent = messages.answerModalCancel || 'Cancel';
		footer.appendChild(cancelBtn);

		var saveBtn = document.createElement('button');
		saveBtn.type = 'button';
		saveBtn.className = 'button button-primary';
		saveBtn.textContent = messages.answerModalSave || 'Save';
		footer.appendChild(saveBtn);

		document.body.appendChild(overlay);
		return {
			overlay: overlay,
			dialog: dialog,
			title: title,
			description: description,
			body: body,
			cancelBtn: cancelBtn,
			saveBtn: saveBtn,
		};
	}

	function openSingleAnswerModal(messages, mode, initialValue) {
		return new Promise(function (resolve) {
			var modal = createAnswerModalElements(messages);
			var resolved = false;

			modal.title.textContent = mode === 'answered'
				? (messages.answerModalTitleEdit || 'Update Answered Prayer')
				: (messages.answerModalTitleNew || 'Mark Prayer as Answered');
			modal.description.textContent = mode === 'answered'
				? (messages.answerModalDescriptionEdit || '')
				: (messages.answerModalDescriptionNew || '');

			var textarea = document.createElement('textarea');
			textarea.className = 'pp-answer-modal-textarea';
			textarea.rows = 6;
			textarea.value = initialValue || '';
			textarea.setAttribute('aria-label', messages.answerModalNoteLabel || 'Answered note');
			modal.body.appendChild(textarea);

			function cleanup(value) {
				if (resolved) {
					return;
				}
				resolved = true;
				document.removeEventListener('keydown', onKeyDown);
				modal.overlay.remove();
				resolve(value);
			}

			function onKeyDown(event) {
				if (event.key === 'Escape') {
					event.preventDefault();
					cleanup(null);
				}
			}

			modal.cancelBtn.addEventListener('click', function () {
				cleanup(null);
			});
			modal.saveBtn.addEventListener('click', function () {
				cleanup(textarea.value);
			});
			modal.overlay.addEventListener('click', function (event) {
				if (event.target === modal.overlay) {
					cleanup(null);
				}
			});

			document.addEventListener('keydown', onKeyDown);
			modal.overlay.style.display = 'flex';
			textarea.focus();
		});
	}

	function collectSelectedBulkAnsweredItems(form) {
		var selected = form.querySelectorAll('tbody input[name="post[]"]:checked');
		var items = [];

		selected.forEach(function (checkbox) {
			var postId = String(checkbox.value || '').trim();
			if (!postId) {
				return;
			}

			var row = checkbox.closest('tr');
			var nameCell = row ? row.querySelector('td.column-pp_name') : null;
			var nameElement = nameCell ? nameCell.querySelector('strong') : null;
			var contentElement = nameCell ? nameCell.querySelector('.pp-submission-full') : null;
			var name = nameElement ? nameElement.textContent.trim() : '';
			var submissionText = contentElement ? contentElement.textContent.trim() : '';

			items.push({
				id: postId,
				name: name || ('#' + postId),
				submission: submissionText,
			});
		});

		return items;
	}

	function collectSelectedBulkEditItems(form) {
		var selected = form.querySelectorAll('tbody input[name="post[]"]:checked');
		var items = [];

		selected.forEach(function (checkbox) {
			var postId = String(checkbox.value || '').trim();
			if (!postId) {
				return;
			}

			var row = checkbox.closest('tr');
			var nameCell = row ? row.querySelector('td.column-pp_name') : null;
			var nameElement = nameCell ? nameCell.querySelector('strong') : null;
			var nameEditable = nameCell ? nameCell.querySelector('.pp-inline-text-name') : null;
			var textEditable = nameCell ? nameCell.querySelector('.pp-inline-text-body') : null;
			var textFallback = nameCell ? nameCell.querySelector('.pp-submission-full') : null;

			var displayName = nameElement ? nameElement.textContent.trim() : '';
			var rawName = nameEditable ? String(nameEditable.getAttribute('data-value') || '').trim() : '';
			var submissionText = '';
			if (textEditable) {
				submissionText = String(textEditable.getAttribute('data-value') || '').trim();
			} else if (textFallback) {
				submissionText = textFallback.textContent.trim();
			}

			items.push({
				id: postId,
				displayName: displayName || ('#' + postId),
				name: rawName,
				submission: submissionText,
			});
		});

		return items;
	}

	function openBulkAnswerModal(messages, items) {
		return new Promise(function (resolve) {
			var modal = createAnswerModalElements(messages);
			var resolved = false;

			modal.title.textContent = messages.answerModalTitleBulk || 'Bulk Mark as Answered';
			modal.description.textContent = messages.answerModalDescriptionBulk || '';
			modal.body.classList.add('pp-answer-modal-body-bulk');

			items.forEach(function (item) {
				var card = document.createElement('div');
				card.className = 'pp-answer-modal-item';
				card.setAttribute('data-post-id', item.id);

				var heading = document.createElement('div');
				heading.className = 'pp-answer-modal-item-heading';
				heading.textContent = item.name;
				card.appendChild(heading);

				if (item.submission) {
					var submissionLabel = document.createElement('div');
					submissionLabel.className = 'pp-answer-modal-item-label';
					submissionLabel.textContent = messages.answerModalSubmissionLabel || 'Submission';
					card.appendChild(submissionLabel);

					var submission = document.createElement('div');
					submission.className = 'pp-answer-modal-item-submission';
					submission.textContent = item.submission;
					card.appendChild(submission);
				}

				var noteLabel = document.createElement('div');
				noteLabel.className = 'pp-answer-modal-item-label';
				noteLabel.textContent = messages.answerModalNoteLabel || 'Answered note';
				card.appendChild(noteLabel);

				var textarea = document.createElement('textarea');
				textarea.className = 'pp-answer-modal-textarea';
				textarea.rows = 3;
				textarea.setAttribute('data-post-id', item.id);
				textarea.setAttribute('aria-label', (messages.answerModalNoteLabel || 'Answered note') + ' ' + item.name);
				card.appendChild(textarea);

				modal.body.appendChild(card);
			});

			function cleanup(value) {
				if (resolved) {
					return;
				}
				resolved = true;
				document.removeEventListener('keydown', onKeyDown);
				modal.overlay.remove();
				resolve(value);
			}

			function onKeyDown(event) {
				if (event.key === 'Escape') {
					event.preventDefault();
					cleanup(null);
				}
			}

			modal.cancelBtn.addEventListener('click', function () {
				cleanup(null);
			});
			modal.saveBtn.addEventListener('click', function () {
				var textareas = modal.body.querySelectorAll('textarea[data-post-id]');
				var perPostMap = {};
				textareas.forEach(function (textarea) {
					var postId = textarea.getAttribute('data-post-id');
					var value = String(textarea.value || '').trim();
					if (postId && value) {
						perPostMap[postId] = value;
					}
				});
				cleanup(perPostMap);
			});
			modal.overlay.addEventListener('click', function (event) {
				if (event.target === modal.overlay) {
					cleanup(null);
				}
			});

			document.addEventListener('keydown', onKeyDown);
			modal.overlay.style.display = 'flex';
			var firstTextarea = modal.body.querySelector('textarea');
			if (firstTextarea) {
				firstTextarea.focus();
			}
		});
	}

	function openBulkEditModal(messages, items) {
		return new Promise(function (resolve) {
			var modal = createAnswerModalElements(messages);
			var resolved = false;

			modal.title.textContent = messages.bulkEditModalTitle || 'Bulk Edit Submissions';
			modal.description.textContent = messages.bulkEditModalDescription || '';
			modal.body.classList.add('pp-answer-modal-body-bulk');

			items.forEach(function (item) {
				var card = document.createElement('div');
				card.className = 'pp-answer-modal-item';
				card.setAttribute('data-post-id', item.id);

				var heading = document.createElement('div');
				heading.className = 'pp-answer-modal-item-heading';
				heading.textContent = item.displayName;
				card.appendChild(heading);

				var nameLabel = document.createElement('div');
				nameLabel.className = 'pp-answer-modal-item-label';
				nameLabel.textContent = messages.bulkEditNameLabel || 'Name';
				card.appendChild(nameLabel);

				var nameInput = document.createElement('input');
				nameInput.type = 'text';
				nameInput.className = 'regular-text pp-answer-modal-textinput';
				nameInput.setAttribute('data-post-id', item.id);
				nameInput.setAttribute('data-field', 'name');
				nameInput.value = item.name || '';
				nameInput.setAttribute('placeholder', messages.bulkEditNamePlaceholder || 'Leave empty for Anonymous');
				nameInput.setAttribute('aria-label', (messages.bulkEditNameLabel || 'Name') + ' ' + item.displayName);
				card.appendChild(nameInput);

				var textLabel = document.createElement('div');
				textLabel.className = 'pp-answer-modal-item-label';
				textLabel.textContent = messages.bulkEditTextLabel || 'Submission text';
				card.appendChild(textLabel);

				var textarea = document.createElement('textarea');
				textarea.className = 'pp-answer-modal-textarea';
				textarea.rows = 4;
				textarea.setAttribute('data-post-id', item.id);
				textarea.setAttribute('data-field', 'submission');
				textarea.value = item.submission || '';
				textarea.setAttribute('aria-label', (messages.bulkEditTextLabel || 'Submission text') + ' ' + item.displayName);
				card.appendChild(textarea);

				modal.body.appendChild(card);
			});

			function cleanup(value) {
				if (resolved) {
					return;
				}
				resolved = true;
				document.removeEventListener('keydown', onKeyDown);
				modal.overlay.remove();
				resolve(value);
			}

			function onKeyDown(event) {
				if (event.key === 'Escape') {
					event.preventDefault();
					cleanup(null);
				}
			}

			modal.cancelBtn.addEventListener('click', function () {
				cleanup(null);
			});
			modal.saveBtn.addEventListener('click', function () {
				var perPostMap = {};
				var cards = modal.body.querySelectorAll('.pp-answer-modal-item[data-post-id]');
				cards.forEach(function (card) {
					var postId = card.getAttribute('data-post-id');
					if (!postId) {
						return;
					}
					var nameInput = card.querySelector('input[data-field="name"]');
					var textInput = card.querySelector('textarea[data-field="submission"]');
					perPostMap[postId] = {
						name: nameInput ? String(nameInput.value || '').trim() : '',
						submission: textInput ? String(textInput.value || '').trim() : '',
					};
				});
				cleanup(perPostMap);
			});
			modal.overlay.addEventListener('click', function (event) {
				if (event.target === modal.overlay) {
					cleanup(null);
				}
			});

			document.addEventListener('keydown', onKeyDown);
			modal.overlay.style.display = 'flex';
			var firstInput = modal.body.querySelector('input[data-field="name"]');
			if (firstInput) {
				firstInput.focus();
			}
		});
	}

	function bindAnsweredPrayerLinks(messages) {
		document.addEventListener('click', function (event) {
			var link = event.target.closest('a.pp-mark-answered-btn');
			if (!link) {
				return;
			}

			event.preventDefault();

			var mode = link.getAttribute('data-mode') || 'approved';
			var currentMessage = link.getAttribute('data-current-message') || '';
			openSingleAnswerModal(messages, mode, currentMessage).then(function (answerMessage) {
				if (answerMessage === null) {
					return;
				}
				var targetUrl = new URL(link.href, window.location.origin);
				targetUrl.searchParams.set('answered_message', answerMessage);
				window.location.href = targetUrl.toString();
			});
		});
	}

	function closeAllAiInfo(exceptWrap) {
		var wraps = document.querySelectorAll('.pp-ai-info-wrap.is-open');
		wraps.forEach(function (wrap) {
			if (exceptWrap && wrap === exceptWrap) {
				return;
			}
			wrap.classList.remove('is-open');
			var button = wrap.querySelector('.pp-ai-info-button');
			if (button) {
				button.setAttribute('aria-expanded', 'false');
			}
		});
	}

	function bindAiInfoTooltips() {
		document.addEventListener('click', function (event) {
			var button = event.target.closest('.pp-ai-info-button');
			if (button) {
				event.preventDefault();
				var wrap = button.closest('.pp-ai-info-wrap');
				if (!wrap) {
					return;
				}

				var willOpen = !wrap.classList.contains('is-open');
				closeAllAiInfo(wrap);
				wrap.classList.toggle('is-open', willOpen);
				button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
				return;
			}

			if (!event.target.closest('.pp-ai-info-wrap')) {
				closeAllAiInfo();
			}
		});

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				closeAllAiInfo();
			}
		});
	}

	function parseInlineOptions(button) {
		if (!button) {
			return [];
		}

		var raw = button.getAttribute('data-options') || '[]';
		try {
			var parsed = JSON.parse(raw);
			return Array.isArray(parsed) ? parsed : [];
		} catch (error) {
			return [];
		}
	}

	function buildInlineOptionButton(option) {
		var value = option && typeof option.value === 'string' ? option.value : '';
		var label = option && typeof option.label === 'string' ? option.label : '';
		var badgeClass = option && typeof option.badge_class === 'string' ? option.badge_class : '';
		var safeBadgeClass = badgeClass.replace(/[^a-z0-9_-]/gi, '');
		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'pp-inline-option pp-badge ' + safeBadgeClass;
		btn.setAttribute('data-value', value);
		btn.textContent = label || value;
		return btn;
	}

	function bindInlineBadgeEditors(messages) {
		if (!messages || !messages.ajaxUrl || !messages.inlineNonce) {
			return;
		}

		var menu = document.createElement('div');
		menu.className = 'pp-inline-badge-menu';
		menu.style.display = 'none';
		document.body.appendChild(menu);

		var activeButton = null;
		var saving = false;

		function closeMenu() {
			if (activeButton) {
				activeButton.setAttribute('aria-expanded', 'false');
			}
			activeButton = null;
			saving = false;
			menu.classList.remove('is-saving');
			menu.style.display = 'none';
			menu.innerHTML = '';
		}

		function placeMenu(button) {
			var rect = button.getBoundingClientRect();
			var top = rect.bottom + 8 + window.scrollY;
			var left = rect.left + window.scrollX;
			menu.style.top = top + 'px';
			menu.style.left = left + 'px';
		}

		function openMenu(button) {
			var options = parseInlineOptions(button);
			if (!options.length) {
				return;
			}

			if (activeButton === button && menu.style.display === 'block') {
				closeMenu();
				return;
			}

			if (activeButton) {
				activeButton.setAttribute('aria-expanded', 'false');
			}

			menu.innerHTML = '';
			options.forEach(function (option) {
				menu.appendChild(buildInlineOptionButton(option));
			});

			activeButton = button;
			activeButton.setAttribute('aria-expanded', 'true');
			placeMenu(button);
			menu.style.display = 'flex';
		}

		function sendInlineUpdate(postId, field, value, answeredMessage) {
			var body = new URLSearchParams();
			body.set('action', 'prayer_pop_inline_update_submission_field');
			body.set('nonce', messages.inlineNonce);
			body.set('post_id', postId);
			body.set('field', field);
			body.set('value', value);
			if (field === 'status' && value === 'answered' && typeof answeredMessage === 'string') {
				body.set('answered_message', answeredMessage);
			}

			return window.fetch(messages.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				},
				body: body.toString(),
			}).then(function (response) {
				return response.json();
			});
		}

		document.addEventListener('click', function (event) {
			var inlineBadge = event.target.closest('.pp-inline-editable');
			if (inlineBadge) {
				event.preventDefault();
				openMenu(inlineBadge);
				return;
			}

			if (!event.target.closest('.pp-inline-badge-menu')) {
				closeMenu();
			}
		});

		menu.addEventListener('click', function (event) {
			var optionButton = event.target.closest('.pp-inline-option');
			if (!optionButton || !activeButton || saving) {
				return;
			}

			if (activeButton.classList.contains('pp-tour-demo-only')) {
				closeMenu();
				return;
			}

			var postId = activeButton.getAttribute('data-post-id') || '';
			var field = activeButton.getAttribute('data-field') || '';
			var currentValue = activeButton.getAttribute('data-value') || '';
			var newValue = optionButton.getAttribute('data-value') || '';
			if (!postId || !field || !newValue) {
				window.alert(messages.inlineInvalid || 'Invalid update option.');
				closeMenu();
				return;
			}

			if (newValue === currentValue) {
				closeMenu();
				return;
			}

			function runUpdate(answeredMessage) {
				saving = true;
				menu.classList.add('is-saving');

				sendInlineUpdate(postId, field, newValue, answeredMessage)
					.then(function (result) {
						if (!result || !result.success) {
							var message = messages.inlineSaveError || 'Could not update this value. Please try again.';
							if (result && result.data && result.data.message) {
								message = result.data.message;
							}
							window.alert(message);
							return;
						}
						window.location.reload();
					})
					.catch(function () {
						window.alert(messages.inlineSaveError || 'Could not update this value. Please try again.');
					})
					.finally(function () {
						saving = false;
						menu.classList.remove('is-saving');
					});
			}

			if (field === 'status' && newValue === 'answered') {
				var existingMessage = activeButton.getAttribute('data-answered-message') || '';
				var mode = existingMessage ? 'answered' : 'approved';
				openSingleAnswerModal(messages, mode, existingMessage).then(function (answeredMessage) {
					if (answeredMessage === null) {
						closeMenu();
						return;
					}
					runUpdate(answeredMessage);
				});
				return;
			}

			runUpdate(null);
		});

		window.addEventListener('resize', closeMenu);
		window.addEventListener('scroll', closeMenu, true);
		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				closeMenu();
			}
		});
	}

	function bindInlineTextEditors(messages) {
		if (!messages || !messages.ajaxUrl || !messages.inlineNonce) {
			return;
		}
		var activeEditor = null;
		var pointerDownInsideEditor = false;
		var suppressNextOutsideClose = false;

		function sendInlineTextUpdate(postId, field, textValue) {
			var body = new URLSearchParams();
			body.set('action', 'prayer_pop_inline_update_submission_field');
			body.set('nonce', messages.inlineNonce);
			body.set('post_id', postId);
			body.set('field', field);
			body.set('value', 'text');
			body.set('text_value', textValue);

			return window.fetch(messages.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				},
				body: body.toString(),
				}).then(function (response) {
					return response.json();
				});
		}

		function closeActiveEditor(restoreContent) {
			if (!activeEditor) {
				return;
			}

			var shouldRestore = (restoreContent !== false);
			var editor = activeEditor;
			editor.el.classList.remove('is-editing', 'is-saving');
			editor.el.setAttribute('aria-expanded', 'false');
			editor.el.removeAttribute('aria-busy');
			editor.el.removeAttribute('data-editing');
			if (shouldRestore) {
				editor.el.innerHTML = editor.originalHTML;
			}

			var compactTable = document.querySelector('.wp-list-table.pp-submissions-compact-view');
			if (compactTable) {
				var row = editor.el.closest('tr');
				if (row) {
					row.classList.remove('pp-compact-row-expanded');
				}
			}
			activeEditor = null;
		}

		function createEditorField(multiline) {
			var field;
			if (multiline) {
				field = document.createElement('textarea');
				field.className = 'pp-inline-text-editor-input pp-inline-text-editor-textarea';
			} else {
				field = document.createElement('input');
				field.type = 'text';
				field.className = 'pp-inline-text-editor-input pp-inline-text-editor-input-single';
			}
			return field;
		}

		function openInlineEditor(trigger) {
			if (!trigger) {
				return;
			}

			if (activeEditor && activeEditor.el === trigger) {
				return;
			}

			if (activeEditor && !activeEditor.saving) {
				closeActiveEditor(true);
			}

			var originalHTML = trigger.innerHTML;
			var multiline = trigger.getAttribute('data-multiline') === '1';
			var value = trigger.getAttribute('data-value') || '';
			var placeholder = trigger.getAttribute('data-placeholder') || '';

			trigger.classList.add('is-editing');
			trigger.setAttribute('aria-expanded', 'true');
			trigger.setAttribute('aria-busy', 'false');
			trigger.setAttribute('data-editing', '1');
			trigger.innerHTML = '';

			var editorField = createEditorField(multiline);
			editorField.value = value;
			if (placeholder) {
				editorField.setAttribute('placeholder', placeholder);
			}

			var fieldWrap = document.createElement('div');
			fieldWrap.className = 'pp-inline-text-editor-field-wrap';
			fieldWrap.appendChild(editorField);

			var actionsWrap = document.createElement('div');
			actionsWrap.className = 'pp-inline-text-editor-actions';

			var cancelBtn = document.createElement('button');
			cancelBtn.type = 'button';
			cancelBtn.className = 'button button-small';
			cancelBtn.textContent = messages.inlineTextCancel || 'Cancel';
			actionsWrap.appendChild(cancelBtn);

			var saveBtn = document.createElement('button');
			saveBtn.type = 'button';
			saveBtn.className = 'button button-small button-primary';
			saveBtn.textContent = messages.inlineTextSave || 'Save';
			actionsWrap.appendChild(saveBtn);

			trigger.appendChild(fieldWrap);
			trigger.appendChild(actionsWrap);

			activeEditor = {
				el: trigger,
				input: editorField,
				multiline: multiline,
				originalHTML: originalHTML,
				saving: false,
				cancelBtn: cancelBtn,
				saveBtn: saveBtn,
			};

			function setSaving(isSaving) {
				if (!activeEditor || activeEditor.el !== trigger) {
					return;
				}
				activeEditor.saving = !!isSaving;
				trigger.classList.toggle('is-saving', !!isSaving);
				trigger.setAttribute('aria-busy', isSaving ? 'true' : 'false');
				editorField.disabled = !!isSaving;
				cancelBtn.disabled = !!isSaving;
				saveBtn.disabled = !!isSaving;
			}

			function saveActiveEditor() {
				if (!activeEditor || activeEditor.el !== trigger || activeEditor.saving) {
					return;
				}

				if (trigger.classList.contains('pp-tour-demo-only')) {
					closeActiveEditor(true);
					return;
				}

				var postId = trigger.getAttribute('data-post-id') || '';
				var field = trigger.getAttribute('data-field') || '';
				var textValue = String(editorField.value || '');
				if (!postId || !field) {
					window.alert(messages.inlineTextSaveError || 'Could not save this text. Please try again.');
					closeActiveEditor(true);
					return;
				}

				setSaving(true);

				sendInlineTextUpdate(postId, field, textValue)
					.then(function (result) {
						if (!result || !result.success) {
							var message = messages.inlineTextSaveError || 'Could not save this text. Please try again.';
							if (result && result.data && result.data.message) {
								message = result.data.message;
							}
							window.alert(message);
							setSaving(false);
							return;
						}
						window.location.reload();
					})
					.catch(function () {
						window.alert(messages.inlineTextSaveError || 'Could not save this text. Please try again.');
						setSaving(false);
					});
			}

			cancelBtn.addEventListener('click', function (event) {
				event.preventDefault();
				event.stopPropagation();
				if (activeEditor && activeEditor.el === trigger && !activeEditor.saving) {
					closeActiveEditor(true);
				}
			});

			saveBtn.addEventListener('click', function (event) {
				event.preventDefault();
				event.stopPropagation();
				saveActiveEditor();
			});

			editorField.addEventListener('keydown', function (event) {
				if (event.key === 'Escape') {
					event.preventDefault();
					if (!activeEditor || activeEditor.saving) {
						return;
					}
					closeActiveEditor(true);
					return;
				}

				if (!multiline && event.key === 'Enter') {
					event.preventDefault();
					saveActiveEditor();
					return;
				}

				if ((event.metaKey || event.ctrlKey) && event.key === 'Enter') {
					event.preventDefault();
					saveActiveEditor();
				}
			});

			editorField.focus();
			if (!multiline && typeof editorField.select === 'function') {
				editorField.select();
			}
		}

		document.addEventListener('click', function (event) {
			var trigger = event.target.closest('.pp-inline-text-editable');
			if (trigger) {
				if (!trigger.classList.contains('is-editing')) {
					event.preventDefault();
					openInlineEditor(trigger);
				}
				return;
			}

			if (suppressNextOutsideClose) {
				suppressNextOutsideClose = false;
				return;
			}

			if (activeEditor && !activeEditor.saving) {
				closeActiveEditor(true);
			}
		});

		document.addEventListener('mousedown', function (event) {
			if (!activeEditor) {
				pointerDownInsideEditor = false;
				return;
			}

			pointerDownInsideEditor = !!event.target.closest('.pp-inline-text-editable');
		});

		document.addEventListener('mouseup', function (event) {
			if (!activeEditor || activeEditor.saving || !pointerDownInsideEditor) {
				pointerDownInsideEditor = false;
				return;
			}

			// Keep editor open when text selection starts inside the field and ends outside it.
			if (!event.target.closest('.pp-inline-text-editable')) {
				suppressNextOutsideClose = true;
			}

			pointerDownInsideEditor = false;
		});

		document.addEventListener('keydown', function (event) {
			var trigger = event.target && event.target.closest ? event.target.closest('.pp-inline-text-editable') : null;
			if (trigger && !trigger.classList.contains('is-editing') && (event.key === 'Enter' || event.key === ' ')) {
				event.preventDefault();
				openInlineEditor(trigger);
				return;
			}

			if (event.key === 'Escape' && activeEditor && !activeEditor.saving) {
				closeActiveEditor(true);
			}
		});

		window.addEventListener('resize', function () {
			if (activeEditor && !activeEditor.saving) {
				closeActiveEditor(true);
			}
		});
		window.addEventListener('scroll', function () {
			if (activeEditor && !activeEditor.saving) {
				closeActiveEditor(true);
			}
		}, true);
	}

	document.addEventListener('DOMContentLoaded', function () {
		var form = document.getElementById('posts-filter');
		if (!form) {
			return;
		}

			var messages = getMessages();
			addCompactToggle(messages);
			var hiddenRecipient = ensureHiddenRecipientField(form);
			var hiddenAnswered = ensureHiddenAnsweredField(form);
			var hiddenAnsweredMap = ensureHiddenAnsweredMapField(form);
		var hiddenBulkEditPayload = ensureHiddenBulkEditPayloadField(form);
		var topSelect = form.querySelector('select[name="action"]');
		var bottomSelect = form.querySelector('select[name="action2"]');
		var map = new Map();
		var bypassAnsweredPrompt = false;
		var bypassBulkEditModal = false;

		if (topSelect) {
			map.set(topSelect, createRecipientField(topSelect, messages));
		}

		if (bottomSelect) {
			map.set(bottomSelect, createRecipientField(bottomSelect, messages));
		}

		map.forEach(function (fieldRef, select) {
			select.addEventListener('change', function () {
				toggleRecipientField(select, fieldRef);
			});
			toggleRecipientField(select, fieldRef);
		});

		form.addEventListener('submit', function (event) {
			var submitterName = event.submitter ? event.submitter.name : '';
			var submitter = event.submitter || null;
			var activeSelect = getSelectBySubmitter(form, submitterName);
			var action = getActionValue(activeSelect);

			if (action === 'mark_as_answered' && bypassAnsweredPrompt) {
				bypassAnsweredPrompt = false;
				return;
			}
			if (action === 'bulk_edit' && bypassBulkEditModal) {
				bypassBulkEditModal = false;
				return;
			}

			hiddenRecipient.value = '';
			hiddenAnswered.value = '';
			hiddenAnsweredMap.value = '';
			hiddenBulkEditPayload.value = '';

			if (action === 'send_via_email') {
				var fieldRef = map.get(activeSelect);
				if (!fieldRef) {
					event.preventDefault();
					window.alert(messages.invalidEmail);
					return;
				}

				var email = fieldRef.input.value.trim();
				if (!isValidEmail(email)) {
					event.preventDefault();
					fieldRef.input.setCustomValidity(messages.invalidEmail);
					fieldRef.input.reportValidity();
					fieldRef.input.focus();
					return;
				}

				fieldRef.input.setCustomValidity('');
				hiddenRecipient.value = email;
				return;
			}

			if (action === 'mark_as_answered') {
				event.preventDefault();
				var selectedItems = collectSelectedBulkAnsweredItems(form);
				if (!selectedItems.length) {
					window.alert(messages.answerModalNoSelection || 'Please select at least one submission first.');
					return;
				}

				openBulkAnswerModal(messages, selectedItems).then(function (perPostMap) {
					if (perPostMap === null) {
						return;
					}
					hiddenAnsweredMap.value = JSON.stringify(perPostMap || {});
					bypassAnsweredPrompt = true;
					if (submitter && typeof submitter.click === 'function') {
						submitter.click();
					} else if (typeof form.requestSubmit === 'function') {
						form.requestSubmit();
					} else {
						form.submit();
					}
				});
				return;
			}

			if (action === 'bulk_edit') {
				event.preventDefault();
				var selectedItemsForEdit = collectSelectedBulkEditItems(form);
				if (!selectedItemsForEdit.length) {
					window.alert(messages.bulkEditNoSelection || 'Please select at least one submission first.');
					return;
				}

				openBulkEditModal(messages, selectedItemsForEdit).then(function (perPostMap) {
					if (perPostMap === null) {
						return;
					}
					hiddenBulkEditPayload.value = JSON.stringify(perPostMap || {});
					bypassBulkEditModal = true;
					if (submitter && typeof submitter.click === 'function') {
						submitter.click();
					} else if (typeof form.requestSubmit === 'function') {
						form.requestSubmit();
					} else {
						form.submit();
					}
				});
			}
		});

			bindAnsweredPrayerLinks(messages);
			bindAiInfoTooltips();
			bindInlineBadgeEditors(messages);
			bindCompactRowExpandToggle();
			bindInlineTextEditors(messages);
		});
	})();
