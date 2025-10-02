// public/assets/js/script.js
// Este script maneja la funcionalidad de los menús desplegables personalizados.

// Mensaje de prueba para confirmar que este script se está ejecutando
console.log("script.js cargado y ejecutándose.");

document.addEventListener('DOMContentLoaded', function() {
    // Selecciona todos los elementos que actúan como "toggle" para un dropdown personalizado
    const customDropdownToggles = document.querySelectorAll('[data-custom-toggle="dropdown"]');

    customDropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(event) {
            event.preventDefault(); // Evita que el enlace navegue a '#'

            // Encuentra el menú desplegable asociado (el siguiente elemento hermano <ul> con clase dropdown-menu)
            const dropdownMenu = this.nextElementSibling;

            if (dropdownMenu && dropdownMenu.classList.contains('dropdown-menu')) {
                // Cierra cualquier otro menú desplegable abierto
                document.querySelectorAll('.dropdown-menu.show').forEach(openMenu => {
                    if (openMenu !== dropdownMenu) { // No cierres el menú actual si ya está abierto
                        openMenu.classList.remove('show');
                        const openToggle = openMenu.previousElementSibling;
                        if (openToggle) {
                            openToggle.setAttribute('aria-expanded', 'false');
                        }
                    }
                });

                // Alterna la visibilidad del menú actual
                dropdownMenu.classList.toggle('show');
                // Actualiza el atributo aria-expanded para accesibilidad
                this.setAttribute('aria-expanded', dropdownMenu.classList.contains('show'));
            }
        });
    });

    // Cierra el menú desplegable si se hace clic fuera de él
    document.addEventListener('click', function(event) {
        customDropdownToggles.forEach(toggle => {
            const dropdownMenu = toggle.nextElementSibling;
            if (dropdownMenu && dropdownMenu.classList.contains('dropdown-menu') && dropdownMenu.classList.contains('show')) {
                // Si el clic no fue dentro del toggle ni dentro del menú desplegable
                if (!toggle.contains(event.target) && !dropdownMenu.contains(event.target)) {
                    dropdownMenu.classList.remove('show');
                    toggle.setAttribute('aria-expanded', 'false');
                }
            }
        });
    });
});
