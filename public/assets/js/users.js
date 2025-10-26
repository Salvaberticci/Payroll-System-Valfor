// public/assets/js/users.js
// Funciones específicas para la página de gestión de usuarios

// Función para mostrar el modal de confirmación de eliminación
function showDeleteConfirm(userId, username) {
    console.log('showDeleteConfirm called with:', userId, username); // Debug
    document.getElementById('usernameToDelete').innerText = username;
    const deleteButton = document.getElementById('confirmDeleteButton');
    deleteButton.href = 'users.php?action=delete&id=' + userId;

    // Usar data attributes para mostrar el modal (más compatible)
    const modalElement = document.getElementById('deleteConfirmModal');
    if (modalElement) {
        modalElement.style.display = 'block';
        modalElement.classList.add('show');
        document.body.classList.add('modal-open');

        // Crear backdrop
        const backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        document.body.appendChild(backdrop);
    } else {
        console.error('Modal element not found');
    }
}

function printUsersPDF() {
    window.open('generate_users_pdf.php', '_blank');
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    console.log('users.js loaded'); // Debug
});