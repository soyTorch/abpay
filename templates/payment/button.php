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
        <div class="container">
            <h1 class="checkout-message">Completa tu pago</h1>
            <div class="paypal-form-container">
                <form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_blank">
                    <!-- Tipo de botón -->
                    <input type="hidden" name="cmd" value="_xclick">
                    <!-- Email de PayPal -->
                    <input type="hidden" name="business" value="<?php echo htmlspecialchars($paypalAccount['account']['email']); ?>">
                    <!-- Datos del producto -->
                    <input type="hidden" name="item_name" value="Pedido #<?php echo htmlspecialchars($order_id); ?>">
                    <input type="hidden" name="amount" value="<?php echo htmlspecialchars($totalAmount); ?>">
                    <input type="hidden" name="currency_code" value="<?php echo htmlspecialchars($currency); ?>">
                    <!-- URLs de retorno -->
                    <input type="hidden" name="return" value="<?php echo "https://" . SITE_HOST; ?>/thanks-1f020f05-3e92-4c33-a09a-afa4ed22efa8.php?id=<?php echo $encoded_id; ?>&account=<?php echo $paypalAccount['index']; ?>&account_id=<?php echo isset($paypalAccount['account_id']) ? $paypalAccount['account_id'] : ($paypalAccount['index'] + 1); ?>&status=success">
                    <input type="hidden" name="cancel_return" value="<?php echo "https://" . SITE_HOST; ?>/button.php?id=<?php echo $encoded_id; ?>">
                    <!-- IPN URL (opcional) -->
                    <input type="hidden" name="notify_url" value="<?php echo "https://" . SITE_HOST; ?>/thanks-1f020f05-3e92-4c33-a09a-afa4ed22efa8.php">
                    <!-- Custom para tracking -->
                    <input type="hidden" name="custom" value="<?php echo $encoded_id . '|' . $paypalAccount['index']; ?>">
                    
                    <!-- Botón de pago -->
                    <input type="submit" value="Pagar con PayPal" class="paypal-button">
                </form>
                
                <p class="payment-info">
                    Serás redirigido a PayPal para completar tu pago de forma segura.
                </p>
            </div>
        </div>

        <script>
            // Prevenir teclas que podrían cerrar la ventana
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