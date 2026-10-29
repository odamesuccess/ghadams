import Swal from 'sweetalert2'

export function useAlert() {
    const success = (message: string) =>
        Swal.fire('Success', message, 'success')

    const error = (message: string) =>
        Swal.fire('Error', message, 'error')

    const confirm = async (text: string) => {
        const result = await Swal.fire({
            title: 'Are you sure?',
            text,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, continue'
        })

        return result.isConfirmed
    }

    return { success, error, confirm }
}
