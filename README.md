MOTOR DE DECISIÓN AHP + REGLAS ADAPTATIVAS
Aplicación PHP + MySQL  

1. REQUISITOS
- WampServer con Apache, PHP 8.1 o superior y MySQL/MariaDB.
- Extensiones PHP: PDO y PDO MySQL (pdo_mysql).
- Navegador moderno (Chrome o Edge).
- No utiliza Composer, Node.js ni bibliotecas externas.


2. DÓNDE ESTÁ EL MOTOR
- src/AhpEngine.php: construye matrices recíprocas, calcula vector de prioridades,
  lambda máximo, CI y CR.
- src/DecisionEngine.php: integra pesos de criterios, prioridades locales y
  puntuaciones globales de las alternativas.
- src/AdaptiveRules.php: aplica reglas adaptativas según nivel del criterio y peso AHP.
- api/calculate.php: recibe el JSON desde la interfaz y ejecuta el motor PHP.
- api/save.php, api/history.php y api/get.php: guardan y consultan evaluaciones.
- index.php y assets/: interfaz del programa.
- database/motor_ahp.sql: estructura de la base MySQL.

3. CÓMO USAR LA APLICACIÓN
1) Ingrese empresa y proyecto.
2) Clasifique C1, C2, C3 y C4 como bajo, medio o alto según indicadores definidos.
3) Complete las seis comparaciones entre criterios.
4) Complete las doce comparaciones entre alternativas: tres por cada criterio.
5) Pulse Calcular modelo AHP + reglas adaptativas.
6) Revise el CR de la matriz de criterios y de cada matriz de alternativas.
Nota: los selectores comparativos comienzan vacíos para evitar asumir que todos los elementos son iguales. Seleccione cada comparación; si considera que dos elementos tienen igual importancia/preferencia, elija explícitamente 1.
7) Si todas cumplen CR < 0.10 y no hay empate, el sistema muestra una alternativa prioritaria.
8) Pulse Guardar en MySQL para registrar el resultado.
9) Use Historial para consultar evaluaciones guardadas.
10) Use Descargar resultado JSON para conservar el caso y su resultado.

4. IMPORTAR CASOS DE PRUEBA
El botón Importar JSON admite archivos .json con las claves. También admite la estructura exportada por la propia aplicación, que contiene el objeto de entrada en la propiedad "entrada":
- metadatos
- comparaciones_criterios
- comparaciones_alternativas
- contexto_proyecto
Los tres casos ficticios y la prueba de inconsistencia están en examples/.
La aplicación también incluye un selector para cargar los casos de demostración.
No importa Excel, CSV, Word ni PDF de forma directa. El Excel es una hoja de
consulta/documentación; los datos se ingresan en el formulario o se importan en JSON.

5. PRUEBAS TÉCNICAS
Desde una terminal dentro de la carpeta Motor_AHP_WAMP ejecute:
   php tests/test_motor.php
Debe mostrar CONCE_SUR, ANDESSOFT y RUTASUR como calculado_consistente,
y CR_INCONSISTENTE como requiere_revision_de_consistencia.

6. BASE MATEMÁTICA DEL MODELO
AHP:
- Vector de prioridades: A*w = lambda_max*w.
- Prioridad global de alternativa j: P_j = sum_i (w_i * p_ij).
- Índice de consistencia: CI = (lambda_max - n)/(n - 1).
- Razón de consistencia: CR = CI/RI.
- Criterio de revisión del proyecto: CR < 0.10.

7. Integración adaptativa propuesta:
- Factor de necesidad f_i para C1, C2 y C3: bajo=1/3, medio=2/3, alto=1.
- Para C4 (experiencia del equipo), el sentido se invierte: bajo=1, medio=2/3, alto=1/3; menor experiencia implica mayor necesidad de acompañamiento.
- Prioridad bruta de acción: u_i = w_i*f_i.
- Prioridad normalizada: q_i = u_i / sum_k(u_k).
Esta fórmula prioriza acciones adaptativas; no modifica la puntuación AHP de las
alternativas. Los factores por criterio son una operacionalización propuesta y requieren
fundamentación y validación antes de utilizarse como resultado científico definitivo.

 
8. USO ACADÉMICO Y SEGURIDAD
- No se registro datos confidenciales de empresas reales en una demostración.
