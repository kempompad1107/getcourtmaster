<div class="row justify-content-start">
    <div class="col-12 col-lg-8 col-xl-7">
        <form method="POST" action="{{ route('admin.tournaments.settings.update', $tournament) }}">
            @csrf
            @method('PUT')
            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0 fw-semibold">Match Rules</h6></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6 col-sm-4">
                            <label class="form-label fw-medium">Points to win</label>
                            <input type="number" name="points_to_win" min="1" max="99" required
                                   value="{{ old('points_to_win', $tournament->getSetting('points_to_win')) }}"
                                   class="form-control @error('points_to_win') is-invalid @enderror">
                            @error('points_to_win')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-6 col-sm-4">
                            <label class="form-label fw-medium">Best of</label>
                            <select name="best_of" class="form-select @error('best_of') is-invalid @enderror">
                                @foreach([1, 3, 5] as $n)
                                <option value="{{ $n }}" @selected((int) old('best_of', $tournament->getSetting('best_of')) === $n)>Best of {{ $n }}</option>
                                @endforeach
                            </select>
                            @error('best_of')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12 col-sm-4 d-flex align-items-end">
                            <div class="form-check">
                                <input type="hidden" name="win_by_2" value="0">
                                <input class="form-check-input" type="checkbox" name="win_by_2" id="set-winby2" value="1"
                                       @checked(old('win_by_2', $tournament->getSetting('win_by_2')))>
                                <label class="form-check-label fw-medium" for="set-winby2">Win by 2</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0 fw-semibold">Scheduling</h6></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-medium">Default match duration (min)</label>
                            <input type="number" name="default_match_duration" min="10" max="240" required
                                   value="{{ old('default_match_duration', $tournament->getSetting('default_match_duration')) }}"
                                   class="form-control @error('default_match_duration') is-invalid @enderror">
                            @error('default_match_duration')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-medium">Court count</label>
                            <input type="number" name="court_count" min="1" max="50" required
                                   value="{{ old('court_count', $tournament->getSetting('court_count')) }}"
                                   class="form-control @error('court_count') is-invalid @enderror">
                            @error('court_count')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h6 class="mb-0 fw-semibold">Registration & Brackets</h6></div>
                <div class="card-body">
                    <div class="d-flex flex-column gap-2">
                        <div class="form-check">
                            <input type="hidden" name="auto_generate_brackets" value="0">
                            <input class="form-check-input" type="checkbox" name="auto_generate_brackets" id="set-autogen" value="1"
                                   @checked(old('auto_generate_brackets', $tournament->getSetting('auto_generate_brackets')))>
                            <label class="form-check-label fw-medium" for="set-autogen">Auto-generate brackets when registration closes</label>
                        </div>
                        <div class="form-check">
                            <input type="hidden" name="allow_late_registration" value="0">
                            <input class="form-check-input" type="checkbox" name="allow_late_registration" id="set-late" value="1"
                                   @checked(old('allow_late_registration', $tournament->getSetting('allow_late_registration')))>
                            <label class="form-check-label fw-medium" for="set-late">Allow late registration (after the deadline, while status is Registration Open)</label>
                        </div>
                        <div class="form-check">
                            <input type="hidden" name="enable_public_registration" value="0">
                            <input class="form-check-input" type="checkbox" name="enable_public_registration" id="set-public" value="1"
                                   @checked(old('enable_public_registration', $tournament->getSetting('enable_public_registration')))>
                            <label class="form-check-label fw-medium" for="set-public">Enable public registration (members self-register from the portal)</label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end">
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </div>
        </form>
    </div>
</div>
