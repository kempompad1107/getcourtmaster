{{-- Raw Alpine member fragment for the time-first picker. @include this INSIDE a
     bookingForm() object literal. The host component must also define: courtId,
     bookingDate, duration, selectedSlot, promoMessage, discount.
     Params: $timelinePath ('/app/courts' | '/admin/courts'), $staff (bool),
     $defaultStart ('HH:MM'). --}}
@php($staff = $staff ?? false)
        // ── Time-first picker state ───────────────────────────────────────────
        startTime: (() => {
            const now = new Date();
            let mins = now.getHours() * 60 + now.getMinutes();
            mins = Math.ceil(mins / 5) * 5;
            if (mins >= 24 * 60) mins = 23 * 60 + 55;
            return String(Math.floor(mins / 60)).padStart(2,'0') + ':' + String(mins % 60).padStart(2,'0');
        })(),
        customDuration: '',
        showCustom: false,
        timeline: null,
        tlLoading: false,
        verdict: null,
        checking: false,
        _checkSeq: 0,
        tlPxWidth: 0,
        _autoAdvanced: false,

        get courtOption() {
            if (!this.courtId) return null;
            return document.querySelector(`select[name="court_id"] option[value="${this.courtId}"]`);
        },
        get courtMin() { return parseInt(this.courtOption?.dataset?.min ?? 30, 10) || 30; },
        get courtMax() { return parseInt(this.courtOption?.dataset?.max ?? 240, 10) || 240; },
        get effDuration() {
            if (this.showCustom) return parseInt(this.customDuration, 10) || 0;
            return parseInt(this.duration, 10) || 0;
        },
        get selEndHm() { return this._addMin(this.startTime, this.effDuration); },

        get tlOpenMin()  { return this.timeline ? this._toMin(this.timeline.open)  : 7 * 60; },
        get tlCloseMin() { return this.timeline ? this._toMin(this.timeline.close) : 22 * 60; },
        get tlSpan()     { return Math.max(1, this.tlCloseMin - this.tlOpenMin); },

        pct(hm) { return ((this._toMin(hm) - this.tlOpenMin) / this.tlSpan) * 100; },
        clampPct(v) { return Math.max(0, Math.min(100, v)); },
        // Hour marks across the operating window, for gridlines + axis labels.
        // Every tick draws a gridline; labels are thinned to a stride so they
        // never overlap on narrow screens (the track shrinks but hours don't).
        get axisTicks() {
            if (!this.timeline) return [];
            const ticks = [];
            for (let m = Math.ceil(this.tlOpenMin / 60) * 60; m <= this.tlCloseMin; m += 60) {
                const h = Math.floor(m / 60) % 24;
                ticks.push({
                    left: this.clampPct(this.pct(this._addMin('00:00', m))),
                    label: `${h % 12 || 12}${h < 12 ? 'am' : 'pm'}`,
                });
            }
            // Thin labels to a stride so they never overlap. Use the track's
            // measured width (set by a ResizeObserver in the timeline partial);
            // before it reports, assume a narrow track so we over-thin rather
            // than overlap — the observer relaxes it once it measures wider.
            // 56px per label keeps spacing comfortable down to ~320px mobile tracks.
            // Fall back to 0 so stride is maximally conservative before ResizeObserver fires.
            const px      = this.tlPxWidth || 0;
            const maxLbls = px > 0 ? Math.max(2, Math.floor(px / 56)) : 5;

            const stride  = Math.max(1, Math.ceil(ticks.length / maxLbls));
            ticks.forEach((t, i) => { t.showLabel = (i % stride === 0); });
            return ticks;
        },
        segClass(seg) {
            return seg.color === 'yellow' ? 'bg-warning text-dark' : 'bg-danger';
        },
        durationLabel(min) {
            min = parseInt(min, 10) || 0;
            if (min === 60)  return '1 hour';
            if (min === 90)  return '1.5 hours';
            if (min === 120) return '2 hours';
            return `${min} min`;
        },
        label(hm) {
            if (!hm) return '';
            const [h, m] = hm.split(':').map(Number);
            const ap = h >= 12 ? 'PM' : 'AM';
            return `${h % 12 || 12}:${String(m).padStart(2, '0')} ${ap}`;
        },
        _toMin(hm) { const [h, m] = (hm || '0:0').split(':').map(Number); return (h || 0) * 60 + (m || 0); },
        _addMin(hm, min) {
            let t = this._toMin(hm) + (min || 0);
            t = Math.max(0, Math.min(24 * 60, t));
            return `${String(Math.floor(t / 60)).padStart(2, '0')}:${String(t % 60).padStart(2, '0')}`;
        },

        onCourtChange() { this._autoAdvanced = false; this.selectedSlot = null; this.verdict = null; this.loadTimeline(); },
        onDateChange()  { this._autoAdvanced = false; this.selectedSlot = null; this.verdict = null; this.loadTimeline(); },
        pickDuration(min) { this.showCustom = false; this.duration = String(min); this.runCheck(); },
        enableCustom() {
            this.showCustom = true;
            if (!this.customDuration) this.customDuration = String(this.duration);
            this.runCheck();
        },
        applyCustom() {
            let v = parseInt(this.customDuration, 10) || 0;
            if (v && v < this.courtMin) v = this.courtMin;
            if (v && v > this.courtMax) v = this.courtMax;
            this.customDuration = v ? String(v) : '';
            this.runCheck();
        },
        useSuggestion(s) {
            this.startTime = s.start;
            const presets = [30, 60, 90, 120];
            const dur = parseInt(s.duration, 10);
            this.showCustom = !presets.includes(dur);
            if (this.showCustom) this.customDuration = String(dur); else this.duration = String(dur);
            this.runCheck();
        },
        onTimelineClick(e, el) {
            const rect = el.getBoundingClientRect();
            const ratio = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
            const min = Math.round((this.tlOpenMin + ratio * this.tlSpan) / 5) * 5;
            this.startTime = this._addMin('00:00', min);
            this.runCheck();
        },

        async loadTimeline() {
            if (!this.courtId || !this.bookingDate) { this.timeline = null; return; }
            this.tlLoading = true;
            try {
                const url = `${window.APP_BASE}{{ $timelinePath }}/${this.courtId}/timeline?date=${this.bookingDate}`;
                const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
                this.timeline = r.ok ? await r.json() : null;
            } catch (e) { this.timeline = null; }
            finally { this.tlLoading = false; }
            this.runCheck();
        },

        async runCheck() {
            this.selectedSlot = null;
            if (!this.courtId || !this.bookingDate || !this.startTime || !this.effDuration) { this.verdict = null; return; }
            const seq = ++this._checkSeq;
            this.checking = true;
            try {
                const url = `${window.APP_BASE}{{ $timelinePath }}/${this.courtId}/check`
                          + `?date=${this.bookingDate}&start=${this.startTime}&duration=${this.effDuration}`;
                const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (seq !== this._checkSeq) return; // a newer check superseded this one
                if (!r.ok) { this.verdict = null; return; }
                const v = await r.json();
                this.verdict = v;
                // Auto-advance when today's slot is blocked only by a current booking
                // or a just-passed time. Jump to the conflict's end (or first suggestion)
                // so the form lands on the next open window automatically.
                if (!v.available && !this._autoAdvanced) {
                    const todayStr = new Date().toISOString().slice(0, 10);
                    if (this.bookingDate === todayStr) {
                        const codes = (v.reasons || []).map(r => r.code);
                        const autoSolvable = codes.length > 0 &&
                            codes.every(c => c === 'overlap_booking' || c === 'past');
                        if (autoSolvable) {
                            const next = v.conflict?.end || v.suggestions?.[0]?.start;
                            if (next) {
                                this._autoAdvanced = true;
                                this.startTime = next;
                                this.runCheck();
                                return;
                            }
                        }
                    }
                }
                if (v.available && v.pricing) {
                    this.selectedSlot = {
                        start: this.startTime,
                        end: this.selEndHm,
                        duration: this.effDuration,
                        total: v.pricing.total,
                        rate: v.pricing.rate,
                        start_label: v.pricing.start_label,
                        end_label: v.pricing.end_label,
                    };
                    this.promoMessage = ''; this.discount = 0; // range changed → drop any promo
                }
            } catch (e) {
                if (seq === this._checkSeq) this.verdict = null;
            } finally {
                if (seq === this._checkSeq) this.checking = false;
            }
        },
