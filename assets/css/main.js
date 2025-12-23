function toggleUserMenu() {
    const menu = document.querySelector('.user-menu');
    if (!menu) return;

    menu.classList.toggle('open');
}

// Cerrar al hacer clic fuera
document.addEventListener('click', function (e) {
    const menu = document.querySelector('.user-menu');
    if (!menu) return;

    if (!menu.contains(e.target)) {
        menu.classList.remove('open');
    }
});
