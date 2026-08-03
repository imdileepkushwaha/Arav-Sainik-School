<?php
session_start();
require_once __DIR__ . '/includes/db_connect.php';
require_once __DIR__ . '/admin/includes/erp_helpers.php';
require_once __DIR__ . '/admin/includes/settings_helpers.php';

ensureErpSchema($pdo);
ensureSettingsSchema($pdo);
$school = getSchoolProfile($pdo);
$logoUrl = schoolBrandingUrl($school['logo'] ?? '', 'portal');
$logoLightUrl = schoolBrandingUrl($school['logo_light'] ?? '', 'portal');
$headerLogoUrl = $logoUrl;
$footerLogoUrl = $logoLightUrl ?: $logoUrl;
$faviconUrl = schoolBrandingUrl($school['favicon'] ?? '', 'portal');

$brandName = $school['name'] ?: 'Our School';
$brandTag = $school['tagline'] ?: 'Modern Education';
$brandPhone = $school['phone'] ?: '';
$brandEmail = $school['email'] ?: '';
$brandAddress = $school['address'] ?: '';
$brandPrincipal = $school['principal'] ?? '';
$brandAffiliation = $school['affiliation'] ?: 'CBSE';

$brandLocation = 'Our Area';
if ($brandAddress) {
    $addrParts = array_filter(array_map('trim', explode(',', $brandAddress)));
    if ($addrParts) {
        $brandLocation = end($addrParts);
    }
}

$studentCount = 0;
$teacherCount = 0;
try {
    $studentCount = (int) $pdo->query("SELECT COUNT(*) FROM students WHERE status = 'Active'")->fetchColumn();
    $teacherCount = (int) $pdo->query("SELECT COUNT(*) FROM teachers WHERE status = 'Active'")->fetchColumn();
} catch (PDOException $e) {
}

$notices = getActiveNotices($pdo, 6, 'All');

function getHomepageToppers(PDO $pdo, int $limit = 2): array {
    try {
        $exam = $pdo->query("SELECT id, class_name, name FROM exams WHERE status = 'Active' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (!$exam) {
            return [];
        }
        $analytics = getExamClassAnalytics($pdo, (int) $exam['id']);
        if (!$analytics || empty($analytics['results'])) {
            return [];
        }
        $out = [];
        foreach (array_slice($analytics['results'], 0, $limit) as $row) {
            $st = $row['student'];
            $photo = '';
            if (!empty($st['photo'])) {
                $rel = ltrim($st['photo'], '/');
                if (file_exists(__DIR__ . '/admin/' . $rel)) {
                    $photo = 'admin/' . $rel;
                }
            }
            $out[] = [
                'name' => $st['name'] ?? 'Student',
                'class' => $exam['class_name'],
                'percentage' => $row['percentage'],
                'photo' => $photo,
            ];
        }
        return $out;
    } catch (PDOException $e) {
        return [];
    }
}

$toppers = getHomepageToppers($pdo, 2);

if (!isset($_SESSION['sw_captcha_a'], $_SESSION['sw_captcha_b'])) {
    $_SESSION['sw_captcha_a'] = random_int(2, 9);
    $_SESSION['sw_captcha_b'] = random_int(2, 9);
}

$contactSuccess = false;
$contactError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['contact_submit'])) {
    $name = trim($_POST['full_name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $captcha = trim($_POST['captcha'] ?? '');
    $expected = (string) ((int) $_SESSION['sw_captcha_a'] + (int) $_SESSION['sw_captcha_b']);

    if ($name === '' || $mobile === '') {
        $contactError = 'Please enter your name and mobile number.';
    } elseif ($captcha !== $expected) {
        $contactError = 'Incorrect captcha answer. Please try again.';
    } else {
        try {
            $pdo->prepare(
                "INSERT INTO admission_enquiries (student_name, mobile, email, class_sought, message, status) VALUES (?,?,?,?,?, 'New')"
            )->execute([$name, $mobile, $email ?: null, 'Website Contact', $message ?: null]);
            $contactSuccess = true;
            $_SESSION['sw_captcha_a'] = random_int(2, 9);
            $_SESSION['sw_captcha_b'] = random_int(2, 9);
        } catch (PDOException $e) {
            $contactError = 'Could not send message. Please call the school office.';
        }
    }
}

$captchaA = (int) $_SESSION['sw_captcha_a'];
$captchaB = (int) $_SESSION['sw_captcha_b'];
$phoneDial = preg_replace('/\s+/', '', $brandPhone);

$heroSlides = [
    'https://images.unsplash.com/photo-1580582932707-520aed937b7b?w=1920&q=80',
    'https://images.unsplash.com/photo-1523050854058-8df90110c9f1?w=1920&q=80',
    'https://images.unsplash.com/photo-1509062522246-3755977927d7?w=1920&q=80',
    'https://images.unsplash.com/photo-1498243691581-b145c3f54a5a?w=1920&q=80',
];

$salientFeatures = [
    ['icon' => 'fa-face-smile', 'title' => 'Expert Teachers', 'text' => 'We have a team of child education professionals, each with more than a decade of experience.', 'sports' => false],
    ['icon' => 'fa-calculator', 'title' => 'Active Learning', 'text' => 'If you want your child to catch up or get ahead, give ' . $brandName . ' a call!', 'sports' => false],
    ['icon' => 'fa-language', 'title' => 'English Medium', 'text' => 'English is the primary language of communication on campus, with special focus on speaking skills.', 'sports' => false],
    ['icon' => 'fa-dumbbell', 'title' => 'Fullday Programs', 'text' => 'To provide a high-quality education that prepares all students to achieve their full potential.', 'sports' => false],
    ['icon' => 'fa-seedling', 'title' => 'Clear Approach', 'text' => 'All children can reach their learning potential and they can achieve everything.', 'sports' => false],
    ['icon' => 'fa-hands-holding-child', 'title' => 'Social Upliftment', 'text' => 'We focus on upliftment of marginalized and less privileged students.', 'sports' => false],
    ['icon' => 'fa-futbol', 'title' => 'Sports', 'text' => 'We encourage active participation in sports and physical activities to build fitness, teamwork, discipline, and confidence among students.', 'sports' => true],
];

$galleryItems = [
    ['title' => 'Student Appreciation', 'img' => 'https://images.unsplash.com/photo-1529390079861-591de354faf5?w=800&q=80'],
    ['title' => 'Picnic', 'img' => 'https://images.unsplash.com/photo-1503454536596-3d119bd781fc?w=800&q=80'],
    ['title' => 'Cultural events', 'img' => 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=800&q=80'],
    ['title' => 'Badge Distribution', 'img' => 'https://images.unsplash.com/photo-1523580494863-6f3031224c94?w=800&q=80'],
];

$statStudents = $studentCount > 0 ? number_format($studentCount) . '+' : '500+';
$statTeachers = $teacherCount > 0 ? number_format($teacherCount) . '+' : '50+';

$nameParts = preg_split('/\s+/', trim($brandName), 2);
$heroTitleMain = $nameParts[0] ?? $brandName;
$heroTitleSub = $nameParts[1] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($brandName); ?> | <?php echo htmlspecialchars($brandTag); ?></title>
    <?php if ($faviconUrl): ?><link rel="icon" href="<?php echo htmlspecialchars($faviconUrl); ?>"><?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&family=Inter:wght@400;500;600&family=Great+Vibes&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
    <link rel="stylesheet" href="assets/css/school-website.css">
</head>
<body>

<div id="preloader" class="sw-preloader">
    <div class="sw-loader-content">
        <div class="sw-book-loader"><span></span><span></span><span></span></div>
        <div class="sw-loader-text">Loading <?php echo htmlspecialchars(mb_strimwidth($brandName, 0, 20, '…')); ?>…</div>
    </div>
</div>

<header class="sw-main-header" id="mainHeader">
    <div class="sw-topbar">
        <div class="sw-container sw-topbar-container">
            <div class="sw-topbar-left">
                <?php if ($brandEmail): ?>
                <a href="mailto:<?php echo htmlspecialchars($brandEmail); ?>"><i class="fa-regular fa-envelope"></i> <?php echo htmlspecialchars($brandEmail); ?></a>
                <?php endif; ?>
                <?php if ($brandPhone): ?>
                <a href="tel:<?php echo htmlspecialchars($phoneDial); ?>"><i class="fa-solid fa-phone-volume"></i> <?php echo htmlspecialchars($brandPhone); ?></a>
                <?php endif; ?>
            </div>
            <div class="sw-topbar-right">
                <span class="sw-follow-text">Follow Us On:</span>
                <div class="sw-topbar-social">
                    <a href="#" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="#" aria-label="Twitter"><i class="fa-brands fa-twitter"></i></a>
                    <a href="#" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
                    <a href="#" aria-label="YouTube"><i class="fa-brands fa-youtube"></i></a>
                </div>
            </div>
        </div>
    </div>

    <nav class="sw-navbar">
        <div class="sw-container sw-nav-container">
            <a href="index.php" class="sw-logo">
                <?php if ($headerLogoUrl): ?>
                <img src="<?php echo htmlspecialchars($headerLogoUrl); ?>" alt="<?php echo htmlspecialchars($brandName); ?>" class="sw-logo-img">
                <?php else: ?>
                <span class="sw-logo-fallback"><?php echo htmlspecialchars(mb_strimwidth($brandName, 0, 24)); ?></span>
                <?php endif; ?>
            </a>

            <ul class="sw-nav-links" id="swNavLinks">
                <li><a href="#home" class="sw-nav-link active">Home</a></li>
                <li><a href="#about" class="sw-nav-link">About</a></li>
                <li><a href="#gallery" class="sw-nav-link">Gallery</a></li>
                <li><a href="#contact" class="sw-nav-link">Contact</a></li>
                <li class="sw-nav-login-mobile">
                    <span class="sw-nav-login-label">Login</span>
                    <a href="admin/index.php" class="sw-nav-link"><i class="fa-solid fa-user-shield"></i> Admin</a>
                    <a href="teacher/index.php" class="sw-nav-link"><i class="fa-solid fa-chalkboard-user"></i> Teacher</a>
                    <a href="portal/index.php" class="sw-nav-link"><i class="fa-solid fa-user-graduate"></i> Student</a>
                </li>
            </ul>

            <div class="sw-nav-right">
                <div class="sw-login-menu">
                    <button type="button" class="sw-btn sw-login-trigger" id="swLoginTrigger" aria-expanded="false" aria-haspopup="true">
                        <i class="fa-solid fa-right-to-bracket"></i> Login <i class="fa-solid fa-chevron-down sw-login-chev"></i>
                    </button>
                    <div class="sw-login-dropdown" id="swLoginDropdown" role="menu">
                        <a href="admin/index.php" role="menuitem"><i class="fa-solid fa-user-shield"></i><div><strong>Admin Login</strong><span>School office dashboard</span></div></a>
                        <a href="teacher/index.php" role="menuitem"><i class="fa-solid fa-chalkboard-user"></i><div><strong>Teacher Login</strong><span>Faculty portal</span></div></a>
                        <a href="portal/index.php" role="menuitem"><i class="fa-solid fa-user-graduate"></i><div><strong>Student Login</strong><span>Student portal</span></div></a>
                    </div>
                </div>
                <a href="#contact" class="sw-btn sw-cta-btn">Enroll Now <i class="fa-solid fa-arrow-right"></i></a>
                <button type="button" class="sw-hamburger" id="swHamburger" aria-label="Menu"><span></span><span></span><span></span></button>
            </div>
        </div>
    </nav>
</header>

<section id="home" class="sw-hero full-width-hero">
    <div class="swiper sw-heroSwiper sw-hero-bg-slider">
        <div class="swiper-wrapper">
            <?php foreach ($heroSlides as $slideImg): ?>
            <div class="swiper-slide">
                <img src="<?php echo htmlspecialchars($slideImg); ?>" alt="<?php echo htmlspecialchars($brandName); ?>" class="sw-hero-img">
            </div>
            <?php endforeach; ?>
        </div>
        <div class="swiper-button-next"></div>
        <div class="swiper-button-prev"></div>
        <div class="sw-hero-overlay"></div>
    </div>

    <div class="sw-hero-shape sw-shape-1"></div>
    <div class="sw-hero-shape sw-shape-2"></div>

    <div class="sw-container sw-hero-container centered-hero">
        <div class="sw-hero-content">
            <div class="sw-section-badge badge-light">🌟 Empowering Future Generations</div>
            <h1 class="sw-hero-title">Welcome to<br><span class="sw-highlight"><?php echo htmlspecialchars($heroTitleMain); ?></span><?php if ($heroTitleSub): ?> <span class="sw-highlight-sub"><?php echo htmlspecialchars($heroTitleSub); ?></span><?php endif; ?></h1>
            <p class="sw-hero-subtitle">Today is the day to learn something new. Get the best education with our modern approach to holistic learning.</p>
            <div class="sw-hero-buttons">
                <a href="#contact" class="sw-btn sw-btn-primary">Enroll Now</a>
                <a href="#about" class="sw-btn sw-btn-secondary sw-btn-light-outline">Read More</a>
            </div>
            <div class="sw-hero-stats-mini">
                <div class="sw-h-stat stat-glass"><strong><?php echo $statStudents; ?></strong><span>Students</span></div>
                <div class="sw-h-stat stat-glass"><strong><?php echo $statTeachers; ?></strong><span>Teachers</span></div>
                <div class="sw-h-stat stat-glass"><strong>#1</strong><span>in <?php echo htmlspecialchars($brandLocation); ?></span></div>
            </div>
        </div>
    </div>
</section>

<section id="about" class="sw-about">
    <div class="sw-container sw-about-container">
        <div class="sw-announcements-section">
            <div class="sw-announcements-header">
                <h3><i class="fa-solid fa-bullhorn"></i> Latest Announcements</h3>
            </div>
            <div class="sw-announcements-list">
                <?php if ($notices): ?>
                <div class="sw-announcements-ticker">
                    <?php foreach ($notices as $n):
                        $pubDate = !empty($n['publish_date']) ? date('d M Y', strtotime($n['publish_date'])) : '';
                    ?>
                    <div class="sw-announcement-card">
                        <div class="sw-ann-content">
                            <?php if ($pubDate): ?><div class="sw-ann-date"><i class="fa-regular fa-calendar-alt"></i> <?php echo $pubDate; ?></div><?php endif; ?>
                            <h4 class="sw-ann-title"><?php echo htmlspecialchars($n['title']); ?></h4>
                            <p class="sw-ann-desc"><?php echo htmlspecialchars(strip_tags($n['body'] ?? '')); ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="sw-empty-announcements">No announcements at this time.</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="sw-about-text">
            <div class="sw-section-badge">About Us</div>
            <h2 class="sw-section-title">Nurturing the Leaders of Tomorrow</h2>
            <p class="sw-section-desc">We aim at providing an environment which motivates children to become confident and well versed in their respective fields. We lay the foundation for right habits, attitude and education.</p>
            <ul class="sw-about-features">
                <li><div class="sw-check-icon">✓</div><div class="sw-feature-text"><strong>Holistic Development</strong> Focusing on both mental and physical fitness.</div></li>
                <li><div class="sw-check-icon">✓</div><div class="sw-feature-text"><strong>Expert Teachers</strong> A team of highly qualified and experienced professionals.</div></li>
                <li><div class="sw-check-icon">✓</div><div class="sw-feature-text"><strong>Interactive Learning</strong> Encouraging children to become keen observers and explorers.</div></li>
            </ul>
        </div>
    </div>
</section>

<section id="features" class="sw-features-redesign section-padding bg-white">
    <div class="sw-container">
        <div class="sw-section-header header-center">
            <div class="sw-section-badge badge-center">Why Choose Us</div>
            <h2 class="sw-section-title">Salient Features</h2>
            <p class="sw-section-desc mt-3">Every child has to follow all the disciplinary rules of the institution to maintain the school decorum.</p>
        </div>
        <div class="sw-features-list-grid">
            <?php foreach ($salientFeatures as $i => $f): ?>
            <div class="sw-feature-item">
                <div class="sw-f-icon<?php echo $f['sports'] ? ' f-icon-sports' : ''; ?>"><i class="fa-solid <?php echo $f['icon']; ?>"></i></div>
                <div class="sw-f-content">
                    <h3><?php echo htmlspecialchars($f['title']); ?></h3>
                    <p><?php echo htmlspecialchars($f['text']); ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section id="results" class="sw-results section-padding bg-light">
    <div class="sw-container">
        <div class="sw-section-header header-center">
            <div class="sw-section-badge badge-center">Our Pride</div>
            <h2 class="sw-section-title">Outstanding Results</h2>
            <p class="sw-section-desc mt-3">We are proud of our top-performing students who have excelled in their academics and brought glory to the school.</p>
        </div>
        <?php if ($toppers): ?>
        <div class="sw-result-grid">
            <?php foreach ($toppers as $t): ?>
            <div class="sw-result-poster">
                <div class="sw-poster-congrats">
                    <span class="sw-cursive">Congratulations</span>
                    <span class="sw-block">TO OUR TOPPER</span>
                </div>
                <div class="sw-poster-avatar-wrapper">
                    <div class="sw-poster-avatar">
                        <?php if ($t['photo']): ?>
                        <img src="<?php echo htmlspecialchars($t['photo']); ?>" alt="<?php echo htmlspecialchars($t['name']); ?>">
                        <?php else: ?>
                        <span><?php echo htmlspecialchars(mb_strtoupper(mb_substr($t['name'], 0, 1))); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="sw-poster-ribbon"><?php echo htmlspecialchars(mb_strtoupper($t['name'])); ?></div>
                <div class="sw-poster-score-box">
                    <span class="sw-class-label">Class - <?php echo htmlspecialchars($t['class']); ?></span>
                    <span class="sw-score-dash">-</span>
                    <span class="sw-score-percent"><?php echo $t['percentage']; ?>%</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="sw-results-empty">Top student results from recent exams will appear here.</div>
        <?php endif; ?>
    </div>
</section>

<section id="rules" class="sw-rules-section bg-white">
    <div class="sw-container">
        <div class="sw-section-header">
            <div class="sw-section-badge">Guidelines</div>
            <h2 class="sw-section-title">General Rules</h2>
        </div>
        <div class="sw-rules-grid">
            <div class="sw-feature-card glass-panel"><div class="sw-feature-icon icon-blue"><i class="fa-solid fa-clock"></i></div><h3>Punctuality</h3><p>Punctual and regular attendance is strictly insisted upon. Students must arrive at school before the morning assembly.</p></div>
            <div class="sw-feature-card glass-panel"><div class="sw-feature-icon icon-blue"><i class="fa-solid fa-shirt"></i></div><h3>Uniform Code</h3><p>Students must wear the prescribed clean and neat school uniform daily. Strict action will be taken for non-compliance.</p></div>
            <div class="sw-feature-card glass-panel"><div class="sw-feature-icon icon-blue"><i class="fa-solid fa-scale-balanced"></i></div><h3>Discipline</h3><p>Every student must possess willingness to comply with school rules and maintain decorum within the campus.</p></div>
            <div class="sw-feature-card glass-panel"><div class="sw-feature-icon icon-blue"><i class="fa-solid fa-book-open"></i></div><h3>Assignments</h3><p>Earnestness in home assignments and projects is required. Parents must monitor their child's daily progress.</p></div>
        </div>
    </div>
</section>

<section id="gallery" class="sw-gallery section-padding bg-light">
    <div class="sw-container">
        <div class="sw-section-header header-split">
            <div>
                <div class="sw-section-badge badge-left">Our Gallery</div>
                <h2 class="sw-section-title">School Memories</h2>
            </div>
            <a href="#gallery" class="sw-btn sw-btn-secondary">View All <i class="fa-solid fa-arrow-right"></i></a>
        </div>
        <div class="sw-gallery-grid">
            <?php foreach ($galleryItems as $g): ?>
            <a href="<?php echo htmlspecialchars($g['img']); ?>" class="sw-gallery-item" target="_blank" rel="noopener">
                <img src="<?php echo htmlspecialchars($g['img']); ?>" alt="<?php echo htmlspecialchars($g['title']); ?>">
                <div class="sw-gallery-caption"><?php echo htmlspecialchars($g['title']); ?></div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="sw-cta-banner">
    <div class="sw-cta-bg" style="background-image:url('<?php echo htmlspecialchars($heroSlides[2]); ?>')"></div>
    <div class="sw-container sw-cta-container">
        <div class="sw-cta-text">
            <h2>Enroll your Child to a Class</h2>
            <p>We will provide the perfect education for your child every day. Join our community and watch your child grow.</p>
        </div>
        <div class="sw-cta-btn-wrapper">
            <a href="#contact" class="sw-btn sw-cta-btn sw-cta-btn-lg">Join Now</a>
        </div>
    </div>
</section>

<section id="contact" class="sw-contact">
    <div class="sw-container sw-contact-container">
        <div class="sw-contact-info">
            <div class="sw-section-badge">Contact Us</div>
            <h2 class="sw-section-title">Keep in touch</h2>
            <p class="sw-section-desc">Welcome to <?php echo htmlspecialchars($brandName); ?>. For any queries, please feel free to reach out to us using the details below.</p>
            <ul class="sw-contact-list">
                <?php if ($brandAddress): ?>
                <li><div class="sw-contact-icon">📍</div><div class="sw-contact-detail"><h4>Address</h4><p><?php echo nl2br(htmlspecialchars($brandAddress)); ?></p></div></li>
                <?php endif; ?>
                <?php if ($brandPhone): ?>
                <li><div class="sw-contact-icon">📞</div><div class="sw-contact-detail"><h4>Phone</h4><p><a href="tel:<?php echo htmlspecialchars($phoneDial); ?>"><?php echo htmlspecialchars($brandPhone); ?></a></p></div></li>
                <?php endif; ?>
                <?php if ($brandEmail): ?>
                <li><div class="sw-contact-icon">✉️</div><div class="sw-contact-detail"><h4>Email</h4><p><a href="mailto:<?php echo htmlspecialchars($brandEmail); ?>"><?php echo htmlspecialchars($brandEmail); ?></a></p></div></li>
                <?php endif; ?>
            </ul>
            <div class="sw-portals-row">
                <a href="portal/index.php"><i class="fa-solid fa-user-graduate"></i> Student Portal</a>
                <a href="teacher/index.php"><i class="fa-solid fa-chalkboard-user"></i> Teacher Portal</a>
                <a href="admin/index.php"><i class="fa-solid fa-user-shield"></i> Admin Login</a>
            </div>
        </div>

        <div class="sw-contact-form-wrapper glass-panel">
            <?php if ($contactSuccess): ?>
            <div class="sw-form-success">
                <i class="fa-solid fa-circle-check"></i>
                <strong>Message sent successfully!</strong>
                <p>We will contact you shortly.</p>
                <a href="index.php#contact" class="sw-btn sw-btn-secondary">Send another</a>
            </div>
            <?php else: ?>
            <?php if ($contactError): ?><div class="sw-form-error"><?php echo htmlspecialchars($contactError); ?></div><?php endif; ?>
            <form method="POST" class="sw-contact-form">
                <input type="hidden" name="contact_submit" value="1">
                <div class="sw-form-group">
                    <input type="text" id="full_name" name="full_name" required placeholder=" ">
                    <label for="full_name">Full Name</label>
                </div>
                <div class="sw-form-group">
                    <input type="email" id="email" name="email" placeholder=" ">
                    <label for="email">Email Address</label>
                </div>
                <div class="sw-form-group">
                    <input type="tel" id="mobile" name="mobile" required placeholder=" " maxlength="15">
                    <label for="mobile">Mobile Number</label>
                </div>
                <div class="sw-form-group">
                    <textarea id="message" name="message" rows="3" placeholder=" "></textarea>
                    <label for="message">Your Message (Optional)</label>
                </div>
                <div class="sw-captcha-row">
                    <span class="sw-captcha-q"><?php echo $captchaA; ?> + <?php echo $captchaB; ?> = ?</span>
                    <div class="sw-form-group sw-captcha-input">
                        <input type="text" id="captcha" name="captcha" required placeholder=" " inputmode="numeric" autocomplete="off">
                        <label for="captcha">Answer</label>
                    </div>
                </div>
                <button type="submit" class="sw-btn sw-btn-primary sw-form-submit">Send Message</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<footer class="sw-modern-footer">
    <div class="sw-container">
        <div class="sw-footer-top-grid">
            <div class="sw-footer-widget">
                <a href="index.php" class="sw-footer-logo-link">
                    <?php if ($footerLogoUrl): ?><img src="<?php echo htmlspecialchars($footerLogoUrl); ?>" alt="<?php echo htmlspecialchars($brandName); ?>" class="sw-footer-logo"><?php endif; ?>
                </a>
                <p class="sw-brand-desc">Providing high-quality education that prepares all students to achieve their full potential and become the leaders of tomorrow.</p>
                <div class="sw-footer-socials">
                    <a href="#" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="#" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
                    <a href="#" aria-label="Twitter"><i class="fa-brands fa-twitter"></i></a>
                    <a href="#" aria-label="YouTube"><i class="fa-brands fa-youtube"></i></a>
                </div>
            </div>
            <div class="sw-footer-widget">
                <h4>Quick Links</h4>
                <a href="#home">Home</a>
                <a href="#about">About Us</a>
                <a href="#gallery">School Gallery</a>
                <a href="#contact">Contact Us</a>
            </div>
            <div class="sw-footer-widget">
                <h4>Academics</h4>
                <a href="#features">Salient Features</a>
                <a href="#rules">General Rules</a>
                <a href="#results">Outstanding Results</a>
                <a href="#contact">Admission Process</a>
            </div>
            <div class="sw-footer-widget">
                <h4>Get in Touch</h4>
                <?php if ($brandAddress): ?><span><?php echo htmlspecialchars($brandAddress); ?></span><?php endif; ?>
                <?php if ($brandPhone): ?><a href="tel:<?php echo htmlspecialchars($phoneDial); ?>"><?php echo htmlspecialchars($brandPhone); ?></a><?php endif; ?>
                <?php if ($brandEmail): ?><a href="mailto:<?php echo htmlspecialchars($brandEmail); ?>"><?php echo htmlspecialchars($brandEmail); ?></a><?php endif; ?>
            </div>
        </div>
        <div class="sw-footer-bottom-bar">
            <span>&copy; <?php echo date('Y'); ?> <strong><?php echo htmlspecialchars($brandName); ?></strong>. All rights reserved.</span>
            <div><a href="#">Privacy Policy</a> <span>|</span> <a href="#">Terms of Service</a></div>
        </div>
    </div>
</footer>

<?php if ($brandPhone): ?>
<div class="sw-float-stack" aria-label="Quick contact">
    <a href="https://wa.me/<?php echo htmlspecialchars(preg_replace('/\D/', '', $brandPhone)); ?>" class="sw-float-btn sw-float-wa" target="_blank" rel="noopener" aria-label="Chat on WhatsApp">
        <span class="sw-float-pulse" aria-hidden="true"></span>
        <span class="sw-float-pulse sw-float-pulse-delay" aria-hidden="true"></span>
        <i class="fa-brands fa-whatsapp"></i>
        <span class="sw-float-tip">WhatsApp</span>
    </a>
    <a href="tel:<?php echo htmlspecialchars($phoneDial); ?>" class="sw-float-btn sw-float-call" aria-label="Call school">
        <span class="sw-float-pulse" aria-hidden="true"></span>
        <span class="sw-float-pulse sw-float-pulse-delay" aria-hidden="true"></span>
        <i class="fa-solid fa-phone"></i>
        <span class="sw-float-tip">Call Us</span>
    </a>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<script>
(function () {
    window.addEventListener('load', function () {
        var pre = document.getElementById('preloader');
        if (pre) {
            pre.classList.add('hide');
            setTimeout(function () { pre.remove(); }, 500);
        }
    });

    new Swiper('.sw-heroSwiper', {
        loop: true,
        effect: 'fade',
        autoplay: { delay: 4500, disableOnInteraction: false },
        navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' }
    });

    var header = document.getElementById('mainHeader');
    window.addEventListener('scroll', function () {
        if (header) header.classList.toggle('scrolled', window.scrollY > 40);
    });

    var burger = document.getElementById('swHamburger');
    var links = document.getElementById('swNavLinks');
    if (burger && links) {
        burger.addEventListener('click', function () {
            links.classList.toggle('open');
            burger.classList.toggle('open');
        });
        links.querySelectorAll('a').forEach(function (a) {
            a.addEventListener('click', function () {
                links.classList.remove('open');
                burger.classList.remove('open');
            });
        });
    }

    var loginTrigger = document.getElementById('swLoginTrigger');
    var loginDropdown = document.getElementById('swLoginDropdown');
    if (loginTrigger && loginDropdown) {
        loginTrigger.addEventListener('click', function (e) {
            e.stopPropagation();
            var open = loginDropdown.classList.toggle('open');
            loginTrigger.classList.toggle('open', open);
            loginTrigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('click', function (e) {
            if (!loginDropdown.contains(e.target) && !loginTrigger.contains(e.target)) {
                loginDropdown.classList.remove('open');
                loginTrigger.classList.remove('open');
                loginTrigger.setAttribute('aria-expanded', 'false');
            }
        });
    }

    document.querySelectorAll('a[href^="#"]').forEach(function (a) {
        a.addEventListener('click', function (e) {
            var href = a.getAttribute('href');
            if (href.length > 1) {
                var target = document.querySelector(href);
                if (target) {
                    e.preventDefault();
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
        });
    });
})();
</script>
</body>
</html>
