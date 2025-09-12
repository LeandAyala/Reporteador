<?php

namespace App\Controller;

use PHPShopify\ShopifySDK;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ShopifyController extends AbstractController
{
    public function index()
    {
        
        $shopify = new ShopifySDK($configuraciones);

        try 
        {
            $index = 1;
            $result = [];
            $page_info = '';
            $productos = $shopify->Product();
            while(true) 
            {
                $params = 
                [
                    'limit' => '100',
                    'page_info' => $page_info
                ];
                $items  = $productos->get($params);
                $result = array_merge($result, $items);
                $nextPageParams = $productos->getNextPageParams();
                $page_info = !empty($nextPageParams['page_info'])?$nextPageParams['page_info']:null;
                //if($index == ceil($productos->count()/100)){break;}
                if($index == 1){break;}
                //if(is_null($page_info)){break;}
                $index ++;
            }
            dump($productos->count());
            dd($result);

            /*$productos = $shopify->Product();
            $listProductos = $productos->get(['limit' => '250']);
            dump($productos->count());
            dd($productos->getNextPageParams());
            $dataProducto = 
            [
                'tags' => 'producto',
                'vendor' => 'producto-creado',
                'body-html' => '<p>Producto creado</p>',
                'title' => 'Producto creado - actualizado',
                'variants' => [['sku' => 'PC-01', 'price' => '21000.00']] 
            ];*/

            //$producto = $shopify->Product(9768345960741)->put($dataProducto);
            //$productos = $shopify->Product(9767706788133)->get();
            /*$variant = $shopify->ProductVariant(50202546143525)->get();
            $variant['price'] = '52000';
            $productoActualizado = $shopify->Product('9767706788133')->put(['variants' => ['price' => '52000.00']]);*/
            //dd($producto);
        } 
        catch(\Exception $e) 
        {
            dd($e->getMessage());
        }

        return $this->render('shopify/index.html.twig', 
        [
            'controller_name' => 'ShopifyController',
        ]);
    }
}
