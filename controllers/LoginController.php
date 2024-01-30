<?php

namespace Controllers;

use MVC\Router;
use Classes\Email;
use Model\Usuario;

class LoginController {
    public static function login(Router $router) {
        $alertas = [];
        $auth = new Usuario;

        if($_SERVER['REQUEST_METHOD'] ==='POST') {
            $auth = new Usuario($_POST);
            $alertas = $auth->validarLogin();

            if(empty($alertas)) {
                $usuario = Usuario::where('email', $auth->email); // Comprobar que el usuario exista
                if($usuario) {
                    // Verificar el password
                    if($usuario->comprobarPasswordAndVerificado($auth->password)) {
                        // Autenticar el usuario
                        session_start();
                        $_SESSION['id'] = $usuario->id;
                        $_SESSION['nombre'] = $usuario->nombre . " " . $usuario->apellido;
                        $_SESSION['email'] = $usuario->email;
                        $_SESSION['login'] = true;

                        // Redireccionamiento
                        if($usuario->admin === "1") {
                            $_SESSION['admin'] = $usuario->admin ?? null;
                            header('Location: /admin');
                        } else {
                            header('Location: /cita');
                        }
                    }
                } else {
                    Usuario::setAlerta('error', 'Usuario no encontrado');
                }
            }
        }

        $alertas = Usuario::getAlertas();

        $router->render('auth/login', [
            'alertas'=>$alertas,
            'auth'=>$auth
        ]);
    }

    public static function logout() {
        session_start();
        $_SESSION = [];
        header('Location: /');
    }

    public static function olvide(Router $router) {
        $alertas = [];

        if($_SERVER['REQUEST_METHOD'] ==='POST') {
            $auth = new Usuario($_POST);
            $alertas = $auth->validarEmail();

            if(empty($alertas)) {
                $usuario = Usuario::where('email', $auth->email); // Comprobar que el usuario exista
                if($usuario && $usuario->confirmado === '1') {
                    $usuario->crearToken(); // Generar un token
                    $usuario->guardar();

                    $email = new Email($usuario->email, $usuario->nombre, $usuario->token); // Enviar email
                    $email->enviarInstrucciones();
                    Usuario::setAlerta('exito', 'Revisa tu email'); // Alerta de éxito
                } else {
                    Usuario::setAlerta('error', 'El  usuario no existe o no está confirmado');
                }   
            }
        }

        $alertas = Usuario::getAlertas();

        $router->render('auth/olvide', [
            'alertas'=>$alertas
        ]);
    }

    public static function recuperar(Router $router) {
        $alertas = []; // Alertas vacías
        $error = false;

        $token = s($_GET['token']);

        $usuario = Usuario::where('token', $token); // Buscar usuario por su token

        if(empty($usuario)) {
            Usuario::setAlerta('error', 'Token no válido'); // Mostrar mensaje de error
            $error = true;
        }
        
        if($_SERVER['REQUEST_METHOD'] ==='POST') {
            $password = new Usuario($_POST); // Leer nuevo password y guardarlo
            $alertas = $password->validarPassword();

            if(empty($alertas)) {
                $usuario->password = null;
                $usuario->password = $password->password;
                $usuario->hashPassword();
                $usuario->token = null;
                $resultado = $usuario->guardar();
                if($resultado) {
                    header('Location: /');
                }    
            }
        }

        $alertas = Usuario::getAlertas();

        $router->render('auth/recuperar', [
            'alertas'=>$alertas, 
            'error'=>$error
        ]);
    }

    public static function crear(Router $router) {
        $usuario = new Usuario;
        $alertas = []; // Alertas vacías

        if($_SERVER['REQUEST_METHOD'] === 'POST') {
            $usuario->sincronizar($_POST);
            $alertas = $usuario->validarNuevaCuenta();

            // Revisar que alerta esté vacío
            if(empty($alertas)) {
                $resultado = $usuario->existeUsuario(); // Verificar que el usuario no esté registrado
                if($resultado->num_rows) {
                    $alertas = Usuario::getAlertas();
                } else {
                    $usuario->hashPassword(); // Hashear password
                    $usuario->crearToken(); // Generar un token único
                    $email = new Email($usuario->email, $usuario->nombre, $usuario->token); // Enviar email
                    $email->enviarConfirmacion();
                    $resultado = $usuario->guardar(); // Crear el usuario
                    if($resultado) {
                        header('Location: /mensaje');
                    }
                }
            }
        }

        $router->render('auth/crear-cuenta', [
            'usuario'=>$usuario,
            'alertas'=>$alertas
        ]);
    }

    public static function mensaje(Router $router) {
        $router->render('auth/mensaje');
    }

    public static function confirmar(Router $router) {
        $alertas = [];
        $token = s($_GET['token']);

        $usuario = Usuario::where('token', $token);

        if(empty($usuario)) {
            Usuario::setAlerta('error', 'Token no válido'); // Mostrar mensaje de error
        } else {
            $usuario->confirmado = "1"; // Modificar a usuario confirmado
            $usuario->token = ""; // Que se borre el token
            $usuario->guardar();
            Usuario::setAlerta('exito', 'Cuenta comprobada correctamente'); // Mostrar mensaje de error
        }

        $alertas = Usuario::getAlertas();
        $router->render('auth/confirmar-cuenta', [
            'alertas'=>$alertas
        ]);
    }
}