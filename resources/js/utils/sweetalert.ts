import Swal from 'sweetalert2'

export const toast = Swal.mixin({
  toast: true,
  position: 'top-end',
  showConfirmButton: false,
  timer: 3000,
  timerProgressBar: true,
})

const modalBase = {
  background: '#181b22',
  color: '#f8fafc',
  width: 510,
  reverseButtons: true,
  buttonsStyling: false,
  allowOutsideClick: false,
  allowEscapeKey: true,
  focusCancel: true,
  iconColor: '#fdba74',
  customClass: {
    popup: 'rounded-xl border border-slate-700 shadow-2xl',
    title: 'text-3xl font-bold text-slate-100',
    htmlContainer: 'text-base leading-7 text-slate-300',
    actions: 'mt-6 flex gap-3',
    cancelButton: 'min-w-32 rounded-md bg-slate-600 px-8 py-3 text-base font-bold text-white hover:bg-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-400',
    confirmButton: 'min-w-32 rounded-md bg-red-600 px-8 py-3 text-base font-bold text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500',
  },
} as const

export const confirmDelete = async (options?: {
  title?: string
  text?: string
  confirmText?: string
}) => {
  const result = await Swal.fire({
    ...modalBase,
    title: options?.title ?? 'Delete',
    text: options?.text ?? 'Are you sure you want to delete this item? This action cannot be undone.',
    icon: 'warning',
    showCancelButton: true,
    cancelButtonText: 'Cancel',
    confirmButtonText: options?.confirmText ?? 'Delete',
  })

  return result.isConfirmed
}

export const confirmLogout = async () => {
  const result = await Swal.fire({
    ...modalBase,
    title: 'Sign Out',
    text: 'Are you sure you want to sign out? You will need to sign in again to continue using the system.',
    icon: 'warning',
    showCancelButton: true,
    cancelButtonText: 'Cancel',
    confirmButtonText: 'Logout',
  })

  return result.isConfirmed
}

export const confirmAction = async (options?: {
  title?: string
  text?: string
  confirmText?: string
  icon?: 'warning' | 'info' | 'question'
}) => {
  const result = await Swal.fire({
    ...modalBase,
    title: options?.title ?? 'Are you sure?',
    text: options?.text ?? 'Please confirm that you want to continue.',
    icon: options?.icon ?? 'warning',
    showCancelButton: true,
    cancelButtonText: 'Cancel',
    confirmButtonText: options?.confirmText ?? 'Continue',
  })

  return result.isConfirmed
}

export const promptText = async (options: {
  title: string
  text?: string
  placeholder?: string
  confirmText?: string
  required?: boolean
}) => {
  const result = await Swal.fire({
    ...modalBase,
    title: options.title,
    text: options.text,
    icon: 'question',
    input: 'text',
    inputPlaceholder: options.placeholder ?? '',
    showCancelButton: true,
    cancelButtonText: 'Cancel',
    confirmButtonText: options.confirmText ?? 'Continue',
    inputValidator: options.required
      ? (value) => (!String(value ?? '').trim() ? 'This field is required.' : undefined)
      : undefined,
    customClass: {
      ...modalBase.customClass,
      input: 'mx-auto w-[82%] rounded-md border border-slate-600 bg-slate-950 px-3 py-2 text-slate-100 outline-none focus:border-blue-500',
    },
  })

  return result.isConfirmed ? String(result.value ?? '') : null
}

export const showError = (title: string, text?: string) =>
  Swal.fire({
    ...modalBase,
    title,
    text,
    icon: 'error',
    showCancelButton: false,
    confirmButtonText: 'OK',
  })
