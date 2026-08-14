@php
    $paid = (bool) ($paid ?? false);
    $athleteOnly = (bool) ($athleteOnly ?? false);
    $sportLabels = [
        'soccer' => 'Soccer',
        'basketball' => 'Basketball',
        'volleyball' => 'Volleyball',
        'football' => 'Football',
        'baseball' => 'Baseball',
        'softball' => 'Softball',
        'tennis' => 'Tennis',
        'badminton' => 'Badminton',
        'table_tennis' => 'Table Tennis',
        'track_and_field' => 'Track & Field',
        'swimming' => 'Swimming',
        'boxing' => 'Boxing',
        'martial_arts' => 'Martial Arts',
    ];
    $graduationYears = range((int) now()->year, (int) now()->year + 8);
@endphp

<div class="fields">
    @if($paid)
        <div class="divider"><span>The athlete</span></div>
    @endif

    <div class="f">
        <label for="sport">Sport</label>
        <select name="sport" id="sport" required>
            <option value="">Choose your sport</option>
            @foreach(array_keys($sportPositions ?? []) as $sportKey)
                <option value="{{ $sportKey }}">{{ $sportLabels[$sportKey] ?? \Illuminate\Support\Str::headline($sportKey) }}</option>
            @endforeach
        </select>
        <div class="msg">Choose a sport.</div>
    </div>

    <div class="f" id="divisionWrap">
        <span class="legend">Division</span>
        <div class="seg" role="group" aria-label="Division">
            <button type="button" data-value="girls" aria-pressed="false">Girls</button>
            <button type="button" data-value="boys" aria-pressed="false">Boys</button>
        </div>
        <div class="msg">Pick a division.</div>
    </div>

    @if($paid)
        <div class="row3">
            <div class="f">
                <label for="grad">Graduation year</label>
                <select name="year" id="grad" required>
                    <option value="">Select</option>
                    @foreach($graduationYears as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
                <div class="msg">Select a grad year.</div>
            </div>
            <div class="f">
                <label for="gpa">GPA <span class="opt">— optional</span></label>
                <input name="gpa" id="gpa" type="text" inputmode="decimal" placeholder="4.0">
            </div>
            <div class="f">
                <label for="jersey">Jersey <span class="opt">— optional</span></label>
                <input name="jersey_number" id="jersey" type="number" min="0" max="99" placeholder="30">
            </div>
        </div>
    @else
        <div class="row">
            <div class="f">
                <label for="grad">Graduation year</label>
                <select name="year" id="grad" required>
                    <option value="">Select</option>
                    @foreach($graduationYears as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
                <div class="msg">Select your grad year.</div>
            </div>
            <div class="f">
                <label for="gpa">GPA <span class="opt">— optional</span></label>
                <input name="gpa" id="gpa" type="text" inputmode="decimal" placeholder="4.0">
            </div>
        </div>
    @endif

    <div class="f" id="positionWrap">
        <span class="legend">Position <span class="opt">— pick up to 3</span></span>
        <div class="chips" id="pos"><span class="hint">Choose a sport first.</span></div>
        <div class="msg">Select at least one position.</div>
    </div>

    <div class="row">
        <div class="f">
            <label for="hs">High school</label>
            <input name="high_school" id="hs" type="text" placeholder="Briar Woods HS" required>
            <div class="msg">Enter the high school.</div>
        </div>
        <div class="f">
            <label for="st">State</label>
            <select name="state" id="st" required>
                <option value="">Select</option>
                @foreach(($states ?? []) as $code => $stateName)
                    <option value="{{ $code }}">{{ $stateName }}</option>
                @endforeach
            </select>
            <div class="msg">Select a state.</div>
        </div>
    </div>

    @if($paid && ! $athleteOnly)
        <div class="divider"><span>Club &amp; team</span></div>

        <div class="f">
            <label for="club">Club or team</label>
            <input name="club_name" id="club" type="text" placeholder="Virginia Development Academy" required>
            <div class="msg">Enter the club or team.</div>
        </div>

        <div class="row">
            <div class="f">
                <label for="league">League</label>
                <input name="league_name" id="league" type="text" list="registrationLeagueOptions" placeholder="ECNL" required>
                <datalist id="registrationLeagueOptions">
                    <option value="ECNL"></option>
                    <option value="ECNL Regional League"></option>
                    <option value="Girls Academy (GA)"></option>
                    <option value="MLS NEXT"></option>
                    <option value="EDP"></option>
                    <option value="NPL"></option>
                    <option value="State League"></option>
                    <option value="High school only"></option>
                </datalist>
                <div class="msg">Enter a league.</div>
            </div>
            <div class="f">
                <label for="age">Age group</label>
                <select name="age_group" id="age" required>
                    <option value="">Select</option>
                    @foreach(($ageGroups ?? []) as $ageGroup)
                        <option value="{{ $ageGroup }}">{{ $ageGroup }}</option>
                    @endforeach
                </select>
                <div class="msg">Select an age group.</div>
            </div>
        </div>

        <div class="row">
            <div class="f">
                <label for="cn">Club coach name</label>
                <input name="club_coach" id="cn" type="text" placeholder="Coach's full name" required>
                <div class="msg">Enter the coach's name.</div>
            </div>
            <div class="f">
                <label for="ce">Club coach email</label>
                <input name="club_coach_email" id="ce" type="email" placeholder="coach@club.org" required>
                <div class="msg">Enter a valid coach email.</div>
            </div>
        </div>
        <p class="hint" style="margin-top:-8px">College coaches contact your club coach for evaluations. Adding it now keeps your recruiting profile ready before they ask.</p>
    @endif
</div>
