// account-tabs.js
// Minimal tab switching for existing profile layout. Uses existing CSS/classes (no visual changes).
document.addEventListener('DOMContentLoaded', ()=>{
    const tabs = Array.from(document.querySelectorAll('.profile-tabs .tab'));
    if (!tabs.length) return;

    // Panels
    const accountPanel = document.querySelector('.account-panel');
    const ordersPanel = document.querySelector('.orders-panel');
    const mannequinPanel = document.querySelector('.mannequin-content');
    const settingsPanel = document.querySelector('.settings-panel');

    function clearActiveTabs(){ tabs.forEach(t=>t.classList.remove('active')); }
    function hideAllPanels(){ 
        if (accountPanel) accountPanel.style.display='none'; 
        if (ordersPanel) ordersPanel.style.display='none'; 
        if (mannequinPanel) mannequinPanel.style.display='none'; 
        if (settingsPanel) settingsPanel.style.display='none'; 
    }

    // Map tab text to handler
    tabs.forEach(tab=>{
        tab.addEventListener('click', (e)=>{
            e.preventDefault();
            // don't re-run if this tab is already active
            if (tab.classList.contains('active')) return;
            const text = (tab.textContent||'').trim().toLowerCase();
            clearActiveTabs(); tab.classList.add('active');
            // update URL hash so direct links work and refreshing preserves selection
            try{ location.hash = '#' + encodeURIComponent(text); }catch(_){ }
            hideAllPanels();
            if (text === 'account'){
                if (accountPanel) accountPanel.style.display = '';
            } else if (text === 'orders'){
                if (ordersPanel) ordersPanel.style.display = '';
                loadOrders();
            } else if (text === 'mannequin'){
                if (mannequinPanel) {
                    mannequinPanel.style.display = '';
                    // ensure the mannequin inner tab defaults to Body Measurements
                    try{
                        if (bodyBtn && otherBtn && bodyTab && otherTab){
                            bodyBtn.classList.add('active'); otherBtn.classList.remove('active');
                            bodyTab.style.display = 'block'; otherTab.style.display = 'none';
                        }
                    }catch(e){/* ignore */}
                    // If the viewer exists, refresh its size (fixes invisible canvas when container was hidden earlier)
                    try{
                        if (window.mannequinAPI && typeof window.mannequinAPI.refresh === 'function') {
                            requestAnimationFrame(()=> window.mannequinAPI.refresh());
                        } else {
                            // wait for the mannequin to be ready and refresh once
                            window.addEventListener('mannequin.ready', () => {
                                if (window.mannequinAPI && typeof window.mannequinAPI.refresh === 'function') window.mannequinAPI.refresh();
                            }, { once: true });
                        }
                    }catch(e){}
                }
            } else if (text === 'settings'){
                // show the Settings panel if present, otherwise fallback to Account
                if (settingsPanel) settingsPanel.style.display = '';
                else if (accountPanel) accountPanel.style.display = '';
            }
        });
    });

    // NOTE: initial tab selection will be performed AFTER inner mannequin controls
    // to avoid a race where inner buttons (bodyTabBtn/otherPrefBtn) are not yet
    // defined when the initial tab click runs. See end of file for init.

    // Orders loader (uses existing API)
    async function loadOrders(){
        const list = document.getElementById('orders-list');
        if (!list) return;
        // If the server already rendered orders (data-hydrated) show them instantly and wire delegated actions
        const serverRendered = list.dataset.hydrated === 'true' || !!list.querySelector('.order-item');
        if (serverRendered) {
            // delegated click handler for cancel/return buttons (works for server-rendered buttons)
            list.addEventListener('click', async (ev) => {
                const btn = ev.target.closest('.cancel-order-btn, .return-product-btn');
                if (!btn) return;
                const isCancel = btn.classList.contains('cancel-order-btn');
                const orderId = btn.dataset.id || btn.getAttribute('data-id');
                if (!orderId) return;
                if (!confirm(isCancel ? 'Cancel this order?' : 'Return this order?')) return;
                btn.disabled = true; btn.textContent = isCancel ? 'Cancelling...' : 'Processing...';
                try{
                    const endpoint = isCancel ? 'api/cancel_order.php' : 'api/return_order.php';
                    const r2 = await fetch(endpoint, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ order_id: orderId }) });
                    const j = await r2.json();
                    if (j.success) {
                        const orderEl = btn.closest('.order-item'); if (!orderEl) return;
                        const header = orderEl.querySelector('.order-header'); const statusSpan = orderEl.querySelector('.order-status');
                        const arrEl = header ? header.querySelector('.order-arrival') : null; if (arrEl) arrEl.remove();
                        if (isCancel) { statusSpan.textContent = 'cancelled'; statusSpan.className = 'order-status cancelled'; orderEl.dataset.status = 'cancelled'; }
                        else { statusSpan.textContent = 'Returned'; statusSpan.className = 'order-status returned'; orderEl.dataset.status = 'returned'; }
                        btn.remove();
                        // update filter counts displayed
                        const fe = document.getElementById('orders-filter');
                        if (fe) {
                            const btns = Array.from(fe.querySelectorAll('.orders-filter-btn'));
                            const counts = { all:0, pending:0, paid:0, shipped:0, confirmed:0, delivered:0, cancelled:0, returned:0 };
                            // count only actual order elements
                            Array.from(list.querySelectorAll('.order-item')).forEach(ch => {
                                const s = (ch.dataset.status||'').toLowerCase(); counts.all += 1;
                                if (s === 'pending') counts.pending += 1;
                                if (s === 'paid') counts.paid += 1;
                                if (s === 'shipped') counts.shipped += 1;
                                if (s === 'confirmed') counts.confirmed += 1;
                                // treat legacy 'complete' as delivered for counting
                                if (s === 'delivered' || s === 'complete') counts.delivered += 1;
                                if (s === 'cancelled') counts.cancelled += 1;
                                if (s === 'returned') counts.returned += 1;
                            });
                            btns.forEach(b => {
                                const val = b.dataset.value; let label = val === 'all' ? 'All' : (val.charAt(0).toUpperCase() + val.slice(1));
                                const count = (val === 'all') ? counts.all : (counts[val] || 0);
                                b.textContent = `${label} (${count})`;
                            });
                            // apply the current filter to the updated order element
                            const active = fe.querySelector('.orders-filter-btn.active-filter');
                            const val = active ? active.dataset.value : 'all';
                            const s = (orderEl.dataset.status||'').toLowerCase();
                            if (val === 'all') orderEl.style.display = '';
                            else if (val === 'delivered') orderEl.style.display = (s === 'delivered' || s === 'complete' ? '' : 'none');
                            else orderEl.style.display = (s === val ? '' : 'none');
                        }
                        try{ if (typeof showNotification === 'function') showNotification(j.message || (isCancel ? 'Order cancelled' : 'Order returned'), 'success'); else alert(j.message || (isCancel ? 'Order cancelled' : 'Order returned')); }catch(e){}
                    } else {
                        alert(j.message || 'Failed to update order'); btn.disabled = false; btn.textContent = isCancel ? 'Cancel Order' : 'Return Order';
                    }
                }catch(err){ console.error(err); alert('Failed to update order'); btn.disabled = false; btn.textContent = isCancel ? 'Cancel Order' : 'Return Order'; }
            });

            // ensure filter button counts reflect server-rendered list (they should already, but recompute defensively)
            try{
                const fe = document.getElementById('orders-filter');
                if (fe) {
                    const btns = Array.from(fe.querySelectorAll('.orders-filter-btn'));
                    const counts = { all:0, pending:0, paid:0, shipped:0, confirmed:0, delivered:0, cancelled:0, returned:0 };
                    Array.from(list.querySelectorAll('.order-item')).forEach(ch => {
                        const s = (ch.dataset.status||'').toLowerCase(); counts.all += 1;
                        if (s === 'pending') counts.pending += 1;
                        if (s === 'paid') counts.paid += 1;
                        if (s === 'shipped') counts.shipped += 1;
                        if (s === 'confirmed') counts.confirmed += 1;
                        if (s === 'delivered' || s === 'complete') counts.delivered += 1;
                        if (s === 'cancelled') counts.cancelled += 1;
                        if (s === 'returned') counts.returned += 1;
                    });
                    btns.forEach(b => {
                        const val = b.dataset.value; let label = val === 'all' ? 'All' : (val.charAt(0).toUpperCase() + val.slice(1));
                        const count = (val === 'all') ? counts.all : (counts[val] || 0);
                        b.textContent = `${label} (${count})`;
                    });
                    // attach click handlers so filters work for server-rendered buttons
                    btns.forEach(btn => btn.addEventListener('click', ()=>{
                        btns.forEach(b=> b.classList.remove('active-filter'));
                        btn.classList.add('active-filter');
                        const val = btn.dataset.value;
                        Array.from(list.children).forEach(ch=>{
                            const s = (ch.dataset.status||'').toLowerCase();
                            if (val === 'all') ch.style.display = '';
                            else if (val === 'delivered') ch.style.display = (s === 'delivered' || s === 'complete' ? '' : 'none');
                            else ch.style.display = (s === val ? '' : 'none');
                        });
                    }));
                    // set default to pending if none active
                    const hasActive = btns.some(b=> b.classList.contains('active-filter'));
                    const defaultBtn = fe.querySelector('.orders-filter-btn[data-value="pending"]');
                    if (!hasActive && defaultBtn) defaultBtn.click();
                }
            }catch(e){}
            return;
        }
        // render immediately; we will append order cards as soon as we have list data
        // Use the site's global showNotification if available, otherwise fall back to alert
        function notify(msg, type = 'info'){
            try{
                if (typeof showNotification === 'function') showNotification(msg, type);
                else alert(msg);
            }catch(e){ try{ alert(msg); }catch(_){} }
        }

    // helper: apply current filter (active tab) to a specific order element
        function applyCurrentFilterToOrder(orderEl){
            try{
                const fe = document.getElementById('orders-filter');
                if (!fe) return;
                const active = fe.querySelector('.orders-filter-btn.active-filter');
                const val = active ? active.dataset.value : 'all';
                const s = (orderEl.dataset.status||'').toLowerCase();
                if (val === 'all') orderEl.style.display = '';
                else if (val === 'delivered') orderEl.style.display = (s === 'delivered' || s === 'complete' ? '' : 'none');
                else orderEl.style.display = (s === val ? '' : 'none');
            }catch(e){}
        }

        // helper: select a filter tab programmatically and apply it
        function selectFilterValue(val){
            try{
                const fe = document.getElementById('orders-filter'); if (!fe) return;
                const btns = Array.from(fe.querySelectorAll('.orders-filter-btn'));
                btns.forEach(b=> b.classList.remove('active-filter'));
                const target = fe.querySelector('.orders-filter-btn[data-value="' + val + '"]');
                if (target) target.classList.add('active-filter');
                // apply filtering same as click handler
                btns.forEach(b=> b.dispatchEvent(new Event('filter:applied')));
                // run our filter logic directly
                const active = fe.querySelector('.orders-filter-btn.active-filter');
                const filterVal = active ? active.dataset.value : 'all';
                const listChildren = Array.from(document.getElementById('orders-list').children);
                listChildren.forEach(ch=>{
                    const s = (ch.dataset.status||'').toLowerCase();
                    if (filterVal === 'all') ch.style.display = '';
                    else if (filterVal === 'delivered') ch.style.display = (s === 'delivered' || s === 'complete' ? '' : 'none');
                    else ch.style.display = (s === filterVal ? '' : 'none');
                });
            }catch(e){}
        }
        try{
            const res = await fetch('api/orders.php', { cache: 'no-store' });
            const data = await res.json();
            // API may return either an array (legacy) or an object { orders: [...], counts: {...} }
            let orders = [];
            let apiCounts = null;
            if (Array.isArray(data)) orders = data;
            else if (data && Array.isArray(data.orders)) { orders = data.orders; apiCounts = data.counts || null; }
            if (!Array.isArray(orders) || orders.length === 0){
                // show styled empty orders block (matches empty cart)
                const emptyHtml = '<div class="empty-orders"><div class="empty-orders-content"><i class="fa fa-box"></i><h2>No orders yet</h2><p>Looks like you haven\'t placed any orders yet.</p><a href="index.php" class="shop-now-btn">Shop Now</a></div></div>';
                list.innerHTML = emptyHtml;
                return;
            }
            // If API provided counts, update the filter buttons immediately to avoid any flash of wrong/global totals
            try{
                if (apiCounts) {
                    const fe = document.getElementById('orders-filter');
                    if (fe){
                        const btns = Array.from(fe.querySelectorAll('.orders-filter-btn'));
                        btns.forEach(btn => {
                            const val = btn.dataset.value;
                            let label = val === 'all' ? 'All' : (val.charAt(0).toUpperCase() + val.slice(1));
                            const count = (val === 'all') ? apiCounts.all : (apiCounts[val] || 0);
                            btn.textContent = `${label} (${count})`;
                        });
                    }
                }
            }catch(e){}
            list.innerHTML = '';

            // Immediately render order cards (no waiting). Show placeholder for items and fill them as responses arrive.
            const orderEls = [];
            orders.forEach((o) => {
                // create placeholder order element and append immediately
                const orderEl = document.createElement('div');
                orderEl.className = 'order-item';
                orderEl.dataset.status = (o.status || '').toLowerCase();

                // Header: compute arrival but show only for pending; for cancelled/returned show the updated date
                const header = document.createElement('div');
                header.className = 'order-header';
                let arrivalText = 'Arrives in 3-5 days';
                try{
                    if (o.created_at) {
                        const d = new Date(o.created_at.replace(' ', 'T'));
                        if (!isNaN(d.getTime())){
                            const start = new Date(d); start.setDate(start.getDate() + 3);
                            const end = new Date(d); end.setDate(end.getDate() + 5);
                            const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                            const sMon = months[start.getMonth()];
                            const eMon = months[end.getMonth()];
                            if (sMon === eMon) arrivalText = `Arrives on ${sMon} ${start.getDate()}-${end.getDate()}`;
                            else arrivalText = `Arrives on ${sMon} ${start.getDate()} - ${eMon} ${end.getDate()}`;
                        }
                    }
                }catch(e){}
                const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                const statusLower = ((o.status||'').toLowerCase());
                let dateInfoHtml = '';
                if (statusLower === 'pending') {
                    // Pending = Placed on (show date+time)
                    if (o.created_at) {
                        try{
                            const d = new Date((o.created_at || '').replace(' ', 'T'));
                            if (!isNaN(d.getTime())) {
                                const when = `${months[d.getMonth()]} ${d.getDate()}, ${d.getFullYear()} | ${d.getHours()%12||12}:${String(d.getMinutes()).padStart(2,'0')}${d.getHours() >= 12 ? 'pm' : 'am'}`;
                                dateInfoHtml = `<p class="order-arrival">Placed on ${when}</p>`;
                            }
                        }catch(e){}
                    }
                } else if (statusLower === 'paid') {
                    // Paid = Paid via (Gcash, Credit card only) on
                    const raw = o.updated_at || o.created_at || null;
                    let when = '';
                    if (raw) {
                        try{
                            const d = new Date(raw.replace(' ', 'T'));
                            if (!isNaN(d.getTime())) when = `${months[d.getMonth()]} ${d.getDate()}, ${d.getFullYear()} | ${d.getHours()%12||12}:${String(d.getMinutes()).padStart(2,'0')}${d.getHours() >= 12 ? 'pm' : 'am'}`;
                        }catch(e){}
                    }
                    const pm = (o.payment_method || '').toString();
                    const showVia = ['gcash','credit card'].includes(pm.toLowerCase());
                    dateInfoHtml = `<p class="order-arrival">Paid${showVia && pm ? ' via ' + pm : ''}${when ? ' on ' + when : ''}</p>`;
                } else if (statusLower === 'shipped') {
                    // Shipped = Arrives on (3-5 days after created_at) - no time
                    if (o.created_at) {
                        try{
                            const d = new Date(o.created_at.replace(' ', 'T'));
                            if (!isNaN(d.getTime())){
                                const start = new Date(d); start.setDate(start.getDate() + 3);
                                const end = new Date(d); end.setDate(end.getDate() + 5);
                                const sMon = months[start.getMonth()]; const eMon = months[end.getMonth()];
                                const startFmt = `${sMon} ${start.getDate()}`;
                                const endFmt = `${eMon} ${end.getDate()}`;
                                dateInfoHtml = `<p class="order-arrival">Arrives on ${startFmt}${startFmt === endFmt ? '' : ' - ' + endFmt}</p>`;
                            }
                        }catch(e){}
                    }
                } else if (statusLower === 'confirmed') {
                    // Confirmed = Confirmed on (use updated_at if present)
                    const raw = o.updated_at || o.created_at || null;
                    if (raw) {
                        try{
                            const d2 = new Date(raw.replace(' ', 'T'));
                            if (!isNaN(d2.getTime())) {
                                const when = `${months[d2.getMonth()]} ${d2.getDate()}, ${d2.getFullYear()} | ${d2.getHours()%12||12}:${String(d2.getMinutes()).padStart(2,'0')}${d2.getHours() >= 12 ? 'pm' : 'am'}`;
                                dateInfoHtml = `<p class="order-arrival">Confirmed on ${when}</p>`;
                            }
                        }catch(e){}
                    }
                } else if (statusLower === 'delivered') {
                    const raw = o.updated_at || o.created_at || null;
                    if (raw) {
                        try{
                            const d2 = new Date(raw.replace(' ', 'T'));
                            if (!isNaN(d2.getTime())) {
                                const when = `${months[d2.getMonth()]} ${d2.getDate()}, ${d2.getFullYear()} | ${d2.getHours()%12||12}:${String(d2.getMinutes()).padStart(2,'0')}${d2.getHours() >= 12 ? 'pm' : 'am'}`;
                                dateInfoHtml = `<p class="order-arrival">Delivered on ${when}</p>`;
                            }
                        }catch(e){}
                    }
                } else if (statusLower === 'cancelled') {
                    const raw = o.updated_at || o.created_at || null;
                    if (raw) {
                        try{
                            const d2 = new Date(raw.replace(' ', 'T'));
                            if (!isNaN(d2.getTime())) {
                                const when = `${months[d2.getMonth()]} ${d2.getDate()}, ${d2.getFullYear()} | ${d2.getHours()%12||12}:${String(d2.getMinutes()).padStart(2,'0')}${d2.getHours() >= 12 ? 'pm' : 'am'}`;
                                dateInfoHtml = `<p class="order-arrival">Cancelled on ${when}</p>`;
                            }
                        }catch(e){}
                    }
                } else if (statusLower === 'returned') {
                    const raw = o.updated_at || o.created_at || null;
                    if (raw) {
                        try{
                            const d2 = new Date(raw.replace(' ', 'T'));
                            if (!isNaN(d2.getTime())) {
                                const when = `${months[d2.getMonth()]} ${d2.getDate()}, ${d2.getFullYear()} | ${d2.getHours()%12||12}:${String(d2.getMinutes()).padStart(2,'0')}${d2.getHours() >= 12 ? 'pm' : 'am'}`;
                                dateInfoHtml = `<p class="order-arrival">Returned on ${when}</p>`;
                            }
                        }catch(e){}
                    }
                }
                header.innerHTML = `<div class="order-left"></div><div class="order-right">${dateInfoHtml}</div>`;
                const statusSpan = document.createElement('span');
                statusSpan.className = 'order-status ' + (o.status ? o.status.toLowerCase() : 'pending');
                statusSpan.textContent = o.status || '';
                const left = header.querySelector('.order-left'); if (left) left.appendChild(statusSpan);
                orderEl.appendChild(header);

                // content placeholder
                const content = document.createElement('div');
                content.className = 'order-content';
                content.innerHTML = '<div class="loading">Loading items...</div>';
                orderEl.appendChild(content);

                // Footer (total and actions) - actions will be updated after items/status are loaded
                const footer = document.createElement('div');
                footer.className = 'order-footer';
                const totalDiv = document.createElement('div');
                totalDiv.className = 'order-total';
                totalDiv.innerHTML = `<p class="order-total-label">Total</p><p class="order-total-amount">₱${Number(o.total_amount || 0).toFixed(2)}</p>`;
                const actionsDiv = document.createElement('div'); actionsDiv.className = 'order-footer-actions';
                footer.appendChild(totalDiv); footer.appendChild(actionsDiv);
                orderEl.appendChild(footer);

                orderEl.style.marginBottom = '18px';
                orderEls.push({ order: o, el: orderEl, header, content, footer, actionsDiv, statusSpan });
                list.appendChild(orderEl);
            });

            // Fetch each order's items asynchronously and populate the corresponding card when ready
            orders.forEach(async (o, idx) => {
                try{
                    const r = await fetch('api/orders.php?order_id=' + encodeURIComponent(o.order_id), { cache: 'no-store' });
                    const items = await r.json();
                    const entry = orderEls.find(e => Number(e.order.order_id) === Number(o.order_id));
                    if (!entry) return;
                    const { el: orderEl, content, actionsDiv, statusSpan, header } = entry;
                    // filter out deleted/removed items
                    const visibleItems = Array.isArray(items) ? items.filter(it => (it.product_name && String(it.product_name).trim().length>0)) : [];
                    if (visibleItems.length === 0) { orderEl.remove(); try{ updateOrdersSummary(); }catch(e){}; return; }
                    // build items
                    content.innerHTML = '';
                    visibleItems.forEach(it => {
                        const p = document.createElement('div'); p.className = 'order-product';
                        p.innerHTML = `<div class="order-product-image"><img src="${it.thumbnail_path || it.thumbnail || 'uploads/thumbnail_img/noimg.png'}" alt=""></div>
                                    <div class="order-product-details">
                                      <p class="order-product-name">${it.product_name}</p>
                                      <p class="order-product-variant">Size: ${it.size || '—'}</p>
                                      <p class="product-quantity">Quantity: ${it.quantity}</p>
                                    </div>
                                    <div class="order-product-price">₱${Number(it.product_price).toFixed(2)}</div>`;
                        content.appendChild(p);
                    });

                    // update actions area depending on status
                    actionsDiv.innerHTML = '';
                    const statusLower = (o.status||'').toLowerCase();
                    const cancellable = ['pending','paid'].includes(statusLower);
                    // treat legacy 'complete' as delivered; use 'delivered' as canonical status
                    const completable = (statusLower === 'delivered' || statusLower === 'complete');
                    if (cancellable) {
                        const cancelBtn = document.createElement('button');
                        cancelBtn.className = 'cancel-order-btn action-btn danger';
                        cancelBtn.textContent = 'Cancel Order';
                        cancelBtn.dataset.id = String(o.order_id);
                        cancelBtn.addEventListener('click', async ()=>{
                            if (!confirm('Cancel this order?')) return;
                            cancelBtn.disabled = true; cancelBtn.textContent = 'Cancelling...';
                            try{
                                const r2 = await fetch('api/cancel_order.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ order_id: o.order_id }) });
                                const j = await r2.json();
                                if (j.success) {
                                    // update UI immediately: remove arrival, mark cancelled, re-filter, and show toast
                                    const arrEl = header.querySelector('.order-arrival'); if (arrEl) arrEl.remove();
                                    statusSpan.textContent = 'Cancelled'; statusSpan.className = 'order-status cancelled';
                                    orderEl.dataset.status = 'cancelled';
                                    cancelBtn.remove();
                                    try{ updateOrdersSummary(); }catch(e){}
                                    applyCurrentFilterToOrder(orderEl);
                                        // switch to the Cancelled tab so the user sees the cancelled orders
                                        selectFilterValue('cancelled');
                                        notify(j.message || 'Order cancelled', 'success');
                                } else {
                                    alert(j.message || 'Failed to cancel');
                                    cancelBtn.disabled = false; cancelBtn.textContent = 'Cancel Order';
                                }
                            }catch(err){ console.error(err); alert('Failed to cancel'); cancelBtn.disabled = false; cancelBtn.textContent = 'Cancel Order'; }
                        });
                        actionsDiv.appendChild(cancelBtn);
                    } else if (completable) {
                        const returnBtn = document.createElement('button');
                        returnBtn.className = 'return-product-btn action-btn';
                        returnBtn.textContent = 'Return Order';
                        returnBtn.dataset.id = String(o.order_id);
                        returnBtn.addEventListener('click', async ()=>{
                            if (!confirm('Return this order?')) return;
                            returnBtn.disabled = true; returnBtn.textContent = 'Processing...';
                            try{
                                const r2 = await fetch('api/return_order.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ order_id: o.order_id }) });
                                if (!r2.ok) { const txt = await r2.text(); throw new Error(txt || 'Server error'); }
                                const j = await r2.json();
                                if (j.success) {
                                    const arrEl = header.querySelector('.order-arrival'); if (arrEl) arrEl.remove();
                                    statusSpan.textContent = 'Returned'; statusSpan.className = 'order-status returned';
                                    orderEl.dataset.status = 'returned';
                                    returnBtn.remove();
                                    try{ updateOrdersSummary(); }catch(e){}
                                    applyCurrentFilterToOrder(orderEl);
                                    selectFilterValue('returned');
                                    notify(j.message || 'Order returned', 'success');
                                } else {
                                    alert(j.message || 'Failed to return');
                                    returnBtn.disabled = false; returnBtn.textContent = 'Return Order';
                                }
                            }catch(err){ console.error(err); alert('Failed to return order. Server may not support returns yet.'); returnBtn.disabled = false; returnBtn.textContent = 'Return Order'; }
                        });
                        actionsDiv.appendChild(returnBtn);
                    }

                    try{ updateOrdersSummary(); }catch(e){}
                }catch(e){ console.warn('Failed fetching items for order', o.order_id, e); }
            });

            // order cards were appended immediately during creation above

            // Hook up filter UI (button tabs). Default to Pending.
            const filterEl = document.getElementById('orders-filter');
            let filterButtons = [];
            function updateOrdersSummary(){
                const counts = { all: 0, pending: 0, paid: 0, shipped: 0, confirmed: 0, delivered: 0, cancelled: 0, returned: 0 };
                Array.from(list.children).forEach(ch => {
                    const s = (ch.dataset.status||'').toLowerCase();
                    counts.all += 1;
                    if (s === 'pending') counts.pending += 1;
                    if (s === 'paid') counts.paid += 1;
                    if (s === 'shipped') counts.shipped += 1;
                    if (s === 'confirmed') counts.confirmed += 1;
                    if (s === 'delivered' || s === 'complete') counts.delivered += 1;
                    if (s === 'cancelled') counts.cancelled += 1;
                    if (s === 'returned') counts.returned += 1;
                });

                if (!filterEl) return;
                filterButtons = Array.from(filterEl.querySelectorAll('.orders-filter-btn'));
                filterButtons.forEach(btn => {
                    const val = btn.dataset.value;
                    let label = val === 'all' ? 'All' : (val.charAt(0).toUpperCase() + val.slice(1));
                    const count = (val === 'all') ? counts.all : (counts[val] || 0);
                    btn.textContent = `${label} (${count})`;
                });

                // If the currently visible (filtered) order count is zero, show a friendly empty state
                try{
                    const visibleOrders = Array.from(list.children).filter(ch => ch.classList && ch.classList.contains('order-item') && ch.style.display !== 'none');
                    const existingEmpty = list.querySelector('.empty-orders');
                    if (visibleOrders.length === 0) {
                        // determine message based on active filter
                        const active = filterEl.querySelector('.orders-filter-btn.active-filter');
                        const val = active ? active.dataset.value : 'all';
                        let title = 'No orders yet';
                        if (val === 'pending') title = 'No pending orders yet';
                        else if (val === 'paid') title = 'No paid orders yet';
                        else if (val === 'shipped') title = 'No shipped orders yet';
                        else if (val === 'confirmed') title = 'No confirmed orders yet';
                        else if (val === 'delivered') title = 'No delivered orders yet';
                        else if (val === 'cancelled') title = 'No cancelled orders yet';
                        else if (val === 'returned') title = 'No returned orders yet';
                        const emptyHtml = `<div class="empty-orders"><div class="empty-orders-content"><i class="fa fa-box"></i><h2>${title}</h2><p>Looks like you don't have any orders in this section yet.</p><a href="index.php" class="shop-now-btn">Shop Now</a></div></div>`;
                        if (!existingEmpty) list.insertAdjacentHTML('beforeend', emptyHtml);
                    } else {
                        if (existingEmpty) existingEmpty.remove();
                    }
                }catch(e){}
            }

                if (filterEl){
                filterButtons = Array.from(filterEl.querySelectorAll('.orders-filter-btn'));
                filterButtons.forEach(btn => btn.addEventListener('click', ()=>{
                    filterButtons.forEach(b=> b.classList.remove('active-filter'));
                    btn.classList.add('active-filter');
                    const val = btn.dataset.value;
                    Array.from(list.children).forEach(ch=>{
                        const s = (ch.dataset.status||'').toLowerCase();
                        if (val === 'all') ch.style.display = '';
                        else if (val === 'delivered') ch.style.display = (s === 'delivered' || s === 'complete' ? '' : 'none');
                        else ch.style.display = (s === val ? '' : 'none');
                    });
                }));

                // set default selected tab to 'pending' if none active
                const hasActive = filterButtons.some(b=> b.classList.contains('active-filter'));
                const defaultBtn = filterEl.querySelector('.orders-filter-btn[data-value="pending"]');
                if (!hasActive && defaultBtn) {
                    // trigger the click handler so filtering is applied to the already-rendered list
                    defaultBtn.click();
                }
            }

            // expose updateOrdersSummary so other actions (like cancel) can call it
            updateOrdersSummary();

            // remove reserved minHeight after render
            list.style.minHeight = '';
        }catch(err){ console.error('Failed to load orders', err); list.textContent = 'Failed to load orders'; }
    }

    // Account form actions: minimal; POST to api/update_profile.php if available
    const acctForm = document.getElementById('account-form') || document.getElementById('account-form');
    if (acctForm){
        acctForm.addEventListener('submit', async (e)=>{
            e.preventDefault();
            const name = document.getElementById('acct-name').value.trim();
            const pw = document.getElementById('acct-pw').value;
            try{
                const res = await fetch('api/update_profile.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({name,name,password:pw})});
                const j = await res.json();
                if (j.success) alert('Profile updated'); else alert(j.message||'Failed to update');
            }catch(err){ alert('Failed to update'); console.error(err); }
        });
    }

    const delBtn = document.getElementById('acct-delete');
    if (delBtn) delBtn.addEventListener('click', async ()=>{
        // Prevent account deletion if the user has active orders in these statuses
        function getCountForStatus(val){
            try{
                const fe = document.getElementById('orders-filter'); if (!fe) return 0;
                const btn = fe.querySelector('.orders-filter-btn[data-value="' + val + '"]');
                if (!btn) return 0;
                const m = btn.textContent.match(/\((\d+)\)/);
                return m ? parseInt(m[1],10) : 0;
            }catch(e){ return 0; }
        }

        const blocked = ['pending','confirmed','shipped','paid'].filter(s => getCountForStatus(s) > 0);
        if (blocked.length > 0) {
            const pretty = blocked.map(s => s.charAt(0).toUpperCase() + s.slice(1)).join(', ');
            alert('You cannot delete your account while you have orders in these statuses: ' + pretty + '. Please cancel or resolve them first.');
            return;
        }

        if (!confirm('Delete your account? This is irreversible.')) return;
        try{
            const res = await fetch('api/delete_account.php',{method:'POST'});
            if (!res.ok) {
                const txt = await res.text();
                try{ const j = JSON.parse(txt); alert(j.message || 'Failed to delete account'); }
                catch(e){ alert('Failed to delete account: ' + (txt || res.statusText)); }
                return;
            }
            const j = await res.json();
            if (j && j.success) window.location.href = 'index.php'; else alert(j && j.message ? j.message : 'Failed to delete');
        }catch(err){ console.error(err); alert('Failed to delete'); }
    });

    // Inner mannequin tab switching (Body Measurements / Other Preferences)
    const bodyBtn = document.getElementById('bodyTabBtn');
    const otherBtn = document.getElementById('otherPrefBtn');
    const bodyTab = document.getElementById('bodyTab');
    const otherTab = document.getElementById('otherPrefTab');
    if (bodyBtn && otherBtn && bodyTab && otherTab){
        bodyBtn.addEventListener('click', ()=>{
            bodyBtn.classList.add('active'); otherBtn.classList.remove('active');
            bodyTab.style.display = 'block'; otherTab.style.display = 'none';
        });
        otherBtn.addEventListener('click', ()=>{
            otherBtn.classList.add('active'); bodyBtn.classList.remove('active');
            bodyTab.style.display = 'none'; otherTab.style.display = 'block';
        });
    }

    // Listen for preference buttons and emit custom events for the mannequin viewer
    // Prefer direct listeners for preference buttons (more reliable than delegation for some browsers)
    function wirePrefButtons(){
        // skin buttons: show a selected state via aria-pressed
        document.querySelectorAll('.skin-btn').forEach(b=> b.addEventListener('click', (ev)=>{
            const color=b.getAttribute('data-skin');
            // update aria-pressed on all skin buttons
            document.querySelectorAll('.skin-btn').forEach(x=> x.setAttribute('aria-pressed', 'false'));
            b.setAttribute('aria-pressed', 'true');
            window.dispatchEvent(new CustomEvent('mannequin.skin',{detail:{color}}));
        }));

        // face/body/pose: toggle active class among siblings in the same group
        document.querySelectorAll('.face-btn').forEach(b=> b.addEventListener('click', (ev)=>{
            const morph=b.getAttribute('data-morph');
            document.querySelectorAll('.face-btn').forEach(x=> x.classList.remove('active'));
            b.classList.add('active');
            window.dispatchEvent(new CustomEvent('mannequin.face',{detail:{morph}}));
        }));
        document.querySelectorAll('.bodyshape-btn').forEach(b=> b.addEventListener('click', (ev)=>{
            const morph=b.getAttribute('data-morph');
            document.querySelectorAll('.bodyshape-btn').forEach(x=> x.classList.remove('active'));
            b.classList.add('active');
            window.dispatchEvent(new CustomEvent('mannequin.shape',{detail:{morph}}));
        }));
        document.querySelectorAll('.pose-btn').forEach(b=> b.addEventListener('click', (ev)=>{
            const morph=b.getAttribute('data-morph');
            document.querySelectorAll('.pose-btn').forEach(x=> x.classList.remove('active'));
            b.classList.add('active');
            window.dispatchEvent(new CustomEvent('mannequin.pose',{detail:{morph}}));
        }));
    }
    // wire now and also after any dynamic loads
    wirePrefButtons();

    // Save measurements / preferences to server when Save button clicked
    (function(){
        const saveBtn = document.getElementById('save-measurements');
        if (!saveBtn) return;
        saveBtn.addEventListener('click', async (ev)=>{
            ev.preventDefault();
            // read sliders and metric selects
            function readMetric(id){
                const slider = document.getElementById(id);
                if (!slider) return null;
                const parent = slider.parentElement;
                const metric = parent.querySelector('.metric-select') ? parent.querySelector('.metric-select').value : 'cm';
                let val = parseFloat(slider.value);
                if (metric === 'inch') val = val * 2.54; // convert to cm
                return Math.round(val * 100) / 100;
            }

            const payload = {
                shoulder_width: readMetric('shoulders'),
                chest_bust: readMetric('chest'),
                waist: readMetric('waist'),
                torso_length: readMetric('torso'),
                arm_length: readMetric('arms'),
                body_shape: (document.querySelector('.bodyshape-btn.active') || {}).getAttribute ? (document.querySelector('.bodyshape-btn.active')?.getAttribute('data-morph')) : null,
                face_shape: (document.querySelector('.face-btn.active') || {}).getAttribute ? (document.querySelector('.face-btn.active')?.getAttribute('data-morph')) : null,
                skin_tone: (document.querySelector('.skin-btn[aria-pressed="true"]') || {}).getAttribute ? (document.querySelector('.skin-btn[aria-pressed="true"]')?.getAttribute('data-skin')) : null
            };

            try{
                const resp = await fetch('api/save_mannequin.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) });
                const j = await resp.json();
                if (j && j.success) {
                    try{ if (typeof showNotification === 'function') showNotification(j.message || 'Saved', 'success'); else alert(j.message || 'Saved'); }catch(e){}
                } else {
                    try{ alert((j && j.message) || 'Failed to save mannequin'); }catch(e){}
                }
            }catch(err){ console.error(err); alert('Failed to save mannequin'); }
        });
    })();

    // Load saved mannequin values into sliders and preference buttons
    (function(){
        async function applySavedToUI(saved){
            if (!saved) return;
            try{
                // sliders: shoulders, chest, waist, arms, torso
                const mapping = {
                    shoulders: saved['shoulder_width'],
                    chest: saved['chest_bust'],
                    waist: saved['waist'],
                    arms: saved['arm_length'] || saved['height'] || null,
                    torso: saved['torso_length']
                };
                Object.keys(mapping).forEach(id => {
                    const val = mapping[id];
                    const slider = document.getElementById(id);
                    if (!slider || val === null || typeof val === 'undefined') return;
                    // set numeric value and update paired displays
                    slider.value = String(val);
                    const parent = slider.parentElement;
                    const display = parent ? parent.querySelector('.value-display') : null;
                    if (display) display.value = String(val);
                    const metric = parent ? parent.querySelector('.metric-select') : null;
                    if (metric) metric.value = 'cm';
                    // trigger input event to let any listeners update morphs
                    slider.dispatchEvent(new Event('input'));
                });

                // skin tone
                if (saved.skin_tone) {
                    document.querySelectorAll('.skin-btn').forEach(b=> b.setAttribute('aria-pressed','false'));
                    const btn = document.querySelector('.skin-btn[data-skin="' + saved.skin_tone + '"]');
                    if (btn) btn.setAttribute('aria-pressed','true');
                }

                // face/body buttons
                if (saved.face_shape) {
                    document.querySelectorAll('.face-btn').forEach(b=> b.classList.remove('active'));
                    const fb = document.querySelector('.face-btn[data-morph="' + saved.face_shape + '"]');
                    if (fb) fb.classList.add('active');
                }
                if (saved.body_shape) {
                    document.querySelectorAll('.bodyshape-btn').forEach(b=> b.classList.remove('active'));
                    const bb = document.querySelector('.bodyshape-btn[data-morph="' + saved.body_shape + '"]');
                    if (bb) bb.classList.add('active');
                }

                // If viewer is ready, set its skin/morphs too
                function applyToViewer(){
                    try{
                        if (!window.mannequinAPI) return;
                        if (saved.skin_tone && typeof window.mannequinAPI.setSkinTone === 'function') window.mannequinAPI.setSkinTone(saved.skin_tone);
                        if (saved.face_shape && typeof window.mannequinAPI.setMorphExclusive === 'function') window.mannequinAPI.setMorphExclusive(saved.face_shape, [saved.face_shape]);
                        if (saved.body_shape && typeof window.mannequinAPI.setMorphExclusive === 'function') window.mannequinAPI.setMorphExclusive(saved.body_shape, [saved.body_shape]);
                        // measurements: convert to influence similarly to product.js mapping
                        const mapping = {
                            shoulder_width: 'Shoulders', chest_bust: 'Chest', waist: 'Waist', torso_length: 'Torso', arm_length: 'Arms'
                        };
                        const ranges = {
                            'Shoulders': [40,55], 'Chest':[80,110], 'Waist':[70,100], 'Torso':[50,80], 'Arms':[150,200]
                        };
                        Object.keys(mapping).forEach(k => {
                            const v = saved[k]; if (v === null || typeof v === 'undefined') return;
                            const morphName = mapping[k]; const rng = ranges[morphName];
                            if (!rng) return;
                            const influence = (v - rng[0])/(rng[1]-rng[0]);
                            // apply by traversing mannequin
                            if (window.mannequin) {
                                window.mannequin.traverse(child => {
                                    if (child.isMesh && child.morphTargetDictionary && child.morphTargetDictionary[morphName] !== undefined){
                                        const idx = child.morphTargetDictionary[morphName];
                                        child.morphTargetInfluences[idx] = Math.max(0, Math.min(1, influence));
                                    }
                                });
                            }
                        });
                    }catch(e){ console.warn('applyToViewer failed', e); }
                }

                if (window.mannequin && window.mannequinAPI) applyToViewer();
                else window.addEventListener('mannequin.ready', applyToViewer, { once: true });
            }catch(e){ console.warn('Failed to apply saved mannequin to UI', e); }
        }

        // load on DOM ready
        (async function(){
            try{
                const res = await fetch('api/get_mannequin.php', { cache: 'no-store' });
                if (!res.ok) return;
                const json = await res.json();
                if (json) await applySavedToUI(json);
            }catch(e){ /* ignore */ }
        })();
    })();

    // Now that inner controls are wired, pick the initial tab (hash or active/default)
    (function pickInitialTab(){
        let desired = null;
        if (location.hash && location.hash.length>1) desired = decodeURIComponent(location.hash.slice(1)).toLowerCase();
        else {
            const sp = new URLSearchParams(location.search);
            if (sp.get('tab')) desired = sp.get('tab').toLowerCase();
        }
        let initTab = null;
        if (desired) initTab = tabs.find(t => (t.textContent||'').trim().toLowerCase() === desired);
        if (!initTab) initTab = tabs.find(t=>t.classList.contains('active')) || tabs[0];
        if (initTab) {
            // small defer to let the browser finish layout changes
            requestAnimationFrame(()=> initTab.click());
        }
    })();
});
