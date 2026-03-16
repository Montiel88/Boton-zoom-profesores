// assets/js/base.js
// Funciones de UI compartidas (menú responsive y helpers ligeros)

function toggleMenu() {
    const menu = document.getElementById('navMenu');
    const hamburger = document.querySelector('.hamburger-menu');
    if (!menu || !hamburger) return;
    menu.classList.toggle('active');
    hamburger.classList.toggle('active');
}

document.addEventListener('click', (e) => {
    const menu = document.getElementById('navMenu');
    const hamburger = document.querySelector('.hamburger-menu');
    if (!menu || !hamburger) return;
    if (!menu.contains(e.target) && !hamburger.contains(e.target) && menu.classList.contains('active')) {
        menu.classList.remove('active');
        hamburger.classList.remove('active');
    }
});

window.addEventListener('resize', () => {
    if (window.innerWidth > 768) {
        const menu = document.getElementById('navMenu');
        const hamburger = document.querySelector('.hamburger-menu');
        if (menu && hamburger) {
            menu.classList.remove('active');
            hamburger.classList.remove('active');
        }
    }
});
