/**
 * home.js — AllPratto Home Page
 *
 * Comportamentos:
 *  - Reveal suave dos cards ao entrar no viewport (IntersectionObserver)
 *  - Hover parallax leve na imagem hero (mousemove)
 *  - Fallback já tratado no HTML via onerror nas <img>
 */

// Removida animação de entrada lenta dos cards para que os símbolos não desapareçam.
// ─── Parallax leve na imagem hero ────────────────────────────────────────────
const heroWrap = document.querySelector('.ap-hero');
const heroImg  = document.querySelector('.ap-hero__img');

if (heroWrap && heroImg && window.matchMedia('(hover: hover)').matches) {
  heroWrap.addEventListener('mousemove', (e) => {
    const { left, top, width, height } = heroWrap.getBoundingClientRect();
    const cx = (e.clientX - left) / width  - 0.5;  // -0.5 … +0.5
    const cy = (e.clientY - top)  / height - 0.5;

    heroImg.style.transform = `scale(1.04) translate(${cx * 8}px, ${cy * 6}px)`;
  });

  heroWrap.addEventListener('mouseleave', () => {
    heroImg.style.transform = 'scale(1) translate(0,0)';
  });
}

// ─── Banner cardápio: parallax scroll leve ───────────────────────────────────
const banner    = document.querySelector('.ap-menu-banner');
const bannerImg = document.querySelector('.ap-menu-banner__img');

if (banner && bannerImg) {
  window.addEventListener('scroll', () => {
    const { top, height } = banner.getBoundingClientRect();
    const center = top + height / 2 - window.innerHeight / 2;
    const shift  = Math.min(Math.max(center * 0.08, -18), 18);
    bannerImg.style.transform = `translateY(${shift}px)`;
  }, { passive: true });
}
