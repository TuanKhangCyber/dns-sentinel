const scannerData = document.getElementById('scannerPageData');

if (scannerData) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const terminal = new Set(['completed', 'failed', 'cancelled']);
    const vi = document.documentElement.lang === 'vi';
    const messages = {
        invalidResponse: vi ? 'Máy chủ trả về dữ liệu không hợp lệ. Vui lòng thử lại.' : 'The server returned an invalid response. Please try again.',
        timeout: vi ? 'Yêu cầu quá thời gian. Vui lòng thử lại.' : 'The request timed out. Please try again.',
        failed: vi ? 'Không thể hoàn tất yêu cầu. Vui lòng thử lại.' : 'The request could not be completed. Please try again.',
        empty: vi ? 'Không có dữ liệu phù hợp.' : 'No matching data.',
    };
    const controllers = new Set();
    const timers = new Set();
    const schedule = (callback, delay) => {
        const timer = setTimeout(() => { timers.delete(timer); callback(); }, delay);
        timers.add(timer);
        return timer;
    };
    const clearTimer = timer => { if (timer) { clearTimeout(timer); timers.delete(timer); } };
    const apiMessage = (response, payload, validationMessage = null) => {
        if (validationMessage) return validationMessage;
        const reasons = {
            upgrade_required: vi ? 'Tính năng này yêu cầu gói Plus.' : 'This feature requires Plus.',
            feature_disabled: vi ? 'Tính năng đang tạm tắt.' : 'This feature is temporarily disabled.',
            insufficient_credits: vi ? 'Bạn không có đủ credits.' : 'You do not have enough credits.',
            service_unavailable: vi ? 'Dịch vụ chưa được cấu hình.' : 'The service is not configured.',
            account_suspended: vi ? 'Tài khoản đang bị tạm khóa.' : 'This account is suspended.',
        };
        return reasons[payload.error_code] || (response.status === 409 ? (payload.message || messages.failed) : messages.failed);
    };
    const request = async (url, options = {}) => {
        const controller = new AbortController();
        controllers.add(controller);
        const timeoutId = setTimeout(() => controller.abort(), options.timeout ?? 15000);
        try {
            const response = await fetch(url, {
                ...options,
                signal: controller.signal,
                headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, ...(options.headers || {})},
            });
            const raw = await response.text();
            let payload;
            try {
                payload = raw === '' ? {} : JSON.parse(raw);
            } catch {
                throw new Error(messages.invalidResponse);
            }
            if (!response.ok || payload.success === false) {
                const validationMessage = Object.values(payload.errors || {}).flat()[0] || null;
                throw new Error(apiMessage(response, payload, validationMessage));
            }
            return payload;
        } catch (error) {
            if (error.name === 'AbortError') throw new Error(messages.timeout);
            throw error;
        } finally {
            clearTimeout(timeoutId);
            controllers.delete(controller);
        }
    };
    const node = (tag, text = '', className = '') => {
        const item = document.createElement(tag);
        if (text !== '') item.textContent = String(text);
        if (className) item.className = className;
        return item;
    };
    const statusClass = status => status === 'completed' ? 'safe' : (status === 'failed' ? 'danger' : (status === 'cancelled' ? 'error' : 'warning'));
    const table = (headers, rows) => {
        const tableNode = node('table', '', 'subdomain-table');
        const head = node('thead'); const headRow = node('tr');
        const body = node('tbody');
        headers.forEach((value, column) => {
            const cell = node('th', value, 'sortable-column');
            cell.tabIndex = 0;
            const sort = () => {
                const rowsToSort = [...body.rows];
                const direction = cell.dataset.direction === 'asc' ? 'desc' : 'asc';
                rowsToSort.sort((left, right) => left.cells[column].textContent.trim().localeCompare(right.cells[column].textContent.trim(), undefined, {numeric: true}) * (direction === 'asc' ? 1 : -1));
                rowsToSort.forEach(row => body.appendChild(row));
                cell.dataset.direction = direction;
            };
            cell.addEventListener('click', sort);
            cell.addEventListener('keydown', event => { if (event.key === 'Enter' || event.key === ' ') sort(); });
            headRow.appendChild(cell);
        });
        head.appendChild(headRow);
        rows.forEach(values => { const row = node('tr'); values.forEach(value => { const cell = node('td'); if (value instanceof Node) cell.appendChild(value); else cell.textContent = value ?? '—'; row.appendChild(cell); }); body.appendChild(row); });
        if (!rows.length) {
            const row = node('tr');
            const cell = node('td', messages.empty, 'empty-state');
            cell.colSpan = headers.length;
            row.appendChild(cell);
            body.appendChild(row);
        }
        tableNode.append(head, body);
        return tableNode;
    };

    if (scannerData.dataset.page === 'create') {
        const form = document.getElementById('scannerCreateForm');
        let statusTimer = null; let startedAt = null; let activeScan = null; let submitting = false;
        const updateStatus = payload => {
            const badge = document.getElementById('currentScanStatus');
            badge.textContent = payload.status; badge.className = `risk-badge ${statusClass(payload.status)}`;
            document.getElementById('currentScanStage').textContent = payload.stage || '—';
            document.getElementById('currentScanProgress').style.width = `${payload.progress || 0}%`;
            document.getElementById('currentScanProgressText').textContent = `${payload.progress || 0}%`;
            if (terminal.has(payload.status)) {
                clearTimer(statusTimer); document.getElementById('scannerSkeleton').hidden = true;
                document.getElementById('cancelCurrentScan').disabled = true;
            }
        };
        const poll = scanId => {
            clearTimer(statusTimer);
            const tick = async () => {
                try {
                    const payload = await request(`${scannerData.dataset.showBase}/${scanId}/status`);
                    updateStatus(payload);
                    document.getElementById('currentScanElapsed').textContent = `${Math.floor((Date.now() - startedAt) / 1000)}s`;
                    if (!terminal.has(payload.status)) statusTimer = schedule(tick, 2000);
                } catch (exception) {
                    const error = document.getElementById('scannerFormError');
                    error.textContent = exception.message; error.hidden = false;
                }
            };
            tick();
        };
        form?.addEventListener('submit', async event => {
            event.preventDefault();
            if (submitting) return;
            submitting = true;
            clearTimer(statusTimer);
            activeScan = null;
            document.getElementById('currentScan').hidden = true;
            const viewLink = document.getElementById('viewCurrentScan');
            viewLink?.removeAttribute('href');
            const error = document.getElementById('scannerFormError'); error.hidden = true; error.textContent = '';
            const submit = form.querySelector('button[type="submit"]'); submit.disabled = true; submit.setAttribute('aria-busy', 'true');
            const data = Object.fromEntries(new FormData(form).entries());
            data.authorization_confirmed = form.authorization_confirmed.checked;
            if (!data.scheduled_at) delete data.scheduled_at;
            if (!data.port_range) delete data.port_range;
            try {
                const payload = await request(form.dataset.storeUrl, {method: 'POST', body: JSON.stringify(data)});
                activeScan = payload.scan_id; startedAt = Date.now(); document.getElementById('currentScan').hidden = false;
                document.getElementById('currentScanTarget').textContent = data.target;
                if (viewLink) viewLink.href = `${scannerData.dataset.showBase}/${payload.scan_id}`;
                updateStatus(payload); poll(payload.scan_id);
            } catch (exception) { error.textContent = exception.message; error.hidden = false; }
            finally { submitting = false; submit.disabled = false; submit.removeAttribute('aria-busy'); }
        });
        document.getElementById('cancelCurrentScan')?.addEventListener('click', async () => {
            if (!activeScan) return;
            try {
                updateStatus(await request(`${scannerData.dataset.showBase}/${activeScan}/cancel`, {method: 'POST', body: '{}'}));
            } catch (exception) {
                const error = document.getElementById('scannerFormError'); error.textContent = exception.message; error.hidden = false;
            }
        });
    }

    if (scannerData.dataset.page === 'show') {
        let hosts = []; let findings = []; let statusTimer;
        const scannerTabs = [...document.querySelectorAll('[data-scanner-tab]')];
        const scannerPanels = [...document.querySelectorAll('[data-scanner-panel]')];
        const activateScannerTab = (button, focus = false) => {
            scannerTabs.forEach(item => {
                const active = item === button;
                item.classList.toggle('active', active);
                item.setAttribute('aria-selected', String(active));
                item.tabIndex = active ? 0 : -1;
            });
            scannerPanels.forEach(panel => {
                const active = panel.dataset.scannerPanel === button.dataset.scannerTab;
                panel.classList.toggle('active', active);
                panel.hidden = !active;
            });
            if (focus) button.focus();
        };
        scannerTabs.forEach(button => {
            button.setAttribute('role', 'tab');
            button.addEventListener('click', () => activateScannerTab(button));
            button.addEventListener('keydown', event => {
                if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
                event.preventDefault();
                const current = scannerTabs.indexOf(button);
                const next = event.key === 'Home' ? 0 : event.key === 'End' ? scannerTabs.length - 1 : (current + (event.key === 'ArrowRight' ? 1 : -1) + scannerTabs.length) % scannerTabs.length;
                activateScannerTab(scannerTabs[next], true);
            });
            if (!button.classList.contains('active')) button.tabIndex = -1;
        });
        scannerPanels.forEach(panel => panel.setAttribute('role', 'tabpanel'));
        scannerTabs[0]?.closest('nav')?.setAttribute('role', 'tablist');
        activateScannerTab(scannerTabs.find(tab => tab.classList.contains('active')) || scannerTabs[0]);
        const renderHosts = () => {
            const query = document.getElementById('hostSearch').value.toLowerCase();
            const rows = hosts.filter(host => `${host.ip_address} ${host.hostname || ''}`.toLowerCase().includes(query)).map(host => [host.ip_address, host.hostname, host.status, host.vendor, host.response_time ? `${host.response_time} ms` : '—']);
            document.getElementById('scanHosts').replaceChildren(table(['IP', 'Hostname', 'Status', 'Vendor', 'Response'], rows));
        };
        const renderPorts = () => {
            const state = document.getElementById('portStateFilter').value; const protocol = document.getElementById('portProtocolFilter').value; const query = document.getElementById('portSearch').value.toLowerCase();
            const rows = hosts.flatMap(host => (host.ports || []).map(port => ({...port, host: host.ip_address}))).filter(port => (!state || port.state === state) && (!protocol || port.protocol === protocol) && `${port.service || ''} ${port.product || ''} ${port.host}`.toLowerCase().includes(query)).map(port => [port.host, port.port, port.protocol, port.state, port.service, [port.product, port.version].filter(Boolean).join(' ')]);
            document.getElementById('scanPorts').replaceChildren(table(['Host', 'Port', 'Protocol', 'State', 'Service', 'Product'], rows));
        };
        const renderFindings = () => {
            const severity = document.getElementById('findingSeverityFilter').value; const status = document.getElementById('findingStatusFilter').value; const query = document.getElementById('findingSearch').value.toLowerCase();
            const badgeClass = value => ({critical:'danger', high:'danger', medium:'warning', low:'warning', informational:'info', passed:'safe'}[value] || 'unknown');
            const rows = findings.filter(finding => (!severity || finding.severity === severity) && (!status || finding.status === status) && JSON.stringify(finding).toLowerCase().includes(query)).map(finding => [node('span', finding.severity, `risk-badge ${badgeClass(finding.severity)}`), finding.title, finding.host?.ip_address, finding.port?.port, (finding.cve || []).join(', '), node('span', finding.status, `risk-badge ${badgeClass(finding.status)}`)]);
            document.getElementById('scanFindings').replaceChildren(table(['Severity', 'Title', 'Host', 'Port', 'CVE', 'Status'], rows));
        };
        const render = payload => {
            hosts = payload.data.hosts?.data || []; findings = payload.data.findings?.data || []; const summary = payload.data.summary || {};
            const overview = document.getElementById('scanOverview'); overview.replaceChildren();
            [['Hosts', summary.hosts], ['Hosts up', summary.hosts_up], ['Ports', summary.ports], ['Open ports', summary.open_ports], ['Findings', summary.findings]].forEach(([label, value]) => { const card = node('article', '', 'metric-card'); card.append(node('strong', value ?? 0), node('span', label)); overview.appendChild(card); });
            renderHosts(); renderPorts(); renderFindings();
            document.getElementById('scanOperatingSystems').replaceChildren(table(['Host', 'Operating system', 'Accuracy'], hosts.filter(host => host.operating_system).map(host => [host.ip_address, host.operating_system, `${host.os_accuracy ?? 0}%`])));
            document.getElementById('scanCves').replaceChildren(table(['CVE', 'Finding', 'Severity'], findings.flatMap(finding => (finding.cve || []).map(cve => [cve, finding.title, finding.severity]))));
            document.getElementById('scanRemediation').replaceChildren(table(['Finding', 'Solution'], findings.filter(finding => finding.solution).map(finding => [finding.title, finding.solution])));
            document.getElementById('scanEvidence').textContent = JSON.stringify({summary, hosts, findings}, null, 2);
        };
        document.getElementById('hostSearch')?.addEventListener('input', renderHosts);
        ['portStateFilter','portProtocolFilter','portSearch'].forEach(id => document.getElementById(id)?.addEventListener('input', renderPorts));
        ['findingSeverityFilter','findingStatusFilter','findingSearch'].forEach(id => document.getElementById(id)?.addEventListener('input', renderFindings));
        document.getElementById('cancelShowScan')?.addEventListener('click', async () => {
            try {
                const payload = await request(scannerData.dataset.cancelUrl, {method: 'POST', body: '{}'});
                const badge = document.getElementById('scanStatusBadge'); badge.textContent = payload.status; badge.className = `risk-badge ${statusClass(payload.status)}`;
            } catch (exception) {
                const error = document.getElementById('scanRuntimeError'); error.textContent = exception.message; error.hidden = false;
            }
        });
        const poll = async () => {
            try {
                const payload = await request(scannerData.dataset.statusUrl); const badge = document.getElementById('scanStatusBadge');
                badge.textContent = payload.status; badge.className = `risk-badge ${statusClass(payload.status)}`; document.getElementById('scanProgress').style.width = `${payload.progress}%`;
                if (payload.status === 'completed') render(await request(scannerData.dataset.resultsUrl));
                if (!terminal.has(payload.status)) statusTimer = schedule(poll, 2500);
                if (payload.error) { const error = document.getElementById('scanRuntimeError'); error.textContent = payload.error; error.hidden = false; }
            } catch (exception) {
                const error = document.getElementById('scanRuntimeError'); error.textContent = exception.message; error.hidden = false;
            }
        };
        poll();
    }

    if (scannerData.dataset.page === 'history') {
        const boxes = [...document.querySelectorAll('[data-compare-scan]')]; const compareButton = document.getElementById('compareButton');
        boxes.forEach(box => box.addEventListener('change', () => { const selected = boxes.filter(item => item.checked); if (selected.length > 2) box.checked = false; compareButton.disabled = boxes.filter(item => item.checked).length !== 2; }));
        document.getElementById('compareScansForm')?.addEventListener('submit', event => { event.preventDefault(); const ids = boxes.filter(item => item.checked).map(item => item.value); if (ids.length === 2) location.href = `${scannerData.dataset.compareUrl}?left=${ids[0]}&right=${ids[1]}`; });
        const showHistoryError = message => {
            let error = document.getElementById('scanRuntimeError');
            if (!error) { error = node('div', '', 'service-error'); error.id = 'scanRuntimeError'; scannerData.before(error); }
            error.textContent = message;
        };
        document.querySelectorAll('[data-rerun-url]').forEach(button => button.addEventListener('click', async () => {
            if (button.dataset.busy === 'true') return;
            button.dataset.busy = 'true'; button.disabled = true;
            try { const payload = await request(button.dataset.rerunUrl, {method:'POST', body:JSON.stringify({authorization_confirmed:true})}); location.href = `/scanner/scans/${payload.scan_id}`; }
            catch (error) { showHistoryError(error.message); button.disabled = false; button.dataset.busy = 'false'; }
        }));
        document.querySelectorAll('[data-delete-url]').forEach(button => button.addEventListener('click', async () => {
            if (button.dataset.busy === 'true' || !confirm(scannerData.dataset.deleteConfirm)) return;
            button.dataset.busy = 'true'; button.disabled = true;
            try { await request(button.dataset.deleteUrl, {method:'DELETE'}); location.reload(); }
            catch (error) { showHistoryError(error.message); button.disabled = false; button.dataset.busy = 'false'; }
        }));
    }

    if (scannerData.dataset.page === 'compare') {
        const container = document.getElementById('scanComparison');
        if (container) Promise.all([request(container.dataset.leftResults), request(container.dataset.rightResults)]).then(([left, right]) => {
            const values = payload => new Set((payload.data.hosts?.data || []).flatMap(host => (host.ports || []).filter(port => port.state === 'open').map(port => `${host.ip_address}:${port.protocol}/${port.port}`)));
            const leftPorts = values(left); const rightPorts = values(right);
            const rows = [...new Set([...leftPorts, ...rightPorts])].sort().map(port => [port, leftPorts.has(port) ? '✓' : '—', rightPorts.has(port) ? '✓' : '—', !leftPorts.has(port) && rightPorts.has(port) ? 'New' : (leftPorts.has(port) && !rightPorts.has(port) ? 'Closed' : 'Unchanged')]);
            container.replaceChildren(table(['Endpoint', 'Left', 'Right', 'Change'], rows));
        }).catch(exception => container.replaceChildren(node('p', exception.message, 'service-error')));
    }

    window.addEventListener('pagehide', () => {
        timers.forEach(timer => clearTimeout(timer));
        timers.clear();
        controllers.forEach(controller => controller.abort());
        controllers.clear();
    }, {once: true});
}
