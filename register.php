<?php include 'db_connect.php'; ?>

<?php
$error_message = '';
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // basic sanitization
    $first_name = trim($_POST['firstName']);
    $last_name = trim($_POST['lastName']);
    $email = trim($_POST['email']);
    $contact_number = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $account_type = isset($_POST['account_type']) ? $_POST['account_type'] : 'Regular';
    $password = $_POST['password'];
    $confirm_password = $_POST['confirmPassword'];

    $error = false;

    if ($password !== $confirm_password) {
        $error_message = 'Passwords do not match!';
        $error = true;
    }

    // Prepare student ID upload handling and discount
    $student_id_path = null;
    $discount_rate = 0.0;

    if (!$error && $account_type === 'Student') {
        if (!isset($_FILES['student_id']) || $_FILES['student_id']['error'] !== UPLOAD_ERR_OK) {
            $error_message = 'Please upload a valid student ID when selecting Student account.';
            $error = true;
        } else {
            // validate file type
            $allowed_mimes = ['image/jpeg', 'image/png', 'application/pdf'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['student_id']['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime, $allowed_mimes)) {
                $error_message = 'Invalid student ID file type. Allowed: JPG, PNG or PDF.';
                $error = true;
            } else {
                $ext = pathinfo($_FILES['student_id']['name'], PATHINFO_EXTENSION);
                $newFileName = uniqid('sid_') . '.' . $ext;
                $targetDir = 'uploads/student_ids/';
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                $targetPath = $targetDir . $newFileName;

                if (!move_uploaded_file($_FILES['student_id']['tmp_name'], $targetPath)) {
                    $error_message = 'Failed to save uploaded student ID.';
                    $error = true;
                } else {
                    // saved successfully
                    $student_id_path = $targetPath;
                    $discount_rate = 0.10; // 10% discount for students
                }
            }
        }
    }

    if (!$error) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $register_date = date("Y-m-d");
        $created_at = date("Y-m-d H:i:s");
        $updated_at = $created_at;

        $stmt = $conn->prepare("INSERT INTO customer_info (first_name, last_name, email, register_date, contact_number, Address, account_type, student_id_path, discount_rate, password, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if ($stmt === false) {
            $error_message = 'Database error: failed to prepare statement.';
        } else {
            $stmt->bind_param("ssssssssdsss", $first_name, $last_name, $email, $register_date, $contact_number, $address, $account_type, $student_id_path, $discount_rate, $hashed_password, $created_at, $updated_at);

            if ($stmt->execute()) {
                $success_message = 'Registration successful! Welcome to MangTV Laundry Shop.';
                echo "<script>
                    setTimeout(function() {
                        window.location.href = 'login.php';
                    }, 2000);
                </script>";
            } else {
                $error_message = 'Error: Unable to register user.';
            }

            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - MangTV Laundry Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { 
            --light-blue: #A8E8F9; 
            --dark-blue: #00537A; 
            --yellow: #FFD35B; 
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--light-blue) 0%, #e3f5fc 100%);
            min-height: 100vh; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            padding: 20px;
        }
        
        .register-container {
            background: white;
            border-radius: 25px;
            box-shadow: 0 20px 60px rgba(0, 83, 122, 0.15);
            overflow: hidden;
            max-width: 1110px;
            width: 100%;
            display: flex;
            animation: slideIn 0.6s ease-out;
        }

        @keyframes slideIn { 
            from { opacity: 0; transform: translateY(30px); } 
            to { opacity: 1; transform: translateY(0); } 
        }
        
        .register-left {
            background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%);
            padding: 3rem; 
            flex: 1; 
            display: flex; 
            flex-direction: column;
            justify-content: center; 
            color: white; 
            position: relative; 
            overflow: hidden;
        }
        
        .register-left::before {
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
            0%,100%{transform:translateY(0)rotate(0deg);}
            50%{transform:translateY(-20px)rotate(5deg);} 
        }
        
        .register-left-content { position: relative; z-index: 2; }
        
        .brand-logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 2rem;
            font-weight: bold;
            color: var(--yellow);
            margin-bottom: 2rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }

        .brand-logo i {
            font-size: 2.2rem;
        }

        .brand-logo h2 {
            font-size: 1.8rem;
            font-weight: bold;
            margin: 0;
        }
        
        .register-left h3 { font-size: 2rem; margin-bottom: 1rem; font-weight: 700; }
        .register-left p { font-size: 1.05rem; line-height: 1.8; opacity: 0.95; margin-bottom: 2rem; }
        
        .feature-list { list-style: none; padding: 0; }
        .feature-list li { padding: 0.8rem 0; display: flex; align-items: center; font-size: 1rem; }
        .feature-list i { color: var(--yellow); margin-right: 1rem; font-size: 1.2rem; }
        
        .register-right { padding: 3rem; flex: 1; display: flex; flex-direction: column; justify-content: center; }
        
        .register-header { text-align: center; margin-bottom: 2rem; }
        .register-header h3 { color: var(--dark-blue); font-size: 2rem; font-weight: 700; margin-bottom: 0.5rem; }
        .register-header p { color: #6c757d; font-size: 0.95rem; }
        
        .form-group {
            margin-bottom: 1.5rem;
            width: 100%;
        }

        .form-label {
            color: var(--dark-blue);
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 0.8rem 1rem;
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        .form-control:focus {
            border-color: var(--light-blue);
            box-shadow: 0 0 0 0.2rem rgba(168,232,249,0.25);
        }

        .form-control:invalid:focus {
            border-color: var(--yellow);
            box-shadow: 0 0 0 0.2rem rgba(255,211,91,0.25);
        }

        .form-row {
            display: flex;
            gap: 20px;
            width: 100%;
            margin-bottom: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }
        
        .input-group { position: relative; }
        .input-group i { position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: #6c757d; z-index: 5; }
        .input-group .form-control { padding-left: 2.8rem; }
        
        .btn-register {
            background: linear-gradient(135deg, var(--yellow) 0%, #ffe082 100%);
            color: var(--dark-blue); 
            border: none; 
            padding: 0.9rem; 
            border-radius: 12px;
            font-weight: 700; 
            font-size: 1rem; 
            width: 100%; 
            transition: all 0.3s;
            margin-top: 1rem; 
            text-transform: uppercase; 
            letter-spacing: 1px;
        }
        
        .btn-register:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 8px 20px rgba(255,211,91,0.4); 
        }
        
        .login-link { 
            text-align: center; 
            margin-top: 1.5rem; 
            color: #6c757d; 
            font-size: 0.95rem; 
        }
        
        .login-link a { 
            color: var(--dark-blue); 
            font-weight: 600; 
            text-decoration: none; 
            transition: color 0.3s; 
        }
        
        .login-link a:hover { color: #006b99; }

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

        /* Alert Styles */
        .alert-custom {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideDown 0.3s ease-out;
        }

        .alert-success-custom {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-danger-custom {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Modal Styles */
        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 1.5rem;
            border: none;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
            opacity: 0.8;
        }

        .modal-header .btn-close:hover {
            opacity: 1;
        }

        .modal-title {
            font-weight: 700;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .modal-body {
            padding: 2rem;
            max-height: 60vh;
            overflow-y: auto;
        }

        .modal-body::-webkit-scrollbar {
            width: 8px;
        }

        .modal-body::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .modal-body::-webkit-scrollbar-thumb {
            background: var(--light-blue);
            border-radius: 10px;
        }

        .modal-body::-webkit-scrollbar-thumb:hover {
            background: var(--dark-blue);
        }

        .modal-section {
            margin-bottom: 2rem;
        }

        .modal-section h5 {
            color: var(--dark-blue);
            font-weight: 700;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--light-blue);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .modal-section h5 i {
            color: var(--yellow);
        }

        .modal-section p, .modal-section li {
            color: #555;
            line-height: 1.8;
            margin-bottom: 0.75rem;
        }

        .modal-section ul {
            padding-left: 1.5rem;
        }

        .modal-section li {
            margin-bottom: 0.5rem;
        }

        .modal-footer {
            border: none;
            padding: 1rem 2rem;
            background: var(--light-blue);
            border-radius: 0 0 20px 20px;
        }

        .btn-modal-close {
            background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%);
            color: white;
            border: none;
            padding: 0.65rem 2rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-modal-close:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,83,122,0.3);
        }

        .form-check-label a {
            color: var(--dark-blue);
            font-weight: 600;
            text-decoration: none;
            transition: color 0.3s;
        }

        .form-check-label a:hover {
            color: #006b99;
            text-decoration: underline;
        }

        @media (max-width:992px) {
            .back-home {
                top: 1rem;
                left: 1rem;
            }

            .register-container {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <a href="homepage.php" class="back-home">
        <i class="fas fa-arrow-left"></i> Back to Home
    </a>
    
    <div class="register-container">
        <div class="register-left">
            <div class="register-left-content">
                <div class="brand-logo">
                    <i class="fas fa-tshirt"></i>
                    <h2>MangTV Laundry Shop</h2>
                </div>
                <h3>Start Your Hassle-Free Laundry Journey</h3>
                <p>Join thousands of satisfied customers who enjoy fresh, clean clothes without the stress.</p>
                <ul class="feature-list">
                    <li><i class="fas fa-check-circle"></i>Free pickup and delivery service</li>
                    <li><i class="fas fa-check-circle"></i>24/7 online booking system</li>
                    <li><i class="fas fa-check-circle"></i>Premium eco-friendly detergents</li>
                    <li><i class="fas fa-check-circle"></i>Real-time order tracking</li>
                    <li><i class="fas fa-check-circle"></i>Same-day service available</li>
                </ul>
            </div>
        </div>

        <div class="register-right">
            <div class="register-header">
                <h3>Create Account</h3>
                <p>Fill in your details to get started</p>
            </div>

            <?php if ($success_message): ?>
                <div class="alert-custom alert-success-custom">
                    <i class="fas fa-check-circle fa-lg"></i>
                    <span><?php echo $success_message; ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert-custom alert-danger-custom">
                    <i class="fas fa-exclamation-circle fa-lg"></i>
                    <span><?php echo $error_message; ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">First Name</label>
                            <div class="input-group">
                                <i class="fas fa-user"></i>
                                <input type="text" name="firstName" class="form-control" placeholder="Enter first name" required>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Last Name</label>
                            <div class="input-group">
                                <i class="fas fa-user"></i>
                                <input type="text" name="lastName" class="form-control" placeholder="Enter last name" required>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <div class="input-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <div class="input-group">
                        <i class="fas fa-phone"></i>
                        <input type="tel" name="phone" class="form-control" placeholder="+63 912 345 6789" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Address</label>
                    <div class="input-group">
                        <i class="fas fa-map-marker-alt"></i>
                        <input type="text" name="address" class="form-control" placeholder="Enter your address" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Account Type</label>
                    <div class="input-group">
                        <i class="fas fa-user-tag"></i>
                        <select name="account_type" id="account_type" class="form-control">
                            <option value="Regular">Regular</option>
                            <option value="Student">Student</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" id="student_id_group" style="display:none;">
                    <label class="form-label">Upload Student ID (JPG, PNG or PDF)</label>
                    <div class="input-group">
                        <i class="fas fa-id-card"></i>
                        <input type="file" name="student_id" id="student_id" class="form-control">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="password" class="form-control" placeholder="Create a password" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Confirm Password</label>
                        <div class="input-group">
                            <i class="fas fa-lock"></i>
                            <input type="password" name="confirmPassword" class="form-control" placeholder="Confirm your password" required>
                        </div>
                    </div>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="terms" required>
                    <label class="form-check-label" for="terms">
                        I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and Conditions</a> and <a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal">Privacy Policy</a>
                    </label>
                </div>

                <button type="submit" class="btn btn-register">Create Account</button>
                <div class="login-link">Already have an account? <a href="login.php">Login here</a></div>
            </form>
        </div>
    </div>

    <!-- Terms and Conditions Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-file-contract"></i>
                        Terms and Conditions
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="modal-section">
                        <h5><i class="fas fa-info-circle"></i>1. Service Agreement</h5>
                        <p>By using MangTV Laundry Shop services, you agree to our terms of service. We provide professional laundry services including washing, drying, folding, and delivery of your garments.</p>
                    </div>

                    <div class="modal-section">
                        <h5><i class="fas fa-shield-alt"></i>2. Customer Responsibilities</h5>
                        <ul>
                            <li>Provide accurate pickup and delivery information</li>
                            <li>Check garments before submitting for service</li>
                            <li>Remove all valuables from pockets</li>
                            <li>Inform us of any special care requirements</li>
                            <li>Report any issues within 24 hours of delivery</li>
                        </ul>
                    </div>

                    <div class="modal-section">
                        <h5><i class="fas fa-exclamation-triangle"></i>3. Liability and Damages</h5>
                        <p>While we take utmost care of your garments, MangTV Laundry Shop is not liable for:</p>
                        <ul>
                            <li>Damage to garments with pre-existing conditions</li>
                            <li>Shrinkage of wool or synthetic fabrics</li>
                            <li>Color bleeding from improperly dyed fabrics</li>
                            <li>Loss of buttons, belts, or decorative items</li>
                            <li>Items not included in our service list</li>
                        </ul>
                    </div>

                    <div class="modal-section">
                        <h5><i class="fas fa-credit-card"></i>4. Payment Terms</h5>
                        <p>Payment is required upon completion of service. We accept cash, GCash, and bank transfers. Student discounts are available with valid ID.</p>
                    </div>

                    <div class="modal-section">
                        <h5><i class="fas fa-clock"></i>5. Service Timeline</h5>
                        <p>Standard service takes 2-3 days. Rush service is available for an additional fee. Delays may occur during peak seasons or holidays.</p>
                    </div>

                    <div class="modal-section">
                        <h5><i class="fas fa-ban"></i>6. Cancellation Policy</h5>
                        <p>Cancellations must be made at least 2 hours before scheduled pickup. Late cancellations may incur a fee.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-close" data-bs-dismiss="modal">I Understand</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Privacy Policy Modal -->
    <div class="modal fade" id="privacyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-shield"></i>
                        Privacy Policy
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="modal-section">
                        <h5><i class="fas fa-database"></i>1. Information We Collect</h5>
                        <p>We collect the following information to provide our services:</p>
                        <ul>
                            <li>Personal details (name, email, phone number)</li>
                            <li>Address for pickup and delivery</li>
                            <li>Payment information</li>
                            <li>Service preferences and history</li>
                            <li>Student ID (for student accounts)</li>
                        </ul>
                    </div>

                    <div class="modal-section">
                        <h5><i class="fas fa-lock"></i>2. How We Use Your Information</h5>
                        <p>Your information is used to:</p>
                        <ul>
                            <li>Process and deliver your laundry orders</li>
                            <li>Send service updates and notifications</li>
                            <li>Improve our services based on feedback</li>
                            <li>Verify student status for discounts</li>
                            <li>Handle customer support inquiries</li>
                        </ul>
                    </div>

                    <div class="modal-section">
                        <h5><i class="fas fa-shield-alt"></i>3. Data Protection</h5>
                        <p>We implement security measures to protect your data:</p>
                        <ul>
                            <li>Encrypted password storage</li>
                            <li>Secure payment processing</li>
                            <li>Limited staff access to personal information</li>
                            <li>Regular security audits</li>
                            <li>Secure data transmission (SSL/TLS)</li>
                        </ul>
                    </div>

                    <div class="modal-section">
                        <h5><i class="fas fa-share-alt"></i>4. Information Sharing</h5>
                        <p>We DO NOT sell or share your personal information with third parties, except:</p>
                        <ul>
                            <li>Payment processors (for transaction processing)</li>
                            <li>Delivery partners (for address information only)</li>
                            <li>Legal authorities (when required by law)</li>
                        </ul>
                    </div>

                    <div class="modal-section">
                        <h5><i class="fas fa-cookie"></i>5. Cookies and Tracking</h5>
                        <p>We use cookies to enhance your experience and track service usage. You can disable cookies in your browser settings, but some features may not work properly.</p>
                    </div>

                    <div class="modal-section">
                        <h5><i class="fas fa-user-edit"></i>6. Your Rights</h5>
                        <p>You have the right to:</p>
                        <ul>
                            <li>Access your personal data</li>
                            <li>Request data correction or deletion</li>
                            <li>Opt-out of marketing communications</li>
                            <li>Download your data</li>
                            <li>Close your account</li>
                        </ul>
                    </div>

                    <div class="modal-section">
                        <h5><i class="fas fa-envelope"></i>7. Contact Us</h5>
                        <p>For privacy concerns or questions, contact us at:</p>
                        <ul>
                            <li>Email: privacy@mangtvlaundry.com</li>
                            <li>Phone: +63 912 345 6789</li>
                            <li>Address: Batangas, Calabarzon, Philippines</li>
                        </ul>
                    </div>

                    <div class="modal-section">
                        <h5><i class="fas fa-sync-alt"></i>8. Policy Updates</h5>
                        <p>We may update this privacy policy from time to time. Continued use of our services after changes constitutes acceptance of the updated policy.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-close" data-bs-dismiss="modal">I Understand</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle student ID upload visibility
        (function(){
            var acc = document.getElementById('account_type');
            var sidGroup = document.getElementById('student_id_group');
            var sidInput = document.getElementById('student_id');

            function toggle() {
                if (!acc) return;
                if (acc.value === 'Student') {
                    sidGroup.style.display = 'block';
                    if (sidInput) sidInput.required = true;
                } else {
                    sidGroup.style.display = 'none';
                    if (sidInput) sidInput.required = false;
                }
            }

            if (acc) {
                acc.addEventListener('change', toggle);
                toggle();
            }
        })();
    </script>
</body>
</html>