@php
    $paid = (bool) ($paid ?? false);
@endphp

<div class="fields">
    <div class="f">
        <label for="club">Club or team</label>
        <input name="club_name" id="club" type="text" placeholder="Virginia Development Academy" required>
        <div class="msg">Enter your club or team.</div>
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

    <div class="f">
        <label for="jersey">Jersey number <span class="opt">— optional</span></label>
        <input name="jersey_number" id="jersey" type="number" min="0" max="99" placeholder="30">
    </div>

    <div class="row">
        <div class="f">
            <label for="cn">Club coach name <span class="opt">— optional</span></label>
            <input name="club_coach" id="cn" type="text" placeholder="Coach's full name">
        </div>
        <div class="f">
            <label for="ce">Club coach email <span class="opt">— optional</span></label>
            <input name="club_coach_email" id="ce" type="email" placeholder="coach@club.org">
        </div>
    </div>
    <div class="hint">College coaches often contact your club coach for evaluations. You can add these details now or update them later.</div>
</div>
