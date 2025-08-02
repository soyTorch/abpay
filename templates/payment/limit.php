<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Límite de Pagos Alcanzado</title>
    <link rel="stylesheet" href="<?php echo BASE_PATH; ?>/assets/css/payment.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
            --text-color: #1f2937;
            --light-text: #6b7280;
            --background: #f3f4f6;
            --card-background: #ffffff;
            --border-radius: 1rem;
            --shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--background);
            color: var(--text-color);
            line-height: 1.6;
        }

        .limit-message {
            background: var(--card-background);
            padding: 2.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            max-width: 90%;
            width: 500px;
            text-align: center;
            margin: 1rem;
        }

        h1 {
            color: var(--primary-color);
            font-size: 1.75rem;
            margin-bottom: 1.5rem;
            font-weight: 700;
        }

        p {
            margin: 1rem 0;
            color: var(--light-text);
        }

        .countdown-container {
            margin: 2rem 0;
            padding: 1.5rem;
            background: var(--background);
            border-radius: calc(var(--border-radius) / 2);
        }

        .countdown {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 1rem;
        }

        .countdown-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            min-width: 60px;
        }

        .countdown-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .countdown-label {
            font-size: 0.875rem;
            color: var(--light-text);
            text-transform: uppercase;
        }

        .retry-info {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--background);
        }

        .retry-info p {
            color: var(--primary-color);
            font-weight: 500;
        }

        @media (max-width: 480px) {
            .limit-message {
                padding: 1.5rem;
            }

            h1 {
                font-size: 1.5rem;
            }

            .countdown-value {
                font-size: 1.5rem;
            }

            .countdown-item {
                min-width: 50px;
            }
        }
    </style>
</head>
<body>
    <div class="limit-message">
        <h1>Límite de Pagos Alcanzado</h1>
        <p>Lo sentimos, hemos alcanzado el límite de pagos disponibles en todos nuestros sistemas.</p>
        <p>Por favor, inténtalo de nuevo cuando el contador llegue a cero.</p>
        
        <div class="countdown-container">
            <div class="countdown">
                <div class="countdown-item">
                    <span class="countdown-value" id="hours">--</span>
                    <span class="countdown-label">Horas</span>
                </div>
                <div class="countdown-item">
                    <span class="countdown-value" id="minutes">--</span>
                    <span class="countdown-label">Minutos</span>
                </div>
                <div class="countdown-item">
                    <span class="countdown-value" id="seconds">--</span>
                    <span class="countdown-label">Segundos</span>
                </div>
            </div>
        </div>

        <div class="retry-info">
            <p>El sistema se reinicia cada día a las 00:00 (hora española).</p>
        </div>
    </div>

    <script>
        function updateCountdown() {
            const now = new Date();
            const madridTime = new Date(now.toLocaleString('en-US', { timeZone: 'Europe/Madrid' }));
            const tomorrow = new Date(madridTime);
            tomorrow.setDate(tomorrow.getDate() + 1);
            tomorrow.setHours(0, 0, 0, 0);
            
            const diff = tomorrow - madridTime;
            
            const hours = Math.floor(diff / (1000 * 60 * 60));
            const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((diff % (1000 * 60)) / 1000);
            
            document.getElementById('hours').textContent = hours.toString().padStart(2, '0');
            document.getElementById('minutes').textContent = minutes.toString().padStart(2, '0');
            document.getElementById('seconds').textContent = seconds.toString().padStart(2, '0');
        }

        // Update immediately and then every second
        updateCountdown();
        setInterval(updateCountdown, 1000);
    </script>
</body>
</html> 