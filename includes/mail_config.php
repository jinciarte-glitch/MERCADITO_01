<?php
// includes/mail_config.php — configuración del envío de correos.
//
// ⚠️ ESTE ES EL ÚNICO ARCHIVO QUE TENÉS QUE TOCAR PARA QUE LLEGUEN LOS MAILS.
//
// Los hostings gratuitos (byethost / InfinityFree) suelen tener la función
// mail() de PHP desactivada. Por eso el sistema manda los correos por SMTP,
// usando una cuenta tuya (Gmail, Brevo, Mailtrap, el SMTP del hosting, etc.).
// Ver LEEME-verificacion.md para el paso a paso de cada proveedor.

// ---------------------------------------------------------------------------
// 1) Datos del remitente (lo que ve quien recibe el mail)
// ---------------------------------------------------------------------------
const MAIL_FROM_EMAIL = 'mercadito.support@gmail.com';
const MAIL_FROM_NOMBRE = 'Mercadito';

// ---------------------------------------------------------------------------
// 2) Servidor SMTP
// ---------------------------------------------------------------------------
// Dejá MAIL_SMTP_HOST en '' para que use mail() de PHP en lugar de SMTP.
//
// Gmail  → host: smtp.gmail.com     puerto: 587  seguridad: 'tls'
//          (la contraseña NO es la de tu cuenta: hay que crear una
//           "Contraseña de aplicación" en la config de seguridad de Google)
// Brevo  → host: smtp-relay.brevo.com  puerto: 587  seguridad: 'tls'
// Mailtrap (pruebas) → host: sandbox.smtp.mailtrap.io  puerto: 587  'tls'
const MAIL_SMTP_HOST = 'smtp.gmail.com';
const MAIL_SMTP_PUERTO = 587;
const MAIL_SMTP_USUARIO = 'mercadito.support@gmail.com';
const MAIL_SMTP_PASSWORD = 'xgjv scsg xmix qcmv';
const MAIL_SMTP_SEGURIDAD = 'tls'; // 'tls' (587), 'ssl' (465) o '' (sin cifrado)
const MAIL_SMTP_TIMEOUT = 20;      // segundos


// ---------------------------------------------------------------------------
// 3) Dirección pública del sitio
// ---------------------------------------------------------------------------
// Se usa para armar el link de activación del correo. Si lo dejás en '' se
// detecta solo a partir del dominio con el que entró el usuario, que funciona
// bien en la mayoría de los casos.
const SITIO_URL = '';

// ---------------------------------------------------------------------------
// 4) Opciones de la verificación
// ---------------------------------------------------------------------------
const VERIF_MINUTOS_VALIDEZ = 60;   // cuánto dura el link / código
const VERIF_INTENTOS_MAXIMOS = 6;   // intentos de código por cada mail enviado
const VERIF_SEGUNDOS_ENTRE_ENVIOS = 60;  // espera mínima para "Reenviar"
const VERIF_ENVIOS_MAXIMOS_POR_HORA = 5;

// Modo desarrollo: si está en true y el mail NO se pudo enviar, la pantalla de
// verificación muestra el link y el código en pantalla para poder seguir
// probando. DEJALO EN false EN PRODUCCIÓN.
const VERIF_MOSTRAR_CODIGO_SI_FALLA = false;

// ---------------------------------------------------------------------------
// 5) Opciones de "Olvidé mi contraseña"
// ---------------------------------------------------------------------------
const RECUP_MINUTOS_VALIDEZ = 60;       // cuánto dura el link para elegir una contraseña nueva
const RECUP_SEGUNDOS_ENTRE_ENVIOS = 60; // espera mínima para pedirlo de nuevo
const RECUP_ENVIOS_MAXIMOS_POR_HORA = 5;

// Mismo modo desarrollo que arriba, pero para el link de "Olvidé mi
// contraseña": si el correo no sale, la pantalla muestra el link en pantalla.
const RECUP_MOSTRAR_LINK_SI_FALLA = false;
