{{-- Time-first booking picker: start time (any minute) + duration (quick buttons
     + custom) + visual day-timeline + live availability verdict + suggestions.
     Requires the parent Alpine component to mix in timePickerState() and to expose
     courtId / bookingDate / selectedSlot. $staff toggles block management. --}}
@php($staff = $staff ?? false)

{{-- Start time + duration --}}
<div class="row g-3 mb-3" x-show="courtId" x-cloak>
    <div class="col-sm-5">
        <label class="form-label fw-medium">Start time <span class="text-danger">*</span></label>
        <input type="time" step="60" x-model="startTime" @change="runCheck()"
               class="form-control">
    </div>
    <div class="col-sm-7">
        <label class="form-label fw-medium">Duration <span class="text-danger">*</span></label>
        <div class="d-flex flex-wrap gap-2">
            <template x-for="d in [30, 60, 90, 120]" :key="d">
                <button type="button" class="btn flex-grow-1"
                        :class="(!showCustom && effDuration === d) ? 'btn-primary' : 'btn-outline-secondary'"
                        :disabled="d < courtMin || d > courtMax"
                        @click="pickDuration(d)" x-text="durationLabel(d)"></button>
            </template>
            <button type="button" class="btn flex-grow-1"
                    :class="showCustom ? 'btn-primary' : 'btn-outline-secondary'"
                    @click="enableCustom()">Custom</button>
        </div>
        <div x-show="showCustom" x-cloak class="input-group mt-2" style="max-width:240px">
            <input type="number" class="form-control" :min="courtMin" :max="courtMax" step="5"
                   x-model="customDuration" @input.debounce.300ms="applyCustom()" placeholder="Minutes">
            <span class="input-group-text">min</span>
        </div>
        <div class="form-text" x-text="`This court allows ${courtMin}–${courtMax} minutes.`"></div>
    </div>
</div>

@include('partials.booking-timeline', ['staff' => $staff])

{{-- Checking spinner --}}
<div x-show="checking" x-cloak class="text-center py-2 mb-3">
    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
    <span class="small text-muted">Checking availability…</span>
</div>

{{-- Availability verdict --}}
<div x-show="courtId && startTime && !checking && verdict" x-cloak class="mb-3">
    <template x-if="verdict && verdict.available">
        <div class="alert alert-success py-2 mb-0 d-flex align-items-center">
            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
            <div>
                <strong>Available</strong>
                <span class="ms-1"
                      x-text="`${verdict.pricing.start_label} – ${verdict.pricing.end_label} · ₱${Number(verdict.pricing.total).toLocaleString()}`"></span>
            </div>
        </div>
    </template>

    <template x-if="verdict && !verdict.available">
        <div class="alert alert-danger py-2 mb-0">
            <div class="d-flex align-items-center mb-1">
                <i class="bi bi-x-circle-fill me-2 fs-5"></i>
                <strong>Not available</strong>
            </div>
            <ul class="mb-2 ps-4 small">
                <template x-for="(r, i) in verdict.reasons" :key="i">
                    <li x-text="r.message"></li>
                </template>
            </ul>
            <template x-if="verdict.suggestions && verdict.suggestions.length">
                <div>
                    <div class="small fw-semibold mb-1"><i class="bi bi-magic me-1"></i>Nearest available:</div>
                    <div class="d-flex flex-wrap gap-2">
                        <template x-for="(s, i) in verdict.suggestions" :key="i">
                            <button type="button" class="btn btn-sm btn-outline-success"
                                    @click="useSuggestion(s)"
                                    x-text="`${s.start_label} – ${s.end_label}`"></button>
                        </template>
                    </div>
                </div>
            </template>
            <template x-if="!verdict.suggestions || !verdict.suggestions.length">
                <div class="small text-muted">No open windows of this length on this day. Try a shorter duration or another date.</div>
            </template>
        </div>
    </template>
</div>

{{-- Hidden inputs consumed by the existing store() pipeline --}}
<input type="hidden" name="start_time" :value="selectedSlot ? selectedSlot.start : ''">
<input type="hidden" name="end_time"   :value="selectedSlot ? selectedSlot.end : ''">
