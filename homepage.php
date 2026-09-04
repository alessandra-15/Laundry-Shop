<?php include 'db_connect.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MangTV Laundry Shop - Fresh Clothes, Hassle-Free Life</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --light-blue: #A8E8F9;
            --dark-blue: #00537A;
            --yellow: #FFF35B;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }
        
        /* Navigation */
        .navbar {
            background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%);
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
            color: var(--yellow) !important;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        
       .nav-link {
    color: white !important;
    margin: 0 0.5rem;
    transition: all 0.3s;
    position: relative;
}

.nav-link:hover {
    color: var(--yellow) !important;
    transform: translateY(-2px);
}

.nav-link.active {
    color: var(--yellow) !important;
}

.nav-link.active::after {
    width: 80% !important;
}
        
       .btn-register {
    background-color: var(--yellow);
    color: var(--dark-blue);
    font-weight: bold;
    border: none;
    padding: 0.5rem 1.5rem;
    border-radius: 25px;
    transition: all 0.3s;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}

.btn-register:hover {
    background-color: #fff94a;
    transform: scale(1.05);
    box-shadow: 0 5px 15px rgba(255,243,91,0.4);
    color: var(--dark-blue);
}
        
        /* Hero Section */
        .hero {
            background: linear-gradient(135deg, rgba(0,83,122,0.9) 0%, rgba(0,107,153,0.85) 100%),
                        url('https://images.unsplash.com/photo-1517677208171-0bc6725a3e60?w=1600') center/cover;
            min-height: 90vh;
            display: flex;
            align-items: center;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(168,232,249,0.2) 0%, transparent 70%);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-30px) rotate(5deg); }
        }
        
        @keyframes sparkle {
        0%, 100% { transform: scale(1) rotate(0deg); }
        50% { transform: scale(1.2) rotate(180deg); }
    }

        .hero-content {
            position: relative;
            z-index: 2;
        }
        
        .hero h1 {
            font-size: 3.5rem;
            font-weight: bold;
            margin-bottom: 1rem;
            text-shadow: 3px 3px 6px rgba(0,0,0,0.3);
            animation: slideInLeft 1s ease-out;
        }
        
        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .hero .tagline {
            font-size: 2rem;
            color: var(--light-blue);
            margin-bottom: 0.5rem;
            animation: slideInLeft 1s ease-out 0.2s both;
        }
        
        .hero .sub-tagline {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.95;
            animation: slideInLeft 1s ease-out 0.4s both;
        }
        
        .hero-buttons .btn {
    margin: 0.5rem;
    padding: 0.8rem 2.5rem;
    font-size: 1.1rem;
    border-radius: 30px;
    transition: all 0.3s;
    animation: slideInUp 1s ease-out 0.6s both;
    position: relative;
}

.hero-buttons .btn.highlight-pulse {
    animation: highlightPulse 1.5s ease-in-out 3;
}

@keyframes highlightPulse {
    0%, 100% {
        transform: scale(1);
        box-shadow: 0 0 0 0 rgba(255, 243, 91, 0.7);
    }
    50% {
        transform: scale(1.1);
        box-shadow: 0 0 30px 15px rgba(255, 243, 91, 0);
    }
}
        
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .btn-primary-custom {
            background-color: var(--yellow);
            color: var(--dark-blue);
            font-weight: bold;
            border: none;
        }
        
        .btn-primary-custom:hover {
            background-color: #fff94a;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(255,243,91,0.4);
        }
        
        .btn-outline-custom {
            border: 2px solid white;
            color: white;
            font-weight: bold;
        }
        
        .btn-outline-custom:hover {
            background-color: white;
            color: var(--dark-blue);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(255,255,255,0.3);
        }
        
        /* About Section */
        .about {
            padding: 5rem 0;
            background: linear-gradient(180deg, white 0%, var(--light-blue) 100%);
        }
        
        .image-grid-about {
            position: relative;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            max-width: 520px;
        }
        
        .image-box-about {
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 15px 40px rgba(0,0,0,0.12);
            position: relative;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .image-box-about:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 50px rgba(0,0,0,0.18);
        }
        
        .image-box-about img {
            width: 100%;
            height: 280px;
            object-fit: cover;
            display: block;
        }
        
        .image-box-about.large {
            grid-row: span 2;
        }
        
        .image-box-about.large img {
            height: 100%;
            min-height: 580px;
        }
        
        .sparkle-icon-about {
            position: absolute;
            color: var(--yellow);
            font-size: 2.5rem;
            top: -40px;
            left: 30px;
            animation: float 3s ease-in-out infinite;
            z-index: 5;
        }
        
        .book-now-card-about {
            position: absolute;
            bottom: -40px;
            right: -40px;
            background: linear-gradient(135deg, var(--dark-blue) 0%, #006992 100%);
            color: white;
            padding: 30px 40px;
            border-radius: 20px;
            box-shadow: 0 20px 50px rgba(0,83,122,0.35);
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 10;
        }
        
        .book-now-card-about:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 25px 60px rgba(0,83,122,0.45);
        }
        
        .book-now-card-about i {
            font-size: 2.2rem;
            margin-bottom: 12px;
            display: block;
        }
        
        .book-now-card-about h5 {
            margin: 0;
            font-weight: bold;
            font-size: 1.2rem;
            letter-spacing: 1px;
        }
        
        .book-now-card-about p {
            margin: 8px 0 0 0;
            font-size: 0.88rem;
            opacity: 0.95;
            line-height: 1.4;
        }
        
        .content-section-about {
            padding: 20px 0;
        }
        
        .badge-custom-about {
            background-color: var(--yellow);
            color: var(--dark-blue);
            padding: 10px 25px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 1.5px;
            display: inline-block;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px rgba(255,243,91,0.3);
        }
        
        .main-heading-about {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--dark-blue);
            line-height: 1.2;
            margin-bottom: 25px;
        }
        
        .description-text-about {
            color: #6c757d;
            font-size: 1.05rem;
            line-height: 1.7;
            margin-bottom: 40px;
        }
        
        .feature-item-about {
            display: flex;
            align-items: flex-start;
            margin-bottom: 30px;
            padding: 20px;
            background: white;
            border-radius: 15px;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }
        
        .feature-item-about:hover {
            transform: translateX(10px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .feature-icon-about {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--yellow) 0%, #ffe082 100%);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 25px;
            flex-shrink: 0;
            box-shadow: 0 8px 20px rgba(255,243,91,0.3);
        }
        
        .feature-icon-about i {
            color: var(--dark-blue);
            font-size: 1.5rem;
        }
        
        .feature-content-about h4 {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 12px;
            color: var(--dark-blue);
        }
        
        .feature-content-about p {
            color: #6c757d;
            font-size: 0.95rem;
            line-height: 1.7;
            margin: 0;
        }
        
        /* Services Section */
        .services {
            padding: 5rem 0;
            background: white;
            position: relative;
        }
        
        .section-title {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--dark-blue);
            margin-bottom: 3rem;
            position: relative;
            display: inline-block;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 4px;
            background: linear-gradient(90deg, var(--dark-blue) 0%, var(--yellow) 100%);
            border-radius: 2px;
        }
        
        .services-badge {
            background: var(--yellow);
            color: var(--dark-blue);
            padding: 0.75rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            display: inline-block;
            margin-bottom: 2rem;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            box-shadow: 0 8px 20px rgba(255, 243, 91, 0.3);
            animation: fadeInDown 0.8s ease-out;
        }
        
        .services-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark-blue);
            line-height: 1.2;
            margin-bottom: 1.5rem;
        }
        
        .services-subtitle {
            font-size: 1.1rem;
            color: #6c757d;
            font-weight: 300;
            margin-bottom: 0;
        }
        
        .service-card-new {
            border: none;
            border-radius: 20px;
            overflow: hidden;
            background: white;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            height: 100%;
            position: relative;
        }
        
        .service-card-new::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0) 100%);
            opacity: 0;
            transition: opacity 0.4s ease;
            pointer-events: none;
            z-index: 1;
        }
        
        .service-card-new:hover {
            transform: translateY(-15px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        }
        
        .service-card-new:hover::before {
            opacity: 1;
        }
        
        .image-container-service {
            position: relative;
            height: 280px;
            overflow: hidden;
        }
        
        .image-container-service::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(to bottom, transparent 0%, rgba(0,0,0,0.4) 100%);
            transition: opacity 0.4s ease;
        }
        
        .service-card-new:hover .image-container-service::after {
            opacity: 0.7;
        }
        
        .service-card-new:nth-child(1) .image-container-service {
            background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%);
        }
        
        .service-card-new:nth-child(2) .image-container-service {
            background: linear-gradient(135deg, #A8E8F9 0%, var(--light-blue) 100%);
        }
        
        .service-card-new:nth-child(3) .image-container-service {
            background: linear-gradient(135deg, var(--yellow) 0%, #ffe082 100%);
        }
        
        .service-card-new img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s cubic-bezier(0.165, 0.84, 0.44, 1);
        }
        
        .service-card-new:hover img {
            transform: scale(1.1);
        }
        
        .icon-badge-service {
            position: absolute;
            top: 20px;
            right: 20px;
            background: white;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.2);
            z-index: 2;
            transition: all 0.3s ease;
        }
        
        .service-card-new:hover .icon-badge-service {
            transform: rotate(360deg) scale(1.1);
        }
        
        .service-card-new:nth-child(1) .icon-badge-service {
            color: var(--dark-blue);
        }
        
        .service-card-new:nth-child(2) .icon-badge-service {
            color: var(--light-blue);
        }
        
        .service-card-new:nth-child(3) .icon-badge-service {
            color: var(--yellow);
        }
        
        .service-card-body-new {
            padding: 2.5rem;
            position: relative;
        }
        
        .service-number {
            position: absolute;
            top: -30px;
            left: 30px;
            background: var(--yellow);
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark-blue);
            box-shadow: 0 5px 20px rgba(255, 243, 91, 0.4);
        }
        
        .service-title-new {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: var(--dark-blue);
            margin-top: 1.5rem;
        }
        
        .service-description-new {
            color: #6c757d;
            line-height: 1.8;
            font-size: 1rem;
            font-weight: 300;
        }
        
        .decorative-shape {
            position: absolute;
            z-index: 0;
            opacity: 0.05;
        }
        
        .shape-1 {
            top: 10%;
            left: 5%;
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%);
            border-radius: 30% 70% 70% 30% / 30% 30% 70% 70%;
            animation: float 6s ease-in-out infinite;
        }
        
        .shape-2 {
            bottom: 20%;
            right: 5%;
            width: 150px;
            height: 150px;
            background: linear-gradient(135deg, var(--light-blue) 0%, var(--yellow) 100%);
            border-radius: 60% 40% 30% 70% / 60% 30% 70% 40%;
            animation: float 8s ease-in-out infinite;
        }
        
        .fade-in-card {
            animation: fadeInUp 0.8s ease-out both;
        }
        
        .fade-in-card:nth-child(1) {
            animation-delay: 0.1s;
        }
        
        .fade-in-card:nth-child(2) {
            animation-delay: 0.3s;
        }
        
        .fade-in-card:nth-child(3) {
            animation-delay: 0.5s;
        }
        
        .service-card {
            background: linear-gradient(135deg, var(--light-blue) 0%, white 100%);
            border: none;
            border-radius: 20px;
            padding: 2rem;
            transition: all 0.3s;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            height: 100%;
        }
        
        .service-card:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 30px rgba(0,83,122,0.15);
        }
        
        .service-icon {
            font-size: 3.5rem;
            color: var(--dark-blue);
            margin-bottom: 1.5rem;
        }
        
        /* Pricing Section */
        .pricing {
            padding: 5rem 0;
            background: linear-gradient(180deg, var(--light-blue) 0%, white 100%);
        }
        
        .price-card {
            background: white;
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s;
            height: 100%;
        }
        
        .price-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0,83,122,0.2);
        }
        
        .price-card.featured {
            background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%);
            color: white;
            transform: scale(1.05);
        }
        
        .price-card.featured .price {
            color: var(--yellow);
        }
        
        .price {
            font-size: 3rem;
            font-weight: bold;
            color: var(--dark-blue);
            margin: 1rem 0;
        }
        
        
        /* FAQ Section - NEW DESIGN */
        .faq {
            padding: 5rem 0;
            background: white;
        }

        @keyframes sparkle {
            0%, 100% { transform: scale(1) rotate(0deg); }
            50% { transform: scale(1.2) rotate(180deg); }
        }
        
        .accordion-button {
            background-color: var(--light-blue);
            color: var(--dark-blue);
            font-weight: 600;
            transition: all 0.3s ease;
            padding: 20px 25px;
            font-size: 1.1rem;
            border-radius: 15px;
        }
        
        .accordion-button:hover {
            background-color: #90dff5;
            transform: translateX(5px);
        }
        
        .accordion-button:not(.collapsed) {
            background-color: var(--dark-blue);
            color: white;
        }
        
        .accordion-button:focus {
            box-shadow: 0 0 0 0.25rem rgba(0,83,122,0.25);
        }

        .accordion-item {
            border: none;
            border-radius: 15px;
            margin-bottom: 15px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.08);
            transition: all 0.3s;
        }
        
        .accordion-item:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.15) !important;
            transform: translateY(-3px);
        }

        .section-title {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--dark-blue);
            margin-bottom: 1rem;
            position: relative;
            display: inline-block;
        }
        
        /* Contact Section - NEW DESIGN */
        .contact {
            padding: 5rem 0;
            background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%);
            color: white;
        }

        .contact-info-card {
            transition: all 0.3s;
        }
        
        .contact-info-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.15) !important;
        }
        
        .contact-info-card a:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 18px rgba(0,0,0,0.25) !important;
        }

        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }s
        
        /* Footer */
        .footer {
            background: #003d57;
            color: white;
            padding: 2rem 0;
        }
        
        .footer a {
            color: var(--light-blue);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .footer a:hover {
            color: var(--yellow);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.5rem;
            }
            
            .hero .tagline {
                font-size: 1.5rem;
            }
            
            .hero .sub-tagline {
                font-size: 1rem;
            }
            
            .section-title {
                font-size: 2rem;
            }
            
            .main-heading-about {
                font-size: 2rem;
            }
            
            .image-grid-about {
                max-width: 100%;
            }
            
            .book-now-card-about {
                position: static;
                margin-top: 30px;
            }
            
            .feature-item-about:hover {
                transform: translateX(0);
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark sticky-top">
        <div class="container">
            <a class="navbar-brand" href="#"><i class="fas fa-tshirt me-2"></i>MangTV Laundry Shop</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item"><a class="nav-link" href="#home">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="#about">About Us</a></li>
                    <li class="nav-item"><a class="nav-link" href="#services">Services</a></li>
                    <li class="nav-item"><a class="nav-link" href="#pricing">Price</a></li>
                    <li class="nav-item"><a class="nav-link" href="#faq">FAQ</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Contact Us</a></li>
                    <li class="nav-item"><a class="btn btn-register ms-2" href="javascript:void(0)" onclick="scrollToAuthButtons()">Book Now</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-7 hero-content">
                    <h1>MangTV Laundry Shop</h1>
                    <p class="tagline">Fresh Clothes, Hassle-Free Life</p>
                    <p class="sub-tagline">Book your laundry pickup & delivery anytime, anywhere.</p>
                    <div class="hero-buttons">
                        <a href="register.php" class="btn btn-primary-custom">Register Now</a>
                        <a href="login.php" class="btn btn-outline-custom">Login</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section class="about" id="about">
        <div class="container">
            <div class="row align-items-center">
                <!-- Left Side - Images -->
                <div class="col-lg-6 mb-5 mb-lg-0">
                    <div class="image-grid-about position-relative">
                        <i class="fas fa-sparkles sparkle-icon-about"></i>
                        
                        <div class="image-box-about">
                            <img src="https://images.unsplash.com/photo-1582735689369-4fe89db7114c?w=500&h=500&fit=crop" alt="Laundry heart hands">
                        </div>
                        
                        <div class="image-box-about large">
                            <img src="https://images.unsplash.com/photo-1517677208171-0bc6725a3e60?w=500&h=500&fit=crop" alt="Woman with laundry">
                        </div>
                        
                        <div class="image-box-about">
                            <img src="https://images.unsplash.com/photo-1610557892470-55d9e80c0bce?w=500&h=500&fit=crop" alt="Washing machine">
                        </div>
                        
                       <div class="book-now-card-about" onclick="scrollToAuthButtons()">
                            <i class="fas fa-calendar-check"></i>
                            <h5>BOOK NOW</h5>
                            <p>Enjoy a whole new level of<br>convenience!</p>
                        </div>
                    </div>
                </div>
                
                <!-- Right Side - Content -->
                <div class="col-lg-6">
                    <div class="content-section-about">
                        <span class="badge-custom-about">ABOUT US</span>
                        
                        <h2 class="main-heading-about">Your Trusted Partner in Laundry Care</h2>
                        
                        <p class="description-text-about">
                            We are dedicated professionals committed to delivering exceptional laundry and dry cleaning services that exceed your expectations.
                        </p>
                        
                        <!-- Features -->
                        <div class="features-list-about">
                            <div class="feature-item-about">
                                <div class="feature-icon-about">
                                    <i class="fas fa-soap"></i>
                                </div>
                                <div class="feature-content-about">
                                    <h4>Personalized Experience</h4>
                                    <p>Your clothes deserve the best care. We meticulously sort your garments—separating whites, colors, and darks—to preserve their vibrancy and longevity. Using premium, gentle detergents, we ensure effective cleaning without compromising fabric quality.</p>
                                </div>
                            </div>
                            
                            <div class="feature-item-about">
                                <div class="feature-icon-about">
                                    <i class="fas fa-wind"></i>
                                </div>
                                <div class="feature-content-about">
                                    <h4>Quality You Can Trust</h4>
                                    <p>Our professional drying process ensures your garments are thoroughly dried at optimal temperatures, preserving fabric quality and preventing shrinkage. Every item is treated with the attention and care it deserves.</p>
                                </div>
                            </div>
                            
                            <div class="feature-item-about">
                                <div class="feature-icon-about">
                                    <i class="fas fa-tshirt"></i>
                                </div>
                                <div class="feature-content-about">
                                    <h4>Convenience at Your Fingertips</h4>
                                    <p>Laundry day has never been easier. Every item is carefully folded and organized, ready to be stored in your closet. Simply book through our online platform, and we'll take care of everything seamlessly.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section class="services" id="services">
        <div class="container position-relative">
            <div class="text-center mb-5">
                <div class="services-badge">OUR SERVICES</div>
                <h2 class="services-title">We Know How To Make<br>Laundry A Breeze</h2>
                <p class="services-subtitle">Experience premium laundry care with our professional wash, dry, and fold services</p>
            </div>
            
            <div class="row g-4">
                <!-- Wash Service -->
                <div class="col-md-6 col-lg-4 fade-in-card">
                    <div class="card service-card-new">
                        <div class="image-container-service">
                            <img src="https://www.thespruce.com/thmb/YWnwT2R569SS93oHP7apKFIE-7Y=/750x0/filters:no_upscale():max_bytes(150000):strip_icc():format(webp)/how-to-keep-white-clothes-white-2146392-05-a08ecae293e044e4af37e1dec899203e.jpg" alt="Wash Service" class="card-img-top">
                            <div class="icon-badge-service">
                                <i class="fas fa-sync-alt"></i>
                            </div>
                        </div>
                        <div class="service-card-body-new">
                            <div class="service-number">01</div>
                            <h3 class="service-title-new">Wash</h3>
                            <p class="service-description-new">
                                Our premium washing service uses state-of-the-art machines and eco-friendly detergents to thoroughly clean your clothes. We treat each garment with care, ensuring optimal cleanliness while maintaining fabric quality.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Dry Service -->
                <div class="col-md-6 col-lg-4 fade-in-card">
                    <div class="card service-card-new">
                        <div class="image-container-service">
                            <img src="https://images.unsplash.com/photo-1582735689369-4fe89db7114c?w=500&h=500&fit=crop" alt="Dry Service" class="card-img-top">
                            <div class="icon-badge-service">
                                <i class="fas fa-wind"></i>
                            </div>
                        </div>
                        <div class="service-card-body-new">
                            <div class="service-number">02</div>
                            <h3 class="service-title-new">Dry</h3>
                            <p class="service-description-new">
                                Our professional drying service ensures your clothes are perfectly dried at the right temperature. We carefully monitor each cycle to prevent shrinkage and maintain the integrity of your favorite garments.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Fold Service -->
                <div class="col-md-6 col-lg-4 fade-in-card">
                    <div class="card service-card-new">
                        <div class="image-container-service">
                            <img src="https://images.unsplash.com/photo-1517677208171-0bc6725a3e60?w=800&h=600&fit=crop" alt="Fold Service" class="card-img-top">
                            <div class="icon-badge-service">
                                <i class="fas fa-layer-group"></i>
                            </div>
                        </div>
                        <div class="service-card-body-new">
                            <div class="service-number">03</div>
                            <h3 class="service-title-new">Fold</h3>
                            <p class="service-description-new">
                                Our expert folding service delivers your clothes neatly organized and ready to put away. Each item is meticulously folded and sorted, making your laundry day stress-free and your closet perfectly organized.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <!-- Pricing Section -->
<section class="pricing" id="pricing">
    <div class="container">
        <!-- Title Section -->
        <div class="row align-items-center mb-5">
            <div class="col-lg-6">
                <div class="badge-custom-about">PRICING LIST</div>
                <h2 class="section-title" style="display: block;">Check Out Our Reasonable Prices</h2>
            </div>
            <div class="col-lg-6">
                <p class="intro-text" style="color: #6c757d; font-size: 1rem; line-height: 1.7;">
                    At MangTV Laundry Shop, we believe that high-quality laundry services should be accessible to everyone. Our reasonable pricing structure ensures you get the best value for your money. Experience exceptional laundry care without breaking the bank!
                </p>
                <p class="intro-text" style="color: #6c757d; font-size: 1rem; line-height: 1.7;">
                    Browse our pricing for machine washing, dry cleaning, and special items. Fresh, clean clothes are just a visit away!
                </p>
            </div>
        </div>

        <!-- Accordion Section -->
        <div class="accordion" id="pricingAccordion">
            <!-- Basic Services -->
            <div class="accordion-item" style="border: none; border-radius: 16px; margin-bottom: 20px; box-shadow: 0 6px 20px rgba(0,0,0,0.08); overflow: hidden; background: #fff;">
                <h2 class="accordion-header">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#basicServices" aria-expanded="true" style="background: #fff; border: none; padding: 20px 25px; font-size: 1.2rem; font-weight: 700; color: var(--dark-blue);">
                        <i class="fas fa-tshirt me-3"></i> BASIC SERVICES
                    </button>
                </h2>
                <div id="basicServices" class="accordion-collapse collapse show" data-bs-parent="#pricingAccordion">
                    <div class="accordion-body" style="padding: 0; background: #fff;">
                        <div class="service-item" style="padding: 20px 25px; border-bottom: 1px solid #f1f1f1; display: flex; justify-content: space-between; align-items: center;">
                            <div class="service-details">
                                <h5 style="color: var(--dark-blue); font-weight: 700; margin-bottom: 6px; font-size: 1.15rem;">Full Service</h5>
                                <p style="color: #666; font-size: 0.9rem; margin-bottom: 8px;">Wash, dry, and fold with premium detergent & fabric conditioner.</p>
                                <ul style="list-style: none; padding: 0; margin: 0;">
                                    <li style="color: #666; font-size: 0.85rem; margin-bottom: 4px;"><i class="fas fa-check text-success me-2"></i>7kg per load capacity</li>
                                    <li style="color: #666; font-size: 0.85rem; margin-bottom: 4px;"><i class="fas fa-check text-success me-2"></i>Professional washing & drying</li>
                                    <li style="color: #666; font-size: 0.85rem; margin-bottom: 4px;"><i class="fas fa-check text-success me-2"></i>Neat folding service</li>
                                </ul>
                            </div>
                            <div style="color: #28a745; font-size: 1.5rem; font-weight: bold; background: #eaf8ed; padding: 8px 14px; border-radius: 10px; min-width: 90px; text-align: center;">₱200</div>
                        </div>

                        <div class="service-item" style="padding: 20px 25px; border-bottom: 1px solid #f1f1f1; display: flex; justify-content: space-between; align-items: center;">
                            <div class="service-details">
                                <h5 style="color: var(--dark-blue); font-weight: 700; margin-bottom: 6px; font-size: 1.15rem;">Self Service - Wash Only</h5>
                                <p style="color: #666; font-size: 0.9rem; margin-bottom: 8px;">Bring your own detergent with flexible timing.</p>
                                <ul style="list-style: none; padding: 0; margin: 0;">
                                    <li style="color: #666; font-size: 0.85rem; margin-bottom: 4px;"><i class="fas fa-check text-success me-2"></i>Budget-friendly</li>
                                    <li style="color: #666; font-size: 0.85rem; margin-bottom: 4px;"><i class="fas fa-check text-success me-2"></i>You control the process</li>
                                </ul>
                            </div>
                            <div style="color: #28a745; font-size: 1.5rem; font-weight: bold; background: #eaf8ed; padding: 8px 14px; border-radius: 10px; min-width: 90px; text-align: center;">₱80</div>
                        </div>

                        <div class="service-item" style="padding: 20px 25px; display: flex; justify-content: space-between; align-items: center;">
                            <div class="service-details">
                                <h5 style="color: var(--dark-blue); font-weight: 700; margin-bottom: 6px; font-size: 1.15rem;">Self Service - Dry Only</h5>
                                <p style="color: #666; font-size: 0.9rem; margin-bottom: 8px;">For your pre-washed clothes.</p>
                                <ul style="list-style: none; padding: 0; margin: 0;">
                                    <li style="color: #666; font-size: 0.85rem; margin-bottom: 4px;"><i class="fas fa-check text-success me-2"></i>Efficient drying</li>
                                    <li style="color: #666; font-size: 0.85rem; margin-bottom: 4px;"><i class="fas fa-check text-success me-2"></i>Perfect temperature control</li>
                                </ul>
                            </div>
                            <div style="color: #28a745; font-size: 1.5rem; font-weight: bold; background: #eaf8ed; padding: 8px 14px; border-radius: 10px; min-width: 90px; text-align: center;">₱70</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Special Items -->
            <div class="accordion-item" style="border: none; border-radius: 16px; margin-bottom: 20px; box-shadow: 0 6px 20px rgba(0,0,0,0.08); overflow: hidden; background: #fff;">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#specialItems" style="background: #fff; border: none; padding: 20px 25px; font-size: 1.2rem; font-weight: 700; color: var(--dark-blue);">
                        <i class="fas fa-bed me-3"></i> SPECIAL ITEMS
                    </button>
                </h2>
                <div id="specialItems" class="accordion-collapse collapse" data-bs-parent="#pricingAccordion">
                    <div class="accordion-body" style="padding: 0; background: #fff;">
                        <div class="service-item" style="padding: 20px 25px; border-bottom: 1px solid #f1f1f1; display: flex; justify-content: space-between; align-items: center;">
                            <div class="service-details">
                                <h5 style="color: var(--dark-blue); font-weight: 700; margin-bottom: 6px; font-size: 1.15rem;">Blanket/Bedsheet</h5>
                                <p style="color: #666; font-size: 0.9rem; margin-bottom: 8px;">Heavy-duty cleaning for thick blankets & bedsheets.</p>
                                <ul style="list-style: none; padding: 0; margin: 0;">
                                    <li style="color: #666; font-size: 0.85rem; margin-bottom: 4px;"><i class="fas fa-check text-success me-2"></i>Deep sanitization</li>
                                    <li style="color: #666; font-size: 0.85rem; margin-bottom: 4px;"><i class="fas fa-check text-success me-2"></i>3kg per load</li>
                                    <li style="color: #666; font-size: 0.85rem; margin-bottom: 4px;"><i class="fas fa-check text-success me-2"></i>Fresh and clean results</li>
                                </ul>
                            </div>
                            <div style="color: #28a745; font-size: 1.5rem; font-weight: bold; background: #eaf8ed; padding: 8px 14px; border-radius: 10px; min-width: 90px; text-align: center;">₱200</div>
                        </div>

                        <div class="service-item" style="padding: 20px 25px; display: flex; justify-content: space-between; align-items: center;">
                            <div class="service-details">
                                <h5 style="color: var(--dark-blue); font-weight: 700; margin-bottom: 6px; font-size: 1.15rem;">Comforter</h5>
                                <p style="color: #666; font-size: 0.9rem; margin-bottom: 8px;">Gentle care to maintain fluffiness.</p>
                                <ul style="list-style: none; padding: 0; margin: 0;">
                                    <li style="color: #666; font-size: 0.85rem; margin-bottom: 4px;"><i class="fas fa-check text-success me-2"></i>Handled with care</li>
                                    <li style="color: #666; font-size: 0.85rem; margin-bottom: 4px;"><i class="fas fa-check text-success me-2"></i>1 piece per load</li>
                                    <li style="color: #666; font-size: 0.85rem; margin-bottom: 4px;"><i class="fas fa-check text-success me-2"></i>Keeps original texture</li>
                                </ul>
                            </div>
                            <div style="color: #28a745; font-size: 1.5rem; font-weight: bold; background: #eaf8ed; padding: 8px 14px; border-radius: 10px; min-width: 90px; text-align: center;">₱200</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add-On Services -->
            <div class="accordion-item" style="border: none; border-radius: 16px; margin-bottom: 20px; box-shadow: 0 6px 20px rgba(0,0,0,0.08); overflow: hidden; background: #fff;">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#addOns" style="background: #fff; border: none; padding: 20px 25px; font-size: 1.2rem; font-weight: 700; color: var(--dark-blue);">
                        <i class="fas fa-plus-circle me-3"></i> ADD-ON SERVICES
                    </button>
                </h2>
                <div id="addOns" class="accordion-collapse collapse" data-bs-parent="#pricingAccordion">
                    <div class="accordion-body" style="padding: 0; background: #fff;">
                        <div class="service-item" style="padding: 20px 25px; display: flex; justify-content: space-between; align-items: center;">
                            <div class="service-details">
                                <h5 style="color: var(--dark-blue); font-weight: 700; margin-bottom: 6px; font-size: 1.15rem;">Extra Dry</h5>
                                <p style="color: #666; font-size: 0.9rem; margin-bottom: 8px;">Extended drying for thick fabrics.</p>
                                <ul style="list-style: none; padding: 0; margin: 0;">
                                    <li style="color: #666; font-size: 0.85rem; margin-bottom: 4px;"><i class="fas fa-check text-success me-2"></i>Ensures complete dryness</li>
                                    <li style="color: #666; font-size: 0.85rem; margin-bottom: 4px;"><i class="fas fa-check text-success me-2"></i>Perfect for bulky clothes</li>
                                </ul>
                            </div>
                            <div style="color: #28a745; font-size: 1.5rem; font-weight: bold; background: #eaf8ed; padding: 8px 14px; border-radius: 10px; min-width: 90px; text-align: center;">₱15</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

    <!-- FAQ Section -->
  <!-- FAQ Section - NEW DESIGN -->
    <section class="faq" id="faq">
        <div class="container">
            <div class="text-center mb-5">
                <div class="mb-3">
                    <i class="fas fa-soap" style="color: var(--yellow); font-size: 2rem; margin: 0 15px; animation: sparkle 1.5s ease-in-out infinite;"></i>
                    <i class="fas fa-star" style="color: var(--yellow); font-size: 2rem; margin: 0 15px; animation: sparkle 1.5s ease-in-out infinite;"></i>
                </div>
                <h2 class="section-title" style="display: block;">Your Laundry Queries, Answered!</h2>
                <p style="color: #6c757d; font-size: 1.1rem;">Everything you need to know about MangTV Laundry Shop</p>
            </div>
            
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="accordion" id="faqAccordion">
                        <!-- Question 1 -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    What are your operating hours?
                                </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    We're open from <strong>7:00 AM to 6:00 PM daily</strong>. Drop off your laundry in the morning, and we'll have it ready by the afternoon! Our same-day service ensures you get your clothes back fresh and clean on the same day.
                                </div>
                            </div>
                        </div>
                        
                        <!-- Question 2 -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    Do you offer same-day service?
                                </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    <strong>Yes, absolutely!</strong> Clothes dropped off in the morning are typically ready by afternoon. We have <strong>6 washing machines</strong> running efficiently to handle multiple loads, ensuring fast turnaround times without compromising quality.
                                </div>
                            </div>
                        </div>
                        
                        <!-- Question 3 -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                    What detergents do you use?
                                </button>
                            </h2>
                            <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    We use <strong>quality detergent and fabric conditioner</strong> included in our full service package. For extra dirty or heavily soiled clothes, we add additional soap at no extra charge to ensure your items are thoroughly cleaned and smell amazing!
                                </div>
                            </div>
                        </div>
                        
                        <!-- Question 4 -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                    Do you offer pick-up and delivery services?
                                </button>
                            </h2>
                            <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    <strong>Yes, we do!</strong> We offer <strong>FREE delivery within Bucana</strong>, especially for students. For other areas in Nasugbu, delivery is available for just <strong>₱35</strong>. Simply call us or book to schedule a pick-up!
                                </div>
                            </div>
                        </div>
                        
                        <!-- Question 5 -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                                    Can you handle delicate and special care items?
                                </button>
                            </h2>
                            <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    <strong>Absolutely!</strong> We carefully handle all laundry types including blankets, bedsheets, and comforters. Our experienced staff of 3 trained employees ensures each item receives appropriate care and attention. We meticulously categorize laundry pieces to prevent mix-ups.
                                </div>
                            </div>
                        </div>
                        
                        <!-- Question 6 -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq6">
                                    How do I pay for your services?
                                </button>
                            </h2>
                            <div id="faq6" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Payment is made when you <strong>pick up your clean laundry</strong> or upon delivery. We accept <strong>cash payments</strong> and keep detailed records of all transactions. <strong>Students</strong> automatically receive special discounted rates!
                                </div>
                            </div>
                        </div>
                        
                        <!-- Question 7 -->
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq7">
                                    How does online booking work?
                                </button>
                            </h2>
                            <div id="faq7" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    You can contact us via <strong>Facebook (@idolsperfume)</strong> or call us directly at <strong>0905-779-8485 / 0907-815-4479</strong>. We collect your Facebook account details for easy status updates on your laundry. Just let us know your preferred drop-off and pick-up times, and we'll take care of the rest!
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-center mt-5">
                        <p class="text-muted mb-2">Still have questions?</p>
                        <a href="https://www.facebook.com/idolsperfume" target="_blank" class="btn btn-register" style="display: inline-block; padding: 0.8rem 2rem; border-radius: 30px;">
                            <i class="fab fa-facebook-messenger me-2"></i>Chat with Us
                        </a>
                        <p class="text-muted mt-3 small">
                            <i class="fas fa-map-marker-alt me-2"></i>R. Martinez St. Brgy. Bucana, Nasugbu, Philippines
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

<!-- Contact Section - INTERACTIVE DESIGN -->
<section class="contact" id="contact">
    <div class="container" style="max-width: 1200px;">
        <div class="text-center mb-5">
            <div class="mb-3">
                <i class="fas fa-headset" style="color: var(--yellow); font-size: 2.5rem; animation: bounce 2s ease-in-out infinite;"></i>
            </div>
            <h2 class="section-title text-white">Get In Touch</h2>
            <p style="color: rgba(255,255,255,0.9); font-size: 1rem; max-width: 600px; margin: 0 auto;">Have questions? We're here to help! Reach out to us anytime.</p>
        </div>
        
        <!-- Contact Information -->
        <div class="row justify-content-center">
            <div class="col-lg-11">
                <div style="background: white; border-radius: 20px; padding: 45px 40px; box-shadow: 0 15px 50px rgba(0,0,0,0.3);">
                    
                    <div class="row g-3">
                        <!-- Location Card - CLICKABLE -->
                        <div class="col-md-4">
                            <a href="https://www.google.com/maps/search/?api=1&query=R.+Martinez+St.+Brgy.+Bucana+Nasugbu+Philippines" target="_blank" style="text-decoration: none; display: block; height: 100%;">
                                <div class="contact-info-card" style="background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%); border-radius: 15px; padding: 30px 20px; text-align: center; height: 100%; box-shadow: 0 4px 12px rgba(0,83,122,0.2); cursor: pointer; transition: all 0.3s ease;">
                                    <div style="background: white; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 18px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); transition: all 0.3s ease;">
                                        <i class="fas fa-map-marker-alt" style="color: var(--dark-blue); font-size: 24px;"></i>
                                    </div>
                                    <h5 style="color: white; font-weight: 700; margin-bottom: 12px; font-size: 1.15rem;">Our Location</h5>
                                    <p style="color: rgba(255,255,255,0.95); margin: 0; font-weight: 500; line-height: 1.6; font-size: 0.9rem;">R. Martinez St. Brgy. Bucana, Nasugbu, Philippines</p>
                                    <p style="color: var(--yellow); margin-top: 10px; font-size: 0.85rem; font-weight: 600;">
                                        <i class="fas fa-external-link-alt me-1"></i>View on Maps
                                    </p>
                                </div>
                            </a>
                        </div>
                        
                        <!-- Phone Card - CLICKABLE -->
                        <div class="col-md-4">
                            <div class="contact-info-card" style="background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%); border-radius: 15px; padding: 30px 20px; text-align: center; height: 100%; box-shadow: 0 4px 12px rgba(0,83,122,0.2);">
                                <div style="background: white; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 18px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
                                    <i class="fas fa-phone-alt" style="color: var(--dark-blue); font-size: 24px;"></i>
                                </div>
                                <h5 style="color: white; font-weight: 700; margin-bottom: 12px; font-size: 1.15rem;">Call Us</h5>
                                <a href="tel:+639057798485" style="color: rgba(255,255,255,0.95); text-decoration: none; font-weight: 600; line-height: 1.7; font-size: 0.95rem; display: block; transition: all 0.3s ease; padding: 5px; border-radius: 5px;">
                                    <i class="fas fa-phone me-2"></i>0905 779 8485
                                </a>
                                <a href="tel:+639078154479" style="color: rgba(255,255,255,0.95); text-decoration: none; font-weight: 600; line-height: 1.7; font-size: 0.95rem; display: block; transition: all 0.3s ease; padding: 5px; border-radius: 5px;">
                                    <i class="fas fa-phone me-2"></i>0907 815 4479
                                </a>
                                <p style="color: var(--yellow); margin-top: 10px; font-size: 0.85rem; font-weight: 600;">
                                    <i class="fas fa-hand-pointer me-1"></i>Click to call
                                </p>
                            </div>
                        </div>
                        
                        <!-- Email Card - CLICKABLE -->
                        <div class="col-md-4">
                            <a href="mailto:adralesidol@gmail.com?subject=Laundry%20Service%20Inquiry&body=Hello%20MangTV%20Laundry%20Shop,%0D%0A%0D%0AI%20would%20like%20to%20inquire%20about..." style="text-decoration: none; display: block; height: 100%;">
                                <div class="contact-info-card" style="background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%); border-radius: 15px; padding: 30px 20px; text-align: center; height: 100%; box-shadow: 0 4px 12px rgba(0,83,122,0.2); cursor: pointer; transition: all 0.3s ease;">
                                    <div style="background: white; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 18px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); transition: all 0.3s ease;">
                                        <i class="fas fa-envelope" style="color: var(--dark-blue); font-size: 24px;"></i>
                                    </div>
                                    <h5 style="color: white; font-weight: 700; margin-bottom: 12px; font-size: 1.15rem;">Email Us</h5>
                                    <p style="color: rgba(255,255,255,0.95); margin: 0; font-weight: 500; line-height: 1.6; font-size: 0.9rem; word-break: break-word;">adralesidol@gmail.com</p>
                                    <p style="color: var(--yellow); margin-top: 10px; font-size: 0.85rem; font-weight: 600;">
                                        <i class="fas fa-paper-plane me-1"></i>Send Email
                                    </p>
                                </div>
                            </a>
                        </div>

                        <!-- Facebook Card -->
                        <div class="col-md-6">
                            <div style="background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%); border-radius: 15px; padding: 30px 25px; text-align: center; height: 100%; box-shadow: 0 5px 15px rgba(0,83,122,0.25);">
                                <div style="background: white; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 18px; box-shadow: 0 5px 15px rgba(0,0,0,0.2);">
                                    <i class="fab fa-facebook-f" style="color: var(--dark-blue); font-size: 26px;"></i>
                                </div>
                                <h5 style="color: white; font-weight: 700; margin-bottom: 12px; font-size: 1.15rem;">Connect on Facebook</h5>
                                <p style="color: rgba(255,255,255,0.9); margin-bottom: 20px; font-size: 0.9rem; line-height: 1.5;">Follow us for updates and promos!</p>
                                <a href="https://www.facebook.com/idolsperfume" target="_blank" style="background: var(--yellow); color: var(--dark-blue); padding: 10px 28px; border-radius: 50px; text-decoration: none; font-weight: 700; display: inline-block; transition: all 0.3s; box-shadow: 0 4px 12px rgba(255,243,91,0.3); font-size: 0.95rem;">
                                    <i class="fab fa-facebook-messenger me-2"></i>Visit Page
                                </a>
                            </div>
                        </div>
                        
                        <!-- Delivery Card -->
                        <div class="col-md-6">
                            <div style="background: linear-gradient(135deg, var(--dark-blue) 0%, #006b99 100%); border-radius: 15px; padding: 30px 25px; text-align: center; height: 100%; box-shadow: 0 5px 15px rgba(0,83,122,0.25);">
                                <div style="background: white; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 18px; box-shadow: 0 5px 15px rgba(0,0,0,0.2);">
                                    <i class="fas fa-truck" style="color: var(--dark-blue); font-size: 26px;"></i>
                                </div>
                                <h5 style="color: white; font-weight: 700; margin-bottom: 12px; font-size: 1.15rem;">Delivery Service</h5>
                                <p style="color: rgba(255,255,255,0.9); margin-bottom: 15px; font-size: 0.9rem; line-height: 1.5;">We deliver to your doorstep!</p>
                                <div style="background: rgba(168,232,249,0.15); border-radius: 10px; padding: 12px;">
                                    <p style="color: white; margin: 0; font-weight: 600; font-size: 0.85rem;"><i class="fas fa-check-circle me-2"></i>Discount for students</p>
                                    <p style="color: white; margin: 6px 0 0 0; font-weight: 600; font-size: 0.85rem;"><i class="fas fa-check-circle me-2"></i>₱35 other areas</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-4 pt-3" style="border-top: 2px dashed #e0e0e0;">
                        <p style="color: #666; font-size: 0.95rem; margin-bottom: 15px; font-weight: 500;">
                            <i class="fas fa-clock me-2" style="color: var(--dark-blue);"></i>
                            Open Daily: <strong style="color: var(--dark-blue);">7:00 AM - 6:00 PM</strong>
                        </p>
                        <a href="https://www.facebook.com/idolsperfume" target="_blank" class="btn btn-register" style="display: inline-block; padding: 12px 40px; border-radius: 50px; font-size: 1.05rem; box-shadow: 0 6px 20px rgba(255,243,91,0.3);">
                            <i class="fab fa-facebook-messenger me-2"></i>Message Us Now
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>


    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    <p class="mb-0">&copy; 2025 MangTV Laundry Shop. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <a href="https://www.facebook.com/idolsperfume" target="_blank" class="me-3"><i class="fab fa-facebook fa-lg"></i></a>
                    <a href="#" class="me-3"><i class="fab fa-instagram fa-lg"></i></a>
                    <a href="#" ><i class="fab fa-twitter fa-lg"></i></a>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to scroll to and highlight auth buttons - KEEP THIS!
        function scrollToAuthButtons() {
            const heroSection = document.querySelector('.hero');
            const authButtons = document.querySelectorAll('.hero-buttons .btn');
            
            // Smooth scroll to hero section
            heroSection.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
            
            // Add highlight animation after scroll
            setTimeout(() => {
                authButtons.forEach(btn => {
                    btn.classList.add('highlight-pulse');
                });
                
                // Remove animation class after it completes
                setTimeout(() => {
                    authButtons.forEach(btn => {
                        btn.classList.remove('highlight-pulse');
                    });
                }, 4500);
            }, 800);
        }

        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add active class to navigation links on scroll
        let ticking = false;
        
        function updateActiveNavLink() {
            const sections = document.querySelectorAll('section[id]');
            const navLinks = document.querySelectorAll('.nav-link[href^="#"]');
            
            let currentSection = '';
            const scrollPosition = window.scrollY + 150;
            
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionHeight = section.offsetHeight;
                
                if (scrollPosition >= sectionTop && scrollPosition < sectionTop + sectionHeight) {
                    currentSection = section.getAttribute('id');
                }
            });
            
            navLinks.forEach(link => {
                link.classList.remove('active');
                
                if (link.getAttribute('href') === `#${currentSection}`) {
                    link.classList.add('active');
                }
            });
            
            ticking = false;
        }
        
        window.addEventListener('scroll', () => {
            if (!ticking) {
                window.requestAnimationFrame(() => {
                    updateActiveNavLink();
                    ticking = false;
                });
                ticking = true;
            }
        });
        
        updateActiveNavLink();
    </script>
</body>
</html>