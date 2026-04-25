<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Season 04 — Demo</title>
  @vite(['resources/css/season04.css', 'resources/js/app.js'])
</head>
<body>

  @include('layouts.navigation')

  <main>
    <section class="ss04-hero courier-hero" aria-label="Hero">
      <div class="hero-card ss04-grid">
        <div class="hero-left">
          <h1 class="hero-title">Effortless Parcel Delivery and<br>Live Tracking Delight</h1>
          <p class="hero-sub">We will pickup your parcel from your location as your entry request and deliver it securely to the recipient with real-time tracking.</p>

          <!-- tracking input removed per request -->
        </div>

        <div class="hero-right">
          <div class="hero-figure">
            <img src="https://images.unsplash.com/photo-1542291026-7eec264c27ff?q=80&w=1200&auto=format&fit=crop&crop=entropy" alt="Courier holding parcel">
          </div>
        </div>
      </div>
    </section>

    <section class="manifesto ss04-grid">
      <div class="label">Manifesto</div>
      <div class="copy">A technical utilitarian editorial voice with <span class="highlight">raw textures</span> and <span class="highlight">massive type</span>.</div>
    </section>
    <!-- Developer specification copy removed from public demo. -->

    <!-- Product examples removed: this demo shows styles only. -->

  </main>

  <footer class="ss04-footer">
    <div class="cols">
      <div><h4>Shop</h4><p>Men • Women • Editorial</p></div>
      <div><h4>Company</h4><p>About • Press • Careers</p></div>
      <div><h4>Help</h4><p>Shipping • Returns</p></div>
      <div>
        <h4>Newsletter</h4>
        <div class="newsletter">
          <input placeholder="Email address" aria-label="Email">
          <button>Send</button>
        </div>
      </div>
    </div>
    <div class="ghost-brand">{{ strtolower(config('app.name', 'swapship')) }}</div>
  </footer>

  <div class="ss04-noise" aria-hidden="true">
    <svg preserveAspectRatio="none" width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
      <filter id="ss04-noise-filter"><feTurbulence type="fractalNoise" baseFrequency="0.9" numOctaves="3" stitchTiles="stitch"/></filter>
      <rect width="100%" height="100%" filter="url(#ss04-noise-filter)" fill="#000"/>
    </svg>
  </div>

  </body>
</html>
