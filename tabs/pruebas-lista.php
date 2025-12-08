<?php
// tabs/pruebas-lista.php

// Si hay una prueba específica seleccionada, mostrar formulario de registro de prueba
if ($prueba_actual) {
    // Obtener datos de la prueba
    $prueba_detalle = $db->fetchOne("
        SELECT pp.*, u.nombre, u.apellido, u.dni, ac.objetivo_prueba
        FROM pruebas_protocolo pp
        LEFT JOIN usuarios u ON pp.conductor_id = u.id
        LEFT JOIN actas_consentimiento ac ON pp.acta_id = ac.id
        WHERE pp.id = ?
    ", [$prueba_actual]);
    
    // Obtener alcoholímetro del checklist
    $alcoholimetro_checklist = $db->fetchOne("
        SELECT co.alcoholimetro_id, a.numero_serie, a.nombre_activo
        FROM checklists_operacion co
        LEFT JOIN alcoholimetros a ON co.alcoholimetro_id = a.id
        WHERE co.operacion_id = ?
    ", [$protocolo_id]);
    ?>
    
    <div class="tab-content active">
        <div class="tab-header">
            <h2><i class="fas fa-vial"></i> 5. Registro de Prueba</h2>
            <p class="text-muted">Registre el resultado de la prueba de alcoholemia para el conductor seleccionado.</p>
            
            <div class="conductor-header">
                <div class="conductor-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div>
                    <h3><?php echo htmlspecialchars($prueba_detalle['nombre'] . ' ' . $prueba_detalle['apellido']); ?></h3>
                    <p class="text-muted">DNI: <?php echo htmlspecialchars($prueba_detalle['dni']); ?> | 
                        Objetivo: <?php echo ucfirst($prueba_detalle['objetivo_prueba']); ?></p>
                </div>
            </div>
        </div>
        
        <form id="formPrueba" method="POST" class="modal-form">
            <input type="hidden" name="guardar_prueba" value="1">
            <input type="hidden" name="prueba_protocolo_id" value="<?php echo $prueba_actual; ?>">
            
            <div class="form-section">
                <h4><i class="fas fa-vial"></i> Datos de la Prueba</h4>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Conductor</label>
                        <div class="form-static"><?php echo htmlspecialchars($prueba_detalle['nombre'] . ' ' . $prueba_detalle['apellido']); ?></div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Alcoholímetro</label>
                        <div class="form-static">
                            <?php if ($alcoholimetro_checklist): ?>
                                <?php echo htmlspecialchars($alcoholimetro_checklist['nombre_activo'] . ' (' . $alcoholimetro_checklist['numero_serie'] . ')'); ?>
                            <?php else: ?>
                                <span class="text-danger">No seleccionado en checklist</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="nivel_alcohol" class="form-label">Nivel de alcohol (g/L) *</label>
                        <input type="number" id="nivel_alcohol" name="nivel_alcohol" class="form-control" 
                               step="0.001" min="0" max="5" required
                               placeholder="0.000" onchange="calcularResultado()">
                    </div>
                    
                    <div class="form-group">
                        <label for="limite_permisible" class="form-label">Límite permisible (g/L)</label>
                        <input type="number" id="limite_permisible" name="limite_permisible" class="form-control" 
                               step="0.001" min="0" max="5" value="0.000" readonly
                               onchange="calcularResultado()">
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h4><i class="fas fa-chart-bar"></i> Resultado y Observaciones</h4>
                
                <div class="resultado-container">
                    <div class="resultado-preview">
                        <div class="resultado-item">
                            <span>Nivel medido:</span>
                            <strong id="nivelMedidoPreview">0.000 g/L</strong>
                        </div>
                        <div class="resultado-item">
                            <span>Límite permisible:</span>
                            <strong id="limitePreview">0.000 g/L</strong>
                        </div>
                        <div class="resultado-item">
                            <span>Resultado:</span>
                            <strong id="resultadoPreview" class="text-warning">Pendiente</strong>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="resultado" class="form-label">Resultado final *</label>
                        <select id="resultado" name="resultado" class="form-control" required>
                            <option value="">Seleccionar resultado</option>
                            <option value="aprobado">Aprobado (Negativo)</option>
                            <option value="reprobado">Reprobado (Positivo)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="observaciones" class="form-label">Observaciones</label>
                        <textarea id="observaciones" name="observaciones" class="form-control" rows="3" 
                                  placeholder="Observaciones sobre la prueba, condiciones del conductor, etc."></textarea>
                    </div>
                </div>
            </div>
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>Nota:</strong> El límite permisible se establece en la configuración del sistema (actualmente 0.000 g/L).
                Si el resultado es "Reprobado", se habilitará el registro Widmark.
            </div>
            
            <div class="alert alert-warning" id="alertaPositivo" style="display: none;">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>¡ATENCIÓN! Resultado positivo detectado.</strong> Deberá completar el registro Widmark (Tab 6) y 
                el informe de resultado positivo (Tab 7) para este conductor.
            </div>
        </form>
    </div>
    
    <script>
    function calcularResultado() {
        const nivelAlcohol = parseFloat(document.getElementById('nivel_alcohol').value) || 0;
        const limitePermisible = parseFloat(document.getElementById('limite_permisible').value) || 0;
        
        // Actualizar previews
        document.getElementById('nivelMedidoPreview').textContent = nivelAlcohol.toFixed(3) + ' g/L';
        document.getElementById('limitePreview').textContent = limitePermisible.toFixed(3) + ' g/L';
        
        // Calcular y mostrar resultado
        let resultado = '';
        let clase = '';
        
        if (nivelAlcohol > limitePermisible) {
            resultado = 'REPROBADO (POSITIVO)';
            clase = 'text-danger';
            document.getElementById('resultado').value = 'reprobado';
            document.getElementById('alertaPositivo').style.display = 'block';
        } else if (nivelAlcohol <= limitePermisible) {
            resultado = 'APROBADO (NEGATIVO)';
            clase = 'text-success';
            document.getElementById('resultado').value = 'aprobado';
            document.getElementById('alertaPositivo').style.display = 'none';
        } else {
            resultado = 'PENDIENTE';
            clase = 'text-warning';
            document.getElementById('alertaPositivo').style.display = 'none';
        }
        
        const preview = document.getElementById('resultadoPreview');
        preview.textContent = resultado;
        preview.className = clase;
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        // Establecer límite permisible desde configuración
        fetch('ajax/obtener-configuracion.php')
            .then(response => response.json())
            .then(data => {
                if (data.limite_alcohol_permisible) {
                    document.getElementById('limite_permisible').value = data.limite_alcohol_permisible;
                    calcularResultado();
                }
            });
            
        // Escuchar cambios en el nivel de alcohol
        document.getElementById('nivel_alcohol').addEventListener('input', calcularResultado);
        
        // Escuchar cambios en el resultado manual
        document.getElementById('resultado').addEventListener('change', function() {
            const alerta = document.getElementById('alertaPositivo');
            if (this.value === 'reprobado') {
                alerta.style.display = 'block';
            } else {
                alerta.style.display = 'none';
            }
        });
    });
    </script>
    
    <style>
    .conductor-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        background: var(--light);
        border-radius: 8px;
        margin: 1rem 0;
        border: 1px solid var(--border);
    }
    
    .conductor-avatar {
        width: 50px;
        height: 50px;
        background: var(--primary);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.5rem;
    }
    
    .resultado-container {
        background: var(--light);
        padding: 1.5rem;
        border-radius: 8px;
        border: 1px solid var(--border);
    }
    
    .resultado-preview {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1rem;
        margin-bottom: 1.5rem;
    }
    
    .resultado-item {
        text-align: center;
        padding: 1rem;
        background: white;
        border-radius: 8px;
        border: 1px solid var(--border);
    }
    
    .resultado-item span {
        display: block;
        font-size: 0.85rem;
        color: var(--gray);
        margin-bottom: 0.5rem;
    }
    
    .resultado-item strong {
        font-size: 1.1rem;
        display: block;
    }
    
    @media (max-width: 768px) {
        .resultado-preview {
            grid-template-columns: 1fr;
        }
    }
    </style>
    
    <?php
} else {
    // Mostrar lista de pruebas para registrar
    ?>
    
    <div class="tab-content active">
        <div class="tab-header">
            <h2><i class="fas fa-list-ul"></i> 5. Lista de Pruebas</h2>
            <p class="text-muted">Seleccione una prueba para registrar el resultado o vea el estado de cada una.</p>
        </div>
        
        <?php if (empty($pruebas)): ?>
        <div class="empty-state">
            <div class="empty-icon">
                <i class="fas fa-vial"></i>
            </div>
            <h3>No hay pruebas registradas</h3>
            <p>Regrese al tab anterior para agregar conductores y registrar sus consentimientos.</p>
            <div class="empty-actions">
                <button type="button" class="btn btn-outline" onclick="cambiarTab(3)">
                    <i class="fas fa-arrow-left"></i> Volver a Consentimiento
                </button>
            </div>
        </div>
        <?php else: ?>
        
        <div class="pruebas-table-container">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Conductor</th>
                            <th>DNI</th>
                            <th>Objetivo</th>
                            <th>Estado</th>
                            <th>Resultado</th>
                            <th>Nivel Alcohol</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pruebas as $prueba): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($prueba['conductor_nombre'] . ' ' . $prueba['conductor_apellido']); ?></strong>
                            </td>
                            <td><?php echo htmlspecialchars($prueba['conductor_dni']); ?></td>
                            <td>
                                <span class="badge secondary"><?php echo ucfirst($prueba['objetivo_prueba'] ?? 'N/A'); ?></span>
                            </td>
                            <td>
                                <?php if ($prueba['completada']): ?>
                                    <span class="badge success">Completada</span>
                                <?php else: ?>
                                    <span class="badge warning">Paso <?php echo $prueba['paso_actual']; ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($prueba['resultado']): ?>
                                    <span class="badge <?php echo $prueba['resultado'] == 'reprobado' ? 'danger' : 'success'; ?>">
                                        <?php echo $prueba['resultado'] == 'reprobado' ? 'Positivo' : 'Negativo'; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge warning">Pendiente</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($prueba['nivel_alcohol']): ?>
                                    <span class="nivel-alcohol <?php echo $prueba['nivel_alcohol'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                        <?php echo number_format($prueba['nivel_alcohol'], 3); ?> g/L
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">--</span>
                                <?php endif; ?>
                            </td>
                            <td class="action-buttons">
                                <?php if (!$prueba['completada']): ?>
                                <button type="button" class="btn-icon primary" 
                                        onclick="cambiarTab(5, <?php echo $prueba['prueba_protocolo_id']; ?>)"
                                        title="Registrar prueba">
                                    <i class="fas fa-vial"></i>
                                </button>
                                <?php endif; ?>
                                
                                <?php if ($prueba['completada'] && $prueba['resultado'] == 'reprobado'): ?>
                                <button type="button" class="btn-icon warning" 
                                        onclick="cambiarTab(6, <?php echo $prueba['prueba_protocolo_id']; ?>)"
                                        title="Registrar Widmark">
                                    <i class="fas fa-chart-line"></i>
                                </button>
                                
                                <button type="button" class="btn-icon danger" 
                                        onclick="cambiarTab(7, <?php echo $prueba['prueba_protocolo_id']; ?>)"
                                        title="Informe Positivo">
                                    <i class="fas fa-file-medical"></i>
                                </button>
                                <?php endif; ?>
                                
                                <?php if ($prueba['completada']): ?>
                                <button type="button" class="btn-icon info" 
                                        onclick="verDetallesPrueba(<?php echo $prueba['prueba_protocolo_id']; ?>)"
                                        title="Ver detalles">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="table-footer">
                <div class="table-summary">
                    <strong>Resumen:</strong>
                    <span class="badge primary">Total: <?php echo count($pruebas); ?></span>
                    <span class="badge success">Completadas: <?php echo count(array_filter($pruebas, fn($p) => $p['completada'])); ?></span>
                    <span class="badge warning">Pendientes: <?php echo count(array_filter($pruebas, fn($p) => !$p['completada'])); ?></span>
                    <span class="badge danger">Positivas: <?php echo count(array_filter($pruebas, fn($p) => $p['resultado'] == 'reprobado')); ?></span>
                </div>
                
                <div class="table-actions">
                    <button type="button" class="btn btn-primary" onclick="nuevaPrueba()">
                        <i class="fas fa-user-plus"></i> Agregar Nuevo Conductor
                    </button>
                    
                    <?php if (count(array_filter($pruebas, fn($p) => $p['completada'])) == count($pruebas) && count($pruebas) > 0): ?>
                    <button type="button" class="btn btn-success" onclick="cambiarTab(8)">
                        <i class="fas fa-arrow-right"></i> Ir al Resumen
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php endif; ?>
    </div>
    
    <script>
    function verDetallesPrueba(pruebaId) {
        // Mostrar modal con detalles completos
        fetch(`ajax/obtener-detalles-prueba.php?prueba_id=${pruebaId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const detalles = data.detalles;
                    let html = `
                        <div class="detalles-prueba">
                            <h4>Detalles completos de la prueba</h4>
                            <div class="detalles-grid">
                                <div class="detalle-item">
                                    <strong>Conductor:</strong> ${detalles.conductor_nombre} ${detalles.conductor_apellido}
                                </div>
                                <div class="detalle-item">
                                    <strong>DNI:</strong> ${detalles.conductor_dni}
                                </div>
                                <div class="detalle-item">
                                    <strong>Fecha prueba:</strong> ${detalles.fecha_prueba}
                                </div>
                                <div class="detalle-item">
                                    <strong>Nivel alcohol:</strong> ${detalles.nivel_alcohol} g/L
                                </div>
                                <div class="detalle-item">
                                    <strong>Resultado:</strong> ${detalles.resultado === 'reprobado' ? 'Positivo' : 'Negativo'}
                                </div>
                                <div class="detalle-item">
                                    <strong>Observaciones:</strong> ${detalles.observaciones || 'Ninguna'}
                                </div>
                            </div>
                        </div>
                    `;
                    
                    // Mostrar modal
                    alert(html); // En una implementación real, usar un modal personalizado
                }
            });
    }
    </script>
    
    <style>
    .pruebas-table-container {
        background: white;
        border-radius: 8px;
        border: 1px solid var(--border);
        overflow: hidden;
    }
    
    .table-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem;
        background: var(--light);
        border-top: 1px solid var(--border);
    }
    
    .table-summary {
        display: flex;
        gap: 0.5rem;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .table-actions {
        display: flex;
        gap: 0.5rem;
    }
    
    .nivel-alcohol {
        font-family: monospace;
        font-weight: 600;
    }
    
    @media (max-width: 768px) {
        .table-footer {
            flex-direction: column;
            gap: 1rem;
            align-items: stretch;
        }
        
        .table-summary, .table-actions {
            justify-content: center;
        }
    }
    </style>
    
    <?php
}
?>