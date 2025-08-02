<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>¡Gracias por tu pedido!</title>
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/assets/css/thanks.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="thank-you-container">
        <div class="icon"><i class="fas fa-check-circle"></i></div>
        <h1>¡Gracias por tu pedido!</h1>
        <p>Hemos recibido tu pedido y lo estamos procesando.</p>
        <div class="order-number">Número de pedido: <?php echo htmlspecialchars($orderId); ?></div>
        <p>Preparamos tu pedido en los próximos 3-4 días laborables. Has recibido un email con los detalles.</p>
        <p>Si tienes alguna pregunta, contáctanos.</p>
    </div>
</body>
</html> 