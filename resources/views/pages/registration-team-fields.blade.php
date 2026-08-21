@php
    $sportLabels = [
        'soccer' => 'Soccer','basketball' => 'Basketball','volleyball' => 'Volleyball','football' => 'Football',
        'baseball' => 'Baseball','softball' => 'Softball','tennis' => 'Tennis','badminton' => 'Badminton',
        'table_tennis' => 'Table Tennis','track_and_field' => 'Track & Field','swimming' => 'Swimming',
        'boxing' => 'Boxing','martial_arts' => 'Martial Arts',
    ];
@endphp

<div class="fields team-fields">
    <div class="divider"><span>Sport, league, club &amp; team</span></div>

    <div class="row sport-team-primary">
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
            <select name="sport" id="sport" required disabled data-suggest-placeholder="Choose gender first">
                <option value="">Choose gender first</option>
                @foreach(array_keys($sportPositions ?? []) as $sportKey)
                    <option value="{{ $sportKey }}">{{ $sportLabels[$sportKey] ?? \Illuminate\Support\Str::headline($sportKey) }}</option>
                @endforeach
            </select>
            <div class="msg">Choose a sport.</div>
        </div>
    </div>

    <div class="f" id="positionWrap">
        <span class="legend">Position <span class="opt">— pick up to 3</span></span>
        <div class="chips" id="pos"><span class="hint">Choose a sport first.</span></div>
        <div class="msg">Select at least one position.</div>
    </div>

    <div class="row">
        <div class="f">
            <label for="league">League</label>
            <select id="league" required disabled data-suggest-placeholder="Choose gender and sport first">
                <option value="">Choose gender and sport first</option>
            </select>
            <input type="hidden" name="league_id" id="leagueId" value="">
            <div class="msg">Select a league.</div>
        </div>

        <div class="f">
            <label for="club">Club</label>
            <select name="club_id" id="club" required disabled data-suggest-placeholder="Choose a league first">
                <option value="">Choose a league first</option>
            </select>
            <button type="button" class="club-other-toggle" id="clubOtherToggle" disabled>
                Club not listed? Add your club
            </button>
            <div class="msg">Select a club or add your club.</div>
        </div>
    </div>

    <div class="f reveal" id="clubOtherWrap">
        <label for="clubOther">Club name</label>
        <input name="club_other" id="clubOther" type="text" placeholder="Type your club name" autocomplete="organization">
        <div class="msg">Enter your club name.</div>
    </div>

    <div class="f">
        <label for="team">Team / age group</label>
        <select name="team_name" id="team" required disabled data-suggest-placeholder="Choose a club first">
            <option value="">Choose a club first</option>
        </select>
        <div class="hint">Start typing U13, U14, U15, etc.</div>
        <div class="msg">Select your team / age group.</div>
    </div>

    <div class="divider"><span>Coach details</span></div>
    <div class="row">
        <div class="f"><label for="cn">Club coach name <span class="opt">— optional</span></label><input name="club_coach" id="cn" type="text" placeholder="Coach's full name" autocomplete="name"></div>
        <div class="f"><label for="ce">Club coach email <span class="opt">— optional</span></label><input name="club_coach_email" id="ce" type="email" placeholder="coach@club.org" autocomplete="email"></div>
    </div>
</div>