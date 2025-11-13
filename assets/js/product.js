// product.js
document.addEventListener('DOMContentLoaded', ()=>{
    const addBtn = document.getElementById('add-to-cart');
    const sizeSel = document.getElementById('size-select');
    const qtyEl = document.getElementById('qty');

    // Render "You Might Also Like" via AJAX to server or using a simple fetch of index-style JSON
    fetch(`api/fetch_recommendations.php?category_id=${encodeURIComponent(PRODUCT.category_id || '')}&exclude_id=${encodeURIComponent(PRODUCT.product_id || '')}`)
    .then(r => r.json())
    .then(list => {
        const container = document.getElementById('also-like');
        if (!container) return;

        const items = list.slice(0, 4);
        if (items.length === 0) return;

        items.forEach(p => {
            const productCard = document.createElement('div');
            productCard.className = 'product-card';

            const imgSrc = (window.AppUtils && window.AppUtils.resolveImagePath) ? window.AppUtils.resolveImagePath(p.image_url || p.image, p.category_name) : (p.image_url || p.image || 'uploads/product_img/no-image.png');
            const priceText = (window.AppUtils && window.AppUtils.formatCurrency) ? window.AppUtils.formatCurrency(p.price) : ((typeof p.price !== 'undefined') ? Number(p.price).toFixed(2) : '0.00');

            productCard.innerHTML = `
                <a href="product.php?id=${p.product_id}">
                    <div class="product-image"><img src="${imgSrc}" alt="${p.name || ''}"></div>
                    <div class="product-info">
                        <h3 class="product-name">${p.name}</h3>
                        <div class="product-price">₱${priceText}</div>
                    </div>
                </a>`;
            container.appendChild(productCard);
        });
    })
    .catch(() => {});


    addBtn.addEventListener('click', async ()=>{
        // size may be a select or a hidden input updated by clicking a .size-option
        const sizeEl = document.getElementById('size-select');
        const size = sizeEl ? sizeEl.value : '';
        const qty = Math.max(1, parseInt(qtyEl.value||1));

        // Ensure guest cart stores a full image path using shared helper
    const imagePath = (window.AppUtils && window.AppUtils.resolveImagePath) ? window.AppUtils.resolveImagePath(PRODUCT.image_url || PRODUCT.image || 'no-image.png', PRODUCT.category_name) : (PRODUCT.image_url || PRODUCT.image || 'uploads/product_img/no-image.png');

        const item = {
            id: PRODUCT.product_id,
            name: PRODUCT.name,
            price: PRODUCT.price,
            category_name: PRODUCT.category_name || '',
            size: size,
            quantity: qty,
            image: imagePath
        };

        if (IS_LOGGED) {
            try{
                const resp = await fetch('api/add_to_cart.php', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(item) });

                // If the HTTP response is not OK, surface an error
                if (!resp.ok) {
                    const txt = await resp.text().catch(()=>null);
                    console.error('add_to_cart: server returned non-OK', resp.status, txt);
                    if (typeof showNotification === 'function') showNotification('Error adding to cart (server error). Please try again.', 'error');
                    else alert('Error adding to cart (server error). Please try again.');
                    return;
                }

                // Try parse JSON; if parsing fails, fall back to fetching the count
                let j = null;
                try { j = await resp.json(); } catch(e){ console.warn('add_to_cart: invalid json, will try to refresh count', e); }

                if (j && j.success) {
                    if (typeof updateCartCount === 'function') updateCartCount(j.count || 0);
                    try{ window.dispatchEvent(new Event('cartUpdated')); }catch(e){}
                    if (typeof showNotification === 'function') showNotification(j.message || 'Added to cart', 'success');
                    else alert(j.message || 'Added to cart');
                } else {
                    // server processed request but returned non-JSON (or malformed). Attempt to refresh count.
                    try{
                        const r = await fetch('api/get_cart_count.php');
                        const cj = await r.json();
                        if (cj && typeof cj.count !== 'undefined' && typeof updateCartCount === 'function') updateCartCount(cj.count);
                    }catch(e){ console.warn('could not refresh count after add_to_cart', e); }
                    if (typeof showNotification === 'function') showNotification((j && j.message) || 'Added to cart', 'success');
                    else alert((j && j.message) || 'Added to cart');
                }
            }catch(err){
                console.error('Error adding to cart', err);
                if (typeof showNotification === 'function') showNotification('Error adding to cart. Please try again.', 'error');
                else alert('Error adding to cart. Please try again.');
            }
        } else {
            // guest - use localStorage
            const cart = getGuestCart();
            // merge if same id+size
            const existing = cart.find(c=>c.id==item.id && c.size==item.size);
            if(existing) existing.quantity = Number(existing.quantity) + Number(item.quantity);
            else cart.push(item);
            setGuestCart(cart);
            // notify other parts of the site (mini-cart, header)
            window.dispatchEvent(new Event('cartUpdated'));
            if (typeof showNotification === 'function') showNotification('Added to cart', 'success');
            else alert('Added to cart');
        }
    });

    // clickable size squares (for perfume, caps)
    document.querySelectorAll('.size-option').forEach(el=> el.addEventListener('click', ()=>{
        document.querySelectorAll('.size-option').forEach(x=> x.classList.remove('selected'));
        el.classList.add('selected');
        const sz = el.getAttribute('data-size') || el.textContent.trim();
        const sizeInput = document.getElementById('size-select');
        if (sizeInput) sizeInput.value = sz;
    }));

    // Initialize hidden input from selected size-option if present
    (function(){
        const sel = document.querySelector('.size-option.selected');
        if (sel){ const sz = sel.getAttribute('data-size') || sel.textContent.trim(); const sizeInput = document.getElementById('size-select'); if (sizeInput) sizeInput.value = sz; }
    })();

    // --- Try on wardrobe (load saved mannequin and show viewer in main image) ----
    (function(){
        const tryBtn = document.getElementById('try-mannequin');
        if (!tryBtn) return;

        // metric ranges must match those used by the mannequin viewer
        const metricRanges = {
            'shoulder-width': { cm: [40, 55] },
            'chest':         { cm: [80, 110] },
            'waist':         { cm: [70, 100] },
            'torso-length':  { cm: [50, 80] },
            'height':        { cm: [150, 200] },
            // alias for arms (viewer uses 'height' range for arm-length slider in account UI)
            'arms':          { cm: [150, 200] }
        };

        function morphNameToId(name){
            return name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        }

        function setMorphInfluence(morphName, influence){
            try{
                if (!window.mannequin) return;
                window.mannequin.traverse(child => {
                    if (child.isMesh && child.morphTargetDictionary && child.morphTargetDictionary[morphName] !== undefined){
                        const idx = child.morphTargetDictionary[morphName];
                        child.morphTargetInfluences[idx] = Math.max(0, Math.min(1, influence));
                    }
                });
            }catch(e){ console.warn('setMorphInfluence failed', e); }
        }

        async function ensureViewerLoaded(container){
            // If module not yet added, add it as module script so imports work
            if (!document.querySelector('script[data-mannequin-module]')){
                const s = document.createElement('script');
                s.type = 'module';
                s.src = 'assets/js/mannequin-viewer.js';
                s.setAttribute('data-mannequin-module','1');
                document.body.appendChild(s);
            }
            if (window.mannequin && window.mannequinAPI) return;
            await new Promise(resolve => {
                window.addEventListener('mannequin.ready', ()=> resolve(), { once: true });
                // safety timeout: resolve after 5s even if mannequin.ready didn't fire
                setTimeout(()=> resolve(), 5000);
            });
        }

        tryBtn.addEventListener('click', async ()=>{
            const mainWrap = document.querySelector('.product-main-image');
            const mainImg = document.getElementById('mainProductImage');
            if (!mainWrap) return;

            // If user is not logged in, redirect to login page (exclusive feature)
            try{
                if (typeof window.IS_LOGGED === 'undefined' || !window.IS_LOGGED) {
                    // preserve return location so user can come back after login
                    const next = encodeURIComponent(window.location.pathname + window.location.search);
                    window.location.href = 'login.php?next=' + next;
                    return;
                }
                // If user is logged in but not verified, show notification and block feature
                if (typeof window.IS_VERIFIED !== 'undefined' && !window.IS_VERIFIED) {
                    if (typeof showNotification === 'function') showNotification('Verify your email first', 'error'); else alert('Verify your email first');
                    tryBtn.disabled = false; tryBtn.textContent = 'Try on Wardrobe';
                    return;
                }
            }catch(e){}

            const originalHtml = mainWrap.__originalHtml || mainWrap.innerHTML;
            mainWrap.__originalHtml = originalHtml;

            // Toggle: if viewer container already exists and is visible, hide it and show image
            let container = document.getElementById('mannequinViewer');
            const isVisible = container && container.style && container.style.display !== 'none' && container.offsetParent !== null;
            if (container && isVisible) {
                tryBtn.disabled = true; tryBtn.textContent = 'Closing...';
                // hide viewer, show original image
                try{ container.style.display = 'none'; }catch(e){}
                try{ if (mainImg) mainImg.style.display = ''; }catch(e){}
                tryBtn.disabled = false; tryBtn.textContent = 'Try on Wardrobe';
                return;
            }

            tryBtn.disabled = true; tryBtn.textContent = 'Loading...';

            // ensure original image remains in DOM and create viewer container if missing
            if (!container) {
                container = document.createElement('div');
                container.id = 'mannequinViewer';
                container.style.width = '100%';
                container.style.height = '100%';
                container.style.display = 'none';
                mainWrap.appendChild(container);
            }

            // hide main image while viewer shows
            if (mainImg) mainImg.style.display = 'none';

            // load the viewer module (it will initialize itself if not already loaded)
            await ensureViewerLoaded(container);
            // show container
            container.style.display = '';

            // fetch saved mannequin (server for logged users, localStorage for guests)
            let saved = null;
            try{
                if (typeof IS_LOGGED !== 'undefined' && IS_LOGGED) {
                    const r = await fetch('api/get_mannequin.php', { cache: 'no-store' });
                    if (r.ok) saved = await r.json();
                    // handle server-side unverified response
                    if (saved && saved.error === 'unverified') {
                        if (typeof showNotification === 'function') showNotification(saved.message || 'Verify your email first', 'error');
                        // restore UI
                        if (mainImg) mainImg.style.display = '';
                        if (container) container.style.display = 'none';
                        tryBtn.disabled = false; tryBtn.textContent = 'Try on Wardrobe';
                        return;
                    }
                } else {
                    const s = localStorage.getItem('savedMannequin');
                    if (s) saved = JSON.parse(s);
                }
            }catch(e){ console.warn('Failed to load saved mannequin', e); }

            // apply saved settings if any
            try{
                if (saved) {
                    // skin tone
                    if (saved.skin_tone && window.mannequinAPI && typeof window.mannequinAPI.setSkinTone === 'function') {
                        try{ window.mannequinAPI.setSkinTone(saved.skin_tone); }catch(e){}
                    }

                    // face/body shapes (best-effort)
                    if (saved.face_shape && window.mannequinAPI && typeof window.mannequinAPI.setMorphExclusive === 'function'){
                        try{ window.mannequinAPI.setMorphExclusive(saved.face_shape, [saved.face_shape]); }catch(e){}
                    }
                    if (saved.body_shape && window.mannequinAPI && typeof window.mannequinAPI.setMorphExclusive === 'function'){
                        try{ window.mannequinAPI.setMorphExclusive(saved.body_shape, [saved.body_shape]); }catch(e){}
                    }

                    // measurements -> morphs mapping
                    const mapping = {
                        shoulder_width: 'Shoulders',
                        chest_bust: 'Chest',
                        waist: 'Waist',
                        torso_length: 'Torso',
                        arm_length: 'Arms'
                    };

                    Object.keys(mapping).forEach(k => {
                        const v = saved[k];
                        if (!v && v !== 0) return;
                        const morphName = mapping[k];
                        // determine metric key used in viewer ranges
                        let metricKey = morphName.toLowerCase();
                        if (metricKey === 'shoulders') metricKey = 'shoulder-width';
                        if (metricKey === 'torso') metricKey = 'torso-length';
                        if (metricKey === 'arms') metricKey = 'arms';
                        const range = metricRanges[metricKey];
                        if (range && typeof v === 'number'){
                            const min = range.cm[0]; const max = range.cm[1];
                            const influence = (v - min) / (max - min);
                            setMorphInfluence(morphName, influence);
                        }
                    });
                }
            }catch(e){ console.warn('Failed applying saved mannequin', e); }

            // attempt a refresh in case the viewer rendered earlier with wrong size
            try{ if (window.mannequinAPI && typeof window.mannequinAPI.refresh === 'function') window.mannequinAPI.refresh(); }catch(e){}

            tryBtn.disabled = false; tryBtn.textContent = 'Close Wardrobe';
        });
    })();

    // --- Thumbnail switching (fade) ---------------------------------
    function wireThumbnails(){
        const thumbnails = document.querySelectorAll('.thumbnail');
        const mainImg = document.getElementById('mainProductImage');
        if (!thumbnails || !mainImg) return;

        thumbnails.forEach(th => th.addEventListener('click', ()=>{
            const src = th.getAttribute('data-image') || (th.querySelector('img') && th.querySelector('img').src);
            if (!src) return;
            // skip if same
            if (mainImg.src && mainImg.src.indexOf(src) !== -1) return;

            // set active class
            thumbnails.forEach(t=> t.classList.remove('active'));
            th.classList.add('active');

            // fade out -> change src -> fade in
            mainImg.style.transition = 'opacity 180ms ease';
            mainImg.style.opacity = 0;
            // ensure we change src after fade-out
            setTimeout(()=>{
                mainImg.src = src;
                // when image loads, fade back in
                mainImg.onload = ()=>{ mainImg.style.opacity = 1; };
                // safety: if onload doesn't fire, force visible after short delay
                setTimeout(()=>{ mainImg.style.opacity = 1; }, 300);
            }, 180);
        }));
    }

    // initial wiring
    wireThumbnails();

    // --- Quantity +/- buttons for product page ----------------------
    (function(){
        const dec = document.querySelector('.qty-decrease');
        const inc = document.querySelector('.qty-increase');
        const qty = document.getElementById('qty');
        if (!qty) return;
        if (dec) dec.addEventListener('click', ()=>{ qty.value = Math.max(1, (parseInt(qty.value||1) - 1)); qty.dispatchEvent(new Event('change')); });
        if (inc) inc.addEventListener('click', ()=>{ qty.value = Math.max(1, (parseInt(qty.value||1) + 1)); qty.dispatchEvent(new Event('change')); });
    })();

    // --- Smooth accordion behavior ----------------------------------
    (function(){
        const headers = document.querySelectorAll('.accordion-header');
        if (!headers) return;
        headers.forEach(h => {
            h.addEventListener('click', ()=>{
                const item = h.closest('.accordion-item');
                if (!item) return;
                const content = item.querySelector('.accordion-content');
                const icon = h.querySelector('i');

                const isActive = item.classList.contains('active');
                if (isActive){
                    // collapse
                    item.classList.remove('active');
                    if (content){
                        content.style.maxHeight = content.scrollHeight + 'px';
                        // trigger reflow then set to 0
                        void content.offsetHeight;
                        content.style.transition = 'max-height 280ms ease';
                        content.style.maxHeight = '0';
                    }
                    if (icon){ icon.classList.remove('fa-minus'); icon.classList.add('fa-plus'); }
                } else {
                    // expand: first collapse other items for accordion behavior
                    document.querySelectorAll('.accordion-item.active').forEach(ai=>{
                        ai.classList.remove('active');
                        const c = ai.querySelector('.accordion-content'); if (c){ c.style.maxHeight = '0'; }
                        const ic = ai.querySelector('.accordion-header i'); if (ic){ ic.classList.remove('fa-minus'); ic.classList.add('fa-plus'); }
                    });

                    item.classList.add('active');
                    if (content){
                        // set to measured height to animate open
                        content.style.transition = 'max-height 320ms ease';
                        content.style.maxHeight = content.scrollHeight + 'px';
                    }
                    if (icon){ icon.classList.remove('fa-plus'); icon.classList.add('fa-minus'); }
                }
            });
        });
        // On load, ensure any pre-opened items are sized correctly
        document.querySelectorAll('.accordion-item.active .accordion-content').forEach(c=>{ c.style.maxHeight = c.scrollHeight + 'px'; });
    })();
});
