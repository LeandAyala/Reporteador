import mensajes from '../central/mensajes';
import { Controller } from "@hotwired/stimulus";

export default class extends Controller 
{
    grafica = null;
    estadoResumen = 0;
    mensaje = new mensajes();

    static values = {};
    static targets = [];

    connect()
    {
        var self = this;
        console.log('connect');
        $('.selectpicker').selectpicker('refresh');
    }

    showResumen()
    {
        /** En esta opción se visualiza/oculta el resumen de los grupos contables */
        /** --------------------------------------------------------------------- */

        if(this.estadoResumen == 0)
        {
            this.estadoResumen = 1;
            if(this.grafica !== null){this.grafica.destroy()}
            $('#resumenGruposContables').css('left', '-260px');
            let divGrafica = document.getElementById('grafica').getContext('2d');
            let grafica = new Chart(divGrafica, 
            {
                type: 'doughnut',
                data:
                {
                    labels: ['7-Dermatocosmético', '8-Consignados', '9-Papelería', '10-Dermatocosmético', '11-Consignados', '12-Papelería'],
                    datasets: [{
                        labels: 'Existencias',
                        data: [9900, 10420, 12240, 2160, 2400, 2880],
                        backgroundColor:
                        [
                            '#28a745',
                            '#dc3545',
                            '#17A',
                            '#FFC107',
                            '#007BFF',
                            'gray'
                        ]

                    }]
                },
                options: 
                {
                    responsive: true,
                    legend: {display: false},
                    plugins: 
                    {
                        legend: 
                        {
                            display: false,
                            position: 'right',
                        }
                    }
                }   
            });
            this.grafica = grafica;
        }
        else
        {
            this.estadoResumen = 0;
            $('#resumenGruposContables').css('left', '-6px');
            setTimeout(() => {grafica.innerHTML = ''; this.grafica.destroy()}, 600);
        }
    }
}   