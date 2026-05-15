/**
 * Cheesecake Factory Calorie Calculator — minimal vanilla JS.
 */
(function () {
	'use strict';

	function ready(fn) {
		if (document.readyState !== 'loading') {
			fn();
		} else {
			document.addEventListener('DOMContentLoaded', fn);
		}
	}

	function getDataForRoot(root) {
		var jsonEl = root.querySelector('.cfc-config-json');
		if (jsonEl && jsonEl.textContent) {
			try {
				return JSON.parse(jsonEl.textContent);
			} catch (e) {
				return null;
			}
		}
		if (typeof CFC_DATA !== 'undefined' && CFC_DATA && CFC_DATA.byCategory) {
			return CFC_DATA;
		}
		return null;
	}

	function nextLineId() {
		if (!window.__cfcLineId) {
			window.__cfcLineId = 0;
		}
		window.__cfcLineId += 1;
		return 'cfc-line-' + window.__cfcLineId;
	}

	function findLine(lines, externalId) {
		for (var i = 0; i < lines.length; i++) {
			if (lines[i].externalId === externalId) {
				return lines[i];
			}
		}
		return null;
	}

	function removeNodes(selector, parent) {
		var list = parent.querySelectorAll(selector);
		for (var i = 0; i < list.length; i++) {
			list[i].parentNode.removeChild(list[i]);
		}
	}

	ready(function () {
		var roots = document.querySelectorAll('[data-cfc-root]');
		for (var r = 0; r < roots.length; r++) {
			var root = roots[r];
			var data = getDataForRoot(root);
			if (!data || !data.byCategory) {
				continue;
			}
			initCalculator(root, data);
		}
	});

	function initCalculator(root, data) {
		var categoryEl = root.querySelector('[data-cfc-category]');
		var productEl = root.querySelector('[data-cfc-product]');
		var qtyEl = root.querySelector('[data-cfc-qty]');
		var addBtn = root.querySelector('[data-cfc-add]');
		var tbody = root.querySelector('[data-cfc-tbody]');
		var emptyRow = root.querySelector('[data-cfc-empty-row]');
		var totalEl = root.querySelector('[data-cfc-total]');
		var resetBtn = root.querySelector('[data-cfc-reset]');

		if (!categoryEl || !productEl || !qtyEl || !addBtn || !tbody || !totalEl) {
			return;
		}

		var lines = [];

		function getSelectedProduct() {
			var opt = productEl.options[productEl.selectedIndex];
			if (!opt || !opt.value) {
				return null;
			}
			try {
				return JSON.parse(opt.getAttribute('data-cfc-item') || '{}');
			} catch (e) {
				return null;
			}
		}

		function fillProducts(cat) {
			while (productEl.options.length > 1) {
				productEl.remove(1);
			}
			if (!cat || !data.byCategory[cat]) {
				productEl.disabled = true;
				return;
			}
			var items = data.byCategory[cat];
			for (var i = 0; i < items.length; i++) {
				var item = items[i];
				var o = document.createElement('option');
				o.value = item.external_id;
				o.textContent = item.product_name;
				o.setAttribute('data-cfc-item', JSON.stringify(item));
				productEl.appendChild(o);
			}
			productEl.disabled = false;
		}

		categoryEl.addEventListener('change', function () {
			productEl.selectedIndex = 0;
			fillProducts(categoryEl.value);
		});

		function renderTable() {
			removeNodes('[data-cfc-line]', tbody);
			if (!lines.length) {
				if (emptyRow) {
					emptyRow.style.display = '';
				}
				totalEl.textContent = '0';
				return;
			}
			if (emptyRow) {
				emptyRow.style.display = 'none';
			}
			var sum = 0;
			for (var i = 0; i < lines.length; i++) {
				var line = lines[i];
				var tr = document.createElement('tr');
				tr.setAttribute('data-cfc-line', line.id);
				var lineCal = line.calories * line.qty;
				sum += lineCal;
				tr.innerHTML =
					'<td>' +
					escapeHtml(line.name) +
					'</td><td>' +
					line.calories +
					'</td><td>' +
					line.qty +
					'</td><td>' +
					lineCal +
					'</td><td><button type="button" class="cfc-btn cfc-btn--danger" data-cfc-remove="' +
					line.id +
					'">' +
					escapeHtml(data.i18n.remove) +
					'</button></td>';
				tbody.appendChild(tr);
			}
			totalEl.textContent = String(sum);
			var btns = tbody.querySelectorAll('[data-cfc-remove]');
			for (var b = 0; b < btns.length; b++) {
				btns[b].addEventListener('click', onRemoveClick);
			}
		}

		function onRemoveClick(ev) {
			var btn = ev.currentTarget;
			var id = btn.getAttribute('data-cfc-remove');
			var next = [];
			for (var i = 0; i < lines.length; i++) {
				if (lines[i].id !== id) {
					next.push(lines[i]);
				}
			}
			lines = next;
			renderTable();
		}

		function escapeHtml(s) {
			var div = document.createElement('div');
			div.textContent = s;
			return div.innerHTML;
		}

		addBtn.addEventListener('click', function () {
			var p = getSelectedProduct();
			if (!p || !p.external_id) {
				return;
			}
			var qty = parseInt(qtyEl.value, 10);
			if (isNaN(qty) || qty < 1) {
				qty = 1;
			}
			if (qty > 999) {
				qty = 999;
			}
			var cal = parseInt(p.calories, 10) || 0;
			var existing = findLine(lines, p.external_id);
			if (existing) {
				existing.qty += qty;
				if (existing.qty > 999) {
					existing.qty = 999;
				}
			} else {
				lines.push({
					id: nextLineId(),
					externalId: p.external_id,
					name: p.product_name,
					calories: cal,
					qty: qty,
				});
			}
			renderTable();
		});

		if (resetBtn) {
			resetBtn.addEventListener('click', function () {
				lines = [];
				categoryEl.selectedIndex = 0;
				productEl.selectedIndex = 0;
				productEl.disabled = true;
				while (productEl.options.length > 1) {
					productEl.remove(1);
				}
				qtyEl.value = '1';
				renderTable();
			});
		}

		renderTable();
	}
})();
