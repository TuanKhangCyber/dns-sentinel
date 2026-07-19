import './bootstrap';
import './scanner';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const root = document.documentElement;
let savedTheme = null;
try { savedTheme = localStorage.getItem('theme'); } catch {}
const preferredTheme = 'dark';

function applyTheme(theme) {
    root.dataset.theme = theme;
    try { localStorage.setItem('theme', theme); } catch {}

    document.querySelectorAll('.theme-toggle').forEach((button) => {
        const dark = theme === 'dark';
        const icon = button.querySelector('.theme-icon');
        if (icon) icon.textContent = dark ? '☀️' : '🌙';
        const label = button.querySelector('.theme-label');
        if (label) {
            label.textContent = dark
                ? document.documentElement.lang === 'vi' ? 'Chế độ sáng' : 'Light mode'
                : document.documentElement.lang === 'vi' ? 'Chế độ tối' : 'Dark mode';
        }
        button.setAttribute('aria-pressed', String(dark));
    });
}

document.querySelectorAll('[data-recaptcha-form]').forEach(form => {
    let submitting = false;
    form.addEventListener('submit', async event => {
        if (submitting) return;
        event.preventDefault();
        const button = form.querySelector('button[type="submit"]');
        const status = form.querySelector('[data-recaptcha-status]');
        const tokenInput = form.querySelector('input[name="g-recaptcha-response"]');
        const siteKey = form.dataset.recaptchaSiteKey;
        if (!button || !tokenInput || !siteKey || !window.grecaptcha) {
            if (status) status.textContent = document.documentElement.lang === 'vi' ? 'Không thể tải xác minh chống bot.' : 'Anti-bot verification could not be loaded.';
            return;
        }
        button.disabled = true;
        try {
            await new Promise(resolve => window.grecaptcha.ready(resolve));
            const token = await window.grecaptcha.execute(siteKey, {action: form.dataset.recaptchaAction});
            tokenInput.value = token;
            submitting = true;
            form.submit();
        } catch {
            if (status) status.textContent = document.documentElement.lang === 'vi' ? 'Xác minh chống bot thất bại. Vui lòng thử lại.' : 'Anti-bot verification failed. Please retry.';
            button.disabled = false;
        }
    });
});

applyTheme(savedTheme || preferredTheme);

document.querySelectorAll('.theme-toggle').forEach((button) => {
    button.addEventListener('click', () => {
        applyTheme(root.dataset.theme === 'dark' ? 'light' : 'dark');
    });
});

document.querySelectorAll('[data-auto-submit]').forEach((select) => {
    select.addEventListener('change', () => select.form.submit());
});

document.querySelectorAll('form[data-submit-lock]').forEach((form) => {
    let submitting = false;
    form.addEventListener('submit', (event) => {
        if (submitting) { event.preventDefault(); return; }
        if (!form.checkValidity()) return;
        submitting = true;
        const button = form.querySelector('button[type="submit"]');
        if (button) { button.disabled = true; button.setAttribute('aria-busy', 'true'); }
    });
});

document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) event.preventDefault();
    });
});

document.querySelectorAll('[data-navigation-toggle]').forEach((toggle) => {
    const navigation = document.getElementById(toggle.getAttribute('aria-controls'));
    if (!navigation) return;
    toggle.addEventListener('click', () => {
        const expanded = toggle.getAttribute('aria-expanded') === 'true';
        toggle.setAttribute('aria-expanded', String(!expanded));
        navigation.toggleAttribute('data-open', !expanded);
    });
});

const mapElement = document.getElementById('ipMap');
const dnsRecordsElement = document.getElementById('dnsRecords');
const rdapElement = document.getElementById('rdap');

if (dnsRecordsElement) {
    const labels = dnsRecordsElement.dataset;
    const recordValue = (record) => {
        if (record.ip) return record.ip;
        if (record.ipv6) return record.ipv6;
        if (record.target) return record.target;
        if (Array.isArray(record.entries)) return record.entries.join(' ');
        if (record.txt) return record.txt;
        if (record.value !== undefined) return record.value;
        if (record.mname) return `${record.mname}${record.rname ? ` / ${record.rname}` : ''}`;
        return '—';
    };

    const addDnsField = (container, label, value) => {
        const row = document.createElement('div');
        const term = document.createElement('span');
        const description = document.createElement('strong');
        term.textContent = `${label}:`;
        description.textContent = value === null || value === undefined || value === '' ? '—' : String(value);
        row.append(term, description);
        container.appendChild(row);
    };

    window.addEventListener('dns:records', (event) => {
        const records = Array.isArray(event.detail?.records) ? event.detail.records : [];
        const tabStatus = document.querySelector('[data-tab-target="dns"] .tab-status');
        if (tabStatus) tabStatus.className = `tab-status ${records.length ? 'safe' : 'warning'}`;
        dnsRecordsElement.replaceChildren();

        if (!records.length) {
            const notice = document.createElement('p');
            notice.className = 'dns-empty';
            notice.textContent = event.detail?.status?.message || labels.labelEmpty;
            dnsRecordsElement.appendChild(notice);
            return;
        }

        const groups = new Map();
        records.forEach((record) => {
            const type = record.type || 'DNS';
            if (!groups.has(type)) groups.set(type, []);
            groups.get(type).push(record);
        });

        const timeline = document.createElement('div');
        timeline.className = 'dns-timeline';

        groups.forEach((groupRecords, type) => {
            const item = document.createElement('details');
            item.className = 'dns-timeline-item';
            item.open = type === 'A' || type === 'AAAA';

            const summary = document.createElement('summary');
            const dot = document.createElement('span');
            const badge = document.createElement('span');
            const count = document.createElement('span');
            dot.className = 'dns-timeline-dot';
            badge.className = 'dns-type-badge';
            badge.textContent = type;
            count.className = 'dns-record-count';
            count.textContent = labels.labelCount.replace('__COUNT__', groupRecords.length);
            summary.append(dot, badge, count);
            item.appendChild(summary);

            const content = document.createElement('div');
            content.className = 'dns-timeline-content';
            groupRecords.forEach((record) => {
                const card = document.createElement('article');
                card.className = 'dns-record-card';
                const details = document.createElement('div');
                details.className = 'dns-record-details';
                addDnsField(details, labels.labelHost, record.host);
                addDnsField(details, labels.labelValue, recordValue(record));
                addDnsField(details, labels.labelTtl, record.ttl);
                if (record.pri !== undefined) addDnsField(details, labels.labelPriority, record.pri);
                card.appendChild(details);
                content.appendChild(card);
            });
            item.appendChild(content);
            timeline.appendChild(item);
        });

        dnsRecordsElement.appendChild(timeline);
    });
}

if (rdapElement) {
    const labels = rdapElement.dataset;
    const formatValue = (value) => {
        if (Array.isArray(value)) return value.length ? value.join(', ') : labels.labelPrivate;
        if (value === null || value === undefined || value === '') return labels.labelPrivate;
        return String(value);
    };
    const formatDate = (value) => {
        if (!value) return labels.labelPrivate;
        const date = new Date(value);
        return Number.isNaN(date.getTime()) ? value : date.toLocaleString(document.documentElement.lang);
    };
    const addRdapField = (container, label, value, className = '') => {
        const row = document.createElement('div');
        if (className) row.className = className;
        const term = document.createElement('dt');
        const description = document.createElement('dd');
        term.textContent = label;
        description.textContent = formatValue(value);
        row.append(term, description);
        container.appendChild(row);
    };

    window.addEventListener('rdap:result', (event) => {
        const summary = event.detail?.summary;
        const tabStatus = document.querySelector('[data-tab-target="rdap"] .tab-status');
        if (tabStatus) tabStatus.className = `tab-status ${summary ? 'safe' : 'warning'}`;
        rdapElement.replaceChildren();
        if (!summary) {
            const notice = document.createElement('p');
            notice.className = 'rdap-empty';
            notice.textContent = event.detail?.error || labels.labelUnavailable;
            rdapElement.appendChild(notice);
            return;
        }

        const list = document.createElement('dl');
        list.className = 'rdap-grid';
        addRdapField(list, labels.labelDomain, summary.domain);
        addRdapField(list, labels.labelHandle, summary.handle);
        addRdapField(list, labels.labelStatus, summary.status);
        addRdapField(list, labels.labelRegistrar, summary.registrar);
        addRdapField(list, labels.labelRegistrant, summary.registrant);
        addRdapField(list, labels.labelTechnical, summary.technical);
        addRdapField(list, labels.labelAdministrative, summary.administrative);
        addRdapField(list, labels.labelNameservers, summary.nameservers, 'rdap-wide');
        addRdapField(list, labels.labelRegisteredAt, formatDate(summary.registered_at));
        addRdapField(list, labels.labelExpiresAt, formatDate(summary.expires_at));
        addRdapField(list, labels.labelUpdatedAt, formatDate(summary.updated_at));
        const dnssec = summary.dnssec === true
            ? labels.labelSigned
            : summary.dnssec === false ? labels.labelUnsigned : labels.labelPrivate;
        addRdapField(list, labels.labelDnssec, dnssec);
        rdapElement.appendChild(list);

        let sourceUrl = null;
        try {
            const candidate = new URL(summary.self_link);
            if (['http:', 'https:'].includes(candidate.protocol)) sourceUrl = candidate.href;
        } catch {}
        if (sourceUrl) {
            const link = document.createElement('a');
            link.href = sourceUrl;
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            link.className = 'rdap-source-link';
            link.textContent = labels.labelSource;
            rdapElement.appendChild(link);
        }
    });
}

if (mapElement) {
    const labels = mapElement.dataset;
    const fetchReconJson = async (url, timeout = 15000) => {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), timeout);
        try {
            const response = await fetch(url, {headers: {'Accept': 'application/json'}, signal: controller.signal});
            const raw = await response.text();
            let payload;
            try { payload = raw === '' ? {} : JSON.parse(raw); }
            catch { throw new Error(`Invalid server response (HTTP ${response.status})`); }
            if (!response.ok) throw new Error(payload.error || payload.message || `HTTP ${response.status}`);
            return payload;
        } finally {
            clearTimeout(timeoutId);
        }
    };
    const map = L.map(mapElement, {scrollWheelZoom: false}).setView([18, 10], 2);
    const markerLayer = L.featureGroup().addTo(map);

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    const addDetail = (container, label, value) => {
        if (value === null || value === undefined || value === '') return;
        const row = document.createElement('div');
        const term = document.createElement('span');
        const description = document.createElement('strong');
        term.textContent = `${label}:`;
        description.textContent = String(value);
        row.append(term, description);
        container.appendChild(row);
    };

    const createIpCard = (entry) => {
        const geo = entry.geo?.success ? entry.geo : {};
        const connection = geo.connection || {};
        const card = document.createElement('article');
        card.className = 'ip-location-card';

        const heading = document.createElement('h4');
        heading.textContent = `${geo.flag?.emoji || '🌐'} ${entry.ip}`;
        card.appendChild(heading);

        const details = document.createElement('div');
        details.className = 'location-details';
        addDetail(details, labels.labelCity, geo.city);
        addDetail(details, labels.labelRegion, geo.region);
        addDetail(details, labels.labelCountry, geo.country);
        addDetail(details, labels.labelIsp, connection.isp);
        addDetail(details, labels.labelOrganization, connection.org);
        addDetail(details, labels.labelAsn, connection.asn ? `AS${connection.asn}` : null);
        addDetail(details, labels.labelScope, entry.scope);
        addDetail(details, labels.labelRange, entry.range);

        if (Number.isFinite(Number(geo.latitude)) && Number.isFinite(Number(geo.longitude))) {
            addDetail(details, labels.labelCoordinates, `${geo.latitude}, ${geo.longitude}`);
            const link = document.createElement('a');
            link.href = `https://www.openstreetmap.org/?mlat=${encodeURIComponent(geo.latitude)}&mlon=${encodeURIComponent(geo.longitude)}#map=13/${encodeURIComponent(geo.latitude)}/${encodeURIComponent(geo.longitude)}`;
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            link.textContent = labels.labelOpenMap;
            link.className = 'map-link';
            details.appendChild(link);
        } else {
            const notice = document.createElement('p');
            notice.className = 'location-notice';
            notice.textContent = labels.labelNoLocation;
            details.appendChild(notice);
        }

        card.appendChild(details);
        return card;
    };

    window.addEventListener('dns:results', (event) => {
        const entries = Array.isArray(event.detail) ? event.detail : [];
        const cards = document.getElementById('ipsGeo');
        cards.replaceChildren();
        markerLayer.clearLayers();

        if (!entries.length) {
            cards.textContent = labels.labelNoResults;
            map.setView([18, 10], 2);
            return;
        }

        entries.forEach((entry) => {
            cards.appendChild(createIpCard(entry));
            const geo = entry.geo?.success ? entry.geo : null;
            const latitude = Number(geo?.latitude);
            const longitude = Number(geo?.longitude);
            if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return;

            const marker = L.circleMarker([latitude, longitude], {
                radius: 9,
                color: '#ffffff',
                weight: 2,
                fillColor: '#2563eb',
                fillOpacity: 0.95,
            });
            const popup = document.createElement('div');
            const title = document.createElement('strong');
            const place = document.createElement('p');
            title.textContent = entry.ip;
            place.textContent = [geo.city, geo.region, geo.country].filter(Boolean).join(', ');
            popup.append(title, place);
            marker.bindPopup(popup);
            marker.addTo(markerLayer);
        });

        if (markerLayer.getLayers().length) {
            map.fitBounds(markerLayer.getBounds().pad(0.3), {maxZoom: 12});
        } else {
            map.setView([18, 10], 2);
        }

        setTimeout(() => map.invalidateSize(), 0);
    });
}

const reconDashboard = document.getElementById('reconDashboard');

if (reconDashboard) {
    const labels = reconDashboard.dataset;
    const statusLabel = (status) => ({
        safe: labels.labelSafe,
        warning: labels.labelWarning,
        danger: labels.labelDanger,
        error: labels.labelError,
        running: labels.labelRunning,
    })[status] || status;

    const reconTabs = [...document.querySelectorAll('[data-tab-target]')];
    const reconPanels = [...document.querySelectorAll('[data-tab-panel]')];
    const activateReconTab = (button, focus = false) => {
        if (!button || button.disabled) return;
        reconTabs.forEach(tab => {
            const active = tab === button;
            tab.classList.toggle('active', active);
            tab.setAttribute('aria-selected', String(active));
            tab.tabIndex = active ? 0 : -1;
        });
        reconPanels.forEach(panel => {
            const active = panel.dataset.tabPanel === button.dataset.tabTarget;
            panel.classList.toggle('active', active);
            panel.hidden = !active;
        });
        if (focus) button.focus();
    };
    reconTabs.forEach((button, index) => {
        button.setAttribute('role', 'tab');
        button.addEventListener('click', () => activateReconTab(button));
        button.addEventListener('keydown', event => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const enabled = reconTabs.filter(tab => !tab.disabled);
            const current = enabled.indexOf(button);
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? enabled.length - 1 : (current + (event.key === 'ArrowRight' ? 1 : -1) + enabled.length) % enabled.length;
            activateReconTab(enabled[next], true);
        });
        if (!button.classList.contains('active')) button.tabIndex = -1;
        if (index === 0 && !reconTabs.some(tab => tab.classList.contains('active'))) activateReconTab(button);
    });
    reconPanels.forEach(panel => panel.setAttribute('role', 'tabpanel'));
    reconTabs[0]?.closest('nav')?.setAttribute('role', 'tablist');
    activateReconTab(reconTabs.find(tab => tab.classList.contains('active')) || reconTabs.find(tab => !tab.disabled));

    const element = (tag, className = '', text = '') => {
        const node = document.createElement(tag);
        if (className) node.className = className;
        if (text !== '') node.textContent = text;
        return node;
    };
    const valueText = (value) => {
        if (Array.isArray(value)) return value.length ? value.join(', ') : '—';
        if (value && typeof value === 'object') return Object.entries(value).map(([key, item]) => `${key}=${item}`).join(', ');
        return value === null || value === undefined || value === '' ? '—' : String(value);
    };
    const keyValueGrid = (items) => {
        const grid = element('dl', 'recon-kv-grid');
        items.forEach(([key, value]) => {
            const row = element('div');
            row.append(element('dt', '', key), element('dd', '', valueText(value)));
            grid.appendChild(row);
        });
        return grid;
    };
    const badge = (status, text = null) => element('span', `risk-badge ${status}`, text || statusLabel(status));
    const setReconExports = (historyId = null) => {
        const actions = document.getElementById('exportActions');
        const links = [...document.querySelectorAll('[data-recon-export]')];
        links.forEach((link) => {
            link.removeAttribute('href');
            link.setAttribute('aria-disabled', 'true');
            if (historyId && link.dataset.exportBase && link.dataset.exportFormat) {
                link.href = `${link.dataset.exportBase}/${encodeURIComponent(historyId)}/export/${link.dataset.exportFormat}`;
                link.removeAttribute('aria-disabled');
            }
        });
        if (actions) actions.hidden = !historyId || links.length === 0;
    };
    window.setReconExports = setReconExports;

    const renderSecurityHeaders = (container, result) => {
        const score = element('div', 'score-card', `${labels.labelScore}: ${result.score ?? 0}/100`);
        container.appendChild(score);
        const list = element('div', 'check-list');
        (result.data?.headers || []).forEach(header => {
            const row = element('article', 'check-row');
            const info = element('div');
            info.append(element('strong', '', header.name), element('p', '', header.value || '—'));
            row.append(info, badge(header.present ? 'safe' : header.risk, header.present ? labels.labelPresent : labels.labelMissing));
            list.appendChild(row);
        });
        container.appendChild(list);
    };

    const renderSsl = (container, result) => {
        const data = result.data || {};
        container.appendChild(keyValueGrid([
            [labels.labelIssuer, data.issuer], [labels.labelSubject, data.subject], [labels.labelSan, data.san],
            [labels.labelIssuedAt, data.issued_at], [labels.labelExpiresAt, data.expires_at],
            [labels.labelDaysRemaining, data.days_remaining], [labels.labelSignature, data.signature_algorithm],
        ]));
    };

    const renderEmail = (container, result) => {
        const data = result.data || {};
        const cards = element('div', 'email-security-grid');
        const emailCard = (title, found, details) => {
            const card = element('article', 'security-card');
            card.append(element('h3', '', title), badge(found ? 'safe' : 'danger', found ? labels.labelPresent : labels.labelMissing), element('p', '', details || '—'));
            return card;
        };
        cards.append(
            emailCard('SPF', data.spf?.found, (data.spf?.records || []).join('\n')),
            emailCard('DMARC', data.dmarc?.found, `${data.dmarc?.policy || '—'}\n${(data.dmarc?.records || []).join('\n')}`),
            emailCard('DKIM', (data.dkim || []).some(item => item.found), (data.dkim || []).map(item => `${item.selector}: ${item.found ? labels.labelPresent : labels.labelMissing}`).join('\n')),
        );
        container.appendChild(cards);
    };

    const renderTech = (container, result) => {
        const data = result.data || {};
        container.appendChild(keyValueGrid([
            [labels.labelServer, data.server], [labels.labelPoweredBy, data.powered_by], [labels.labelGenerator, data.generator],
            [labels.labelTechnologies, data.technologies], [labels.labelCdnWaf, data.cdn_waf], ['HTTP', data.http_status],
        ]));
    };

    const renderNmap = (container, result) => {
        const data = result.data || {};
        container.appendChild(keyValueGrid([
            [labels.labelNmapTargetIp, data.target_ip],
            [labels.labelNmapHostStatus, data.host_up ? labels.labelNmapHostUp : labels.labelNmapHostDown],
            [labels.labelNmapProfile, data.profile],
            [labels.labelNmapOpenPorts, data.open_port_count ?? 0],
        ]));

        if (!(data.ports || []).length) {
            container.appendChild(element('p', 'empty-state', labels.labelNmapNoOpenPorts));
            return;
        }

        const wrapper = element('div', 'table-scroll');
        const table = element('table', 'subdomain-table');
        const head = document.createElement('thead');
        const headRow = document.createElement('tr');
        [labels.labelNmapPort, labels.labelNmapProtocol, labels.labelNmapState, labels.labelNmapService]
            .forEach(label => headRow.appendChild(element('th', '', label)));
        head.appendChild(headRow);
        const body = document.createElement('tbody');
        data.ports.forEach(port => {
            const row = document.createElement('tr');
            row.append(
                element('td', '', port.port), element('td', '', port.protocol),
                element('td', '', port.state), element('td', '', port.service || '—'),
            );
            body.appendChild(row);
        });
        table.append(head, body);
        wrapper.appendChild(table);
        container.appendChild(wrapper);
    };

    let currentSubdomains = [];
    const renderSubdomainRows = () => {
        const tbody = document.getElementById('subdomainRows');
        const query = document.getElementById('subdomainFilter').value.trim().toLowerCase();
        tbody.replaceChildren();
        currentSubdomains.filter(item => item.name.includes(query)).forEach(item => {
            const row = document.createElement('tr');
            row.append(element('td', '', item.name), element('td'), element('td', '', (item.ips || []).join(', ') || '—'));
            row.children[1].appendChild(badge(item.resolves ? 'safe' : 'danger', item.resolves ? labels.labelResolved : labels.labelUnresolved));
            tbody.appendChild(row);
        });
    };
    document.getElementById('subdomainFilter')?.addEventListener('input', renderSubdomainRows);
    document.querySelector('[data-sort-subdomains]')?.addEventListener('click', () => {
        currentSubdomains.reverse();
        renderSubdomainRows();
    });

    window.addEventListener('recon:result', (event) => {
        const {service, result} = event.detail;
        const resultContainer = document.querySelector(`[data-service-result="${service}"]`);
        const statusBadge = document.querySelector(`[data-service-badge="${service}"]`);
        const tabStatus = document.querySelector(`[data-tab-target="${service}"] .tab-status`);
        statusBadge?.replaceChildren(badge(result.status));
        if (tabStatus) tabStatus.className = `tab-status ${result.status}`;

        if (service === 'subdomains') {
            currentSubdomains = result.data?.subdomains || [];
            renderSubdomainRows();
            return;
        }
        if (!resultContainer) return;
        resultContainer.replaceChildren();
        if (result.status === 'error') {
            resultContainer.appendChild(element('div', 'service-error', result.error || labels.labelServiceFailed));
            return;
        }
        if (service === 'security_headers') renderSecurityHeaders(resultContainer, result);
        if (service === 'ssl_certificate') renderSsl(resultContainer, result);
        if (service === 'email_security') renderEmail(resultContainer, result);
        if (service === 'tech_fingerprint') renderTech(resultContainer, result);
        if (service === 'nmap') renderNmap(resultContainer, result);
    });

    const loadHistory = async () => {
        const list = document.getElementById('historyList');
        try {
            const payload = await fetchReconJson('/recon/history');
            list.replaceChildren();
            if (!(payload.histories || []).length) {
                list.appendChild(element('p', 'empty-state', labels.labelHistoryEmpty));
                return;
            }
            payload.histories.forEach(history => {
                const button = element('button', 'history-item');
                button.type = 'button';
                button.dataset.historyId = history.id;
                button.append(element('strong', '', history.target), badge(history.status), element('small', '', new Date(history.created_at).toLocaleString(document.documentElement.lang)));
                button.addEventListener('click', async () => {
                    button.disabled = true;
                    setReconExports();
                    try {
                        const historyPayload = await fetchReconJson(`/recon/history/${history.id}`);
                        const record = historyPayload.history;
                        if (!record) throw new Error(labels.labelServiceFailed);
                        const targetInput = document.getElementById('target');
                        if (targetInput) targetInput.value = record.target;
                        setReconExports(record.id);
                        Object.entries(record.results || {}).forEach(([service, result]) => {
                            if (service === 'dns') {
                                const data = result.data || {};
                                window.dispatchEvent(new CustomEvent('dns:records', {detail: {records: data.dns_records || [], status: data.dns_status || {}}}));
                                window.dispatchEvent(new CustomEvent('dns:results', {detail: data.enriched_ips || []}));
                                window.dispatchEvent(new CustomEvent('rdap:result', {detail: {summary: data.rdap_summary || null, error: data.rdap_error || null}}));
                            } else {
                                window.dispatchEvent(new CustomEvent('recon:result', {detail: {service, result}}));
                            }
                        });
                    } catch {
                        setReconExports();
                        list.prepend(element('p', 'service-error', labels.labelServiceFailed));
                    } finally {
                        button.disabled = false;
                    }
                });
                list.appendChild(button);
            });
        } catch {
            list.replaceChildren(element('p', 'empty-state', labels.labelServiceFailed));
        }
    };
    loadHistory();
    window.addEventListener('recon:history-refresh', loadHistory);
}
