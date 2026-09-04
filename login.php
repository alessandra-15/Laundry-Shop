<?php
session_start();
include 'db_connect.php';

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    // fetch user by email
    if ($stmt = $conn->prepare("SELECT * FROM customer_info WHERE email = ? LIMIT 1")) {
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($user = $res->fetch_assoc()) {
            $dbPass = $user['password'] ?? '';
            $authenticated = false;

            // Prefer hashed verification, fallback to plaintext compare
            if (!empty($dbPass) && password_verify($password, $dbPass)) {
                $authenticated = true;
            } elseif ($password === $dbPass) {
                $authenticated = true;
                // Upgrade plaintext to a hash
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                if ($up = $conn->prepare("UPDATE customer_info SET password = ? WHERE Customer_ID = ?")) {
                    $up->bind_param('si', $newHash, $user['Customer_ID']);
                    $up->execute();
                    $up->close();
                }
            }

            if ($authenticated) {
                // Store user info in session
                $_SESSION['customer_id'] = $user['Customer_ID'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['email'] = $user['email'];

                // Record login in user_activity table
                $status = 'Online';
                if ($ins = $conn->prepare("INSERT INTO user_activity (customer_id, login_time, status) VALUES (?, NOW(), ?)")) {
                    $ins->bind_param('is', $user['Customer_ID'], $status);
                    $ins->execute();
                    $_SESSION['activity_id'] = $conn->insert_id;
                    $ins->close();
                }

                header("Location: userdashboard.php");
                exit();
            } else {
                $error_message = 'Invalid email or password. Please try again.';
            }
        } else {
            $error_message = 'Invalid email or password. Please try again.';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MangTV Laundry Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --light-blue: #A8E8F9;
            --dark-blue: #00537A;
            --yellow: #FFD35B;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--light-blue) 0%, #e3f5fc 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            border-radius: 30px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 1000px;
            width: 100%;
            display: flex;
            animation: slideIn 0.6s ease-out;
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
        
        .login-left {
            flex: 1;
            padding: 3rem;
            background: linear-gradient(135deg, rgba(0,83,122,0.95) 0%, rgba(0,107,153,0.9) 100%),
                        url('https://images.unsplash.com/photo-1517677208171-0bc6725a3e60?w=800') center/cover;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
        }
        
        .login-left::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(168,232,249,0.2) 0%, transparent 70%);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(5deg); }
        }
        
        .login-left-content {
            position: relative;
            z-index: 2;
        }
        
        .brand-logo {
            font-size: 1.9rem;
            font-weight: bold;
            color: var(--yellow);
            margin-bottom: 2rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .brand-logo i {
            margin-right: 0;
            font-size: 2.2rem;
        }
        
        .login-left h2 {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 1rem;
        }
        
        .login-left p {
            font-size: 1.1rem;
            opacity: 0.9;
            line-height: 1.6;
        }
        
        .login-right {
            flex: 1;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-header h3 {
            color: var(--dark-blue);
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .login-header p {
            color: #6c757d;
            font-size: 0.95rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--dark-blue);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .form-control {
            padding: 0.75rem 1rem;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            transition: all 0.3s;
            font-size: 0.95rem;
        }
        
        .form-control:focus {
            border-color: var(--light-blue);
            box-shadow: 0 0 0 0.2rem rgba(168,232,249,0.25);
            outline: none;
        }

        .form-control:invalid:not(:placeholder-shown) {
            border-color: var(--yellow);
        }

        .form-control.is-invalid {
            border-color: #dc3545;
            padding-right: calc(1.5em + 0.75rem);
        }
        
        .input-group {
            position: relative;
        }
        
        .input-group-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            z-index: 10;
        }
        
        .input-group .form-control {
            padding-left: 2.75rem;
        }
        
        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            cursor: pointer;
            z-index: 10;
            transition: color 0.3s;
        }
        
        .password-toggle:hover {
            color: var(--dark-blue);
        }

        /* Custom Validation Tooltip */
        .validation-tooltip {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: linear-gradient(135deg, var(--yellow) 0%, #ffe082 100%);
            color: var(--dark-blue);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-top: 0.25rem;
            box-shadow: 0 4px 12px rgba(255,211,91,0.3);
            display: none;
            animation: slideDown 0.3s ease-out;
            z-index: 100;
        }

        .validation-tooltip::before {
            content: '';
            position: absolute;
            top: -5px;
            left: 20px;
            width: 0;
            height: 0;
            border-left: 5px solid transparent;
            border-right: 5px solid transparent;
            border-bottom: 5px solid var(--yellow);
        }

        .validation-tooltip.show {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .validation-tooltip i {
            font-size: 1rem;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-5px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
        }
        
        .form-check-label {
            color: #6c757d;
            cursor: pointer;
        }
        
        .forgot-link {
            color: var(--dark-blue);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }
        
        .forgot-link:hover {
            color: #006b99;
        }
        
        .btn-login {
            width: 100%;
            padding: 0.9rem;
            background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%);
            border: none;
            border-radius: 12px;
            color: white;
            font-weight: bold;
            font-size: 1rem;
            transition: all 0.3s;
            margin-bottom: 1rem;
            position: relative;
        }
        
        .btn-login:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,83,122,0.3);
        }

        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }

        .btn-login .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto;
        }

        .btn-login.loading .spinner {
            display: block;
        }

        .btn-login.loading .btn-text {
            display: none;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .divider {
            text-align: center;
            margin: 1.5rem 0;
            position: relative;
        }
        
        .divider::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            width: 100%;
            height: 1px;
            background: #e9ecef;
        }
        
        .divider span {
            background: white;
            padding: 0 1rem;
            color: #6c757d;
            font-size: 0.85rem;
            position: relative;
            z-index: 1;
        }
        
        .signup-link {
            text-align: center;
            color: #6c757d;
            font-size: 0.95rem;
        }
        
        .signup-link a {
            color: var(--dark-blue);
            text-decoration: none;
            font-weight: bold;
            transition: color 0.3s;
        }
        
        .signup-link a:hover {
            color: #006b99;
        }
        
        .back-home {
            position: absolute;
            top: 2rem;
            left: 2rem;
            color: var(--dark-blue);
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s;
            z-index: 100;
        }
        
        .back-home:hover {
            color: white;
            transform: translateX(-5px);
        }

        /* Error Modal */
        .error-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease-out;
        }

        .error-modal.show {
            display: flex;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .error-modal-content {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.3s ease-out;
            text-align: center;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .error-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            box-shadow: 0 8px 24px rgba(220,53,69,0.3);
        }

        .error-icon i {
            font-size: 2rem;
            color: white;
        }

        .error-modal-content h4 {
            color: var(--dark-blue);
            font-weight: 700;
            margin-bottom: 0.75rem;
        }

        .error-modal-content p {
            color: #6c757d;
            margin-bottom: 1.5rem;
            line-height: 1.6;
        }

        .btn-error-close {
            background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-error-close:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,83,122,0.3);
        }
        
        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
            }
            
            .login-left {
                padding: 2rem;
                min-height: 300px;
            }
            
            .login-left h2 {
                font-size: 1.8rem;
            }
            
            .login-right {
                padding: 2rem;
            }
            
            .back-home {
                top: 1rem;
                left: 1rem;
            }
        }
    </style>
</head>
<body>
    <a href="homepage.php" class="back-home">
        <i class="fas fa-arrow-left"></i> Back to Home
    </a>
    
    <div class="login-container">
        <div class="login-left">
            <div class="login-left-content">
                <div class="brand-logo">
                    <i class="fas fa-tshirt"></i>MangTV Laundry Shop
                </div>
                <h2>Welcome Back!</h2>
                <p>Login to access your account and enjoy hassle-free laundry services. We're here to make your life easier.</p>
            </div>
        </div>
        
        <div class="login-right">
            <div class="login-header">
                <h3>Login to Your Account</h3>
                <p>Enter your credentials to continue</p>
            </div>
            
            <form id="loginForm" method="POST" action="login.php" novalidate>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <div class="input-group">
                        <i class="fas fa-envelope input-group-icon"></i>
                        <input type="email" class="form-control" id="emailInput" name="email" placeholder="Enter your email" required>
                    </div>
                    <div class="validation-tooltip" id="emailTooltip">
                        <i class="fas fa-exclamation-circle"></i>
                        <span>Please enter a valid email address</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock input-group-icon"></i>
                        <input type="password" id="passwordInput" name="password" class="form-control" placeholder="Enter your password" required minlength="6">
                        <i class="fas fa-eye password-toggle" onclick="togglePassword()"></i>
                    </div>
                    <div class="validation-tooltip" id="passwordTooltip">
                        <i class="fas fa-exclamation-circle"></i>
                        <span>Password must be at least 6 characters</span>
                    </div>
                </div>
                
                <div class="remember-forgot">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="rememberMe">
                        <label class="form-check-label" for="rememberMe">
                            Remember me
                        </label>
                    </div>
                    <a href="#" class="forgot-link">Forgot Password?</a>
                </div>
                
                <button type="submit" class="btn btn-login" id="loginBtn">
                    <span class="btn-text">Login</span>
                    <div class="spinner"></div>
                </button>
                
                <div class="divider">
                    <span>Or continue with</span>
                </div>
            
                <div class="signup-link">
                    Don't have an account? <a href="register.php">Sign up now</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Error Modal -->
    <div class="error-modal" id="errorModal">
        <div class="error-modal-content">
            <div class="error-icon">
                <i class="fas fa-times"></i>
            </div>
            <h4>Login Failed</h4>
            <p id="errorMessage">Invalid email or password. Please check your credentials and try again.</p>
            <button class="btn-error-close" onclick="closeErrorModal()">Try Again</button>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const emailInput = document.getElementById('emailInput');
        const passwordInput = document.getElementById('passwordInput');
        const emailTooltip = document.getElementById('emailTooltip');
        const passwordTooltip = document.getElementById('passwordTooltip');
        const loginForm = document.getElementById('loginForm');

        // Show PHP error in modal if exists
        <?php if ($error_message): ?>
            document.addEventListener('DOMContentLoaded', function() {
                showErrorModal('<?php echo addslashes($error_message); ?>');
            });
        <?php endif; ?>

        function showErrorModal(message) {
            document.getElementById('errorMessage').textContent = message;
            document.getElementById('errorModal').classList.add('show');
        }

        function closeErrorModal() {
            document.getElementById('errorModal').classList.remove('show');
        }

        // Close modal on outside click
        document.getElementById('errorModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeErrorModal();
            }
        });

        // Email validation
        emailInput.addEventListener('blur', function() {
            if (!this.validity.valid && this.value) {
                emailTooltip.classList.add('show');
                this.classList.add('is-invalid');
            }
        });

        emailInput.addEventListener('input', function() {
            if (this.validity.valid || !this.value) {
                emailTooltip.classList.remove('show');
                this.classList.remove('is-invalid');
            }
        });

        emailInput.addEventListener('focus', function() {
            emailTooltip.classList.remove('show');
        });

        // Password validation
        passwordInput.addEventListener('blur', function() {
            if (!this.validity.valid && this.value) {
                passwordTooltip.classList.add('show');
                this.classList.add('is-invalid');
            }
        });

        passwordInput.addEventListener('input', function() {
            if (this.validity.valid || !this.value) {
                passwordTooltip.classList.remove('show');
                this.classList.remove('is-invalid');
            }
        });

        passwordInput.addEventListener('focus', function() {
            passwordTooltip.classList.remove('show');
        });

        function togglePassword() {
            const passwordInput = document.getElementById('passwordInput');
            const toggleIcon = document.querySelector('.password-toggle');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
        
        // Form submission with validation
        loginForm.addEventListener('submit', function(e) {
            let isValid = true;

            // Validate email
            if (!emailInput.validity.valid) {
                emailTooltip.classList.add('show');
                emailInput.classList.add('is-invalid');
                isValid = false;
            }

            // Validate password
            if (!passwordInput.validity.valid) {
                passwordTooltip.classList.add('show');
                passwordInput.classList.add('is-invalid');
                isValid = false;
            }

            if (!isValid) {
                e.preventDefault();
                return false;
            }

            // Show loading state
            const loginBtn = document.getElementById('loginBtn');
            loginBtn.disabled = true;
            loginBtn.classList.add('loading');
        });
    </script>
</body>
</html>