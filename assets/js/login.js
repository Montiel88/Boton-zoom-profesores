// assets/js/login.js
// Genera partículas flotantes sutiles para el fondo del login.

document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('particle-container');
    if (!container) return;

    const particleCount = 20;

    for (let i = 0; i < particleCount; i++) {
        const particle = document.createElement('div');
        particle.className = 'particle';

        const size = Math.random() * 5 + 2 + 'px';
        particle.style.width = size;
        particle.style.height = size;

        particle.style.left = Math.random() * 100 + 'vw';
        particle.style.setProperty('--d', Math.random() * 10 + 10 + 's');
        particle.style.animationDelay = Math.random() * 20 + 's';

        container.appendChild(particle);
    }
});
