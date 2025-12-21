/**
 * DigitalFemsa - Payment Options Styling
 * Layout: Text (left) | Icon (right before radio) | Radio button (far right)
 */
(function() {
    // Inject CSS directly into the page for maximum specificity
    function injectStyles() {
        if (document.getElementById('digitalfemsa-payment-styles')) return;
        
        var style = document.createElement('style');
        style.id = 'digitalfemsa-payment-styles';
        style.textContent = `
            .payment-option {
                display: flex !important;
                align-items: center !important;
                flex-direction: row !important;
                background-color: #BBC0DC4D !important;
                border-radius: 8px !important;
                padding: 12px 20px !important;
                margin-bottom: 10px !important;
            }
            .payment-option .custom-radio {
                order: 3 !important;
                margin-left: 10px !important;
                flex-shrink: 0 !important;
            }
            .payment-option label {
                display: flex !important;
                align-items: center !important;
                flex: 1 !important;
                order: 1 !important;
                justify-content: flex-start !important;
            }
            .payment-option label span {
                text-align: left !important;
                flex-grow: 0 !important;
                flex-shrink: 0 !important;
                color: #1a3a4a !important;
                font-size: 14px !important;
                font-weight: 500 !important;
            }
            .payment-option label img {
                margin-left: auto !important;
                margin-right: 10px !important;
                height: 24px !important;
                width: auto !important;
            }
        `;
        document.head.appendChild(style);
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', injectStyles);
    } else {
        injectStyles();
    }
})();
