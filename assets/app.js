(() => {
  'use strict';
  const $ = (id) => document.getElementById(id);
  const CRITERIA = {
    C1: 'Volatilidad de los requisitos',
    C2: 'Criticidad de la arquitectura técnica',
    C3: 'Restricciones de costo y tiempo',
    C4: 'Experiencia del equipo de trabajo'
  };
  const ALTS = {predictiva:'Predictiva', adaptativa:'Adaptativa', hibrida:'Híbrida'};
  const LEVELS = ['bajo','medio','alto'];
  const SAATY = [
    ['1','1 · Igual importancia'],['2','2 · Intermedio'],['3','3 · Moderada'],['4','4 · Intermedio'],['5','5 · Fuerte'],['6','6 · Intermedio'],['7','7 · Muy fuerte'],['8','8 · Intermedio'],['9','9 · Extrema'],
    ['0.5','1/2 · Recíproco'],['0.3333333333333333','1/3 · Recíproco'],['0.25','1/4 · Recíproco'],['0.2','1/5 · Recíproco'],['0.16666666666666666','1/6 · Recíproco'],['0.14285714285714285','1/7 · Recíproco'],['0.125','1/8 · Recíproco'],['0.1111111111111111','1/9 · Recíproco']
  ];
  let lastInput = null;
  let lastResult = null;

  function optionMarkup(value, label) { return `<option value="${value}">${label}</option>`; }
  function selectMarkup(key) {
    return `<select data-comparison="${key}"><option value="">Seleccione valoración</option>${SAATY.map(([v,l]) => optionMarkup(v,l)).join('')}</select>`;
  }
  function addComparison(container, label, key) {
    const el = document.createElement('div');
    el.className = 'comparison';
    el.innerHTML = `<label>${label}</label>${selectMarkup(key)}`;
    container.appendChild(el);
  }
  function buildForm() {
    const levels = $('levelGrid');
    Object.entries(CRITERIA).forEach(([code, name]) => {
      const card = document.createElement('label');
      card.className = 'level-card';
      card.innerHTML = `<span>${code} · ${name}</span><small>Clasificación de la condición del proyecto</small><select id="level_${code}"><option value="">Seleccione nivel</option><option value="bajo">Bajo</option><option value="medio">Medio</option><option value="alto">Alto</option></select>`;
      levels.appendChild(card);
    });
    const cc = $('criteriaComparisons');
    const cs = Object.keys(CRITERIA);
    for (let i=0;i<cs.length;i++) for (let j=i+1;j<cs.length;j++) addComparison(cc, `${cs[i]} frente a ${cs[j]}`, `${cs[i]}_${cs[j]}`);

    const altContainer = $('alternativeComparisons');
    const as = Object.keys(ALTS);
    Object.entries(CRITERIA).forEach(([code,name]) => {
      const group = document.createElement('div');
      group.className = 'alternative-group';
      const heading = document.createElement('h5'); heading.textContent = `${code} · ${name}`; group.appendChild(heading);
      const grid = document.createElement('div'); grid.className = 'comparison-grid';
      for (let i=0;i<as.length;i++) for (let j=i+1;j<as.length;j++) addComparison(grid, `${ALTS[as[i]]} frente a ${ALTS[as[j]]}`, `${code}|${as[i]}_${as[j]}`);
      group.appendChild(grid); altContainer.appendChild(group);
    });
  }
  function getInput() {
    const empresa = $('empresa').value.trim();
    const proyecto = $('proyecto').value.trim();
    if (!empresa || !proyecto) throw new Error('Complete el nombre de la empresa y del proyecto.');
    const context = {};
    Object.keys(CRITERIA).forEach(code => {
      const value = $(`level_${code}`).value;
      if (!LEVELS.includes(value)) throw new Error(`Seleccione nivel bajo, medio o alto para ${code}.`);
      context[code] = value;
    });
    const criterionComparisons = {};
    document.querySelectorAll('#criteriaComparisons [data-comparison]').forEach(s => {
      if (!s.value) throw new Error(`Complete la comparación ${s.dataset.comparison}; seleccione 1 si considera que tienen igual importancia.`);
      criterionComparisons[s.dataset.comparison] = Number(s.value);
    });
    const altComparisons = {};
    Object.keys(CRITERIA).forEach(code => altComparisons[code] = {});
    document.querySelectorAll('#alternativeComparisons [data-comparison]').forEach(s => {
      if (!s.value) throw new Error(`Complete la comparación ${s.dataset.comparison.replace('|',' / ')}; seleccione 1 si considera que tienen igual preferencia.`);
      const [code, pair] = s.dataset.comparison.split('|');
      altComparisons[code][pair] = Number(s.value);
    });
    return {
      metadatos: {empresa, proyecto, tipo_dato: $('tipoDato').value, origen: 'Aplicación PHP + MySQL en WAMP'},
      comparaciones_criterios: criterionComparisons,
      comparaciones_alternativas: altComparisons,
      contexto_proyecto: context
    };
  }
  function normalizeImportedPayload(payload) {
    // Admite tanto el JSON de entrada como el JSON exportado por la aplicación ({entrada, resultado}).
    const data = payload?.entrada && typeof payload.entrada === 'object' ? payload.entrada : payload;
    if (!data || typeof data !== 'object') throw new Error('El archivo no contiene un objeto JSON válido.');
    if (!data.metadatos?.empresa || !data.metadatos?.proyecto) throw new Error('El JSON debe incluir metadatos.empresa y metadatos.proyecto.');
    if (!data.comparaciones_criterios || !data.comparaciones_alternativas || !data.contexto_proyecto) throw new Error('Faltan comparaciones_criterios, comparaciones_alternativas o contexto_proyecto.');
    const criteriaPairs = ['C1_C2','C1_C3','C1_C4','C2_C3','C2_C4','C3_C4'];
    const altPairs = ['predictiva_adaptativa','predictiva_hibrida','adaptativa_hibrida'];
    const validSaaty = (v) => Number.isFinite(Number(v)) && SAATY.some(([n]) => Math.abs(Number(n)-Number(v)) < 1e-8);
    for (const pair of criteriaPairs) {
      if (!Object.prototype.hasOwnProperty.call(data.comparaciones_criterios,pair) || !validSaaty(data.comparaciones_criterios[pair])) throw new Error(`Falta o es inválida la comparación ${pair}.`);
    }
    for (const code of Object.keys(CRITERIA)) {
      if (!LEVELS.includes(String(data.contexto_proyecto[code] || '').toLowerCase())) throw new Error(`Falta el nivel bajo, medio o alto para ${code}.`);
      const comparisons = data.comparaciones_alternativas[code];
      if (!comparisons || typeof comparisons !== 'object') throw new Error(`Faltan comparaciones de alternativas para ${code}.`);
      for (const pair of altPairs) {
        if (!Object.prototype.hasOwnProperty.call(comparisons,pair) || !validSaaty(comparisons[pair])) throw new Error(`Falta o es inválida la comparación ${code}.${pair}.`);
      }
    }
    return data;
  }
  function setInput(payload) {
    const data = normalizeImportedPayload(payload);
    $('empresa').value = data.metadatos.empresa;
    $('proyecto').value = data.metadatos.proyecto;
    $('tipoDato').value = ['simulacion','investigacion','demostracion'].includes(data.metadatos.tipo_dato) ? data.metadatos.tipo_dato : 'simulacion';
    Object.keys(CRITERIA).forEach(code => $(`level_${code}`).value = String(data.contexto_proyecto[code]).toLowerCase());
    document.querySelectorAll('[data-comparison]').forEach(s => {
      let value;
      if (s.dataset.comparison.includes('|')) {
        const [code,pair] = s.dataset.comparison.split('|'); value = data.comparaciones_alternativas[code][pair];
      } else value = data.comparaciones_criterios[s.dataset.comparison];
      s.value = String(value);
    });
    return data;
  }
  async function loadDemo(name) {
    try {
      const response = await fetch(`examples/${encodeURIComponent(name)}`);
      if (!response.ok) throw new Error('No se pudo cargar el caso de demostración.');
      const data = await response.json(); setInput(data); $('formMessage').textContent = 'Caso de demostración cargado. Revise la advertencia antes de utilizarlo.';
    } catch (e) { $('formMessage').textContent = e.message; }
  }
  function fmt(x, digits=4) { return (Number(x)*100).toFixed(2) + '%'; }
  function safe(text) { return String(text ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c])); }
  function bar(label, value, alt=false) {
    const pct = Math.max(0, Math.min(100, Number(value)*100));
    return `<div class="bar-row"><div class="bar-label"><span>${safe(label)}</span><b>${pct.toFixed(2)}%</b></div><div class="bar-track"><div class="bar-fill ${alt?'alt':''}" style="width:${pct}%"></div></div></div>`;
  }
  function renderResult(input, result) {
    lastInput = input; lastResult = result;
    $('resultsPanel').classList.remove('hidden');
    $('resultSubtitle').textContent = `${input.metadatos.empresa} · ${input.metadatos.proyecto}`;
    const consistent = result.estado === 'calculado_consistente';
    const badge = $('resultBadge'); badge.className = 'result-badge' + (consistent?'':(result.estado==='empate_entre_alternativas'?' warn':' bad'));
    badge.textContent = consistent ? 'Consistente' : (result.estado==='empate_entre_alternativas'?'Empate técnico':'Requiere revisión');
    $('winner').textContent = result.alternativa_prioritaria ? ALTS[result.alternativa_prioritaria] : 'No determinada';
    $('criteriaCr').textContent = Number(result.diagnostico_matriz_criterios.cr).toFixed(4);
    $('resultState').textContent = result.estado.replaceAll('_',' ');
    $('warnings').innerHTML = (result.advertencias || []).map(w => `<div class="warning">${safe(w)}</div>`).join('');
    $('criteriaResults').innerHTML = Object.entries(result.criterios).map(([code,row]) => bar(`${code} · ${row.nombre}`,row.peso_ahp)).join('');
    $('alternativeResults').innerHTML = result.orden_alternativas.map((row,i)=>bar(`${i+1}. ${row.nombre}`,row.puntuacion,true)).join('');
    let rules = result.integracion_reglas_adaptativas.reglas || [];
    $('ruleResults').innerHTML = `<table class="result-table"><thead><tr><th>Orden</th><th>Criterio / nivel y acción</th><th>Peso AHP</th><th>Factor necesidad</th><th>Prioridad acción</th></tr></thead><tbody>${rules.map((r,i)=>`<tr><td><span class="rank-chip">${i+1}</span></td><td><b>${safe(r.criterio)}</b><br><small>${safe(r.nivel)}</small><br>${safe(r.accion)}</td><td>${fmt(r.peso_ahp)}</td><td>${Number(r.factor_necesidad_adaptacion).toFixed(3)}</td><td>${fmt(r.prioridad_regla_normalizada)}</td></tr>`).join('')}</tbody></table><p class="tiny-note">${safe(result.integracion_reglas_adaptativas.advertencia)}</p>`;
    $('saveMessage').textContent = '';
    $('resultsPanel').scrollIntoView({behavior:'smooth',block:'start'});
  }
  async function calculate() {
    $('btnCalculate').disabled = true; $('formMessage').textContent = 'Calculando…';
    try {
      const input = getInput();
      const response = await fetch('api/calculate.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(input)});
      const data = await response.json();
      if (!response.ok || !data.ok) throw new Error(data.error || 'Error al calcular.');
      renderResult(input,data.resultado); $('formMessage').textContent = 'Cálculo completado.';
    } catch(e) { $('formMessage').textContent = e.message; }
    finally { $('btnCalculate').disabled = false; }
  }
  async function saveResult() {
    if (!lastInput || !lastResult) return;
    $('btnSave').disabled = true; $('saveMessage').textContent = 'Guardando…';
    try {
      const response = await fetch('api/save.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({entrada:lastInput,resultado:lastResult})});
      const data = await response.json();
      if (!response.ok || !data.ok) throw new Error(data.error || 'No se pudo guardar.');
      $('saveMessage').textContent = `Guardado en MySQL. Registro #${data.id}.`; checkDb();
    } catch(e) { $('saveMessage').textContent = e.message; }
    finally { $('btnSave').disabled = false; }
  }
  function exportResult() {
    if (!lastInput || !lastResult) return;
    const blob = new Blob([JSON.stringify({entrada:lastInput,resultado:lastResult},null,2)],{type:'application/json'});
    const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = `resultado_${(lastInput.metadatos.empresa||'proyecto').replace(/[^a-z0-9_-]/gi,'_')}.json`; a.click(); URL.revokeObjectURL(a.href);
  }
  async function history() {
    $('historyPanel').classList.remove('hidden'); $('historyContent').textContent = 'Consultando MySQL…';
    try {
      const response = await fetch('api/history.php'); const data = await response.json();
      if (!response.ok || !data.ok) throw new Error(data.error || 'No se pudo leer el historial.');
      if (!data.items.length) { $('historyContent').textContent='Aún no hay evaluaciones guardadas.'; return; }
      $('historyContent').innerHTML = data.items.map(item=>`<div class="history-item"><div><strong>${safe(item.empresa)}</strong><small>${safe(item.proyecto)} · ${safe(item.creado_en)}</small></div><em>${safe(item.estado)}</em><button class="btn btn-outline" data-open-record="${item.id}">Ver</button></div>`).join('');
      document.querySelectorAll('[data-open-record]').forEach(btn=>btn.addEventListener('click',()=>openRecord(btn.dataset.openRecord)));
    } catch(e) { $('historyContent').textContent=e.message; }
  }
  async function openRecord(id) {
    try {
      const response = await fetch(`api/get.php?id=${encodeURIComponent(id)}`); const data = await response.json();
      if (!response.ok || !data.ok) throw new Error(data.error || 'No se pudo abrir el registro.');
      renderResult(data.registro.entrada,data.registro.resultado);
    } catch(e) { $('historyContent').textContent=e.message; }
  }
  async function checkDb() {
    try { const r=await fetch('api/history.php'); const j=await r.json(); $('dbStatus').textContent=j.ok?'MySQL conectado':'MySQL pendiente'; $('dbStatus').className='status-dot '+(j.ok?'ok':'error'); }
    catch(e) { $('dbStatus').textContent='MySQL pendiente'; $('dbStatus').className='status-dot error'; }
  }
  function clearForm() {
    $('empresa').value=''; $('proyecto').value=''; $('tipoDato').value='simulacion';
    Object.keys(CRITERIA).forEach(code=>$(`level_${code}`).value='');
    document.querySelectorAll('[data-comparison]').forEach(s=>s.value='');
    $('resultsPanel').classList.add('hidden'); $('formMessage').textContent='Formulario limpio.'; lastInput=null; lastResult=null;
  }
  function importFile(file) {
    const reader = new FileReader();
    reader.onload = () => {
      try {
        setInput(JSON.parse(reader.result));
        $('formMessage').textContent = `Archivo ${file.name} importado y estructura verificada.`;
      } catch(e) {
        $('formMessage').textContent = e.message || 'JSON inválido o estructura incompleta.';
      }
    };
    reader.onerror = () => { $('formMessage').textContent = 'No se pudo leer el archivo seleccionado.'; };
    reader.readAsText(file);
  }

  buildForm();
  $('btnCalculate').addEventListener('click',calculate);
  $('btnSave').addEventListener('click',saveResult);
  $('btnExport').addEventListener('click',exportResult);
  $('btnPrint').addEventListener('click',()=>window.print());
  $('btnClear').addEventListener('click',clearForm);
  $('btnImport').addEventListener('click',()=>$('jsonFile').click());
  $('jsonFile').addEventListener('change',e=>{if(e.target.files[0])importFile(e.target.files[0]);e.target.value='';});
  $('demoSelect').addEventListener('change',e=>{if(e.target.value)loadDemo(e.target.value);e.target.value='';});
  $('btnHistory').addEventListener('click',history);
  $('btnRefreshHistory').addEventListener('click',history);
  $('btnCloseHistory').addEventListener('click',()=>$('historyPanel').classList.add('hidden'));
  checkDb();
})();
