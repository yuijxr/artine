document.addEventListener('DOMContentLoaded', () => {
    const addrContainer = document.getElementById('account-addresses');
    const openMgrBtn = document.getElementById('open-address-manager');

    const modal = document.getElementById('address-modal');
    const managerListWrap = document.getElementById('modal_list');
    const managerList = document.getElementById('modal_addresses_list');
    const btnAddNew = document.getElementById('modal_add_new');
    const modalCloseIcon = document.getElementById('modal_close_icon');
    const form = document.getElementById('addr-modal-form');
    const btnBack = document.getElementById('modal_back');

    const fld = {
        id: document.getElementById('modal_address_id'),
        full_name: document.getElementById('modal_full_name'),
        phone: document.getElementById('modal_phone'),
        house_number: document.getElementById('modal_house_number'),
        street: document.getElementById('modal_street'),
        barangay: document.getElementById('modal_barangay'),
        city: document.getElementById('modal_city'),
        province: document.getElementById('modal_province'),
        postal_code: document.getElementById('modal_postal_code'),
        country: document.getElementById('modal_country')
    };

    let addressesCache = [];
    let pendingSelectedAddressId = null; // selection inside modal before saving

    function notify(msg, type = 'info') {
        try {
            if (typeof showNotification === 'function') {
                showNotification(msg, type);
            } else {
                alert(msg);
            }
        } catch (e) {
            try {
                alert(msg);
            } catch (_) {}
        }
    }

    async function fetchAllAddresses() {
        try {
            const res = await fetch('api/addresses.php', { cache: 'no-store' });
            if (!res.ok) throw new Error('Failed to fetch');
            const list = await res.json();
            addressesCache = Array.isArray(list) ? list : [];
            return addressesCache;
        } catch (err) {
            console.error(err);
            addressesCache = [];
            return [];
        }
    }

    async function loadAddresses() {
        const list = await fetchAllAddresses();
        renderPreview(list);
    }

    function renderPreview(list) {
        // We'll update both the account preview (if present) and the settings addresses list so they stay in sync
        const acctContainer = document.getElementById('account-addresses');
        const settingsContainer = document.getElementById('addresses-list');
        if (!acctContainer && !settingsContainer) return;
        if (acctContainer) acctContainer.innerHTML = '';
        if (settingsContainer) settingsContainer.innerHTML = '';
        if (!Array.isArray(list) || list.length === 0) {
            if (acctContainer) acctContainer.innerHTML = '<div style="color:#666">No saved addresses</div>';
            if (settingsContainer) settingsContainer.innerHTML = '<div style="color:#666">No saved addresses</div>';
            return;
        }
        // build preview up to 2 addresses: default first, then next
        const preview = [];
        const def = list.find(a => Number(a.is_default) === 1 || a.is_default === '1');
        if (def) preview.push(def);
        for (const a of list) {
            if (preview.length >= 2) break;
            if (def && a.address_id == def.address_id) continue;
            preview.push(a);
        }
        // if no default and list has at least 2, take first two
        if (preview.length === 0) preview.push(list[0]);

        // We'll render cards into both containers and attach click handlers so selection updates both
        preview.forEach(a => {
            const country = (!a.country || a.country === '0') ? 'Philippines' : a.country;
            const html = `
                <div class="address-card" data-address-id="${escapeHtml(a.address_id)}">
                    <div class="addr-main">
                        <strong>${escapeHtml(a.full_name)}</strong>
                        <span style="color:#64748b; font-size:14px">(${escapeHtml(a.phone)})</span>
                        <div style="margin-top:5px; color:#64748b; font-size:14px">${escapeHtml(a.house_number ? a.house_number + ', ' : '')}${escapeHtml(a.street)}</div>
                        <div style="margin-top:5px; color:#64748b; font-size:14px">${escapeHtml(a.city)}${a.barangay ? (', Barangay ' + escapeHtml(a.barangay)) : ''}</div>
                        <div style="margin-top:5px; color:#64748b; font-size:14px">${escapeHtml(a.province)}, ${escapeHtml(a.postal_code || '')}, ${escapeHtml(country)}</div>
                    </div>
                    <div class="addr-actions">
                        ${Number(a.is_default) === 1 || a.is_default === '1' ? '<span class="default-badge">Default</span>' : ''}
                    </div>
                </div>
            `;
            if (acctContainer) acctContainer.insertAdjacentHTML('beforeend', html);
            if (settingsContainer) settingsContainer.insertAdjacentHTML('beforeend', html);
        });

        // Attach click handlers to newly-inserted cards in both containers
        const attachClick = (root) => {
            if (!root) return;
            root.querySelectorAll('.address-card .addr-main').forEach(el => {
                const card = el.closest('.address-card');
                if (!card) return;
                const aid = card.getAttribute('data-address-id');
                el.addEventListener('click', async () => {
                    try {
                        const r = await fetch('api/addresses.php', {
                            method: 'PUT',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ address_id: aid, set_default: 1 })
                        });
                        const j = await r.json();
                        if (j && j.success) {
                            notify('Default updated', 'success');
                            const list2 = await fetchAllAddresses();
                            renderPreview(list2);
                        } else {
                            notify('Could not set default', 'error');
                        }
                    } catch (err) {
                        notify('Failed', 'error');
                    }
                });
            });
        };
        attachClick(acctContainer);
        attachClick(settingsContainer);
    }

    // show manager list in modal
    async function showManager() {
        const list = await fetchAllAddresses();
        // initialize pending selection to current default so user sees existing choice
        const def = list.find(a => Number(a.is_default) === 1 || a.is_default === '1');
        pendingSelectedAddressId = def ? def.address_id : null;
        renderManagerList(list);
        // set modal title to Manage Addresses and show list view, hide form
        try { document.getElementById('addr-modal-title').textContent = 'Manage Addresses'; } catch (e) {}
        managerListWrap.style.display = '';
        form.style.display = 'none';
        modal.style.display = '';
    }

    function renderManagerList(list) {
        if (!managerList) return;
        managerList.innerHTML = '';
        if (!Array.isArray(list) || list.length === 0) {
            managerList.innerHTML = '<div style="color:#666">No saved addresses</div>';
            return;
        }
        list.forEach(a => {
            const el = document.createElement('div');
            el.className = 'address-card';
            const main = document.createElement('div');
            main.className = 'addr-main';
            const country = (!a.country || a.country === '0') ? 'Philippines' : a.country;
            main.innerHTML = `<strong>${escapeHtml(a.full_name)}</strong> ` +
                `<span style="color:#64748b; font-size:14px">(${escapeHtml(a.phone)})</span> ` +
                `${Number(a.is_default) === 1 ? '<span class="default-badge">Default</span>' : ''}` +
                    `<div style="margin-top:5px; color:#64748b; font-size:14px">${escapeHtml(a.house_number ? a.house_number + ', ' : '')}${escapeHtml(a.street)}</div>` +
                    `<div style="margin-top:5px; color:#64748b; font-size:14px">${escapeHtml(a.city)}${a.barangay ? (', Barangay ' + escapeHtml(a.barangay)) : ''}</div>` +
                    `<div style="margin-top:5px; color:#64748b; font-size:14px">${escapeHtml(a.province)}, ${escapeHtml(a.postal_code || '')}, ${escapeHtml(country)}</div>`;
            const actions = document.createElement('div');
            actions.className = 'addr-actions';
            // Edit icon button
            const editBtn = document.createElement('button');
            editBtn.className = 'icon-btn';
            editBtn.title = 'Edit';
            editBtn.innerHTML = '<i class="fa fa-pen"></i>';
            editBtn.addEventListener('click', (ev) => {
                ev.stopPropagation();
                openFormForAddress(a);
            });
            // Delete icon button
            const delBtn = document.createElement('button');
            delBtn.className = 'icon-btn danger';
            delBtn.title = 'Delete';
            delBtn.innerHTML = '<i class="fa fa-trash"></i>';
            delBtn.addEventListener('click', async (ev) => {
                ev.stopPropagation();
                if (!confirm('Delete this address?')) return;
                try {
                    const r = await fetch('api/addresses.php', {
                        method: 'DELETE',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ address_id: a.address_id })
                    });
                    const j = await r.json();
                    if (j && j.success) {
                        notify('Deleted', 'success');
                        const list2 = await fetchAllAddresses();
                        // adjust pending selection if needed
                        const defAfter = list2.find(x => Number(x.is_default) === 1 || x.is_default === '1');
                        pendingSelectedAddressId = defAfter ? defAfter.address_id : null;
                        renderManagerList(list2);
                        renderPreview(list2);
                    } else {
                        notify('Delete failed: ' + (j && j.message ? j.message : ''), 'error');
                    }
                } catch (err) {
                    notify('Delete failed', 'error');
                }
            });

            const wrapActions = document.createElement('div');
            wrapActions.style.display = 'flex';
            wrapActions.style.gap = '8px';
            wrapActions.appendChild(editBtn);
            wrapActions.appendChild(delBtn);
            actions.appendChild(wrapActions);
            // clicking the card selects it locally; Save will commit the change
            el.addEventListener('click', () => {
                pendingSelectedAddressId = a.address_id;
                // update visual selection
                Array.from(managerList.children).forEach(ch => {
                    ch.classList.remove('selected');
                    ch.style.borderColor = '';
                    ch.style.boxShadow = '';
                });
                el.classList.add('selected');
                el.style.borderColor = '#1976d2';
                el.style.boxShadow = '0 0 0 3px rgba(25,118,210,0.06)';
            });
            el.appendChild(main);
            el.appendChild(actions);
            managerList.appendChild(el);
        });
        // ensure the currently pending selection is visually marked
        if (pendingSelectedAddressId) {
            Array.from(managerList.children).forEach((ch, idx) => {
                const addr = list[idx];
                if (addr && String(addr.address_id) === String(pendingSelectedAddressId)) {
                    ch.classList.add('selected');
                    ch.style.borderColor = '#1976d2';
                    ch.style.boxShadow = '0 0 0 3px rgba(25,118,210,0.06)';
                }
            });
        }
    }

    function openFormForAddress(address) {
        if (address) {
            fld.id.value = address.address_id || '';
            fld.full_name.value = address.full_name || '';
            fld.phone.value = address.phone || '';
            fld.house_number.value = address.house_number || '';
            fld.street.value = address.street || '';
            fld.barangay.value = address.barangay || '';
            fld.city.value = address.city || '';
            fld.province.value = address.province || '';
            fld.postal_code.value = address.postal_code || '';
            fld.country.value = (!address.country || address.country === '0') ? 'Philippines' : address.country;
            try { document.getElementById('addr-modal-title').textContent = 'Edit address'; } catch (e) {}
        } else {
            fld.id.value = '';
            // Keep full_name, phone and country prefilled (readonly) from server; clear address-specific fields
            fld.house_number.value = '';
            fld.street.value = '';
            fld.barangay.value = '';
            fld.city.value = '';
            fld.province.value = '';
            fld.postal_code.value = '';
            try { document.getElementById('addr-modal-title').textContent = 'Add address'; } catch (e) {}
        }
        managerListWrap.style.display = 'none';
        form.style.display = '';
    }

    function closeModal() {
        modal.style.display = 'none';
    }

    async function saveAddress(ev) {
        ev.preventDefault();
        const payload = {
            full_name: fld.full_name.value.trim(),
            phone: fld.phone.value.trim(),
            house_number: fld.house_number.value.trim(),
            street: fld.street.value.trim(),
            barangay: fld.barangay.value.trim(),
            city: fld.city.value.trim(),
            province: fld.province.value.trim(),
            postal_code: fld.postal_code.value.trim(),
            country: fld.country.value.trim()
        };
        const id = fld.id.value;
        try {
            if (id) {
                payload.address_id = id;
                const r = await fetch('api/addresses.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const j = await r.json();
                if (j && j.success) {
                    notify('Address updated', 'success');
                    const list2 = await fetchAllAddresses();
                    renderManagerList(list2);
                    renderPreview(list2);
                    managerListWrap.style.display = '';
                    form.style.display = 'none';
                    try { document.getElementById('addr-modal-title').textContent = 'Manage Addresses'; } catch (e) {}
                } else {
                    notify('Save failed', 'error');
                }
            } else {
                const r = await fetch('api/addresses.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const j = await r.json();
                if (j && j.success) {
                    notify('Address saved', 'success');
                    const list2 = await fetchAllAddresses();
                    renderManagerList(list2);
                    renderPreview(list2);
                    managerListWrap.style.display = '';
                    form.style.display = 'none';
                    try { document.getElementById('addr-modal-title').textContent = 'Manage Addresses'; } catch (e) {}
                } else {
                    notify('Save failed', 'error');
                }
            }
        } catch (err) {
            console.error(err);
            notify('Save failed', 'error');
        }
    }

    // small helper
    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        return String(s).replace(/[&<>\"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '\"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // events
    openMgrBtn && openMgrBtn.addEventListener('click', showManager);
    // modal_add_new may be an anchor (moved to header); prevent default navigation
    btnAddNew && btnAddNew.addEventListener('click', (e) => { e.preventDefault(); openFormForAddress(null); });
    modalCloseIcon && modalCloseIcon.addEventListener('click', () => closeModal());
    btnBack && btnBack.addEventListener('click', (e) => {
        e.preventDefault();
        form.style.display = 'none';
        managerListWrap.style.display = '';
        try { document.getElementById('addr-modal-title').textContent = 'Manage Addresses'; } catch (e) {}
    });
    form && form.addEventListener('submit', saveAddress);
    // Save/Cancel inside manager list (commit selection on Save)
    const modalSaveBtn = document.getElementById('modal_save_changes');
    const modalCancelBtn = document.getElementById('modal_cancel_changes');
    modalCancelBtn && modalCancelBtn.addEventListener('click', (e) => {
        e.preventDefault();
        // discard selection and close
        e.stopPropagation();
        pendingSelectedAddressId = null;
        closeModal();
    });
    modalSaveBtn && modalSaveBtn.addEventListener('click', async (e) => {
        e.preventDefault();
        e.stopPropagation();
        // close modal immediately as requested by UX
        closeModal();
        // if nothing selected, nothing to do
        if (!pendingSelectedAddressId) return;
        const currentDefault = addressesCache.find(a => Number(a.is_default) === 1 || a.is_default === '1');
        if (currentDefault && String(currentDefault.address_id) === String(pendingSelectedAddressId)) {
            // nothing changed
            return;
        }
        // perform the API call and show a single toast depending on outcome
        try {
            const r = await fetch('api/addresses.php', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ address_id: pendingSelectedAddressId, set_default: 1 })
            });
            const j = await r.json();
            if (j && j.success) {
                // refresh cache and UI
                const list2 = await fetchAllAddresses();
                const def2 = list2.find(a => Number(a.is_default) === 1 || a.is_default === '1');
                pendingSelectedAddressId = def2 ? def2.address_id : null;
                // update account preview if present
                try {
                    renderPreview(list2);
                } catch (e) {}
                // update manager list if modal is open (it was closed) — but keep cache consistent
                try {
                    renderManagerList(list2);
                } catch (e) {}
                // update checkout card if present
                try {
                    const checkoutCard = document.querySelector('.checkout-address-card');
                    if (checkoutCard) {
                        const addr = list2.find(x => String(x.address_id) === String(pendingSelectedAddressId));
                        if (addr) {
                            checkoutCard.setAttribute('data-address-id', addr.address_id);
                            checkoutCard.innerHTML = `\
                                <div class="address-name">${escapeHtml(addr.full_name)} ` +
                                `<span class="address-phone">(${escapeHtml(addr.phone)})</span></div>\
                                <div class="address-details">${escapeHtml(addr.house_number ? addr.house_number + ', ' : '')}${escapeHtml(addr.street)}</div>\
                                <div class="address-details">${escapeHtml(addr.city)}${addr.barangay ? (', Barangay ' + escapeHtml(addr.barangay)) : ''}</div>\
                                <div class="address-details">${escapeHtml(addr.province)}, ${escapeHtml(addr.postal_code)}, ${escapeHtml(addr.country)}</div>\
                                <div class="address-ship"><i class="fa-solid fa-location-dot"></i> ` +
                                `Shipping to this address</div>\
                            `;
                            // ensure it's marked active
                            document.querySelectorAll('.checkout-address-card').forEach(c => c.classList.remove('active'));
                            checkoutCard.classList.add('active');
                        }
                    }
                } catch (e) {}
                notify('Default updated', 'success');
            } else {
                notify('Could not set default', 'error');
            }
        } catch (err) {
            console.error(err);
            notify('Could not set default', 'error');
        }
    });
    // no delete button inside the edit form; deletes are done via the trash icon in the list
    // close modal on ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeModal();
    });

    // initial load
    loadAddresses();
    // expose a global helper so other pages (checkout) can open the address manager modal
    try {
        window.openAddressManager = showManager;
    } catch (e) {
        /* ignore */
    }
    // If redirected from checkout (legacy behavior), open the address manager automatically
    try {
        const sp = new URLSearchParams(window.location.search || '');
        if (sp.get('from') === 'checkout') {
            if (typeof window.openAddressManager === 'function') window.openAddressManager();
            else if (typeof showManager === 'function') showManager();
        }
    } catch (e) { /* ignore */ }
});

// Settings nav behavior: switch panels inside Settings tab
document.addEventListener('DOMContentLoaded', () => {
    const navItems = document.querySelectorAll('.settings-nav-item');
    const panels = document.querySelectorAll('.settings-panel-content');
    function showPanel(id) {
        panels.forEach(p=>{ p.style.display = p.id === id ? '' : 'none'; });
        navItems.forEach(n=>{ n.classList.toggle('active', n.getAttribute('data-panel') === id); });
    }
    if (navItems.length > 0) {
        navItems.forEach(it=>{
            it.addEventListener('click', (e)=>{
                e.preventDefault();
                const action = it.getAttribute('data-action');
                if (action === 'logout') {
                    if (confirm('Are you sure you want to logout?')) window.location.href = 'logout.php';
                    return;
                }
                const target = it.getAttribute('data-panel');
                if (target) showPanel(target);
            });
        });
        // show default
        showPanel('panel-account');
    }

    // logout nav item (if present) - for backward compatibility
    const logoutNav = document.getElementById('settings-logout-nav');
    if (logoutNav) logoutNav.addEventListener('click', (e)=>{ e.preventDefault(); if (confirm('Are you sure you want to logout?')) window.location.href = 'logout.php'; });

    // wire settings buttons
    const manageAddrs = document.getElementById('settings-manage-addresses');
    if (manageAddrs) manageAddrs.addEventListener('click', (e)=>{ e.preventDefault(); if (typeof window.openAddressManager === 'function') window.openAddressManager(); else document.getElementById('open-address-manager') && document.getElementById('open-address-manager').click(); });

    const managePayments = document.getElementById('settings-manage-payments');
    if (managePayments) managePayments.addEventListener('click', (e)=>{ e.preventDefault(); window.location.href = 'account.php#payments'; });

    // Make payment method cards behave like checkout: selectable and optionally show modal for extra info
    const paymentsList = document.getElementById('payments-list');
    function needsPaymentModal(name){ return /gcash|credit|card/i.test(name || ''); }
    if (paymentsList) {
        paymentsList.addEventListener('click', (e)=>{
            const card = e.target.closest('.pm-card');
            if (!card) return;
            const name = card.querySelector('.pm-name') ? card.querySelector('.pm-name').textContent : '';
            const selectCard = ()=>{
                paymentsList.querySelectorAll('.pm-card.active').forEach(c=>c.classList.remove('active'));
                card.classList.add('active');
            };
            if (!needsPaymentModal(name)) {
                selectCard();
            } else {
                // show a lightweight modal for extra info (reuse a simple prompt if payment-modal not present)
                const pmModal = document.getElementById('payment-modal');
                if (pmModal) {
                    // Build a small body depending on method
                    const title = pmModal.querySelector('#pm-modal-title');
                    const body = pmModal.querySelector('#pm-modal-body');
                    const confirm = pmModal.querySelector('#pm-modal-confirm');
                    const cancel = pmModal.querySelector('#pm-modal-cancel');
                    const closeBtn = pmModal.querySelector('.pm-modal-close');
                    title.textContent = name + ' - Additional Info';
                    let html='';
                    if (/gcash/i.test(name)) html = '<div class="form-group"><label>GCash Number</label><input class="input-form" id="pm-field" type="tel" placeholder="09xxxxxxxxx" required></div>';
                    else html = '<div class="form-group"><label>Card Holder Name</label><input class="input-form" id="pm-field" type="text" placeholder="Name on card" required></div>';
                    body.innerHTML = html;
                    pmModal.setAttribute('aria-hidden','false');
                    function hide(){ pmModal.setAttribute('aria-hidden','true'); removeListeners(); }
                    function removeListeners(){ cancel.removeEventListener('click', onCancel); confirm.removeEventListener('click', onConfirm); closeBtn.removeEventListener('click', onCancel); }
                    function onCancel(){ hide(); }
                    function onConfirm(){ const f = document.getElementById('pm-field'); if (f && !f.value.trim()){ alert('Please fill required field'); return; } hide(); selectCard(); }
                    cancel.addEventListener('click', onCancel);
                    confirm.addEventListener('click', onConfirm);
                    closeBtn.addEventListener('click', onCancel);
                } else {
                    const val = prompt('Please enter required info for ' + name + ' (for demo, any value will do)');
                    if (val !== null) selectCard();
                }
            }
        });
    }

    const changePwBtn = document.getElementById('settings-change-password');
    if (changePwBtn) changePwBtn.addEventListener('click', (e)=>{ e.preventDefault(); const panel = document.getElementById('change-password-panel'); if (panel) panel.style.display = panel.style.display === 'none' ? 'block' : 'none'; });

    const saveAccountBtn = document.getElementById('settings-save-account');
    if (saveAccountBtn) saveAccountBtn.addEventListener('click', (e)=>{
        e.preventDefault();
        // copy values into main account form and submit
        const acctForm = document.getElementById('account-form');
        if (acctForm) {
            const aName = document.getElementById('settings-fullname');
            const aPhone = document.getElementById('settings-phone');
            if (aName) document.getElementById('acct-name').value = aName.value || '';
            if (aPhone) document.getElementById('acct-phone').value = aPhone.value || '';
            acctForm.submit();
        }
    });

    const delAccountBtn = document.getElementById('settings-delete-account');
    if (delAccountBtn) delAccountBtn.addEventListener('click', (e)=>{ e.preventDefault(); const confirmDel = confirm('Delete your account? This cannot be undone.'); if (!confirmDel) return; document.getElementById('acct-delete') && document.getElementById('acct-delete').click(); });

    // Edit password link (new UI): show the change-password panel
    const editPwLink = document.getElementById('settings-edit-password');
    if (editPwLink) editPwLink.addEventListener('click', (e)=>{ e.preventDefault(); const panel = document.getElementById('change-password-panel'); if (panel) panel.style.display = panel.style.display === 'none' ? 'block' : 'none'; if (panel) panel.scrollIntoView({behavior:'smooth'}); });

    // 2FA toggle: attempt to notify server (if endpoint exists), otherwise keep client-side state
    const faToggle = document.getElementById('settings-2fa-toggle');
    if (faToggle) faToggle.addEventListener('change', async (e)=>{
        const enable = faToggle.checked ? 1 : 0;
        try {
            const res = await fetch('auth/2fa_toggle.php', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({enable})});
            if (res.ok) {
                const j = await res.json().catch(()=>({success:false}));
                if (j && j.success) notify('Two-factor preference updated','success');
                else notify(j && j.message ? j.message : 'Two-factor update failed','error');
            } else {
                notify('Two-factor update not available on server','error');
            }
        } catch (err) {
            // backend may not implement toggle endpoint; just show a hint
            notify('Two-factor toggle not available (server)', 'info');
        }
    });

    // Logout from all devices
    const logoutAllBtn = document.getElementById('settings-logout-all');
    if (logoutAllBtn) logoutAllBtn.addEventListener('click', async (e)=>{
        e.preventDefault(); if (!confirm('Logout from all devices?')) return;
        try {
            const res = await fetch('auth/logout_all.php', {method:'POST'});
            if (res.ok) {
                const j = await res.json().catch(()=>({success:false}));
                if (j && j.success) { notify('All sessions logged out', 'success'); }
                else notify(j && j.message ? j.message : 'Logout all failed', 'error');
            } else {
                notify('Logout-all endpoint not available on server', 'error');
            }
        } catch (err) {
            notify('Logout-all failed', 'error');
        }
    });

    // Panel logout button (simple confirmation)
    const panelLogoutBtn = document.getElementById('panel-logout-btn');
    if (panelLogoutBtn) panelLogoutBtn.addEventListener('click', (e)=>{ e.preventDefault(); if (!confirm('Are you sure you want to logout?')) return; window.location.href = 'logout.php'; });
});
