<?php
declare(strict_types=1);
?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Motor de decisión AHP — Planificación metodológica de software</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
  <div class="brand"><div class="brand-mark">AHP</div><div><strong>Motor de decisión</strong><small>Selección metodológica de proyectos de software</small></div></div>
  <div class="top-actions"><span class="pill">WAMP · PHP + MySQL</span><button class="btn btn-ghost" id="btnHistory">Historial</button></div>
</header>
<main class="container">
  <section class="intro">
    <div><p class="eyebrow">PROTOTIPO ACADÉMICO · OBJETIVO ESPECÍFICO 3</p><h1>Evaluación multicriterio de proyectos de software</h1><p class="lead">Integra el cálculo de prioridades AHP con reglas adaptativas para generar una selección metodológica y acciones de adaptación justificadas.</p></div>
    <div class="intro-note"><strong>Importante</strong><p>Las reglas adaptativas de esta versión son una propuesta inicial. Los casos de demostración contienen datos ficticios y no constituyen evidencia empírica.</p></div>
  </section>

  <div class="layout">
    <section class="panel form-panel">
      <div class="panel-heading"><div><span class="step">01</span><h2>Datos del proyecto</h2></div><span class="tag">Entrada</span></div>
      <div class="field-grid">
        <label>Empresa / organización<input id="empresa" type="text" placeholder="Ej. CONCE SUR" value=""></label>
        <label>Proyecto de software<input id="proyecto" type="text" placeholder="Ej. Sistema de inventario"></label>
        <label class="full">Tipo de información<select id="tipoDato"><option value="simulacion">Simulación / prueba</option><option value="investigacion">Datos de investigación</option><option value="demostracion">Demostración</option></select></label>
      </div>
      <div class="section-sub"><div><h3>Perfil del proyecto</h3><p>Clasifique cada criterio como bajo, medio o alto con base en indicadores documentados.</p></div></div>
      <div class="level-grid" id="levelGrid"></div>
      <div class="section-sub"><div><h3>Comparaciones AHP</h3><p>Valore la importancia o preferencia del elemento izquierdo frente al derecho con la escala de Saaty. Debe seleccionar un valor; use 1 si considera que son iguales.</p></div><div class="scale-key">1 igual · 3 moderada · 5 fuerte · 7 muy fuerte · 9 extrema</div></div>
      <div class="matrix-block"><h4>Matriz de criterios (4 × 4)</h4><p class="helper">Seis comparaciones independientes entre los cuatro criterios.</p><div id="criteriaComparisons" class="comparison-grid"></div></div>
      <div class="matrix-block"><h4>Matrices de alternativas (3 × 3)</h4><p class="helper">Tres comparaciones por criterio; doce en total.</p><div id="alternativeComparisons"></div></div>
      <div class="actions-row">
        <button class="btn btn-outline" id="btnImport">Importar JSON</button><input type="file" id="jsonFile" accept=".json,application/json" hidden>
        <select id="demoSelect" class="demo-select"><option value="">Cargar una demostración…</option><option value="01_CONCE_SUR.json">CONCE SUR</option><option value="02_ANDESSOFT.json">AndesSoft Solutions</option><option value="03_RUTASUR_LOGISTICA.json">RutaSur Logística</option><option value="04_PRUEBA_CR_INCONSISTENTE.json">Prueba de inconsistencia</option></select>
        <button class="btn btn-outline" id="btnClear">Limpiar</button>
      </div>
      <div class="calculate-row"><button class="btn btn-primary btn-large" id="btnCalculate">Calcular modelo AHP + reglas adaptativas</button><span id="formMessage" class="message" role="status"></span></div>
    </section>

    <aside class="side-column">
      <section class="panel compact-panel"><div class="panel-heading"><div><span class="step">02</span><h2>Flujo del motor</h2></div></div>
        <div class="flow-list">
          <div class="flow-item"><span>1</span><div><b>Validación de entradas</b><small>Escala Saaty, campos y niveles</small></div></div>
          <div class="flow-item"><span>2</span><div><b>Cálculo AHP</b><small>Pesos, prioridades y consistencia</small></div></div>
          <div class="flow-item"><span>3</span><div><b>Reglas adaptativas</b><small>Nivel del criterio + peso AHP</small></div></div>
          <div class="flow-item"><span>4</span><div><b>Resultado integrado</b><small>Ranking y acciones priorizadas</small></div></div>
        </div>
      </section>
      <section class="panel compact-panel formula-panel"><div class="panel-heading"><div><span class="step">03</span><h2>Fórmulas principales</h2></div></div>
        <div class="formula"><small>Prioridad global alternativa j</small><code>P<sub>j</sub> = Σ w<sub>i</sub> · p<sub>ij</sub></code></div>
        <div class="formula"><small>Índice de consistencia</small><code>CI = (λ<sub>max</sub> − n) / (n − 1)</code></div>
        <div class="formula"><small>Razón de consistencia</small><code>CR = CI / RI</code></div>
        <div class="formula"><small>Factor de necesidad adaptativa (propuesta)</small><code>q<sub>i</sub> = (w<sub>i</sub> · f<sub>i</sub>) / Σ(w · f)</code></div>
        <p class="tiny-note">El factor fᵢ es una operacionalización propuesta y diferenciada por criterio; no es una fórmula estándar de AHP.</p>
      </section>
      <section class="panel compact-panel"><div class="panel-heading"><div><span class="step">04</span><h2>Base de datos</h2></div><span id="dbStatus" class="status-dot">Comprobando</span></div><p class="helper">El historial guarda cada evaluación en MySQL. Para habilitarlo, importe <code>database/motor_ahp.sql</code> en phpMyAdmin.</p><button class="btn btn-outline btn-block" id="btnRefreshHistory">Actualizar historial</button></section>
    </aside>
  </div>

  <section class="panel results-panel hidden" id="resultsPanel">
    <div class="panel-heading"><div><span class="step">05</span><h2>Resultado del modelo</h2><p id="resultSubtitle" class="helper"></p></div><div id="resultBadge" class="result-badge"></div></div>
    <div id="warnings"></div>
    <div class="result-grid">
      <div class="result-card"><small>Alternativa prioritaria</small><strong id="winner">—</strong><span>Solo se declara ganadora si todas las matrices cumplen CR &lt; 0,10 y no existe empate.</span></div>
      <div class="result-card"><small>Consistencia de criterios</small><strong id="criteriaCr">—</strong><span>Umbral de aceptación: CR &lt; 0,10</span></div>
      <div class="result-card"><small>Estado del cálculo</small><strong id="resultState">—</strong><span>Diagnóstico de las matrices AHP</span></div>
    </div>
    <div class="result-columns"><div><h3>Peso de los criterios</h3><div id="criteriaResults"></div></div><div><h3>Prioridad global de alternativas</h3><div id="alternativeResults"></div></div></div>
    <div><h3>Acciones adaptativas priorizadas</h3><p class="helper">Se ordenan combinando la importancia AHP del criterio y la intensidad de su nivel.</p><div id="ruleResults"></div></div>
    <div class="result-footer"><button class="btn btn-primary" id="btnSave">Guardar en MySQL</button><button class="btn btn-outline" id="btnExport">Descargar resultado JSON</button><button class="btn btn-outline" id="btnPrint">Imprimir resultado</button><span id="saveMessage" class="message"></span></div>
    <div class="disclaimer">El ranking AHP y las reglas adaptativas se presentan como dos capas complementarias. En esta versión las reglas no alteran las puntuaciones AHP. El prototipo tampoco calcula una proporción porcentual de hibridación.</div>
  </section>

  <section class="panel history-panel hidden" id="historyPanel"><div class="panel-heading"><div><span class="step">06</span><h2>Historial de evaluaciones</h2></div><button class="btn btn-outline" id="btnCloseHistory">Cerrar</button></div><div id="historyContent" class="helper">Cargando…</div></section>
  <footer>Motor académico AHP + reglas adaptativas · PHP y MySQL · WAMP</footer>
</main>
<script src="assets/app.js"></script>
</body>
</html>
