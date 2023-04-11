<?php
class DoofinderCallbackModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            header('Content-Type: application/json');
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: POST, GET, OPTIONS, DELETE, PUT');
            header('Access-Control-Allow-Headers: x-requested-with, Content-Type, origin, authorization, accept, client-security-token');

            // Leer el cuerpo de la petición POST
            $request_body = file_get_contents('php://input');

            // Procesar el cuerpo de la petición POST aquí
            // ...

            die(json_encode(['status' => 'success']));
        } else {
            http_response_code(405); // Método no permitido
            die();
        }
    }

    public function initContent()
    {
        parent::initContent();
    }
}