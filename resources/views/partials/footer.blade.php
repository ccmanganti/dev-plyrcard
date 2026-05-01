<footer id="site-footer">
  <a href="/" class="footer-logo" aria-label="PLYRCARD Home">PLYR<span>CARD</span></a>

  <nav class="footer-nav" aria-label="Footer navigation">
    <a href="/" class="{{ ($activePage ?? '') === 'home' ? 'active' : '' }}">Home</a>
    <a href="/about" class="{{ ($activePage ?? '') === 'about' ? 'active' : '' }}">About</a>
    <a href="/pricing" class="{{ ($activePage ?? '') === 'pricing' ? 'active' : '' }}">Pricing</a>
    <a href="/podcast" class="{{ ($activePage ?? '') === 'podcast' ? 'active' : '' }}">Podcast</a>
    <a href="/book-demo" class="{{ ($activePage ?? '') === 'book-demo' ? 'active' : '' }}">Book a Demo</a>
    <a href="/registration" class="{{ ($activePage ?? '') === 'registration' ? 'active' : '' }}">Start Free</a>
  </nav>

  <div class="footer-bottom">
    <p class="footer-copy">&copy; {{ date("Y") }} PLYRCARD. All rights reserved.</p>
    <p class="footer-tagline">Your Game. Your Brand. One Card.</p>
  </div>
</footer>
