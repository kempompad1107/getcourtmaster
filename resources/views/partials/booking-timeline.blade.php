{{-- Visual court day-timeline. Driven entirely by the parent Alpine component
     (see timePickerState() mixed into each booking form). $staff toggles the
     block-management affordances (create/delete blocks, click-to-prefill). --}}
@php($staff = $staff ?? false)

<div x-show="timeline" x-cloak class="mb-3">
    <div class="d-flex justify-content-between align-items-center mb-1">
        <label class="form-label fw-medium mb-0">Court schedule</label>
        <span class="small text-muted" x-show="timeline"
              x-text="timeline ? label(timeline.open) + ' – ' + label(timeline.close) : ''"></span>
    </div>

    <template x-if="timeline && timeline.is_closed">
        <div class="alert alert-secondary py-2 small mb-2">
            <i class="bi bi-door-closed me-1"></i> The venue is closed on this day.
        </div>
    </template>

    {{-- Whole-court status (set from the Courts page): maintenance / closed --}}
    <template x-if="timeline && (timeline.court_status === 'maintenance' || timeline.court_status === 'closed')">
        <div class="alert alert-warning py-2 small mb-2">
            <i class="bi bi-cone-striped me-1"></i>
            This court is currently <span class="fw-semibold" x-text="timeline.court_status"></span> and can't be booked.
            @if($staff)<span class="text-muted">Change its status from the Courts page.</span>@endif
        </div>
    </template>

    {{-- Scrollable timeline wrapper --}}
    <div class="rounded border overflow-hidden" style="overflow-x:auto!important;-webkit-overflow-scrolling:touch;scrollbar-width:none"
         x-show="!(timeline && timeline.is_closed)">

        {{-- Track: fixed 52px per hour so labels always have room; scrolls on narrow screens --}}
        <div class="position-relative"
             :style="`width:max(100%,${tlSpan / 60 * 52}px);height:48px;cursor:pointer;background:var(--bs-tertiary-bg)`"
             @click="onTimelineClick($event, $el)">

            {{-- hour gridlines --}}
            <template x-for="(t, i) in axisTicks" :key="'g' + i">
                <div class="position-absolute top-0 h-100"
                     :style="`left:${t.left}%;width:1px;background:rgba(128,128,128,.18)`"></div>
            </template>

            <template x-for="(seg, i) in (timeline ? timeline.segments : [])" :key="i">
                <div class="position-absolute top-0 h-100 d-flex align-items-center justify-content-center text-white overflow-hidden"
                     :class="segClass(seg)"
                     :style="`left:${clampPct(pct(seg.start))}%;width:${Math.max(0.5, clampPct(pct(seg.end)) - clampPct(pct(seg.start)))}%`"
                     :title="seg.label + ' · ' + seg.start_label + ' – ' + seg.end_label">
                    <span class="text-truncate px-1" style="font-size:.62rem;line-height:1" x-text="seg.label"></span>
                </div>
            </template>

            {{-- Selected range overlay --}}
            <div x-show="startTime" x-cloak
                 class="position-absolute top-0 h-100 border border-2 rounded"
                 :class="verdict && verdict.available ? 'border-primary' : 'border-danger'"
                 :style="`left:${clampPct(pct(startTime))}%;width:${Math.max(1, clampPct(pct(selEndHm)) - clampPct(pct(startTime)))}%;background:rgba(13,110,253,.18)`"></div>

            {{-- Hour axis labels pinned inside the scrollable track --}}
            <template x-for="(t, i) in axisTicks" :key="'l' + i">
                <span class="position-absolute text-muted"
                      style="font-size:.65rem;font-weight:500;bottom:-18px;white-space:nowrap;line-height:1;pointer-events:none"
                      :style="`left:${t.left}%;transform:${i === 0 ? 'translateX(0)' : (i === axisTicks.length - 1 ? 'translateX(-100%)' : 'translateX(-50%)')}`"
                      x-text="t.label"></span>
            </template>
        </div>
    </div>
    {{-- Spacer for the labels that hang below the track --}}
    <div style="height:22px" x-show="!(timeline && timeline.is_closed)"></div>

    {{-- Closed-day message (outside scroll wrapper) --}}
    <template x-if="timeline && timeline.is_closed">
        <div class="rounded border bg-secondary-subtle d-flex align-items-center justify-content-center" style="height:48px">
            <span class="small text-muted"><i class="bi bi-door-closed me-1"></i>Venue closed this day</span>
        </div>
    </template>

    {{-- Legend --}}
    <div class="d-flex column-gap-4 row-gap-2 mt-3 small text-muted flex-wrap">
        <span class="d-inline-flex align-items-center gap-2"><span class="d-inline-block rounded bg-body-tertiary border" style="width:12px;height:12px"></span> Open</span>
        <span class="d-inline-flex align-items-center gap-2"><span class="d-inline-block rounded bg-danger" style="width:12px;height:12px"></span> Booked</span>
        <span class="d-inline-flex align-items-center gap-2"><span class="d-inline-block rounded bg-warning" style="width:12px;height:12px"></span> Pending</span>
        <span class="d-inline-flex align-items-center gap-2"><span class="d-inline-block rounded border border-primary border-2" style="width:12px;height:12px;background:rgba(13,110,253,.18)"></span> Your selection</span>
    </div>
</div>
