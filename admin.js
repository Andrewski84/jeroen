// Admin UI script: handles tabs, uploads, modals, and small UX helpers
// This file aims to keep admin interactions snappy without hard reloads.
// Where possible, it updates the DOM in-place and restores context via URL hash.

document.addEventListener('DOMContentLoaded', function () {
    
    // Forceer de upload container om verborgen te zijn bij het laden van de pagina
    document.getElementById('upload-progress-container')?.classList.add('hidden');

    // --- Expliciete click handlers voor Hero/Bio image pickers ---
    document.getElementById('hero_image_container')?.addEventListener('click', () => {
        document.getElementById('hero_image_input')?.click();
    });
    document.getElementById('hero_image_input')?.addEventListener('change', e => {
        if (e.target.files.length > 0) {
            queueFileUpload(e.target.files[0], { target: 'hero' });
            e.target.value = '';
        }
    });

    // --- Tab Navigation ---
    const tabButtons = document.querySelectorAll('.admin-tab-button');
    const tabPanels = document.querySelectorAll('.admin-tab-panel');

    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            const tabId = button.getAttribute('data-tab');
            
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabPanels.forEach(panel => panel.classList.remove('active'));
            button.classList.add('active');
            document.getElementById(tabId).classList.add('active');
            
            const newHash = '#' + tabId;
            if(history.pushState) {
                history.pushState(null, null, newHash);
            } else {
                window.location.hash = newHash;
            }
        });
    });

    function activateTabFromHash() {
        const fullHash = window.location.hash.substring(1);
        if (!fullHash) return;

        const [tabId, ...params] = fullHash.split('&');
        
        const targetButton = document.querySelector(`.admin-tab-button[data-tab="${tabId}"]`);
        if (targetButton) {
            targetButton.click();

            const paramMap = params.reduce((acc, param) => {
                const [key, value] = param.split('=');
                if (key && value) acc[key] = decodeURIComponent(value);
                return acc;
            }, {});

            if (paramMap.theme) {
                setTimeout(() => {
                    const row = document.querySelector(`#portfolio-sortable-list tr[data-theme="${paramMap.theme}"]`);
                    if (row) row.click();
                }, 100);
            } else if (paramMap.slug) {
                setTimeout(() => {
                    const row = document.querySelector(`.admin-table tr[data-slug="${paramMap.slug}"]`);
                     if (row) row.click();
                }, 100);
            }
        }
    }
    activateTabFromHash();

    // --- Detail View for Portfolio & Galleries ---
    function setupDetailView(rowSelector, cardSelector, dataAttribute) {
        const tableRows = document.querySelectorAll(rowSelector);
        const detailCards = document.querySelectorAll(cardSelector);

        tableRows.forEach(row => {
            row.addEventListener('click', (e) => {
                if (e.target.closest('button, form, a, .drag-handle')) return;
                const detailId = row.getAttribute(dataAttribute);
                const targetCard = document.querySelector(`${cardSelector}[${dataAttribute}="${detailId}"]`);

                if (row.classList.contains('active-row')) {
                    targetCard.classList.add('hidden');
                    row.classList.remove('active-row');
                } else {
                    detailCards.forEach(card => card.classList.add('hidden'));
                    tableRows.forEach(r => r.classList.remove('active-row'));
                    if (targetCard) {
                        targetCard.classList.remove('hidden');
                        row.classList.add('active-row');
                        targetCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                }
            });
        });
    }

    setupDetailView('#portfolio-sortable-list tr', '.theme-card', 'data-theme');
    setupDetailView('.admin-table tbody tr', '.gallery-card', 'data-slug');
    setupDetailView('#practice-table tbody tr', '.practice-card-editor', 'data-slug');

    // --- Upload Queue System ---
    const uploadQueue = [];
    let activeUploads = 0;
    const MAX_CONCURRENT_UPLOADS = 3;
    const progressContainer = document.getElementById('upload-progress-container');
    const progressList = document.getElementById('upload-progress-list');
    const progressSummary = document.getElementById('upload-progress-summary');
    const progressClearBtn = document.getElementById('upload-clear-btn');
    const minimizeBtn = document.getElementById('upload-minimize-btn');

    function updateProgressSummary() {
        if (!progressSummary) return;
        const counts = uploadQueue.reduce((acc, item) => {
            acc[item.status] = (acc[item.status] || 0) + 1;
            return acc;
        }, { success: 0, uploading: 0, queued: 0, error: 0 });

        progressSummary.textContent = `${counts.success} voltooid, ${counts.uploading} bezig, ${counts.queued} in wachtrij`;
        if (counts.error > 0) progressSummary.textContent += `, ${counts.error} mislukt`;
        if (progressContainer) progressContainer.classList.toggle('hidden', uploadQueue.length === 0);
    }

    if (progressClearBtn) {
        progressClearBtn.addEventListener('click', () => {
            // Simply hide the container and clear everything immediately
            progressContainer.classList.add('hidden');
            progressList.innerHTML = '';
            uploadQueue.length = 0;
        });
    }

    if (minimizeBtn && progressContainer) {
        minimizeBtn.addEventListener('click', () => {
            progressContainer.classList.toggle('is-minimized');
            minimizeBtn.innerHTML = progressContainer.classList.contains('is-minimized') ? '&#9633;' : '&minus;';
            minimizeBtn.title = progressContainer.classList.contains('is-minimized') ? 'Maximaliseren' : 'Minimaliseren';
        });
    }

    function processUploadQueue() {
        while (activeUploads < MAX_CONCURRENT_UPLOADS) {
            const nextTask = uploadQueue.find(item => item.status === 'queued');
            if (!nextTask) break;
            startUpload(nextTask);
        }
    }

    // Create and append a new portfolio photo card to the DOM
    function appendPortfolioPhoto(theme, index, path) {
        const list = document.querySelector(`.photo-list[data-theme="${theme}"]`);
        if (!list) return;
        const el = document.createElement('div');
        el.className = 'photo-list-item';
        el.dataset.id = index;
        el.dataset.title = '';
        el.dataset.description = '';
        el.dataset.alt = '';
        el.dataset.featured = 'false';
        el.innerHTML = `
            <img src="${path}" class="admin-thumb" alt="">
            <div class="photo-list-overlay">
                <button class="btn-icon" onclick="editPhoto(this, '${theme}', ${index})" title="Bewerk">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5"><path d="M5.433 13.917l1.262-3.155A4 4 0 017.58 9.42l6.92-6.918a2.121 2.121 0 013 3l-6.92 6.918c-.383.383-.84.685-1.343.886l-3.154 1.262a.5.5 0 01-.65-.65z" /><path d="M3.5 5.75c0-.69.56-1.25 1.25-1.25H10A.75.75 0 0010 3H4.75A2.75 2.75 0 002 5.75v9.5A2.75 2.75 0 004.75 18h9.5A2.75 2.75 0 0017 15.25V10a.75.75 0 00-1.5 0v5.25c0 .69-.56 1.25-1.25 1.25h-9.5c-.69 0-1.25-.56-1.25-1.25v-9.5z" /></svg>
                </button>
                <form action="save.php" method="POST" class="delete-photo-form">
                    <input type="hidden" name="action" value="delete_photo">
                    <input type="hidden" name="theme_name" value="${theme}">
                    <input type="hidden" name="photo_index" value="${index}">
                    <button type="submit" class="btn-icon btn-icon-danger" title="Verwijder">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.58.22-2.365.468a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.573l.842-10.518.149.022a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193v-.443A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd" /></svg>
                    </button>
                </form>
            </div>`;
        list.appendChild(el);
        // Increment count in table row
        const row = document.querySelector(`#portfolio-sortable-list tr[data-theme="${theme}"]`);
        if (row) {
            const cells = row.querySelectorAll('td');
            const countCell = cells[cells.length - 1];
            if (countCell) countCell.textContent = (parseInt(countCell.textContent, 10) + 1).toString();
        }
    }

    // Create and append a new gallery photo card
        // Create and append a new gallery photo card
    function appendGalleryPhoto(slug, index, path) {
        let list = document.querySelector(`.gallery-photo-list[data-gallery="${slug}"]`);
        if (!list) {
            // Create the list container on-the-fly when gallery was initially empty
            const dz = document.querySelector(`.dropzone[data-target="gallery"][data-slug="${slug}"]`);
            if (dz) {
                // Remove empty message if present
                const emptyMsg = dz.parentElement?.querySelector('p.text-slate-500');
                if (emptyMsg) emptyMsg.remove();
                list = document.createElement('div');
                list.className = 'gallery-photo-list';
                list.setAttribute('data-gallery', slug);
                dz.parentElement?.appendChild(list);
                // Initialize Sortable on newly created list
                if (typeof Sortable !== 'undefined') {
                    new Sortable(list, {
                        animation: 150,
                        ghostClass: 'sortable-ghost',
                        onEnd: (evt) => {
                            const slugVal = evt.target.dataset.gallery;
                            if (!slugVal) return;
                            const order = Array.from(evt.target.querySelectorAll('[data-id]')).map(item => item.dataset.id);
                            const formData = new FormData();
                            formData.append('action', 'update_gallery_photo_order');
                            formData.append('slug', slugVal);
                            order.forEach(i => formData.append('order[]', i));
                            fetch('save.php', { method: 'POST', body: formData })
                                .then(r => r.json()).then(d => { if (d.status !== 'success') showToast('Volgorde opslaan mislukt', false); });
                        }
                    });
                }
            }
        }
        if (!list) return;
        const el = document.createElement('div');
        el.className = 'gallery-photo-list-item';
        el.dataset.id = index;
        el.innerHTML = `
            <img src="${path}" class="admin-thumb">
            <div class="photo-list-overlay items-center justify-center">
                <form action="save.php" method="POST" class="delete-photo-form">
                    <input type="hidden" name="action" value="delete_gallery_photo">
                    <input type="hidden" name="gallery_slug" value="${slug}">
                    <input type="hidden" name="photo_index" value="${index}">
                    <button type="submit" class="btn-icon btn-icon-danger" title="Verwijder">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.58.22-2.365.468a.75.75 0 10.23 1.482l.149-.022.841 10.518A2.75 2.75 0 007.596 19h4.807a2.75 2.75 0 002.742-2.573l.842-10.518.149.022a.75.75 0 00.23-1.482A41.03 41.03 0 0014 4.193v-.443A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 00-1.5.06l.3 7.5a.75.75 0 101.5-.06l-.3-7.5zm4.34.06a.75.75 0 10-1.5-.06l-.3 7.5a.75.75 0 101.5.06l.3-7.5z" clip-rule="evenodd" /></svg>
                    </button>
                </form>
            </div>`;
        list.appendChild(el);
    }
function startUpload(task) {
        task.status = 'uploading';
        activeUploads++;
        task.progress.setStatus('uploading');
        updateProgressSummary();

        const formData = new FormData();
        formData.append('file', task.file);
        formData.append('target', task.meta.target);
        if (task.meta.slug) formData.append('slug', task.meta.slug);
        if (task.meta.theme) formData.append('theme', task.meta.theme);
        if (task.meta.member_id) formData.append('member_id', task.meta.member_id);

        fetch('upload_ajax.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(res => {
                activeUploads--;
                if (res.status === 'success') {
                    task.status = 'success';
                    task.progress.setStatus('success');
                    // Reflect the change immediately in the UI without a full reload
                    if (task.meta.target === 'portfolio') {
                        appendPortfolioPhoto(task.meta.theme, res.index, res.path);
                    } else if (task.meta.target === 'gallery') {
                        appendGalleryPhoto(task.meta.slug, res.index, res.path);
                    } else if (task.meta.target === 'hero') {
                        const img = document.querySelector('#hero_image_container img');
                        if (img) img.src = res.path;
                    } else if (task.meta.target === 'bio') {
                        const img = document.querySelector('#bio_image_container img');
                        if (img) img.src = res.path;
                    } else if (task.meta.target === 'team') {
                        // Refresh the image within the nearest card
                        const dz = document.querySelector(`.dropzone[data-target="team"][data-member-id="${task.meta.member_id}"]`);
                        if (dz) { const img = dz.parentElement.querySelector('img'); if (img) img.src = res.path; }
                    } else if (task.meta.target === 'links_hero') {
                        const dz = document.querySelector('.dropzone[data-target="links_hero"]');
                        if (dz) { const img = dz.parentElement.querySelector('img'); if (img) img.src = res.path; }
                    }
                } else {
                    task.status = 'error';
                    task.progress.setStatus('error');
                }
            })
            .catch(() => {
                activeUploads--;
                task.status = 'error';
                task.progress.setStatus('error');
            })
            .finally(() => {
                task.progress.setProgress(100);
                updateProgressSummary();
                const remaining = uploadQueue.filter(item => item.status === 'queued' || item.status === 'uploading').length;
                if (remaining === 0) {
                    const hasErrors = uploadQueue.some(item => item.status === 'error');
                    showToast(hasErrors ? 'Uploads voltooid (met fouten).' : 'Uploads voltooid.', !hasErrors);
                    // Reset the progress UI but keep the page context intact
                    if (progressContainer) progressContainer.classList.add('hidden');
                    if (progressList) progressList.innerHTML = '';
                    uploadQueue.length = 0;
                } else {
                    processUploadQueue();
                }
            });
    }

    function queueFileUpload(file, meta) {
        const progress = createProgressBar(file.name);
        uploadQueue.push({ file, meta, progress, status: 'queued' });
        updateProgressSummary();
        processUploadQueue();
    }

    function createProgressBar(fileName) {
        if (!progressList) return null;
        const item = document.createElement('div');
        item.className = 'upload-item';
        item.innerHTML = `<div class="upload-item-name">${fileName}</div><div class="progress-bar-bg"><div class="progress-bar status-queued"></div></div>`;
        progressList.appendChild(item);
        const bar = item.querySelector('.progress-bar');
        return {
            element: item,
            setProgress: (percent) => { bar.style.width = percent + '%'; },
            setStatus: (status) => {
                bar.className = `progress-bar status-${status}`;
                if (status === 'success' || status === 'error') bar.style.width = '100%';
            }
        };
    }

    document.querySelectorAll('.dropzone').forEach(dz => {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eName => dz.addEventListener(eName, e => { e.preventDefault(); e.stopPropagation(); }));
        ['dragenter', 'dragover'].forEach(eName => dz.addEventListener(eName, () => dz.classList.add('is-dragover')));
        ['dragleave', 'drop'].forEach(eName => dz.addEventListener(eName, () => dz.classList.remove('is-dragover')));
        dz.addEventListener('drop', e => {
            if (e.dataTransfer.files) Array.from(e.dataTransfer.files).forEach(file => queueFileUpload(file, { target: dz.dataset.target, slug: dz.dataset.slug, theme: dz.dataset.theme, member_id: dz.dataset.memberId }));
        });
        
        // Make portfolio/gallery/pricing dropzones clickable
        if (dz.dataset.target === 'portfolio' || dz.dataset.target === 'gallery' || dz.dataset.target === 'pricing' || dz.dataset.target === 'team' || dz.dataset.target === 'links_hero') {
            dz.addEventListener('click', () => {
                const input = document.createElement('input');
                input.type = 'file';
                input.multiple = dz.dataset.target !== 'pricing'; // allow multiple uploads except pricing
                input.accept = 'image/*';
                input.onchange = e => {
                     if (e.target.files) Array.from(e.target.files).forEach(file => queueFileUpload(file, { target: dz.dataset.target, slug: dz.dataset.slug, theme: dz.dataset.theme, member_id: dz.dataset.memberId }));
                };
                input.click();
            });
        }
    });

    // --- AJAXify forms (subtle save, no full reload) ---
    function syncRichtext(form){ try { form.querySelectorAll('textarea.richtext').forEach(t => { if (t._ck && typeof t._ck.getData === 'function') { t.value = t._ck.getData(); } }); } catch(e){} }
    function refreshActiveTab() {
        const activeBtn = document.querySelector('.admin-tab-button.active');
        if (!activeBtn) return;
        const tabId = activeBtn.getAttribute('data-tab');
        fetch(window.location.href, { method: 'GET', cache: 'no-store' })
          .then(r => r.text())
          .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const fresh = doc.getElementById(tabId);
            const current = document.getElementById(tabId);
            if (fresh && current) {
                current.innerHTML = fresh.innerHTML;
                // Rebind
                document.querySelectorAll('textarea.richtext').forEach(window.initRichtext);
                ajaxifyForms();
                // Rebind dropzones
                document.querySelectorAll('.dropzone').forEach(dz => {
                    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eName => dz.addEventListener(eName, e => { e.preventDefault(); e.stopPropagation(); }));
                    ['dragenter', 'dragover'].forEach(eName => dz.addEventListener(eName, () => dz.classList.add('is-dragover')));
                    ['dragleave', 'drop'].forEach(eName => dz.addEventListener(eName, () => dz.classList.remove('is-dragover')));
                });
                // Normalize pinned toggle buttons to use the global toggler and label
                if (tabId === 'tab-pinned') {
                  document.querySelectorAll('#pinned-list .btn.btn-secondary.btn-sm').forEach(btn => {
                    const hasInline = btn.getAttribute('onclick');
                    if (hasInline && hasInline.indexOf('pinned_scope_all') !== -1 || (btn.textContent||'').match(/Selectie wissen/)) {
                      btn.removeAttribute('onclick');
                      btn.textContent = "Alle pagina's";
                      btn.addEventListener('click', () => window.togglePinnedAll(btn));
                    }
                  });
                }
                // Re-init sortables for the refreshed panel
                try {
                    const practiceTable = document.querySelector('#practice-table tbody');
                    if (practiceTable && typeof Sortable !== 'undefined') {
                        new Sortable(practiceTable, {
                            animation: 150,
                            handle: '.drag-handle',
                            onEnd: () => {
                                const order = Array.from(practiceTable.querySelectorAll('tr[data-slug]')).map(tr => tr.dataset.slug);
                                const fd = new FormData(); fd.append('action','reorder_practice_pages'); order.forEach(s => fd.append('order[]', s));
                                fd.append('ajax','1');
                                fetch('save.php', { method: 'POST', body: fd });
                            }
                        });
                    }
                    const pinnedList = document.getElementById('pinned-list');
                    if (pinnedList && typeof Sortable !== 'undefined') {
                        new Sortable(pinnedList, {
                            animation: 150,
                            handle: '.drag-handle',
                            onEnd: () => {
                                const order = Array.from(pinnedList.children).map(b => b.querySelector('input[name="pinned_id[]"]').value);
                                const fd = new FormData(); fd.append('action','reorder_pinned'); order.forEach(id => fd.append('order[]', id));
                                fd.append('ajax','1');
                                fetch('save.php', { method: 'POST', body: fd });
                            }
                        });
                    }
                    const linksList = document.getElementById('links-list');
                    if (linksList && typeof Sortable !== 'undefined') {
                        new Sortable(linksList, {
                            animation: 150,
                            handle: '.drag-handle',
                            onEnd: () => {
                                const order = Array.from(linksList.children).map(ch => ch.dataset.id).filter(Boolean);
                                const fd = new FormData(); fd.append('action','reorder_links'); order.forEach(i => fd.append('order[]', i));
                                fd.append('ajax','1');
                                fetch('save.php', { method: 'POST', body: fd });
                            }
                        });
                    }
                } catch(e){}
            }
          }).catch(()=>{});
    }

    function ajaxifyForms() {
        const allowedActions = new Set(['add_team_member','update_team_member','delete_team_member','save_practice_page','delete_practice_page','save_links','save_pinned','save_settings']);
        // Some actions already update the DOM (team forms) and do not require a panel refresh
        const actionsNeedingRefresh = new Set(['save_practice_page','delete_practice_page','save_links','save_pinned','save_settings']);
        const forms = document.querySelectorAll('.admin-content form');
        forms.forEach(form => {
            if (form.dataset.ajaxBound === '1') return;
            // Skip file-uploading forms
            if (form.querySelector('input[type="file"]')) return;
            form.addEventListener('submit', (e) => {
                // Allow normal behavior if explicitly opted-out
                if (form.classList.contains('no-ajax')) return;
                const actInput = form.querySelector('input[name="action"]');
                const actionName = actInput ? String(actInput.value || '') : '';
                if (!allowedActions.has(actionName)) return;
                e.preventDefault();
                syncRichtext(form);
                const fd = new FormData(form);
                fd.append('ajax', '1');
                fetch('save.php', { method: 'POST', body: fd })
                  .then(r => r.text()).then(txt => { let d=null; try{ d=JSON.parse(txt);}catch(e){} if(!d||d.status!=='success'){ showToast(d&&d.message?d.message:'Opslaan mislukt', false); return; }
                    showToast('Opgeslagen', true);
                    // Post-save UX tweaks for specific actions
                    const action = actionName;
                    if (action === 'add_team_member' && d.member) {
                        appendTeamMemberCard(d.member);
                        form.reset();
                    } else if (action === 'delete_team_member' && d.id) {
                        const card = document.querySelector(`.dropzone[data-target="team"][data-member-id="${d.id}"]`)?.closest('.border');
                        card?.remove();
                    } else if (action === 'save_practice_page' && d.slug) {
                        ensurePracticeRow(d.slug, d.title || d.slug);
                    } else if (action === 'delete_practice_page' && d.slug) {
                        document.querySelector(`#practice-table tr[data-slug="${d.slug}"]`)?.remove();
                        document.querySelector(`.practice-card-editor[data-slug="${d.slug}"]`)?.remove();
                    }
                    if (actionsNeedingRefresh.has(action)) {
                        // Only expensive refresh for panels that are not updated inline
                        setTimeout(refreshActiveTab, 300);
                    }
                  }).catch(() => showToast('Netwerkfout', false));
            });
            form.dataset.ajaxBound = '1';
        });
    }
    ajaxifyForms();

    function ensurePracticeRow(slug, title) {
        const row = document.querySelector(`#practice-table tr[data-slug="${slug}"]`);
        if (row) return;
        const tbody = document.querySelector('#practice-table tbody');
        if (!tbody) return;
        const tr = document.createElement('tr');
        tr.setAttribute('data-slug', slug);
        tr.innerHTML = `<td></td><td>${title}</td><td>0</td><td><form action="save.php" method="POST" onsubmit="return confirm('Pagina verwijderen?');"><input type="hidden" name="action" value="delete_practice_page"><input type="hidden" name="slug" value="${slug}"><button type="submit" class="btn btn-danger">Verwijder</button></form>`;
        tbody.appendChild(tr);
    }

    function appendTeamMemberCard(m) {
        // Append a simplified card after the add form
        const container = document.querySelector('#tab-team .card-body');
        if (!container) return;
        const wrap = document.createElement('div');
        wrap.className = 'border border-slate-200 rounded-lg p-4';
        wrap.innerHTML = `
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-center">
                <div>
                    <div class="relative group w-full aspect-[4/3] bg-slate-100 rounded-md overflow-hidden">
                        <img src="" alt="" class="w-full h-full object-cover">
                        <div class="dropzone absolute inset-0" data-target="team" data-member-id="${m.id}"><span>Sleep foto of klik</span></div>
                    </div>
                </div>
                <div class="sm:col-span-2">
                    <form action="save.php" method="POST" class="space-y-2">
                        <input type="hidden" name="action" value="update_team_member">
                        <input type="hidden" name="id" value="${m.id}">
                        <label class="form-label">Naam</label>
                        <input type="text" name="name" class="form-input" value="${m.name || ''}">
                        <label class="form-label">Functie</label>
                        <input type="text" name="role" class="form-input" value="${m.role || ''}">
                        <label class="form-label">Afspraak URL</label>
                        <input type="text" name="appointment_url" class="form-input" value="${m.appointment_url || ''}">
                        <div class="flex gap-2">
                            <button type="submit" class="btn btn-secondary">Opslaan</button>
                            <button type="button" class="btn btn-danger" onclick="(function(f){ if(!confirm('Verwijder dit teamlid?')) return; const fd=new FormData(); fd.append('action','delete_team_member'); fd.append('id','${m.id}'); fd.append('ajax','1'); fetch('save.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{ if(d&&d.status==='success'){ f.closest('.border').remove(); showToast('Verwijderd',true);} else { showToast('Verwijderen mislukt',false);} }); })(this)">Verwijder</button>
                            <form action="save.php" method="POST" class="hidden">
                                <input type="hidden" name="action" value="delete_team_member">
                                <input type="hidden" name="id" value="${m.id}">
                            </form>
                        </div>
                    </form>
                </div>
            </div>`;
        container.appendChild(wrap);
        ajaxifyForms();
        // Bind minimal dropzone behavior to the new team dropzone
        const dz = wrap.querySelector('.dropzone[data-target="team"][data-member-id]');
        if (dz) {
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eName => dz.addEventListener(eName, e => { e.preventDefault(); e.stopPropagation(); }));
            ['dragenter', 'dragover'].forEach(eName => dz.addEventListener(eName, () => dz.classList.add('is-dragover')));
            ['dragleave', 'drop'].forEach(eName => dz.addEventListener(eName, () => dz.classList.remove('is-dragover')));
            dz.addEventListener('drop', e => {
                if (e.dataTransfer.files) Array.from(e.dataTransfer.files).forEach(file => queueFileUpload(file, { target: dz.dataset.target, member_id: dz.dataset.memberId }));
            });
            dz.addEventListener('click', () => {
                const input = document.createElement('input'); input.type = 'file'; input.accept = 'image/*';
                input.onchange = e => { if (e.target.files) Array.from(e.target.files).forEach(file => queueFileUpload(file, { target: dz.dataset.target, member_id: dz.dataset.memberId })); };
                input.click();
            });
        }
    }

    // --- Reordering (SortableJS) ---
    try {
        const practiceTable = document.querySelector('#practice-table tbody');
        if (practiceTable && typeof Sortable !== 'undefined') {
            new Sortable(practiceTable, {
                animation: 150,
                handle: '.drag-handle',
                onEnd: () => {
                    const order = Array.from(practiceTable.querySelectorAll('tr[data-slug]')).map(tr => tr.dataset.slug);
                    const fd = new FormData(); fd.append('action','reorder_practice_pages'); order.forEach(s => fd.append('order[]', s));
                    fd.append('ajax','1');
                    fetch('save.php', { method: 'POST', body: fd });
                }
            });
        }
        const pinnedList = document.getElementById('pinned-list');
        if (pinnedList && typeof Sortable !== 'undefined') {
            new Sortable(pinnedList, {
                animation: 150,
                handle: '.drag-handle',
                onEnd: () => {
                    const order = Array.from(pinnedList.children).map(b => b.querySelector('input[name="pinned_id[]"]').value);
                    const fd = new FormData(); fd.append('action','reorder_pinned'); order.forEach(id => fd.append('order[]', id));
                    fd.append('ajax','1');
                    fetch('save.php', { method: 'POST', body: fd });
                }
            });
        }
        const linksList = document.getElementById('links-list');
        if (linksList && typeof Sortable !== 'undefined') {
            // Ensure children have a data-id (provided by server)
            new Sortable(linksList, {
                animation: 150,
                handle: '.drag-handle',
                onEnd: () => {
                    const order = Array.from(linksList.children).map(ch => ch.dataset.id).filter(Boolean);
                    const fd = new FormData(); fd.append('action','reorder_links'); order.forEach(i => fd.append('order[]', i));
                    fd.append('ajax','1');
                    fetch('save.php', { method: 'POST', body: fd });
                }
            });
        }
    } catch (e) {}
    // --- Global Pop-up System ---
    const toastPopup = document.getElementById('toast-popup');
    const confirmModal = document.getElementById('confirm-modal');
    
    window.showToast = (message, isSuccess = true) => {
        if (!toastPopup) return;
        toastPopup.textContent = message;
        toastPopup.style.backgroundColor = isSuccess ? 'var(--btn-success-bg)' : 'var(--btn-danger-bg)';
        toastPopup.classList.add('show');
        setTimeout(() => toastPopup.classList.remove('show'), 3000);
    };

    window.showConfirm = (message, onConfirm) => {
        if (!confirmModal) return;
        confirmModal.querySelector('#confirm-text').textContent = message;
        confirmModal.classList.remove('hidden');
        const yesBtn = confirmModal.querySelector('#confirm-yes');
        const noBtn = confirmModal.querySelector('#confirm-no');
        const confirmHandler = () => { onConfirm(); cleanup(); };
        const cancelHandler = () => cleanup();
        function cleanup() {
            confirmModal.classList.add('hidden');
            yesBtn.removeEventListener('click', confirmHandler);
            noBtn.removeEventListener('click', cancelHandler);
        }
        yesBtn.addEventListener('click', confirmHandler);
        noBtn.addEventListener('click', cancelHandler);
    };

    // Intercept deletes via event delegation so newly appended items work too
    document.addEventListener('submit', e => {
        const form = e.target.closest('.delete-form, .delete-photo-form');
        if (!form) return;
        e.preventDefault();
        showConfirm('Weet je zeker dat je dit wilt verwijderen?', () => form.submit());
    });

    // --- 'Edit Photo' Modal ---
    const editModal = document.getElementById('editModal');
    const editForm = document.getElementById('editForm');
    window.editPhoto = (buttonElement, themeName, photoIndex) => {
        const photoContainer = buttonElement.closest('[data-id]');
        document.getElementById('edit_theme_name').value = themeName;
        document.getElementById('edit_photo_index').value = photoIndex;
        document.getElementById('edit_title').value = photoContainer.dataset.title;
        document.getElementById('edit_description').value = photoContainer.dataset.description;
        document.getElementById('edit_alt').value = photoContainer.dataset.alt || '';
        document.getElementById('edit_featured').checked = photoContainer.dataset.featured === 'true';
        editModal.classList.remove('hidden');
    };
    window.closeModal = () => editModal.classList.add('hidden');

    if (editForm) {
        editForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            fetch('save.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    // Update the current photo item without a full reload
                    const themeName = formData.get('theme_name');
                    const idx = parseInt(formData.get('photo_index'), 10);
                    const container = document.querySelector(`.photo-list[data-theme="${themeName}"] .photo-list-item[data-id="${idx}"]`);
                    if (container) {
                        container.dataset.title = formData.get('title') || '';
                        container.dataset.description = formData.get('description') || '';
                        container.dataset.alt = formData.get('alt') || '';
                        const isFeatured = !!formData.get('featured');
                        container.dataset.featured = isFeatured ? 'true' : 'false';
                        // Toggle featured star icon
                        const star = container.querySelector('.featured-star');
                        if (isFeatured && !star) {
                            const svg = document.createElement('div');
                            svg.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="featured-star"><path fill-rule="evenodd" d="M10.868 2.884c.321-.662 1.215-.662 1.536 0l1.681 3.462 3.818.554c.729.106 1.022.992.494 1.506l-2.764 2.693.654 3.802c.124.723-.635 1.27-1.282.944l-3.415-1.795-3.415 1.795c-.647.326-1.406-.221-1.282-.944l.654-3.802-2.764-2.693c-.528-.514-.235-1.399.494-1.506l3.818-.554 1.681-3.462z" clip-rule="evenodd" /></svg>';
                            container.prepend(svg.firstChild);
                        } else if (!isFeatured && star) {
                            star.remove();
                        }
                    }
                    showToast('Foto details opgeslagen.', true);
                } else {
                    showToast(data.message || 'Fout bij opslaan.', false);
                }
            })
            .catch(() => showToast('Netwerkfout.', false))
            .finally(() => closeModal());
        });
    }

    // --- SortableJS ---
    const portfolioList = document.getElementById('portfolio-sortable-list');
    if (portfolioList) {
        new Sortable(portfolioList, {
            handle: '.drag-handle', animation: 150, ghostClass: 'sortable-ghost',
            onEnd: (evt) => {
                const order = Array.from(evt.target.querySelectorAll('tr[data-theme]')).map(item => item.dataset.theme);
                const formData = new FormData();
                formData.append('action', 'update_portfolio_order');
                order.forEach(themeName => formData.append('order[]', themeName));
                fetch('save.php', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') showToast('Volgorde opgeslagen', true);
                        else showToast(data.message || 'Volgorde opslaan mislukt', false);
                    }).catch(() => showToast('Netwerkfout bij opslaan volgorde.', false));
            }
        });
    }

    document.querySelectorAll('.photo-list').forEach(list => {
        new Sortable(list, {
            animation: 150,
            ghostClass: 'sortable-ghost',
            onEnd: (evt) => {
                const theme = evt.target.dataset.theme;
                if (!theme) return;
                const order = Array.from(evt.target.querySelectorAll('[data-id]')).map(item => item.dataset.id);
                
                const formData = new FormData();
                formData.append('action', 'update_photo_order');
                formData.append('theme', theme);
                order.forEach(index => formData.append('order[]', index));

                fetch('save.php', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            showToast('Volgorde foto\'s opgeslagen.', true);
                            // Update the data-id attributes in the DOM to reflect the new order
                            const items = evt.target.querySelectorAll('[data-id]');
                            items.forEach((item, index) => {
                                item.dataset.id = index;
                            });
                        } else {
                            showToast(data.message || 'Volgorde foto\'s opslaan mislukt.', false);
                        }
                    })
                    .catch(() => showToast('Netwerkfout bij opslaan volgorde.', false));
            }
        });
    });

    document.querySelectorAll('.gallery-photo-list').forEach(list => {
        new Sortable(list, {
            animation: 150,
            ghostClass: 'sortable-ghost',
            onEnd: (evt) => {
                const slug = evt.target.dataset.gallery;
                if (!slug) return;
                const order = Array.from(evt.target.querySelectorAll('[data-id]')).map(item => item.dataset.id);
                
                const formData = new FormData();
                formData.append('action', 'update_gallery_photo_order');
                formData.append('slug', slug);
                order.forEach(index => formData.append('order[]', index));

                fetch('save.php', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if (data.status === 'success') {
                            showToast('Volgorde opgeslagen.', true);
                             // Update the data-id attributes in the DOM to reflect the new order
                            const items = evt.target.querySelectorAll('[data-id]');
                            items.forEach((item, index) => {
                                item.dataset.id = index;
                            });
                        } else {
                            showToast(data.message || 'Volgorde opslaan mislukt.', false);
                        }
                    }).catch(() => showToast('Netwerkfout bij opslaan volgorde.', false));
            }
        });
    });

    // --- Copy Link Buttons ---
    document.body.addEventListener('click', e => {
        const copyBtn = e.target.closest('.copy-link-btn');
        if (copyBtn) {
            let link = copyBtn.dataset.link;
            if (!link.startsWith('http')) link = window.location.origin + link;
            navigator.clipboard.writeText(link)
                .then(() => showToast('Link gekopieerd!', true))
                .catch(() => showToast('KopiÃ«ren mislukt', false));
        }
    });

});




