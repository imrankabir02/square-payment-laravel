<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Square Payment Form</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="{{ config('square.environment') === 'sandbox'
        ? 'https://sandbox.web.squarecdn.com/v1/square.js'
        : 'https://web.squarecdn.com/v1/square.js' }}">
    </script>
    <style>
        .container { max-width: 800px; margin: 40px auto; padding: 20px; }
        .payment-form { background: #f8f9fa; padding: 20px; border-radius: 8px; }
        #card-container { min-height: 90px; margin-bottom: 20px; }
        #payment-status { margin-top: 20px; padding: 10px; display: none; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        button {
            background: #006aff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:disabled { background: #cccccc; }
        .amount-input {
            margin-bottom: 20px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 100%;
            max-width: 200px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="payment-form">
            <h2>Payment Form</h2>
            <form id="payment-form">
                <div class="amount-wrapper">
                    <label for="amount">Amount ($)</label>
                    <input type="number" id="amount" class="amount-input" step="0.01" min="0.01" value="1.00" required>
                </div>
                <div id="card-container"></div>
                <button id="card-button" type="button">Pay Now</button>
                <div id="payment-status"></div>
            </form>
        </div>
    </div>

    <script>
        let payments = null;
        let card = null;

        async function initializeSquare() {
            if (!window.Square) {
                throw new Error('Square.js failed to load properly');
            }

            try {
                payments = window.Square.payments('{{ $applicationId }}', '{{ $locationId }}');
                return await createNewCard();
            } catch (e) {
                console.error('Square initialization failed:', e);
                throw e;
            }
        }

        async function createNewCard() {
            // If there's an existing card, destroy it
            if (card) {
                try {
                    await card.destroy();
                } catch (e) {
                    console.warn('Error destroying card:', e);
                }
            }

            // Clear the container
            const container = document.getElementById('card-container');
            container.innerHTML = '';

            // Create and attach new card
            card = await payments.card();
            await card.attach('#card-container');
            return card;
        }

        async function handlePayment(event) {
            event.preventDefault();

            const amountInput = document.getElementById('amount');
            const amount = parseFloat(amountInput.value);

            if (!amount || amount <= 0) {
                updatePaymentStatus('Please enter a valid amount', 'error');
                return;
            }

            const statusContainer = document.getElementById('payment-status');
            const submitButton = document.getElementById('card-button');

            try {
                statusContainer.style.display = 'none';
                submitButton.disabled = true;
                submitButton.textContent = 'Processing...';

                const result = await card.tokenize();

                if (result.status === 'OK') {
                    try {
                        const response = await fetch('/process-payment', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({
                                sourceId: result.token,
                                amount: amount
                            })
                        });

                        const data = await response.json();
                        console.log('Payment response:', data);

                        if (data.success) {
                            updatePaymentStatus('Payment successful!', 'success');
                            // Reset form and create new card instance
                            document.getElementById('payment-form').reset();
                            amountInput.value = '1.00';
                            await createNewCard();
                        } else {
                            throw new Error(data.error || 'Payment failed');
                        }
                    } catch (e) {
                        throw new Error(e.message || 'Payment processing failed');
                    }
                } else {
                    throw new Error(result.errors[0].message);
                }
            } catch (e) {
                console.error('Payment error:', e);
                updatePaymentStatus(e.message, 'error');
            } finally {
                submitButton.disabled = false;
                submitButton.textContent = 'Pay Now';
            }
        }

        function updatePaymentStatus(message, type) {
            const statusContainer = document.getElementById('payment-status');
            statusContainer.className = type;
            statusContainer.textContent = message;
            statusContainer.style.display = 'block';
        }

        document.addEventListener('DOMContentLoaded', async function () {
            try {
                card = await initializeSquare();

                const cardButton = document.getElementById('card-button');
                cardButton.addEventListener('click', handlePayment);
            } catch (e) {
                console.error('Initialization error:', e);
                updatePaymentStatus('Failed to initialize payment form', 'error');
            }
        });
    </script>
</body>
</html>


{{-- <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Form</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="{{ config('square.environment') === 'sandbox'
        ? 'https://sandbox.web.squarecdn.com/v1/square.js'
        : 'https://web.squarecdn.com/v1/square.js' }}">
    </script>
    <style>
        .container { max-width: 800px; margin: 40px auto; padding: 20px; }
        .payment-form { background: #f8f9fa; padding: 20px; border-radius: 8px; }
        #card-container { min-height: 90px; margin-bottom: 20px; }
        #payment-status { margin-top: 20px; padding: 10px; display: none; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
        button {
            background: #006aff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:disabled { background: #cccccc; }
        .amount-input {
            margin-bottom: 20px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 100%;
            max-width: 200px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="payment-form">
            <h2>Payment Details</h2>
            <form id="payment-form">
                <div class="amount-wrapper">
                    <label for="amount">Amount ($)</label>
                    <input type="number" id="amount" class="amount-input" step="0.01" min="0.01" required>
                </div>
                <div id="card-container"></div>
                <button id="card-button" type="button">Process Payment</button>
                <div id="payment-status"></div>
            </form>
        </div>
    </div>

    <script>
        let square = null;
        let card = null;

        async function initializeSquare() {
            if (!window.Square) {
                throw new Error('Square.js failed to load properly');
            }

            try {
                square = window.Square;
                const payments = square.payments('{{ $applicationId }}', '{{ $locationId }}');
                card = await payments.card();
                await card.attach('#card-container');
            } catch (e) {
                console.error('Square initialization failed:', e);
                updatePaymentStatus('Payment form initialization failed. Please refresh the page.', 'error');
            }
        }

        async function handlePaymentMethodSubmission(event) {
            event.preventDefault();

            const amountInput = document.getElementById('amount');
            const amount = parseFloat(amountInput.value);

            if (!amount || amount <= 0) {
                updatePaymentStatus('Please enter a valid amount', 'error');
                return;
            }

            const statusContainer = document.getElementById('payment-status');
            const submitButton = document.getElementById('card-button');

            try {
                statusContainer.style.display = 'none';
                submitButton.disabled = true;
                submitButton.textContent = 'Processing...';

                const result = await card.tokenize();
                if (result.status === 'OK') {
                    try {
                        const response = await fetch('/process-payment', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({
                                sourceId: result.token,
                                amount: amount
                            })
                        });

                        if (!response.ok) {
                            const errorText = await response.text();
                            let errorMessage;
                            try {
                                const errorJson = JSON.parse(errorText);
                                errorMessage = errorJson.error || errorJson.message || 'Payment processing failed';
                            } catch (e) {
                                console.error('Response parsing error:', errorText);
                                errorMessage = 'An unexpected error occurred';
                            }
                            throw new Error(errorMessage);
                        }

                        const data = await response.json();

                        if (data.success) {
                            updatePaymentStatus('Payment successful!', 'success');
                            document.getElementById('payment-form').reset();
                            await card.attach('#card-container');
                        } else {
                            throw new Error(data.error || 'Payment failed');
                        }
                    } catch (fetchError) {
                        console.error('Fetch error:', fetchError);
                        throw new Error(fetchError.message || 'Payment processing failed');
                    }
                } else {
                    throw new Error(result.errors[0].message);
                }
            } catch (e) {
                console.error('Payment error:', e);
                updatePaymentStatus(e.message || 'Payment failed. Please try again.', 'error');
            } finally {
                submitButton.disabled = false;
                submitButton.textContent = 'Process Payment';
            }
        }

        function updatePaymentStatus(message, type) {
            const statusContainer = document.getElementById('payment-status');
            statusContainer.className = type;
            statusContainer.textContent = message;
            statusContainer.style.display = 'block';
        }

        document.addEventListener('DOMContentLoaded', async function () {
            try {
                await initializeSquare();
                const cardButton = document.getElementById('card-button');
                cardButton.addEventListener('click', handlePaymentMethodSubmission);
            } catch (e) {
                console.error('Initialization error:', e);
                updatePaymentStatus('Failed to initialize payment form. Please refresh the page.', 'error');
            }
        });
    </script>
</body>
</html> --}}
