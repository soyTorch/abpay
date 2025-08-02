<!DOCTYPE html>
<html lang="es">
<head>
    <title>Pago con PayPal</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/assets/css/payment.css">
</head>
<body>
    <?php if (!$paypalAccount): ?>
        <div class="limit-message">
            Hemos alcanzado el máximo de pedidos disponibles para hoy.
            <p>Por favor, inténtalo de nuevo mañana. Gracias por tu comprensión.</p>
        </div>
    <?php else: ?>
        <div id="loader-overlay" style="display:none;">
            <div class="loader-message">
                <div class="spinner"></div>
                <p>Procesando tu pago... por favor espera</p>
            </div>
        </div>

        <div class="container">
            <h1 class="checkout-message">Completa tu pago</h1>
            <div id="paypal-button-container"></div>
        </div>

        <script src="https://www.paypal.com/sdk/js?client-id=<?php echo $paypalAccount['account']['client_id']; ?>&currency=<?php echo $currency; ?>"></script>
        <script>
            paypal.Buttons({
                    createOrder: function(data, actions) {
                        return actions.order.create({
                            purchase_units: [{
                                amount: {
                                    value: '<?php echo $totalAmount; ?>',
                                    currency_code: '<?php echo $currency; ?>'
                                },
                                description: 'Order <?php echo $order_id; ?>'
                            }]
                        });
                    },
                    style: {
                        layout: 'vertical',
                        color: 'gold',
                        shape: 'rect',
                        label: 'paypal',
                    },
                    onApprove: function(data, actions) {
                        document.getElementById("loader-overlay").style.display = "flex";
                        return actions.order.capture().then(function(details) {
                            window.location.href = "<?php echo "https://" . SITE_HOST; ?>/payment/thanks-1f020f05-3e92-4c33-a09a-afa4ed22efa8.php?id=<?php echo $encoded_id; ?>&account=<?php echo $paypalAccount['index']; ?>&account_id=<?php echo isset($paypalAccount['account_id']) ? $paypalAccount['account_id'] : ($paypalAccount['index'] + 1); ?>&details=" + encodeURIComponent(JSON.stringify(details));
                        });
                    },
                    onCancel: function (data) {
                        return;
                    },
                    onError: function (err) {
                        console.error('PayPal error', err);
                        alert('Ocurrió un error con el pago.');
                    }
                }).render('#paypal-button-container');


            document.onkeydown = (e) => {
                if (e.key == 123) {
                    e.preventDefault();
                }
                if (e.ctrlKey && e.shiftKey && e.key == 'I') {
                    e.preventDefault();
                }
                if (e.ctrlKey && e.shiftKey && e.key == 'C') {
                    e.preventDefault();
                }
                if (e.ctrlKey && e.shiftKey && e.key == 'J') {
                    e.preventDefault();
                }
                if (e.ctrlKey && e.key == 'U') {
                    e.preventDefault();
                }
            };

            document.addEventListener('contextmenu', function(e) {
                e.preventDefault();
            });
        </script>
    <?php endif; ?>
</body>
</html> 