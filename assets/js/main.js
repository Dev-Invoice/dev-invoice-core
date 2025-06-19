// Task Bill Main JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Bootstrap tooltips initialization
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Auto-close alerts after 5 seconds
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
        alerts.forEach(function(alert) {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);

    // Verification code input handling
    var verificationInputs = document.querySelectorAll('.verification-code input');
    if (verificationInputs.length > 0) {
        // Auto focus next input after typing
        verificationInputs.forEach(function(input, index) {
            input.addEventListener('keyup', function(e) {
                // If backspace key is pressed, focus previous input
                if (e.keyCode === 8 && input.value === '' && index > 0) {
                    verificationInputs[index - 1].focus();
                }
                // If input has value and there's a next input, focus it
                else if (input.value !== '' && index < verificationInputs.length - 1) {
                    verificationInputs[index + 1].focus();
                }
            });
            
            // Handle paste event for verification code
            input.addEventListener('paste', function(e) {
                e.preventDefault();
                var pastedData = e.clipboardData.getData('text');
                if (pastedData.length <= verificationInputs.length) {
                    for (var i = 0; i < pastedData.length; i++) {
                        if (index + i < verificationInputs.length) {
                            verificationInputs[index + i].value = pastedData[i];
                        }
                    }
                    // Focus on the next empty input or the last input
                    if (index + pastedData.length < verificationInputs.length) {
                        verificationInputs[index + pastedData.length].focus();
                    } else {
                        verificationInputs[verificationInputs.length - 1].focus();
                    }
                }
            });
        });
    }

    // Invoice item handling
    var addItemButton = document.getElementById('add-invoice-item');
    if (addItemButton) {
        addItemButton.addEventListener('click', function() {
            var itemsContainer = document.getElementById('invoice-items-container');
            var itemsCount = document.querySelectorAll('.invoice-item').length;
            
            var newItem = document.createElement('div');
            newItem.className = 'invoice-item card mb-3';
            newItem.innerHTML = `
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <div class="d-flex justify-content-between">
                                <h5 class="card-title">Item #${itemsCount + 1}</h5>
                                <button type="button" class="btn btn-sm btn-danger remove-item" title="Remove Item">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-12 mb-3">
                            <select name="task_id[]" class="form-select">
                                <option value="">Select a task (optional)</option>
                                ${document.getElementById('task_template').innerHTML}
                            </select>
                        </div>
                        <div class="col-md-12 mb-3">
                            <textarea name="description[]" class="form-control" placeholder="Description" required></textarea>
                        </div>
                        <div class="col-md-4 mb-3">
                            <select name="rate_type[]" class="form-select rate-type-select">
                                <option value="Hourly">Hourly Rate</option>
                                <option value="Flat">Flat Rate</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <input type="number" name="quantity[]" class="form-control quantity-input" placeholder="Hours/Quantity" min="0.01" step="0.01" value="1" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <input type="number" name="rate[]" class="form-control rate-input" placeholder="Rate" min="0.01" step="0.01" required>
                        </div>
                    </div>
                </div>
            `;
            
            itemsContainer.appendChild(newItem);
            
            // Add event listener to the remove button
            newItem.querySelector('.remove-item').addEventListener('click', function() {
                itemsContainer.removeChild(newItem);
                updateItemNumbers();
                calculateInvoiceTotal();
            });
            
            // Add event listeners to the new inputs
            newItem.querySelector('.quantity-input').addEventListener('input', calculateInvoiceTotal);
            newItem.querySelector('.rate-input').addEventListener('input', calculateInvoiceTotal);
            newItem.querySelector('.rate-type-select').addEventListener('change', updateQuantityLabel);
            
            updateItemNumbers();
            updateQuantityLabel();
        });
        
        // Function to update item numbers
        function updateItemNumbers() {
            var items = document.querySelectorAll('.invoice-item');
            items.forEach(function(item, index) {
                item.querySelector('.card-title').textContent = 'Item #' + (index + 1);
            });
        }
        
        // Function to update quantity label based on rate type
        function updateQuantityLabel() {
            var rateTypeSelects = document.querySelectorAll('.rate-type-select');
            rateTypeSelects.forEach(function(select) {
                var quantityInput = select.closest('.row').querySelector('.quantity-input');
                if (select.value === 'Hourly') {
                    quantityInput.placeholder = 'Hours';
                } else {
                    quantityInput.placeholder = 'Quantity';
                }
            });
        }
        
        // Function to calculate invoice total
        function calculateInvoiceTotal() {
            var subtotal = 0;
            var items = document.querySelectorAll('.invoice-item');
            
            items.forEach(function(item) {
                var quantity = parseFloat(item.querySelector('.quantity-input').value) || 0;
                var rate = parseFloat(item.querySelector('.rate-input').value) || 0;
                subtotal += quantity * rate;
            });
            
            var taxRate = parseFloat(document.getElementById('tax_rate').value) || 0;
            var discount = parseFloat(document.getElementById('discount').value) || 0;
            
            var taxAmount = subtotal * (taxRate / 100);
            var total = subtotal + taxAmount - discount;
            
            document.getElementById('subtotal').textContent = subtotal.toFixed(2);
            document.getElementById('tax_amount').textContent = taxAmount.toFixed(2);
            document.getElementById('total').textContent = total.toFixed(2);
            
            // Update hidden fields
            document.getElementById('subtotal_hidden').value = subtotal.toFixed(2);
            document.getElementById('tax_amount_hidden').value = taxAmount.toFixed(2);
            document.getElementById('total_hidden').value = total.toFixed(2);
        }
        
        // Add event listeners to calculate total
        document.getElementById('tax_rate').addEventListener('input', calculateInvoiceTotal);
        document.getElementById('discount').addEventListener('input', calculateInvoiceTotal);
        
        // Trigger add item on load if no items exist
        if (document.querySelectorAll('.invoice-item').length === 0) {
            addItemButton.click();
        }
    }

    // Password strength meter
    var passwordInput = document.querySelector('input[name="password"]');
    var passwordConfirmInput = document.querySelector('input[name="password_confirm"]');
    var passwordStrengthMeter = document.getElementById('password-strength-meter');
    var passwordMatchStatus = document.getElementById('password-match-status');
    
    if (passwordInput && passwordStrengthMeter) {
        passwordInput.addEventListener('input', function() {
            var strength = 0;
            var password = passwordInput.value;
            
            if (password.length >= 8) strength += 1;
            if (password.match(/[a-z]+/)) strength += 1;
            if (password.match(/[A-Z]+/)) strength += 1;
            if (password.match(/[0-9]+/)) strength += 1;
            if (password.match(/[^a-zA-Z0-9]+/)) strength += 1;
            
            passwordStrengthMeter.value = strength;
            
            switch(strength) {
                case 0:
                case 1:
                    passwordStrengthMeter.className = 'password-strength-meter weak';
                    break;
                case 2:
                case 3:
                    passwordStrengthMeter.className = 'password-strength-meter medium';
                    break;
                case 4:
                case 5:
                    passwordStrengthMeter.className = 'password-strength-meter strong';
                    break;
            }
            
            // Check if password confirmation matches
            if (passwordConfirmInput && passwordConfirmInput.value) {
                checkPasswordMatch();
            }
        });
    }
    
    if (passwordConfirmInput && passwordMatchStatus) {
        passwordConfirmInput.addEventListener('input', checkPasswordMatch);
        
        function checkPasswordMatch() {
            if (passwordInput.value === passwordConfirmInput.value) {
                passwordMatchStatus.textContent = 'Passwords match';
                passwordMatchStatus.className = 'text-success';
            } else {
                passwordMatchStatus.textContent = 'Passwords do not match';
                passwordMatchStatus.className = 'text-danger';
            }
        }
    }

    // Client selection for task assignment
    var clientSelect = document.getElementById('client_id');
    if (clientSelect) {
        clientSelect.addEventListener('change', function() {
            var clientEmail = clientSelect.options[clientSelect.selectedIndex].getAttribute('data-email');
            var clientEmailInput = document.getElementById('client_email');
            if (clientEmailInput) {
                clientEmailInput.value = clientEmail || '';
            }
        });
    }

    // Date picker initialization
    var dateInputs = document.querySelectorAll('.datepicker');
    dateInputs.forEach(function(input) {
        // You can add a date picker library initialization here if needed
        input.type = 'date';
    });
});