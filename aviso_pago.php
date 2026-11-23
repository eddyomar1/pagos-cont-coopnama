<?php
require_once __DIR__ . '/mantenimiento.php';
// Aviso general del sistema. Fechas comunes para todos los usuarios y pantallas.
function coopnama_render_payment_notice(): void
{
    $inicio = new DateTimeImmutable('2026-11-16T00:00:00-04:00');
    $vencimiento = $inicio->modify('+7 days');
    $restanteMs = max(0, (int) round(($vencimiento->getTimestamp() - microtime(true)) * 1000));
    $segundos = (int) ceil($restanteMs / 1000);
    $contador = sprintf(
        '%d días %02d:%02d:%02d',
        intdiv($segundos, 86400),
        intdiv($segundos % 86400, 3600),
        intdiv($segundos % 3600, 60),
        $segundos % 60
    );
?>
<style>
 .coopnama-payment-notice{
   box-sizing:border-box;display:flex;flex-wrap:wrap;align-items:center;gap:18px;
   margin:0 0 24px;padding:20px;border:1px solid #f5bd60;border-left:5px solid #b45309;
   border-radius:12px;background:#fffbeb;color:#78350f;font-family:system-ui,sans-serif;
   font-size:15px;line-height:1.5;overflow-wrap:anywhere;
 }
 .coopnama-payment-notice__message{flex:1 1 300px;min-width:0;}
 .coopnama-payment-notice__title{display:block;margin-bottom:6px;font-size:17px;}
 .coopnama-payment-notice p{margin:0 0 10px;}
 .coopnama-payment-notice a{color:#78350f;font-weight:700;text-decoration:underline;}
 .coopnama-payment-notice a:focus-visible{outline:2px solid #78350f;outline-offset:4px;}
 .coopnama-payment-notice__countdown{
   flex:0 1 260px;min-width:0;padding:14px 18px;border-radius:8px;background:#fef3c7;
 }
 .coopnama-payment-notice__timer{display:block;font-size:23px;font-weight:700;font-variant-numeric:tabular-nums;}
 .coopnama-payment-notice__deadline{display:block;margin-top:4px;font-size:12px;}
 @media (max-width:992px){
   .mobile-menu-fab ~ .content .coopnama-payment-notice{margin-top:52px;}
 }
 @media (max-width:576px){
   .coopnama-payment-notice{padding:16px;gap:14px;}
   .coopnama-payment-notice__countdown{flex-basis:100%;}
 }
 @media print{.coopnama-payment-notice{display:none !important;}}
</style>
<aside class="coopnama-payment-notice" aria-label="Aviso de atraso del sistema de pago">
  <div class="coopnama-payment-notice__message">
    <strong class="coopnama-payment-notice__title">Aviso de atraso</strong>
    <p>Su sistema de pago de su cuenta de Residencial COOPNAMA II presenta un atraso. Favor contactarse con su administrador lo antes posible.</p>
    <span>Contacto: <a href="tel:+18098770862">809-877-0862</a></span>
  </div>
  <div class="coopnama-payment-notice__countdown" data-payment-remaining-ms="<?= $restanteMs ?>">
    <span>Tiempo restante</span>
    <span class="coopnama-payment-notice__timer" role="timer" aria-live="off"><?= $contador ?></span>
    <small class="coopnama-payment-notice__deadline">Días · horas:minutos:segundos</small>
    <small class="coopnama-payment-notice__deadline">Vence el <time datetime="<?= $vencimiento->format('c') ?>">23 de noviembre de 2026, 12:00 a. m.</time> (hora de Santo Domingo).</small>
    <p class="coopnama-payment-notice__expired" role="status"<?= $restanteMs > 0 ? ' hidden' : '' ?>>Plazo vencido. Contacte a su administrador.</p>
  </div>
</aside>
<script>
(function () {
  var script = document.currentScript;
  var notice = script.previousElementSibling;
  var countdown = notice.querySelector('[data-payment-remaining-ms]');
  var timer = countdown.querySelector('[role="timer"]');
  var expired = countdown.querySelector('[role="status"]');
  // El servidor fija el plazo; no depende del reloj ni del almacenamiento del usuario.
  var remainingMs = Number(countdown.getAttribute('data-payment-remaining-ms'));
  var startedAt = performance.now();
  var interval;

  function pad(value) { return String(value).padStart(2, '0'); }
  function update() {
    var seconds = Math.max(0, Math.ceil((remainingMs - (performance.now() - startedAt)) / 1000));
    var days = Math.floor(seconds / 86400);
    var hours = Math.floor((seconds % 86400) / 3600);
    var minutes = Math.floor((seconds % 3600) / 60);
    timer.textContent = days + (days === 1 ? ' día ' : ' días ') + pad(hours) + ':' + pad(minutes) + ':' + pad(seconds % 60);
    if (seconds === 0) {
      expired.hidden = false;
      clearInterval(interval);
      document.removeEventListener('visibilitychange', update);
    }
  }

  if (remainingMs > 0) {
    interval = setInterval(update, 1000);
    document.addEventListener('visibilitychange', update);
  }
  update();
})();
</script>
<?php
}
