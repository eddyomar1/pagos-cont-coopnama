<?php
/** Modo temporal de solo lectura para todo el sitio. */
const MANTENIMIENTO_ACTIVO = true;
if (!MANTENIMIENTO_ACTIVO) {
    return;
}

function mantenimiento_mostrar_error_db(): void
{
    http_response_code(503);
    header('Retry-After: 3600');
    header('Cache-Control: no-store');
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sitio temporalmente no disponible</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;padding:20px;background:#0f172a;font:16px system-ui,sans-serif;color:#1e293b}.popup{max-width:480px;padding:32px;border-radius:18px;background:white;text-align:center;box-shadow:0 24px 80px #0006}p{line-height:1.6;color:#475569}a{font-weight:700;color:#1d4ed8}</style></head><body><main class="popup" role="alertdialog" aria-modal="true"><h1>Servicio temporalmente no disponible</h1><p>No fue posible cargar la información en este momento. Favor contactar con el administrador del sitio web.</p><a href="tel:+18098770862">809-877-0862</a></main></body></html>';
    exit;
}

$metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$ruta = basename($_SERVER['SCRIPT_NAME'] ?? '');
$esBorradoPorGet = $ruta === 'delete.php';

// Rechaza cambios antes de que el controlador pueda tocar la base de datos.
if ($metodo !== 'GET' && $metodo !== 'HEAD' || $esBorradoPorGet) {
    http_response_code(503);
    header('Retry-After: 3600');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Content-Type: text/html; charset=UTF-8');
    ?>
    <!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sitio en solo lectura</title>
    <style>*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:20px;background:#0f172a;color:#1e293b;font-family:system-ui,sans-serif}.popup{width:min(100%,480px);padding:32px;border-radius:18px;background:#fff;box-shadow:0 24px 80px #0006;text-align:center}h1{font-size:23px}p{color:#475569;line-height:1.6}a{color:#1d4ed8;font-weight:700}</style></head>
    <body><main class="popup" role="alertdialog" aria-modal="true"><h1>Acceso de solo lectura</h1><p>Esta acción está temporalmente deshabilitada. Para recibir asistencia, favor contactar con el administrador del sitio web.</p><p><a href="tel:+18098770862">809-877-0862</a></p></main></body></html>
    <?php
    exit;
}

// Las páginas GET conservan sus datos detrás del popup, difuminados y sin interacción.
ob_start(static function (string $html): string {
    if (stripos($html, '</body>') === false) {
        return $html;
    }
    $overlay = <<<'HTML'
<style>
html.maintenance-active,html.maintenance-active body{overflow:hidden!important}
html.maintenance-active body>*:not(#maintenance-overlay){filter:blur(7px)!important;pointer-events:none!important;user-select:none!important}
#maintenance-overlay{position:fixed;inset:0;z-index:2147483647;display:grid;place-items:center;padding:20px;background:rgba(15,23,42,.42);font-family:system-ui,-apple-system,"Segoe UI",sans-serif;color:#1e293b}
#maintenance-overlay *{box-sizing:border-box}
#maintenance-overlay .maintenance-popup{width:min(100%,480px);padding:32px;border-radius:18px;background:#fff;box-shadow:0 24px 80px rgba(0,0,0,.35);text-align:center}
#maintenance-overlay h2{margin:0 0 12px;font-size:23px}
#maintenance-overlay p{margin:0;color:#475569;line-height:1.6}
#maintenance-overlay a{color:#1d4ed8;font-weight:700}
@media(max-width:480px){#maintenance-overlay .maintenance-popup{padding:24px 20px}}
</style>
<script>document.documentElement.classList.add('maintenance-active');</script>
<div id="maintenance-overlay" role="alertdialog" aria-modal="true" aria-labelledby="maintenance-title" aria-describedby="maintenance-message">
  <section class="maintenance-popup"><h2 id="maintenance-title">Acceso de solo lectura</h2><p id="maintenance-message">Puedes consultar la información de esta página. Las modificaciones están temporalmente deshabilitadas. Para recibir asistencia, favor contactar con el administrador del sitio web.</p><p style="margin-top:16px"><a href="tel:+18098770862">809-877-0862</a></p></section>
</div>
HTML;
    return preg_replace('~</body>~i', $overlay . '</body>', $html, 1) ?? $html;
});
