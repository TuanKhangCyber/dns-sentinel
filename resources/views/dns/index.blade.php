<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('ui.dns_page_title') }}</title>
    @include('partials.theme-bootstrap')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    @include('partials.app-navigation', ['placement' => 'dns'])
    <section class="dashboard-hero">
        <p class="eyebrow">{{ __('ui.security_operations') }}</p>
        <h1>{{ __('ui.dashboard_title') }}</h1>
        <p>{{ __('ui.dashboard_intro') }}</p>
    </section>

    <section class="network-summary" aria-labelledby="public-ip-title">
        <div>
            <h2 id="public-ip-title">{{ __('ui.public_ip') }}</h2>
            <p>{{ __('ui.public_ip_help') }}</p>
            <div id="publicIpConnectionStatus" class="connection-badge" data-status="loading" role="status" aria-live="polite">
                <span class="status-light" aria-hidden="true"></span>
                <span class="status-text">{{ __('ui.checking_connection') }}</span>
            </div>
        </div>
        <dl id="publicIp" class="ip-details" data-status="loading" aria-live="polite">
            <div>
                <dt>{{ __('ui.ip_address') }}</dt>
                <dd id="publicIpAddress">{{ __('ui.loading') }}</dd>
            </div>
            <div>
                <dt>{{ __('ui.ip_version') }}</dt>
                <dd id="publicIpVersion">—</dd>
            </div>
            <div>
                <dt>{{ __('ui.ip_type') }}</dt>
                <dd id="publicIpType">—</dd>
            </div>
            <div>
                <dt>{{ __('ui.ip_scope') }}</dt>
                <dd id="publicIpScope">—</dd>
            </div>
            <div>
                <dt>{{ __('ui.ip_range') }}</dt>
                <dd id="publicIpRange">—</dd>
            </div>
            <div>
                <dt>{{ __('ui.data_source') }}</dt>
                <dd id="publicIpSource">—</dd>
            </div>
        </dl>
    </section>

    <div id="reconDashboard" class="dashboard-layout"
        data-label-safe="{{ __('ui.safe') }}"
        data-label-warning="{{ __('ui.warning') }}"
        data-label-danger="{{ __('ui.danger') }}"
        data-label-error="{{ __('ui.error_status') }}"
        data-label-running="{{ __('ui.running') }}"
        data-label-present="{{ __('ui.present') }}"
        data-label-missing="{{ __('ui.missing') }}"
        data-label-resolved="{{ __('ui.resolved') }}"
        data-label-unresolved="{{ __('ui.unresolved') }}"
        data-label-score="{{ __('ui.score') }}"
        data-label-issuer="{{ __('ui.issuer') }}"
        data-label-subject="{{ __('ui.subject') }}"
        data-label-san="{{ __('ui.san') }}"
        data-label-issued-at="{{ __('ui.issued_at') }}"
        data-label-expires-at="{{ __('ui.expires_at') }}"
        data-label-days-remaining="{{ __('ui.days_remaining') }}"
        data-label-signature="{{ __('ui.signature_algorithm') }}"
        data-label-server="{{ __('ui.server_technology') }}"
        data-label-powered-by="{{ __('ui.powered_by') }}"
        data-label-generator="{{ __('ui.generator') }}"
        data-label-technologies="{{ __('ui.technologies') }}"
        data-label-cdn-waf="{{ __('ui.cdn_waf') }}"
        data-label-nmap-target-ip="{{ __('ui.nmap_target_ip') }}"
        data-label-nmap-host-status="{{ __('ui.nmap_host_status') }}"
        data-label-nmap-profile="{{ __('ui.nmap_profile') }}"
        data-label-nmap-open-ports="{{ __('ui.nmap_open_ports') }}"
        data-label-nmap-port="{{ __('ui.nmap_port') }}"
        data-label-nmap-protocol="{{ __('ui.nmap_protocol') }}"
        data-label-nmap-state="{{ __('ui.nmap_state') }}"
        data-label-nmap-service="{{ __('ui.nmap_service') }}"
        data-label-nmap-host-up="{{ __('ui.nmap_host_up') }}"
        data-label-nmap-host-down="{{ __('ui.nmap_host_down') }}"
        data-label-nmap-no-open-ports="{{ __('ui.nmap_no_open_ports') }}"
        data-label-history-empty="{{ __('ui.history_empty') }}"
        data-label-service-failed="{{ __('ui.service_failed') }}">
        <aside class="history-panel">
            <h2>{{ __('ui.history') }}</h2>
            <div id="historyList" class="history-list"><div class="skeleton skeleton-line"></div></div>
        </aside>

        <main class="dashboard-main">
            <form id="lookupForm" class="scan-form">
                <label class="sr-only" for="target">{{ __('ui.scan_domain') }}</label>
                <input id="target" name="target" placeholder="{{ __('ui.scan_placeholder') }}" required>
                <button type="submit">{{ __('ui.scan_domain') }}</button>
            </form>
            <div id="error" class="error" role="alert"></div>

            <div id="exportActions" class="export-actions" hidden>
                <strong>{{ __('ui.export_report') }}</strong>
                @if($featureStatuses['export_json']['allowed'])<a id="exportJson" class="button-link" data-recon-export data-export-base="{{ url('/recon/history') }}" data-export-format="json" aria-disabled="true">{{ __('ui.export_json') }}</a>@else<span class="badge warning" aria-disabled="true">JSON · Plus</span>@endif
                @if($featureStatuses['export_pdf']['allowed'])<a id="exportPdf" class="button-link" data-recon-export data-export-base="{{ url('/recon/history') }}" data-export-format="pdf" aria-disabled="true">{{ __('ui.export_pdf') }}</a>@else<span class="badge warning" aria-disabled="true">PDF · Plus</span>@endif
            </div>

            <nav class="result-tabs" aria-label="{{ __('ui.security_results') }}">
                @php($tabFeatures = ['ssl_certificate'=>'ssl_analysis','security_headers'=>'security_headers','email_security'=>'email_security','subdomains'=>'subdomain_scan','tech_fingerprint'=>'technology_fingerprint','nmap'=>'nmap_scan'])
                @foreach ([
                    'dns' => __('ui.tab_dns'), 'ssl_certificate' => __('ui.tab_ssl'),
                    'security_headers' => __('ui.tab_headers'), 'email_security' => __('ui.tab_email'),
                    'subdomains' => __('ui.tab_subdomains'), 'rdap' => __('ui.tab_rdap'),
                    'tech_fingerprint' => __('ui.tab_tech'), 'nmap' => __('ui.tab_nmap'),
                ] as $tab => $label)
                    @php($tabAccess = isset($tabFeatures[$tab]) ? $featureStatuses[$tabFeatures[$tab]] : ['allowed'=>true,'reason'=>null])
                    @continue(!$tabAccess['allowed'] && !($tabAccess['visible'] ?? true))
                    <button type="button" class="tab-button @if($loop->first) active @endif @unless($tabAccess['allowed']) locked @endunless" data-tab-target="{{ $tab }}" @disabled(!$tabAccess['allowed']) title="{{ !$tabAccess['allowed'] ? __('platform.errors.'.$tabAccess['reason']) : '' }}">
                        <span>{{ $label }} @unless($tabAccess['allowed']) 🔒 @endunless</span><span class="tab-status" aria-hidden="true"></span>
                    </button>
                @endforeach
            </nav>

            <section class="tab-panel active" data-tab-panel="dns">
                <div class="grid results-grid">
                    <div>
            <h3>{{ __('ui.dns_records') }}</h3>
            <p class="section-help">{{ __('ui.dns_timeline_help') }}</p>
            <div id="dnsRecords" class="dns-records"
                data-label-type="{{ __('ui.record_type') }}"
                data-label-host="{{ __('ui.record_host') }}"
                data-label-value="{{ __('ui.record_value') }}"
                data-label-ttl="{{ __('ui.record_ttl') }}"
                data-label-priority="{{ __('ui.record_priority') }}"
                data-label-count="{{ __('ui.record_count', ['count' => '__COUNT__']) }}"
                data-label-empty="{{ __('ui.no_dns_records') }}" aria-live="polite">—</div>
                    </div>
                    <div>
            <h3>{{ __('ui.resolved_ips') }}</h3>
            <p class="section-help">{{ __('ui.ip_scope_help') }}</p>
            <div id="ipsGeo" class="ip-cards" aria-live="polite">—</div>
                    </div>
                    <div class="grid-wide map-panel">
            <h3>{{ __('ui.map_title') }}</h3>
            <p class="section-help">{{ __('ui.map_help') }}</p>
            <div id="ipMap"
                data-label-city="{{ __('ui.city') }}"
                data-label-region="{{ __('ui.region') }}"
                data-label-country="{{ __('ui.country') }}"
                data-label-coordinates="{{ __('ui.coordinates') }}"
                data-label-isp="{{ __('ui.isp') }}"
                data-label-organization="{{ __('ui.organization') }}"
                data-label-asn="{{ __('ui.asn') }}"
                data-label-scope="{{ __('ui.network_scope') }}"
                data-label-range="{{ __('ui.network_range') }}"
                data-label-no-location="{{ __('ui.no_location') }}"
                data-label-no-results="{{ __('ui.no_ip_results') }}"
                data-label-open-map="{{ __('ui.open_map') }}"></div>
                    </div>
                </div>
            </section>

            @foreach (['ssl_certificate', 'security_headers', 'email_security', 'tech_fingerprint', 'nmap'] as $service)
                <section class="tab-panel" data-tab-panel="{{ $service }}">
                    <div class="service-heading"><h2>{{ __('ui.tab_'.match($service) {
                        'ssl_certificate' => 'ssl', 'security_headers' => 'headers',
                        'email_security' => 'email', 'nmap' => 'nmap', default => 'tech'
                    }) }}</h2><span class="service-badge" data-service-badge="{{ $service }}">—</span></div>
                    <div class="service-result" data-service-result="{{ $service }}">
                        <div class="empty-state">{{ __('ui.loading_service') }}</div>
                    </div>
                </section>
            @endforeach

            <section class="tab-panel" data-tab-panel="subdomains">
                <div class="service-heading"><h2>{{ __('ui.tab_subdomains') }}</h2><span class="service-badge" data-service-badge="subdomains">—</span></div>
            <input id="subdomainFilter" class="table-filter" placeholder="{{ __('ui.filter_subdomains') }}" aria-label="{{ __('ui.filter_subdomains') }}">
                <div class="table-scroll"><table class="subdomain-table">
                    <thead><tr><th data-sort-subdomains>{{ __('ui.subdomain_name') }}</th><th>{{ __('ui.resolution') }}</th><th>{{ __('ui.ip_short') }}</th></tr></thead>
                    <tbody id="subdomainRows"><tr><td colspan="3">{{ __('ui.loading_service') }}</td></tr></tbody>
                </table></div>
            </section>

            <section class="tab-panel" data-tab-panel="rdap">
                <div class="service-heading"><h2>{{ __('ui.tab_rdap') }}</h2></div>
                <div>
            <h3>{{ __('ui.rdap_whois') }}</h3>
            <div id="rdap" class="rdap-details"
                data-label-domain="{{ __('ui.rdap_domain') }}"
                data-label-handle="{{ __('ui.rdap_handle') }}"
                data-label-status="{{ __('ui.rdap_status') }}"
                data-label-registrar="{{ __('ui.rdap_registrar') }}"
                data-label-registrant="{{ __('ui.rdap_registrant') }}"
                data-label-technical="{{ __('ui.rdap_technical') }}"
                data-label-administrative="{{ __('ui.rdap_administrative') }}"
                data-label-nameservers="{{ __('ui.rdap_nameservers') }}"
                data-label-registered-at="{{ __('ui.rdap_registered_at') }}"
                data-label-expires-at="{{ __('ui.rdap_expires_at') }}"
                data-label-updated-at="{{ __('ui.rdap_updated_at') }}"
                data-label-dnssec="{{ __('ui.rdap_dnssec') }}"
                data-label-signed="{{ __('ui.rdap_signed') }}"
                data-label-unsigned="{{ __('ui.rdap_unsigned') }}"
                data-label-source="{{ __('ui.rdap_source') }}"
                data-label-private="{{ __('ui.rdap_private') }}"
                data-label-unavailable="{{ __('ui.rdap_unavailable') }}" aria-live="polite">—</div>
                </div>
            </section>
        </main>
    </div>

    <script>
        async function fetchJson(url, options = {}, timeout = 75000) {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), timeout);
            try {
                const response = await fetch(url, {...options, signal: controller.signal});
                const raw = await response.text();
                let data;
                try {
                    data = raw === '' ? {} : JSON.parse(raw);
                } catch {
                    throw new Error(`Invalid server response (HTTP ${response.status})`);
                }
                return {response, data};
            } catch (error) {
                if (error.name === 'AbortError') throw new Error(@json(__('ui.connection_failed')));
                throw error;
            } finally {
                clearTimeout(timeoutId);
            }
        }

        fetchJson(@json(route('network.public-ip')), {headers: {'Accept': 'application/json'}}, 15000)
            .then(({response, data}) => {
                if (!response.ok) throw new Error(data.error || data.message || @json(__('ui.public_ip_unavailable')));
                const details = data.details || {};
                document.getElementById('publicIpAddress').textContent = data.ip || @json(__('ui.unknown'));
                document.getElementById('publicIpVersion').textContent = details.version || @json(__('ui.unknown'));
                document.getElementById('publicIpType').textContent = details.public ? @json(__('ui.public')) : @json(__('ui.private'));
                document.getElementById('publicIpScope').textContent = details.scope || @json(__('ui.unknown'));
                document.getElementById('publicIpRange').textContent = details.range || @json(__('ui.unknown'));
                document.getElementById('publicIpSource').textContent = data.source === 'public-egress'
                    ? @json(__('ui.public_egress'))
                    : @json(__('ui.request_fallback'));
                setPublicIpConnectionStatus(data.source === 'public-egress' && !data.warning);
            })
            .catch(error => {
                const address = document.getElementById('publicIpAddress');
                address.textContent = error.message || @json(__('ui.public_ip_unavailable'));
                address.classList.add('value-error');
                setPublicIpConnectionStatus(false);
            });

        function setPublicIpConnectionStatus(connected) {
            const status = connected ? 'connected' : 'disconnected';
            const statusLabel = connected ? @json(__('ui.connected')) : @json(__('ui.disconnected'));
            const badge = document.getElementById('publicIpConnectionStatus');
            document.getElementById('publicIp').dataset.status = status;
            badge.dataset.status = status;
            badge.querySelector('.status-text').textContent = statusLabel;
        }

        function setExportLinks(historyId = null) {
            if (typeof window.setReconExports === 'function') {
                window.setReconExports(historyId);
                return;
            }
            const links = [...document.querySelectorAll('[data-recon-export]')];
            links.forEach(link => {
                link.removeAttribute('href');
                link.setAttribute('aria-disabled', 'true');
                if (historyId && link.dataset.exportBase && link.dataset.exportFormat) {
                    link.href = `${link.dataset.exportBase}/${encodeURIComponent(historyId)}/export/${link.dataset.exportFormat}`;
                    link.removeAttribute('aria-disabled');
                }
            });
            document.getElementById('exportActions').hidden = !historyId || links.length === 0;
        }

        function resetReconResults() {
            setExportLinks();
            window.dispatchEvent(new CustomEvent('dns:records', {detail: {records: [], status: {}}}));
            window.dispatchEvent(new CustomEvent('dns:results', {detail: []}));
            window.dispatchEvent(new CustomEvent('rdap:result', {detail: {summary: null}}));
            document.querySelectorAll('[data-service-result]').forEach(element => {
                const skeleton = document.createElement('div');
                skeleton.className = 'skeleton-stack';
                ['skeleton-line', 'skeleton-card', 'skeleton-card'].forEach(className => {
                    const item = document.createElement('div');
                    item.className = `skeleton ${className}`;
                    skeleton.appendChild(item);
                });
                element.replaceChildren(skeleton);
            });
            document.querySelectorAll('.tab-status').forEach(element => element.className = 'tab-status');
            document.querySelectorAll('[data-service-badge]').forEach(element => element.textContent = '—');
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 3;
            cell.textContent = @json(__('ui.loading_service'));
            row.appendChild(cell);
            document.getElementById('subdomainRows').replaceChildren(row);
        }

        function showReconFailure(message) {
            setExportLinks();
            document.querySelectorAll('[data-service-result]').forEach(element => {
                const notice = document.createElement('div');
                notice.className = 'service-error';
                notice.textContent = message;
                element.replaceChildren(notice);
            });
        }

        document.getElementById('lookupForm').addEventListener('submit', async function(e){
            e.preventDefault();
            if (this.dataset.busy === 'true') return;
            const target = document.getElementById('target').value.trim();
            if(!target) return;
            const button = this.querySelector('button');
            const errorBox = document.getElementById('error');
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            this.dataset.busy = 'true';
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            errorBox.textContent = '';
            resetReconResults();

            try {
                const {response: startResponse, data: startData} = await fetchJson(@json(route('recon.start')), {
                    method: 'POST',
                    headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN': csrf},
                    body: JSON.stringify({target})
                });
                if (!startResponse.ok) {
                    throw new Error(Object.values(startData.errors || {}).flat()[0] || startData.error || startData.message);
                }
                const historyId = startData.history_id;
                setExportLinks(historyId);

                const dnsPromise = fetchJson(@json(route('dns.lookup')), {
                    method: 'POST',
                    headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN': csrf},
                    body: JSON.stringify({target, history_id: historyId})
                }).then(({response, data}) => {
                    if (!response.ok) throw new Error(data.error || data.message || `HTTP ${response.status}`);
                    window.dispatchEvent(new CustomEvent('dns:records', {detail: {records: data.dns_records || [], status: data.dns_status || {}}}));
                    window.dispatchEvent(new CustomEvent('dns:results', {detail: data.enriched_ips || []}));
                    window.dispatchEvent(new CustomEvent('rdap:result', {detail: {summary: data.rdap_summary || null, error: data.rdap_error || null}}));
                });

                const services = @json($allowedReconServices);
                const servicePromises = services.map(service => fetchJson(`${@json(url('/recon/scan'))}/${service}`, {
                    method: 'POST',
                    headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN': csrf},
                    body: JSON.stringify({target, history_id: historyId})
                }).then(({response, data: payload}) => {
                    if (!response.ok) throw new Error(payload.error || payload.message || `HTTP ${response.status}`);
                    window.dispatchEvent(new CustomEvent('recon:result', {detail: {service, result: payload.result}}));
                }).catch(error => {
                    window.dispatchEvent(new CustomEvent('recon:result', {detail: {service, result: {status: 'error', error: error.message, data: {}}}}));
                }));

                await Promise.allSettled([dnsPromise, ...servicePromises]);
                window.dispatchEvent(new CustomEvent('recon:history-refresh'));
            } catch (error) {
                const message = error.message || @json(__('ui.connection_failed'));
                errorBox.textContent = message;
                showReconFailure(message);
            } finally {
                this.dataset.busy = 'false';
                button.disabled = false;
                button.removeAttribute('aria-busy');
            }

        });
    </script>
</body>
</html>
