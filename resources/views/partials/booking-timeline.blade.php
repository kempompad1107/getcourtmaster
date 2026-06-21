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

    {{-- Schedule track: neutral = open/free; colored blocks = busy. Hour
         gridlines + the axis labels below make it read as a timeline. --}}
    <div class="position-relative rounded border"
         :class="(timeline && timeline.is_closed) ? 'bg-secondary-subtle' : 'bg-body-tertiary'"
         style="height:48px;cursor:pointer;overflow:hidden"
         x-init="const _d = $data;
                 $nextTick(() => { _d.tlPxWidth = $el.clientWidth });
                 new ResizeObserver(([e]) => { _d.tlPxWidth = e.contentRect.width }).observe($el)"
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
    </div>

    {{-- Hour axis labels. First/last anchor inward so they don't clip at the edges. --}}
    <div class="position-relative mt-1" style="height:16px"
         x-show="!(timeline && timeline.is_closed)">
        <template x-for="(t, i) in axisTicks" :key="'l' + i">
            <span class="position-absolute text-muted" x-show="t.showLabel"
                  style="font-size:.65rem;font-weight:500;top:0;white-space:nowrap;line-height:1"
                  :style="`left:${t.left}%;transform:${i === 0 ? 'translateX(0)' : (i === axisTicks.length - 1 ? 'translateX(-100%)' : 'translateX(-50%)')}`"
                  x-text="t.label"></span>
        </template>
    </div>

    {{-- Legend --}}
    <div class="d-flex column-gap-4 row-gap-2 mt-3 small text-muted flex-wrap">
        <span class="d-inline-flex align-items-center gap-2"><span class="d-inline-block rounded bg-body-tertiary border" style="width:12px;height:12px"></span> Open</span>
        <span class="d-inline-flex align-items-center gap-2"><span class="d-inline-block rounded bg-danger" style="width:12px;height:12px"></span> Booked</span>
        <span class="d-inline-flex align-items-center gap-2"><span class="d-inline-block rounded bg-warning" style="width:12px;height:12px"></span> Pending</span>
        <span class="d-inline-flex align-items-center gap-2"><span class="d-inline-block rounded border border-primary border-2" style="width:12px;height:12px;background:rgba(13,110,253,.18)"></span> Your selection</span>
    </div>
</div>
