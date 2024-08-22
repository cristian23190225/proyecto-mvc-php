<?php

namespace Controllers;

use Classes\Email;
use MVC\Router;
use Model\Usuario;


class LoginController {

    public static function login(Router $router) {
        $alertas = [];

        if($_SERVER['REQUEST_METHOD'] === 'POST') {
            $auth = new Usuario($_POST);
            $alertas = $auth->validarLogin();

            if(empty($alertas)) {
                // Comprobar que exista el usuario
                $usuario = Usuario::buscarPorCampo('email', $auth->email);

                if($usuario) {
                    // Verificar el password
                    if( $usuario->comprobarContrasenaAndVerificado($auth->password) ) {
                        // Autenticar el usuario
                        session_start();

                        $_SESSION['id'] = $usuario->id;
                        $_SESSION['nombre'] = $usuario->nombre . " " . $usuario->apellido;
                        $_SESSION['email'] = $usuario->email;
                        $_SESSION['login'] = true;

                        // Redireccionamiento
                        if($usuario->admin === 1) {
                            $_SESSION['admin'] = $usuario->admin ?? null;
                            header('Location: /admin');
                        } else {
                            header('Location: /cliente');
                        }
                    }
                } else {
                    Usuario::setAlerta('error', 'Usuario no encontrado');
                }

            }
        }

        $alertas = Usuario::getAlertas();
        
        $router->render('auth/login', [
            'alertas' => $alertas
        ]);
    }

    public static function admin() {
        echo 'Desde admin';
    }

    public static function cliente() {
        echo 'Desde cliente';
    }

    public static function logout() {
        echo 'Desde logout';
    }


    public static function olvide(Router $router) {
        //echo 'Desde olvide';
        $alertas = [];

        if($_SERVER['REQUEST_METHOD'] === 'POST') {
            $auth = new Usuario($_POST);

            $alertas = $auth->validarEmail();

            if (empty($alertas)) {
                $usuario = Usuario::buscarPorCampo('email', $auth->email);
                //debuguear($usuario);

                if ($usuario && $usuario->confirmado == 1) {
                    //debuguear('Si existe y esta confirmado');
                    //Genera un token
                    $usuario->crearToken();
                    $usuario->guardar();
                    
                    //Enviar email
                    $email= new Email(
                        $usuario->email,
                        $usuario->nombre,
                        $usuario->token
                    );
                    $email->enviarInstrucciones();

                    //TODO: enviar email
                    Usuario::setAlerta('exito', 'Revisa tu correo');
                }else {
                    //debuguear('No existe o no esta confirmado');
                    Usuario::setAlerta('error', 'El usuario no existe o no esta confirmado');
                    //$alertas = Usuario::getAlertas();
                }
            }
        }
        $alertas = Usuario::getAlertas();
        $router->render('auth/olvide-password', [
            'alertas' => $alertas
        ]);
    }

    public static function recuperar(Router $router) {
        //echo 'Desde recuperar';

        $alertas = [];
        $error = false;

        $token = s($_GET['token']);
        //Buscar usuaio por token
        $usuario = Usuario::buscarPorCampo('token', $token);
        
        if(empty($usuario)) {
            Usuario::setAlerta('error', 'Token no valido');
            $error = true;
        }

        if($_SERVER['REQUEST_METHOD'] === 'POST') {

            //Leer el nuevo password y guardalo
            $password = new Usuario($_POST);
            $alertas = $password->validarPassword();
            //debuguear($password);

            if(empty($alertas)) {
                $usuario->password = null;

                $usuario->password = $password->password;
                $usuario->hashPassword();
                $usuario->token = null;

                $resultado = $usuario->guardar();
                if($resultado) {
                    header('Location: /');
                }
                //debuguear($usuario);
            }
        }

        $alertas = Usuario::getAlertas();

        $router->render('auth/recuperar-password',[
            'alertas' => $alertas,
            'error' => $error
        ]);
    }

    public static function crear(Router $router) {
        
        $usuario = new Usuario;
        //alertas vacia
        $alertas = [];

        if($_SERVER['REQUEST_METHOD'] === 'POST') {

            $usuario->sincronizar($_POST);
            $alertas = $usuario->validarNuevaCuenta();
            

            //Revisar que alerta este vacia
            if (empty($alertas)) {

                //Verifica que el usuario no este registrado
                $resultado = $usuario->existeUsuario();
                

                if($resultado->num_rows){
                    $alertas = Usuario::getAlertas();
                }else {
                    //Hashear el password
                    $usuario->hashPassword();

                    //Geberar un Token unico
                    $usuario->crearToken();

                    //debuguear($usuario);
                    
                    //Enviar el imal
                    $email = new Email($usuario->email, $usuario->nombre, $usuario->token);
                    //debuguear($email);
                    $email->enviarConfirmacion();
                   
                    //Crear el usuario 
                    $resultado = $usuario->guardar();
                    debuguear($resultado);
                    //debuguear($usuario);

                    if($resultado) {
                        header('Location: /mensaje');
                    }


                }

            }
        }
        $router->render('auth/crear-cuenta', [
            'usuario' => $usuario,
            'alertas' => $alertas
        ]);
    }

    public static function confirmar(Router $router) {
        $alertas = [];

        $token = s ($_GET['token']);

        //debuguear($token);

        $usuario = Usuario::buscarPorCampo('token', $token);
        
        if(empty($usuario)) {
            // echo 'Token no valido';
            Usuario::setAlerta('error', 'Token no valido');
        }else {
            //Modificar a usuario confirmado
            // echo 'Token valido, confirmando usuario...';

            $usuario->confirmado = '1';
            $usuario->token = '';

            // debuguear($usuario);

            $usuario->guardar();
            Usuario::setAlerta('exito', 'cuenta comprobada correctamente');

        }

        //Obtener alertas
        $alertas = Usuario::getAlertas();

        $router->render('auth/confirmar-cuenta', [
            'alertas' => $alertas
        ]);
    }

    public static function mensaje(Router $router) {
        
        $router->render('auth/mensaje');
    }
}