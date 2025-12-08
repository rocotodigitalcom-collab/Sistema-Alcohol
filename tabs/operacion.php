<?php
// tabs/operacion.php
?>
<div class="tab-content active">
    <div class="tab-header">
        <h2><i class="fas fa-clipboard-list"></i> 1. Datos de la Operación</h2>
        <p class="text-muted">Complete los datos generales de la operación de alcoholemia.</p>
    </div>
    
    <form id="formOperacion" method="POST" class="modal-form">
        <input type="hidden" name="guardar_operacion" value="1">
        <input type="hidden" name="protocolo_id" value="<?php echo $protocolo_id; ?>">
        
        <div class="form-section">
            <h4><i class="fas fa-building"></i> Ubicación y Lugar</h4>
            <div class="form-grid">
                <div class="form-group">
                    <label for="ubicacion_id" class="form-label">Sede/Área/Unidad *</label>
                    <select id="ubicacion_id" name="ubicacion_id" class="form-control" required>
                        <option value="">Seleccionar ubicación</option>
                        <?php foreach ($ubicaciones as $ubicacion): ?>
                        <option value="<?php echo $ubicacion['id']; ?>" 
                            <?php echo ($operacion['ubicacion_id'] ?? '') == $ubicacion['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ubicacion['nombre_ubicacion']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="lugar_pruebas" class="form-label">Lugar específico de pruebas *</label>
                    <input type="text" id="lugar_pruebas" name="lugar_pruebas" class="form-control" 
                           placeholder="Ej: Puerta principal, Taller, Oficina de control, etc." 
                           required maxlength="255"
                           value="<?php echo htmlspecialchars($operacion['lugar_pruebas'] ?? ''); ?>">
                </div>
            </div>
        </div>
        
        <div class="form-section">
            <h4><i class="fas fa-calendar-alt"></i> Fecha y Horario</h4>
            <div class="form-grid">
                <div class="form-group">
                    <label for="fecha" class="form-label">Fecha de la operación *</label>
                    <input type="date" id="fecha" name="fecha" class="form-control" required
                           value="<?php echo $operacion['fecha'] ?? date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="plan_motivo" class="form-label">Plan o motivo *</label>
                    <select id="plan_motivo" name="plan_motivo" class="form-control" required>
                        <option value="">Seleccionar motivo</option>
                        <option value="diario" <?php echo ($operacion['plan_motivo'] ?? '') == 'diario' ? 'selected' : ''; ?>>Control Diario</option>
                        <option value="aleatorio" <?php echo ($operacion['plan_motivo'] ?? '') == 'aleatorio' ? 'selected' : ''; ?>>Aleatorio</option>
                        <option value="semanal" <?php echo ($operacion['plan_motivo'] ?? '') == 'semanal' ? 'selected' : ''; ?>>Semanal</option>
                        <option value="mensual" <?php echo ($operacion['plan_motivo'] ?? '') == 'mensual' ? 'selected' : ''; ?>>Mensual</option>
                        <option value="sospecha" <?php echo ($operacion['plan_motivo'] ?? '') == 'sospecha' ? 'selected' : ''; ?>>Sospecha</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="hora_inicio" class="form-label">Hora de inicio *</label>
                    <input type="time" id="hora_inicio" name="hora_inicio" class="form-control" required
                           value="<?php echo $operacion['hora_inicio'] ?? '08:00'; ?>">
                </div>
                
                <div class="form-group">
                    <label for="hora_cierre" class="form-label">Hora de cierre estimada</label>
                    <input type="time" id="hora_cierre" name="hora_cierre" class="form-control"
                           value="<?php echo $operacion['hora_cierre'] ?? '18:00'; ?>">
                </div>
            </div>
        </div>
        
        <div class="form-section">
            <h4><i class="fas fa-user-shield"></i> Responsable</h4>
            <div class="form-group">
                <div class="form-info">
                    <strong>Operador responsable:</strong>
                    <span class="text-primary"><?php echo htmlspecialchars($operacion['operador_nombre'] . ' ' . $operacion['operador_apellido']); ?></span>
                </div>
                <small class="text-muted">Este dato se asignó automáticamente al iniciar el protocolo.</small>
            </div>
        </div>
        
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <strong>Nota:</strong> Los campos marcados con * son obligatorios. 
            Estos datos serán utilizados en todo el protocolo y en los reportes finales.
        </div>
    </form>
</div>