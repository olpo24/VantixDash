<div id="confirm-modal" class="modal">
    <div class="modal-content" style="max-width: 400px; text-align: center;">
        <div style="font-size: 3rem; margin-bottom: 1rem; color: var(--primary-color);">
            <i class="ph ph-question"></i>
        </div>
        <h3 id="confirm-title" style="margin-top:0;">Bestätigen</h3>
        <p id="confirm-message" style="color: var(--text-muted); margin-bottom: 2rem;"></p>
        <div style="display: flex; gap: 10px; justify-content: center;">
            <button id="confirm-cancel" class="btn-secondary">Abbrechen</button>
            <button id="confirm-ok" class="btn-primary">Bestätigen</button>
        </div>
    </div>
</div>
<div id="details-modal" class="modal-overlay" style="display:none;">
    <div class="modal-content card shadow-lg">
        <div class="modal-header">
            <h3 id="modal-title">Instanz-Details</h3>
            <button onclick="closeModal()" class="close-btn"><i class="ph ph-x"></i></button>
        </div>
        <div id="modal-body">
            <div class="text-center p-5">
                <i class="ph ph-circle-notch spinner"></i> Lade Details...
            </div>
        </div>
    </div>
</div>
<script src="assets/js/app.js"></script>
</body>
</html>
