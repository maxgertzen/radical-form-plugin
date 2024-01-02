document.addEventListener('DOMContentLoaded', () => {
    window.addEventListener('radical-cart-updated', () => {
        jQuery(document.body).trigger('wc_fragment_refresh');
    });
});