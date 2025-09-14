<?php 
session_start(); 
include 'db.php';  

// Initialize cart count if not set
if (!isset($_SESSION['cart_count'])) {
    $_SESSION['cart_count'] = 0;
}

// Fetch products from the database with category and stock information
$products = $con->query("SELECT p.*, c.name as category_name 
                        FROM products p 
                        LEFT JOIN categories c ON p.category_id = c.category_id
                        WHERE p.stock > 0"); 
?> 

<!DOCTYPE html> 
<html lang="en"> 
<head>     
    <meta charset="UTF-8">     
    <meta name="viewport" content="width=device-width, initial-scale=1.0">     
    <title>CraftyHands Workshops — Handmade Goods</title>     
    <link rel="stylesheet" href="Styles/index.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root{
            --brand:#2874f0;
            --ink:#1f2937;
            --muted:#6b7280;
            --bg:#f8fafc;
            --paper:#ffffff;
            --border:#e5e7eb;
            --dark:#0b1220;
        }
        body{font-family:"Poppins",sans-serif;color:var(--ink); background:#fff;}

        /* NOTE: No explicit header background here to allow your OLD banner styles from Styles/index.css */
        .header-content{max-width:1100px; margin:0 auto; padding:16px 20px; display:flex; align-items:center; justify-content:space-between; gap:16px;}
        .brand{display:flex; flex-direction:column; line-height:1.1;}
        .brand h1{margin:0; font-size:1.6rem;}
        .header-navigation ul{list-style:none; display:flex; gap:14px; padding:0; margin:0;}
        .header-navigation a{text-decoration:none; color:inherit;}
        .header-navigation a:hover{color:var(--brand);}
        .user-profile-link{color:inherit; text-decoration:none;}
        .user-logo{width:36px; height:36px; border-radius:50%; background:var(--brand); color:#fff; display:flex; align-items:center; justify-content:center; font-weight:700;}

        /* ABOUT (top) */
        .about{background:var(--bg); padding:32px 20px; border-top:1px solid var(--border); border-bottom:1px solid var(--border);}
        .about-inner{max-width:1100px; margin:0 auto;}
        .about h2{font-size:1.6rem; margin:0 0 8px;}
        .about .tagline{color:var(--muted); margin:0 0 16px;}
        .about-grid{display:grid; grid-template-columns:repeat(3,1fr); gap:16px;}
        .about-card{background:#fff; border:1px solid var(--border); border-radius:12px; padding:16px;}
        .about-card h3{font-size:1rem; margin:0 0 6px;}
        .about-card p{margin:0; color:var(--muted);}
        @media (max-width:900px){ .about-grid{grid-template-columns:1fr 1fr;} }
        @media (max-width:640px){ .about-grid{grid-template-columns:1fr;} }

        /* PRODUCTS */
        .container{max-width:1100px; margin:0 auto; padding:24px 20px;}
        .product-list{display:grid; grid-template-columns:repeat(3,1fr); gap:16px;}
        .product-item{background:#fff; border:1px solid var(--border); border-radius:16px; padding:14px;}
        .product-image{width:100%; height:240px; object-fit:cover; border-radius:12px; border:1px solid var(--border);}
        .product-description{ max-height: 55px; overflow: hidden; position: relative; transition: max-height 0.3s ease; }
        .product-description.expanded{ max-height: 200px; }
        .read-more{ color: var(--brand); cursor: pointer; display: inline-block; margin-left: 5px; }
        .error-image{ width: 100%; height: 200px; display: flex; justify-content: center; align-items: center; background-color: #f0f0f0; color: #888; }
        .stock-info{ margin: 10px 0; font-size: 0.9em; color: #666; }
        .out-of-stock{ color: #ff0000; font-weight: bold; }
        .low-stock{ color: #ffa500; }
        .add-to-cart-btn{background:var(--brand); color:#fff; border:none; border-radius:10px; padding:10px 12px; cursor:pointer;}
        .add-to-cart-btn:disabled{ background-color: #cccccc; cursor: not-allowed; }
        @media (max-width:900px){ .product-list{grid-template-columns:1fr 1fr;} }
        @media (max-width:640px){ .product-list{grid-template-columns:1fr;} }

        /* FAQ */
        .faq{max-width:1100px; margin:0 auto 20px; padding:0 20px;}
        .faq h3{margin:0 0 10px;}
        .accordion{border:1px solid var(--border); border-radius:12px; overflow:hidden; background:#fff;}
        .acc-item + .acc-item{border-top:1px solid var(--border);}
        .acc-btn{width:100%; text-align:left; background:#fff; border:0; padding:14px; font-weight:600; cursor:pointer;}
        .acc-panel{display:none; padding:0 14px 14px; color:var(--muted); background:#fff;}

        /* FOOTER (simple) */
        footer.site-footer{background:#0b1220; color:#e5e7eb; margin-top:32px;}
        .footer-wrap{max-width:1100px; margin:0 auto; padding:20px;}
        .footer-top{display:grid; gap:16px; grid-template-columns:2fr 1fr 1.2fr;}
        .footer-brand h4{color:#fff; margin:0 0 6px;}
        .footer-brand p{color:#cdd3dc; margin:0 0 10px; font-size:.95rem;}
        .footer-col h5{margin:0 0 8px; color:#fff; font-size:1rem;}
        .footer-col ul{list-style:none; padding:0; margin:0;}
        .footer-col li{margin:6px 0;}
        .footer-col a{color:#cdd3dc; text-decoration:none;}
        .footer-col a:hover{color:#fff; text-decoration:underline;}
        .footer-bottom{border-top:1px solid #1f2937; margin-top:14px; padding-top:10px; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px;}
    </style>
</head> 
<body> 
    <header>     
        <div class="header-content">         
            <div class="brand">
                <h1>CraftyHands Workshops</h1>
            </div>

            <?php if(isset($_SESSION['user_id'])): ?>             
                <a href="UserProfile.php" class="user-profile-link" title="Your profile">
                    <div class="user-profile">                 
                        <div class="user-profile-container" style="display:flex; align-items:center; gap:8px;">                     
                            <div class="user-logo">
                                <?php $username = $_SESSION['username'] ?? 'User'; echo strtoupper(substr($username, 0, 1)); ?>                     
                            </div>                     
                            <div class="user-name"><?php echo htmlspecialchars($username); ?></div>                 
                        </div>                 
                    </div>
                </a>
            <?php endif; ?>          

            <div class="header-navigation">             
                <nav>                 
                    <ul>
                        <?php if(isset($_SESSION['user_id'])): ?>
                            <li><a class="logoutbtn" href="logout.php">Logout</a></li>
                            <li>
                                <a href="Cart.php" class="cart-icon">
                                    My Cart
                                    <?php
                                    $cartCount = isset($_SESSION['cart_count']) ? $_SESSION['cart_count'] : 0;
                                    if($cartCount > 0): ?>
                                        <span class="cart-count"><?php echo (int)$cartCount; ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                            <li><a href="productCategory.php">Product Categories</a></li>
                            <li><a href="#about">About Us</a></li>
                            <li><a href="#faq">FAQ</a></li>
                        <?php else: ?>      
                            <li><a href="login.php">Login</a></li>                         
                            <li><a href="register.php">Register</a></li>   
                            <li><a href="productCategory.php">Product Categories</a></li>
                            <li><a href="#about">About Us</a></li>      
                            <li><a href="#faq">FAQ</a></li>           
                        <?php endif; ?>                 
                    </ul>             
                </nav>         
            </div>     
        </div> 
    </header>    

    <!-- ABOUT US (TOP) -->
    <section id="about" class="about">
        <div class="about-inner">
            <h2>About CraftyHands Workshops</h2>
            <p class="tagline">Handmade with heart — small-batch creations and friendly workshops.</p>

            <div class="about-grid">
                <div class="about-card">
                    <h3>Our Story</h3>
                    <p>We began as a small market stall and grew into a community hub connecting Australian makers with people who value quality and sustainability.</p>
                </div>
                <div class="about-card">
                    <h3>What We Do</h3>
                    <p>We curate handmade goods and host weekend workshops. Each session is led by a local artisan and includes all materials.</p>
                </div>
                <div class="about-card">
                    <h3>Our Promise</h3>
                    <p>Fair pricing for creators, low-waste packaging, and pieces built to last. Custom orders welcome.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- PRODUCTS -->
    <div class="container">         
        <h2>Our Products</h2>         
        <div class="product-list">             
            <?php 
            if ($products && $products->num_rows > 0):
                while($product = $products->fetch_assoc()): 
            ?>                 
                <div class="product-item">                     
                    <div class="product-image-container">                         
                        <?php                          
                        $image_path = $product['Image'];                         
                        if(!empty($image_path) && file_exists($image_path)):                         
                        ?>                             
                            <img src="<?php echo htmlspecialchars($image_path); ?>"                                 
                            alt="<?php echo htmlspecialchars($product['name']); ?>"                                 
                            class="product-image">                         
                        <?php else: ?>                             
                        <div class="error-image">No image available</div>                         
                        <?php endif; ?>                     
                    </div>                    

                    <h3><?php echo htmlspecialchars($product['name']); ?></h3>   
                                      
                    <p class="product-description"><?php echo htmlspecialchars($product['description']); ?></p>  
                    <span class="read-more">... More</span>

                    <?php if(!empty($product['category_name'])): ?>                         
                        <div class="product-category-tag">
                            <?php echo "Category : ", htmlspecialchars($product['category_name']); ?>                         
                        </div>                     
                    <?php endif; ?>

                    <div class="stock-info <?php echo ($product['stock'] <= 0 ? 'out-of-stock' : ($product['stock'] <= 5 ? 'low-stock' : '')); ?>">
                        <?php
                        if ($product['stock'] <= 0) {
                            echo 'Out of Stock';
                        } elseif ($product['stock'] <= 5) {
                            echo 'Low Stock: ' . (int)$product['stock'] . ' left';
                        } else {
                            echo 'Available: ' . (int)$product['stock'];
                        }
                        ?>
                    </div>                   
                    <div class="product-price">$ <?php echo number_format((float)$product['price'], 2); ?></div>

                    <form action="Cart.php" method="POST" class="cart-form">                         
                        <input type="hidden" name="product_id" value="<?php echo (int)$product['product_id']; ?>">
                        <input type="hidden" name="available_stock" value="<?php echo (int)$product['stock']; ?>">
                        <button type="submit" class="add-to-cart-btn" <?php echo ($product['stock'] <= 0 ? 'disabled' : ''); ?>>
                            <?php echo ($product['stock'] <= 0 ? 'Out of Stock' : 'Add to Cart'); ?>
                        </button>
                    </form>
                </div>             
            <?php 
                endwhile; 
            else:
            ?>
                <div class="no-products">
                    <p>No products available at the moment.</p>
                </div>
            <?php endif; ?>         
        </div>     
    </div>

    <!-- FAQ -->
    <section id="faq" class="faq">
        <h3>FAQs</h3>
        <div class="accordion">
            <div class="acc-item">
                <button class="acc-btn">How do workshop bookings work?</button>
                <div class="acc-panel">
                    <p>Workshops run most weekends. Choose a craft, pick a date at checkout (coming soon), and you’ll receive a confirmation email with details.</p>
                </div>
            </div>
            <div class="acc-item">
                <button class="acc-btn">Do you offer gift cards?</button>
                <div class="acc-panel">
                    <p>Yes — digital gift cards can be used on products or any workshop. Email us to arrange a custom amount.</p>
                </div>
            </div>
            <div class="acc-item">
                <button class="acc-btn">Where are you based?</button>
                <div class="acc-panel">
                    <p>Sydney, NSW — with pop-up classes in select suburbs. We ship Australia-wide.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer class="site-footer">
        <div class="footer-wrap">
            <div class="footer-top">
                <div class="footer-brand">
                    <h4>CraftyHands Workshops</h4>
                    <p>Curated handcrafted goods & friendly classes with local makers.</p>
                </div>

                <div class="footer-col">
                    <h5>Links</h5>
                    <ul>
                        <li><a href="#about">About Us</a></li>
                        <li><a href="#faq">FAQ</a></li>
                        <li><a href="productCategory.php">Shop</a></li>
                    </ul>
                </div>

                <div class="footer-col">
                    <h5>Contact</h5>
                    <ul>
                        <li><a href="mailto:support@craftyhands.example">support@craftyhands.example</a></li>
                        <li><a href="tel:+61123456789">+61 123 456 789</a></li>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
                <small>&copy; <?php echo date('Y'); ?> CraftyHands Workshops. All rights reserved.</small>
            </div>
        </div>
    </footer>

    <script>
        // Product description -> go to details
        document.querySelectorAll('.product-item').forEach(item => {
            const description = item.querySelector('.product-description');
            const readMore = item.querySelector('.read-more');

            if (description && readMore) {
                readMore.addEventListener('click', () => {
                    const productId = item.querySelector('input[name="product_id"]').value;
                    window.location.href = `productDetails.php?id=${encodeURIComponent(productId)}`;
                });
            }
        });

        // Simple accordion for FAQ
        document.querySelectorAll('.acc-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                const panel = btn.nextElementSibling;
                const open = panel.style.display === 'block';
                document.querySelectorAll('.acc-panel').forEach(p => p.style.display = 'none');
                panel.style.display = open ? 'none' : 'block';
            });
        });
    </script>
</body> 
</html>

<?php 
// Close the database connection
if (isset($con) && $con instanceof mysqli) { $con->close(); }
?>
