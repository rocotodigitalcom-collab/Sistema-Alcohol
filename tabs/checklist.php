<?php
// tabs/checklist.php
?>
<div class="tab-content active">
    <div class="tab-header">
        <h2><i class="fas fa-clipboard-check"></i> 2. Checklist Pre-Operación</h2>
        <p class="text-muted">Verifique el estado del alcoholímetro y la documentación necesaria antes de comenzar.</p>
    </div>
    
    <form id="formChecklist" method="POST" class="modal-form">
        <input type="hidden" name="guardar_checklist" value="1">
        <input type="hidden" name="protocolo_id" value="<?php echo $protocolo_id; ?>">
        
        <div class="form-section">
            <h4><i class="fas fa-vial"></i> Alcoholímetro</h4>
            
            <div class="form-grid">
                <div class="form-group">
                    <label for="alcoholimetro_id" class="form-label">Alcoholímetro utilizado *</label>
                    <select id="alcoholimetro_id" name="alcoholimetro_id" class="form-control" required>
                        <option value="">Seleccionar alcoholímetro</option>
                        <?php foreach ($alcoholimetros as $alc): ?>
                        <option value="<?php echo $alc['id']; ?>" 
                            <?php echo ($checklist['alcoholimetro_id'] ?? '') == $alc['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($alc['nombre_activo'] . ' (' . $alc['numero_serie'] . ')'); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="estado_alcoholimetro" class="form-label">Estado del alcoholímetro *</label>
                    <select id="estado_alcoholimetro" name="estado_alcoholimetro" class="form-control" required>
                        <option value="">Seleccionar estado</option>
                        <option value="conforme" <?php echo ($checklist['estado_alcoholimetro'] ?? '') == 'conforme' ? 'selected' : ''; ?>>Conforme</option>
                        <option value="no_conforme" <?php echo ($checklist['estado_alcoholimetro'] ?? '') == 'no_conforme' ? 'selected' : ''; ?>>No Conforme</option>
                    </select>
                </div>
            </div>
            
            <div class="alert <?php echo ($checklist['estado_alcoholimetro'] ?? '') == 'no_conforme' ? 'alert-danger' : 'alert-success'; ?>"
                 id="estadoAlcoholimetroAlert" style="<?php echo !isset($checklist['estado_alcoholimetro']) ? 'display: none;' : ''; ?>">
                <i class="fas fa-<?php echo ($checklist['estado_alcoholimetro'] ?? '') == 'no_conforme' ? 'exclamation-triangle' : 'check-circle'; ?>"></i>
                <span id="estadoAlcoholimetroText">
                    <?php if (isset($checklist['estado_alcoholimetro'])): ?>
                        <?php echo $checklist['estado_alcoholimetro'] == 'conforme' ? 'El alcoholímetro está en condiciones óptimas para su uso.' : '⚠️ ALERTA: El alcoholímetro no está conforme. No proceda con las pruebas.'; ?>
                    <?php endif; ?>
                </span>
            </div>
        </div>
        
        <div class="form-section">
            <h4><i class="fas fa-tools"></i> Verificaciones Técnicas</h4>
            <p class="text-muted">Marque con un check (✓) las verificaciones que están en condiciones.</p>
            
            <div class="checklist-grid">
                <div class="checklist-item">
                    <label class="checkbox-label">
                        <input type="checkbox" name="fecha_hora_actualizada" value="1" 
                               <?php echo ($checklist['fecha_hora_actualizada'] ?? 0) ? 'checked' : ''; ?>
                               onchange="actualizarChecklistCompletitud()">
                        <span class="checkbox-custom"></span>
                        <span class="checkbox-text">Fecha y hora actualizada</span>
                    </label>
                </div>
                
                <div class="checklist-item">
                    <label class="checkbox-label">
                        <input type="checkbox" name="bateria_cargada" value="1"
                               <?php echo ($checklist['bateria_cargada'] ?? 0) ? 'checked' : ''; ?>
                               onchange="actualizarChecklistCompletitud()">
                        <span class="checkbox-custom"></span>
                        <span class="checkbox-text">Batería cargada al 100%</span>
                    </label>
                </div>
                
                <div class="checklist-item">
                    <label class="checkbox-label">
                        <input type="checkbox" name="enciende_condiciones" value="1"
                               <?php echo ($checklist['enciende_condiciones'] ?? 0) ? 'checked' : ''; ?>
                               onchange="actualizarChecklistCompletitud()">
                        <span class="checkbox-custom"></span>
                        <span class="checkbox-text">Enciende en perfectas condiciones</span>
                    </label>
                </div>
                
                <div class="checklist-item">
                    <label class="checkbox-label">
                        <input type="checkbox" name="impresora_operativa" value="1"
                               <?php echo ($checklist['impresora_operativa'] ?? 0) ? 'checked' : ''; ?>
                               onchange="actualizarChecklistCompletitud()">
                        <span class="checkbox-custom"></span>
                        <span class="checkbox-text">Impresora operativa</span>
                    </label>
                </div>
                
                <div class="checklist-item">
                    <label class="checkbox-label">
                        <input type="checkbox" name="boquillas" value="1"
                               <?php echo ($checklist['boquillas'] ?? 0) ? 'checked' : ''; ?>
                               onchange="actualizarChecklistCompletitud()">
                        <span class="checkbox-custom"></span>
                        <span class="checkbox-text">Boquillas nuevas disponibles</span>
                    </label>
                </div>
            </div>
        </div>
        
        <div class="form-section">
            <h4><i class="fas fa-file-contract"></i> Documentación y Equipos</h4>
            
            <div class="checklist-grid">
                <div class="checklist-item">
                    <label class="checkbox-label">
                        <input type="checkbox" name="documentacion_disponible" value="1"
                               <?php echo ($checklist['documentacion_disponible'] ?? 0) ? 'checked' : ''; ?>
                               onchange="actualizarChecklistCompletitud()">
                        <span class="checkbox-custom"></span>
                        <span class="checkbox-text">Documentación del procedimiento</span>
                    </label>
                </div>
                
                <div class="checklist-item">
                    <label class="checkbox-label">
                        <input type="checkbox" name="huellero" value="1"
                               <?php echo ($checklist['huellero'] ?? 0) ? 'checked' : ''; ?>
                               onchange="actualizarChecklistCompletitud()">
                        <span class="checkbox-custom"></span>
                        <span class="checkbox-text">Huellero digital disponible</span>
                    </label>
                </div>
                
                <div class="checklist-item">
                    <label class="checkbox-label">
                        <input type="checkbox" name="lapicero" value="1"
                               <?php echo ($checklist['lapicero'] ?? 0) ? 'checked' : ''; ?>
                               onchange="actualizarChecklistCompletitud()">
                        <span class="checkbox-custom"></span>
                        <span class="checkbox-text">Lapiceros para firmas</span>
                    </label>
                </div>
            </div>
        </div>
        
        <div class="checklist-summary">
            <div class="summary-item">
                <span>Verificaciones completadas:</span>
                <strong id="checklistCompletadas">0/8</strong>
            </div>
            <div class="summary-item">
                <span>Estado general:</span>
                <strong id="checklistEstado" class="text-warning">Pendiente</strong>
            </div>
        </div>
        
        <div class="alert alert-warning" id="checklistAlert">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Importante:</strong> Todos los ítems deben estar verificados y el alcoholímetro debe estar "Conforme" para proceder con las pruebas.
        </div>
    </form>
</div>

<script>
function actualizarChecklistCompletitud() {
    const checkboxes = document.querySelectorAll('#formChecklist input[type="checkbox"]');
    const checked = document.querySelectorAll('#formChecklist input[type="checkbox"]:checked');
    const completadas = checked.length;
    const total = checkboxes.length;
    
    // Actualizar contador
    document.getElementById('checklistCompletadas').textContent = `${completadas}/${total}`;
    
    // Actualizar estado
    const estadoElement = document.getElementById('checklistEstado');
    if (completadas === total) {
        estadoElement.textContent = 'Completo';
        estadoElement.className = 'text-success';
    } else if (completadas > 0) {
        estadoElement.textContent = 'Parcial';
        estadoElement.className = 'text-warning';
    } else {
        estadoElement.textContent = 'Pendiente';
        estadoElement.className = 'text-warning';
    }
}

// Actualizar alerta de estado del alcoholímetro
document.getElementById('estado_alcoholimetro').addEventListener('change', function() {
    const valor = this.value;
    const alertElement = document.getElementById('estadoAlcoholimetroAlert');
    const textElement = document.getElementById('estadoAlcoholimetroText');
    
    if (valor === 'conforme') {
        alertElement.className = 'alert alert-success';
        textElement.textContent = 'El alcoholímetro está en condiciones óptimas para su uso.';
    } else if (valor === 'no_conforme') {
        alertElement.className = 'alert alert-danger';
        textElement.textContent = '⚠️ ALERTA: El alcoholímetro no está conforme. No proceda con las pruebas.';
    }
    
    alertElement.style.display = 'block';
});

// Inicializar contador al cargar
document.addEventListener('DOMContentLoaded', function() {
    actualizarChecklistCompletitud();
});
</script>

<style>
.checklist-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1rem;
    margin: 1rem 0;
}

.checklist-item {
    padding: 1rem;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: white;
    transition: var(--transition);
}

.checklist-item:hover {
    background: var(--light);
    border-color: var(--primary);
}

.checkbox-label {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    cursor: pointer;
    user-select: none;
}

.checkbox-custom {
    width: 20px;
    height: 20px;
    border: 2px solid var(--gray);
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: var(--transition);
}

.checkbox-label input:checked + .checkbox-custom {
    background: var(--primary);
    border-color: var(--primary);
}

.checkbox-label input:checked + .checkbox-custom::after {
    content: '✓';
    color: white;
    font-size: 0.8rem;
    font-weight: bold;
}

.checkbox-text {
    flex: 1;
    font-size: 0.9rem;
    color: var(--dark);
}

.checklist-summary {
    display: flex;
    justify-content: space-between;
    background: var(--light);
    padding: 1rem;
    border-radius: 8px;
    margin: 1.5rem 0;
}

.summary-item {
    text-align: center;
}

.summary-item span {
    display: block;
    font-size: 0.85rem;
    color: var(--gray);
    margin-bottom: 0.25rem;
}

.summary-item strong {
    font-size: 1.2rem;
}
</style>