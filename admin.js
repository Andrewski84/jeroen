// Admin UI script: handles tabs, uploads, modals, and other UX helpers
document.addEventListener('DOMContentLoaded', function () {
    
    // Hide upload container on page load
    document.getElementById('upload-progress-container')?.classList.add('hidden');

    // --- Click handler for hidden Hero image input ---
    document.getElementById('hero_image_container')?.addEventListener('click', () => {
        document.getElementById('hero_image_input')?.click();
    });
    document.getElementById('hero_image_input')?.addEventListener('change', e => {
        if (e.target.files.length > 0) {
            queueFileUpload(e.target.files[0], { target: 'hero' });
            e.target.value = ''; // Reset input
        }
    });

    // --- Tab Navigation ---
    const tabButtons = document.querySelectorAll('.admin-tab-button');
    const tabPanels = document.querySelectorAll('.admin-tab-panel');

    function switchTab(tabId) {
        if (!tabId) return;
        tabButtons.forEach(btn => btn.classList.toggle('active', btn.dataset.tab === tabId));
        tabPanels.forEach(panel => panel.classList.toggle('active', panel.id === tabId));
        // Also toggle auxiliary panels linked to main tabs
        const teamSortPanel = document.getElementById('tab-team-sort-panel');
        if (teamSortPanel) teamSortPanel.classList.toggle('active', tabId === 'tab-team');
        
        const newHash = '#' + tabId;
        if (history.pushState && window.location.hash !== newHash) {
            history.pushState(null, null, newHash);
        } else if (!history.pushState) {
            window.location.hash = newHash;
        }
    }

    tabButtons.forEach(button => {
        button.addEventListener('click', (e) => {
            e.preventDefault();
            switchTab(button.dataset.tab);
        });
    });

    function activateTabFromHash() {
        const tabId = window.location.hash.substring(1).split('&')[0] || 'tab-homepage';
        switchTab(tabId);
    }
    activateTabFromHash();

    // --- Detail View for Practice Pages ---
    document.addEventListener('click', e => {
        const row = e.target.closest('#practice-table tbody tr');
        if (!row || e.target.closest('button, form, a, .drag-handle')) return;
        
        const detailId = row.dataset.slug;
        const targetCard = document.querySelector(`.practice-card-editor[data-slug="${detailId}"]`);
        
        if (row.classList.contains('active-row')) {
            row.classList.remove('active-row');
            if (targetCard) targetCard.classList.add('hidden');
        } else {
            document.querySelectorAll('#practice-table tbody tr.active-row').forEach(r => r.classList.remove('active-row'));
            document.querySelectorAll('.practice-card-editor').forEach(c => c.classList.add('hidden'));
            row.classList.add('active-row');
            if (targetCard) {
                targetCard.classList.remove('hidden');
                targetCard.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }
    });

    // --- Upload Queue System ---
    const uploadQueue = [];
    let activeUploads = 0;
    const MAX_CONCURRENT_UPLOADS = 2;
    const progressContainer = document.getElementById('upload-progress-container');
    const progressList = document.getElementById('upload-progress-list');
    const progressSummary = document.getElementById('upload-progress-summary');

    function updateProgressSummary() {
        if (!progressSummary) return;
        const total = uploadQueue.length;
        const done = uploadQueue.filter(t => t.status === 'success' || t.status === 'error').length;
        progressSummary.textContent = `${done}/${total} uploads voltooid`;
        if (progressContainer) {
            progressContainer.classList.toggle('hidden', total === 0 || done === total);
        }
    }
    
    document.getElementById('upload-clear-btn')?.addEventListener('click', () => {
         progressContainer.classList.add('hidden');
         progressList.innerHTML = '';
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
            .catch(err => {
                task.status = 'error';
                task.progress.setStatus('error', 'Netwerkfout');
            })
            .finally(() => {
                activeUploads--;
                updateProgressSummary();
                if(uploadQueue.every(t => t.status === 'success' || t.status === 'error')) {
                    const hasErrors = uploadQueue.some(t => t.status === 'error');
                    showToast(hasErrors ? 'Uploads voltooid, met fouten.' : 'Uploads voltooid, pagina herlaadt...', !hasErrors);
                    if (hasErrors) {
                        setTimeout(() => {
                            progressContainer?.classList.add('hidden');
                        }, 5000); // Hide after 5 seconds on error
                    } else {
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
        item.innerHTML = `<div class="upload-item-name">${fileName}</div><div class="progress-bar-bg"><div class="progress-bar status-queued"></div></div><div class="upload-item-status"></div>`;
        progressList.appendChild(item);
        const bar = item.querySelector('.progress-bar');
        const statusEl = item.querySelector('.upload-item-status');
        return {
            setStatus: (status, message = '') => {
                bar.className = `progress-bar status-${status}`;
                statusEl.textContent = message;
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
            input.type = 'file';
            input.multiple = true;
            input.accept = 'image/*';
            input.onchange = e => dropHandler(e.target.files);
            input.click();
        });
    });

    // --- Reordering (SortableJS via AJAX) ---
    function initSortable(selector, action) {
        const el = document.querySelector(selector);
        if (el && typeof Sortable !== 'undefined') {
            new Sortable(el, { 
                animation: 150, 
                handle: '.drag-handle', 
                onEnd: (evt) => {
                    const order = Array.from(evt.target.children).map(item => item.dataset.id || item.dataset.slug);
                    const fd = new FormData(); 
                    fd.append('action', action); 
                    order.forEach(id => fd.append('order[]', id)); 
                    fd.append('ajax', '1');
                    fetch('save.php', { method: 'POST', body: fd });
                }
            });
        }
    }
    initSortable('#practice-table tbody', 'reorder_practice_pages');
    initSortable('#pinned-list', 'reorder_pinned');
    initSortable('#pinned-table-body', 'reorder_pinned');
    initSortable('#links-list', 'reorder_links');
    initSortable('#team-groups', 'reorder_team_groups');

    // Sort members within each team group
    function initTeamMembersSort() {
        document.querySelectorAll('.team-members-list').forEach(list => {
            if (list._sortableInit || typeof Sortable === 'undefined') return;
            const groupId = list.dataset.groupId || '';
            list._sortableInit = true;
            new Sortable(list, {
                animation: 150,
                handle: '.drag-handle',
                onEnd: (evt) => {
                    const order = Array.from(evt.target.children).map(item => item.dataset.id);
                    const fd = new FormData();
                    fd.append('action', 'reorder_team_members');
                    fd.append('group_id', groupId);
                    order.forEach(id => fd.append('order[]', id));
                    fd.append('ajax', '1');
                    fetch('save.php', { method: 'POST', body: fd });
                }
            });
        });
    }
    initTeamMembersSort();
    
    // --- Global Pop-up System ---
    const toastPopup = document.getElementById('toast-popup');
    window.showToast = (message, isSuccess = true) => {
        if (!toastPopup) return;
        toastPopup.textContent = message;
        toastPopup.style.backgroundColor = isSuccess ? 'var(--btn-success-bg)' : 'var(--btn-danger-bg)';
        toastPopup.classList.add('show');
        setTimeout(() => toastPopup.classList.remove('show'), 3000);
    };

    // --- Confirmation Modal for Deletes ---
    const confirmModal = document.getElementById('confirm-modal');
    let formToSubmit = null;

    if (confirmModal) {
        document.addEventListener('submit', e => {
            if (e.target.closest('.delete-form')) {
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
            confirmModal.classList.add('hidden');
            if (formToSubmit) {
                formToSubmit.submit();
            }
            formToSubmit = null;
        });
    }

    // --- Rich Text Editor Sync ---
    document.addEventListener('submit', (e) => {
        const form = e.target;
        form.querySelectorAll('textarea.richtext').forEach(t => { 
            if (t._ck && typeof t._ck.getData === 'function') {
                t.value = t._ck.getData();
            }
        });
    });
});

