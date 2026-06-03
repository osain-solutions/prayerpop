(function () {
	'use strict';

	function getConfig() {
		if (window.prayerPopAdminTour && typeof window.prayerPopAdminTour === 'object') {
			return window.prayerPopAdminTour;
		}
		return {};
	}

	function isVisible(element) {
		if (!element) {
			return false;
		}

		var rect = element.getBoundingClientRect();
		return rect.width > 0 && rect.height > 0;
	}

	function getFirstVisibleElement(selector) {
		if (!selector) {
			return null;
		}

		var candidates = document.querySelectorAll(selector);
		for (var i = 0; i < candidates.length; i += 1) {
			if (isVisible(candidates[i])) {
				return candidates[i];
			}
		}

		return null;
	}

	function getVisibleElements(selector, limit) {
		if (!selector) {
			return [];
		}

		var output = [];
		var candidates = document.querySelectorAll(selector);
		var max = typeof limit === 'number' && limit > 0 ? limit : 0;
		for (var i = 0; i < candidates.length; i += 1) {
			if (!isVisible(candidates[i])) {
				continue;
			}
			output.push(candidates[i]);
			if (max > 0 && output.length >= max) {
				break;
			}
		}
		return output;
	}

	function getCombinedRect(targets) {
		if (!Array.isArray(targets) || !targets.length) {
			return null;
		}

		var minTop = null;
		var minLeft = null;
		var maxRight = null;
		var maxBottom = null;

		targets.forEach(function (target) {
			if (!isVisible(target)) {
				return;
			}

			var rect = target.getBoundingClientRect();
			if (minTop === null || rect.top < minTop) {
				minTop = rect.top;
			}
			if (minLeft === null || rect.left < minLeft) {
				minLeft = rect.left;
			}
			if (maxRight === null || rect.right > maxRight) {
				maxRight = rect.right;
			}
			if (maxBottom === null || rect.bottom > maxBottom) {
				maxBottom = rect.bottom;
			}
		});

		if (minTop === null || minLeft === null || maxRight === null || maxBottom === null) {
			return null;
		}

		return {
			top: minTop,
			left: minLeft,
			right: maxRight,
			bottom: maxBottom,
			width: Math.max(1, maxRight - minLeft),
			height: Math.max(1, maxBottom - minTop),
		};
	}

	function isPanelVisible(panel) {
		if (!panel) {
			return false;
		}
		return window.getComputedStyle(panel).display !== 'none';
	}

	function openScreenOptionsPanel() {
		var panel = document.getElementById('screen-options-wrap');
		var button = document.getElementById('show-settings-link');
		if (!panel || !button) {
			return;
		}
		if (!isPanelVisible(panel)) {
			button.click();
		}
	}

	function findNextValidStepIndex(steps, fromIndex, direction) {
		var index = fromIndex;
		while (index >= 0 && index < steps.length) {
			var step = steps[index];
			if (!step || typeof step !== 'object') {
				index += direction;
				continue;
			}

			if (!step.selector) {
				return index;
			}

			if (getFirstVisibleElement(step.selector)) {
				return index;
			}

			index += direction;
		}

		return -1;
	}

	function createTourUi(config) {
		var overlay = document.createElement('div');
		overlay.className = 'pp-admin-tour-overlay';
		overlay.setAttribute('aria-hidden', 'true');

		var mask = document.createElement('div');
		mask.className = 'pp-admin-tour-mask';
		overlay.appendChild(mask);

		var highlight = document.createElement('div');
		highlight.className = 'pp-admin-tour-highlight';
		overlay.appendChild(highlight);

		var card = document.createElement('div');
		card.className = 'pp-admin-tour-card';
		card.setAttribute('role', 'dialog');
		card.setAttribute('aria-modal', 'true');
		overlay.appendChild(card);

		var closeButton = document.createElement('button');
		closeButton.type = 'button';
		closeButton.className = 'pp-admin-tour-close';
		closeButton.setAttribute('aria-label', config.closeLabel || 'Close');
		closeButton.textContent = '×';
		card.appendChild(closeButton);

		var counter = document.createElement('div');
		counter.className = 'pp-admin-tour-counter';
		card.appendChild(counter);

		var title = document.createElement('h2');
		title.className = 'pp-admin-tour-title';
		card.appendChild(title);

		var body = document.createElement('div');
		body.className = 'pp-admin-tour-body';
		card.appendChild(body);

		var footer = document.createElement('div');
		footer.className = 'pp-admin-tour-footer';
		card.appendChild(footer);

		var backButton = document.createElement('button');
		backButton.type = 'button';
		backButton.className = 'button button-secondary';
		backButton.textContent = config.backLabel || 'Back';
		footer.appendChild(backButton);

		var nextButton = document.createElement('button');
		nextButton.type = 'button';
		nextButton.className = 'button button-primary';
		nextButton.textContent = config.nextLabel || 'Next';
		footer.appendChild(nextButton);

		document.body.appendChild(overlay);

		var stopCardClick = function (event) {
			event.stopPropagation();
		};
		card.addEventListener('click', stopCardClick);
		card.addEventListener('mousedown', stopCardClick);
		card.addEventListener('mouseup', stopCardClick);

		return {
			overlay: overlay,
			highlight: highlight,
			card: card,
			counter: counter,
			title: title,
			body: body,
			backButton: backButton,
			nextButton: nextButton,
			closeButton: closeButton,
		};
	}

	function scrollTargetIntoView(target, callback) {
		if (!target) {
			callback();
			return;
		}

		target.scrollIntoView({
			behavior: 'smooth',
			block: 'center',
			inline: 'nearest',
		});

		window.setTimeout(callback, 180);
	}

	function initTour(config) {
		var steps = Array.isArray(config.steps) ? config.steps : [];
		if (!steps.length) {
			return;
		}

		var ui = createTourUi(config);
		var state = {
			isOpen: false,
			index: -1,
			targets: [],
			teardownDemoContent: null,
		};

		function stepRequiresPersistentUi(step) {
			if (!step || typeof step !== 'object' || !step.before) {
				return false;
			}

			return [
				'open_inline_submission_text',
				'open_inline_type_options',
				'open_inline_visibility_options',
				'open_inline_status_options',
				'open_inline_core_badges',
				'open_ai_details',
				'open_screen_options',
			].indexOf(step.before) !== -1;
		}

		function refreshCurrentStepUi() {
			if (!state.isOpen || state.index < 0 || state.index >= steps.length) {
				return;
			}

			var step = steps[state.index] || {};
			if (!stepRequiresPersistentUi(step)) {
				return;
			}

			runStepAction(step);
			window.setTimeout(function () {
				var refreshed = getStepTargetData(step);
				if (refreshed.targets.length) {
					state.targets = refreshed.targets;
				}
				positionUi();
			}, 80);
		}

		function getActionIconSvg(icon) {
			var paths = '';
			switch (icon) {
				case 'edit':
					paths = '<path d=\"M12 20h9\"/><path d=\"M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4 12.5-12.5z\"/>';
					break;
				case 'trash':
					paths = '<polyline points=\"3 6 5 6 21 6\"/><path d=\"M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6\"/><path d=\"M10 11v6\"/><path d=\"M14 11v6\"/><path d=\"M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2\"/>';
					break;
				case 'archive':
					paths = '<rect x=\"3\" y=\"4\" width=\"18\" height=\"4\" rx=\"1\"/><path d=\"M5 8v11a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8\"/><path d=\"M10 12h4\"/>';
					break;
				case 'approve':
					paths = '<path d=\"M20 6L9 17l-5-5\"/>';
					break;
				case 'decline':
					paths = '<path d=\"M18 6 6 18\"/><path d=\"m6 6 12 12\"/>';
					break;
				default:
					paths = '<circle cx=\"12\" cy=\"12\" r=\"3\"/>';
			}
			return '<svg class=\"pp-action-icon-svg\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2.5\" stroke-linecap=\"round\" stroke-linejoin=\"round\" aria-hidden=\"true\" focusable=\"false\">' + paths + '</svg>';
		}

		function createInlineBadge(options) {
			var button = document.createElement('button');
			button.type = 'button';
			button.className = 'pp-badge ' + options.badgeClass + ' pp-inline-editable pp-tour-demo-only';
			button.textContent = options.label;
			button.setAttribute('data-post-id', '0');
			button.setAttribute('data-field', options.field);
			button.setAttribute('data-value', options.value);
			button.setAttribute('data-options', JSON.stringify(options.items || []));
			button.setAttribute('aria-haspopup', 'true');
			button.setAttribute('aria-expanded', 'false');
			return button;
		}

		function createActionButton(className, icon, label) {
			var wrap = document.createElement('span');
			wrap.className = 'pp-action-tooltip-wrap';
			var link = document.createElement('a');
			link.href = '#';
			link.className = 'button button-small ' + className + ' pp-tour-demo-only';
			link.setAttribute('aria-label', label);
			link.innerHTML = getActionIconSvg(icon);
			link.addEventListener('click', function (event) {
				event.preventDefault();
			});
			var tooltip = document.createElement('span');
			tooltip.className = 'pp-action-tooltip';
			tooltip.setAttribute('role', 'tooltip');
			tooltip.textContent = label;
			wrap.appendChild(link);
			wrap.appendChild(tooltip);
			return wrap;
		}

		function createDemoRow() {
			var row = document.createElement('tr');
			row.className = 'pp-tour-demo-row';

			var cb = document.createElement('th');
			cb.className = 'check-column';
			cb.scope = 'row';
			cb.innerHTML = '<input type=\"checkbox\" disabled />';
			row.appendChild(cb);

			var nameCell = document.createElement('td');
			nameCell.className = 'column-pp_name';
			var nameEditable = document.createElement('div');
			nameEditable.className = 'pp-inline-text-editable pp-inline-text-name pp-tour-demo-only';
			nameEditable.setAttribute('data-post-id', '0');
			nameEditable.setAttribute('data-field', 'name_text');
			nameEditable.setAttribute('data-multiline', '0');
			nameEditable.setAttribute('data-value', config.demoName || 'Demo User');
			nameEditable.setAttribute('data-placeholder', 'Leave empty for Anonymous');
			nameEditable.setAttribute('aria-haspopup', 'dialog');
			nameEditable.setAttribute('aria-expanded', 'false');
			nameEditable.setAttribute('role', 'button');
			nameEditable.setAttribute('tabindex', '0');
			nameEditable.innerHTML = '<strong>' + (config.demoName || 'Demo User') + '</strong>';
			nameCell.appendChild(nameEditable);

			var bodyEditable = document.createElement('div');
			bodyEditable.className = 'pp-inline-text-editable pp-inline-text-body pp-submission-full pp-tour-demo-only';
			bodyEditable.setAttribute('data-post-id', '0');
			bodyEditable.setAttribute('data-field', 'submission_text');
			bodyEditable.setAttribute('data-multiline', '1');
			bodyEditable.setAttribute('data-value', config.demoMessage || 'Please pray for wisdom and peace in my family this week.');
			bodyEditable.setAttribute('data-placeholder', 'Add submission text...');
			bodyEditable.setAttribute('aria-haspopup', 'dialog');
			bodyEditable.setAttribute('aria-expanded', 'false');
			bodyEditable.setAttribute('role', 'button');
			bodyEditable.setAttribute('tabindex', '0');
			bodyEditable.textContent = config.demoMessage || 'Please pray for wisdom and peace in my family this week.';
			nameCell.appendChild(bodyEditable);
			row.appendChild(nameCell);

			var typeCell = document.createElement('td');
			typeCell.className = 'column-pp_type';
			typeCell.appendChild(
				createInlineBadge({
					field: 'type',
					value: 'prayer_request',
					label: config.demoTypeRequest || 'Prayer Request',
					badgeClass: 'pp-type-request',
					items: [
						{ value: 'prayer_request', label: config.demoTypeRequest || 'Prayer Request', badge_class: 'pp-type-request' },
					],
				})
			);
			row.appendChild(typeCell);

			var visibilityCell = document.createElement('td');
			visibilityCell.className = 'column-pp_visibility';
			visibilityCell.appendChild(
				createInlineBadge({
					field: 'visibility',
					value: 'public',
					label: config.demoVisibilityPublic || 'Public',
					badgeClass: 'pp-visibility-public',
					items: [
						{ value: 'public', label: config.demoVisibilityPublic || 'Public', badge_class: 'pp-visibility-public' },
					],
				})
			);
			row.appendChild(visibilityCell);

			var statusCell = document.createElement('td');
			statusCell.className = 'column-pp_status';
			var statusMain = document.createElement('span');
			statusMain.className = 'pp-status-main';
			statusMain.appendChild(
				createInlineBadge({
					field: 'status',
					value: 'pending',
					label: config.demoStatusPending || 'Pending Action',
					badgeClass: 'pp-status-pending',
					items: [
						{ value: 'pending', label: config.demoStatusPending || 'Pending Action', badge_class: 'pp-status-pending' },
						{ value: 'approved', label: config.demoStatusApproved || 'Approved', badge_class: 'pp-status-approved' },
						{ value: 'answered', label: config.demoStatusAnswered || 'Answered', badge_class: 'pp-status-answered' },
						{ value: 'declined', label: config.demoStatusDeclined || 'Declined', badge_class: 'pp-status-declined' },
						{ value: 'archived', label: config.demoStatusArchived || 'Archived', badge_class: 'pp-status-archived' },
					],
				})
			);
			statusCell.appendChild(statusMain);
			row.appendChild(statusCell);

			var actionsCell = document.createElement('td');
			actionsCell.className = 'column-pp_actions';
			var actions = document.createElement('div');
			actions.className = 'pp-actions-column';
			actions.appendChild(createActionButton('pp-trash-btn', 'trash', config.demoActionTrash || 'Move to Trash'));
			actions.appendChild(createActionButton('pp-archive-btn', 'archive', config.demoActionArchive || 'Archive'));
			actions.appendChild(createActionButton('pp-approve-btn', 'approve', config.demoActionApprove || 'Approve'));
			actions.appendChild(createActionButton('pp-decline-btn', 'decline', config.demoActionDecline || 'Decline'));
			actionsCell.appendChild(actions);
			row.appendChild(actionsCell);

			var dateCell = document.createElement('td');
			dateCell.className = 'column-date';
			dateCell.innerHTML = '<span>' + (config.demoDateText || 'Last Modified') + '<br>' + (config.demoDateValue || '2026/03/13 at 10:30 am') + '</span>';
			row.appendChild(dateCell);

			return row;
		}

		function ensureDemoEnvironment() {
			var hiddenNotices = [];
			var hiddenRows = [];
			var created = [];

			document.querySelectorAll('.wrap .notice, .wrap .update-nag').forEach(function (notice) {
				if (notice.classList.contains('pp-admin-tour-temp-notice')) {
					return;
				}
				notice.classList.add('pp-admin-tour-hidden-notice');
				hiddenNotices.push(notice);
			});

			var wrap = document.querySelector('.wrap');
			var heading = wrap ? wrap.querySelector('h1.wp-heading-inline') : null;
			if (wrap && heading) {
				var insertBefore = wrap.querySelector('.subsubsub') || wrap.querySelector('.tablenav.top') || null;
				var actionNotice = document.createElement('div');
				actionNotice.className = 'notice notice-success pp-admin-tour-temp-notice';
				actionNotice.innerHTML = '<p>' + (config.demoActionNotice || 'Submission updated successfully.') + '</p>';

				if (insertBefore && insertBefore.parentNode === wrap) {
					wrap.insertBefore(actionNotice, insertBefore);
				} else {
					heading.insertAdjacentElement('afterend', actionNotice);
				}
				created.push(actionNotice);
			}

			var tbody = document.getElementById('the-list');
			if (tbody) {
				tbody.querySelectorAll('tr').forEach(function (row) {
					if (row.classList.contains('pp-tour-demo-row')) {
						return;
					}
					row.classList.add('pp-admin-tour-hidden-row');
					hiddenRows.push(row);
				});
				var demoRow = createDemoRow();
				tbody.insertAdjacentElement('afterbegin', demoRow);
				created.push(demoRow);
			}

			return function () {
				created.forEach(function (node) {
					if (node && node.parentNode) {
						node.parentNode.removeChild(node);
					}
				});
				hiddenNotices.forEach(function (notice) {
					notice.classList.remove('pp-admin-tour-hidden-notice');
				});
				hiddenRows.forEach(function (row) {
					row.classList.remove('pp-admin-tour-hidden-row');
				});
				closeTransientUi();
			};
		}

		function getPrevIndex() {
			return findNextValidStepIndex(steps, state.index - 1, -1);
		}

		function getNextIndex() {
			return findNextValidStepIndex(steps, state.index + 1, 1);
		}

		function isPotentiallyAvailableStep(step) {
			if (!step || typeof step !== 'object') {
				return false;
			}

			if (!step.selector) {
				return true;
			}

			if (getFirstVisibleElement(step.selector)) {
				return true;
			}

			if (step.before === 'open_screen_options' && document.getElementById('show-settings-link')) {
				return true;
			}

			return false;
		}

		function getStepCounter(index) {
			var availableIndices = [];
			for (var i = 0; i < steps.length; i += 1) {
				if (isPotentiallyAvailableStep(steps[i])) {
					availableIndices.push(i);
				}
			}

			if (!availableIndices.length) {
				return {
					current: index + 1,
					total: steps.length,
				};
			}

			var position = availableIndices.indexOf(index);
			if (position === -1) {
				position = Math.max(0, availableIndices.length - 1);
			}

			return {
				current: position + 1,
				total: availableIndices.length,
			};
		}

		function positionUi() {
			if (!state.isOpen) {
				return;
			}

			var viewportWidth = window.innerWidth;
			var viewportHeight = window.innerHeight;
			var margin = 12;

			var rect = getCombinedRect(state.targets);
			if (rect) {
				ui.overlay.querySelector('.pp-admin-tour-mask').style.background = 'transparent';
				var pad = 6;
				ui.highlight.style.display = 'block';
				ui.highlight.style.top = Math.max(margin, rect.top - pad) + 'px';
				ui.highlight.style.left = Math.max(margin, rect.left - pad) + 'px';
				ui.highlight.style.width = Math.max(24, rect.width + pad * 2) + 'px';
				ui.highlight.style.height = Math.max(24, rect.height + pad * 2) + 'px';

				var cardRect = ui.card.getBoundingClientRect();
				var cardTop = rect.bottom + 14;
				if (cardTop + cardRect.height > viewportHeight - margin) {
					cardTop = rect.top - cardRect.height - 14;
				}
				if (cardTop < margin) {
					cardTop = margin;
				}

				var cardLeft = rect.left;
				if (cardLeft + cardRect.width > viewportWidth - margin) {
					cardLeft = viewportWidth - cardRect.width - margin;
				}
				if (cardLeft < margin) {
					cardLeft = margin;
				}

				ui.card.style.top = cardTop + 'px';
				ui.card.style.left = cardLeft + 'px';
				ui.card.style.transform = 'none';
			} else {
				ui.overlay.querySelector('.pp-admin-tour-mask').style.background = 'rgba(15, 23, 42, 0.48)';
				ui.highlight.style.display = 'none';
				ui.card.style.top = '50%';
				ui.card.style.left = '50%';
				ui.card.style.transform = 'translate(-50%, -50%)';
			}
		}

		function closeTour() {
			state.isOpen = false;
			state.targets = [];
			state.index = -1;
			if (typeof state.teardownDemoContent === 'function') {
				state.teardownDemoContent();
			}
			state.teardownDemoContent = null;
			ui.overlay.classList.remove('is-open');
			ui.overlay.setAttribute('aria-hidden', 'true');
			document.body.classList.remove('pp-admin-tour-open');
		}

		function closeTransientUi() {
			document.querySelectorAll('.pp-ai-info-wrap.is-open').forEach(function (wrap) {
				wrap.classList.remove('is-open');
			});
			document.querySelectorAll('.pp-ai-info-button[aria-expanded="true"]').forEach(function (button) {
				button.setAttribute('aria-expanded', 'false');
			});
			document.querySelectorAll('.pp-tour-badge-menu-preview').forEach(function (menu) {
				if (menu && menu.parentNode) {
					menu.parentNode.removeChild(menu);
				}
			});
			document.body.click();
		}

		function triggerFirst(selector) {
			var target = getFirstVisibleElement(selector);
			if (!target) {
				return null;
			}
			target.click();
			return target;
		}

		function createBadgeMenuPreview(button) {
			if (!button) {
				return null;
			}
			var rawOptions = button.getAttribute('data-options');
			if (!rawOptions) {
				return null;
			}
			var options = [];
			try {
				options = JSON.parse(rawOptions);
			} catch (e) {
				options = [];
			}
			if (!Array.isArray(options) || !options.length) {
				return null;
			}

			var menu = document.createElement('div');
			menu.className = 'pp-inline-badge-menu pp-tour-badge-menu-preview';
			options.forEach(function (option) {
				if (!option || typeof option !== 'object') {
					return;
				}
				var item = document.createElement('span');
				item.className = 'pp-badge pp-inline-option ' + (option.badge_class || '');
				item.textContent = option.label || option.value || '';
				menu.appendChild(item);
			});

			document.body.appendChild(menu);
			var rect = button.getBoundingClientRect();
			var menuWidth = Math.max(190, Math.round(rect.width));
			menu.style.width = menuWidth + 'px';
			menu.style.minWidth = menuWidth + 'px';
			menu.style.position = 'absolute';
			var left = window.scrollX + rect.left;
			var maxLeft = window.scrollX + window.innerWidth - menu.offsetWidth - 12;
			if (left > maxLeft) {
				left = Math.max(window.scrollX + 12, maxLeft);
			}
			menu.style.left = left + 'px';
			menu.style.top = (window.scrollY + rect.bottom + 8) + 'px';
			return menu;
		}

		function openCoreBadgePreviews() {
			document.querySelectorAll('.pp-tour-badge-menu-preview').forEach(function (menu) {
				if (menu && menu.parentNode) {
					menu.parentNode.removeChild(menu);
				}
			});
			var selectors = [
				'.pp-tour-demo-row td.column-pp_type .pp-inline-editable',
				'.pp-tour-demo-row td.column-pp_visibility .pp-inline-editable',
				'.pp-tour-demo-row td.column-pp_status .pp-inline-editable',
			];
			selectors.forEach(function (selector) {
				var button = getFirstVisibleElement(selector);
				createBadgeMenuPreview(button);
			});
		}

		function forceOpenAiDetails() {
			var button = getFirstVisibleElement('.pp-tour-demo-row td.column-pp_status .pp-ai-info-button') || getFirstVisibleElement('td.column-pp_status .pp-ai-info-button');
			if (!button) {
				return;
			}

			var wrap = button.closest('.pp-ai-info-wrap');
			if (!wrap) {
				return;
			}

			if (typeof document.querySelectorAll === 'function') {
				document.querySelectorAll('.pp-ai-info-wrap.is-open').forEach(function (openWrap) {
					if (openWrap === wrap) {
						return;
					}
					openWrap.classList.remove('is-open');
					var openButton = openWrap.querySelector('.pp-ai-info-button');
					if (openButton) {
						openButton.setAttribute('aria-expanded', 'false');
					}
				});
			}

			wrap.classList.add('is-open');
			button.setAttribute('aria-expanded', 'true');
		}

		function runStepAction(step) {
			if (!step || typeof step !== 'object' || !step.before) {
				return;
			}

			switch (step.before) {
				case 'open_screen_options':
					openScreenOptionsPanel();
					break;
				case 'open_inline_submission_text':
					triggerFirst('.pp-tour-demo-row td.column-pp_name .pp-inline-text-body') || triggerFirst('td.column-pp_name .pp-inline-text-body');
					break;
				case 'open_inline_type_options':
					triggerFirst('.pp-tour-demo-row td.column-pp_type .pp-inline-editable') || triggerFirst('td.column-pp_type .pp-inline-editable');
					break;
				case 'open_inline_visibility_options':
					triggerFirst('.pp-tour-demo-row td.column-pp_visibility .pp-inline-editable') || triggerFirst('td.column-pp_visibility .pp-inline-editable');
					break;
				case 'open_inline_status_options':
					triggerFirst('.pp-tour-demo-row td.column-pp_status .pp-inline-editable') || triggerFirst('td.column-pp_status .pp-inline-editable');
					break;
				case 'open_inline_core_badges':
					openCoreBadgePreviews();
					break;
				case 'open_ai_details':
					forceOpenAiDetails();
					break;
				default:
					break;
			}
		}

		function getStepTargetData(step) {
			var primary = null;
			var targets = [];

			if (step && step.selector) {
				if (step.selectorAll) {
					var selectorLimit = typeof step.selectorLimit === 'number' ? step.selectorLimit : 0;
					var mainTargets = getVisibleElements(step.selector, selectorLimit);
					if (mainTargets.length) {
						primary = mainTargets[0];
						targets = targets.concat(mainTargets);
					}
				} else {
					primary = getFirstVisibleElement(step.selector);
					if (primary) {
						targets.push(primary);
					}
				}
			}

			if (step && step.selectorSecondary) {
				if (step.selectorSecondaryAll) {
					var secondaryLimit = typeof step.selectorSecondaryLimit === 'number' ? step.selectorSecondaryLimit : 0;
					var secondaryTargets = getVisibleElements(step.selectorSecondary, secondaryLimit);
					if (secondaryTargets.length) {
						targets = targets.concat(secondaryTargets);
					}
				} else {
					var secondary = getFirstVisibleElement(step.selectorSecondary);
					if (secondary) {
						targets.push(secondary);
					}
				}
			}

			return {
				primary: primary,
				targets: targets,
			};
		}

		function applyStepState(index, step, targets) {
			state.index = index;
			state.targets = Array.isArray(targets) ? targets : [];
			state.isOpen = true;

			var count = getStepCounter(index);
			ui.counter.textContent = (config.titlePrefix || 'Step') + ' ' + count.current + ' ' + (config.ofLabel || 'of') + ' ' + count.total;
			ui.title.textContent = step.title || '';
			ui.body.innerHTML = step.body || '';

			var prevIndex = getPrevIndex();
			var nextIndex = getNextIndex();
			ui.backButton.disabled = prevIndex === -1;
			ui.nextButton.textContent = nextIndex === -1 ? (config.doneLabel || 'Done') : (config.nextLabel || 'Next');

			ui.overlay.classList.add('is-open');
			ui.overlay.setAttribute('aria-hidden', 'false');
			document.body.classList.add('pp-admin-tour-open');

			var anchorTarget = state.targets.length ? state.targets[0] : null;
			scrollTargetIntoView(anchorTarget, function () {
				runStepAction(step);
				window.setTimeout(function () {
					var updatedTargetData = getStepTargetData(step);
					if (updatedTargetData.targets.length) {
						state.targets = updatedTargetData.targets;
					}
					positionUi();
				}, 90);
			});
		}

		function renderStep(index) {
			if (index < 0 || index >= steps.length) {
				closeTour();
				return;
			}

			var step = steps[index] || {};
			closeTransientUi();
			var opensScreenOptions = step.before === 'open_screen_options';
			if (opensScreenOptions) {
				openScreenOptionsPanel();
			}
			var targetData = getStepTargetData(step);
			if (opensScreenOptions && step.selector && !targetData.primary) {
				window.setTimeout(function () {
					var delayedData = getStepTargetData(step);
					if (!delayedData.primary) {
						var delayedFallback = findNextValidStepIndex(steps, index + 1, 1);
						if (delayedFallback === -1) {
							closeTour();
							return;
						}
						renderStep(delayedFallback);
						return;
					}
					applyStepState(index, step, delayedData.targets);
				}, 220);
				return;
			}

			if (step.selector && !targetData.primary) {
				var fallbackIndex = findNextValidStepIndex(steps, index + 1, 1);
				if (fallbackIndex === -1) {
					closeTour();
					return;
				}
				renderStep(fallbackIndex);
				return;
			}

			applyStepState(index, step, targetData.targets);
		}

		function openTour() {
			if (typeof state.teardownDemoContent === 'function') {
				state.teardownDemoContent();
			}
			state.teardownDemoContent = ensureDemoEnvironment();
			var firstIndex = findNextValidStepIndex(steps, 0, 1);
			if (firstIndex === -1) {
				if (typeof state.teardownDemoContent === 'function') {
					state.teardownDemoContent();
				}
				state.teardownDemoContent = null;
				return;
			}
			renderStep(firstIndex);
		}

		ui.closeButton.addEventListener('click', closeTour);
		ui.card.addEventListener('mousedown', function () {
			window.setTimeout(refreshCurrentStepUi, 0);
		});
		ui.card.addEventListener('click', function () {
			window.setTimeout(refreshCurrentStepUi, 0);
		});
		ui.overlay.querySelector('.pp-admin-tour-mask').addEventListener('click', closeTour);
		ui.overlay.addEventListener('click', function (event) {
			if (event.target === ui.overlay) {
				closeTour();
			}
		});

		ui.backButton.addEventListener('click', function () {
			if (!state.isOpen) {
				return;
			}
			var prevIndex = getPrevIndex();
			if (prevIndex !== -1) {
				renderStep(prevIndex);
			}
		});

		ui.nextButton.addEventListener('click', function () {
			if (!state.isOpen) {
				return;
			}
			var nextIndex = getNextIndex();
			if (nextIndex === -1) {
				closeTour();
				return;
			}
			renderStep(nextIndex);
		});

		window.addEventListener('resize', positionUi);
		window.addEventListener('scroll', positionUi, true);
		document.addEventListener('keydown', function (event) {
			if (!state.isOpen) {
				return;
			}
			if (event.key === 'Escape') {
				event.preventDefault();
				closeTour();
			}
		});

		return {
			open: openTour,
		};
	}

	function getNudgeStorageKey() {
		return 'prayerPopAdminTourNudgeSeen_v1';
	}

	function markNudgeSeen() {
		try {
			window.localStorage.setItem(getNudgeStorageKey(), '1');
		} catch (error) {
			// Ignore storage failures.
		}
	}

	function shouldShowNudge() {
		try {
			return window.localStorage.getItem(getNudgeStorageKey()) !== '1';
		} catch (error) {
			return false;
		}
	}

	function findButtonMountPoint() {
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

	function addLaunchButton(config, openTour) {
		if (!openTour) {
			return null;
		}
		if (document.getElementById('pp-admin-tour-launch')) {
			return document.getElementById('pp-admin-tour-launch');
		}

		var mount = findButtonMountPoint();
		if (!mount || !mount.container) {
			return null;
		}

		var button = document.createElement('button');
		button.type = 'button';
		button.id = 'pp-admin-tour-launch';
		button.className = 'button pp-admin-tour-launch';
		button.innerHTML = '<span class="pp-admin-tour-launch-icon" aria-hidden="true">?</span><span class="pp-admin-tour-launch-label">' + (config.buttonLabel || 'How to Use') + '</span>';
		button.addEventListener('click', function () {
			markNudgeSeen();
			openTour();
		});

		if (mount.beforeNode) {
			mount.container.insertBefore(button, mount.beforeNode);
		} else {
			mount.container.appendChild(button);
		}

		return button;
	}

	function showFirstTimeNudge(button, config) {
		if (!button || !shouldShowNudge()) {
			return;
		}

		var nudge = document.createElement('div');
		nudge.className = 'pp-admin-tour-nudge';
		nudge.setAttribute('role', 'status');
		nudge.innerHTML =
			'<div class="pp-admin-tour-nudge-text">' + (config.nudgeText || 'New here? Click How to Use for a quick walkthrough.') + '</div>' +
			'<button type="button" class="pp-admin-tour-nudge-close" aria-label="' + (config.closeLabel || 'Close') + '">×</button>';
		document.body.appendChild(nudge);

		var closeButton = nudge.querySelector('.pp-admin-tour-nudge-close');
		function closeNudge() {
			markNudgeSeen();
			nudge.remove();
		}

		function placeNudge() {
			if (!document.body.contains(nudge) || !document.body.contains(button)) {
				return;
			}
			var rect = button.getBoundingClientRect();
			var nudgeRect = nudge.getBoundingClientRect();
			var top = rect.bottom + 10;
			if (top + nudgeRect.height > window.innerHeight - 8) {
				top = rect.top - nudgeRect.height - 10;
			}
			if (top < 8) {
				top = 8;
			}

			var left = rect.left + rect.width - nudgeRect.width;
			if (left < 8) {
				left = 8;
			}
			if (left + nudgeRect.width > window.innerWidth - 8) {
				left = window.innerWidth - nudgeRect.width - 8;
			}

			nudge.style.top = Math.round(top) + 'px';
			nudge.style.left = Math.round(left) + 'px';
		}

		button.classList.add('is-pulsing');
		closeButton.addEventListener('click', function (event) {
			event.preventDefault();
			button.classList.remove('is-pulsing');
			closeNudge();
		});

		nudge.addEventListener('click', function (event) {
			if (event.target.closest('.pp-admin-tour-nudge-close')) {
				return;
			}
			button.classList.remove('is-pulsing');
			closeNudge();
			button.click();
		});

		window.addEventListener('resize', placeNudge);
		window.addEventListener('scroll', placeNudge, true);
		placeNudge();
	}

	document.addEventListener('DOMContentLoaded', function () {
		var config = getConfig();
		if (!config || !config.enabled) {
			return;
		}

		var tour = initTour(config);
		if (!tour || typeof tour.open !== 'function') {
			return;
		}

		var launchButton = addLaunchButton(config, tour.open);
		showFirstTimeNudge(launchButton, config);
	});
})();
