<div class="card shadow-sm border-0 mb-4 bg-light">
    <div class="card-body">
        <div class="d-flex align-items-center gap-3">
            <div class="form-check form-switch" style="transform: scale(1.1); transform-origin: left;">
                <input class="form-check-input bg-primary border-primary" type="checkbox" id="specialProcessToggle" style="cursor: pointer;">
                <label class="form-check-label fw-bold ms-1" for="specialProcessToggle" style="cursor: pointer;">
                    <i class="bi bi-funnel-fill"></i> Jadwal Proses Khusus
                </label>
            </div>
        </div>
        <div id="specialProcessOptions" class="mt-3 d-none">
            <div class="d-flex flex-wrap gap-3">
                <div class="form-check">
                    <input class="form-check-input process-radio" type="radio" name="special_process" id="processLeakTest" value="Leak Test">
                    <label class="form-check-label fw-bold" for="processLeakTest">Leak Test</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input process-radio" type="radio" name="special_process" id="processAssyBushing" value="Assy Bushing">
                    <label class="form-check-label fw-bold" for="processAssyBushing">Assy Bushing</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input process-radio" type="radio" name="special_process" id="processAssyShaft" value="Assy Shaft">
                    <label class="form-check-label fw-bold" for="processAssyShaft">Assy Shaft</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input process-radio" type="radio" name="special_process" id="processJigPlug" value="Jig Plug">
                    <label class="form-check-label fw-bold" for="processJigPlug">Jig Plug</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input process-radio" type="radio" name="special_process" id="processPainting" value="Painting">
                    <label class="form-check-label fw-bold" for="processPainting">Painting</label>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggle = document.getElementById('specialProcessToggle');
    const options = document.getElementById('specialProcessOptions');
    const radios = document.querySelectorAll('.process-radio');
    
    // Auto-check the toggle if category is set
    const urlParams = new URLSearchParams(window.location.search);
    const category = urlParams.get('category');
    
    if (category && category !== 'Machining') {
        toggle.checked = true;
        options.classList.remove('d-none');
        
        radios.forEach(r => {
            if (r.value === category) {
                r.checked = true;
            }
        });
    }

    toggle.addEventListener('change', function() {
        if (this.checked) {
            options.classList.remove('d-none');
        } else {
            // Redirect back to machining if turned off
            const dateInput = document.querySelector('input[name="date"]');
            let dateQuery = '';
            if (dateInput && dateInput.value) {
                dateQuery = '?date=' + dateInput.value;
            }
            window.location.href = '/machining/daily-schedule' + dateQuery;
        }
    });

    radios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.checked) {
                const dateInput = document.querySelector('input[name="date"]');
                let dateParam = '';
                if (dateInput && dateInput.value) {
                    dateParam = '&date=' + dateInput.value;
                }
                window.location.href = '/machining/daily-schedule?category=' + encodeURIComponent(this.value) + dateParam;
            }
        });
    });
});
</script>
