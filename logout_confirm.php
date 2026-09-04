<?php
session_start();

if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit();
}

$firstName = $_SESSION['first_name'] ?? '';
$lastName = $_SESSION['last_name'] ?? '';
$fullName = trim($firstName . ' ' . $lastName);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Logout - MangTV Laundry Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --dark-blue: #00537A;
            --yellow: #FFD35B;
            --light-blue: #A8E8F9;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(168,232,249,0.1) 0%, transparent 70%);
            animation: rotate 30s linear infinite;
        }

        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .logout-container {
            background: white;
            border-radius: 24px;
            padding: 3rem 2.5rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 480px;
            width: 100%;
            text-align: center;
            animation: slideIn 0.5s ease-out;
            position: relative;
            z-index: 1;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo-container {
            margin-bottom: 2rem;
        }

        .brand-logo {
            width: 90px;
            height: 90px;
            background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            box-shadow: 0 10px 30px rgba(0,83,122,0.3);
            position: relative;
        }

        .brand-logo::before {
            content: '';
            position: absolute;
            width: 110px;
            height: 110px;
            border: 3px solid var(--yellow);
            border-radius: 50%;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.1);
                opacity: 0.5;
            }
        }

        .brand-logo i {
            font-size: 2.8rem;
            color: var(--yellow);
            position: relative;
            z-index: 1;
        }

        .brand-text {
            margin-top: 1rem;
            color: var(--dark-blue);
        }

        .brand-text h4 {
            font-weight: 700;
            font-size: 1.6rem;
            margin-bottom: 0.25rem;
        }

        .brand-text p {
            color: #6c757d;
            font-size: 0.9rem;
            margin: 0;
        }

        .logout-icon {
            font-size: 3.5rem;
            color: var(--yellow);
            margin: 1.5rem 0;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-10px);
            }
        }

        h3 {
            color: var(--dark-blue);
            font-weight: 700;
            margin-bottom: 1rem;
            font-size: 1.75rem;
        }

        .logout-message {
            color: #6c757d;
            margin-bottom: 2rem;
            font-size: 0.95rem;
            line-height: 1.7;
        }

        .user-info {
            background: linear-gradient(135deg, var(--light-blue) 0%, rgba(168,232,249,0.3) 100%);
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin: 1.5rem 0;
            border-left: 4px solid var(--dark-blue);
        }

        .user-info strong {
            color: var(--dark-blue);
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .user-info strong i {
            color: var(--yellow);
        }

        .btn-group-custom {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn-custom {
            flex: 1;
            padding: 0.85rem 1.5rem;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
        }

        .btn-logout {
            background: linear-gradient(135deg, var(--yellow) 0%, #ffe082 100%);
            color: var(--dark-blue);
            box-shadow: 0 4px 15px rgba(255,213,91,0.3);
        }

        .btn-logout:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255,213,91,0.4);
            color: var(--dark-blue);
        }

        .btn-stay {
            background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(0,83,122,0.3);
        }

        .btn-stay:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,83,122,0.4);
            color: white;
        }

        .divider {
            margin: 2rem 0 1.5rem;
            text-align: center;
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            width: 100%;
            height: 1px;
            background: linear-gradient(to right, transparent, rgba(168,232,249,0.5), transparent);
        }

        .divider span {
            background: white;
            padding: 0 1rem;
            color: #6c757d;
            font-size: 0.85rem;
            position: relative;
            z-index: 1;
            font-weight: 500;
        }

        .footer-text {
            color: #adb5bd;
            font-size: 0.85rem;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .footer-text i {
            color: var(--yellow);
        }

        @media (max-width: 576px) {
            .logout-container {
                padding: 2rem 1.5rem;
            }

            .btn-group-custom {
                flex-direction: column;
            }

            h3 {
                font-size: 1.5rem;
            }

            .brand-logo {
                width: 80px;
                height: 80px;
            }

            .brand-logo i {
                font-size: 2.2rem;
            }
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="logo-container">
            <div class="brand-logo">
                <i class="fas fa-tshirt"></i>
            </div>
            <div class="brand-text">
                <h4>MangTV Laundry Shop</h4>
                <p>Your Trusted Laundry Partner</p>
            </div>
        </div>
        
        <div class="logout-icon">
            <i class="fas fa-sign-out-alt"></i>
        </div>
        
        <h3>Leaving So Soon?</h3>
        
        <div class="logout-message">
            <p>You are about to log out of your account</p>
        </div>

        <div class="user-info">
            <strong>
                <i class="fas fa-user-circle"></i>
                <?php echo htmlspecialchars($fullName); ?>
            </strong>
        </div>

        <p style="color: #6c757d; font-size: 0.9rem; margin-top: 1rem;">
            Don't worry, your data is safe! You can log back in anytime.
        </p>

        <div class="btn-group-custom">
            <a href="homepage.php" class="btn-custom btn-logout">
                <i class="fas fa-sign-out-alt"></i>
                <span>Yes, Logout</span>
            </a>
            <a href="userdashboard.php" class="btn-custom btn-stay">
                <i class="fas fa-arrow-left"></i>
                <span>Stay Logged In</span>
            </a>
        </div>

        <div class="divider">
            <span>Thank you for using our service</span>
        </div>

        <p class="footer-text">
            <i class="fas fa-heart"></i>
            We hope to see you again soon!
        </p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add logout functionality
        document.querySelector('.btn-logout').addEventListener('click', function(e) {
            e.preventDefault();
            // Clear session and redirect
            window.location.href = 'homepage.php';

        });
    </script>
</body>
</html>