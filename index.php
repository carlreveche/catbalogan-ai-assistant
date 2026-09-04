<?php
require_once __DIR__ . '/includes/functions.php';
$loggedIn = is_logged_in();
$userName = current_user_name();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Catbalogan City AI Assistant - Municipal Permits &amp; Clearances</title>
<link rel="icon" type="image/png" href="assets/images/favicon.png">
<meta name="description" content="The official AI virtual assistant of Catbalogan City. Ask about Barangay Clearance, Business Permits, requirements, fees, steps, and processing time - available 24/7.">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Public+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/vendor/bootstrap/bootstrap.min.css">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="landing-page">

  <nav class="landing-nav">
    <a class="landing-brand" href="index.php">
      <img class="brand-badge" src="assets/images/logo.jpg" alt="Catbalogan City logo">
      <div>
        <strong>Catbalogan City</strong>
        <span>AI Virtual Assistant</span>
      </div>
    </a>
    <div class="landing-nav-actions">
      <?php if ($loggedIn): ?>
        <a href="chat.php" class="btn btn-primary">Continue to chat</a>
      <?php else: ?>
        <a href="login.php" class="btn btn-secondary">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" x2="3" y1="12" y2="12"/></svg>
          Log in
        </a>
        <a href="register.php" class="btn btn-primary">Register</a>
      <?php endif; ?>
    </div>
  </nav>

  <header class="hero">
    <div class="hero-grid">
      <div class="hero-content">
        <p class="hero-eyebrow">An official service of the City Government of Catbalogan</p>
        <h1>Municipal permits information, available 24/7</h1>
        <p class="hero-sub">
          Ask about the <strong>Barangay Clearance</strong>, <strong>New Business Permit</strong>,
          and <strong>Business Permit Renewal</strong> &mdash; get requirements, fees, steps, and
          processing time instantly, any time of day.
        </p>
        <div class="hero-ctas">
          <?php if ($loggedIn): ?>
            <a href="chat.php" class="btn btn-primary btn-lg">Start a conversation</a>
          <?php else: ?>
            <a href="register.php" class="btn btn-primary btn-lg">Start a conversation</a>
            <a href="login.php" class="btn btn-lg btn-hero-ghost">I already have an account</a>
          <?php endif; ?>
        </div>
        <ul class="hero-points">
          <li>Requirements</li>
          <li>Fees</li>
          <li>Steps</li>
          <li>Processing time</li>
          <li>Office hours</li>
        </ul>
      </div>
    </div>
  </header>

  <section class="landing-section" id="services">
    <div class="landing-container">
      <h2 class="landing-h2">What you can ask about</h2>
      <p class="landing-lead">Every service our citizens request most, answered in plain language.</p>
      <div class="landing-cards">
        <a class="landing-card" href="<?= $loggedIn ? 'chat.php' : 'register.php' ?>">
          <div class="card-icon">&#128203;</div>
          <h3>Barangay Clearance</h3>
          <p>Requirements, fee, and steps to get your barangay clearance for employment, school, or legal purposes.</p>
          <span class="card-link">Ask the assistant &rarr;</span>
        </a>
        <a class="landing-card" href="<?= $loggedIn ? 'chat.php' : 'register.php' ?>">
          <div class="card-icon">&#127970;</div>
          <h3>New Business Permit</h3>
          <p>What to prepare when opening a business in Catbalogan City, from requirements to processing time.</p>
          <span class="card-link">Ask the assistant &rarr;</span>
        </a>
        <a class="landing-card" href="<?= $loggedIn ? 'chat.php' : 'register.php' ?>">
          <div class="card-icon">&#128260;</div>
          <h3>Business Permit Renewal</h3>
          <p>Renewal documents, fees, deadlines, and steps so your business stays compliant every year.</p>
          <span class="card-link">Ask the assistant &rarr;</span>
        </a>
      </div>
    </div>
  </section>

  <section class="landing-section landing-section-alt">
    <div class="landing-container">
      <h2 class="landing-h2">How it works</h2>
      <div class="landing-steps">
        <div class="step">
          <span class="step-num">1</span>
          <h3>Ask</h3>
          <p>Type your question, or tap a quick topic. No forms, no waiting in line.</p>
        </div>
        <div class="step">
          <span class="step-num">2</span>
          <h3>Get the details</h3>
          <p>Receive the exact requirements, fees, steps, and processing time for your permit.</p>
        </div>
        <div class="step">
          <span class="step-num">3</span>
          <h3>Arrive prepared</h3>
          <p>Visit the city office with everything ready &mdash; shorter visits, fewer follow-ups.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="trust-strip">
    <div class="landing-container">
      <p>An official service of the City Government of Catbalogan &mdash; serving the people of Samar's western coast.</p>
    </div>
  </section>

  <footer class="landing-footer">
    <div class="landing-container landing-footer-grid">
      <div>
        <div class="landing-brand">
          <img class="brand-badge" src="assets/images/logo.jpg" alt="Catbalogan City logo">
          <div>
            <strong>Catbalogan City</strong>
            <span>AI Virtual Assistant</span>
          </div>
        </div>
        <p class="footer-about">Your 24/7 guide to municipal permits and clearances.</p>
      </div>
      <div>
        <h4>City Hall</h4>
        <p>City Hall, Brgy. San Roque,<br>Catbalogan City, Samar, Philippines</p>
      </div>
      <div>
        <h4>Office Hours</h4>
        <p>Monday &ndash; Friday<br>8:00 AM &ndash; 5:00 PM<br>(Closed on holidays)</p>
      </div>
      <div>
        <h4>Get started</h4>
        <p>
          <?php if ($loggedIn): ?>
            <a href="chat.php">Continue to chat</a>
          <?php else: ?>
            <a href="register.php">Create an account</a><br>
            <a href="login.php">Log in</a>
          <?php endif; ?>
        </p>
      </div>
    </div>
    <div class="landing-container landing-footer-bottom">
      &copy; <?= date('Y') ?> City Government of Catbalogan. All rights reserved.
    </div>
  </footer>

</body>
</html>
