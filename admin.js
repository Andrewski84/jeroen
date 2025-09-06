// Admin UI script: handles navigation, uploads, modals, and other UX helpers
document.addEventListener('DOMContentLoaded', function () {

    // --- Core UI Initialization ---
    function initializeRichtext(selector = 'textarea.richtext') {
        document.querySelectorAll(selector).forEach(el => {
            if (!window.ClassicEditor || el._ck_inited) return;
            ClassicEditor.create(el, {
                toolbar: ['heading', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', 'undo', 'redo'],
                link: {
                    addTargetToExternalLinks: true
                }
            })
            .then(editor => {
                el._ck = editor;
                el._ck_inited = true;
            })
            .catch(() => {});
        });
    }

    // Initialize all rich text editors on page load
    initializeRichtext();

    // --- Navigation ---
    const navLinks = document.querySelectorAll('.admin-nav-link');
    const tabPanels = document.querySelectorAll('.admin-tab-panel');

    function switchTab(tabId, pushState = true) {
        if (!tabId) return;
        const targetPanel = document.getElementById(tabId);
        if (!targetPanel) return;

        navLinks.forEach(link => link.classList.toggle('active', link.dataset.tab === tabId));
        tabPanels.forEach(panel => panel.classList.remove('active'));
        
        targetPanel.classList.add('active');
        
        if (pushState) {
            const newHash = '#' + tabId;
            if (history.pushState && window.location.hash !== newHash) {
                history.pushState(null, null, newHash);
            } else {
                window.location.hash = newHash;
            }
        }
    }

    navLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            const tabId = link.dataset.tab;
            if (tabId) {
                e.preventDefault();
                switchTab(tabId);
            }
        });
    });

    // Activate tab from URL hash on page load
    const currentHash = window.location.hash.substring(1);
    switchTab(currentHash || 'homepage', false);


    // --- Global Pop-up / Toast System ---
    const toastContainer = document.getElementById('toast-container');
    const toastTemplate = document.getElementById('toast-template');
    window.showToast = (message, type = 'success') => { // type can be 'success' or 'error'
        if (!toastContainer || !toastTemplate) return;
        
        const toast = toastTemplate.content.cloneNode(true).firstElementChild;
        toast.querySelector('.toast-message').textContent = message;
        toast.classList.add(type);

        const iconContainer = toast.querySelector('.toast-icon');
        if (type === 'success') {
            iconContainer.innerHTML = window.APP_ICONS.success || '';
        } else {
            iconContainer.innerHTML = window.APP_ICONS.error || '';
        }
        
        toastContainer.appendChild(toast);
        setTimeout(() => toast.remove(), 4000);
    };


    // --- Confirmation Modal for Deletes ---
    const confirmModal = document.getElementById('confirm-modal');
    let formToSubmit = null;

    if (confirmModal) {
        document.addEventListener('submit', e => {
            if (e.target.matches('.delete-form')) {
                e.preventDefault();
                formToSubmit = e.target;
                confirmModal.classList.remove('hidden');
            }
        });

        document.getElementById('confirm-no')?.addEventListener('click', () => {
            confirmModal.classList.add('hidden');
            formToSubmit = null;
        });

        document.getElementById('confirm-yes')?.addEventListener('click', () => {
            if (formToSubmit) {
                formToSubmit.submit();
            }
            confirmModal.classList.add('hidden');
            formToSubmit = null;
        });
    }

    // --- Dynamic Content Functions (Adding items to lists) ---
    function addNewItem(containerId, html, richtextSelector = null) {
        const container = document.getElementById(containerId);
        if (!container) return;
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = html.trim();
        const newItem = tempDiv.firstChild;
        container.appendChild(newItem);
        if (richtextSelector) {
            initializeRichtext(richtextSelector);
        }
    }

    window.addWelcomeCard = () => {
        addNewItem('welcome-cards', `
            <div class="border border-slate-200 rounded-md p-3">
                <div class="flex items-center justify-end">
                    <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.border').remove()">${window.APP_ICONS.delete} Verwijder</button>
                </div>
                <textarea name="welcome_card_html[]" class="form-textarea richtext" rows="4"></textarea>
            </div>
        `, '#welcome-cards .richtext:not([data-processed])');
    };
    
    window.addPracticeCard = (containerId) => {
        addNewItem(containerId, `
            <div class="border border-slate-200 rounded-md p-3">
                <div class="text-right"><button type="button" class="btn btn-danger btn-sm" onclick="this.closest('div.border').remove()">${window.APP_ICONS.delete}</button></div>
                <textarea name="card_html[]" class="form-textarea richtext" rows="5"></textarea>
            </div>
        `);
    };

    window.addLinkItem = () => {
        const id = 'link_' + Math.random().toString(36).substring(2, 9);
        addNewItem('links-list', `
            <div class="border border-slate-200 rounded p-3" data-id="${id}">
                <div class="flex items-center justify-between mb-2">
                    <span class="drag-handle cursor-move text-slate-400" title="Sleep om te sorteren">${window.APP_ICONS.drag}</span>
                    <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.border').remove()">${window.APP_ICONS.delete}</button>
                </div>
                <input type="hidden" name="link_id[]" value="${id}">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    <input type="text" class="form-input" name="link_label[]" placeholder="Naam">
                    <input type="text" class="form-input" name="link_url[]" placeholder="URL (https://...)">
                    <input type="text" class="form-input" name="link_tel[]" placeholder="Telefoon">
                    <input type="text" class="form-input" name="link_desc[]" placeholder="Omschrijving">
                </div>
            </div>
        `);
    };

    window.addPhoneItem = () => {
        addNewItem('phones-list', `
            <div class="border border-slate-200 rounded p-3" data-id="phone_${Math.random().toString(36).substring(2, 9)}">
                 <div class="flex items-center justify-between mb-2">
                    <span class="drag-handle cursor-move text-slate-400" title="Sleep om te sorteren">${window.APP_ICONS.drag}</span>
                    <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.border').remove()">${window.APP_ICONS.delete}</button>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    <input type="text" class="form-input" name="phone_label[]" placeholder="Naam">
                    <input type="text" class="form-input" name="phone_tel[]" placeholder="Telefoon">
                    <input type="text" class="form-input" name="phone_desc[]" placeholder="Omschrijving">
                    <input type="text" class="form-input" name="phone_url[]" placeholder="Link (optioneel)">
                </div>
            </div>
        `);
    };
    
    window.addPinnedItem = () => {
        const id = 'pin_' + Math.random().toString(36).substring(2, 9);
        addNewItem('pinned-list', `
            <div class="border border-slate-200 rounded-lg p-4" data-id="${id}">
                <input type="hidden" name="pinned_id[]" value="${id}">
                <div class="flex items-center justify-between mb-2">
                    <span class="drag-handle cursor-move text-slate-400" title="Sleep om te sorteren">${window.APP_ICONS.drag}</span>
                    <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('.border').remove()">${window.APP_ICONS.delete} Verwijderen</button>
                </div>
                <label class="form-label">Titel</label>
                <input type="text" class="form-input" name="pinned_title[]" value="">
                <label class="form-label mt-2">Tekst</label>
                <textarea class="form-textarea richtext" name="pinned_text[]" rows="4"></textarea>
                 <div class="mt-4">
                    <label class="form-label">Zichtbaar op:</label>
                     <div class="flex flex-wrap gap-4 text-sm">
                        <label class="inline-flex items-center gap-2"><input type="checkbox" name="pinned_scope_all[${id}]" onchange="window.togglePinnedAll(this)"><span>Alle pagina's</span></label>
                        <label class="inline-flex items-center gap-2"><input type="checkbox" name="pinned_scope_home[${id}]"><span>Homepage</span></label>
                        <label class="inline-flex items-center gap-2"><input type="checkbox" name="pinned_scope_team[${id}]"><span>Team</span></label>
                        <label class="inline-flex items-center gap-2"><input type="checkbox" name="pinned_scope_practice[${id}]"><span>Praktijkinfo</span></label>
                        <label class="inline-flex items-center gap-2"><input type="checkbox" name="pinned_scope_links[${id}]"><span>Links</span></label>
                        <label class="inline-flex items-center gap-2"><input type="checkbox" name="pinned_scope_phones[${id}]"><span>Telefoon</span></label>
                    </div>
                </div>
            </div>
        `);
        initializeRichtext(`#pinned-list [data-id="${id}"] .richtext`);
    };

    window.togglePinnedAll = function(checkbox) {
        const container = checkbox.closest('.border[data-id]');
        const isChecked = checkbox.checked;
        container.querySelectorAll('input[type="checkbox"]').forEach(cb => {
            if (cb !== checkbox) cb.checked = isChecked;
        });
    };

    // --- Upload Queue System ---
    const uploadQueue = [];
    let activeUploads = 0;
    const MAX_CONCURRENT_UPLOADS = 2;
    const progressContainer = document.getElementById('upload-progress-container');
    const progressList = document.getElementById('upload-progress-list');
    const progressSummary = document.getElementById('upload-progress-summary');

    function updateProgressSummary() {
        if (!progressSummary || !progressContainer) return;
        const total = uploadQueue.length;
        if (total === 0) {
            progressContainer.classList.add('hidden');
            return;
        }
        progressContainer.classList.remove('hidden');
        const done = uploadQueue.filter(t => t.status === 'success' || t.status === 'error').length;
        progressSummary.textContent = `${done}/${total} uploads voltooid`;
    }
    
    document.getElementById('upload-clear-btn')?.addEventListener('click', () => {
         progressContainer?.classList.add('hidden');
         if(progressList) progressList.innerHTML = '';
         uploadQueue.length = 0;
    });

    function processUploadQueue() {
        while (activeUploads < MAX_CONCURRENT_UPLOADS) {
            const nextTask = uploadQueue.find(item => item.status === 'queued');
            if (!nextTask) break;
            startUpload(nextTask);
        }
    }

    function startUpload(task) {
        task.status = 'uploading';
        activeUploads++;
        task.progress.setStatus('uploading');
        updateProgressSummary();

        const formData = new FormData();
        formData.append('file', task.file);
        formData.append('target', task.meta.target);
        if (task.meta.member_id) formData.append('member_id', task.meta.member_id);

        fetch('upload_ajax.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    task.status = 'success';
                    task.progress.setStatus('success');
                } else {
                    task.status = 'error';
                    task.progress.setStatus('error', res.message || 'Serverfout');
                }
            })
            .catch(() => {
                task.status = 'error';
                task.progress.setStatus('error', 'Netwerkfout');
            })
            .finally(() => {
                activeUploads--;
                if(uploadQueue.every(t => t.status === 'success' || t.status === 'error')) {
                    const hasErrors = uploadQueue.some(t => t.status === 'error');
                    window.showToast(hasErrors ? 'Uploads voltooid, met fouten.' : 'Alle uploads voltooid!', hasErrors ? 'error' : 'success');
                    if (!hasErrors) {
                        setTimeout(() => window.location.reload(), 1500);
                    }
                }
                processUploadQueue();
            });
    }

    function queueFileUpload(file, meta) {
        const progress = createProgressBar(file.name);
        uploadQueue.push({ file, meta, progress, status: 'queued' });
        updateProgressSummary();
        processUploadQueue();
    }

    function createProgressBar(fileName) {
        if (!progressList) return { setStatus: ()=>{} };
        const item = document.createElement('div');
        item.className = 'upload-item';
        item.innerHTML = `<div class="upload-item-name">${fileName}</div><div class="progress-bar-bg"><div class="progress-bar"></div></div><div class="upload-item-status">In wachtrij...</div>`;
        progressList.appendChild(item);
        const bar = item.querySelector('.progress-bar');
        const statusEl = item.querySelector('.upload-item-status');
        return {
            setStatus: (status, message = '') => {
                bar.className = `progress-bar status-${status}`;
                statusEl.textContent = message || status.charAt(0).toUpperCase() + status.slice(1);
                statusEl.className = `upload-item-status status-${status}`;
            }
        };
    }

    document.querySelectorAll('.dropzone').forEach(dz => {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eName => dz.addEventListener(eName, e => { e.preventDefault(); e.stopPropagation(); }));
        ['dragenter', 'dragover'].forEach(eName => dz.addEventListener(eName, () => dz.classList.add('is-dragover')));
        ['dragleave', 'drop'].forEach(eName => dz.addEventListener(eName, () => dz.classList.remove('is-dragover')));
        
        const dropHandler = (files) => {
            if (files) Array.from(files).forEach(file => queueFileUpload(file, { target: dz.dataset.target, member_id: dz.dataset.memberId }));
        };

        dz.addEventListener('drop', e => dropHandler(e.dataTransfer.files));
        dz.addEventListener('click', () => {
            const input = document.createElement('input');
            input.type = 'file'; input.multiple = true; input.accept = 'image/*';
            input.onchange = e => dropHandler(e.target.files);
            input.click();
        });
    });

    // --- Reordering (SortableJS) ---
    function initSortable(selector, action, group = 'default') {
        const el = document.querySelector(selector);
        if (el && typeof Sortable !== 'undefined') {
            new Sortable(el, { 
                animation: 150, 
                handle: '.drag-handle',
                group: group,
                onEnd: () => {
                    const order = Array.from(el.children).map(item => item.dataset.id || item.dataset.slug).filter(Boolean);
                    const fd = new FormData(); 
                    fd.append('action', action); 
                    order.forEach(id => fd.append('order[]', id)); 
                    fd.append('ajax', '1');
                    fetch('save.php', { method: 'POST', body: fd })
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 'success') {
                                window.showToast('Volgorde opgeslagen', 'success');
                            } else {
                                window.showToast('Fout bij opslaan volgorde', 'error');
                            }
                        });
                }
            });
        }
    }
    initSortable('#practice-pages-list', 'reorder_practice_pages');
    initSortable('#pinned-list', 'reorder_pinned');
    initSortable('#links-list', 'reorder_links');
    initSortable('#phones-list', 'reorder_phones');
    initSortable('#team-groups-list', 'reorder_team_groups');
    initSortable('#team-members-list', 'reorder_team_members');

    // --- Rich Text Editor Sync on Form Submit ---
    document.addEventListener('submit', (e) => {
        const form = e.target;
        form.querySelectorAll('textarea.richtext').forEach(t => { 
            if (t._ck && typeof t._ck.getData === 'function') {
                t.value = t._ck.getData();
            }
        });
    });
});

