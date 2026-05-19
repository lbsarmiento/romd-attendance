<?php

require_once 'config.php';
require_once 'includes/rmis_config.php';
require_once 'includes/rmis_repository.php';

requireAdmin();

$conn = getDBConnection();
ensureRmisTables($conn);

$app_settings = getAppSettings($conn);
$system_name = $app_settings['system_name'] ?? 'ROMD Attendance';
$page_title = 'RMIS Documents - ' . $system_name;
$current_page = 'rmis_monitor';
$show_back_btn = true;

$configReady = rmisConfigIsReady();
$documentTotal = rmisCountDocuments($conn);
$cursor = rmisGetSyncState($conn, 'cursor', ['next_page' => 1, 'total_pages' => null, 'completed' => true]);
$baseUrl = getRmisConfig()['base_url'];

$conn->close();

include 'includes/header.php';
?>
    <div class="container">
        <?php if (!$configReady): ?>
            <div class="message error" style="display:block;margin-bottom:16px;">
                Copy <code>includes/rmis_config.local.php.example</code> to
                <code>includes/rmis_config.local.php</code> and set your intranet email and password.
            </div>
        <?php endif; ?>

        <header class="rmis-page-header">
            <div>
                <h2>wRMIS Documents</h2>
                <p>Newest records appear first after <strong>Sync latest</strong> (wRMIS page 1).</p>
            </div>
            <div class="rmis-action-bar">
                <span class="rmis-badge" id="rmisDocTotalBadge"><?php echo (int) $documentTotal; ?> stored</span>
                <button type="button" class="rmis-icon-btn primary" id="rmisOpenSync" title="Sync from intranet" <?php echo $configReady ? '' : 'disabled'; ?>>
                    <span aria-hidden="true">↻</span> Sync
                </button>
                <button type="button" class="rmis-icon-btn" id="rmisOpenHistory" title="Sync history">
                    <span aria-hidden="true">☰</span> History
                </button>
                <button type="button" class="rmis-icon-btn" id="rmisOpenReport" title="Monitoring report (uses filters below)">
                    <span aria-hidden="true">📊</span> Report
                </button>
                <a class="rmis-icon-btn" href="<?php echo htmlspecialchars($baseUrl . '/RMIS'); ?>" target="_blank" rel="noopener" title="Open wRMIS on intranet">
                    <span aria-hidden="true">↗</span> wRMIS
                </a>
            </div>
        </header>

        <section class="card settings-section">
            <div class="rmis-docs-toolbar">
                <div class="form-group" style="flex:1 1 240px;">
                    <label for="rmisSearchInput">Search</label>
                    <input type="search" id="rmisSearchInput" placeholder="Subject, origin, number, series, type…" autocomplete="off">
                </div>
                <div class="form-group" style="flex:0 1 130px;">
                    <label for="rmisYearFilter">Year issued</label>
                    <select id="rmisYearFilter">
                        <option value="">All years</option>
                    </select>
                </div>
                <div class="form-group" style="flex:0 1 min(280px, 100%);">
                    <label for="rmisOriginFilter">Origin office</label>
                    <select id="rmisOriginFilter">
                        <option value="">All offices</option>
                    </select>
                </div>
                <div class="form-group" style="flex:0 1 200px;">
                    <label for="rmisTypeFilter">Document type</label>
                    <select id="rmisTypeFilter">
                        <option value="">All types</option>
                    </select>
                </div>
                <div class="form-group" style="flex:0 1 120px;">
                    <label for="rmisPerPage">Per page</label>
                    <select id="rmisPerPage">
                        <option value="10">10</option>
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <button type="button" class="btn btn-primary" id="rmisSearchBtn">Search</button>
                <button type="button" class="btn btn-secondary" id="rmisClearFilters">Clear</button>
            </div>
            <p class="rmis-sync-hint" style="margin:0 0 12px;">Search includes subject and other fields. Origin office dropdown filters by office only. Report uses the same filters.</p>

            <p class="rmis-docs-meta" id="rmisDocsMeta">Loading…</p>

            <div class="rmis-docs-table-wrap">
                <table class="rmis-docs-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Issued</th>
                            <th>Series</th>
                            <th>No.</th>
                            <th class="col-origin">Origin / Subject</th>
                            <th>Effectivity</th>
                            <th>Received</th>
                            <th>PDF</th>
                        </tr>
                    </thead>
                    <tbody id="rmisDocsBody">
                        <tr><td colspan="8">Loading documents…</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="rmis-pagination" id="rmisPagination" hidden>
                <button type="button" class="btn btn-secondary" id="rmisPrevPage" disabled>← Previous</button>
                <div class="rmis-pagination-pages" id="rmisPageNumbers"></div>
                <button type="button" class="btn btn-secondary" id="rmisNextPageBtn" disabled>Next →</button>
            </div>
        </section>
    </div>

    <!-- Sync modal -->
    <div id="rmisSyncModal" class="modal" style="display:none;" role="dialog" aria-labelledby="rmisSyncModalTitle" aria-modal="true">
        <div class="modal-content month-modal">
            <div class="modal-header">
                <div>
                    <span class="modal-kicker">wRMIS</span>
                    <h3 id="rmisSyncModalTitle">Sync controls</h3>
                </div>
                <span class="close" id="rmisCloseSync" role="button" tabindex="0" aria-label="Close">&times;</span>
            </div>
            <div class="modal-body">
                <p class="rmis-sync-hint">
                    <strong>Sync latest</strong> pulls wRMIS <em>page 1</em> (newest documents on top).
                    Existing rows are updated; new rows are added. Your table sorts by date received, so fresh items show first.
                </p>
                <p class="rmis-sync-hint">
                    Full catalog: use <strong>Continue full sync</strong> repeatedly, or <strong>Restart</strong> from page 1.
                    Progress: page <strong id="rmisNextPage"><?php echo (int) ($cursor['next_page'] ?? 1); ?></strong>
                    <?php if (!empty($cursor['total_pages'])): ?>
                        of <strong id="rmisTotalPages"><?php echo (int) $cursor['total_pages']; ?></strong>
                    <?php else: ?>
                        <strong id="rmisTotalPages" hidden></strong>
                    <?php endif; ?>
                </p>

                <div class="settings-actions" style="flex-wrap:wrap;gap:10px;">
                    <button type="button" class="btn btn-primary" id="rmisSyncIncremental" <?php echo $configReady ? '' : 'disabled'; ?>>
                        Sync latest (page 1)
                    </button>
                    <button type="button" class="btn btn-secondary" id="rmisSyncBatch" <?php echo $configReady ? '' : 'disabled'; ?>>
                        Continue full sync (10 pages)
                    </button>
                    <button type="button" class="btn btn-secondary" id="rmisSyncReset" <?php echo $configReady ? '' : 'disabled'; ?>>
                        Restart full sync
                    </button>
                </div>

                <pre id="rmisSyncStatus" style="margin-top:16px;padding:12px;background:#0f172a;color:#e2e8f0;border-radius:8px;white-space:pre-wrap;min-height:48px;">Ready.</pre>
            </div>
        </div>
    </div>

    <!-- History modal -->
    <div id="rmisHistoryModal" class="modal" style="display:none;" role="dialog" aria-labelledby="rmisHistoryModalTitle" aria-modal="true">
        <div class="modal-content month-modal results-modal-content">
            <div class="modal-header">
                <div>
                    <span class="modal-kicker">wRMIS</span>
                    <h3 id="rmisHistoryModalTitle">Recent sync runs</h3>
                </div>
                <span class="close" id="rmisCloseHistory" role="button" tabindex="0" aria-label="Close">&times;</span>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Started</th>
                                <th>Mode</th>
                                <th>Status</th>
                                <th>Pages</th>
                                <th>New</th>
                                <th>Updated</th>
                                <th>Error</th>
                            </tr>
                        </thead>
                        <tbody id="rmisSyncLogBody">
                            <tr><td colspan="7">Loading…</td></tr>
                        </tbody>
                    </table>
                </div>
                <p class="rmis-modal-note">After each sync, open this panel to confirm new or updated counts.</p>
            </div>
        </div>
    </div>

    <script>
        const rmisStatusEl = document.getElementById('rmisSyncStatus');
        const rmisDocTotalBadge = document.getElementById('rmisDocTotalBadge');
        const rmisNextPageEl = document.getElementById('rmisNextPage');

        function openModal(id) {
            const el = document.getElementById(id);
            if (el) el.style.display = 'block';
        }

        function closeModal(id) {
            const el = document.getElementById(id);
            if (el) el.style.display = 'none';
        }

        document.getElementById('rmisOpenSync')?.addEventListener('click', () => openModal('rmisSyncModal'));
        document.getElementById('rmisCloseSync')?.addEventListener('click', () => closeModal('rmisSyncModal'));
        document.getElementById('rmisOpenHistory')?.addEventListener('click', () => {
            openModal('rmisHistoryModal');
            loadSyncLogs();
        });
        document.getElementById('rmisCloseHistory')?.addEventListener('click', () => closeModal('rmisHistoryModal'));

        [document.getElementById('rmisSyncModal'), document.getElementById('rmisHistoryModal')].forEach(modal => {
            modal?.addEventListener('click', e => {
                if (e.target === modal) closeModal(modal.id);
            });
        });

        async function loadSyncLogs() {
            const body = document.getElementById('rmisSyncLogBody');
            if (!body) return;
            body.innerHTML = '<tr><td colspan="7">Loading…</td></tr>';
            try {
                const res = await fetch('rmis_sync_logs_api.php?limit=15');
                const data = await res.json();
                if (!data.ok) throw new Error('failed');

                if (rmisDocTotalBadge && typeof data.documents_total === 'number') {
                    rmisDocTotalBadge.textContent = data.documents_total + ' stored';
                }

                if (!data.logs || data.logs.length === 0) {
                    body.innerHTML = '<tr><td colspan="7">No sync runs yet.</td></tr>';
                    return;
                }

                body.innerHTML = data.logs.map(log => `<tr>
                    <td>${escapeHtml(log.started_at || '')}</td>
                    <td>${escapeHtml(log.mode || '')}</td>
                    <td>${escapeHtml(log.status || '')}</td>
                    <td>${Number(log.pages_fetched || 0)}</td>
                    <td>${Number(log.rows_inserted || 0)}</td>
                    <td>${Number(log.rows_updated || 0)}</td>
                    <td>${escapeHtml(log.error_message || '—')}</td>
                </tr>`).join('');
            } catch (e) {
                body.innerHTML = '<tr><td colspan="7">Could not load sync history.</td></tr>';
            }
        }

        async function runRmisSync(payload) {
            rmisStatusEl.textContent = 'Sync in progress...';
            const response = await fetch('rmis_sync_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await response.json();
            const lines = [
                data.ok ? 'OK' : 'FAILED',
                data.message || '',
                `Mode: ${data.mode || ''}`,
                `Pages fetched: ${data.pages_fetched ?? 0}`,
                `Rows parsed: ${data.rows_parsed ?? 0}`,
                `New: ${data.rows_inserted ?? 0}, Updated: ${data.rows_updated ?? 0}`,
                data.mode === 'incremental'
                    ? 'Latest page synced — check the table for newest items on top.'
                    : `Next page: ${data.next_page ?? 1}${data.total_pages ? ' of ' + data.total_pages : ''}`,
                data.completed ? 'Batch complete for this run.' : 'Run again to continue full sync.'
            ];
            rmisStatusEl.textContent = lines.filter(Boolean).join('\n');

            if (typeof data.documents_total === 'number' && rmisDocTotalBadge) {
                rmisDocTotalBadge.textContent = data.documents_total + ' stored';
            }
            if (typeof data.next_page === 'number' && rmisNextPageEl) {
                rmisNextPageEl.textContent = String(data.next_page);
            }

            if (!data.completed && payload.mode === 'full' && data.ok) {
                return runRmisSync({
                    mode: 'full',
                    start_page: data.next_page,
                    max_pages: payload.max_pages || 10
                });
            }

            if (data.ok) {
                loadRmisDocuments(1);
                loadSyncLogs();
            }
        }

        document.getElementById('rmisSyncIncremental')?.addEventListener('click', () => {
            runRmisSync({ mode: 'incremental', force_login: false });
        });

        document.getElementById('rmisSyncBatch')?.addEventListener('click', () => {
            runRmisSync({ mode: 'full', max_pages: 10, force_login: false });
        });

        document.getElementById('rmisSyncReset')?.addEventListener('click', () => {
            if (!confirm('Restart full sync from page 1?')) return;
            runRmisSync({ mode: 'full', max_pages: 10, reset: true, force_login: true });
        });

        const rmisDocsState = { page: 1, q: '', originOffice: '', yearIssued: '', documentType: '', perPage: 25, totalPages: 0 };

        function getRmisFilterParams() {
            const params = {
                page: String(rmisDocsState.page),
                per_page: String(rmisDocsState.perPage),
                year_issued: rmisDocsState.yearIssued,
                document_type: rmisDocsState.documentType
            };
            if (rmisDocsState.q) params.q = rmisDocsState.q;
            if (rmisDocsState.originOffice) params.origin_office = rmisDocsState.originOffice;
            return params;
        }

        function openRmisReport() {
            const params = new URLSearchParams({
                q: document.getElementById('rmisSearchInput')?.value.trim() || '',
                origin_office: document.getElementById('rmisOriginFilter')?.value || '',
                year_issued: document.getElementById('rmisYearFilter')?.value || '',
                document_type: document.getElementById('rmisTypeFilter')?.value || ''
            });
            window.open('rmis_report.php?' + params.toString(), '_blank', 'noopener');
        }

        document.getElementById('rmisOpenReport')?.addEventListener('click', openRmisReport);

        function escapeHtml(text) {
            const d = document.createElement('div');
            d.textContent = text == null ? '' : String(text);
            return d.innerHTML;
        }

        /** wRMIS links must not end with spaces or %20. */
        function cleanPdfHref(url) {
            if (!url) return '';
            let u = String(url).trim();
            u = u.replace(/(%20|\s)+$/gi, '');
            return u;
        }

        function buildPageNumbers(current, total) {
            if (total <= 1) return [1];
            const pages = new Set([1, total, current, current - 1, current + 1, current - 2, current + 2]);
            const list = [...pages].filter(p => p >= 1 && p <= total).sort((a, b) => a - b);
            const out = [];
            let prev = 0;
            for (const p of list) {
                if (p - prev > 1) out.push('…');
                out.push(p);
                prev = p;
            }
            return out;
        }

        function renderPagination(data) {
            const wrap = document.getElementById('rmisPagination');
            const nums = document.getElementById('rmisPageNumbers');
            const prev = document.getElementById('rmisPrevPage');
            const next = document.getElementById('rmisNextPageBtn');
            if (!wrap || !nums) return;

            rmisDocsState.totalPages = data.total_pages || 0;
            if (rmisDocsState.totalPages <= 1 && data.total === 0) {
                wrap.hidden = true;
                return;
            }
            wrap.hidden = false;
            prev.disabled = data.page <= 1;
            next.disabled = data.page >= rmisDocsState.totalPages;

            nums.innerHTML = '';
            for (const item of buildPageNumbers(data.page, rmisDocsState.totalPages)) {
                if (item === '…') {
                    const span = document.createElement('span');
                    span.className = 'ellipsis';
                    span.textContent = '…';
                    nums.appendChild(span);
                    continue;
                }
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = String(item);
                if (item === data.page) btn.classList.add('active');
                btn.addEventListener('click', () => loadRmisDocuments(item));
                nums.appendChild(btn);
            }
        }

        function renderDocumentsTable(data) {
            const body = document.getElementById('rmisDocsBody');
            const meta = document.getElementById('rmisDocsMeta');
            if (!body || !meta) return;

            const total = data.total || 0;
            const page = data.page || 1;
            const perPage = data.per_page || 25;
            const from = total === 0 ? 0 : (page - 1) * perPage + 1;
            const to = Math.min(page * perPage, total);

            const filterBits = [];
            if (rmisDocsState.q) filterBits.push('Search: ' + rmisDocsState.q);
            if (rmisDocsState.originOffice) filterBits.push('Office: ' + rmisDocsState.originOffice);
            if (rmisDocsState.yearIssued) filterBits.push('Year ' + rmisDocsState.yearIssued);
            if (rmisDocsState.documentType) filterBits.push(rmisDocsState.documentType);
            const filterNote = filterBits.length ? ' · ' + filterBits.join(' · ') : '';

            meta.textContent = total === 0
                ? 'No documents match filters. Use Sync to import from wRMIS.'
                : `Showing ${from}–${to} of ${total} (newest first)${filterNote}`;

            if (!data.rows || data.rows.length === 0) {
                body.innerHTML = '<tr><td colspan="8" class="rmis-docs-empty">No documents found.</td></tr>';
                renderPagination(data);
                return;
            }

            body.innerHTML = data.rows.map(row => {
                const pdf = cleanPdfHref(row.pdf_href || '');
                const pdfCell = pdf
                    ? `<a class="btn btn-secondary rmis-pdf-btn" target="_blank" rel="noopener" href="${escapeHtml(pdf)}">View PDF</a>`
                    : '—';
                return `<tr>
                    <td>${escapeHtml(row.document_type)}</td>
                    <td>${escapeHtml(row.date_issued || '')}</td>
                    <td>${escapeHtml(row.document_series)}</td>
                    <td>${escapeHtml(row.document_number)}</td>
                    <td class="col-origin"><strong>${escapeHtml(row.origin_office)}</strong><em>${escapeHtml(row.subject)}</em></td>
                    <td>${escapeHtml(row.effectivity || '—')}</td>
                    <td>${escapeHtml(row.date_received || '')}</td>
                    <td>${pdfCell}</td>
                </tr>`;
            }).join('');

            renderPagination(data);
        }

        async function loadRmisDocuments(page) {
            rmisDocsState.page = page || 1;
            rmisDocsState.q = document.getElementById('rmisSearchInput')?.value.trim() || '';
            rmisDocsState.originOffice = document.getElementById('rmisOriginFilter')?.value || '';
            rmisDocsState.yearIssued = document.getElementById('rmisYearFilter')?.value || '';
            rmisDocsState.documentType = document.getElementById('rmisTypeFilter')?.value || '';
            rmisDocsState.perPage = parseInt(document.getElementById('rmisPerPage')?.value || '25', 10);

            const body = document.getElementById('rmisDocsBody');
            if (body) body.innerHTML = '<tr><td colspan="8">Loading…</td></tr>';

            const params = new URLSearchParams(getRmisFilterParams());

            try {
                const res = await fetch('rmis_documents_api.php?' + params.toString());
                const data = await res.json();
                if (!data.ok) throw new Error('Failed to load documents');

                const typeSelect = document.getElementById('rmisTypeFilter');
                if (typeSelect && data.document_types && typeSelect.options.length <= 1) {
                    const current = rmisDocsState.documentType;
                    data.document_types.forEach(t => {
                        const opt = document.createElement('option');
                        opt.value = t;
                        opt.textContent = t;
                        if (t === current) opt.selected = true;
                        typeSelect.appendChild(opt);
                    });
                }

                const yearSelect = document.getElementById('rmisYearFilter');
                if (yearSelect && data.issued_years && yearSelect.options.length <= 1) {
                    const currentYear = rmisDocsState.yearIssued;
                    data.issued_years.forEach(y => {
                        const opt = document.createElement('option');
                        opt.value = String(y);
                        opt.textContent = String(y);
                        if (String(y) === currentYear) opt.selected = true;
                        yearSelect.appendChild(opt);
                    });
                }

                const originSelect = document.getElementById('rmisOriginFilter');
                if (originSelect && data.origin_offices && originSelect.options.length <= 1) {
                    const currentOffice = rmisDocsState.originOffice;
                    data.origin_offices.forEach(office => {
                        const opt = document.createElement('option');
                        opt.value = office;
                        opt.textContent = office;
                        if (office === currentOffice) opt.selected = true;
                        originSelect.appendChild(opt);
                    });
                }

                renderDocumentsTable(data);
            } catch (e) {
                if (body) body.innerHTML = '<tr><td colspan="8">Could not load documents.</td></tr>';
            }
        }

        document.getElementById('rmisSearchBtn')?.addEventListener('click', () => loadRmisDocuments(1));
        document.getElementById('rmisSearchInput')?.addEventListener('keydown', e => {
            if (e.key === 'Enter') { e.preventDefault(); loadRmisDocuments(1); }
        });
        document.getElementById('rmisClearFilters')?.addEventListener('click', () => {
            const search = document.getElementById('rmisSearchInput');
            const origin = document.getElementById('rmisOriginFilter');
            const year = document.getElementById('rmisYearFilter');
            const type = document.getElementById('rmisTypeFilter');
            if (search) search.value = '';
            if (origin) origin.value = '';
            if (year) year.value = '';
            if (type) type.value = '';
            loadRmisDocuments(1);
        });
        document.getElementById('rmisOriginFilter')?.addEventListener('change', () => loadRmisDocuments(1));
        document.getElementById('rmisYearFilter')?.addEventListener('change', () => loadRmisDocuments(1));
        document.getElementById('rmisTypeFilter')?.addEventListener('change', () => loadRmisDocuments(1));
        document.getElementById('rmisPerPage')?.addEventListener('change', () => loadRmisDocuments(1));
        document.getElementById('rmisPrevPage')?.addEventListener('click', () => {
            if (rmisDocsState.page > 1) loadRmisDocuments(rmisDocsState.page - 1);
        });
        document.getElementById('rmisNextPageBtn')?.addEventListener('click', () => {
            if (rmisDocsState.page < rmisDocsState.totalPages) loadRmisDocuments(rmisDocsState.page + 1);
        });

        loadRmisDocuments(1);
    </script>
<?php
include 'includes/footer.php';
