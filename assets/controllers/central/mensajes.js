export default class
{
    mostrarMensaje(msg, tipo, duracion = '5000')
    {
        /** Configuración de los mensajes toastr */
        /** ------------------------------------ */
        
        toastr.options = {
            "debug": false,
            "onclick": null,
            "timeOut": duracion,
            "closeButton": true,
            "progressBar": false,
            "newestOnTop": false,
            "showDuration": "300",
            "showEasing": "swing",
            "hideDuration": "1000",
            "hideEasing": "linear",
            "showMethod": "fadeIn",
            "hideMethod": "fadeOut",
            "extendedTimeOut": "1000",
            "preventDuplicates": true,
            "positionClass": "toast-top-right"
        }

        if(tipo == 1)
        {
            toastr.success(msg);
        }
        else
        {
            toastr.error(msg);
        }
    }
}