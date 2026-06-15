import Swal from 'sweetalert2'

const isDarkMode = () => document.documentElement.classList.contains('dark')

const buildPopupClasses = () => ({
    popup: isDarkMode() ? 'sgi-swal-popup sgi-swal-popup-dark' : 'sgi-swal-popup sgi-swal-popup-light',
    title: 'sgi-swal-title',
    htmlContainer: 'sgi-swal-html',
    confirmButton: 'sgi-swal-confirm',
    cancelButton: 'sgi-swal-cancel',
    actions: 'sgi-swal-actions',
})

const buildToast = () => Swal.mixin({
    toast: true,
    position: 'top-end',
    timer: 3400,
    timerProgressBar: true,
    showConfirmButton: false,
    customClass: buildPopupClasses(),
    buttonsStyling: false,
})

const buildDialog = () => Swal.mixin({
    reverseButtons: true,
    focusCancel: true,
    customClass: buildPopupClasses(),
    buttonsStyling: false,
})

export const notify = ({ icon = 'info', title, text }) => buildToast().fire({ icon, title, text })

export const notifySuccess = (title, text = '') => notify({ icon: 'success', title, text })
export const notifyError = (title, text = '') => notify({ icon: 'error', title, text })
export const notifyInfo = (title, text = '') => notify({ icon: 'info', title, text })
export const notifyWarning = (title, text = '') => notify({ icon: 'warning', title, text })

export const confirmDanger = async ({
    title = 'Confirmar accion',
    text = 'Esta accion no se puede deshacer.',
    confirmText = 'Eliminar',
    cancelText = 'Cancelar',
    icon = 'warning',
} = {}) => {
    const result = await buildDialog().fire({
        icon,
        title,
        text,
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: cancelText,
    })

    return result.isConfirmed
}
