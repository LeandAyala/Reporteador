<?php

namespace App\Controller;

use Smalot\PdfParser\Parser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class LoginController extends AbstractController
{
    public function login()
    {   
        $ruta = $this->getParameter('imgs_directory');
        $factura = base64_encode(file_get_contents($ruta.'factura.jpg'));
        return $this->render('login\login.html.twig');
    }

    public function cargarFactura(Request $request)
    {
        $data = [];
        $result = [];
        $message = '';
        $status = 'success';
        $parser = new Parser();
        $archivo = $request->files->get('factura');
        $factura = base64_encode(file_get_contents($archivo));
        $tipoBusqueda = ($archivo->getMimeType() == 'application/pdf')?'texto':'archivo';
        $prompt = 'Procesa el siguiente '.$tipoBusqueda.' y valida si su estructura corresponde a una factura. Si es así, retorna el json {"status" : "success", "data" : arreglo con la siguiente información de la lista de productos: producto, cantidad, valor y total (cantidad * valor)}; de lo contrario, retorna el json {"status" : "error", "message" : "El archivo seleccionado no corresponde a una factura", "data" : []}. Es importante que los valores queden sin formato, respetando únicamente el punto decimal; si existen puntos diferentes al decimal debes quitarlos. El json debe ser limpio para que sea fácil decodificarlo. Quiero que en el content no pongas ninguna aclaración, solo el json. Además, es importante que analices y retornes absolutamente todas las líneas de productos disponibles, no importa si un producto está duplicado mas de una vez.';

        /** Se valida la estructura de la petición de acuerdo al tipo de archivo */
        /** -------------------------------------------------------------------- */

        if($archivo->getMimeType() == 'application/pdf')
        {
            $pdf = $parser->parseFile($archivo->getPathname());
            /*$prompt = 'Extrae del siguiente texto absolutamente todas las líneas de productos. Si hay resultados retorna el siguiente json {"status" : "success", "data" : arreglo con la siguiente información de la lista de productos: producto, cantidad, valor y total (cantidad * valor)}; de lo contrario, retorna el json {"status" : "error", "message" : "No se encontraron productos para listar", "data" : []}. Es importante que los valores queden sin formato, respetando únicamente el punto decimal; si existen puntos diferentes al decimal debes quitarlos. Retorna la totalidad de productos sin omitir duplicados y dime cuantos productos se encontraron';
            dump(str_replace("\n", '', $pdf->getText()));
            dump($pdf->getText());
            $data = 
            [
                'model' => 'gpt-4o',
                'messages' => 
                [
                    [
                        'role' => 'system',
                        'content' => 'Eres un asistente experto en procesamiento de facturas. Siempre devuelves un JSON limpio, sin explicaciones, encabezados, ni texto adicional.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt."\n".str_replace("\n", '', $pdf->getText())
                    ]
                ]
            ];*/
            $data = 
            [
                'model' => 'gpt-4o',
                'messages' => 
                [
                    [
                        'role' => 'user',
                        'content' => $prompt."\n".str_replace("\n", '', $pdf->getText())
                    ]
                ]
            ];
        }
        else
        {
            $data = 
            [
                'model' => 'gpt-4o',
                'messages' => 
                [
                    [
                        'role' => 'user',
                        'content' => 
                        [
                            [
                                'type' => 'text',
                                'text' => $prompt
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => 
                                [
                                    'url' => 'data:'.$archivo->getMimeType().';base64,'.$factura
                                ]
                            ]
                        ]
                    ]
                ],
                'max_tokens' => 5000,
                'temperature' => 0,
            ];
        }

        /** Se realiza la petición al API de Opena AI */
        /** ----------------------------------------- */

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, 
        [
            'Content-Type: application/json',
            'Authorization: Bearer '.$apiKey
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $response = curl_exec($ch);
        if(curl_errno($ch)) 
        {
            $status = 'error';
        }
        else 
        {
            $result = json_decode($response, true);
        }
        curl_close($ch);

        /** Se obtiene la respuesta generada por el API */
        /** ------------------------------------------- */

        if(!empty($result))
        {
            if(!array_key_exists('error', $result))
            {
                $content = trim($result['choices'][0]['message']['content']);
                $content = preg_replace('/^```json|```$/m', '', $content);
                $content = trim($content);
                $result = json_decode($content, true);
                if(is_array($result) && array_key_exists('status', $result))
                {
                    if($result['status'] == 'error')
                    {
                        $status = 'error';
                        $message = $result['message'];
                    }
                    $data = $result;
                }
                else
                {
                    $status = 'error';
                    $message = '¡La imagen seleccionada no corresponde a una factura!';
                }
            }
            else
            {
                $status = 'error';
                $message = $result['error']['message'];
            }
        }
        return new Response(json_encode(['status' => $status, 'message' => $message, 'data' => $data]));
    }

    public function frameFactura(Request $request)
    {
        /** 
         * En esta función se genera la factura
         * ------------------------------------
         * @access public
        */

        $factura = json_decode($request->getContent(), true);
        return $this->render('facturas/frameFactura.html.twig', ['factura' => $factura]);
    }
}
