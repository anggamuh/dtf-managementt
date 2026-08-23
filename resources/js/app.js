import './bootstrap';
import Alpine from 'alpinejs'
import Swal from 'sweetalert2'

window.Alpine = Alpine
window.Swal = Swal
Alpine.start()

// SweetAlert2 Global Helpers
window.showSuccessAlert = (title, message = '') => {
    return Swal.fire({
        icon: 'success',
        title,
        text: message,
        confirmButtonColor: '#16a34a',
        confirmButtonText: 'OK'
    });
};

window.showErrorAlert = (title, message = '') => {
    return Swal.fire({
        icon: 'error',
        title,
        text: message,
        confirmButtonColor: '#dc2626',
        confirmButtonText: 'OK'
    });
};

window.showWarningAlert = (title, message = '') => {
    return Swal.fire({
        icon: 'warning',
        title,
        text: message,
        confirmButtonColor: '#d97706',
        confirmButtonText: 'OK'
    });
};

window.showConfirmDialog = (title, message = '', confirmText = 'Ya', cancelText = 'Batal') => {
    return Swal.fire({
        title,
        text: message,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#2563eb',
        cancelButtonColor: '#6b7280',
        confirmButtonText: confirmText,
        cancelButtonText: cancelText
    });
};

window.showLoadingAlert = (message = 'Mohon tunggu...') => {
    Swal.fire({
        title: message,
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
};

window.closeLoadingAlert = () => {
    Swal.close();
};
// Theme management
(function() {
    const THEME_KEY = 'theme';
    const themeLightIcon = () => document.getElementById('theme-icon-light');
    const themeDarkIcon = () => document.getElementById('theme-icon-dark');

    const getPreferredTheme = () => {
        const stored = localStorage.getItem(THEME_KEY);
        if (stored) return stored;
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    };

    const updateThemeIcon = (theme) => {
        const lightIcon = themeLightIcon();
        const darkIcon = themeDarkIcon();
        if (!lightIcon || !darkIcon) return;

        if (theme === 'dark') {
            lightIcon.classList.remove('hidden');
            darkIcon.classList.add('hidden');
        } else {
            darkIcon.classList.remove('hidden');
            lightIcon.classList.add('hidden');
        }
    };

    const applyTheme = (theme) => {
        document.documentElement.classList.toggle('dark', theme === 'dark');
        updateThemeIcon(theme);
    };

    const initTheme = () => {
        const theme = getPreferredTheme();
        applyTheme(theme);
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', e => {
            if (!localStorage.getItem(THEME_KEY)) {
                applyTheme(e.matches ? 'dark' : 'light');
            }
        });
    };

    window.toggleTheme = () => {
        const isDark = document.documentElement.classList.contains('dark');
        const newTheme = isDark ? 'light' : 'dark';
        localStorage.setItem(THEME_KEY, newTheme);
        applyTheme(newTheme);
    };

    initTheme();
})();

