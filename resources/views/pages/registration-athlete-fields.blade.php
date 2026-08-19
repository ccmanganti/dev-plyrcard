@php
    $paid = (bool) ($paid ?? false);
    $sportLabels = [
        'soccer' => 'Soccer','basketball' => 'Basketball','volleyball' => 'Volleyball','football' => 'Football',
        'baseball' => 'Baseball','softball' => 'Softball','tennis' => 'Tennis','badminton' => 'Badminton',
        'table_tennis' => 'Table Tennis','track_and_field' => 'Track & Field','swimming' => 'Swimming',
        'boxing' => 'Boxing','martial_arts' => 'Martial Arts',
    ];
    $graduationYears = range((int) now()->year, (int) now()->year + 8);
@endphp

<div class="fields">
    <div class="f" id="divisionWrap">
        <span class="legend">Gender</span>
        <div class="seg" role="group" aria-label="Gender">
            <button type="button" data-value="girls" aria-pressed="false">Girls</button>
            <button type="button" data-value="boys" aria-pressed="false">Boys</button>
        </div>
        <div class="msg">Pick a gender.</div>
    </div>

    <div class="f">
        <label for="sport">Sport</label>
        <select name="sport" id="sport" required disabled>
            <option value="">Choose gender first</option>
            @foreach(array_keys($sportPositions ?? []) as $sportKey)
                <option value="{{ $sportKey }}">{{ $sportLabels[$sportKey] ?? \Illuminate\Support\Str::headline($sportKey) }}</option>
            @endforeach
        </select>
        <div class="msg">Choose a sport.</div>
    </div>

    <div class="row3">
        <div class="f"><label for="grad">Graduation year</label><select name="year" id="grad" required><option value="">Select</option>@foreach($graduationYears as $year)<option value="{{ $year }}">{{ $year }}</option>@endforeach</select><div class="msg">Select your grad year.</div></div>
        <div class="f"><label for="gpa">GPA <span class="opt">— optional</span></label><input name="gpa" id="gpa" type="text" inputmode="decimal" placeholder="4.0"></div>
        <div class="f"><label for="jersey">Jersey <span class="opt">— optional</span></label><input name="jersey_number" id="jersey" type="number" min="0" max="99" placeholder="30"></div>
    </div>

    <div class="f" id="positionWrap"><span class="legend">Position <span class="opt">— pick up to 3</span></span><div class="chips" id="pos"><span class="hint">Choose a sport first.</span></div><div class="msg">Select at least one position.</div></div>

    <div class="row">
        <div class="f"><label for="hs">High school <span class="opt">— optional</span></label><input name="high_school" id="hs" type="text" placeholder="Briar Woods HS"></div>
        <div class="f"><label for="st">State</label><select name="state" id="st" required><option value="">Select</option>@foreach(($states ?? []) as $code => $stateName)<option value="{{ $code }}">{{ $stateName }}</option>@endforeach</select><div class="msg">Select a state.</div></div>
    </div>

    @if($paid)
        @include('pages.registration-team-fields', ['paid' => true])
    @endif
</div>