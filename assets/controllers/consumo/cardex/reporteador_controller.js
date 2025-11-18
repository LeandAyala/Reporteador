import mensajes from '../../central/mensajes';
import { Controller } from "@hotwired/stimulus";

export default class extends Controller 
{
    mensaje = new mensajes();

    static values = {};
    static targets = [];

    connect()
    {
        var self = this;
        console.log('connect');
        $('.selectpicker').selectpicker('refresh');
    }
}   