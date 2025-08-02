<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido Ya Procesado - Drip Centers</title>
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/assets/css/styles.css">
    <style>
        .already-paid-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            background: #ffffff;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
            font-family: 'Helvetica Neue', Arial, sans-serif;
        }

        .icon-check {
            width: 80px;
            height: 80px;
            background: #4CAF50;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .icon-check svg {
            width: 40px;
            height: 40px;
            fill: white;
        }

        h1 {
            color: #333;
            font-size: 24px;
            margin-bottom: 20px;
        }

        p {
            color: #666;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .contact-info {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .contact-info p {
            color: #888;
            font-size: 14px;
        }

        .order-number {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 5px;
            margin: 20px 0;
            font-family: monospace;
            font-size: 18px;
        }
    </style>
</head>
<body>
    <div class="already-paid-container">
        <div class="icon-check">
            <svg viewBox="0 0 24 24">
                <path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/>
            </svg>
        </div>
        
        <h1>¡Tu pedido ya ha sido procesado!</h1>
        
        <div class="order-number">
            Pedido #<?php echo htmlspecialchars($order_id); ?>
        </div>

        <p>
            El pago de tu pedido ya ha sido confirmado anteriormente. 
            Deberías haber recibido un correo electrónico de confirmación con los detalles de tu compra.
        </p>
        
        <p>
            Por favor, revisa tu bandeja de entrada y la carpeta de spam/correo no deseado.
        </p>

        <div class="contact-info">
            <p>
                ¿No encuentras el correo de confirmación? Contáctanos por Instagram para ayudarte.
            </p>
        </div>
    </div>
</body>
</html> 