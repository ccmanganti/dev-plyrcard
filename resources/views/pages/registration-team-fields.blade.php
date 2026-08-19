<div class="fields team-fields">
    <div class="divider"><span>League, club &amp; team</span></div>

    <div class="f">
        <label for="league">League</label>
        <select name="league_id" id="league" required disabled>
            <option value="">Choose gender and sport first</option>
        </select>
        <div class="msg">Select a league.</div>
    </div>

    <div class="f">
        <label for="club">Club</label>
        <select name="club_id" id="club" required disabled>
            <option value="">Choose a league first</option>
        </select>
        <button type="button" class="club-other-toggle" id="clubOtherToggle" disabled>
            Club not listed? Add your club
        </button>
        <div class="msg">Select a club or add your club.</div>
    </div>

    <div class="f reveal" id="clubOtherWrap">
        <label for="clubOther">Club name</label>
        <input name="club_other" id="clubOther" type="text" placeholder="Enter your club name">
        <div class="msg">Enter your club name.</div>
    </div>

    <div class="f">
        <label for="team">Team / age group</label>
        <select name="team_name" id="team" required disabled>
            <option value="">Choose a club first</option>
        </select>
        <div class="hint">Team options come from the configured PLYRCARD age groups.</div>
        <div class="msg">Select your team / age group.</div>
    </div>

    <div class="row">
        <div class="f"><label for="cn">Club coach name <span class="opt">— optional</span></label><input name="club_coach" id="cn" type="text" placeholder="Coach's full name"></div>
        <div class="f"><label for="ce">Club coach email <span class="opt">— optional</span></label><input name="club_coach_email" id="ce" type="email" placeholder="coach@club.org"></div>
    </div>
</div>