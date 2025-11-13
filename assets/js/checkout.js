// Checkout page JS moved from checkout.php
async function loadCheckout(){
    // Start all fetches concurrently so the UI can render together
    let addrsPromise, pmPromise, itemsPromise;
    if (typeof window !== 'undefined' && window.INIT_CHECKOUT_DATA) {
        // Use server-provided initial data to avoid flashes on first load
        addrsPromise = Promise.resolve(window.INIT_CHECKOUT_DATA.addresses || []);
        pmPromise = Promise.resolve(window.INIT_CHECKOUT_DATA.payment_methods || []);
        itemsPromise = Promise.resolve(window.INIT_CHECKOUT_DATA.items || []);
        // optional: remove the global to free memory
        try{ delete window.INIT_CHECKOUT_DATA; }catch(e){}
    } else {
        addrsPromise = fetch('api/addresses.php').then(r=>r.json()).catch(()=>[]);
        pmPromise = fetch('api/get_payment_methods.php').then(r=>r.json()).catch(()=>[]);
        itemsPromise = fetch('api/fetch_cart_items.php').then(r=>r.json()).catch(()=>[]);
    }

    const [addrs, pm, items] = await Promise.all([addrsPromise, pmPromise, itemsPromise]);

    // addresses (render as a single clickable card)
    const list = document.getElementById('addresses-list');
    if (list) {
        list.innerHTML = '';
        let chosen = (Array.isArray(addrs) ? addrs.find(x=>x.is_default) || addrs[0] : null);
        if (chosen) {
            const card = document.createElement('div');
            card.className = 'checkout-address-card active';
            card.setAttribute('data-address-id', chosen.address_id);
            card.innerHTML = `
                <div class="address-name">${chosen.full_name} <span class="address-phone">(${chosen.phone})</span></div>
                <div class="address-details">${chosen.house_number ? chosen.house_number + ', ' : ''}${chosen.street}</div>
                <div class="address-details">${chosen.city}${chosen.barangay ? ', Barangay ' + chosen.barangay : ''}</div>
                <div class="address-details">${chosen.province}, ${chosen.postal_code}, ${chosen.country}</div>
                <div class="address-ship"><i class="fa-solid fa-location-dot"></i> Shipping to this address</div>
            `;
            card.title = 'Click to manage or change address';
            card.addEventListener('click', ()=>{
                // Open the account address manager modal if available, otherwise fallback to the addresses page
                    if (typeof window.openAddressManager === 'function') {
                    window.openAddressManager();
                } else {
                    // legacy fallback: open the account page and instruct it to open the address manager
                    window.location.href = 'account.php?from=checkout';
                }
            });
            list.appendChild(card);
        } else {
            const card = document.createElement('div');
            card.className = 'checkout-address-card';
            card.innerHTML = '<div class="no-address">No address found. Click to add an address.</div>';
            card.addEventListener('click', ()=>{
                if (typeof window.openAddressManager === 'function') {
                    window.openAddressManager();
                } else {
                    // legacy fallback: open the account page and instruct it to open the address manager
                    window.location.href = 'account.php?from=checkout';
                }
            });
            list.appendChild(card);
        }
    }

    // payment methods (render as address-like cards with radio on the right)
    const pmContainer = document.getElementById('payment-methods');
    function needsModal(name){ return /gcash|credit|card/i.test(name || ''); }
    if (pmContainer) {
        pmContainer.innerHTML='';
        const methods = Array.isArray(pm) ? pm : [];
        // choose initial selection if the first method does not require extra info
        const initialIndex = (methods[0] && !needsModal(methods[0].name)) ? 0 : -1;
        methods.forEach((m,i)=>{
            const card = document.createElement('div');
                card.className = 'pm-card';
                card.setAttribute('data-method-id', String(m.method_id));
                if (i === initialIndex) card.classList.add('active');
                card.innerHTML = `
                    <div class="pm-left">
                        <div class="pm-info">
                            <div class="pm-name">${m.name}</div>
                            <div class="pm-desc">${m.description || ''}</div>
                        </div>
                    </div>
                `;
                // click on card selects it (but if method requires modal, show it first)
                card.addEventListener('click', (e)=>{
                    const selectCard = ()=>{
                        // remove active from siblings
                        pmContainer.querySelectorAll('.pm-card.active').forEach(c=>c.classList.remove('active'));
                        card.classList.add('active');
                    };
                    if (!needsModal(m.name)){
                        selectCard();
                    } else {
                        // show modal for extra info, only mark active if confirmed
                        showPaymentModal(m, (extraData)=>{ if (extraData !== null) selectCard(); });
                    }
                });

                pmContainer.appendChild(card);
        });
    }

    // items (styled like cart items)
    const container = document.getElementById('checkout-items');
    if (container) {
        container.innerHTML='';
        let subtotal=0;
        let totalQty = 0;
        (Array.isArray(items) ? items : []).forEach(it=>{
            const div = document.createElement('div');
            div.className = 'checkout-item';
            div.innerHTML = `
                <div class="checkout-item-image"><img src="${it.image_url || it.thumbnail_path || 'uploads/thumbnail_img/noimg.png'}" alt="${it.name}"></div>
                <div class="checkout-item-details">
                    <div class="checkout-item-name">${it.name}</div>
                    <div class="checkout-item-size">Size: ${it.size || '-'}</div>
                    <div class="checkout-item-qty">Qty: ${it.quantity}</div>
                </div>
                <div class="checkout-item-price">₱${Number(it.price).toFixed(2)}</div>
            `;
            container.appendChild(div);
            subtotal += Number(it.price)*Number(it.quantity);
            totalQty += Number(it.quantity || 0);
        });

        // update items count in summary
        const itemsCountEl = document.getElementById('items-count');
        if (itemsCountEl) itemsCountEl.textContent = String(totalQty);

        // Prices are tax-inclusive. Show subtotal (tax included), compute the tax portion only for display.
        const shipping = 49.00;
        document.getElementById('subtotal').textContent = subtotal.toFixed(2);
        // tax portion when prices are tax-inclusive: tax = subtotal * (taxRate / (1 + taxRate))
        const taxPortion = subtotal * 0.12;
        document.getElementById('tax').textContent = taxPortion.toFixed(2);
        const total = subtotal + shipping; // do not add tax because subtotal already includes it
        document.getElementById('total').textContent = total.toFixed(2);
    }

    // All dynamic content is now rendered - remove loading flag so elements don't flash
    try{ document.body.removeAttribute('data-loading'); }catch(e){}
}

// Payment modal helpers
function showPaymentModal(method, onConfirm){
    const modal = document.getElementById('payment-modal');
    if (!modal) { if (typeof onConfirm === 'function') onConfirm(null); return; }
    const title = modal.querySelector('#pm-modal-title');
    const body = modal.querySelector('#pm-modal-body');
    const cancel = modal.querySelector('#pm-modal-cancel');
    const confirm = modal.querySelector('#pm-modal-confirm');
    const closeBtn = modal.querySelector('.pm-modal-close');
    title.textContent = method.name + ' - Additional Info';
    // build simple form depending on payment method
    const name = (method.name||'').toLowerCase();
    let html = '';
    if (/gcash/i.test(name)){
        html = `
            <div class="form-group">
                <label for="pm-field">GCash Number</label>
                <input class="input-form" id="pm-field" type="tel" placeholder="09xxxxxxxxx" required />
            </div>
        `;
    } else if (/credit|card/i.test(name)){
        html = `
            <div class="form-group">
                <label for="pm-field">Card Holder Name</label>
                <input class="input-form" id="pm-field" type="text" placeholder="Name on card" required />
            </div>
            <div class="form-group">
                <label for="pm-field-last4">Last 4 Digits</label>
                <input class="input-form" id="pm-field-last4" type="text" maxlength="4" placeholder="1234" />
            </div>
        `;
    } else {
        html = `<div>No extra information required for ${method.name}.</div>`;
    }
    body.innerHTML = html;
    modal.setAttribute('aria-hidden','false');

    function hide(){ modal.setAttribute('aria-hidden','true'); removeListeners(); }
    function removeListeners(){ cancel.removeEventListener('click', onCancel); confirm.removeEventListener('click', onConfirmClick); closeBtn.removeEventListener('click', onCancel); }
    function onCancel(){ hide(); if (typeof onConfirm === 'function') onConfirm(null); }
    function onConfirmClick(){
        // basic validation
        let extra = null;
        const f = document.getElementById('pm-field');
        if (f){ if (!f.value.trim()){ alert('Please fill required field'); return; } extra = f.value.trim(); }
        const f2 = document.getElementById('pm-field-last4');
        if (f2){ extra = { primary: extra, last4: f2.value.trim() }; }
        hide(); if (typeof onConfirm === 'function') onConfirm(extra);
    }
    cancel.addEventListener('click', onCancel);
    confirm.addEventListener('click', onConfirmClick);
    closeBtn.addEventListener('click', onCancel);
}

document.addEventListener('DOMContentLoaded', ()=>{
    loadCheckout();

    const placeBtn = document.getElementById('place-order');
    if (placeBtn) {
        // If user is logged in but email not verified, intercept clicks and show a toast
        if (typeof window.IS_VERIFIED !== 'undefined' && !window.IS_VERIFIED) {
            placeBtn.addEventListener('click', (e)=>{
                e.preventDefault();
                if (typeof showNotification === 'function') showNotification('Verify your email first', 'error'); else alert('Verify your email first');
            });
        } else {
            placeBtn.addEventListener('click', async ()=>{
            try {
                const addrNode = document.querySelector('#addresses-list [data-address-id]');
                if (!addrNode) { if (typeof showNotification === 'function') showNotification('Please add a delivery address first.', 'error'); else alert('Please add a delivery address first.'); return; }
                const address_id = addrNode.getAttribute('data-address-id');
                    const sel = document.querySelector('#payment-methods .pm-card.active');
                    const payment_method_id = sel ? sel.getAttribute('data-method-id') : null;
                if (!payment_method_id) { if (typeof showNotification === 'function') showNotification('Please select a payment method.', 'error'); else alert('Please select a payment method.'); return; }
                const res = await fetch('api/place_order.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({address_id:address_id,payment_method_id:payment_method_id})});
                const j = await res.json();
                if (j.success){ if (typeof showNotification === 'function') showNotification('Order placed!', 'success'); setTimeout(()=>{ window.location.href = 'account.php#orders'; },1200); }
                else { if (typeof showNotification === 'function') showNotification(j.message || 'Order failed', 'error'); else alert(j.message || 'Order failed'); }
            } catch (err) { if (typeof showNotification === 'function') showNotification('Failed to place order: ' + err.message, 'error'); else alert('Failed to place order: ' + err.message); }
            });
        }
    }
});
