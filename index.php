<?php
require_once __DIR__ . '/includes/header.php';

// Get Settings for landing page
$stmt = $pdo->query("SELECT * FROM settings");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$hero_bg  = $settings['hero_bg_image'] ?? 'https://images.unsplash.com/photo-1522202176988-66273c2fd55f?q=80&w=2071&auto=format&fit=crop';
$hero_h   = $settings['hero_heading']  ?? 'Unlock Your Potential with Expert Guidance';
$hero_p   = $settings['hero_subtext']  ?? 'Experience premium education designed to elevate your skills and career. Join our community of lifelong learners today.';

// Custom HTML Support
$tenant = isset($tenant) ? $tenant : []; 
if (!empty($tenant['custom_homepage_html'])):
    $custom_html = $tenant['custom_homepage_html'];
    $placeholders = [
        '{{hero_bg_image}}' => $hero_bg,
        '{{hero_heading}}'  => $hero_h,
        '{{hero_subtext}}'  => $hero_p,
        '{{tutor_name}}'    => $settings['tutor_name'] ?? 'Dr. John Smith',
        '{{tutor_bio}}'     => $settings['tutor_bio'] ?? 'PhD in Education...',
        '{{tutor_image}}'   => $settings['tutor_image'] ?? '',
        '{{site_name}}'     => $settings['site_name'] ?? 'Elms'
    ];
    foreach ($placeholders as $key => $val) {
        $custom_html = str_replace($key, htmlspecialchars($val), $custom_html);
    }
    echo $custom_html;

else: ?>

<link rel="stylesheet" href="<?= SITE_ROOT ?>css/home.css?v=<?= filemtime(__DIR__ . '/css/home.css') ?>">


<!-- Hero Section -->
<section class="hero">
    <div class="hero-visual">
        <div class="hero-visual-grid"></div>
    </div>
    <div class="container">
        <div class="hero-content">
            <div class="hero-badge">
                <div class="hero-badge-dot"></div> Enrolling Now for 2026
            </div>
            <h1><?= htmlspecialchars($hero_h) ?></h1>
            <p><?= htmlspecialchars($hero_p) ?></p>
            <div class="hero-cta">
                <a href="courses.php" class="btn btn-primary btn-lg">Explore Courses</a>
                <?php if (!isset($_SESSION['user_id'])): ?>
                    <a href="register.php" class="btn btn-secondary btn-lg">Join Now</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Features -->
<section class="container mb-12" style="padding-top: 80px;">
    <div class="section-header">
        <span class="section-eyebrow">Why Choose Us</span>
        <h2>A Platform Built for Excellence</h2>
        <p class="text-secondary">Premium education designed for modern learners.</p>
    </div>
    
    <div class="grid grid-cols-3">
        <div class="card feature-card">
            <div class="feature-icon-box">
                <svg class="icon icon-lg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
            </div>
            <h4 class="mb-2">Expert Tutors</h4>
            <p class="text-secondary" style="font-size: 0.9rem;">Learn from industry leaders and PhDs with years of practical experience.</p>
        </div>
        
        <div class="card feature-card">
            <div class="feature-icon-box">
                <svg class="icon icon-lg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
            </div>
            <h4 class="mb-2">Flexible Learning</h4>
            <p class="text-secondary" style="font-size: 0.9rem;">Access your classes and materials anytime, anywhere — on your schedule.</p>
        </div>
        
        <div class="card feature-card">
            <div class="feature-icon-box">
                <svg class="icon icon-lg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="23,7 16,12 23,17"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>
                </svg>
            </div>
            <h4 class="mb-2">Premium Content</h4>
            <p class="text-secondary" style="font-size: 0.9rem;">High-quality video recordings, detailed notes, and interactive live sessions.</p>
        </div>
    </div>
</section>

<!-- Tutor Spotlight -->
<section class="container mb-12" style="padding-bottom: 80px;">
    <div class="tutor-grid">
        <div class="tutor-image-wrap">
            <img src="<?= htmlspecialchars($settings['tutor_image'] ?? 'https://images.unsplash.com/photo-1560250097-0b93528c311a?q=80&w=800&auto=format&fit=crop') ?>" alt="Tutor">
        </div>
        <div>
            <div class="badge badge-indigo mb-4">Lead Instructor</div>
            <h2 class="mb-4"><?= htmlspecialchars($settings['tutor_name'] ?? 'Dr. John Smith') ?></h2>
            <p class="text-secondary mb-8" style="font-size: 1.05rem;">
                <?= htmlspecialchars($settings['tutor_bio'] ?? 'PhD in Education with over 15 years of teaching experience in world-class institutions. Specialised in digital transformation and modern pedagogy.') ?>
            </p>
            <div class="tutor-stat-grid">
                <div class="tutor-stat">
                    <div class="tutor-stat-num">15+</div>
                    <div class="tutor-stat-label">Years Exp.</div>
                </div>
                <div class="tutor-stat">
                    <div class="tutor-stat-num">10k+</div>
                    <div class="tutor-stat-label">Students</div>
                </div>
                <div class="tutor-stat">
                    <div class="tutor-stat-num">50+</div>
                    <div class="tutor-stat-label">Courses</div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="container mb-12">
    <div class="cta-section">
        <h2>Ready to Start Your Journey?</h2>
        <p>Join thousands of students who are already learning and growing with us.</p>
        <div class="flex-center">
            <a href="register.php" class="btn btn-primary btn-lg">Get Started Now</a>
            <a href="courses.php" class="btn btn-secondary btn-lg">Browse Courses</a>
        </div>
    </div>
</section>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
