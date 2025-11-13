/* Shared client-side utilities
 * Exposes a single global object `AppUtils` with helpers used across scripts.
 */
(function(global){
    const AppUtils = global.AppUtils || {};

    AppUtils.formatCurrency = AppUtils.formatCurrency || function(v){ return Number(v||0).toFixed(2); };

    AppUtils.safeJson = AppUtils.safeJson || async function(response){
        try{ return await response.json(); }catch(e){ console.warn('safeJson parse failed', e); return null; }
    };

    AppUtils.resolveImagePath = AppUtils.resolveImagePath || function(image, category){
        if (!image) return 'uploads/product_img/noimg.png';
        if (String(image).includes('uploads/product_img/')) return image;
        if (String(image).includes('assets/')) {
            // convert legacy assets reference to uploads
            return String(image).replace(/assets\/img\//i, 'uploads/product_img/');
        }
        const cat = (category || '').toLowerCase();
        let folder = '';
        if (cat.indexOf('shirt') !== -1) folder = 'shirts/';
        else if (cat.indexOf('cap') !== -1) folder = 'caps/';
        else if (cat.indexOf('perfume') !== -1) folder = 'perfumes/';
        if (!folder) folder = 'products/';
        return 'uploads/product_img/' + folder + image;
    };

    AppUtils.escapeHtml = AppUtils.escapeHtml || function(s){ if(s===null||s===undefined) return ''; return String(s).replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); };

    AppUtils.notify = AppUtils.notify || function(msg, type){ try{ if (typeof global.showNotification === 'function') global.showNotification(msg, type); else alert(msg); }catch(e){ try{ alert(msg); }catch(_){} } };

    global.AppUtils = AppUtils;
})(window);
