<!DOCTYPE html>
<html>
<head>
    <title>Square Payment Form</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://sandbox.web.squarecdn.com/v1/square.js"></script>
    <style>
        .container {
            max-width: 500px;
            margin: 50px auto;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        #card-container {
            min-height: 90px;
            padding: 12px;
            margin: 20px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        input {
            width: 100%;
            padding: 12px;
            margin: 8px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            width: 100%;
            padding: 12px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:disabled {
            background-color: #cccccc;
        }
        #payment-status {
            margin-top: 20px;
            padding: 12px;
            border-radius: 4px;
            display: none;
        }
        .error { background: #ffebee; color: #c62828; }
        .success { background: #e8f5e9; color: #2e7d32; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Payment Form</h2>
        <form id="payment-form">
            <input type="number" id="amount" min="0.01" step="0.01" placeholder="Enter amount" required>
            <div id="card-container"></div>
            <button type="submit" id="submit-button">Pay Now</button>
            <div id="payment-status"></div>
        </form>
    </div>

    <script>
        let payments;
        let card;
        const appId = '{{ $applicationId }}';
        const locationId = '{{ $locationId }}';

        async function initializeSquare() {
            payments = Square.payments(appId, locationId);
            card = await payments.card();
            await card.attach('#card-container');
        }

        const form = document.getElementById('payment-form');
        const submitButton = document.getElementById('submit-button');
        const statusContainer = document.getElementById('payment-status');

        form.addEventListener('submit', async function(event) {
            event.preventDefault();
            submitButton.disabled = true;
            statusContainer.style.display = 'none';

            try {
                const result = await card.tokenize();
                if (result.status === 'OK') {
                    const amount = document.getElementById('amount').value;

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

                    console.log(response);

                    if(!response.ok) {
                        const errorData = await response.json();
                        throw new Error(errorData.error || 'Payment failed')
                    }

                    const data = await response.json();

                    statusContainer.style.display = 'block';
                    if (data.success) {
                        statusContainer.className = 'success';
                        statusContainer.textContent = data.message;
                        form.reset();
                        await card.attach('#card-container');
                    } else {
                        throw new Error(data.error);
                    }
                }
            } catch (e) {
                console.error(e);
                statusContainer.style.display = 'block';
                statusContainer.className = 'error';
                statusContainer.textContent = e.message;
            }

            submitButton.disabled = false;
        });

        // Initialize Square on page load
        initializeSquare().catch(e => {
            console.error('Square initialization failed:', e);
            statusContainer.style.display = 'block';
            statusContainer.className = 'error';
            statusContainer.textContent = 'Failed to initialize payment form';
        });
    </script>
</body>
</html>
