/**
 * Exchange Rate Utilities for MONET
 */
class ExchangeRateHandler {
    constructor() {
        this.exchangeRateUrl = '/admin/transactions/exchange-rate';
        this.setupEventListeners();
    }

    /**
     * Set up event listeners for currency exchange elements
     */
    setupEventListeners() {
        // Set up event listeners when the DOM is loaded
        document.addEventListener('DOMContentLoaded', () => {
            // For transfer modal
            const transferForm = document.getElementById('transfer-form');
            if (transferForm) {
                // Source account selection change
                const sourceAccountSelect = document.getElementById('from_account_id');
                if (sourceAccountSelect) {
                    sourceAccountSelect.addEventListener('change', () => this.checkCurrencyDifference());
                }

                // Destination account selection change
                const destAccountSelect = document.getElementById('to_account_id');
                if (destAccountSelect) {
                    destAccountSelect.addEventListener('change', () => this.checkCurrencyDifference());
                }

                // Real-time rate checkbox
                const realTimeRateCheckbox = document.getElementById('use_real_time_rate');
                if (realTimeRateCheckbox) {
                    realTimeRateCheckbox.addEventListener('change', () => this.toggleDestinationAmountField());
                }

                // Amount input for currency transfers
                const amountInput = document.getElementById('amount');
                if (amountInput) {
                    amountInput.addEventListener('input', () => this.updateDestinationAmount());
                }

                // Get rate button
                const getRateButton = document.getElementById('get-rate-button');
                if (getRateButton) {
                    getRateButton.addEventListener('click', (e) => {
                        e.preventDefault();
                        this.updateDestinationAmount();
                    });
                }
            }
        });
    }

    /**
     * Check if source and destination accounts have different currencies
     * and show/hide the appropriate fields
     */
    checkCurrencyDifference() {
        const sourceAccount = document.getElementById('from_account_id');
        const destAccount = document.getElementById('to_account_id');
        
        if (!sourceAccount || !destAccount) return;
        
        const sourceOption = sourceAccount.options[sourceAccount.selectedIndex];
        const destOption = destAccount.options[destAccount.selectedIndex];
        
        if (!sourceOption || !destOption) return;
        
        const sourceCurrency = sourceOption.getAttribute('data-currency');
        const destCurrency = destOption.getAttribute('data-currency');
        
        const currencyTransferFields = document.getElementById('currency-transfer-fields');
        
        if (sourceCurrency && destCurrency && sourceCurrency !== destCurrency) {
            // Different currencies - show currency transfer fields
            if (currencyTransferFields) {
                currencyTransferFields.classList.remove('d-none');
                
                // Update the currency labels
                const sourceCurrencyLabel = document.getElementById('source-currency-code');
                const destCurrencyLabel = document.getElementById('dest-currency-code');
                
                if (sourceCurrencyLabel) sourceCurrencyLabel.textContent = sourceCurrency;
                if (destCurrencyLabel) destCurrencyLabel.textContent = destCurrency;
                
                // Check if we should auto-update the rate
                this.toggleDestinationAmountField();
            }
        } else {
            // Same currency - hide currency transfer fields
            if (currencyTransferFields) {
                currencyTransferFields.classList.add('d-none');
            }
        }
    }
    
    /**
     * Toggle the destination amount field based on real-time rate checkbox
     */
    toggleDestinationAmountField() {
        const realTimeRateCheckbox = document.getElementById('use_real_time_rate');
        const destinationAmountGroup = document.getElementById('destination-amount-group');
        const rateInfoGroup = document.getElementById('rate-info-group');
        
        if (!realTimeRateCheckbox || !destinationAmountGroup) return;
        
        if (realTimeRateCheckbox.checked) {
            // Using real-time rate - disable manual input
            document.getElementById('destination_amount').disabled = true;
            rateInfoGroup.classList.remove('d-none');
            this.updateDestinationAmount();
        } else {
            // Using manual rate - enable input
            document.getElementById('destination_amount').disabled = false;
            rateInfoGroup.classList.add('d-none');
        }
    }
    
    /**
     * Update destination amount based on exchange rate
     */
    updateDestinationAmount() {
        const realTimeRateCheckbox = document.getElementById('use_real_time_rate');
        if (!realTimeRateCheckbox || !realTimeRateCheckbox.checked) return;
        
        const sourceAccount = document.getElementById('from_account_id');
        const destAccount = document.getElementById('to_account_id');
        const amountInput = document.getElementById('amount');
        
        if (!sourceAccount || !destAccount || !amountInput) return;
        
        const sourceOption = sourceAccount.options[sourceAccount.selectedIndex];
        const destOption = destAccount.options[destAccount.selectedIndex];
        
        if (!sourceOption || !destOption) return;
        
        const sourceCurrency = sourceOption.getAttribute('data-currency');
        const destCurrency = destOption.getAttribute('data-currency');
        const amount = parseFloat(amountInput.value);
        
        if (!sourceCurrency || !destCurrency || isNaN(amount) || amount <= 0) return;
        
        // Show loading indicator
        this.showLoading(true);
        
        // Fetch the exchange rate
        fetch(`${this.exchangeRateUrl}?from_currency=${sourceCurrency}&to_currency=${destCurrency}&amount=${amount}`)
            .then(response => response.json())
            .then(data => {
                this.showLoading(false);
                
                if (data.status === 'success') {
                    const rate = data.data.rate;
                    const convertedAmount = data.data.converted_amount;
                    
                    // Update destination amount
                    document.getElementById('destination_amount').value = convertedAmount.toFixed(2);
                    
                    // Update rate display
                    const rateDisplay = document.getElementById('exchange-rate-display');
                    if (rateDisplay) {
                        rateDisplay.textContent = `1 ${sourceCurrency} = ${rate.toFixed(4)} ${destCurrency}`;
                    }
                    
                    // Show rate info
                    document.getElementById('rate-info-group').classList.remove('d-none');
                } else {
                    console.error('Error fetching exchange rate:', data.message);
                    alert(`Error: ${data.message}`);
                }
            })
            .catch(error => {
                this.showLoading(false);
                console.error('Error fetching exchange rate:', error);
                alert('Failed to get exchange rate. Please try again.');
            });
    }
    
    /**
     * Show or hide loading indicator
     */
    showLoading(show) {
        const rateDisplay = document.getElementById('exchange-rate-display');
        const loadingSpinner = document.getElementById('rate-loading-spinner');
        
        if (rateDisplay && loadingSpinner) {
            if (show) {
                rateDisplay.classList.add('d-none');
                loadingSpinner.classList.remove('d-none');
            } else {
                loadingSpinner.classList.add('d-none');
                rateDisplay.classList.remove('d-none');
            }
        }
    }
}

// Initialize the exchange rate handler
document.addEventListener('DOMContentLoaded', () => {
    window.exchangeRateHandler = new ExchangeRateHandler();
});
