// cart.js - robust cart frontend (fetch-safe, immediate UI updates)

function formatCurrency(v){ return Number(v||0).toFixed(2); }

async function safeJson(response){
    try{
        return await response.json();
    }catch(e){
        console.error('safeJson: failed to parse JSON', e, response);
        return null;
    }
}

function calculateTotals(items){
    const subtotal = items.reduce((s,it)=> s + (Number(it.price||0) * Number(it.quantity||0)), 0);
    const tax = subtotal * 0.12;
    const shipping = 49;
    const grand = subtotal + shipping;
    return { subtotal, tax, shipping, grand };
}

function updateTotalsUI(totals){
    const subEl = document.getElementById('subtotal'); if(subEl) subEl.textContent = formatCurrency(totals.subtotal);
    const taxEl = document.getElementById('tax'); if(taxEl) taxEl.textContent = formatCurrency(totals.tax);
    const grandEl = document.getElementById('grand'); if(grandEl) grandEl.textContent = formatCurrency(totals.grand);
    const itemsCountEl = document.getElementById('items-count'); if(itemsCountEl) itemsCountEl.textContent = (totals.itemsCount!==undefined? totals.itemsCount : 0);
}

function renderCartItems(items){
    const container = document.getElementById('cart-items');
    if (!container) return;
    container.innerHTML = '';

    const emptyEl = document.getElementById('emptyCart');
    const summaryEl = document.querySelector('.cart-summary');
    const containerWrapper = document.querySelector('.cart-container');

    if (!items || items.length === 0){
        // clear items area
        container.innerHTML = '';
        // hide the main container (items + summary) to avoid empty layout space
        if (containerWrapper) containerWrapper.style.display = 'none';
        // show empty cart UI if present
        if (emptyEl) emptyEl.style.display = '';
        // hide summary on empty
        if (summaryEl) summaryEl.style.display = 'none';
        updateTotalsUI({ subtotal:0, tax:0, shipping:0, grand:0, itemsCount:0 });
        if (typeof updateCartCount === 'function') updateCartCount(0);
        return;
    }

    // show the main container and hide empty cart UI when showing items
    if (containerWrapper) containerWrapper.style.display = 'grid';
    if (emptyEl) emptyEl.style.display = 'none';
    if (summaryEl) summaryEl.style.display = 'block';

    items.forEach((it, idx)=>{
        const row = document.createElement('div');
        row.className = 'cart-item';
        const imagePath = (window.AppUtils && window.AppUtils.resolveImagePath) ? window.AppUtils.resolveImagePath(it.image_url || it.image || 'no-image.png', it.category_name) : (it.image_url || it.image || 'uploads/product_img/no-image.png');
        const lineTotal = (Number(it.price||0) * Number(it.quantity||0)).toFixed(2);

        row.innerHTML = `
            <div class="cart-item-image"><img src="${imagePath}" alt="${(it.name||'')}"></div>
            <div class="cart-item-details">
                <h4 class="cart-item-name">${it.name}</h4>
                <p class="cart-item-category">${it.category_name || ''}</p>
                <p class="cart-item-size">Size: ${it.size || '-'}</p>
                <div class="cart-item-price">₱${formatCurrency(it.price)}</div>
            </div>
            <div class="cart-item-quantity">
                <button class="quantity-btn qty-decrease" data-idx="${idx}" type="button">-</button>
                <input type="number" min="1" value="${it.quantity}" data-idx="${idx}" class="quantity-input qty-input" />
                <button class="quantity-btn qty-increase" data-idx="${idx}" type="button">+</button>
            </div>
            <div class="cart-item-total">₱${lineTotal}</div>
            <button class="remove-item-btn" data-idx="${idx}" title="Remove item"><i class="fa fa-trash"></i></button>
        `;

        container.appendChild(row);
    });

    // totals
    const totals = calculateTotals(items);
    totals.itemsCount = items.reduce((s,i)=> s + Number(i.quantity||0), 0);
    updateTotalsUI(totals);

    // wire up events
    container.querySelectorAll('.remove-item-btn').forEach(btn=> btn.addEventListener('click', async (e)=>{
        const idx = parseInt(btn.getAttribute('data-idx'));
        const item = items[idx];
        if (!item) return;

        if (window.IS_LOGGED) {
            try{
                const resp = await fetch('api/remove_cart_item.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ product_id: item.id, size: item.size }) });
                const j = await safeJson(resp);
                if (!j || !j.success){
                    if (typeof showNotification === 'function') showNotification((j && j.message) || 'Failed to remove item', 'error');
                    else alert((j && j.message) || 'Failed to remove item');
                    return;
                }
                // refresh items from server
                const r2 = await fetch('api/fetch_cart_items.php');
                const updated = await safeJson(r2) || [];
                renderCartItems(updated);
                if (typeof updateCartCount === 'function') updateCartCount(updated.reduce((s,i)=>s+Number(i.quantity||0),0));
                window.dispatchEvent(new Event('cartUpdated'));
                }catch(err){
                console.error('remove failed', err);
                if (typeof showNotification === 'function') showNotification('Error removing item', 'error');
                else alert('Error removing item');
            }
        } else {
            // guest cart stored in localStorage
            const cart = JSON.parse(localStorage.getItem('cart')||'[]');
            cart.splice(idx,1);
            localStorage.setItem('cart', JSON.stringify(cart));
            renderCartItems(cart);
            if (typeof updateCartCount === 'function') updateCartCount(cart.reduce((s,i)=>s+Number(i.quantity||0),0));
            window.dispatchEvent(new Event('cartUpdated'));
        }
    }));

    container.querySelectorAll('.quantity-input').forEach(inp=> inp.addEventListener('change', async (e)=>{
        const idx = parseInt(inp.getAttribute('data-idx'));
        const newVal = Math.max(1, parseInt(inp.value||1));
        const item = items[idx];
        if (!item) return;

        if (window.IS_LOGGED) {
            try{
                const resp = await fetch('api/add_to_cart.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ product_id: item.id, size: item.size, quantity: newVal, update:true }) });
                const j = await safeJson(resp);
                if (!j || !j.success){ if (typeof showNotification === 'function') showNotification((j && j.message) || 'Failed to update quantity', 'error'); else alert((j && j.message) || 'Failed to update quantity'); return; }

                const r2 = await fetch('api/fetch_cart_items.php');
                const updated = await safeJson(r2) || [];
                renderCartItems(updated);
                if (typeof updateCartCount === 'function') updateCartCount(updated.reduce((s,i)=>s+Number(i.quantity||0),0));
                window.dispatchEvent(new Event('cartUpdated'));
            }catch(err){ console.error('update qty failed', err); if (typeof showNotification === 'function') showNotification('Error updating quantity', 'error'); else alert('Error updating quantity'); }
        } else {
            const cart = JSON.parse(localStorage.getItem('cart')||'[]');
            if (cart[idx]) { cart[idx].quantity = newVal; localStorage.setItem('cart', JSON.stringify(cart)); renderCartItems(cart); if (typeof updateCartCount === 'function') updateCartCount(cart.reduce((s,i)=>s+Number(i.quantity||0),0)); window.dispatchEvent(new Event('cartUpdated')); }
        }
    }));

    // wire custom qty buttons (decrease / increase)
    container.querySelectorAll('.qty-decrease').forEach(btn => btn.addEventListener('click', (e)=>{
        const idx = btn.getAttribute('data-idx');
        const input = container.querySelector('input[data-idx="' + idx + '"]');
        if (!input) return;
        const cur = Math.max(1, parseInt(input.value||1));
        input.value = Math.max(1, cur - 1);
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }));

    container.querySelectorAll('.qty-increase').forEach(btn => btn.addEventListener('click', (e)=>{
        const idx = btn.getAttribute('data-idx');
        const input = container.querySelector('input[data-idx="' + idx + '"]');
        if (!input) return;
        const cur = Math.max(1, parseInt(input.value||1));
        input.value = cur + 1;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }));
}

// init
document.addEventListener('DOMContentLoaded', async ()=>{
    if (window.IS_LOGGED && window.INITIAL_CART_DATA) {
        // Use pre-loaded cart data for immediate rendering
        renderCartItems(window.INITIAL_CART_DATA);
        if (typeof updateCartCount === 'function') updateCartCount(window.INITIAL_CART_DATA.reduce((s,i)=>s+Number(i.quantity||0),0));
    } else {
        // Normalize guest cart items (ensure full image paths) to support older stored entries
        (async ()=>{
            let items = JSON.parse(localStorage.getItem('cart')||'[]');

            // helper: test whether an image URL actually loads
            function testImage(url, timeout = 2000){
                return new Promise(resolve=>{
                    const img = new Image();
                    let done = false;
                    const timer = setTimeout(()=>{ if (!done){ done = true; resolve(false); img.src = ''; } }, timeout);
                    img.onload = ()=>{ if (!done){ done = true; clearTimeout(timer); resolve(true); } };
                    img.onerror = ()=>{ if (!done){ done = true; clearTimeout(timer); resolve(false); } };
                    img.src = url;
                });
            }

            const candidateFolders = ['shirts/','caps/','perfumes/'];

            const normalized = [];
            for (const it of items){
                if (!it){ normalized.push(it); continue; }
                const image = it.image || it.image_url || '';
                if (image && !image.includes('assets/')){
                    // use the shared resolver to avoid repeating folder logic
                    try{
                                it.image = (window.AppUtils && window.AppUtils.resolveImagePath) ? window.AppUtils.resolveImagePath(image, it.category_name) : ('uploads/product_img/' + (it.category_name && it.category_name.toLowerCase().includes('cap') ? 'caps/' : (it.category_name && it.category_name.toLowerCase().includes('perfume') ? 'perfumes/' : 'shirts/')) + image);
                    }catch(e){ it.image = 'uploads/product_img/shirts/' + image; }
                }
                normalized.push(it);
            }

            // persist normalized guest cart (safe to overwrite)
            try{ localStorage.setItem('cart', JSON.stringify(normalized)); }catch(e){}
            renderCartItems(normalized);
        })();
    }
});
