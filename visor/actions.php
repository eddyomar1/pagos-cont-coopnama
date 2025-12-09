<?php
/*********** 3) Acciones CRUD (store/update/delete) ***********/

// EXONERAR CUOTAS PENDIENTES
if ($action === 'exonerar' && $_SERVER['REQUEST_METHOD']==='POST') {
  $id = (int) body('id');
  if ($id <= 0) {
    header('Location:?action=full'); exit;
  }

  $pendientes = cuotas_pendientes_residente_local($pdo, $id, BASE_DUE);
  $fechaHoy = date('Y-m-d');
  $fechaAhora = date('Y-m-d H:i:s');

  try{
    $pdo->beginTransaction();

    // Registrar un pago con monto 0 para marcar meses como exonerados
    $stmt = $pdo->prepare(
      "INSERT INTO pagos_residentes
       (residente_id, fecha_recibo, fecha_pagada, meses_pagados, monto_base, mora, total, observaciones)
       VALUES (?,?,?,?,?,?,?,?)"
    );
    $stmt->execute([
      $id,
      $fechaAhora,
      $fechaHoy,
      json_encode($pendientes, JSON_UNESCAPED_UNICODE),
      0, 0, 0,
      'Exoneración manual desde visor'
    ]);

    // Limpiar montos y moras en la tabla principal y marcar exonerado
    $pdo->prepare(
      "UPDATE residentes
       SET fecha_pagada=?, mora=0, monto_a_pagar=0, monto_pagado=0, deuda_extra=0, exonerado=1, exonerado_desde=?
       WHERE id=?"
    )->execute([$fechaHoy, $fechaAhora, $id]);

    $pdo->commit();
    header('Location:?action=full&exonerado=1'); exit;
  }catch(PDOException $ex){
    $pdo->rollBack();
    $_SESSION['errors'] = ['No se pudo exonerar: '.$ex->getMessage()];
    header('Location:?action=full'); exit;
  }
}

// QUITAR EXONERACIÓN (reactivar cobros futuros)
if ($action === 'desexonerar' && $_SERVER['REQUEST_METHOD']==='POST') {
  $id = (int) body('id');
  if ($id <= 0) {
    header('Location:?action=full'); exit;
  }

  try{
    $stmt = $pdo->prepare("UPDATE residentes SET exonerado=0, exonerado_desde=NULL WHERE id=?");
    $stmt->execute([$id]);
    header('Location:?action=edit&id='.$id.'&desexonerado=1'); exit;
  }catch(PDOException $ex){
    $_SESSION['errors'] = ['No se pudo reactivar la facturación: '.$ex->getMessage()];
    header('Location:?action=edit&id='.$id); exit;
  }
}

// CREATE
if ($action === 'store' && $_SERVER['REQUEST_METHOD']==='POST') {
  $edif_apto         = body('edif_apto');
  $nombres_apellidos = body('nombres_apellidos');
  $cedula_in         = body('cedula');
  $codigo            = body('codigo');
  $telefono          = body('telefono');
  $deuda_inicial     = toDecimal(body('deuda_inicial')) ?? 0;
  $deuda_extra       = toDecimal(body('deuda_extra')) ?? $deuda_inicial;
  $fecha_x_pagar     = toDateOrNull(body('fecha_x_pagar'));
  $fecha_pagada      = toDateOrNull(body('fecha_pagada'));
  $mora              = toDecimal(body('mora')) ?? 0;
  $monto_a_pagar     = toDecimal(body('monto_a_pagar')) ?? 0;
  $monto_pagado      = toDecimal(body('monto_pagado')) ?? 0;
  $no_recurrente     = isset($_POST['no_recurrente']) ? 1 : 0;

  $errors=[];
  if(!required($edif_apto))         $errors[]="Edif/Apto es obligatorio.";
  if(!required($nombres_apellidos)) $errors[]="Nombres y Apellidos es obligatorio.";

  // Cédula opcional
  $cedula_digits = digits_only($cedula_in);
  if($cedula_digits !== '' && !cedula_valida($cedula_digits)) {
    $errors[] = "Cédula no válida.";
  }
  $cedula_db = ($cedula_digits !== '') ? $cedula_digits : null;

  if($errors){
    $_SESSION['errors']=$errors;
    $_SESSION['old']=$_POST;
    header('Location:?action=new'); exit;
  }

  try{
    if (defined('HAS_DEUDA_INICIAL') && HAS_DEUDA_INICIAL) {
      $stmt=$pdo->prepare(
        "INSERT INTO residentes
         (edif_apto,nombres_apellidos,cedula,codigo,telefono,deuda_inicial,deuda_extra,fecha_x_pagar,fecha_pagada,mora,monto_a_pagar,monto_pagado,no_recurrente)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)"
      );
      $stmt->execute([
        $edif_apto,$nombres_apellidos,$cedula_db,$codigo ?: null,$telefono ?: null,
        $deuda_inicial,$deuda_extra,
        $fecha_x_pagar,$fecha_pagada,$mora,$monto_a_pagar,$monto_pagado,$no_recurrente
      ]);
    } else {
      $stmt=$pdo->prepare(
        "INSERT INTO residentes
         (edif_apto,nombres_apellidos,cedula,codigo,telefono,fecha_x_pagar,fecha_pagada,mora,monto_a_pagar,monto_pagado,no_recurrente)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)"
      );
      $stmt->execute([
        $edif_apto,$nombres_apellidos,$cedula_db,$codigo ?: null,$telefono ?: null,
        $fecha_x_pagar,$fecha_pagada,$mora,$monto_a_pagar,$monto_pagado,$no_recurrente
      ]);
    }
    header('Location:?action=full&saved=1'); exit;
  }catch(PDOException $ex){
    $_SESSION['errors']=[ "No se pudo guardar: ".$ex->getCode() ];
    $_SESSION['old']=$_POST; header('Location:?action=new'); exit;
  }
}

// UPDATE
if ($action === 'update' && $_SERVER['REQUEST_METHOD']==='POST') {
  $id                = (int) body('id');

  $edif_apto         = body('edif_apto');
  $nombres_apellidos = body('nombres_apellidos');
  $cedula_in         = body('cedula');
  $codigo            = body('codigo');
  $telefono          = body('telefono');
  $deuda_inicial     = toDecimal(body('deuda_inicial')) ?? 0;
  $deuda_extra       = toDecimal(body('deuda_extra')) ?? $deuda_inicial;
  $fecha_x_pagar     = toDateOrNull(body('fecha_x_pagar'));
  $fecha_pagada      = toDateOrNull(body('fecha_pagada'));
  $mora              = toDecimal(body('mora')) ?? 0;
  $monto_a_pagar     = toDecimal(body('monto_a_pagar')) ?? 0;
  $monto_pagado      = toDecimal(body('monto_pagado')) ?? 0;
  $no_recurrente     = isset($_POST['no_recurrente']) ? 1 : 0;

  $errors=[];
  if($id<=0)                         $errors[]="ID inválido.";
  if(!required($edif_apto))          $errors[]="Edif/Apto es obligatorio.";
  if(!required($nombres_apellidos))  $errors[]="Nombres y Apellidos es obligatorio.";

  // Cédula opcional
  $cedula_digits = digits_only($cedula_in);
  if($cedula_digits !== '' && !cedula_valida($cedula_digits)) {
    $errors[] = "Cédula no válida.";
  }
  $cedula_db = ($cedula_digits !== '') ? $cedula_digits : null;

  if($errors){
    $_SESSION['errors']=$errors;
    $_SESSION['old']=$_POST;
    header('Location:?action=edit&id='.$id); exit;
  }

  try{
    if (defined('HAS_DEUDA_INICIAL') && HAS_DEUDA_INICIAL) {
      $stmt=$pdo->prepare(
        "UPDATE residentes SET
          edif_apto=?, nombres_apellidos=?, cedula=?, codigo=?, telefono=?,
          deuda_inicial=?, deuda_extra=?,
          fecha_x_pagar=?, fecha_pagada=?, mora=?, monto_a_pagar=?, monto_pagado=?, no_recurrente=?
         WHERE id=?"
      );
      $stmt->execute([
        $edif_apto,$nombres_apellidos,$cedula_db,$codigo ?: null,$telefono ?: null,
        $deuda_inicial,$deuda_extra,
        $fecha_x_pagar,$fecha_pagada,$mora,$monto_a_pagar,$monto_pagado,$no_recurrente,$id
      ]);
    } else {
      $stmt=$pdo->prepare(
        "UPDATE residentes SET
          edif_apto=?, nombres_apellidos=?, cedula=?, codigo=?, telefono=?,
          fecha_x_pagar=?, fecha_pagada=?, mora=?, monto_a_pagar=?, monto_pagado=?, no_recurrente=?
         WHERE id=?"
      );
      $stmt->execute([
        $edif_apto,$nombres_apellidos,$cedula_db,$codigo ?: null,$telefono ?: null,
        $fecha_x_pagar,$fecha_pagada,$mora,$monto_a_pagar,$monto_pagado,$no_recurrente,$id
      ]);
    }
    header('Location:?action=full&updated=1'); exit;
  }catch(PDOException $ex){
    $_SESSION['errors']=[ "No se pudo actualizar: ".$ex->getCode() ];
    $_SESSION['old']=$_POST; header('Location:?action=edit&id='.$id); exit;
  }
}

// DELETE
if ($action === 'delete' && isset($_GET['id'])) {
  $id=(int)$_GET['id'];
  if($id>0){
    $pdo->prepare("DELETE FROM residentes WHERE id=?")->execute([$id]);
  }
  header('Location:?action=full&deleted=1'); exit;
}
