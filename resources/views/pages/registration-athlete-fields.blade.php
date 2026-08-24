@php
    $graduationYears = range((int) now()->year, (int) now()->year + 8);
@endphp

<div class="fields athlete-detail-fields">
    <div class="divider"><span>Other athlete details</span></div>

    <div class="row3">
        <div class="f">
            <label for="grad">Graduation year</label>
            <select name="year" id="grad" required data-suggest-placeholder="Search year">
                <option value="">Select graduation year</option>
                @foreach($graduationYears as $year)<option value="{{ $year }}">{{ $year }}</option>@endforeach
            </select>
            <div class="msg">Select your grad year.</div>
        </div>
        <div class="f"><label for="gpa">GPA <span class="opt">— optional</span></label><input name="gpa" id="gpa" type="text" inputmode="decimal" maxlength="4" placeholder="3.8"></div>
        <div class="f"><label for="jersey">Jersey <span class="opt">— optional</span></label><input name="jersey_number" id="jersey" type="text" inputmode="numeric" maxlength="2" placeholder="30"></div>
    </div>

    <div class="row">
        <div class="f"><label for="hs">High school <span class="opt">— optional</span></label><input name="high_school" id="hs" type="text" placeholder="Briar Woods High School" autocomplete="organization"></div>
        <div class="f">
            <label for="st">State <span class="opt">— optional</span></label>
            <select name="state" id="st" data-suggest-placeholder="Type or choose a state">
                <option value="">Select state</option>
                @foreach(($states ?? []) as $code => $stateName)<option value="{{ $code }}">{{ $stateName }}</option>@endforeach
            </select>
        </div>
    </div>
</div>